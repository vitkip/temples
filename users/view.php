<?php
// filepath: c:\xampp\htdocs\temples\users\view.php
ob_start();

$page_title = 'ລາຍລະອຽດຜູ້ໃຊ້';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດການໃຊ້ງານ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';
$current_user_id = $_SESSION['user']['id'];

// ກວດສອບ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ມີຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ພ້ອມຂໍ້ມູນວັດແລະແຂວງ
$stmt = $pdo->prepare("
    SELECT 
        u.*, 
        t.name as temple_name, 
        t.province_id,
        p.province_name,
        p.province_code
    FROM users u 
    LEFT JOIN temples t ON u.temple_id = t.id 
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເບິ່ງຂໍ້ມູນ
$has_view_permission = false;

// ຜູ້ໃຊ້ສາມາດເຂົ້າເບິ່ງຂໍ້ມູນຕົນເອງໄດ້
if ($user_id == $current_user_id) {
    $has_view_permission = true;
} elseif ($is_superadmin) {
    // superadmin ສາມາດເບິ່ງຂໍ້ມູນຜູ້ໃຊ້ໄດ້ທຸກຄົນ
    $has_view_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດເບິ່ງໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ
    $has_view_permission = true;
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // province_admin ສາມາດເບິ່ງໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_province_access 
        WHERE user_id = ? AND province_id = ?
    ");
    $province_stmt->execute([$current_user_id, $user['province_id']]);
    
    if ($province_stmt->fetchColumn() > 0) {
        $has_view_permission = true;
    }
}

if (!$has_view_permission) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເບິ່ງຂໍ້ມູນຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ດຶງແຂວງທີ່ດູແລໃນກໍລະນີເປັນ province_admin
$managed_provinces = [];
if ($user['role'] === 'province_admin') {
    $province_stmt = $pdo->prepare("
        SELECT p.* 
        FROM provinces p
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $managed_provinces = $province_stmt->fetchAll();
}

// ກວດສອບສິດການແກ້ໄຂ
$can_edit = false;
if ($user_id == $current_user_id || $is_superadmin || 
    ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id'] && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin') ||
    ($is_province_admin && $has_view_permission && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin' && $user['role'] !== 'admin')) {
    $can_edit = true;
}

// ກວດສອບສິດການລະງັບຫຼືເປີດໃຊ້ງານ
$can_change_status = false;
if ($is_superadmin || 
    ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id'] && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin' && $user_id != $current_user_id) ||
    ($is_province_admin && $has_view_permission && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin' && $user['role'] !== 'admin' && $user_id != $current_user_id)) {
    $can_change_status = true;
}

// ສະແດງສະຖານະເປັນພາສາລາວ
$status_labels = [
    'active' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">ໃຊ້ງານ</span>',
    'pending' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium">ລໍຖ້າອະນຸມັດ</span>',
    'inactive' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">ປິດການໃຊ້ງານ</span>'
];

// ສະແດງບົດບາດເປັນພາສາລາວ
$role_labels = [
    'superadmin' => 'ຜູ້ດູແລລະບົບສູງສຸດ',
    'province_admin' => 'ຜູ້ດູແລລະດັບແຂວງ',
    'admin' => 'ຜູ້ດູແລວັດ',
    'user' => 'ຜູ້ໃຊ້ທົ່ວໄປ'
];

// ດຶງປະຫວັດການປ່ຽນແປງສະຖານະ
$logs = [];
if ($is_superadmin || $is_admin || $is_province_admin) {
    try {
        $log_stmt = $pdo->prepare("
            SELECT 
                usl.*,
                u.name as changed_by_name,
                u.username as changed_by_username,
                p.province_name
            FROM user_status_log usl
            LEFT JOIN users u ON usl.changed_by = u.id
            LEFT JOIN provinces p ON usl.province_id = p.province_id
            WHERE usl.user_id = ?
            ORDER BY usl.changed_at DESC
            LIMIT 10
        ");
        $log_stmt->execute([$user_id]);
        $logs = $log_stmt->fetchAll();
    } catch (PDOException $e) {
        // หากไม่มีตาราง log ก็ไม่ต้องแสดงข้อมูล
    }
}

$page_title = 'ລາຍລະອຽດຜູ້ໃຊ້';
require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນ -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">ສຳເລັດ!</strong>
            <span class="block sm:inline"><?= $_SESSION['success'] ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">ເກີດຂໍ້ຜິດພາດ!</strong>
            <span class="block sm:inline"><?= $_SESSION['error'] ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- ຫົວຂໍ້ແລະປຸ່ມກັບຄືນ -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?= $page_title ?></h1>
        <div class="flex space-x-2">
            <?php if ($can_edit): ?>
            <a href="<?= $base_url ?>users/edit.php?id=<?= $user_id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-edit mr-1"></i> ແກ້ໄຂຂໍ້ມູນ
            </a>
            <?php endif; ?>
            
            <?php if ($can_change_status): ?>
                <?php if ($user['status'] === 'active'): ?>
                <a href="<?= $base_url ?>users/suspend.php?id=<?= $user_id ?>" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລະງັບການໃຊ້ງານຜູ້ໃຊ້ນີ້?');">
                    <i class="fas fa-ban mr-1"></i> ລະງັບການໃຊ້ງານ
                </a>
                <?php elseif ($user['status'] === 'inactive' || $user['status'] === 'pending'): ?>
                <a href="<?= $base_url ?>users/activate.php?id=<?= $user_id ?>" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition" onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການເປີດໃຊ້ງານຜູ້ໃຊ້ນີ້?');">
                    <i class="fas fa-check-circle mr-1"></i> ເປີດໃຊ້ງານ
                </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="<?= $base_url ?>users/" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-1"></i> ກັບຄືນ
            </a>
        </div>
    </div>

    <!-- ແຜງຂໍ້ມູນສ່ວນຕົວ -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">ຂໍ້ມູນສ່ວນຕົວ</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ຊື່ຜູ້ໃຊ້</h3>
                    <p class="text-lg"><?= htmlspecialchars($user['username']) ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ຊື່-ນາມສະກຸນ</h3>
                    <p class="text-lg"><?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ອີເມວ</h3>
                    <p class="text-lg"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>' ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ເບີໂທລະສັບ</h3>
                    <p class="text-lg"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>' ?></p>
                </div>
            </div>
            <div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ບົດບາດ</h3>
                    <p class="text-lg"><?= $role_labels[$user['role']] ?? htmlspecialchars($user['role']) ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ສະຖານະ</h3>
                    <p><?= $status_labels[$user['status']] ?? htmlspecialchars($user['status']) ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ວັດ</h3>
                    <p class="text-lg"><?= !empty($user['temple_name']) ? htmlspecialchars($user['temple_name']) : '<span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>' ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ແຂວງ</h3>
                    <p class="text-lg"><?= !empty($user['province_name']) ? htmlspecialchars($user['province_name']) : '<span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>' ?></p>
                </div>
                <div class="mb-4">
                    <h3 class="text-sm font-medium text-gray-500">ວັນທີສ້າງ</h3>
                    <p class="text-lg"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ແຂວງທີ່ຮັບຜິດຊອບ (ສຳລັບ province_admin) -->
    <?php if ($user['role'] === 'province_admin' && !empty($managed_provinces)): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">ແຂວງທີ່ຮັບຜິດຊອບ</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <?php foreach ($managed_provinces as $province): ?>
                <div class="bg-blue-50 p-3 rounded-md">
                    <span class="font-medium"><?= htmlspecialchars($province['province_name']) ?></span>
                    <span class="text-sm text-gray-600 ml-2">(<?= htmlspecialchars($province['province_code']) ?>)</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ປະຫວັດການປ່ຽນແປງສະຖານະ -->
    <?php if (!empty($logs) && ($is_superadmin || $is_admin || $is_province_admin)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">ປະຫວັດການປ່ຽນແປງສະຖານະ</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ດຳເນີນການໂດຍ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ປ່ຽນຈາກ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ປ່ຽນເປັນ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ບັນທຶກເພີ່ມເຕີມ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('d/m/Y H:i', strtotime($log['changed_at'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($log['changed_by_name'] ?? $log['changed_by_username']) ?>
                            </div>
                            <?php if (!empty($log['province_name'])): ?>
                                <div class="text-sm text-gray-500">
                                    <?= htmlspecialchars($log['province_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php $old_status = $log['old_status'] ?? 'unknown'; ?>
                            <?= $status_labels[$old_status] ?? $old_status ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php $new_status = $log['new_status'] ?? 'unknown'; ?>
                            <?= $status_labels[$new_status] ?? $new_status ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($log['note'] ?? '') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// ສຳລັບປິດຂໍ້ຄວາມແຈ້ງເຕືອນ
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('[role="alert"]');
    alerts.forEach(alert => {
        const closeButton = alert.querySelector('svg[role="button"]');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                alert.remove();
            });
        }
        
        // ປິດອັດຕະໂນມັດຫຼັງຈາກ 5 ວິນາທີ
        setTimeout(() => {
            alert.classList.add('opacity-0', 'transition-opacity', 'duration-500');
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>