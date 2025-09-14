<?php
// filepath: c:\xampp\htdocs\temples\api\mark-all-notifications-read.php
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

$user_id = $_SESSION['user']['id'];

try {
    $pdo->beginTransaction();
    
    // ນັບຈຳນວນ notifications ທີ່ຍັງບໍ່ອ່ານ
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $count_stmt->execute([$user_id]);
    $unread_count = $count_stmt->fetch()['unread_count'];
    
    // ອັບເດດການແຈ້ງເຕືອນທັງໝົດໃຫ້ເປັນອ່ານແລ້ວ
    $update_stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND is_read = 0
    ");
    $update_stmt->execute([$user_id]);
    
    $affected_rows = $update_stmt->rowCount();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ໝາຍວ່າອ່ານທັງໝົດແລ້ວສຳເລັດ',
        'updated_count' => $affected_rows,
        'previous_unread_count' => $unread_count
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'error' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
