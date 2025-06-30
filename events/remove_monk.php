<?php
// filepath: c:\xampp\htdocs\temples\events\remove_monk.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'auth/');
    exit;
}

// ກວດສອບວ່າມີ ID ແລະ event_id ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['error'] = "ຂໍ້ມູນບໍ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_monk_id = (int)$_GET['id'];
$event_id = (int)$_GET['event_id'];

// ດຶງຂໍ້ມູນກິດຈະກໍາ ແລະ ພະສົງ
$stmt = $pdo->prepare("
    SELECT em.*, e.temple_id, m.name as monk_name
    FROM event_monk em
    JOIN events e ON em.event_id = e.id
    JOIN monks m ON em.monk_id = m.id
    WHERE em.id = ? AND em.event_id = ?
");
$stmt->execute([$event_monk_id, $event_id]);
$participation = $stmt->fetch();

if (!$participation) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງ";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ກວດສອບສິດໃນການລຶບຂໍ້ມູນ
$can_delete = $_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $participation['temple_id']);

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງນີ້";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

try {
    // ລຶບຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງ
    $stmt = $pdo->prepare("DELETE FROM event_monk WHERE id = ?");
    $stmt->execute([$event_monk_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "ລຶບພະສົງ " . $participation['monk_name'] . " ອອກຈາກກິດຈະກໍາແລ້ວ";
    } else {
        $_SESSION['error'] = "ບໍ່ສາມາດລຶບຂໍ້ມູນການເຂົ້າຮ່ວມໄດ້";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
}

// ກັບໄປທີ່ໜ້າລາຍລະອຽດກິດຈະກໍາ
header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
exit;

// Flush the buffer at the end of the file
ob_end_flush();
?>