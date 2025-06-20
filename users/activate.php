<?php
// filepath: c:\xampp\htdocs\temples\users\activate.php
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
$is_province_admin = $_SESSION['user']['role'] === 'province_admin'; // เพิ่มผู้ดูแลระดับแขวง

// อนุญาตให้ผู้ดูแลระดับแขวงใช้งานหน้านี้ได้
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

// ກວດສອບສິດໃນການເປີດໃຊ້ງານຜູ້ໃຊ້
$has_permission = false;

if ($is_superadmin) {
    // superadmin ສາມາດເປີດໃຊ້ງານຜູ້ໃຊ້ໄດ້ທຸກກໍລະນີ
    $has_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດເປີດໃຊ້ງານຜູ້ໃຊ້ໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ
    $has_permission = true;
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // province_admin ສາມາດເປີດໃຊ້ງານຜູ້ໃຊ້ໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
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

// ຖ້າບໍ່ມີສິດເປີດໃຊ້ງານຜູ້ໃຊ້
if (!$has_permission) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເປີດໃຊ້ງານຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ province_admin ເປີດໃຊ້ງານ admin ຫຼື superadmin
if ($is_province_admin && ($user['role'] == 'admin' || $user['role'] == 'superadmin')) {
    $_SESSION['error'] = "ທ່ານບໍ່ສາມາດເປີດໃຊ້ງານຜູ້ດູແລລະບົບໄດ້";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ admin ເປີດໃຊ້ງານ superadmin
if ($is_admin && $user['role'] == 'superadmin') {
    $_SESSION['error'] = "ທ່ານບໍ່ສາມາດເປີດໃຊ້ງານຜູ້ດູແລລະບົບສູງສຸດໄດ້";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

try {
    // ເລີ່ມ transaction
    $pdo->beginTransaction();
    
    // ດຳເນີນການເປີດໃຊ້ງານຜູ້ໃຊ້
    $update_stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$user_id]);
    
    // ບັນທຶກປະຫວັດການເປີດໃຊ້ງານຜູ້ໃຊ້
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO user_status_log (user_id, changed_by, old_status, new_status, province_id, note, changed_at)
            VALUES (?, ?, ?, 'active', ?, 'Activated by admin/province_admin', NOW())
        ");
        
        // ເກັບຂໍ້ມູນແຂວງກໍລະນີເປີດໃຊ້ງານໂດຍ province_admin
        $province_id = null;
        if ($is_province_admin && !empty($user['province_id'])) {
            $province_id = $user['province_id'];
        }
        
        $log_stmt->execute([
            $user_id,
            $_SESSION['user']['id'],
            $user['status'],
            $province_id
        ]);
    } catch (PDOException $e) {
        // ຖ້າບໍ່ມີຕາຕະລາງ log ຈະບໍ່ສົ່ງຄ່າຜິດພາດ ແຕ່ຍັງສາມາດເປີດໃຊ້ງານຜູໃຊ້ໄດ້
        error_log('Could not log user activation: ' . $e->getMessage());
    }
    
    // ຢືນຢັນ transaction
    $pdo->commit();
    
    $_SESSION['success'] = "ເປີດໃຊ້ງານຜູໃຊ້ " . htmlspecialchars($user['name'] ?? $user['username']) . " ສຳເລັດແລ້ວ";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
    
} catch (PDOException $e) {
    // ຍົກເລີກ transaction ກໍລະນີເກີດຂໍ້ຜິດພາດ
    $pdo->rollBack();
    
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}
?>