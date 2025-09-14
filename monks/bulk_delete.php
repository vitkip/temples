<?php
// filepath: c:\xampp\htdocs\temples\monks\bulk_delete.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ตรวจสอบสิทธิ์การใช้งาน
if (!in_array($user_role, ['superadmin', 'admin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบว่าเป็น POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "ວິທີການເຂົ້າເຖິງບໍ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบว่ามี monk_ids ส่งมาหรือไม่
if (!isset($_POST['monk_ids']) || !is_array($_POST['monk_ids']) || empty($_POST['monk_ids'])) {
    $_SESSION['error'] = "ກະລຸນາເລືອກພະສົງທີ່ຕ້ອງການລົບ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_ids = array_filter($_POST['monk_ids'], 'is_numeric');
if (empty($monk_ids)) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງທີ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

try {
    $pdo->beginTransaction();
    
    $deleted_count = 0;
    $failed_count = 0;
    $failed_names = [];
    
    // ดึงข้อมูลพระสงฆ์ที่จะลบ พร้อมตรวจสอบสิทธิ์
    $placeholders = str_repeat('?,', count($monk_ids) - 1) . '?';
    $check_query = "
        SELECT m.*, t.name as temple_name, t.province_id
        FROM monks m 
        LEFT JOIN temples t ON m.temple_id = t.id 
        WHERE m.id IN ($placeholders)
    ";
    
    $stmt = $pdo->prepare($check_query);
    $stmt->execute($monk_ids);
    $monks_to_delete = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($monks_to_delete as $monk) {
        // ตรวจสอบสิทธิ์การลบสำหรับแต่ละพระสงฆ์
        $can_delete = false;
        
        if ($user_role === 'superadmin') {
            $can_delete = true;
        } elseif ($user_role === 'admin') {
            $can_delete = ($user_temple_id == $monk['temple_id']);
        } elseif ($user_role === 'province_admin') {
            // ตรวจสอบว่า province_admin มีสิทธิ์ในจังหวัดนี้หรือไม่
            $province_check = $pdo->prepare("SELECT 1 FROM user_province_access WHERE user_id = ? AND province_id = ?");
            $province_check->execute([$user_id, $monk['province_id']]);
            $can_delete = $province_check->fetchColumn() !== false;
        }
        
        if (!$can_delete) {
            $failed_count++;
            $failed_names[] = $monk['name'];
            continue;
        }
        
        // ลบข้อมูลจากตาราง event_monk ก่อน (ถ้ามี)
        $delete_event_monk = $pdo->prepare("DELETE FROM event_monk WHERE monk_id = ?");
        $delete_event_monk->execute([$monk['id']]);
        
        // ลบไฟล์รูปภาพ (ถ้ามี)
        if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png') {
            $photo_path = '../' . $monk['photo'];
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
        }
        
        // ลบข้อมูลพระสงฆ์
        $delete_monk = $pdo->prepare("DELETE FROM monks WHERE id = ?");
        if ($delete_monk->execute([$monk['id']])) {
            $deleted_count++;
        } else {
            $failed_count++;
            $failed_names[] = $monk['name'];
        }
    }
    
    $pdo->commit();
    
    // สร้างข้อความแจ้งผลลัพธ์
    $messages = [];
    if ($deleted_count > 0) {
        $messages[] = "ລົບຂໍ້ມູນພະສົງສຳເລັດ $deleted_count ລາຍການ";
    }
    if ($failed_count > 0) {
        $messages[] = "ລົບບໍ່ສຳເລັດ $failed_count ລາຍການ";
        if (!empty($failed_names)) {
            $messages[] = "ລາຍການທີ່ລົບບໍ່ສຳເລັດ: " . implode(', ', array_slice($failed_names, 0, 5));
            if (count($failed_names) > 5) {
                $messages[] = "ແລະອື່ນໆ...";
            }
        }
    }
    
    if ($deleted_count > 0) {
        $_SESSION['success'] = implode(' | ', $messages);
    } else {
        $_SESSION['error'] = implode(' | ', $messages);
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Bulk delete monks error: " . $e->getMessage());
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລົບຂໍ້ມູນ: " . $e->getMessage();
}

header('Location: ' . $base_url . 'monks/');
exit;
?>
