<?php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check permissions - only superadmin and admin can delete
if ($_SESSION['user']['role'] !== 'superadmin' && $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການລຶບວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// Check if ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

$temple_id = (int)$_GET['id'];

// Get temple data to confirm it exists and display details
$stmt = $pdo->prepare("
    SELECT t.*, d.district_name, p.province_name 
    FROM temples t
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE t.id = ?
");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch();

if (!$temple) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'temples/');
        exit;
    }
    
    try {
        // Check for related records first (monks, events, users)
        $monk_check = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE temple_id = ?");
        $monk_check->execute([$temple_id]);
        $has_monks = $monk_check->fetchColumn() > 0;
        
        $event_check = $pdo->prepare("SELECT COUNT(*) FROM events WHERE temple_id = ?");
        $event_check->execute([$temple_id]);
        $has_events = $event_check->fetchColumn() > 0;
        
        // เพิ่มการตรวจสอบตาราง users
        $user_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE temple_id = ?");
        $user_check->execute([$temple_id]);
        $has_users = $user_check->fetchColumn() > 0;
        
        // ดึงข้อมูลไฟล์รูปภาพก่อนลบ
        $photo_stmt = $pdo->prepare("SELECT photo, logo FROM temples WHERE id = ?");
        $photo_stmt->execute([$temple_id]);
        $temple_files = $photo_stmt->fetch();
        
        // If there are related records, use soft delete (update status)
        if ($has_monks || $has_events || $has_users) {
            $stmt = $pdo->prepare("UPDATE temples SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$temple_id]);
            
            // แสดงข้อความแจ้งเตือนที่ชัดเจนว่ามีผู้ใช้ที่เกี่ยวข้องกับวัดนี้
            $_SESSION['warning'] = "ອັບເດດສະຖານະວັດເປັນ 'ປິດໃຊ້ງານ' ແລ້ວ ເນື່ອງຈາກມີຂໍ້ມູນທີ່ກ່ຽວຂໍ່ນ";
            
            if ($has_users) {
                $_SESSION['warning'] .= " - ມີຜູ່ໃຊ້ຖືກເຊື່ອມຕໍ່ກັບວັດນີ້";
            }
            
            if ($has_monks) {
                $_SESSION['warning'] .= " - ມີຂໍ້ມູນພະສົງ";
            }
            
            if ($has_events) {
                $_SESSION['warning'] .= " - ມີຂໍ້ມູນກິດຈະກຳ";
            }
        } else {
            // Hard delete - ลบไฟล์รูปภาพก่อนลบ
            
            // ลบรูปภาพหลัก (photo) ถ้ามี
            if (!empty($temple_files['photo']) && file_exists('../' . $temple_files['photo'])) {
                unlink('../' . $temple_files['photo']);
            }
            
            // ลบรูปโลโก้ (logo) ถ้ามี
            if (!empty($temple_files['logo']) && file_exists('../' . $temple_files['logo'])) {
                unlink('../' . $temple_files['logo']);
            }
            
            // Hard delete from database
            $stmt = $pdo->prepare("DELETE FROM temples WHERE id = ?");
            $stmt->execute([$temple_id]);
            $_SESSION['success'] = "ລຶບຂໍ້ມູນວັດແລະຮູບພາບທີ່ກ່ຽວຂໍ່ສຳເລັດແລ້ວ";
        }
        
        header('Location: ' . $base_url . 'temples/');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
        header('Location: ' . $base_url . 'temples/delete.php?id=' . $temple_id);
        exit;
    }
}

// ส่วนที่แสดงผล HTML ควรอยู่หลังจากการตรวจสอบทั้งหมด
$page_title = 'ລຶບວັດ';
require_once '../includes/header.php';
?>

<!-- Delete Confirmation -->
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ຢືນຢັນການລຶບວັດ</h1>
            <p class="text-sm text-gray-600">ກະລຸນາຢືນຢັນການລຶບຂໍ້ມູນ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>temples/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6">
            <div class="flex items-center p-4 mb-6 text-red-50 bg-red-500 rounded-lg">
                <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
                <div>
                    <span class="font-bold">ແຈ້ງເຕືອນ!</span> 
                    <p>ການກະທຳນີ້ບໍ່ສາມາດຖືກເອົາກັບຄືນໄດ້. ກະລຸນາຢືນຢັນວ່າທ່ານຕ້ອງການລຶບຂໍ້ມູນວັດນີ້ແທ້.</p>
                </div>
            </div>
            
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນວັດທີ່ຈະລຶບ</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <h3 class="text-sm text-gray-500">ຊື່ວັດ</h3>
                        <p class="text-gray-800 font-medium"><?= htmlspecialchars($temple['name']) ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm text-gray-500">ເຂດ/ເມືອງ, ແຂວງ</h3>
                        <p class="text-gray-800">
                            <?php 
                                // ใช้ district_name และ province_name ที่ JOIN มาจาก SQL
                                echo htmlspecialchars(($temple['district_name'] ?? 'ບໍ່ລະບຸ') . ', ' . ($temple['province_name'] ?? 'ບໍ່ລະບຸ'));
                            ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm text-gray-500">ເຈົ້າອະທິການ</h3>
                        <p class="text-gray-800"><?= htmlspecialchars($temple['abbot_name'] ?? '-') ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm text-gray-500">ສະຖານະປັດຈຸບັນ</h3>
                        <p>
                            <?php if($temple['status'] === 'active'): ?>
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">ເປີດໃຊ້ງານ</span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">ປິດໃຊ້ງານ</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- เพิ่มส่วนแสดงข้อมูลที่เกี่ยวข้องก่อนปุ่มยืนยันการลบ -->
            <div class="mt-6 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-medium text-gray-800 mb-2">ຂໍ້ມູນທີ່ເຊື່ອມໂຍງກັບວັດນີ້:</h3>
                
                <?php
                // ตรวจสอบข้อมูลที่เกี่ยวข้อง
                $monk_count = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE temple_id = ?");
                $monk_count->execute([$temple_id]);
                $monks = $monk_count->fetchColumn();
                
                $event_count = $pdo->prepare("SELECT COUNT(*) FROM events WHERE temple_id = ?");
                $event_count->execute([$temple_id]);
                $events = $event_count->fetchColumn();
                
                $user_count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE temple_id = ?");
                $user_count->execute([$temple_id]);
                $users = $user_count->fetchColumn();
                
                $has_relations = ($monks > 0 || $events > 0 || $users > 0);
                ?>
                
                <div class="flex flex-col space-y-2 text-sm mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-user-friends w-5 text-gray-500"></i>
                        <span>ຜູ້ໃຊ້ລະບົບ: <strong><?= $users ?></strong> ຄົນ</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-pray w-5 text-gray-500"></i>
                        <span>ພະສົງ: <strong><?= $monks ?></strong> ລາຍການ</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt w-5 text-gray-500"></i>
                        <span>ກິດຈະກຳ: <strong><?= $events ?></strong> ລາຍການ</span>
                    </div>
                </div>
                
                <?php if ($has_relations): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>ຄຳເຕືອນ:</strong> ວັດນີ້ມີຂໍ້ມູນທີ່ເຊື່ອມໂຍງຢູ່ ການດຳເນີນການຈະເປັນການປິດການໃຊ້ງານແທນການລຶບຂໍ້ມູນ
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <form action="<?= $base_url ?>temples/delete.php?id=<?= $temple_id ?>" method="post" class="mt-6 border-t border-gray-200 pt-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="flex flex-wrap gap-4">
                    <a href="<?= $base_url ?>temples/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition flex items-center">
                        <i class="fas fa-trash-alt mr-2"></i> ຢືນຢັນການລຶບ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>