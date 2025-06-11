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

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// กระบวนการเปิดใช้งานผู้ใช้ (คล้ายกับ approve.php)
$user_id = (int)$_GET['id'];

// ดึงข้อมูลผู้ใช้และตรวจสอบสิทธิ์เช่นเดียวกับ approve.php
// ...

// อัปเดตสถานะเป็น active
$update_stmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
$update_stmt->execute([$user_id]);

$_SESSION['success'] = "ເປີດໃຊ້ງານຜູ້ໃຊ້ສຳເລັດແລ້ວ";
header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
exit;