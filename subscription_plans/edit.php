<?php
// filepath: c:\xampp\htdocs\temples\subscription_plans\edit.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງ (ສະເພາະ superadmin)
if ($_SESSION['user']['role'] !== 'superadmin') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກວດສອບ ID ຂອງແຜນ
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ແຜນການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}
$plan_id = (int)$_GET['id'];

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ດຶງຂໍ້ມູນແຜນທີ່ຕ້ອງການແກ້ໄຂ
try {
    $sql = "SELECT * FROM subscription_plans WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();

    if (!$plan) {
        $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ";
        header('Location: ' . $base_url . 'subscription_plans/');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage();
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ກໍານົດຕົວແປເພື່ອເກັບຂໍ້ຜິດພາດແລະຂໍ້ມູນຟອມ
$errors = [];
$form_data = [
    'name' => $plan['name'],
    'description' => $plan['description'],
    'price' => $plan['price'],
    'duration_months' => $plan['duration_months'],
    'features' => $plan['features'],
    'status' => $plan['status']
];

// ປະມວນຜົນການສົ່ງແບບຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $plan_id);
        exit;
    }
    
    // ຮັບຂໍ້ມູນແລະກວດສອບຄວາມຖືກຕ້ອງ
    $form_data = [
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'price' => $_POST['price'] ?? '',
        'duration_months' => $_POST['duration_months'] ?? '1',
        'features' => $_POST['features'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // ກວດສອບຄວາມຖືກຕ້ອງຂອງຂໍ້ມູນ
    if (empty($form_data['name'])) {
        $errors['name'] = 'ກະລຸນາລະບຸຊື່ແຜນ';
    } elseif (strlen($form_data['name']) > 100) {
        $errors['name'] = 'ຊື່ແຜນຍາວເກີນໄປ (ສູງສຸດ 100 ຕົວອັກສອນ)';
    }
    
    if (empty($form_data['price'])) {
        $errors['price'] = 'ກະລຸນາລະບຸລາຄາ';
    } elseif (!is_numeric($form_data['price']) || $form_data['price'] < 0) {
        $errors['price'] = 'ລາຄາຕ້ອງເປັນຕົວເລກທີ່ມີຄ່າບໍ່ຕິດລົບ';
    }
    
    if (empty($form_data['duration_months'])) {
        $errors['duration_months'] = 'ກະລຸນາລະບຸໄລຍະເວລາ';
    } elseif (!is_numeric($form_data['duration_months']) || $form_data['duration_months'] < 1) {
        $errors['duration_months'] = 'ໄລຍະເວລາຕ້ອງເປັນຕົວເລກທີ່ມີຄ່າຢ່າງໜ້ອຍ 1';
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ອັບເດດຂໍ້ມູນໃນຖານຂໍ້ມູນ
    if (empty($errors)) {
        try {
            $sql = "
                UPDATE subscription_plans SET
                name = ?, description = ?, price = ?, 
                duration_months = ?, features = ?, status = ?
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $form_data['name'],
                $form_data['description'],
                $form_data['price'],
                $form_data['duration_months'],
                $form_data['features'],
                $form_data['status'],
                $plan_id
            ]);
            
            $_SESSION['success'] = "ອັບເດດແຜນການສະໝັກສະມາຊິກສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'subscription_plans/');
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການອັບເດດຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}

$page_title = "ແກ້ໄຂແຜນການສະໝັກສະມາຊິກ #" . $plan_id;
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂແຜນການສະໝັກສະມາຊິກ #<?= $plan_id ?></h1>
            <p class="text-sm text-gray-600">ປັບປຸງຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>subscription_plans/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ແບບຟອມແກ້ໄຂແຜນການສະໝັກສະມາຊິກ -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <form action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $plan_id ?>" method="POST" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- ຊື່ແຜນ -->
            <div class="mb-6">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ຊື່ແຜນການສະໝັກ <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" class="form-input rounded-md shadow-sm w-full" value="<?= htmlspecialchars($form_data['name']) ?>" required>
                <?php if (isset($errors['name'])): ?>
                <p class="mt-1 text-sm text-red-600"><?= $errors['name'] ?></p>
                <?php endif; ?>
            </div>
            
            <!-- ລາຍລະອຽດ -->
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">ລາຍລະອຽດ</label>
                <textarea name="description" id="description" rows="3" class="form-textarea rounded-md shadow-sm w-full"><?= htmlspecialchars($form_data['description']) ?></textarea>
            </div>
            
            <!-- ລາຄາແລະໄລຍະເວລາ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">ລາຄາ (ກີບ) <span class="text-red-500">*</span></label>
                    <input type="number" name="price" id="price" class="form-input rounded-md shadow-sm w-full" min="0" step="1000" value="<?= $form_data['price'] ?>" required>
                    <?php if (isset($errors['price'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['price'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="duration_months" class="block text-sm font-medium text-gray-700 mb-1">ໄລຍະເວລາ (ເດືອນ) <span class="text-red-500">*</span></label>
                    <input type="number" name="duration_months" id="duration_months" class="form-input rounded-md shadow-sm w-full" min="1" value="<?= $form_data['duration_months'] ?>" required>
                    <?php if (isset($errors['duration_months'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['duration_months'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ຄຸນສົມບັດ -->
            <div class="mb-6">
                <label for="features" class="block text-sm font-medium text-gray-700 mb-1">ຄຸນສົມບັດ</label>
                <p class="text-xs text-gray-500 mb-2">ປ້ອນຄຸນສົມບັດແຕ່ລະລາຍການໃນແຕ່ລະແຖວ</p>
                <textarea name="features" id="features" rows="4" class="form-textarea rounded-md shadow-sm w-full" placeholder="- ຄຸນສົມບັດທີ 1&#10;- ຄຸນສົມບັດທີ 2&#10;- ຄຸນສົມບັດທີ 3"><?= htmlspecialchars($form_data['features']) ?></textarea>
            </div>
            
            <!-- ສະຖານະ -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <div class="flex space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="status" value="active" class="form-radio" <?= $form_data['status'] === 'active' ? 'checked' : '' ?>>
                        <span class="ml-2">ເປີດໃຊ້ງານ</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="status" value="inactive" class="form-radio" <?= $form_data['status'] === 'inactive' ? 'checked' : '' ?>>
                        <span class="ml-2">ປິດໃຊ້ງານ</span>
                    </label>
                </div>
            </div>
            
            <!-- ປຸ່ມສົ່ງຟອມ -->
            <div class="flex justify-end space-x-3">
                <a href="<?= $base_url ?>subscription_plans/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກການປ່ຽນແປງ
                </button>
            </div>
        </form>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>