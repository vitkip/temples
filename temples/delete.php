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
$stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
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
        // Check for related records first (monks, events)
        $monk_check = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE temple_id = ?");
        $monk_check->execute([$temple_id]);
        $has_monks = $monk_check->fetchColumn() > 0;
        
        $event_check = $pdo->prepare("SELECT COUNT(*) FROM events WHERE temple_id = ?");
        $event_check->execute([$temple_id]);
        $has_events = $event_check->fetchColumn() > 0;
        
        // If there are related records, use soft delete (update status)
        if ($has_monks || $has_events) {
            $stmt = $pdo->prepare("UPDATE temples SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$temple_id]);
            $_SESSION['success'] = "ອັບເດດສະຖານະວັດເປັນ 'ປິດໃຊ້ງານ' ແລ້ວ ເນື່ອງຈາກມີຂໍ້ມູນພະສົງ ຫຼື ກິດຈະກຳທີ່ກ່ຽວຂ້ອງ";
        } else {
            // Hard delete if no related records
            $stmt = $pdo->prepare("DELETE FROM temples WHERE id = ?");
            $stmt->execute([$temple_id]);
            $_SESSION['success'] = "ລຶບຂໍ້ມູນວັດສຳເລັດແລ້ວ";
        }
        
        header('Location: ' . $base_url . 'temples/');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ";
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
                        <p class="text-gray-800"><?= htmlspecialchars($temple['district'] . ', ' . $temple['province']) ?></p>
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