<?php
// filepath: c:\xampp\htdocs\temples\monks\update_status.php
require_once '../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$monk_id = $data['monk_id'] ?? null;
$new_status = $data['status'] ?? null;

if (!$monk_id || !in_array($new_status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

// ตรวจสอบสิทธิ์ (เพิ่มตามระบบของคุณ)
// ...

if ($new_status === 'inactive') {
    // สึก: บันทึกวันที่สึก
    $resignation_date = date('Y-m-d');
    $stmt = $pdo->prepare("UPDATE monks SET status = ?, resignation_date = ? WHERE id = ?");
    $success = $stmt->execute([$new_status, $resignation_date, $monk_id]);
} else {
    // กลับเป็นบวช: ลบวันที่สึก
    $stmt = $pdo->prepare("UPDATE monks SET status = ?, resignation_date = NULL WHERE id = ?");
    $success = $stmt->execute([$new_status, $monk_id]);
}

if ($success) {
    echo json_encode(['success' => true, 'message' => 'ປ່ຽນສະຖານະສຳເລັດ']);
} else {
    echo json_encode(['success' => false, 'message' => 'ບັນທຶກບໍ່ສຳເລັດ']);
}
?>