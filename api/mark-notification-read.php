<?php
// filepath: c:\xampp\htdocs\temples\api\mark-notification-read.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

// ກວດສອບ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ອ່ານ JSON input
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ບໍ່ມີ notification_id']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();
    
    // ກວດສອບວ່າ notification ນີ້ເປັນຂອງ user ນີ້ບໍ
    $check_stmt = $pdo->prepare("
        SELECT id, is_read 
        FROM notifications 
        WHERE id = ? AND user_id = ?
    ");
    $check_stmt->execute([$notification_id, $user_id]);
    $notification = $check_stmt->fetch();
    
    if (!$notification) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'ບໍ່ພົບການແຈ້ງເຕືອນນີ້']);
        exit;
    }
    
    // ອັບເດດສະຖານະການອ່ານ
    $update_stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    $update_stmt->execute([$notification_id, $user_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ໝາຍວ່າອ່ານແລ້ວສຳເລັດ',
        'was_unread' => !$notification['is_read']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
