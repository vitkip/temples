<?php
// filepath: c:\xampp\htdocs\temples\api\temple_stats.php
header('Content-Type: application/json');

require_once '../config/db.php';
require_once '../config/base_url.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

// Initialize response array
$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'data' => []
];

try {
    // Get total temples count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM temples");
    $response['data']['total_temples'] = (int)$stmt->fetch()['total'];
    
    // Get active temples count
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM temples WHERE status = 'active'");
    $response['data']['active_temples'] = (int)$stmt->fetch()['active'];
    
    // Get inactive temples count
    $stmt = $pdo->query("SELECT COUNT(*) as inactive FROM temples WHERE status = 'inactive'");
    $response['data']['inactive_temples'] = (int)$stmt->fetch()['inactive'];
    
    // Get temples by province
    $stmt = $pdo->query("
        SELECT province, COUNT(*) as count 
        FROM temples 
        GROUP BY province 
        ORDER BY count DESC
    ");
    $response['data']['temples_by_province'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get temples with most monks
    $stmt = $pdo->query("
        SELECT t.id, t.name, t.district, t.province, COUNT(m.id) as monk_count 
        FROM temples t
        LEFT JOIN monks m ON t.id = m.temple_id AND m.status = 'active'
        WHERE t.status = 'active'
        GROUP BY t.id
        ORDER BY monk_count DESC
        LIMIT 5
    ");
    $response['data']['temples_with_most_monks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get temples with upcoming events
    $stmt = $pdo->query("
        SELECT t.id, t.name, COUNT(e.id) as event_count 
        FROM temples t
        JOIN events e ON t.id = e.temple_id
        WHERE e.event_date >= CURDATE()
        GROUP BY t.id
        ORDER BY event_count DESC
        LIMIT 5
    ");
    $response['data']['temples_with_upcoming_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get monks statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM monks WHERE status = 'active'");
    $response['data']['total_active_monks'] = (int)$stmt->fetch()['total'];
    
    // Get events statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE event_date >= CURDATE()");
    $response['data']['upcoming_events'] = (int)$stmt->fetch()['total'];
    
    // Recently updated temples
    $stmt = $pdo->query("
        SELECT id, name, district, province, updated_at
        FROM temples
        ORDER BY updated_at DESC
        LIMIT 5
    ");
    $response['data']['recently_updated_temples'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add average number of monks per temple
    if ($response['data']['total_temples'] > 0) {
        $response['data']['avg_monks_per_temple'] = round($response['data']['total_active_monks'] / $response['data']['total_temples'], 1);
    } else {
        $response['data']['avg_monks_per_temple'] = 0;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $response = [
        'status' => 'error',
        'message' => 'ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ',
        'error_details' => DEBUG_MODE ? $e->getMessage() : null
    ];
}

// Define a constant for debug mode - you might want to set this in config
defined('DEBUG_MODE') or define('DEBUG_MODE', false);

// Output the response as JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;