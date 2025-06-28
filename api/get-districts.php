<?php
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'ບໍ່ໄດ້ຮັບອະນຸຍາດ']);
    exit;
}

// ตรวจสอบว่ามี province_id ส่งมาหรือไม่
if (!isset($_GET['province_id']) || empty($_GET['province_id'])) {
    echo json_encode(['success' => false, 'message' => 'ກະລຸນາລະບຸແຂວງ']);
    exit;
}

$province_id = (int)$_GET['province_id'];
$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];

try {
    // สร้าง query ตามสิทธิ์ผู้ใช้
    if ($user_role === 'superadmin') {
        // superadmin เห็นทุกเมืองในแขวงที่เลือก
        $sql = "SELECT district_id, district_name FROM districts 
                WHERE province_id = ? ORDER BY district_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$province_id]);
    } elseif ($user_role === 'province_admin') {
        // province_admin เห็นเฉพาะเมืองในแขวงที่รับผิดชอบ
        $sql = "SELECT d.district_id, d.district_name 
                FROM districts d
                JOIN user_province_access upa ON d.province_id = upa.province_id
                WHERE d.province_id = ? AND upa.user_id = ?
                ORDER BY d.district_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$province_id, $user_id]);
    } else {
        // admin ดูได้เฉพาะเมืองของวัดตัวเอง
        $sql = "SELECT d.district_id, d.district_name 
                FROM districts d
                JOIN temples t ON d.district_id = t.district_id
                WHERE d.province_id = ? AND t.id = ?
                ORDER BY d.district_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$province_id, $_SESSION['user']['temple_id']]);
    }
    
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

?>