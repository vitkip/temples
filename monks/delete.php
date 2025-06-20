<?php
// filepath: c:\xampp\htdocs\temples\monks\delete.php
ob_start(); // เพิ่ม output buffering
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

// ตรวจสอบสิทธิ์การใช้งาน - เพิ่ม province_admin
if (!in_array($user_role, ['superadmin', 'admin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบค่า ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// ดึงข้อมูลพระสงฆ์พร้อมข้อมูลวัด
$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        t.name as temple_name,
        t.province_id,
        d.district_name,
        p.province_name
    FROM monks m 
    LEFT JOIN temples t ON m.temple_id = t.id 
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE m.id = ?
");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบสิทธิ์ในการลบข้อมูล
$can_delete = false;

if ($user_role === 'superadmin') {
    $can_delete = true;
} elseif ($user_role === 'admin' && $user_temple_id == $monk['temple_id']) {
    $can_delete = true;
} elseif ($user_role === 'province_admin') {
    // ตรวจสอบว่าวัดของพระสงฆ์อยู่ในแขวงที่ province_admin ดูแลหรือไม่
    $check_access = $pdo->prepare("
        SELECT COUNT(*) FROM temples t
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE t.id = ? AND upa.user_id = ?
    ");
    $check_access->execute([$monk['temple_id'], $user_id]);
    $can_delete = ($check_access->fetchColumn() > 0);
}

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນພະສົງນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตรวจสอบ CSRF Token (ถ้ามีการส่งมาจากฟอร์ม)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'monks/');
        exit;
    }
}

// ตรวจสอบว่ามีการยืนยันการลบหรือไม่
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // แสดงหน้ายืนยันการลบ
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    require_once '../includes/header.php';
    ?>
    
    <div class="page-container">
        <div class="max-w-lg mx-auto mt-10">
            <div class="bg-white rounded-lg shadow-lg p-6 border-t-4 border-red-500">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">ຢືນຢັນການລຶບຂໍ້ມູນ</h3>
                        <p class="text-sm text-gray-500">ການກະທຳນີ້ບໍ່ສາມາດຍົກເລີກໄດ້</p>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h4 class="font-medium text-gray-800 mb-2">ຂໍ້ມູນພະສົງທີ່ຈະລຶບ:</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li><strong>ຊື່:</strong> <?= htmlspecialchars($monk['prefix'] . ' ' . $monk['name']) ?></li>
                        <?php if (!empty($monk['lay_name'])): ?>
                        <li><strong>ນາມສະກຸນ:</strong> <?= htmlspecialchars($monk['lay_name']) ?></li>
                        <?php endif; ?>
                        <li><strong>ວັດ:</strong> <?= htmlspecialchars($monk['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></li>
                        <li><strong>ພັນສາ:</strong> <?= htmlspecialchars($monk['pansa']) ?> ພັນສາ</li>
                    </ul>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <p class="text-red-800 text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <strong>ຄຳເຕືອນ:</strong> ການລຶບຂໍ້ມູນນີ້ຈະທຳການລຶບ:
                    </p>
                    <ul class="text-red-700 text-sm mt-2 ml-6 list-disc">
                        <li>ຂໍ້ມູນພະສົງທັງໝົດ</li>
                        <li>ຮູບພາບຂອງພະສົງ (ຖ້າມີ)</li>
                        <li>ບໍ່ສາມາດຟື້ນຟູຂໍ້ມູນໄດ້</li>
                    </ul>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="<?= $base_url ?>monks/" 
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition">
                        <i class="fas fa-times mr-2"></i>ຍົກເລີກ
                    </a>
                    
                    <form method="POST" action="<?= $base_url ?>monks/delete.php?id=<?= $monk_id ?>&confirm=yes" class="inline">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" 
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
                                onclick="return confirm('ທ່ານແນ່ໃຈວ່າຕ້ອງການລຶບຂໍ້ມູນນີ້ບໍ?')">
                            <i class="fas fa-trash mr-2"></i>ລຶບຂໍ້ມູນ
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    require_once '../includes/footer.php';
    ob_end_flush();
    exit;
}

// ดำเนินการลบข้อมูล
try {
    // เริ่ม transaction
    $pdo->beginTransaction();
    
    // สร้างชื่อไฟล์เต็มสำหรับแสดงใน log
    $monk_full_name = trim(($monk['prefix'] ?? '') . ' ' . $monk['name']);
    
    // ลบรูปภาพถ้าไม่ใช่รูปภาพเริ่มต้น
    if (!empty($monk['photo']) && 
        $monk['photo'] !== 'uploads/monks/default.png' && 
        file_exists('../' . $monk['photo'])) {
        @unlink('../' . $monk['photo']); // ใช้ @ เพื่อป้องกัน warning
    }
    
    // ลบข้อมูลจากฐานข้อมูล
    $stmt = $pdo->prepare("DELETE FROM monks WHERE id = ?");
    $stmt->execute([$monk_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("ບໍ່ສາມາດລຶບຂໍ້ມູນພະສົງໄດ້");
    }
    
    // บันทึก activity log (ถ้ามีตาราง activity_logs)
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, target_type, target_id, description, created_at) 
            VALUES (?, 'delete', 'monk', ?, ?, NOW())
        ");
        $log_stmt->execute([
            $user_id,
            $monk_id,
            "ลบข้อมูลพระสงฆ์: {$monk_full_name} จากวัด: " . ($monk['temple_name'] ?? 'ไม่ทราบ')
        ]);
    } catch (PDOException $e) {
        // ไม่ให้ log error ขัดขวางการลบข้อมูลหลัก
        error_log('Log error in delete.php: ' . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    $_SESSION['success'] = "ລຶບຂໍ້ມູນພະສົງ {$monk_full_name} ສຳເລັດແລ້ວ";
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollback();
    
    error_log('Error in delete.php: ' . $e->getMessage());
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ";
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollback();
    
    error_log('Database error in delete.php: ' . $e->getMessage());
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ";
}

// กลับไปที่หน้ารายการพระสงฆ์
header('Location: ' . $base_url . 'monks/');
exit;

ob_end_flush();
?>