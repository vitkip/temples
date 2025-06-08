<?php
// filepath: c:\xampp\htdocs\temples\events\delete.php
ob_start(); // เพิ่ม output buffering
session_start(); // เริ่มต้น session

require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງກິດຈະກໍ່າ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນກິດຈະກໍ່າ
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນກິດຈະກໍ່າ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ກວດສອບສິດໃນການລຶບຂໍ້ມູນ
$can_delete = $_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id']);

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນກິດຈະກໍ່ານີ້";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// เริ่มธุรกรรม (transaction) เพื่อให้การลบข้อมูลมีความสมบูรณ์
$pdo->beginTransaction();

try {
    // 1. ລຶບຂໍ້ມູນພະສົງທີ່ເຂົ້າຮ່ວມກິດຈະກໍ່ານີ້ຈາກຕາຕະລາງ event_monk ກ່ອນ
    $stmt = $pdo->prepare("DELETE FROM event_monk WHERE event_id = ?");
    $stmt->execute([$event_id]);
    
    // 2. ລຶບຂໍ້ມູນກິດຈະກໍ່າຈາກຕາຕະລາງ events
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    
    // ຖ້າການລຶບສຳເລັດ ໃຫ້ຢືນຢັນການທຳທຸລະກຳ
    $pdo->commit();
    
    $_SESSION['success'] = "ລຶບຂໍ້ມູນກິດຈະກໍ່າ \"" . $event['title'] . "\" ສຳເລັດແລ້ວ";
} catch (PDOException $e) {
    // ຖ້າເກີດຂໍ້ຜິດພາດ ໃຫ້ຍົກເລີກການທຳທຸລະກຳທັງໝົດ
    $pdo->rollBack();
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
    
    // บันทึกข้อผิดพลาดลงในไฟล์ log (ถ้าต้องการ)
    error_log("Database error in events/delete.php: " . $e->getMessage());
}

// ກັບໄປທີ່ໜ້າລາຍການກິດຈະກໍ່າ
header('Location: ' . $base_url . 'events/');
exit;

// ສິ້ນສຸດການ buffer
ob_end_flush();
?>