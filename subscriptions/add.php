<?php
// filepath: c:\xampp\htdocs\temples\subscriptions\add.php
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

// ກວດສອບສິດໃນການເຂົ້າເຖິງ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງຫມົດ
$users_sql = "SELECT id, username, name, role, temple_id FROM users";
$users_params = [];

// ຖ້າເປັນ admin ໃຫ້ດຶງສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ
if ($is_admin && $temple_id) {
    $users_sql .= " WHERE temple_id = ?";
    $users_params[] = $temple_id;
}

$users_sql .= " ORDER BY name";
$users_stmt = $pdo->prepare($users_sql);
$users_stmt->execute($users_params);
$users = $users_stmt->fetchAll();

// ດຶງຂໍ້ມູນວັດທັງຫມົດ
$temples_sql = "SELECT id, name FROM temples";
$temples_params = [];

// ຖ້າເປັນ admin ໃຫ້ດຶງສະເພາະວັດຂອງຕົນເອງ
if ($is_admin && $temple_id) {
    $temples_sql .= " WHERE id = ?";
    $temples_params[] = $temple_id;
}

$temples_sql .= " ORDER BY name";
$temples_stmt = $pdo->prepare($temples_sql);
$temples_stmt->execute($temples_params);
$temples = $temples_stmt->fetchAll();

// ດຶງຂໍ້ມູນແຜນສະມາຊິກທັງຫມົດ
$plans_stmt = $pdo->prepare("SELECT id, name, price, duration_months FROM subscription_plans WHERE status = 'active' ORDER BY price");
$plans_stmt->execute();
$plans = $plans_stmt->fetchAll();

// ກໍານົດຕົວແປເພື່ອເກັບຂໍ້ຜິດພາດແລະຂໍ້ມູນຟອມ
$errors = [];
$form_data = [
    'user_id' => '',
    'temple_id' => $temple_id ?? '',
    'plan_id' => '',
    'status' => 'active',
    'start_date' => date('Y-m-d'),
    'end_date' => '',
    'amount' => '',
    'payment_method' => 'cash',
    'payment_reference' => '',
    'notes' => ''
];

// ປະມວນຜົນການສົ່ງແບບຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ຮັບຂໍ້ມູນແລະກວດສອບຄວາມຖືກຕ້ອງ
    $form_data = [
        'user_id' => $_POST['user_id'] ?? '',
        'temple_id' => $_POST['temple_id'] ?? '',
        'plan_id' => $_POST['plan_id'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'start_date' => $_POST['start_date'] ?? '',
        'end_date' => $_POST['end_date'] ?? '',
        'amount' => $_POST['amount'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'payment_reference' => $_POST['payment_reference'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];
    
    // ກວດສອບຄວາມຖືກຕ້ອງຂອງຂໍ້ມູນ
    if (empty($form_data['user_id'])) {
        $errors['user_id'] = 'ກະລຸນາເລືອກຜູ້ໃຊ້';
    }
    
    if (empty($form_data['temple_id'])) {
        $errors['temple_id'] = 'ກະລຸນາເລືອກວັດ';
    }
    
    if (empty($form_data['start_date'])) {
        $errors['start_date'] = 'ກະລຸນາລະບຸວັນທີ່ເລີ່ມຕົ້ນ';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form_data['start_date'])) {
        $errors['start_date'] = 'ຮູບແບບວັນທີບໍ່ຖືກຕ້ອງ (YYYY-MM-DD)';
    }
    
    if (empty($form_data['end_date'])) {
        $errors['end_date'] = 'ກະລຸນາລະບຸວັນທີ່ສິ້ນສຸດ';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form_data['end_date'])) {
        $errors['end_date'] = 'ຮູບແບບວັນທີບໍ່ຖືກຕ້ອງ (YYYY-MM-DD)';
    } elseif (strtotime($form_data['end_date']) <= strtotime($form_data['start_date'])) {
        $errors['end_date'] = 'ວັນທີ່ສິ້ນສຸດຕ້ອງຫຼັງຈາກວັນທີ່ເລີ່ມຕົ້ນ';
    }
    
    if (empty($form_data['amount'])) {
        $errors['amount'] = 'ກະລຸນາລະບຸຈໍານວນເງິນ';
    } elseif (!is_numeric($form_data['amount']) || $form_data['amount'] < 0) {
        $errors['amount'] = 'ຈໍານວນເງິນຕ້ອງເປັນຕົວເລກທີ່ມີຄ່າບໍ່ຕິດລົບ';
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ເພີ່ມຂໍ້ມູນໃສ່ຖານຂໍ້ມູນ
    if (empty($errors)) {
        try {
            $insert_sql = "
                INSERT INTO subscriptions 
                (user_id, temple_id, plan_id, status, start_date, end_date, amount, payment_method, payment_reference, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([
                $form_data['user_id'],
                $form_data['temple_id'],
                !empty($form_data['plan_id']) ? $form_data['plan_id'] : null,
                $form_data['status'],
                $form_data['start_date'],
                $form_data['end_date'],
                $form_data['amount'],
                $form_data['payment_method'],
                $form_data['payment_reference'],
                $form_data['notes']
            ]);
            
            $_SESSION['success'] = "ເພີ່ມການສະໝັກສະມາຊິກສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'subscriptions/');
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}

// ຖ້າເລືອກແຜນການສະໝັກສະມາຊິກ, ຄໍານວນວັນທີ່ສິ້ນສຸດແລະຈໍານວນເງິນ
if (!empty($_GET['plan_id']) && is_numeric($_GET['plan_id'])) {
    try {
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
        $plan_stmt->execute([$_GET['plan_id']]);
        $plan = $plan_stmt->fetch();
        
        if ($plan) {
            $form_data['plan_id'] = $plan['id'];
            $form_data['amount'] = $plan['price'];
            
            // ຄໍານວນວັນທີ່ສິ້ນສຸດ
            $start_date = new DateTime($form_data['start_date']);
            $end_date = clone $start_date;
            $end_date->modify('+' . $plan['duration_months'] . ' months');
            $form_data['end_date'] = $end_date->format('Y-m-d');
        }
    } catch (Exception $e) {
        // ຈັດການຂໍ້ຜິດພາດຖ້າມີ
    }
}

$page_title = "ເພີ່ມການສະໝັກສະມາຊິກ";
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ເພີ່ມການສະໝັກສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ເພີ່ມຂໍ້ມູນການສະໝັກສະມາຊິກໃໝ່</p>
        </div>
        <div>
            <a href="<?= $base_url ?>subscriptions/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
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
    
    <!-- ແບບຟອມເພີ່ມການສະໝັກສະມາຊິກ -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- ຜູ້ໃຊ້ແລະວັດ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">ຜູ້ໃຊ້ <span class="text-red-500">*</span></label>
                    <select name="user_id" id="user_id" class="form-select rounded-md shadow-sm w-full">
                        <option value="">-- ເລືອກຜູ້ໃຊ້ --</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $form_data['user_id'] == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['user_id'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['user_id'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ <span class="text-red-500">*</span></label>
                    <select name="temple_id" id="temple_id" class="form-select rounded-md shadow-sm w-full" <?= $is_admin && $temple_id ? 'disabled' : '' ?>>
                        <option value="">-- ເລືອກວັດ --</option>
                        <?php foreach ($temples as $temple): ?>
                        <option value="<?= $temple['id'] ?>" <?= $form_data['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($temple['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_admin && $temple_id): ?>
                    <input type="hidden" name="temple_id" value="<?= $temple_id ?>">
                    <?php endif; ?>
                    <?php if (isset($errors['temple_id'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['temple_id'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ແຜນການສະໝັກສະມາຊິກ -->
            <div class="mb-6">
                <label for="plan_id" class="block text-sm font-medium text-gray-700 mb-1">ແຜນການສະໝັກສະມາຊິກ</label>
                <select name="plan_id" id="plan_id" class="form-select rounded-md shadow-sm w-full" onchange="updatePlanDetails(this.value)">
                    <option value="">-- ເລືອກແຜນ (ຖ້າມີ) --</option>
                    <?php foreach ($plans as $plan): ?>
                    <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>" data-duration="<?= $plan['duration_months'] ?>" <?= $form_data['plan_id'] == $plan['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($plan['name']) ?> - <?= number_format($plan['price'], 0, ',', '.') ?> ກີບ (<?= $plan['duration_months'] ?> ເດືອນ)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- ສະຖານະ -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <div class="flex space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="status" value="active" class="form-radio" <?= $form_data['status'] === 'active' ? 'checked' : '' ?>>
                        <span class="ml-2">ໃຊ້ງານ</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="status" value="pending" class="form-radio" <?= $form_data['status'] === 'pending' ? 'checked' : '' ?>>
                        <span class="ml-2">ລໍຖ້າ</span>
                    </label>
                </div>
            </div>
            
            <!-- ໄລຍະເວລາແລະຈໍານວນເງິນ -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນທີ່ເລີ່ມຕົ້ນ <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-input rounded-md shadow-sm w-full" value="<?= $form_data['start_date'] ?>">
                    <?php if (isset($errors['start_date'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['start_date'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນທີ່ສິ້ນສຸດ <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-input rounded-md shadow-sm w-full" value="<?= $form_data['end_date'] ?>">
                    <?php if (isset($errors['end_date'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['end_date'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">ຈໍານວນເງິນ (ກີບ) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="amount" class="form-input rounded-md shadow-sm w-full" min="0" step="1000" value="<?= $form_data['amount'] ?>">
                    <?php if (isset($errors['amount'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= $errors['amount'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ການຊໍາລະເງິນ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">ວິທີການຊໍາລະເງິນ</label>
                    <select name="payment_method" id="payment_method" class="form-select rounded-md shadow-sm w-full">
                        <option value="cash" <?= $form_data['payment_method'] === 'cash' ? 'selected' : '' ?>>ເງິນສົດ</option>
                        <option value="bank_transfer" <?= $form_data['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>ໂອນຜ່ານທະນາຄານ</option>
                        <option value="qr_payment" <?= $form_data['payment_method'] === 'qr_payment' ? 'selected' : '' ?>>QR Payment</option>
                        <option value="other" <?= $form_data['payment_method'] === 'other' ? 'selected' : '' ?>>ອື່ນໆ</option>
                    </select>
                </div>
                
                <div>
                    <label for="payment_reference" class="block text-sm font-medium text-gray-700 mb-1">ເລກອ້າງອີງການຊໍາລະ</label>
                    <input type="text" name="payment_reference" id="payment_reference" class="form-input rounded-md shadow-sm w-full" value="<?= htmlspecialchars($form_data['payment_reference']) ?>">
                </div>
            </div>
            
            <!-- ຫມາຍເຫດ -->
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ຫມາຍເຫດ</label>
                <textarea name="notes" id="notes" rows="3" class="form-textarea rounded-md shadow-sm w-full"><?= htmlspecialchars($form_data['notes']) ?></textarea>
            </div>
            
            <!-- ປຸ່ມສົ່ງຟອມ -->
            <div class="flex justify-end space-x-3">
                <a href="<?= $base_url ?>subscriptions/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function updatePlanDetails(planId) {
        if (!planId) return;
        
        const planSelect = document.getElementById('plan_id');
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        const duration = selectedOption.getAttribute('data-duration');
        
        if (price) {
            document.getElementById('amount').value = price;
        }
        
        // ຄໍານວນວັນທີ່ສິ້ນສຸດ
        if (duration) {
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                const date = new Date(startDate);
                date.setMonth(date.getMonth() + parseInt(duration));
                
                // Format the date as YYYY-MM-DD
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                
                document.getElementById('end_date').value = `${year}-${month}-${day}`;
            }
        }
    }
    
    document.getElementById('start_date').addEventListener('change', function() {
        const planId = document.getElementById('plan_id').value;
        if (planId) {
            updatePlanDetails(planId);
        }
    });
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>