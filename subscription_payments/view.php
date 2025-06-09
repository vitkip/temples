<?php
ob_start();
session_start();

$page_title = 'ລາຍລະອຽດການຊຳລະເງິນ';
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

// ຂໍ້ມູນສະຖານະການຊຳລະເງິນ
$status_text = [
    'pending' => 'ລໍຖ້າການຢືນຢັນ',
    'approved' => 'ອະນຸມັດແລ້ວ',
    'rejected' => 'ຖືກປະຕິເສດ'
];

$status_badge = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800'
];

$status_icon = [
    'pending' => 'fas fa-clock text-yellow-500',
    'approved' => 'fas fa-check-circle text-green-500',
    'rejected' => 'fas fa-times-circle text-red-500'
];

// ສະແດງໜ້າ
?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <!-- ສ່ວນຫົວຂອງໜ້າ -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ລາຍລະອຽດການຊຳລະເງິນ</h1>
            <p class="mt-1 text-sm text-gray-600">ເບິ່ງຂໍ້ມູນການຊຳລະເງິນແລະຈັດການສະຖານະ</p>
        </div>
        
        <div class="flex space-x-2 mt-4 md:mt-0">
            <a href="<?= $base_url ?>subscription_payments/" class="inline-flex items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            
            <?php if ($payment['status'] === 'pending'): ?>
            <a href="<?= $base_url ?>subscription_payments/approve.php?id=<?= $payment['id'] ?>" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການອະນຸມັດການຊຳລະເງິນນີ້?');">
                <i class="fas fa-check-circle mr-2"></i> ອະນຸມັດ
            </a>
            <a href="<?= $base_url ?>subscription_payments/reject.php?id=<?= $payment['id'] ?>" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການປະຕິເສດການຊຳລະເງິນນີ້?');">
                <i class="fas fa-times-circle mr-2"></i> ປະຕິເສດ
            </a>
            <?php endif; ?>
            
            <a href="<?= $base_url ?>subscription_payments/edit.php?id=<?= $payment['id'] ?>" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
            </a>
        </div>
    </div>

    <!-- ຂໍ້ມູນສະຖານະ -->
    <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">ສະຖານະການຊຳລະເງິນ</h2>
                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?= $status_badge[$payment['status']] ?>">
                    <i class="<?= $status_icon[$payment['status']] ?> mr-1"></i>
                    <?= $status_text[$payment['status']] ?>
                </span>
            </div>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-500">ຈຳນວນເງິນ:</span>
                <p class="mt-1 text-lg font-semibold"><?= number_format($payment['amount']) ?> ກີບ</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500">ວັນທີຊຳລະເງິນ:</span>
                <p class="mt-1"><?= date('d/m/Y H:i', strtotime($payment['payment_date'])) ?></p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500">ວິທີການຊຳລະເງິນ:</span>
                <p class="mt-1"><?= $payment_methods[$payment['payment_method']] ?? $payment['payment_method'] ?></p>
            </div>
        </div>
        
        <?php if (!empty($payment['notes'])): ?>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <h3 class="text-sm font-medium text-gray-500">ໝາຍເຫດ:</h3>
            <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><?= nl2br(htmlspecialchars($payment['notes'])) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ຂໍ້ມູນການສະໝັກສະມາຊິກ -->
    <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h2>
        </div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-500">ຜູ້ໃຊ້:</span>
                <p class="mt-1"><?= htmlspecialchars($payment['user_name']) ?> (<?= htmlspecialchars($payment['username']) ?>)</p>
                <?php if (!empty($payment['user_email'])): ?>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($payment['user_email']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500">ວັດ:</span>
                <p class="mt-1"><?= htmlspecialchars($payment['temple_name']) ?></p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500">ແຜນການສະໝັກສະມາຊິກ:</span>
                <p class="mt-1"><?= htmlspecialchars($payment['plan_name']) ?></p>
                <p class="text-sm text-gray-500"><?= number_format($payment['price']) ?> ກີບ / <?= $payment['duration_months'] ?> ເດືອນ</p>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500">ສະຖານະການສະໝັກສະມາຊິກ:</span>
                <p class="mt-1">
                    <?php 
                    switch($payment['subscription_status']) {
                        case 'active':
                            echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">ໃຊ້ງານຢູ່</span>';
                            break;
                        case 'pending':
                            echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">ລໍຖ້າການຢືນຢັນ</span>';
                            break;
                        case 'expired':
                            echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">ໝົດອາຍຸແລ້ວ</span>';
                            break;
                        case 'canceled':
                            echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">ຍົກເລີກແລ້ວ</span>';
                            break;
                        default:
                            echo htmlspecialchars($payment['subscription_status']);
                    }
                    ?>
                </p>
                <?php if (!empty($payment['start_date'])): ?>
                <p class="text-sm text-gray-500 mt-1">
                    ວັນທີເລີ່ມຕົ້ນ: <?= date('d/m/Y', strtotime($payment['start_date'])) ?><br>
                    ວັນທີສິ້ນສຸດ: <?= date('d/m/Y', strtotime($payment['end_date'])) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
            <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $payment['subscription_id'] ?>" class="inline-flex items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-external-link-alt mr-2"></i> ເບິ່ງລາຍລະອຽດການສະໝັກສະມາຊິກ
            </a>
        </div>
    </div>

    <!-- ຫຼັກຖານການຊຳລະເງິນ -->
    <?php if (!empty($payment['payment_proof'])): ?>
    <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">ຫຼັກຖານການຊຳລະເງິນ</h2>
        </div>
        <div class="p-6">
            <?php
            $file_extension = pathinfo($payment['payment_proof'], PATHINFO_EXTENSION);
            if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])):
            ?>
                <div class="flex justify-center">
                    <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank">
                        <img src="<?= $base_url . $payment['payment_proof'] ?>" alt="ຫຼັກຖານການຊຳລະເງິນ" class="max-h-96 rounded-lg shadow-lg">
                    </a>
                </div>
            <?php elseif (strtolower($file_extension) === 'pdf'): ?>
                <div class="flex flex-col items-center justify-center">
                    <div class="bg-gray-100 p-4 rounded-lg mb-4 text-center">
                        <i class="far fa-file-pdf text-red-500 text-5xl mb-2"></i>
                        <p class="text-gray-700">ໄຟລ໌ PDF</p>
                    </div>
                    <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <i class="fas fa-download mr-2"></i> ເປີດໄຟລ໌ PDF
                    </a>
                </div>
            <?php else: ?>
                <div class="flex justify-center">
                    <a href="<?= $base_url . $payment['payment_proof'] ?>" target="_blank" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <i class="fas fa-download mr-2"></i> ດາວໂຫຼດຫຼັກຖານການຊຳລະເງິນ
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ປຸ່ມຈັດການ -->
    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
        <div>
            <a href="<?= $base_url ?>subscription_payments/" class="inline-flex items-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-arrow-left mr-2"></i> ກັບໄປໜ້າລາຍການ
            </a>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <?php if ($payment['status'] === 'pending'): ?>
            <a href="<?= $base_url ?>subscription_payments/approve.php?id=<?= $payment['id'] ?>" class="inline-flex items-center justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການອະນຸມັດການຊຳລະເງິນນີ້?');">
                <i class="fas fa-check-circle mr-2"></i> ອະນຸມັດການຊຳລະເງິນ
            </a>
            <a href="<?= $base_url ?>subscription_payments/reject.php?id=<?= $payment['id'] ?>" class="inline-flex items-center justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການປະຕິເສດການຊຳລະເງິນນີ້?');">
                <i class="fas fa-times-circle mr-2"></i> ປະຕິເສດການຊຳລະເງິນ
            </a>
            <?php endif; ?>
            <a href="<?= $base_url ?>subscription_payments/edit.php?id=<?= $payment['id'] ?>" class="inline-flex items-center justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂຂໍ້ມູນ
            </a>
            <a href="<?= $base_url ?>subscription_payments/delete.php?id=<?= $payment['id'] ?>" class="inline-flex items-center justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບຂໍ້ມູນການຊຳລະເງິນນີ້?');">
                <i class="fas fa-trash-alt mr-2"></i> ລຶບຂໍ້ມູນ
            </a>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>