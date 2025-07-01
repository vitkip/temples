<?php
// filepath: c:\xampp\htdocs\temples\events\add_monk.php
ob_start();

$page_title = 'ເພີ່ມພະສົງເຂົ້າຮ່ວມກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບວ່າມີ event_id ຫຼືບໍ່
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_id = (int)$_GET['event_id'];

// ດຶງຂໍ້ມູນກິດຈະກໍາ
$event_stmt = $pdo->prepare("SELECT e.*, t.name as temple_name FROM events e LEFT JOIN temples t ON e.temple_id = t.id WHERE e.id = ?");
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ກວດສອບສິດໃນການເພີ່ມຂໍ້ມູນ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id']));

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເພີ່ມພະສົງໃນກິດຈະກໍານີ້";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ດຶງພະສົງທີ່ສາມາດເພີ່ມໄດ້ (ຈາກວັດດຽວກັນ ແລະ ຍັງບໍ່ໄດ້ເຂົ້າຮ່ວມກິດຈະກໍານີ້)
$monk_stmt = $pdo->prepare("
    SELECT m.*, t.name as temple_name FROM monks m 
    JOIN temples t ON m.temple_id = t.id
    WHERE m.status = 'active'
    AND m.id NOT IN (SELECT monk_id FROM event_monk WHERE event_id = ?)
    ORDER BY m.temple_id = ? DESC, t.name, m.pansa DESC, m.name
");
$monk_stmt->execute([$event_id, $event['temple_id']]);
$available_monks = $monk_stmt->fetchAll();

// ຖ້າບໍ່ມີພະສົງໃຫ້ເພີ່ມ
if (count($available_monks) == 0) {
    $_SESSION['error'] = "ບໍ່ມີພະສົງທີ່ສາມາດເພີ່ມເຂົ້າຮ່ວມກິດຈະກໍານີ້ໄດ້";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ตรวจสอบว่ามีพระในวัดนี้หรือไม่
$check_monks = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE temple_id = ? AND status = 'active'");
$check_monks->execute([$event['temple_id']]);
$monk_count = $check_monks->fetchColumn();

if ($monk_count == 0) {
    $_SESSION['error'] = "ບໍ່ພົບພະສົງໃນວັດທີ່ຈັດກິດຈະກໍານີ້ ກະລຸນາເພີ່ມພະສົງໃນວັດກ່ອນ";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'monk_id' => '',
    'role' => '',
    'note' => ''
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບຂໍ້ມູນທີ່ປ້ອນເຂົ້າມາ
    $form_data = [
        'monk_id' => isset($_POST['monk_id']) ? (int)$_POST['monk_id'] : 0,
        'role' => trim($_POST['role'] ?? ''),
        'note' => trim($_POST['note'] ?? ''),
    ];
    
    // ກົດລະບຽບການກວດສອບຂໍ້ມູນ
    if (empty($form_data['monk_id'])) {
        $errors[] = "ກະລຸນາເລືອກພະສົງ";
    }
    
    // ຖ້າການກວດສອບຜ່ານ
    if (empty($errors)) {
        try {
            // ເພີ່ມພະສົງເຂົ້າຮ່ວມກິດຈະກໍາ
            $stmt = $pdo->prepare("
                INSERT INTO event_monk (event_id, monk_id, role, note) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $event_id,
                $form_data['monk_id'],
                $form_data['role'],
                $form_data['note']
            ]);
            
            $_SESSION['success'] = "ເພີ່ມພະສົງເຂົ້າຮ່ວມກິດຈະກໍາສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ເພີ່ມພະສົງເຂົ້າຮ່ວມກິດຈະກໍາ</h1>
            <p class="text-sm text-gray-600">ກິດຈະກໍາ: <?= htmlspecialchars($event['title']) ?></p>
        </div>
        <div>
            <a href="<?= $base_url ?>events/view.php?id=<?= $event_id ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <!-- ສແດງຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">ພົບຂໍ້ຜິດພາດ <?= count($errors) ?> ລາຍການ</h3>
                <div class="mt-2 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ຟອມເພີ່ມພະສົງ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>events/add_monk.php?event_id=<?= $event_id ?>" method="post" class="p-6">
            <div class="space-y-6">
                <div>
                    <label for="monk_id" class="block text-sm font-medium text-gray-700 mb-2">ເລືອກພະສົງ <span class="text-red-600">*</span></label>
                    <select name="monk_id" id="monk_id" class="form-select rounded-md w-full" required>
                        <option value="">-- ເລືອກພະສົງ --</option>
                        <?php foreach ($available_monks as $monk): ?>
                        <option value="<?= $monk['id'] ?>" <?= $form_data['monk_id'] == $monk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($monk['name']) ?> 
                            <?php if (!empty($monk['pansa'])): ?>
                            (<?= htmlspecialchars($monk['pansa']) ?> ພັນສາ)
                            <?php endif; ?>
                            - <?= htmlspecialchars($monk['temple_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">ບົດບາດໃນກິດຈະກໍາ</label>
                    <input type="text" name="role" id="role" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['role']) ?>" placeholder="ເຊັ່ນ: ປະທານສູດມົນ, ຜູ້ສູດມົນ, ຮັບບາດຕັກບາດ">
                </div>
                
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-2">ໝາຍເຫດ</label>
                    <textarea name="note" id="note" rows="3" class="form-textarea rounded-md w-full" placeholder="ບັນທຶກລາຍລະອຽດເພີ່ມເຕີມ"><?= htmlspecialchars($form_data['note']) ?></textarea>
                </div>
            </div>
            
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                <a href="<?= $base_url ?>events/view.php?id=<?= $event_id ?>" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>