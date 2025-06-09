<?php
// filepath: c:\xampp\htdocs\temples\subscription_plans\view.php
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

// ສະເພາະ superadmin ແລະ admin ສາມາດເຂົ້າເຖິງໄດ້
if (!$is_superadmin && !$is_admin) {
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

// ດຶງຂໍ້ມູນແຜນ
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
    
    // ດຶງຈໍານວນຜູ້ສະໝັກສະມາຊິກຕາມແຜນ
    $subs_sql = "SELECT COUNT(*) as total, status FROM subscriptions WHERE plan_id = ? GROUP BY status";
    $subs_stmt = $pdo->prepare($subs_sql);
    $subs_stmt->execute([$plan_id]);
    $subscriptions_stats = $subs_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_subs = array_sum($subscriptions_stats);
    $active_subs = $subscriptions_stats['active'] ?? 0;
    
    // ດຶງລາຍຊື່ຜູ້ສະໝັກສະມາຊິກລ່າສຸດ
    $recent_subs_sql = "
        SELECT s.id, s.start_date, s.end_date, s.status, s.amount, 
               u.name as user_name, t.name as temple_name
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN temples t ON s.temple_id = t.id
        WHERE s.plan_id = ?
        ORDER BY s.created_at DESC
        LIMIT 5
    ";
    $recent_subs_stmt = $pdo->prepare($recent_subs_sql);
    $recent_subs_stmt->execute([$plan_id]);
    $recent_subscriptions = $recent_subs_stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage();
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ພາກສ່ວນຄຸນສົມບັດ
$features = [];
if (!empty($plan['features'])) {
    $features = array_filter(explode("\n", $plan['features']), 'trim');
}

$page_title = "ລາຍລະອຽດແຜນການສະໝັກສະມາຊິກ: " . $plan['name'];
require_once '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ລາຍລະອຽດແຜນການສະໝັກສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ເບິ່ງຂໍ້ມູນລະອຽດແຜນການສະໝັກສະມາຊິກ</p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>subscription_plans/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <?php if ($is_superadmin): ?>
            <a href="<?= $base_url ?>subscription_plans/edit.php?id=<?= $plan_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center transition">
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
                    <h2 class="text-lg font-medium text-gray-900">ຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ</h2>
                    <div>
                        <?php if ($plan['status'] === 'active'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            ເປີດໃຊ້ງານ
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            ປິດໃຊ້ງານ
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500">ຊື່ແຜນ</h3>
                            <p class="mt-1 text-lg font-medium text-gray-900"><?= htmlspecialchars($plan['name']) ?></p>
                        </div>
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500">ລາຄາ</h3>
                            <p class="mt-1 text-lg font-medium text-green-600"><?= number_format($plan['price'], 0, ',', '.') ?> ກີບ</p>
                        </div>
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500">ໄລຍະເວລາ</h3>
                            <p class="mt-1 text-lg font-medium text-gray-900"><?= $plan['duration_months'] ?> ເດືອນ</p>
                        </div>
                    </div>
                    <div>
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500">ສ້າງວັນທີ</h3>
                            <p class="mt-1 text-base text-gray-900"><?= date("d/m/Y H:i", strtotime($plan['created_at'])) ?></p>
                        </div>
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-500">ID</h3>
                            <p class="mt-1 text-base text-gray-900"><?= $plan_id ?></p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($plan['description'])): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">ລາຍລະອຽດ</h3>
                    <div class="mt-1 prose text-gray-900">
                        <?= nl2br(htmlspecialchars($plan['description'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($features)): ?>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">ຄຸນສົມບັດ</h3>
                    <ul class="mt-1 space-y-1">
                        <?php foreach ($features as $feature): ?>
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 mt-1"></i>
                            </div>
                            <p class="ml-2 text-gray-700"><?= htmlspecialchars($feature) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ສະຖິຕິແລະຂໍ້ມູນເພີ່ມເຕີມ -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="border-b border-gray-200">
                    <div class="px-6 py-4">
                        <h2 class="text-lg font-medium text-gray-900">ສະຖິຕິການສະໝັກ</h2>
                    </div>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                    <i class="fas fa-users text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">ຈໍານວນຜູ້ສະໝັກທັງໝົດ</h3>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $total_subs ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                                    <i class="fas fa-user-check text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">ຜູ້ສະໝັກທີ່ຍັງໃຊ້ງານ</h3>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $active_subs ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-purple-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                                    <i class="fas fa-dollar-sign text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-500">ລາຍຮັບທັງໝົດ</h3>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($total_subs * $plan['price'], 0, ',', '.') ?> ກີບ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($is_superadmin): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="border-b border-gray-200">
                    <div class="px-6 py-4">
                        <h2 class="text-lg font-medium text-gray-900">ເຄື່ອງມື</h2>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <a href="<?= $base_url ?>subscription_plans/edit.php?id=<?= $plan_id ?>" class="w-full flex justify-center items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition">
                        <i class="fas fa-edit mr-2"></i> ແກ້ໄຂແຜນ
                    </a>
                    <?php if ($plan['status'] === 'active'): ?>
                    <a href="<?= $base_url ?>subscription_plans/status.php?id=<?= $plan_id ?>&status=inactive" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການປິດໃຊ້ງານແຜນນີ້?');" class="w-full flex justify-center items-center px-4 py-2 bg-yellow-100 text-yellow-700 rounded-md hover:bg-yellow-200 transition">
                        <i class="fas fa-toggle-off mr-2"></i> ປິດໃຊ້ງານແຜນ
                    </a>
                    <?php else: ?>
                    <a href="<?= $base_url ?>subscription_plans/status.php?id=<?= $plan_id ?>&status=active" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການເປີດໃຊ້ງານແຜນນີ້?');" class="w-full flex justify-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition">
                        <i class="fas fa-toggle-on mr-2"></i> ເປີດໃຊ້ງານແຜນ
                    </a>
                    <?php endif; ?>
                    <?php if ($total_subs === 0): ?>
                    <a href="<?= $base_url ?>subscription_plans/delete.php?id=<?= $plan_id ?>" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບແຜນນີ້? ການດໍາເນີນການນີ້ບໍ່ສາມາດເອົາກັບຄືນໄດ້!');" class="w-full flex justify-center items-center px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition">
                        <i class="fas fa-trash mr-2"></i> ລຶບແຜນ
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ຜູ້ສະໝັກສະມາຊິກລ່າສຸດ -->
    <?php if (!empty($recent_subscriptions)): ?>
    <div class="mt-8">
        <h2 class="text-lg font-medium text-gray-900 mb-4">ຜູ້ສະໝັກສະມາຊິກລ່າສຸດ</h2>
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຜູ້ໃຊ້</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ່ເລີ່ມຕົ້ນ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ່ສິ້ນສຸດ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈໍານວນເງິນ</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_subscriptions as $subscription): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($subscription['user_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($subscription['temple_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date("d/m/Y", strtotime($subscription['start_date'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date("d/m/Y", strtotime($subscription['end_date'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($subscription['status'] === 'active'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ໃຊ້ງານ
                                </span>
                                <?php elseif ($subscription['status'] === 'pending'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    ລໍຖ້າ
                                </span>
                                <?php elseif ($subscription['status'] === 'expired'): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    ໝົດອາຍຸ
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    ຍົກເລີກ
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= number_format($subscription['amount'], 0, ',', '.') ?> ກີບ</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-eye"></i> ເບິ່ງ
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ເບິ່ງທັງໝົດ -->
        <div class="mt-4 text-center">
            <a href="<?= $base_url ?>subscriptions/?plan_id=<?= $plan_id ?>" class="inline-flex items-center px-4 py-2 bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100 transition">
                <i class="fas fa-list mr-2"></i> ເບິ່ງຜູ້ສະໝັກທັງໝົດ
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>