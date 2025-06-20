<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['province_id']) || empty($_GET['province_id'])) {
    echo json_encode(['success' => false, 'message' => 'Province ID required']);
    exit;
}

$province_id = (int)$_GET['province_id'];

try {
    $stmt = $pdo->prepare("
        SELECT district_id, district_name 
        FROM districts 
        WHERE province_id = ? 
        ORDER BY district_name ASC
    ");
    $stmt->execute([$province_id]);
    $districts = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'districts' => $districts
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>