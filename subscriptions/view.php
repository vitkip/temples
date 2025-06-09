<?php
// filepath: c:\xampp\htdocs\temples\subscriptions\view.php
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
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກວດສອບ ID ຂອງການສະໝັກສະມາຊິກ
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}
$subscription_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນການສະໝັກສະມາຊິກ
try {
    $sql = "
        SELECT s.*, 
               u.name as user_name, u.username, u.email as user_email, u.phone as user_phone,
               t.name as temple_name, t.address as temple_address,
               p.name as plan_name, p.price as plan_price, p.duration_months
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN temples t ON s.temple_id = t.id
        LEFT JOIN subscription_plans p ON s.plan_id = p.id
        WHERE s.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການສະໝັກສະມາຊິກ";
        header('Location: ' . $base_url . 'subscriptions/');
        exit;
    }
    
    // ກວດສອບສິດ admin ວ່າສາມາດເບິ່ງຂໍ້ມູນການສະໝັກນີ້ໄດ້ບໍ່
    if ($is_admin && !$is_superadmin && $subscription['temple_id'] != $user_temple_id) {
        $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເບິ່ງການສະໝັກສະມາຊິກນີ້";
        header('Location: ' . $base_url . 'subscriptions/');
        exit;
    }
    
    // ກວດສອບສະຖານະປັດຈຸບັນ
    $current_date = date('Y-m-d');
    $status_badge = '';
    $status_text = '';
    
    switch ($subscription['status']) {
        case 'active':
            if ($current_date > $subscription['end_date']) {
                $status_badge = 'bg-red-100 text-red-800';
                $status_text = 'ໝົດອາຍຸແລ້ວ (ຄວນອັບເດດ)';
            } else {
                $status_badge = 'bg-green-100 text-green-800';
                $status_text = 'ໃຊ້ງານ';
            }
            break;
        case 'pending':
            $status_badge = 'bg-yellow-100 text-yellow-800';
            $status_text = 'ລໍຖ້າ';
            break;
        case 'expired':
            $status_badge = 'bg-gray-100 text-gray-800';
            $status_text = 'ໝົດອາຍຸ';
            break;
        case 'cancelled':
            $status_badge = 'bg-red-100 text-red-800';
            $status_text = 'ຍົກເລີກ';
            break;
        default:
            $status_badge = 'bg-gray-100 text-gray-800';
            $status_text = $subscription['status'];
    }
    
    // ຄໍານວນໄລຍະເວລາທີ່ເຫຼືອ
    $days_remaining = 0;
    $is_expired = false;
    
    if ($subscription['status'] === 'active') {
        $end_date = new DateTime($subscription['end_date']);
        $today = new DateTime($current_date);
        $interval = $today->diff($end_date);
        
        if ($end_date < $today) {
            $is_expired = true;
            $days_remaining = 0;
        } else {
            $days_remaining = $interval->days;
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage();
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

$page_title = "ລາຍລະອຽດການສະໝັກສະມາຊິກ #" . $subscription_id;
require_once '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ລາຍລະອຽດການສະໝັກສະມາຊິກ #<?= $subscription_id ?></h1>
            <p class="text-sm text-gray-600">ເບິ່ງແລະຈັດການຂໍ້ມູນການສະໝັກສະມາຊິກ</p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>subscriptions/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <?php if ($is_superadmin || ($is_admin && $subscription['temple_id'] == $user_temple_id)): ?>
            <a href="<?= $base_url ?>subscriptions/edit.php?id=<?= $subscription_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
            </a>
            <?php endif; ?>
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
    
    <?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- ຂໍ້ມູນພື້ນຖານ -->
        <div class="bg-white rounded-lg shadow overflow-hidden col-span-2">
            <div class="border-b border-gray-200">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-lg font-medium text-gray-900">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h2>
                    <div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_badge ?>">
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- ຂໍ້ມູນຜູ້ໃຊ້ -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-4">ຂໍ້ມູນຜູ້ໃຊ້</h3>
                        <div class="mb-4 p-4 bg-gray-50 rounded-md">
                            <div class="flex items-center mb-3">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <span class="text-indigo-700 font-medium"><?= substr($subscription['user_name'], 0, 1) ?></span>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($subscription['user_name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($subscription['username']) ?></p>
                                </div>
                            </div>
                            <?php if (!empty($subscription['user_email'])): ?>
                            <div class="mb-2">
                                <span class="text-xs text-gray-500">ອີເມວ:</span>
                                <span class="text-sm text-gray-900 ml-1"><?= htmlspecialchars($subscription['user_email']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($subscription['user_phone'])): ?>
                            <div>
                                <span class="text-xs text-gray-500">ເບີໂທ:</span>
                                <span class="text-sm text-gray-900 ml-1"><?= htmlspecialchars($subscription['user_phone']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ຂໍ້ມູນວັດ -->
                        <h3 class="text-sm font-medium text-gray-500 mb-2">ວັດ</h3>
                        <p class="text-base text-gray-900 mb-1"><?= htmlspecialchars($subscription['temple_name']) ?></p>
                        <?php if (!empty($subscription['temple_address'])): ?>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($subscription['temple_address']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ຂໍ້ມູນແຜນແລະການຊໍາລະ -->
                    <div>
                        <?php if (!empty($subscription['plan_id'])): ?>
                        <h3 class="text-sm font-medium text-gray-500 mb-4">ແຜນການສະໝັກສະມາຊິກ</h3>
                        <div class="mb-4 p-4 bg-indigo-50 rounded-md">
                            <p class="text-base font-medium text-indigo-800 mb-2"><?= htmlspecialchars($subscription['plan_name']) ?></p>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">ລາຄາ:</span>
                                <span class="font-medium text-gray-900"><?= number_format($subscription['plan_price'], 0, ',', '.') ?> ກີບ</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">ໄລຍະເວລາ:</span>
                                <span class="font-medium text-gray-900"><?= $subscription['duration_months'] ?> ເດືອນ</span>
                            </div>
                            <div class="mt-3">
                                <a href="<?= $base_url ?>subscription_plans/view.php?id=<?= $subscription['plan_id'] ?>" class="text-xs text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-external-link-alt mr-1"></i> ເບິ່ງລາຍລະອຽດແຜນ
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <h3 class="text-sm font-medium text-gray-500 mb-2">ການຊໍາລະເງິນ</h3>
                        <div class="mb-4">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">ຈໍານວນເງິນ:</span>
                                <span class="font-medium text-gray-900"><?= number_format($subscription['amount'], 0, ',', '.') ?> ກີບ</span>
                            </div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">ວິທີການຊໍາລະ:</span>
                                <span class="font-medium text-gray-900">
                                    <?php
                                    switch($subscription['payment_method']) {
                                        case 'cash':
                                            echo 'ເງິນສົດ';
                                            break;
                                        case 'bank_transfer':
                                            echo 'ໂອນຜ່ານທະນາຄານ';
                                            break;
                                        case 'qr_payment':
                                            echo 'QR Payment';
                                            break;
                                        default:
                                            echo htmlspecialchars($subscription['payment_method']);
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if (!empty($subscription['payment_reference'])): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">ເລກອ້າງອີງ:</span>
                                <span class="font-medium text-gray-900"><?= htmlspecialchars($subscription['payment_reference']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- ໄລຍະເວລາການສະໝັກ -->
                <div class="mt-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-4">ໄລຍະເວລາການສະໝັກ</h3>
                    <div class="relative">
                        <div class="flex items-center space-x-2 mb-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-calendar-plus text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ວັນທີ່ເລີ່ມຕົ້ນ</p>
                                <p class="text-base font-medium text-gray-900"><?= date("d/m/Y", strtotime($subscription['start_date'])) ?></p>
                            </div>
                        </div>
                        <div class="h-12 border-l-2 border-dashed border-gray-300 ml-5"></div>
                        <div class="flex items-center space-x-2">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-<?= $is_expired ? 'red' : 'green' ?>-100 flex items-center justify-center">
                                <i class="fas fa-calendar-times text-<?= $is_expired ? 'red' : 'green' ?>-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ວັນທີ່ສິ້ນສຸດ</p>
                                <p class="text-base font-medium text-<?= $is_expired ? 'red' : 'gray' ?>-900"><?= date("d/m/Y", strtotime($subscription['end_date'])) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($subscription['status'] === 'active'): ?>
                    <div class="mt-4">
                        <?php if ($is_expired): ?>
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">ການສະໝັກສະມາຊິກນີ້ໝົດອາຍຸແລ້ວ</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>ການສະໝັກສະມາຊິກນີ້ໝົດອາຍຸແລ້ວ. ກະລຸນາຕໍ່ອາຍຸ ຫຼື ປ່ຽນສະຖານະເປັນ "ໝົດອາຍຸ".</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">ໄລຍະເວລາທີເຫຼືອ</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>ໄລຍະເວລາທີເຫຼືອຂອງການສະໝັກສະມາຊິກນີ້: <strong><?= $days_remaining ?> ວັນ</strong></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($subscription['notes'])): ?>
                <div class="mt-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">ໝາຍເຫດ</h3>
                    <div class="bg-gray-50 rounded-md p-4">
                        <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($subscription['notes'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ຂໍ້ມູນເພີ່ມເຕີມແລະເຄື່ອງມື -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="border-b border-gray-200">
                    <div class="px-6 py-4">
                        <h2 class="text-lg font-medium text-gray-900">ຂໍ້ມູນເພີ່ມເຕີມ</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-500">ສ້າງວັນທີ</p>
                            <p class="text-base text-gray-900"><?= date("d/m/Y H:i", strtotime($subscription['created_at'])) ?></p>
                        </div>
                        <?php if ($subscription['updated_at'] !== $subscription['created_at']): ?>
                        <div>
                            <p class="text-sm text-gray-500">ອັບເດດລ່າສຸດ</p>
                            <p class="text-base text-gray-900"><?= date("d/m/Y H:i", strtotime($subscription['updated_at'])) ?></p>
                        </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm text-gray-500">ID</p>
                            <p class="text-base text-gray-900"><?= $subscription_id ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($is_superadmin || ($is_admin && $subscription['temple_id'] == $user_temple_id)): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="border-b border-gray-200">
                    <div class="px-6 py-4">
                        <h2 class="text-lg font-medium text-gray-900">ເຄື່ອງມື</h2>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <a href="<?= $base_url ?>subscriptions/edit.php?id=<?= $subscription_id ?>" class="w-full flex justify-center items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-2"></i> ແກ້ໄຂການສະໝັກສະມາຊິກ
                    </a>
                    
                    <?php if ($subscription['status'] === 'active'): ?>
                    <a href="<?= $base_url ?>subscriptions/extend.php?id=<?= $subscription_id ?>" class="w-full flex justify-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition">
                        <i class="fas fa-calendar-plus mr-2"></i> ຕໍ່ອາຍຸການສະໝັກສະມາຊິກ
                    </a>
                
                    <a href="<?= $base_url ?>subscriptions/status.php?id=<?= $subscription_id ?>&status=canceled" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການຍົກເລີກການສະໝັກສະມາຊິກນີ້?');" class="w-full flex justify-center items-center px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition">
                        <i class="fas fa-times-circle mr-2"></i> ຍົກເລີກການສະໝັກສະມາຊິກ
                    </a>
                    <?php elseif ($subscription['status'] === 'pending'): ?>
                    <a href="<?= $base_url ?>subscriptions/status.php?id=<?= $subscription_id ?>&status=active" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການເປີດໃຊ້ງານການສະໝັກສະມາຊິກນີ້?');" class="w-full flex justify-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition">
                        <i class="fas fa-check-circle mr-2"></i> ເປີດໃຊ້ງານການສະໝັກສະມາຊິກ
                    </a>
                    <?php elseif ($subscription['status'] === 'expired'): ?>
                    <a href="<?= $base_url ?>subscriptions/extend.php?id=<?= $subscription_id ?>" class="w-full flex justify-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition">
                        <i class="fas fa-redo mr-2"></i> ຕໍ່ອາຍຸການສະໝັກສະມາຊິກ
                    </a>
                    <?php elseif ($subscription['status'] === 'canceled'): ?>
                    <a href="<?= $base_url ?>subscriptions/status.php?id=<?= $subscription_id ?>&status=active" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການເປີດໃຊ້ງານການສະໝັກສະມາຊິກນີ້ອີກຄັ້ງ?');" class="w-full flex justify-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition">
                        <i class="fas fa-redo mr-2"></i> ເປີດໃຊ້ງານອີກຄັ້ງ
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?= $base_url ?>subscriptions/delete.php?id=<?= $subscription_id ?>" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບການສະໝັກສະມາຊິກນີ້? ການດໍາເນີນການນີ້ບໍ່ສາມາດເອົາກັບຄືນໄດ້!');" class="w-full flex justify-center items-center px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-2"></i> ລຶບການສະໝັກສະມາຊິກ
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>