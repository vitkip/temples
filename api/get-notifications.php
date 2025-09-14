<?php
// filepath: c:\xampp\htdocs\temples\api\get-notifications.php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once '../config/db.php';
require_once '../config/notification_functions.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

try {
    // ດຶງການແຈ້ງເຕືອນ
    $notifications = get_user_notifications($user_id, $limit, $offset, $unread_only);
    
    // ນັບຈຳນວນທີ່ຍັງບໍ່ອ່ານ
    $unread_count = count_unread_notifications($user_id);
    
    // ຈັດຮູບແບບເວລາສຳລັບການສະແດງຜົນ
    foreach ($notifications as &$notification) {
        $notification['created_at_formatted'] = format_lao_datetime($notification['created_at']);
        $notification['time_ago'] = time_ago_lao($notification['created_at']);
        
        // ຖອດລະຫັດ JSON data
        if (!empty($notification['data'])) {
            $notification['data'] = json_decode($notification['data'], true);
        }
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'has_more' => count($notifications) === $limit
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * ຈັດຮູບແບບວັນທີເວລາເປັນພາສາລາວ
 */
function format_lao_datetime($datetime) {
    $timestamp = strtotime($datetime);
    $lao_months = [
        'ມ.ກ.', 'ກ.ພ.', 'ມີ.ນ.', 'ມ.ສ.', 'ພ.ພ.', 'ມິ.ຖ.',
        'ກ.ລ.', 'ສ.ຫ.', 'ກ.ຍ.', 'ຕ.ລ.', 'ພ.ຈ.', 'ທ.ວ.'
    ];
    
    $day = date('j', $timestamp);
    $month = $lao_months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return "$day $month $year, $time ໂມງ";
}

/**
 * ຄິດໄລຍະເວລາທີ່ຜ່ານມາເປັນພາສາລາວ
 */
function time_ago_lao($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'ຫາກໍ່';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "$minutes ນາທີ ແລ້ວ";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours ຊົ່ວໂມງ ແລ້ວ";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return "$days ວັນ ແລ້ວ";
    } else {
        $months = floor($diff / 2592000);
        return "$months ເດືອນ ແລ້ວ";
    }
}
?>
