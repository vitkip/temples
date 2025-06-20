<?php
// filepath: c:\xampp\htdocs\temples\api\temple_stats.php
// เปิด error reporting เพื่อดู error
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config/db.php';
    
    // เริ่ม session อย่างปลอดภัย
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // ตรวจสอบการเข้าสู่ระบบ
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Please login first']);
        exit;
    }
    
    $user_role = $_GET['user_role'] ?? $_SESSION['user']['role'];
    $user_id = $_GET['user_id'] ?? $_SESSION['user']['id'];
    $user_temple_id = $_SESSION['user']['temple_id'] ?? null;
    
    $data = [];
    
    if ($user_role === 'superadmin') {
        // Superadmin เห็นสถิติทุกแขวง
        $stmt = $pdo->query("
            SELECT 
                COALESCE(p.province_name, 'ບໍ່ລະບຸແຂວງ') as province, 
                COUNT(t.id) as count
            FROM provinces p
            LEFT JOIN temples t ON p.province_id = t.province_id AND t.status = 'active'
            GROUP BY p.province_id, p.province_name
            ORDER BY p.province_name
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'province_admin') {
        // Province admin เห็นเฉพาะแขวงที่รับผิดชอบ
        $stmt = $pdo->prepare("
            SELECT 
                p.province_name as province, 
                COUNT(t.id) as count
            FROM provinces p
            JOIN user_province_access upa ON p.province_id = upa.province_id
            LEFT JOIN temples t ON p.province_id = t.province_id AND t.status = 'active'
            WHERE upa.user_id = ?
            GROUP BY p.province_id, p.province_name
            ORDER BY p.province_name
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin' && $user_temple_id) {
        // Admin เห็นเฉพาะวัดของตน
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(p.province_name, 'ວັດຂອງທ່ານ') as province, 
                COUNT(t.id) as count
            FROM temples t
            LEFT JOIN provinces p ON t.province_id = p.province_id
            WHERE t.id = ? AND t.status = 'active'
            GROUP BY p.province_id, p.province_name
        ");
        $stmt->execute([$user_temple_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ถ้าไม่มีข้อมูล ให้ส่งข้อมูลตัวอย่าง
    if (empty($data)) {
        $data = [
            ['province' => 'ບໍ່ມີຂໍ້ມູນ', 'count' => 0]
        ];
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log error
    error_log('Error in temple_stats.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>