<?php
// filepath: c:\xampp\htdocs\temples\events\edit.php
ob_start();

$page_title = 'ແກ້ໄຂກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ตรวจสอบสิทธิ์ login
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'auth/');
    exit;
}

// ตรวจสอบ id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}
$event_id = (int)$_GET['id'];

// ดึงข้อมูลกิจกรรม
$stmt = $pdo->prepare("
    SELECT e.*, t.province_id, t.id as temple_id
    FROM events e
    LEFT JOIN temples t ON e.temple_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ตรวจสอบสิทธิ์การแก้ไข
$can_edit = false;
if ($_SESSION['user']['role'] === 'superadmin') {
    $can_edit = true;
} elseif ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id']) {
    $can_edit = true;
} elseif ($_SESSION['user']['role'] === 'province_admin') {
    // province_admin: ตรวจสอบว่าวัดนี้อยู่ในแขวงที่มีสิทธิ์
    $province_stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $province_ids = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($event['province_id'] && in_array($event['province_id'], $province_ids)) {
        $can_edit = true;
    }
}
if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂກິດຈະກໍານີ້";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ดึงวัดสำหรับ dropdown
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} elseif ($_SESSION['user']['role'] === 'province_admin') {
    $province_stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $province_ids = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($province_ids) {
        $in = str_repeat('?,', count($province_ids) - 1) . '?';
        $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE province_id IN ($in) AND status = 'active' ORDER BY name");
        $temple_stmt->execute($province_ids);
        $temples = $temple_stmt->fetchAll();
    } else {
        $temples = [];
    }
} else {
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// กำหนดค่าเริ่มต้น
$errors = [];
$form_data = [
    'title' => $event['title'],
    'description' => $event['description'],
    'event_date' => $event['event_date'],
    'event_time' => $event['event_time'],
    'location' => $event['location'],
    'temple_id' => $event['temple_id'],
];

// อัปเดตข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'event_date' => trim($_POST['event_date'] ?? ''),
        'event_time' => trim($_POST['event_time'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0,
    ];

    if (empty($form_data['title'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ກິດຈະກໍາ";
    }
    if (empty($form_data['event_date'])) {
        $errors[] = "ກະລຸນາປ້ອນວັນທີກິດຈະກໍາ";
    }
    if (empty($form_data['event_time'])) {
        $errors[] = "ກະລຸນາປ້ອນເວລາກິດຈະກໍາ";
    }
    if (empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE events SET
                    temple_id = :temple_id,
                    title = :title,
                    description = :description,
                    event_date = :event_date,
                    event_time = :event_time,
                    location = :location
                WHERE id = :id
            ");
            $stmt->execute([
                ':temple_id' => $form_data['temple_id'],
                ':title' => $form_data['title'],
                ':description' => $form_data['description'],
                ':event_date' => $form_data['event_date'],
                ':event_time' => $form_data['event_time'],
                ':location' => $form_data['location'],
                ':id' => $event_id
            ]);
            $_SESSION['success'] = "ແກ້ໄຂຂໍ້ມູນສຳເລັດ";
            header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
            exit;
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂກິດຈະກໍາ</h1>
            <p class="text-sm text-gray-600">ຟອມແກ້ໄຂຂໍ້ມູນກິດຈະກໍາ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>events/view.php?id=<?= $event_id ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
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

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="" method="post" class="p-6">
            <div class="grid grid-cols-1 gap-6">
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນກິດຈະກໍາ</h2>
                    <div class="mb-4">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">ຊື່ກິດຈະກໍາ <span class="text-red-600">*</span></label>
                        <input type="text" name="title" id="title" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['title']) ?>" required>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນທີຈັດກິດຈະກໍາ <span class="text-red-600">*</span></label>
                            <input type="date" name="event_date" id="event_date" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['event_date']) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="event_time" class="block text-sm font-medium text-gray-700 mb-2">ເວລາຈັດກິດຈະກໍາ <span class="text-red-600">*</span></label>
                            <input type="time" name="event_time" id="event_time" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['event_time']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-2">ສະຖານທີ່ຈັດກິດຈະກໍາ</label>
                        <input type="text" name="location" id="location" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['location']) ?>">
                    </div>
                    <div class="mb-4">
                        <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-2">ວັດ <span class="text-red-600">*</span></label>
                        <select name="temple_id" id="temple_id" class="form-select rounded-md w-full" required <?= $_SESSION['user']['role'] === 'admin' ? 'disabled' : '' ?>>
                            <option value="">ເລືອກວັດ</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $temple['id'] == $form_data['temple_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <input type="hidden" name="temple_id" value="<?= $_SESSION['user']['temple_id'] ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">ລາຍລະອຽດກິດຈະກໍາ</label>
                        <textarea name="description" id="description" rows="4" class="form-textarea rounded-md w-full"><?= htmlspecialchars($form_data['description']) ?></textarea>
                    </div>
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
ob_end_flush();
require_once '../includes/footer.php';
?>