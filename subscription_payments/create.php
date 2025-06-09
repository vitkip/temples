<?php
ob_start();
session_start();

$page_title = 'ເພີ່ມການຊຳລະເງິນ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງ subscription_id ຈາກ GET parameter ຖ້າມີ
$subscription_id = isset($_GET['subscription_id']) && is_numeric($_GET['subscription_id']) ? (int)$_GET['subscription_id'] : null;
$subscription = null;

// ດຶງລາຍຊື່ການສະໝັກສະມາຊິກທີ່ສາມາດເພີ່ມການຊຳລະເງິນໄດ້
$subscription_sql = "
    SELECT s.id, s.user_id, s.temple_id, s.plan_id, s.status, s.start_date, s.end_date,
           u.username, u.name as user_name,
           t.name as temple_name,
           p.name as plan_name, p.price, p.duration_months
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN temples t ON s.temple_id = t.id
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.status IN ('active', 'pending', 'expired')
";

// Admin ເຫັນສະເພາະຂໍ້ມູນຂອງວັດຕົນເທົ່ານັ້ນ
if ($is_admin && !$is_superadmin) {
    $subscription_sql .= " AND s.temple_id = ?";
    $stmt = $pdo->prepare($subscription_sql);
    $stmt->execute([$temple_id]);
} else {
    $stmt = $pdo->query($subscription_sql);
}

$subscriptions = $stmt->fetchAll();

// ຖ້າມີ subscription_id, ດຶງຂໍ້ມູນຂອງການສະໝັກສະມາຊິກນັ້ນ
if ($subscription_id) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.temple_id, s.plan_id, s.status, s.start_date, s.end_date,
               u.username, u.name as user_name,
               t.name as temple_name,
               p.name as plan_name, p.price, p.duration_months
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN temples t ON s.temple_id = t.id
        JOIN subscription_plans p ON s.plan_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການສະໝັກສະມາຊິກ";
        header('Location: ' . $base_url . 'subscription_payments/');
        exit;
    }
    
    // ກວດສອບວ່າ admin ມີສິດເຂົ້າເຖິງຂໍ້ມູນຂອງວັດນີ້ຫຼືບໍ່
    if ($is_admin && !$is_superadmin && $subscription['temple_id'] != $temple_id) {
        $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນການສະໝັກສະມາຊິກນີ້";
        header('Location: ' . $base_url . 'subscription_payments/');
        exit;
    }
}

// ປະມວນຜົນຟອມເມື່ອມີການ submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // ດຶງຂໍ້ມູນຈາກຟອມ
    $subscription_id = isset($_POST['subscription_id']) && is_numeric($_POST['subscription_id']) ? (int)$_POST['subscription_id'] : null;
    $amount = isset($_POST['amount']) && is_numeric($_POST['amount']) ? (float)$_POST['amount'] : null;
    $payment_date = $_POST['payment_date'] ?? null;
    $payment_time = $_POST['payment_time'] ?? '00:00';
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    
    // ກວດສອບຂໍ້ມູນ
    if (!$subscription_id) {
        $errors[] = "ກະລຸນາເລືອກການສະໝັກສະມາຊິກ";
    }
    
    if (!$amount || $amount <= 0) {
        $errors[] = "ກະລຸນາລະບຸຈຳນວນເງິນທີ່ຖືກຕ້ອງ";
    }
    
    if (!$payment_date) {
        $errors[] = "ກະລຸນາລະບຸວັນທີຊຳລະເງິນ";
    } else {
        // ລວມວັນທີແລະເວລາເຂົ້າກັນ
        $payment_datetime = $payment_date . ' ' . $payment_time . ':00';
    }
    
    if (!in_array($status, ['pending', 'approved', 'rejected'])) {
        $errors[] = "ສະຖານະບໍ່ຖືກຕ້ອງ";
    }
    
    // ປະມວນຜົນໄຟລ໌ຫຼັກຖານການຊຳລະເງິນ
    $payment_proof = null;
    if (!empty($_FILES['payment_proof']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "ຮູບແບບໄຟລ໌ບໍ່ຖືກຕ້ອງ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG, ແລະ PDF ເທົ່ານັ້ນ";
        } else if ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) {
            $errors[] = "ຂະໜາດໄຟລ໌ໃຫຍ່ເກີນໄປ. ກະລຸນາອັບໂຫຼດໄຟລ໌ທີ່ມີຂະໜາດນ້ອຍກວ່າ 5MB";
        } else {
            $upload_dir = '../uploads/payments/';
            
            // ສ້າງໂຟລເດີຖ້າຍັງບໍ່ມີ
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = 'payment_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
                $payment_proof = 'uploads/payments/' . $filename;
            } else {
                $errors[] = "ມີຂໍ້ຜິດພາດໃນການອັບໂຫຼດໄຟລ໌";
            }
        }
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ບັນທຶກຂໍ້ມູນ
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO subscription_payments 
                    (subscription_id, amount, payment_date, payment_method, payment_proof, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $subscription_id, 
                $amount, 
                $payment_datetime, 
                $payment_method, 
                $payment_proof, 
                $status, 
                $notes
            ]);
            
            // ຖ້າສະຖານະເປັນ "approved", ໃຫ້ປັບປຸງສະຖານະຂອງການສະໝັກສະມາຊິກເປັນ "active"
            if ($status === 'approved') {
                $update_sub = $pdo->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ? AND status = 'pending'");
                $update_sub->execute([$subscription_id]);
            }
            
            $_SESSION['success'] = "ບັນທຶກຂໍ້ມູນການຊຳລະເງິນສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'subscription_payments/');
            exit;
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}

// ຂໍ້ມູນວິທີການຊຳລະເງິນ
$payment_methods = [
    'bank_transfer' => 'ໂອນເງິນຜ່ານທະນາຄານ',
    'mobile_banking' => 'ມືຖືທະນາຄານ',
    'cash' => 'ເງິນສົດ',
    'credit_card' => 'ບັດເຄຣດິດ',
    'other' => 'ອື່ນໆ'
];
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ເພີ່ມການຊຳລະເງິນ</h1>
            <p class="mt-1 text-sm text-gray-600">ບັນທຶກຂໍ້ມູນການຊຳລະເງິນໃໝ່ສຳລັບການສະໝັກສະມາຊິກ</p>
        </div>
        <a href="<?= $base_url ?>subscription_payments/" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
        </a>
    </div>

    <!-- ສະແດງຂໍ້ຜິດພາດ -->
    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
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

    <!-- ຟອມເພີ່ມຂໍ້ມູນ -->
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <form method="post" enctype="multipart/form-data" class="p-6">
            <div class="grid grid-cols-1 gap-6">
                <!-- ເລືອກການສະໝັກສະມາຊິກ -->
                <div>
                    <label for="subscription_id" class="block text-sm font-medium text-gray-700 mb-1">ການສະໝັກສະມາຊິກ <span class="text-red-500">*</span></label>
                    <select name="subscription_id" id="subscription_id" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" <?= $subscription_id ? 'disabled' : '' ?>>
                        <option value="">-- ເລືອກການສະໝັກສະມາຊິກ --</option>
                        <?php foreach ($subscriptions as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= ($subscription_id == $sub['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['user_name']) ?> (<?= htmlspecialchars($sub['username']) ?>) - 
                            <?= htmlspecialchars($sub['temple_name']) ?> - 
                            <?= htmlspecialchars($sub['plan_name']) ?> 
                            (<?= number_format($sub['price']) ?> ກີບ)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($subscription_id): ?>
                    <input type="hidden" name="subscription_id" value="<?= $subscription_id ?>">
                    <?php endif; ?>
                </div>
                
                <!-- ຂໍ້ມູນການສະໝັກທີ່ເລືອກ (ສະແດງເມື່ອເລືອກການສະໝັກແລ້ວ) -->
                <?php if ($subscription): ?>
                <div class="bg-gray-50 p-4 rounded-md">
                    <h3 class="text-sm font-medium text-gray-700 mb-2">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">ຜູ້ໃຊ້</p>
                            <p class="text-sm font-medium"><?= htmlspecialchars($subscription['user_name']) ?> (<?= htmlspecialchars($subscription['username']) ?>)</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">ວັດ</p>
                            <p class="text-sm font-medium"><?= htmlspecialchars($subscription['temple_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">ແຜນ</p>
                            <p class="text-sm font-medium"><?= htmlspecialchars($subscription['plan_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">ລາຄາ</p>
                            <p class="text-sm font-medium"><?= number_format($subscription['price']) ?> ກີບ / <?= $subscription['duration_months'] ?> ເດືອນ</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ຈຳນວນເງິນ -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">ຈຳນວນເງິນ (ກີບ) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" id="amount" required min="1" value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ($subscription ? $subscription['price'] : '') ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- ວິທີການຊຳລະເງິນ -->
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">ວິທີການຊຳລະເງິນ</label>
                        <select name="payment_method" id="payment_method" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- ເລືອກວິທີການຊຳລະເງິນ --</option>
                            <?php foreach ($payment_methods as $key => $value): ?>
                            <option value="<?= $key ?>" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] === $key) ? 'selected' : '' ?>><?= $value ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ວັນທີຊຳລະເງິນ -->
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນທີຊຳລະເງິນ <span class="text-red-500">*</span></label>
                        <input type="date" name="payment_date" id="payment_date" required value="<?= isset($_POST['payment_date']) ? htmlspecialchars($_POST['payment_date']) : date('Y-m-d') ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- ເວລາຊຳລະເງິນ -->
                    <div>
                        <label for="payment_time" class="block text-sm font-medium text-gray-700 mb-1">ເວລາຊຳລະເງິນ</label>
                        <input type="time" name="payment_time" id="payment_time" value="<?= isset($_POST['payment_time']) ? htmlspecialchars($_POST['payment_time']) : date('H:i') ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <!-- ຫຼັກຖານການຊຳລະເງິນ -->
                <div>
                    <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-1">ຫຼັກຖານການຊຳລະເງິນ</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                        <div class="space-y-1 text-center">
                            <div id="previewContainer" class="flex justify-center mb-3" style="display: none;">
                                <img id="preview" src="#" alt="ຕົວຢ່າງຫຼັກຖານການຊຳລະເງິນ" class="h-32 object-cover">
                            </div>
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="payment_proof" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                    <span>ອັບໂຫຼດໄຟລ໌</span>
                                    <input id="payment_proof" name="payment_proof" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf" onchange="previewImage(this)">
                                </label>
                                <p class="pl-1">ຫຼື ລາກໄຟລ໌ມາວາງໃສ່</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG, JPEG, PDF ຂະໜາດສູງສຸດ 5MB</p>
                        </div>
                    </div>
                </div>

                <!-- ໝາຍເຫດ -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ໝາຍເຫດ</label>
                    <textarea name="notes" id="notes" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                </div>

                <!-- ສະຖານະ (ສຳລັບ superadmin ແລະ admin ເທົ່ານັ້ນ) -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະການຊຳລະເງິນ</label>
                    <select name="status" id="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="pending" <?= (isset($_POST['status']) && $_POST['status'] === 'pending') || !isset($_POST['status']) ? 'selected' : '' ?>>ລໍຖ້າການຢືນຢັນ</option>
                        <option value="approved" <?= isset($_POST['status']) && $_POST['status'] === 'approved' ? 'selected' : '' ?>>ອະນຸມັດແລ້ວ</option>
                        <option value="rejected" <?= isset($_POST['status']) && $_POST['status'] === 'rejected' ? 'selected' : '' ?>>ປະຕິເສດ</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">ຖ້າອະນຸມັດແລ້ວ, ການສະໝັກສະມາຊິກຈະຖືກເປີດໃຊ້ງານໂດຍອັດຕະໂນມັດ</p>
                </div>

                <!-- ປຸ່ມບັນທຶກ -->
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript ສຳລັບ preview ຮູບພາບ -->
<script>
function previewImage(input) {
    var preview = document.getElementById('preview');
    var previewContainer = document.getElementById('previewContainer');
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            // ເຊັກນາມສະກຸນໄຟລ໌
            var fileName = input.files[0].name;
            var fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt === 'pdf') {
                // ຖ້າເປັນໄຟລ໌ PDF ໃຫ້ສະແດງໄອຄອນ
                preview.src = '<?= $base_url ?>assets/images/pdf-icon.png';
            } else {
                // ຖ້າເປັນຮູບພາບໃຫ້ສະແດງຮູບຕົວຢ່າງ
                preview.src = e.target.result;
            }
            
            previewContainer.style.display = 'flex';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>