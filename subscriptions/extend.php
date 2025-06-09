<?php
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

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$current_user_id = $_SESSION['user']['id'] ?? null;
$temple_id = $_SESSION['user']['temple_id'] ?? null;

// ດຶງ subscription ID ຈາກ GET parameter
$subscription_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$subscription_id) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ດຶງຂໍ້ມູນການສະໝັກສະມາຊິກ
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name as user_name, u.email as user_email,
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
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ
if (!$is_superadmin) {
    if ($is_admin && $subscription['temple_id'] != $temple_id) {
        // ຖ້າເປັນ admin ແຕ່ບໍ່ແມ່ນຂໍ້ມູນຂອງວັດຕົນເອງ
        $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນການສະໝັກສະມາຊິກນີ້";
        header('Location: ' . $base_url . 'subscriptions/');
        exit;
    } else if (!$is_admin && $subscription['user_id'] != $current_user_id) {
        // ຖ້າເປັນຜູ້ໃຊ້ທົ່ວໄປແຕ່ບໍ່ແມ່ນຂໍ້ມູນຂອງຕົນເອງ
        $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນການສະໝັກສະມາຊິກນີ້";
        header('Location: ' . $base_url . 'subscriptions/');
        exit;
    }
}

// ດຶງແຜນການສະໝັກສະມາຊິກທັງໝົດ
$plans_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price ASC");
$plans_stmt->execute();
$available_plans = $plans_stmt->fetchAll();

// ກຳນົດຕົວແປສຳລັບເກັບຂໍ້ມູນແລະຂໍ້ຜິດພາດ
$errors = [];
$selected_plan_id = $subscription['plan_id']; // ຄ່າເລີ່ມຕົ້ນແມ່ນແຜນປະຈຸບັນ
$payment_method = '';
$payment_date = date('Y-m-d');
$payment_proof = '';
$payment_notes = '';

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ດຶງຂໍ້ມູນຈາກຟອມ
    $selected_plan_id = isset($_POST['plan_id']) && is_numeric($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_notes = trim($_POST['payment_notes'] ?? '');
    
    // ກວດສອບຂໍ້ມູນ
    if (!$selected_plan_id) {
        $errors[] = "ກະລຸນາເລືອກແຜນສະໝັກສະມາຊິກ";
    }
    
    if (empty($payment_method)) {
        $errors[] = "ກະລຸນາເລືອກວິທີການຊຳລະເງິນ";
    }
    
    if (empty($payment_date)) {
        $errors[] = "ກະລຸນາລະບຸວັນທີຊຳລະເງິນ";
    }
    
    // ກວດສອບການອັບໂຫຼດໄຟລ໌ຫຼັກຖານການຈ່າຍເງິນ
    if (!empty($_FILES['payment_proof']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = "ຮູບແບບໄຟລ໌ບໍ່ຖືກຕ້ອງ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG, ແລະ PDF ເທົ່ານັ້ນ";
        } else if ($_FILES['payment_proof']['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = "ຂະໜາດໄຟລ໌ໃຫຍ່ເກີນໄປ. ກະລຸນາອັບໂຫຼດໄຟລ໌ທີ່ມີຂະໜາດນ້ອຍກວ່າ 5MB";
        } else {
            // ການອັບໂຫຼດໄຟລ໌
            $upload_dir = '../uploads/payments/';
            
            // ສ້າງໂຟນເດີ ຖ້າຍັງບໍ່ມີ
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
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ດຳເນີນການຕໍ່ອາຍຸ
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // ດຶງຂໍ້ມູນແຜນການສະໝັກສະມາຊິກທີ່ເລືອກ
            $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
            $plan_stmt->execute([$selected_plan_id]);
            $plan = $plan_stmt->fetch();
            
            if (!$plan) {
                throw new Exception("ບໍ່ພົບແຜນການສະໝັກສະມາຊິກ");
            }
            
            // ຄຳນວນວັນທີສິ້ນສຸດໃໝ່
            $current_date = new DateTime();
            $end_date = new DateTime($subscription['end_date']);
            
            // ຖ້າວັນທີສິ້ນສຸດປະຈຸບັນຍັງບໍ່ທັນໝົດອາຍຸ, ໃຫ້ນັບຕໍ່ຈາກວັນທີສິ້ນສຸດເກົ່າ
            // ຖ້າໝົດອາຍຸແລ້ວ, ໃຫ້ນັບຈາກວັນທີປະຈຸບັນ
            if ($end_date < $current_date) {
                $start_date = $current_date->format('Y-m-d');
                $end_date = $current_date;
            } else {
                $start_date = $subscription['start_date']; // ໃຊ້ວັນທີເລີ່ມຕົ້ນເດີມ
            }
            
            // ເພີ່ມຈຳນວນເດືອນຂອງແຜນໃຫມ່
            $end_date->add(new DateInterval('P' . $plan['duration_months'] . 'M'));
            $new_end_date = $end_date->format('Y-m-d');
            
            // ສ້າງລາຍການຊຳລະເງິນໃໝ່
            $payment_stmt = $pdo->prepare("
                INSERT INTO subscription_payments 
                (subscription_id, amount, payment_date, payment_method, payment_proof, status, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // ຖ້າເປັນ superadmin ຫຼື admin, ສະຖານະຈະຖືກກຳນົດເປັນ 'approved' ທັນທີ
            $payment_status = ($is_superadmin || $is_admin) ? 'approved' : 'pending';
            $payment_stmt->execute([
                $subscription_id,
                $plan['price'],
                $payment_date . ' ' . date('H:i:s'),
                $payment_method,
                $payment_proof,
                $payment_status,
                $payment_notes
            ]);
            
            $payment_id = $pdo->lastInsertId();
            
            // ອັບເດດການສະໝັກສະມາຊິກ
            $update_stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET plan_id = ?, 
                    status = ?, 
                    end_date = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            
            // ຖ້າການຈ່າຍເງິນຖືກອະນຸມັດແລ້ວ, ອັບເດດສະຖານະເປັນ 'active'
            $subscription_status = ($payment_status === 'approved') ? 'active' : $subscription['status'];
            
            $update_stmt->execute([
                $selected_plan_id,
                $subscription_status,
                $new_end_date,
                $subscription_id
            ]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "ຕໍ່ອາຍຸການສະໝັກສະມາຊິກສຳເລັດແລ້ວ" . 
                                   ($payment_status === 'pending' ? " ແລະ ລໍຖ້າການຢືນຢັນການຊຳລະເງິນ" : "");
            
            // ສົ່ງກັບໄປຍັງໜ້າລາຍລະອຽດການສະໝັກສະມາຊິກ
            header('Location: ' . $base_url . 'subscriptions/view.php?id=' . $subscription_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການຕໍ່ອາຍຸການສະໝັກສະມາຊິກ: " . $e->getMessage();
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

// ກຳນົດຫົວຂໍ້ໜ້າ ແລະ ໂຫຼດ header
$page_title = 'ຕໍ່ອາຍຸການສະໝັກສະມາຊິກ';
require_once '../includes/header.php';
?>

<div class="container mx-auto py-8 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">ຕໍ່ອາຍຸການສະໝັກສະມາຊິກ</h1>
            <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription_id ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                ກັບໄປໜ້າລາຍລະອຽດ
            </a>
        </div>

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

        <!-- ຂໍ້ມູນການສະໝັກສະມາຊິກປະຈຸບັນ -->
        <div class="bg-white shadow-md rounded-lg mb-6 overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-800">ຂໍ້ມູນການສະໝັກສະມາຊິກປະຈຸບັນ</h2>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">ວັດ</p>
                        <p class="font-medium"><?= htmlspecialchars($subscription['temple_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ຜູ້ໃຊ້</p>
                        <p class="font-medium"><?= htmlspecialchars($subscription['user_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ແຜນປະຈຸບັນ</p>
                        <p class="font-medium"><?= htmlspecialchars($subscription['plan_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ລາຄາ</p>
                        <p class="font-medium"><?= number_format($subscription['price']) ?> ກີບ (<?= $subscription['duration_months'] ?> ເດືອນ)</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີເລີ່ມຕົ້ນ</p>
                        <p class="font-medium"><?= date('d/m/Y', strtotime($subscription['start_date'])) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີສິ້ນສຸດ</p>
                        <p class="font-medium <?= strtotime($subscription['end_date']) < time() ? 'text-red-600' : '' ?>">
                            <?= date('d/m/Y', strtotime($subscription['end_date'])) ?>
                            <?= strtotime($subscription['end_date']) < time() ? ' (ໝົດອາຍຸແລ້ວ)' : '' ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ສະຖານະ</p>
                        <p class="inline-flex items-center">
                            <?php if ($subscription['status'] === 'active'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">ໃຊ້ງານ</span>
                            <?php elseif ($subscription['status'] === 'expired'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">ໝົດອາຍຸ</span>
                            <?php elseif ($subscription['status'] === 'pending'): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">ລໍຖ້າການຢືນຢັນ</span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800"><?= $subscription['status'] ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ຟອມຕໍ່ອາຍຸການສະໝັກສະມາຊິກ -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-800">ຕໍ່ອາຍຸການສະໝັກສະມາຊິກ</h2>
            </div>
            <form method="post" enctype="multipart/form-data" class="p-6">
                <!-- ເລືອກແຜນການສະໝັກສະມາຊິກ -->
                <div class="mb-6">
                    <label for="plan_id" class="block text-sm font-medium text-gray-700 mb-1">ແຜນການສະໝັກສະມາຊິກ <span class="text-red-500">*</span></label>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($available_plans as $plan): ?>
                            <label class="relative border rounded-lg p-4 cursor-pointer <?= $selected_plan_id == $plan['id'] ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200' ?>">
                                <input type="radio" name="plan_id" value="<?= $plan['id'] ?>" <?= $selected_plan_id == $plan['id'] ? 'checked' : '' ?> class="sr-only">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <div class="flex items-center">
                                            <div class="w-5 h-5 border rounded-full flex items-center justify-center mr-2 <?= $selected_plan_id == $plan['id'] ? 'border-indigo-500' : 'border-gray-400' ?>">
                                                <?php if ($selected_plan_id == $plan['id']): ?>
                                                    <div class="w-3 h-3 bg-indigo-500 rounded-full"></div>
                                                <?php endif; ?>
                                            </div>
                                            <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($plan['name']) ?></h3>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600"><?= htmlspecialchars($plan['description']) ?></p>
                                    </div>
                                    <div class="mt-2 md:mt-0 flex items-center">
                                        <div class="text-lg font-bold"><?= number_format($plan['price']) ?> ກີບ</div>
                                        <span class="text-gray-400 mx-2">/</span>
                                        <div class="text-sm text-gray-500"><?= $plan['duration_months'] ?> ເດືອນ</div>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ຂໍ້ມູນການຊຳລະເງິນ -->
                <div class="border-t border-gray-200 pt-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">ຂໍ້ມູນການຊຳລະເງິນ</h3>
                    
                    <!-- ວິທີການຊຳລະເງິນ -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">ວິທີການຊຳລະເງິນ <span class="text-red-500">*</span></label>
                            <select id="payment_method" name="payment_method" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                                <option value="">-- ເລືອກວິທີການຊຳລະເງິນ --</option>
                                <?php foreach ($payment_methods as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $payment_method === $key ? 'selected' : '' ?>><?= $value ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນທີຊຳລະເງິນ <span class="text-red-500">*</span></label>
                            <input type="date" id="payment_date" name="payment_date" value="<?= htmlspecialchars($payment_date) ?>" max="<?= date('Y-m-d') ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                        </div>
                    </div>
                    
                    <!-- ຫຼັກຖານການຊຳລະເງິນ -->
                    <div class="mb-4">
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
                    <div class="mb-4">
                        <label for="payment_notes" class="block text-sm font-medium text-gray-700 mb-1">ໝາຍເຫດ</label>
                        <textarea id="payment_notes" name="payment_notes" rows="3" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?= htmlspecialchars($payment_notes) ?></textarea>
                    </div>
                </div>

                <!-- ປຸ່ມຢືນຢັນ -->
                <div class="flex justify-end mt-6 space-x-3">
                    <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription_id ?>" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-sync-alt mr-2"></i> ຢືນຢັນການຕໍ່ອາຍຸສະມາຊິກ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for image preview -->
<script>
function previewImage(input) {
    const preview = document.getElementById('preview');
    const previewContainer = document.getElementById('previewContainer');
    
    if (input.files && input.files[0]) {
        const fileName = input.files[0].name.toLowerCase();
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // ກວດສອບປະເພດໄຟລ໌
            if (fileName.endsWith('.pdf')) {
                preview.src = '<?= $base_url ?>assets/images/pdf-icon.png';
            } else {
                preview.src = e.target.result;
            }
            previewContainer.style.display = 'flex';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// ເພີ່ມເຫດການ click ໃຫ້ກັບທຸກ label ທີ່ມີ input[type=radio]
document.querySelectorAll('label:has(input[type=radio])').forEach(label => {
    label.addEventListener('click', function() {
        // ລຶບ class ສະແດງການເລືອກອອກຈາກທຸກ label
        document.querySelectorAll('label:has(input[type=radio])').forEach(l => {
            l.classList.remove('border-indigo-500', 'bg-indigo-50');
            l.classList.add('border-gray-200');
            
            // ລຶບວົງມົນສີນ້ຳເງິນພາຍໃນ
            const circle = l.querySelector('.w-5');
            if (circle) {
                circle.classList.remove('border-indigo-500');
                circle.classList.add('border-gray-400');
                
                const innerCircle = circle.querySelector('div');
                if (innerCircle) {
                    innerCircle.remove();
                }
            }
        });
        
        // ເພີ່ມ class ສະແດງການເລືອກໃຫ້ກັບ label ທີ່ຖືກ click
        this.classList.remove('border-gray-200');
        this.classList.add('border-indigo-500', 'bg-indigo-50');
        
        // ສ້າງວົງມົນສີນ້ຳເງິນພາຍໃນ
        const circle = this.querySelector('.w-5');
        if (circle) {
            circle.classList.remove('border-gray-400');
            circle.classList.add('border-indigo-500');
            
            if (!circle.querySelector('div')) {
                const innerCircle = document.createElement('div');
                innerCircle.className = 'w-3 h-3 bg-indigo-500 rounded-full';
                circle.appendChild(innerCircle);
            }
        }
        
        // ເລືອກ radio button
        const radio = this.querySelector('input[type=radio]');
        if (radio) {
            radio.checked = true;
        }
    });
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>