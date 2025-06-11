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

// ກວດສອບສິດການໃຊ້ງານ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';

if (!$is_superadmin && !$is_admin) {
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
    // ດຶງຂໍ້ມູນພະສົງ
    $stmt = $pdo->prepare("SELECT m.*, t.name as temple_name FROM monks m 
                          LEFT JOIN temples t ON m.temple_id = t.id 
                          WHERE m.id = ?");
    $stmt->execute([$monk_id]);
    $monk = $stmt->fetch();

    if (!$monk) {
        echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນພະສົງ']);
        exit;
    }

    // ກວດສອບສິດໃນການປ່ຽນສະຖານະ (admin ສາມາດປ່ຽນສະຖານະໄດ້ສະເພາະພະສົງໃນວັດຂອງຕົນເອງ)
    if ($is_admin && $monk['temple_id'] != $_SESSION['user']['temple_id']) {
        echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດປ່ຽນສະຖານະພະສົງນີ້']);
        exit;
    }

    // ປັບປຸງສະຖານະຂອງພະສົງ
    $update_stmt = $pdo->prepare("UPDATE monks SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $monk_id]);
    
    // ແປງສະຖານະເປັນພາສາລາວ
    $status_names = [
        'active' => 'ບວດຢູ່',
        'inactive' => 'ສິກແລ້ວ'
    ];
    
    $status_label = $status_names[$new_status];
    $monk_name = htmlspecialchars($monk['name']);
    
    $success_messages = [
        'active' => "ປ່ຽນສະຖານະພະສົງ $monk_name ເປັນບວດຢູ່ສຳເລັດແລ້ວ",
        'inactive' => "ປ່ຽນສະຖານະພະສົງ $monk_name ເປັນສິກແລ້ວສຳເລັດແລ້ວ"
    ];
    
    $message = $success_messages[$new_status];
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'status_label' => $status_label,
        'monk_id' => $monk_id,
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    error_log('Error in update_status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage()
    ]);
}
?>