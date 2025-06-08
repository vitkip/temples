<?php
// filepath: c:\xampp\htdocs\temples\events\edit_monk.php
ob_start();

$page_title = 'ແກ້ໄຂຂໍ້ມູນພະສົງເຂົ້າຮ່ວມ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບວ່າມີ ID ແລະ event_id ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    $_SESSION['error'] = "ຂໍ້ມູນບໍ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_monk_id = (int)$_GET['id'];
$event_id = (int)$_GET['event_id'];

// ດຶງຂໍ້ມູນກິດຈະກໍາ ແລະ ພະສົງ
$stmt = $pdo->prepare("
    SELECT em.*, e.temple_id, e.title as event_title, m.name as monk_name
    FROM event_monk em
    JOIN events e ON em.event_id = e.id
    JOIN monks m ON em.monk_id = m.id
    WHERE em.id = ? AND em.event_id = ?
");
$stmt->execute([$event_monk_id, $event_id]);
$participation = $stmt->fetch();

if (!$participation) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງ";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂຂໍ້ມູນ
$can_edit = $_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $participation['temple_id']);

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງນີ້";
    header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
    exit;
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'role' => $participation['role'],
    'note' => $participation['note']
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບຂໍ້ມູນທີ່ປ້ອນເຂົ້າມາ
    $form_data = [
        'role' => trim($_POST['role'] ?? ''),
        'note' => trim($_POST['note'] ?? ''),
    ];
    
    // ຖ້າການກວດສອບຜ່ານ (ບໍ່ມີເງື່ອນໄຂບັງຄັບໃນກໍລະນີນີ້)
    if (empty($errors)) {
        try {
            // ແກ້ໄຂຂໍ້ມູນການເຂົ້າຮ່ວມຂອງພະສົງ
            $stmt = $pdo->prepare("
                UPDATE event_monk SET role = ?, note = ? WHERE id = ?
            ");
            
            $stmt->execute([
                $form_data['role'],
                $form_data['note'],
                $event_monk_id
            ]);
            
            $_SESSION['success'] = "ແກ້ໄຂຂໍ້ມູນການເຂົ້າຮ່ວມສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂຂໍ້ມູນການເຂົ້າຮ່ວມ</h1>
            <p class="text-sm text-gray-600">ພະສົງ: <?= htmlspecialchars($participation['monk_name']) ?></p>
            <p class="text-sm text-gray-600">ກິດຈະກໍາ: <?= htmlspecialchars($participation['event_title']) ?></p>
        </div>
        <div>
            <a href="<?= $base_url ?>events/view.php?id=<?= $event_id ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <!-- ສະແດງຂໍ້ຜິດພາດ -->
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
    
    <!-- ຟອມແກ້ໄຂຂໍ້ມູນ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>events/edit_monk.php?id=<?= $event_monk_id ?>&event_id=<?= $event_id ?>" method="post" class="p-6">
            <div class="space-y-6">
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