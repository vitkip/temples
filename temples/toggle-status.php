<?php
// filepath: c:\xampp\htdocs\temples\temples\toggle-status.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตั้งค่า header ให้ถูกต้อง
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// สำหรับ debug
error_log("Toggle status request received");

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // ตอบกลับสำหรับ preflight request
    http_response_code(200);
    exit;
}

// ตรวจสอบการร้องขอ Ajax - แบบยืดหยุ่นมากขึ้น
// if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
//     error_log("Invalid request type: Not AJAX");
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ']);
//     exit;
// }

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'superadmin' && $_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'province_admin')) {
    error_log("Permission denied: User role - " . ($_SESSION['user']['role'] ?? 'not logged in'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດໃນການອັບເດດສະຖານະ']);
    exit;
}

// รับข้อมูล JSON จากคำขอ POST
$json = file_get_contents('php://input');
error_log("Received data: " . $json);
$data = json_decode($json, true);

// กรณีไม่มีข้อมูลหรือรูปแบบไม่ถูกต้อง ให้ลองรับข้อมูลจาก $_POST
if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    error_log("Invalid JSON, trying POST data");
    $data = [
        'temple_id' => $_POST['temple_id'] ?? null,
        'status' => $_POST['status'] ?? null
    ];
}

// ตรวจสอบข้อมูล
if (!isset($data['temple_id']) || !isset($data['status'])) {
    error_log("Missing data: temple_id or status");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ຂໍ້ມູນບໍ່ຄົບຖ້ວນ']);
    exit;
}

$temple_id = $data['temple_id'];
$status = $data['status'];

// ตรวจสอบความถูกต้องของสถานะ
if (!in_array($status, ['active', 'inactive'])) {
    error_log("Invalid status: $status");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ສະຖານະບໍ່ຖືກຕ້ອງ']);
    exit;
}

try {
    // อัปเดตสถานะในฐานข้อมูล
    $stmt = $pdo->prepare("UPDATE temples SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $temple_id]);
    
    if ($result) {
        // บันทึก log ลงในไฟล์แทน
        $action = $status === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        $user_id = $_SESSION['user']['id'] ?? 'unknown';
        $log_message = "User ID: $user_id, Action: $action, Temple ID: $temple_id, Status: $status, Time: " . date('Y-m-d H:i:s');
        error_log($log_message);
        
        echo json_encode([
            'success' => true, 
            'message' => 'ອັບເດດສະຖານະສຳເລັດແລ້ວ', 
            'status' => $status
        ]);
    } else {
        error_log("Update failed for temple_id: $temple_id");
        throw new Exception("การอัปเดตสถานะล้มเหลว");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ເກີດຂໍ້ຜິດພາດໃນຖານຂໍ້ມູນ: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ตรวจสอบข้อมูลวัด
$stmt = $pdo->prepare("
    SELECT t.id, t.name, t.province_id
    FROM temples t
    WHERE t.id = ?
");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch(PDO::FETCH_ASSOC);

$user_role = $_SESSION['user']['role'];
if ($user_role === 'superadmin' || $user_role === 'admin') {
    // Super Admin และ Admin สามารถเปลี่ยนสถานะวัดได้ทุกวัด
    $can_edit = true;
} elseif ($user_role === 'province_admin') {
    // Province Admin มีสิทธิ์เปลี่ยนสถานะวัดในแขวงที่รับผิดชอบ
    $check_province = $pdo->prepare("
        SELECT COUNT(*) FROM user_province_access 
        WHERE user_id = ? AND province_id = ?
    ");
    $check_province->execute([$user_id, $temple['province_id']]);
    $can_edit = ($check_province->fetchColumn() > 0);
}
?>