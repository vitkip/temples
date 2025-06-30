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
    $user_role = $_GET['user_role'] ?? $_SESSION['user']['role'];
    $user_id = $_GET['user_id'] ?? $_SESSION['user']['id'];
    $user_temple_id = $_SESSION['user']['temple_id'] ?? null;
    $current_year = date('Y');

    $data = [];

    if ($user_role === 'superadmin') {
        // Get all data by province, district, and temple
        $stmt = $pdo->prepare("
            SELECT 
                p.province_name as province,
                d.district_name as district,
                t.name as temple,
                COUNT(CASE WHEN YEAR(m.ordination_date) = ? THEN 1 END) as monks_ordination_this_year,
                COUNT(CASE WHEN YEAR(m.resignation_date) = ? THEN 1 END) as monks_resign_this_year
            FROM temples t
            JOIN provinces p ON t.province_id = p.province_id
            JOIN districts d ON t.district_id = d.district_id
            LEFT JOIN monks m ON t.id = m.temple_id
            WHERE t.status = 'active'
            GROUP BY t.id, p.province_name, d.district_name, t.name
            ORDER BY p.province_name, d.district_name, t.name
        ");
        $stmt->execute([$current_year, $current_year]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($user_role === 'admin') {
        // Get only their temple data
        if ($user_temple_id) {
            $stmt = $pdo->prepare("
                SELECT 
                    p.province_name as province,
                    d.district_name as district,
                    t.name as temple,
                    COUNT(CASE WHEN YEAR(m.ordination_date) = ? THEN 1 END) as monks_ordination_this_year,
                    COUNT(CASE WHEN YEAR(m.resignation_date) = ? THEN 1 END) as monks_resign_this_year
                FROM temples t
                JOIN provinces p ON t.province_id = p.province_id
                JOIN districts d ON t.district_id = d.district_id
                LEFT JOIN monks m ON t.id = m.temple_id
                WHERE t.id = ? AND t.status = 'active'
                GROUP BY t.id, p.province_name, d.district_name, t.name
            ");
            $stmt->execute([$current_year, $current_year, $user_temple_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } elseif ($user_role === 'province_admin') {
        // Get data for assigned provinces
        $stmt = $pdo->prepare("
            SELECT 
                p.province_name as province,
                d.district_name as district,
                t.name as temple,
                COUNT(CASE WHEN YEAR(m.ordination_date) = ? THEN 1 END) as monks_ordination_this_year,
                COUNT(CASE WHEN YEAR(m.resignation_date) = ? THEN 1 END) as monks_resign_this_year
            FROM temples t
            JOIN provinces p ON t.province_id = p.province_id
            JOIN districts d ON t.district_id = d.district_id
            JOIN user_province_access upa ON p.province_id = upa.province_id
            LEFT JOIN monks m ON t.id = m.temple_id
            WHERE upa.user_id = ? AND t.status = 'active'
            GROUP BY t.id, p.province_name, d.district_name, t.name
            ORDER BY p.province_name, d.district_name, t.name
        ");
        $stmt->execute([$current_year, $current_year, $user_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ensure we have data
    if (empty($data)) {
        $data = [[
            'province' => 'ບໍ່ມີຂໍ້ມູນ',
            'district' => 'ບໍ່ມີຂໍ້ມູນ',
            'temple' => 'ບໍ່ມີຂໍ້ມູນ',
            'monks_ordination_this_year' => 0,
            'monks_resign_this_year' => 0
        ]];
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