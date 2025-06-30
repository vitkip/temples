<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

try {
    $user_role = $_SESSION['user']['role'];
    $user_id = $_SESSION['user']['id'];
    $user_temple_id = $_SESSION['user']['temple_id'] ?? null;

    $data = [];

    if ($user_role === 'superadmin') {
        // Get all provinces with temple counts
        $stmt = $pdo->query("
            SELECT 
                p.province_name as province,
                COUNT(t.id) as count
            FROM provinces p
            LEFT JOIN temples t ON p.province_id = t.province_id AND t.status = 'active'
            GROUP BY p.province_id, p.province_name
            HAVING COUNT(t.id) > 0
            ORDER BY COUNT(t.id) DESC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Get only their temple's province
        if ($user_temple_id) {
            $stmt = $pdo->prepare("
                SELECT 
                    p.province_name as province,
                    COUNT(t.id) as count
                FROM temples t
                JOIN provinces p ON t.province_id = p.province_id
                WHERE t.id = ? AND t.status = 'active'
                GROUP BY p.province_id, p.province_name
            ");
            $stmt->execute([$user_temple_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } elseif ($user_role === 'province_admin') {
        // Get provinces assigned to this admin
        $stmt = $pdo->prepare("
            SELECT 
                p.province_name as province,
                COUNT(t.id) as count
            FROM provinces p
            JOIN user_province_access upa ON p.province_id = upa.province_id
            LEFT JOIN temples t ON p.province_id = t.province_id AND t.status = 'active'
            WHERE upa.user_id = ?
            GROUP BY p.province_id, p.province_name
            HAVING COUNT(t.id) > 0
            ORDER BY COUNT(t.id) DESC
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ensure we have data
    if (empty($data)) {
        $data = [['province' => 'ບໍ່ມີຂໍ້ມູນ', 'count' => 0]];
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>