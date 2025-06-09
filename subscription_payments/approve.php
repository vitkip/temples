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
$user_id = $_SESSION['user']['id'] ?? null;

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

// ດຶງຂໍ້ມູນການຊຳລະເງິນ
$stmt = $pdo->prepare("
    SELECT sp.*, s.id as subscription_id, s.temple_id, s.status as subscription_status, s.end_date
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
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດອະນຸມັດການຊຳລະເງິນນີ້";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ກວດສອບວ່າການຊຳລະເງິນຍັງລໍຖ້າການຢືນຢັນຢູ່ຫຼືບໍ່
if ($payment['status'] !== 'pending') {
    $_SESSION['error'] = "ການຊຳລະເງິນນີ້ໄດ້ຖືກດຳເນີນການແລ້ວ (" . ($payment['status'] === 'approved' ? 'ອະນຸມັດແລ້ວ' : 'ປະຕິເສດແລ້ວ') . ")";
    header('Location: ' . $base_url . 'subscription_payments/view.php?id=' . $payment_id);
    exit;
}

try {
    // ເລີ່ມຕົ້ນການ transaction
    $pdo->beginTransaction();
    
    // ອັບເດດສະຖານະການຊຳລະເງິນເປັນ "approved"
    $update_payment = $pdo->prepare("
        UPDATE subscription_payments 
        SET status = 'approved', processed_by = ?, processed_at = NOW() 
        WHERE id = ?
    ");
    $update_payment->execute([$user_id, $payment_id]);
    
    // ອັບເດດສະຖານະການສະໝັກສະມາຊິກເປັນ "active" ຖ້າເປັນ "pending"
    if ($payment['subscription_status'] === 'pending') {
        $update_sub = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'active', updated_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $update_sub->execute([$payment['subscription_id']]);
    }
    
    // ຖ້າການສະໝັກສະມາຊິກໝົດອາຍຸແລ້ວ, ໃຫ້ຕໍ່ອາຍຸມັນ
    if ($payment['subscription_status'] === 'expired') {
        // ດຶງຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ
        $plan_stmt = $pdo->prepare("
            SELECT p.duration_months 
            FROM subscriptions s
            JOIN subscription_plans p ON s.plan_id = p.id
            WHERE s.id = ?
        ");
        $plan_stmt->execute([$payment['subscription_id']]);
        $plan = $plan_stmt->fetch();
        
        if ($plan) {
            // ຄຳນວນວັນທີສິ້ນສຸດໃໝ່ ໂດຍເພີ່ມຈຳນວນເດືອນຂອງແຜນຈາກວັນທີປະຈຸບັນ
            $new_end_date = date('Y-m-d', strtotime('+' . $plan['duration_months'] . ' months'));
            
            // ອັບເດດສະຖານະແລະວັນທີສິ້ນສຸດ
            $update_sub = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'active', end_date = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update_sub->execute([$new_end_date, $payment['subscription_id']]);
        }
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = "ອະນຸມັດການຊຳລະເງິນສຳເລັດແລ້ວ";
} catch (PDOException $e) {
    // ຖ້າມີຂໍ້ຜິດພາດໃດໆ, ຍົກເລີກການປ່ຽນແປງທັງໝົດ
    $pdo->rollBack();
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການອະນຸມັດການຊຳລະເງິນ: " . $e->getMessage();
}

// ກັບໄປທີ່ໜ້າລາຍລະອຽດການຊຳລະເງິນ
header('Location: ' . $base_url . 'subscription_payments/view.php?id=' . $payment_id);
exit;
?>