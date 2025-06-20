<?php
// filepath: c:\xampp\htdocs\temples\users\suspend.php
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

// ອະນຸຍາດໃຫ້ໃຊ້ງານສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
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

// ກວດສອບສິດໃນການລະງັບຜູ້ໃຊ້
$has_permission = false;

if ($is_superadmin) {
    // superadmin ສາມາດລະງັບຜູ້ໃຊ້ໄດ້ທຸກກໍລະນີ
    $has_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດລະງັບຜູ້ໃຊ້ໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງເທົ່ານັ້ນ
    $has_permission = true;
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // province_admin ສາມາດລະງັບຜູ້ໃຊ້ໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
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

// ຖ້າບໍ່ມີສິດລະງັບຜູ້ໃຊ້
if (!$has_permission) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລະງັບຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ລະງັບໂຕເອງ
if ($user_id == $_SESSION['user']['id']) {
    $_SESSION['error'] = "ບໍ່ສາມາດລະງັບຜູ້ໃຊ້ປັດຈຸບັນໄດ້";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ລະງັບ superadmin ຍົກເວັ້ນແຕ່ຜູ້ລະງັບເປັນ superadmin ເຊັ່ນກັນ
if ($user['role'] == 'superadmin' && !$is_superadmin) {
    $_SESSION['error'] = "ທ່ານບໍ່ສາມາດລະງັບຜູ້ດູແລລະບົບສູງສຸດໄດ້";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

// ບໍ່ອະນຸຍາດໃຫ້ admin ລະງັບ province_admin
if ($user['role'] == 'province_admin' && $is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ສາມາດລະງັບຜູ້ດູແລລະດັບແຂວງໄດ້";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}

try {
    // ເລີ່ມ transaction
    $pdo->beginTransaction();
    
    // ດຳເນີນການລະງັບຜູ້ໃຊ້
    $update_stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$user_id]);
    
    // ບັນທຶກປະຫວັດການລະງັບຜູ້ໃຊ້
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO user_status_log (user_id, changed_by, old_status, new_status, province_id, note, changed_at)
            VALUES (?, ?, ?, 'inactive', ?, 'Suspended by admin/province_admin', NOW())
        ");
        
        // ເກັບຂໍ້ມູນແຂວງກໍລະນີລະງັບໂດຍ province_admin
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
        // ຖ້າບໍ່ມີຕາຕະລາງ log ຈະບໍ່ສົ່ງຄ່າຜິດພາດ ແຕ່ຍັງສາມາດລະງັບຜູ້ໃຊ້ໄດ້
        error_log('Could not log user suspension: ' . $e->getMessage());
    }
    
    // ຢືນຢັນ transaction
    $pdo->commit();
    
    $_SESSION['success'] = "ລະງັບການໃຊ້ງານຜູ້ໃຊ້ " . htmlspecialchars($user['name']) . " ສຳເລັດແລ້ວ";
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