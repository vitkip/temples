<?php
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

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ (ສະເພາະ superadmin ເທົ່ານັ້ນທີ່ສາມາດປ່ຽນສະຖານະແຜນການສະໝັກໄດ້)
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
if (!$is_superadmin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງຂໍ້ມູນຈາກ URL parameters
$plan_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$new_status = isset($_GET['status']) ? $_GET['status'] : null;

// ກວດສອບຄວາມຖືກຕ້ອງຂອງຂໍ້ມູນ
if (!$plan_id) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງແຜນການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

if ($new_status !== 'active' && $new_status !== 'inactive') {
    $_SESSION['error'] = "ສະຖານະບໍ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ດຶງຂໍ້ມູນແຜນການສະໝັກສະມາຊິກເພື່ອກວດສອບວ່າມີຢູ່ແທ້ຫຼືບໍ່
$stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

try {
    // ອັບເດດສະຖານະ
    $update_stmt = $pdo->prepare("UPDATE subscription_plans SET status = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$new_status, $plan_id]);
    
    // ກວດສອບວ່າມີການອັບເດດຈຳນວນແຖວຫຼືບໍ່
    if ($update_stmt->rowCount() > 0) {
        $status_text = $new_status === 'active' ? 'ເປີດໃຊ້ງານ' : 'ປິດໃຊ້ງານ';
        $_SESSION['success'] = "ປ່ຽນສະຖານະແຜນການສະໝັກສະມາຊິກເປັນ " . $status_text . " ສຳເລັດແລ້ວ";
    } else {
        $_SESSION['warning'] = "ບໍ່ມີການປ່ຽນແປງສະຖານະເກີດຂຶ້ນ";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການປ່ຽນສະຖານະ: " . $e->getMessage();
}

// ຢ້ອນກັບໄປຍັງໜ້າລາຍການແຜນການສະໝັກສະມາຊິກ
if (isset($_SERVER['HTTP_REFERER']) && 
    strpos($_SERVER['HTTP_REFERER'], $base_url . 'subscription_plans') !== false) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    header('Location: ' . $base_url . 'subscription_plans/');
}
exit;
?>