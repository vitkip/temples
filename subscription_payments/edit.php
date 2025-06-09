<?php
ob_start();
session_start();

$page_title = 'ແກ້ໄຂຂໍ້ມູນການຊຳລະເງິນ';
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

// ດຶງ payment ID ຈາກ GET parameter
$payment_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$payment_id) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ດຶງຂໍ້ມູນການຊຳລະເງິນ
$stmt = $pdo->prepare("
    SELECT sp.*, 
           s.id as subscription_id, s.status as subscription_status, s.start_date, s.end_date, 
           u.username, u.name as user_name, u.email as user_email,
           t.name as temple_name, 
           p.name as plan_name, p.price, p.duration_months
    FROM subscription_payments sp
    JOIN subscriptions s ON sp.subscription_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN temples t ON s.temple_id = t.id
    JOIN subscription_plans p ON s.plan_id = p.id
    WHERE sp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ກວດສອບວ່າ admin ມີສິດເຂົ້າເຖິງຂໍ້ມູນຂອງວັດນີ້ຫຼືບໍ່
if ($is_admin && !$is_superadmin && $payment['temple_id'] != $temple_id) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນການຊຳລະເງິນນີ້";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ຂໍ້ມູນວິທີການຊຳລະເງິນ
$payment_methods = [
    'bank_transfer' => 'ໂອນເງິນຜ່ານທະນາຄານ',
    'mobile_banking' => 'ມືຖືທະນາຄານ',
    'cash' => 'ເງິນສົດ',
    'credit_card' => 'ບັດເຄຣດິດ',
    'other' => 'ອື່ນໆ'
];

$errors = [];

// ປະມວນຜົນການ submit ຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ດຶງຂໍ້ມູນຈາກຟອມ
    $amount = isset($_POST['amount']) && is_numeric($_POST['amount']) ? (float)$_POST['amount'] : null;
    $payment_date = $_POST['payment_date'] ?? null;
    $payment_time = $_POST['payment_time'] ?? '00:00';
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $status = $_POST['status'] ?? null;
    
    // ກວດສອບຂໍ້ມູນ
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

    // ຈັດການອັບໂຫຼດໄຟລ໌ຫຼັກຖານໃໝ່
    $payment_proof = $payment['payment_proof']; // ເກັບຄ່າເກົ່າໄວ້ກ່ອນ
    
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
                // ລຶບໄຟລ໌ເກົ່າຖ້າມີ
                if (!empty($payment['payment_proof']) && file_exists('../' . $payment['payment_proof'])) {
                    unlink('../' . $payment['payment_proof']);
                }
                
                $payment_proof = 'uploads/payments/' . $filename;
            } else {
                $errors[] = "ມີຂໍ້ຜິດພາດໃນການອັບໂຫຼດໄຟລ໌";
            }
        }
    }

    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ອັບເດດຂໍ້ມູນ
    if (empty($errors)) {
        try {
            $sql = "UPDATE subscription_payments 
                    SET amount = ?, payment_date = ?, payment_method = ?, payment_proof = ?, status = ?, notes = ?, updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $amount, 
                $payment_datetime, 
                $payment_method, 
                $payment_proof, 
                $status, 
                $notes,
                $payment_id
            ]);
            
            // ຖ້າສະຖານະເປັນ "approved" ໃຫ້ອັບເດດສະຖານະການສະໝັກສະມາຊິກເປັນ "active"
            if ($status === 'approved' && $payment['subscription_status'] === 'pending') {
                $update_sub = $pdo->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ? AND status = 'pending'");
                $update_sub->execute([$payment['subscription_id']]);
            }
            
            $_SESSION['success'] = "ອັບເດດຂໍ້ມູນການຊຳລະເງິນສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'subscription_payments/view.php?id=' . $payment_id);
            exit;
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການອັບເດດຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}

// ແຍກວັນທີ ແລະ ເວລາ ສຳລັບການສະແດງໃນຟອມ
$payment_date = date('Y-m-d', strtotime($payment['payment_date']));
$payment_time = date('H:i', strtotime($payment['payment_date']));

?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <!-- ສ່ວນຫົວຂອງໜ້າ -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ແກ້ໄຂຂໍ້ມູນການຊຳລະເງິນ</h1>
            <p class="mt-1 text-sm text-gray-600">ປັບປຸງຂໍ້ມູນການຊຳລະເງິນແລະສະຖານະ</p>
        </div>
        
        <div class="flex space-x-2 mt-4 md:mt-0">
            <a href="<?= $base_url ?>subscription_payments/view.php?id=<?= $payment_id ?>" class="inline-flex items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
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

    <!-- ຟອມແກ້ໄຂຂໍ້ມູນ -->
    <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">ຂໍ້ມູນການຊຳລະເງິນ</h2>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="p-6">
            <!-- ຂໍ້ມູນການສະໝັກສະມາຊິກ (ເບິ່ງໄດ້ແຕ່ບໍ່ສາມາດແກ້ໄຂໄດ້) -->
            <div class="bg-gray-50 p-4 rounded-md mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500">ຜູ້ໃຊ້</p>
                        <p class="text-sm font-medium"><?= htmlspecialchars($payment['user_name']) ?> (<?= htmlspecialchars($payment['username']) ?>)</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">ວັດ</p>
                        <p class="text-sm font-medium"><?= htmlspecialchars($payment['temple_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">ແຜນ</p>
                        <p class="text-sm font-medium"><?= htmlspecialchars($payment['plan_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">ລາຄາ</p>
                        <p class="text-sm font-medium"><?= number_format($payment['price']) ?> ກີບ / <?= $payment['duration_months'] ?> ເດືອນ</p>
                    </div>
                </div>
            </div>

            <!-- ຈຳນວນເງິນ -->
            <div class="mb-4">
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">ຈຳນວນເງິນ (ກີບ) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" id="amount" value="<?= htmlspecialchars($payment['amount']) ?>" required min="1" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                <!-- ວັນທີຊຳລະເງິນ -->
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນທີຊຳລະເງິນ <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" id="payment_date" value="<?= htmlspecialchars($payment_date) ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                
                <!-- ເວລາຊຳລະເງິນ -->
                <div>
                    <label for="payment_time" class="block text-sm font-medium text-gray-700 mb-1">ເວລາຊຳລະເງິນ</label>
                    <input type="time" name="payment_time" id="payment_time" value="<?= htmlspecialchars($payment_time) ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
            </div>

            <!-- ວິທີການຊຳລະເງິນ -->
            <div class="mb-4">
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">ວິທີການຊຳລະເງິນ</label>
                <select name="payment_method" id="payment_method" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">-- ເລືອກວິທີການຊຳລະເງິນ --</option>
                    <?php foreach ($payment_methods as $key => $value): ?>
                    <option value="<?= $key ?>" <?= $payment['payment_method'] === $key ? 'selected' : '' ?>><?= $value ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ຫຼັກຖານການຊຳລະເງິນ -->
            <div class="mb-4">
                <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-1">ຫຼັກຖານການຊຳລະເງິນ</label>
                
                <?php if (!empty($payment['payment_proof'])): ?>
                <div class="mb-2">
                    <p class="text-sm text-gray-600 mb-1">ຫຼັກຖານປະຈຸບັນ:</p>
                    <div class="flex items-center">
                        <?php
                        $file_extension = pathinfo($payment['payment_proof'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])):
                        ?>
                            <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank" class="mr-4">
                                <img src="<?= $base_url . $payment['payment_proof'] ?>" alt="ຫຼັກຖານການຊຳລະເງິນ" class="h-16 w-auto object-cover rounded border border-gray-200">
                            </a>
                        <?php elseif (strtolower($file_extension) === 'pdf'): ?>
                            <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank" class="mr-4 flex items-center bg-gray-100 px-3 py-2 rounded">
                                <i class="far fa-file-pdf text-red-500 text-lg mr-2"></i>
                                <span class="text-sm text-gray-700">ເບິ່ງໄຟລ໌ PDF</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank" class="mr-4 text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-download mr-1"></i> ດາວໂຫຼດຫຼັກຖານ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
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
                                <span>ອັບໂຫຼດໄຟລ໌ໃໝ່</span>
                                <input id="payment_proof" name="payment_proof" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf" onchange="previewImage(this)">
                            </label>
                            <p class="pl-1">ຫຼື ລາກໄຟລ໌ມາວາງໃສ່</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, JPEG, PDF ຂະໜາດສູງສຸດ 5MB</p>
                    </div>
                </div>
            </div>

            <!-- ໝາຍເຫດ -->
            <div class="mb-4">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ໝາຍເຫດ</label>
                <textarea name="notes" id="notes" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?= htmlspecialchars($payment['notes']) ?></textarea>
            </div>

            <!-- ສະຖານະ -->
            <div class="mb-6">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະການຊຳລະເງິນ <span class="text-red-500">*</span></label>
                <div class="mt-1 flex items-center space-x-4">
                    <div class="flex items-center">
                        <input id="status_pending" name="status" type="radio" value="pending" <?= $payment['status'] === 'pending' ? 'checked' : '' ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="status_pending" class="ml-2 block text-sm text-gray-700">ລໍຖ້າການຢືນຢັນ</label>
                    </div>
                    <div class="flex items-center">
                        <input id="status_approved" name="status" type="radio" value="approved" <?= $payment['status'] === 'approved' ? 'checked' : '' ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="status_approved" class="ml-2 block text-sm text-gray-700">ອະນຸມັດແລ້ວ</label>
                    </div>
                    <div class="flex items-center">
                        <input id="status_rejected" name="status" type="radio" value="rejected" <?= $payment['status'] === 'rejected' ? 'checked' : '' ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="status_rejected" class="ml-2 block text-sm text-gray-700">ປະຕິເສດ</label>
                    </div>
                </div>
                <p class="mt-1 text-xs text-gray-500">ຖ້າອະນຸມັດແລ້ວ, ສະຖານະການສະໝັກສະມາຊິກຈະຖືກປັບເປັນ "ໃຊ້ງານ" ໂດຍອັດຕະໂນມັດ</p>
            </div>

            <!-- ປຸ່ມກະທຳ -->
            <div class="flex justify-end space-x-3">
                <a href="<?= $base_url ?>subscription_payments/view.php?id=<?= $payment_id ?>" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    ບັນທຶກການປ່ຽນແປງ
                </button>
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