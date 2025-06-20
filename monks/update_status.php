<?php
// filepath: c:\xampp\htdocs\temples\monks\update_status.php
header('Content-Type: application/json');
require_once '../config/db.php';
require_once '../config/base_url.php';
session_start();

// ກວດສອບ AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ກວດສອບສິດການໃຊ້ງານ - เพิ่ม province_admin
if (!in_array($user_role, ['superadmin', 'admin', 'province_admin'])) {
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້']);
    exit;
}

// ຮັບຂໍ້ມູນ
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['monk_id']) || !isset($data['status']) || !is_numeric($data['monk_id'])) {
    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ']);
    exit;
}

$monk_id = (int)$data['monk_id'];
$new_status = $data['status'];

// ກວດສອບສະຖານະ
$allowed_statuses = ['active', 'inactive'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'ສະຖານະບໍ່ຖືກຕ້ອງ']);
    exit;
}

try {
    // ດຶງຂໍ້ມູນພະສົງພ້ອມຂໍ້ມູນວັດ, ເມືອງ, ແຂວງ
    $stmt = $pdo->prepare("
        SELECT 
            m.*, 
            t.name as temple_name,
            t.province_id,
            d.district_name,
            p.province_name
        FROM monks m 
        LEFT JOIN temples t ON m.temple_id = t.id 
        LEFT JOIN districts d ON t.district_id = d.district_id
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE m.id = ?
    ");
    $stmt->execute([$monk_id]);
    $monk = $stmt->fetch();

    if (!$monk) {
        echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນພະສົງ']);
        exit;
    }

    // ກວດສອບສິດໃນການປ່ຽນສະຖານະ
    $can_edit = false;
    
    if ($user_role === 'superadmin') {
        $can_edit = true;
    } elseif ($user_role === 'admin' && $monk['temple_id'] == $user_temple_id) {
        $can_edit = true;
    } elseif ($user_role === 'province_admin') {
        // ตรวจสอบว่าพระสงฆ์อยู่ในวัดที่อยู่ในแขวงที่ province_admin ดูแลหรือไม่
        $check_access = $pdo->prepare("
            SELECT COUNT(*) FROM temples t
            JOIN user_province_access upa ON t.province_id = upa.province_id
            WHERE t.id = ? AND upa.user_id = ?
        ");
        $check_access->execute([$monk['temple_id'], $user_id]);
        $can_edit = ($check_access->fetchColumn() > 0);
    }

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດປ່ຽນສະຖານະພະສົງນີ້']);
        exit;
    }

    // ປັບປຸງສະຖານະຂອງພະສົງ
    $update_stmt = $pdo->prepare("UPDATE monks SET status = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$new_status, $monk_id]);
    
    // ແປງສະຖານະເປັນພາສາລາວ
    $status_names = [
        'active' => 'ບວດຢູ່',
        'inactive' => 'ສິກແລ້ວ'
    ];
    
    $status_label = $status_names[$new_status];
    $monk_name = htmlspecialchars($monk['prefix'] . ' ' . $monk['name']);
    
    $success_messages = [
        'active' => "ປ່ຽນສະຖານະພະສົງ $monk_name ເປັນບວດຢູ່ສຳເລັດແລ້ວ",
        'inactive' => "ປ່ຽນສະຖານະພະສົງ $monk_name ເປັນສິກແລ້ວສຳເລັດແລ້ວ"
    ];
    
    $message = $success_messages[$new_status];
    
    // Log activity (ถ้ามีระบบ log)
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, target_type, target_id, description, created_at) 
            VALUES (?, 'update_status', 'monk', ?, ?, NOW())
        ");
        $log_stmt->execute([
            $user_id,
            $monk_id,
            "เปลี่ยนสถานะพระสงฆ์ {$monk_name} เป็น {$status_label}"
        ]);
    } catch (PDOException $e) {
        // ไม่ให้ log error ขัดขวางการทำงานหลัก
        error_log('Log error in update_status.php: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'status_label' => $status_label,
        'monk_id' => $monk_id,
        'new_status' => $new_status,
        'monk_name' => $monk_name
    ]);
    
} catch (PDOException $e) {
    error_log('Error in update_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => "ເກີດຂໍ້ຜິດພາດໃນການປັບປຸງຂໍ້ມູນ"
    ]);
}
?>