<?php
// filepath: c:\xampp\htdocs\temples\users\change_status.php
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
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';

// ອະນຸຍາດໃຫ້ໃຊ້ງານສະເພາະ superadmin, admin, ແລະ province_admin
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້']);
    exit;
}

// ຮັບຂໍ້ມູນ
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id']) || !isset($data['status']) || !is_numeric($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຖືກຕ້ອງ']);
    exit;
}

$user_id = (int)$data['user_id'];
$new_status = $data['status'];

// ກວດສອບສະຖານະ
$allowed_statuses = ['active', 'pending', 'inactive'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'ສະຖານະບໍ່ຖືກຕ້ອງ']);
    exit;
}

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ພ້ອມຂໍ້ມູນວັດແລະແຂວງ
$stmt = $pdo->prepare("
    SELECT u.*, t.id as temple_id, t.name as temple_name, t.province_id 
    FROM users u 
    LEFT JOIN temples t ON u.temple_id = t.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້']);
    exit;
}

// ກວດສອບສິດໃນການປ່ຽນສະຖານະ
$has_permission = false;

if ($is_superadmin) {
    // superadmin ສາມາດປ່ຽນສະຖານະໄດ້ທຸກກໍລະນີ
    $has_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດປ່ຽນສະຖານະໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ
    $has_permission = true;
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // ຜູ້ດູແລລະດັບແຂວງສາມາດປ່ຽນສະຖານະຜູ້ໃຊ້ທີ່ຢູ່ໃນວັດຂອງແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_province_access 
        WHERE user_id = ? AND province_id = ?
    ");
    $province_stmt->execute([$_SESSION['user']['id'], $user['province_id']]);
    
    if ($province_stmt->fetchColumn() > 0) {
        $has_permission = true;
    }
}

if (!$has_permission) {
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດປ່ຽນສະຖານະຜູ້ໃຊ້ນີ້']);
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ປ່ຽນສະຖານະຕົນເອງ
if ($_SESSION['user']['id'] == $user_id) {
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ສາມາດປ່ຽນສະຖານະຂອງຕົນເອງໄດ້']);
    exit;
}

try {
    // ດຳເນີນການອັບເດດສະຖານະຜູ້ໃຊ້
    $update_stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $user_id]);
    
    // ບັນທຶກປະຫວັດການປ່ຽນສະຖານະ
    $log_stmt = $pdo->prepare("
        INSERT INTO user_status_log (user_id, changed_by, old_status, new_status, province_id, changed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    // ເກັບຂໍ້ມູນແຂວງກໍລະນີປ່ຽນໂດຍ province_admin
    $province_id = null;
    if ($is_province_admin && !empty($user['province_id'])) {
        $province_id = $user['province_id'];
    }
    
    // ບັນທຶກລົງ log (ຖ້າມີຕາຕະລາງ)
    try {
        $log_stmt->execute([
            $user_id,
            $_SESSION['user']['id'],
            $user['status'],
            $new_status,
            $province_id
        ]);
    } catch (PDOException $e) {
        // ຖ້າບໍ່ມີຕາຕະລາງ log ຈະບໍ່ສົ່ງຄ່າຜິດພາດ ແຕ່ຍັງສາມາດປ່ຽນສະຖານະໄດ້
        error_log('Could not log status change: ' . $e->getMessage());
    }
    
    // ແປງສະຖານະເປັນພາສາລາວ
    $status_names = [
        'active' => 'ໃຊ້ງານໄດ້',
        'pending' => 'ລໍຖ້າອະນຸມັດ',
        'inactive' => 'ປິດໃຊ້ງານ'
    ];
    
    $status_label = $status_names[$new_status];
    $user_name = htmlspecialchars($user['name'] ?? $user['username']);
    
    $success_messages = [
        'active' => "ອະນຸມັດຜູ້ໃຊ້ $user_name ສຳເລັດແລ້ວ",
        'pending' => "ປັບສະຖານະຜູ້ໃຊ້ $user_name ເປັນລໍຖ້າອະນຸມັດສຳເລັດແລ້ວ",
        'inactive' => "ປິດໃຊ້ງານຜູ້ໃຊ້ $user_name ສຳເລັດແລ້ວ"
    ];
    
    $message = $success_messages[$new_status];
    
    // ສົ່ງຄືນຂໍ້ມູນສະຖານະ
    $status_colors = [
        'active' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'inactive' => 'bg-red-100 text-red-800'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'status_label' => $status_label,
        'status_class' => $status_colors[$new_status]
    ]);
    
} catch (PDOException $e) {
    // ເກັບຂໍ້ຜິດພາດໄວ້ໃນ log ເພື່ອກວດສອບ
    error_log('Database error in change_status.php: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage()
    ]);
}
?>