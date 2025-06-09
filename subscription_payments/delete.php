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

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງ payment ID ຈາກ GET parameter
$payment_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$payment_id) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ດຶງຂໍ້ມູນການຊຳລະເງິນເພື່ອກວດສອບສິດແລະນຳໄຟລ໌ອອກ
$stmt = $pdo->prepare("
    SELECT sp.*, s.temple_id 
    FROM subscription_payments sp
    JOIN subscriptions s ON sp.subscription_id = s.id
    WHERE sp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ກວດສອບວ່າ admin ມີສິດເຂົ້າເຖິງຂໍ້ມູນຂອງວັດນີ້ຫຼືບໍ່
if ($is_admin && !$is_superadmin && $payment['temple_id'] != $temple_id) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນການຊຳລະເງິນນີ້";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

try {
    // ເລີ່ມຕົ້ນການ transaction ເພື່ອໃຫ້ແນ່ໃຈວ່າການລຶບຂໍ້ມູນຈະສຳເລັດທຸກຂັ້ນຕອນຫຼືບໍ່ສຳເລັດເລີຍ
    $pdo->beginTransaction();
    
    // ລຶບຂໍ້ມູນການຊຳລະເງິນ
    $delete_stmt = $pdo->prepare("DELETE FROM subscription_payments WHERE id = ?");
    $delete_stmt->execute([$payment_id]);
    
    // ລຶບໄຟລ໌ຫຼັກຖານການຊຳລະເງິນ ຖ້າມີ
    if (!empty($payment['payment_proof']) && file_exists('../' . $payment['payment_proof'])) {
        unlink('../' . $payment['payment_proof']);
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = "ລຶບຂໍ້ມູນການຊຳລະເງິນສຳເລັດແລ້ວ";
} catch (PDOException $e) {
    // ຖ້າມີຂໍ້ຜິດພາດໃດໆ, ຍົກເລີກການປ່ຽນແປງທັງໝົດ
    $pdo->rollBack();
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
}

// ກັບໄປທີ່ໜ້າລາຍການການຊຳລະເງິນ
header('Location: ' . $base_url . 'subscription_payments/');
exit;
?>