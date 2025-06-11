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

if (!$is_superadmin && !$is_admin) {
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

// ດຶງຂໍ້ມູນຜູ້ໃຊ້
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບສິດໃນການອະນຸມັດ (admin ສາມາດອະນຸມັດໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ)
if ($is_admin && $user['temple_id'] != $_SESSION['user']['temple_id']) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດອະນຸມັດຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບວ່າຜູ້ໃຊ້ຢູ່ໃນສະຖານະລໍຖ້າອະນຸມັດຫຼືບໍ່
if ($user['status'] !== 'pending') {
    $_SESSION['error'] = "ຜູ້ໃຊ້ນີ້ບໍ່ຢູ່ໃນສະຖານະລໍຖ້າອະນຸມັດ";
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
    
    // ສົ່ງອີເມວແຈ້ງເຕືອນຜູ້ໃຊ້ວ່າໄດ້ຮັບການອະນຸມັດແລ້ວ (ຖ້າຕ້ອງການ)
    // ໃສ່ໂຄດສົ່ງອີເມວຢູ່ນີ້...
    
    $_SESSION['success'] = "ອະນຸມັດຜູ້ໃຊ້ " . htmlspecialchars($user['name']) . " ສຳເລັດແລ້ວ";
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
    header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
    exit;
}