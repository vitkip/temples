<?php
// filepath: c:\xampp\htdocs\temples\api\get-districts-by-province-name.php
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isset($_GET['province_name']) || empty($_GET['province_name'])) {
    echo json_encode(['success' => false, 'message' => 'Missing province name']);
    exit;
}

$province_name = trim($_GET['province_name']);

try {
    // หา province_id จาก province_name
    $province_stmt = $pdo->prepare("
        SELECT province_id FROM provinces 
        WHERE province_name = ?
    ");
    $province_stmt->execute([$province_name]);
    $province = $province_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$province) {
        echo json_encode(['success' => false, 'message' => 'Province not found']);
        exit;
    }
    
    $province_id = $province['province_id'];
    
    // ดึงข้อมูลเมืองตามจังหวัด
    $stmt = $pdo->prepare("
        SELECT district_id, district_name
        FROM districts
        WHERE province_id = ?
        ORDER BY district_name
    ");
    
    $stmt->execute([$province_id]);
    $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
exit;