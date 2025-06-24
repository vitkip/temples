<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'ບໍ່ໄດ້ຮັບອະນຸຍາດ']);
    exit;
}

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// เตรียมเงื่อนไขตามสิทธิ์ผู้ใช้
$params = [];
$condition = 'WHERE t.status = "active"';

// ตรวจสอบสิทธิ์ผู้ใช้
if ($user_role === 'admin') {
    // admin เห็นเฉพาะวัดของตัวเอง
    $condition .= ' AND t.id = ?';
    $params[] = $user_temple_id;
} elseif ($user_role === 'province_admin') {
    // province_admin เห็นวัดในแขวงที่รับผิดชอบ
    $condition .= ' AND t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)';

    $params[] = $user_id;
}

// กรองตามแขวง (ถ้ามี)
if (isset($_GET['province_id']) && !empty($_GET['province_id'])) {
    $province_id = (int)$_GET['province_id'];
    $condition .= ' AND t.province_id = ?';
    $params[] = $province_id;
}

// กรองตามเมือง (ถ้ามี)
if (isset($_GET['district_id']) && !empty($_GET['district_id'])) {
    $district_id = (int)$_GET['district_id'];
    $condition .= ' AND t.district_id = ?';
    $params[] = $district_id;
}

try {
    // สร้าง query
    $sql = "SELECT t.id, t.name, t.province_id, t.district_id, 
                   p.province_name, d.district_name
            FROM temples t 
            LEFT JOIN provinces p ON t.province_id = p.province_id
            LEFT JOIN districts d ON t.district_id = d.district_id
            $condition
            ORDER BY t.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $temples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // บันทึก log สำหรับ debug
    error_log("Temples API found: " . count($temples) . " temples with condition: $condition");

    echo json_encode([
        'success' => true,
        'temples' => $temples,
        'count' => count($temples)
    ]);
} catch (PDOException $e) {
    error_log("Temples API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>