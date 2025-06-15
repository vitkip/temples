<?php
// filepath: c:\xampp\htdocs\temples\temples\direct-update-status.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'superadmin' && $_SESSION['user']['role'] !== 'admin')) {
    $_SESSION['error'] = 'ທ່ານບໍ່ມີສິດໃນການອັບເດດສະຖານະ';
    header('Location: ' . $base_url . 'temples/index.php');
    exit;
}

// ตรวจสอบข้อมูลที่ส่งมา
if (!isset($_POST['temple_id']) || !isset($_POST['status'])) {
    $_SESSION['error'] = 'ຂໍ້ມູນບໍ່ຄົບຖ້ວນ';
    header('Location: ' . $base_url . 'temples/index.php');
    exit;
}

$temple_id = $_POST['temple_id'];
$status = $_POST['status'];
$redirect = $_POST['redirect'] ?? $base_url . 'temples/index.php';

// ตรวจสอบความถูกต้องของสถานะ
if (!in_array($status, ['active', 'inactive'])) {
    $_SESSION['error'] = 'ສະຖານະບໍ່ຖືກຕ້ອງ';
    header('Location: ' . $redirect);
    exit;
}

try {
    // อัปเดตสถานะในฐานข้อมูล
    $stmt = $pdo->prepare("UPDATE temples SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $temple_id]);
    
    if ($result) {
        // บันทึก log ลงในไฟล์แทน
        $action = $status === 'active' ? 'ເປີດໃຊ້ງານ' : 'ປິດໃຊ້ງານ';
        $user_id = $_SESSION['user']['id'] ?? 'unknown';
        $log_message = "User ID: $user_id, Action: $action, Temple ID: $temple_id, Status: $status, Time: " . date('Y-m-d H:i:s');
        error_log($log_message);
        
        $_SESSION['success'] = 'ອັບເດດສະຖານະສຳເລັດແລ້ວ';
    } else {
        $_SESSION['error'] = 'ບໍ່ສາມາດອັບເດດສະຖານະໄດ້';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'ເກີດຂໍ້ຜິດພາດໃນຖານຂໍ້ມູນ: ' . $e->getMessage();
}

// ย้อนกลับไปยังหน้าก่อนหน้า
header('Location: ' . $redirect);
exit;