<?php
// filepath: c:\xampp\htdocs\temples\users\approve.php
ob_start();
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດການໃຊ້ງານ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';

// ອະນຸຍາດໃຫ້ໃຊ້ໜ້ານີ້ສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກວດສອບ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ມີຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ພ້ອມຂໍ້ມູນວັດແລະແຂວງ
$stmt = $pdo->prepare("
    SELECT u.*, t.name as temple_name, t.province_id 
    FROM users u 
    LEFT JOIN temples t ON u.temple_id = t.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບສິດໃນການອະນຸມັດ
$has_permission = false;

if ($is_superadmin) {
    // superadmin ສາມາດອະນຸມັດໄດ້ທຸກກໍລະນີ
    $has_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດອະນຸມັດໄດ້ສະເພາະຜູ່ໃຊ້ໃນວັດຂອງຕົນເອງ
    $has_permission = true;
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // province_admin ສາມາດອະນຸມັດໄດ້ສະເພາະຜູໃຊ້ໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
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

// ຖ້າບໍ່ມີສິດອະນຸມັດ
if (!$has_permission) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດອະນຸມັດຜູໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບວ່າຜູ້ໃຊ້ຢູ່ໃນສະຖານະລໍຖ້າອະນຸມັດຫຼືບໍ່
if ($user['status'] !== 'pending') {
    $_SESSION['error'] = "ຜູ່ໃຊ້ນີ້ບໍ່ຢູ່ໃນສະຖານະລໍຖ້າອະນຸມັດ";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

// ດໍາເນີນການອະນຸມັດຜູ້ໃຊ້
try {
    $update_stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$user_id]);
    
    // ບັນທຶກປະຫວັດການອະນຸມັດ (ຖ້າມີຕາຕະລາງ audit_log)
    /*
    $log_stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, target_id, target_type, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $log_stmt->execute([
        $_SESSION['user']['id'],
        'approve',
        $user_id,
        'user',
        'ອະນຸມັດຜູ້ໃຊ້: ' . $user['username']
    ]);
    */
    
    // ບັນທຶກຂໍ້ມູນລະດັບແຂວງ (ຖ້າເປັນ province_admin ແລະ ອະນຸມັດໂດຍ province_admin)
    if ($is_province_admin) {
        // ບັນທຶກວ່າຜູ້ໃຊ້ນີ້ຖືກອະນຸມັດໂດຍຜູ້ດູແລແຂວງໃດ
        $approval_stmt = $pdo->prepare("
            INSERT INTO user_approval_log (user_id, approved_by, province_id, approved_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        // ໃສ່ລະຫັດແຂວງທີ່ຜູໃຊ້ນີ້ສັງກັດ
        $approval_stmt->execute([
            $user_id,
            $_SESSION['user']['id'],
            $user['province_id']
        ]);
    }
    
    // ສົ່ງອີເມວແຈ້ງເຕືອນຜູໃຊ້ວ່າໄດ້ຮັບການອະນຸມັດແລ້ວ (ຖ້າຕ້ອງການ)
    // ໃສ່ໂຄດສົ່ງອີເມວຢູ່ນີ້...
    
    $_SESSION['success'] = "ອະນຸມັດຜູ້ໃຊ້ " . htmlspecialchars($user['name']) . " ສຳເລັດແລ້ວ";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}