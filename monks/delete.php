<?php
// filepath: c:\xampp\htdocs\temples\monks\delete.php
ob_start(); // เพิ่ม output buffering
session_start(); // เพิ่มบรรทัดนี้เพื่อเริ่มต้น session

require_once '../config/db.php';
require_once '../config/base_url.php';

// เพิ่มการตรวจสอบว่า session ยังคงอยู่หรือไม่
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบค่า ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// ดึงข้อมูลพระสงฆ์
$stmt = $pdo->prepare("SELECT * FROM monks WHERE id = ?");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบสิทธิ์ในการลบข้อมูล (แก้ไขให้สมบูรณ์)
$can_delete = $_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $monk['temple_id']);

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນພະສົງນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

try {
    // ลบรูปภาพถ้าไม่ใช่รูปภาพเริ่มต้น
    if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png' && file_exists('../' . $monk['photo'])) {
        unlink('../' . $monk['photo']);
    }
    
    // ลบข้อมูลจากฐานข้อมูล
    $stmt = $pdo->prepare("DELETE FROM monks WHERE id = ?");
    $stmt->execute([$monk_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "ລຶບຂໍ້ມູນພະສົງ " . $monk['name'] . " ສຳເລັດແລ້ວ";
    } else {
        $_SESSION['error'] = "ບໍ່ສາມາດລຶບຂໍ້ມູນພະສົງໄດ້";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
}

// กลับไปที่หน้ารายการพระสงฆ์
header('Location: ' . $base_url . 'monks/');
exit;

// สิ้นสุด buffer
ob_end_flush();
?>