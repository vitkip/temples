<?php
require_once '../config/db.php';

// คำขอ API สำหรับข้อมูล districts
if (isset($_GET['get_districts']) && !empty($_GET['province_id'])) {
    $province_id = (int)$_GET['province_id'];
    $api_district_stmt = $pdo->prepare("
        SELECT district_id, district_name 
        FROM districts 
        WHERE province_id = ? 
        ORDER BY district_name
    ");
    $api_district_stmt->execute([$province_id]);
    $api_districts = $api_district_stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'districts' => $api_districts]);
    exit;
}

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