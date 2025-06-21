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
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເບິ່ງຂໍ້ມູນຜູໃຊ້ນີ້";
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
    'active' => '<span class="status-badge status-active"><i class="fas fa-circle text-xs mr-1"></i>ໃຊ້ງານ</span>',
    'pending' => '<span class="status-badge bg-yellow-100 text-yellow-800 border border-yellow-200"><i class="fas fa-clock text-xs mr-1"></i>ລໍຖ້າອະນຸມັດ</span>',
    'inactive' => '<span class="status-badge bg-red-100 text-red-800 border border-red-200"><i class="fas fa-ban text-xs mr-1"></i>ປິດໃຊ້ງານ</span>'
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

// กำหนดไอคอนตามบทบาท
$role_icons = [
    'superadmin' => '<i class="fas fa-crown"></i>',
    'province_admin' => '<i class="fas fa-map-marked-alt"></i>', 
    'admin' => '<i class="fas fa-gopuram"></i>',
    'user' => '<i class="fas fa-user"></i>'
];

// กำหนดสีไอคอนตามบทบาท
$role_colors = [
    'superadmin' => 'amber',
    'province_admin' => 'blue',
    'admin' => 'green',
    'user' => 'indigo'
];

// นำเข้า CSS พิเศษ
echo '<link rel="stylesheet" href="' . $base_url . 'assets/css/monk-style.css">';
?>

<div class="page-container bg-temple-pattern">
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນ -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="animate__animated animate__fadeInDown bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
            <div class="flex items-center">
                <div class="mr-3">
                    <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                </div>
                <div>
                    <p class="font-bold">ສຳເລັດ!</p>
                    <p><?= $_SESSION['success'] ?></p>
                </div>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="animate__animated animate__fadeInDown bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
            <div class="flex items-center">
                <div class="mr-3">
                    <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
                </div>
                <div>
                    <p class="font-bold">ເກີດຂໍ້ຜິດພາດ!</p>
                    <p><?= $_SESSION['error'] ?></p>
                </div>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                    <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <title>Close</title>
                        <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                    </svg>
                </span>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- header-section ເພີ່ມสไตล์ใหม่ ปรับแต่งตาม monk-style.css -->
    <div class="header-section mb-8 animate__animated animate__fadeInDown">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
            <div class="mb-4 md:mb-0">
                <h1 class="text-3xl font-bold text-amber-800 flex items-center">
                    <div class="category-icon bg-amber-600">
                        <?= $role_icons[$user['role']] ?? '<i class="fas fa-user"></i>' ?>
                    </div>
                    <?= htmlspecialchars($user['name']) ?>
                </h1>
                <div class="flex items-center mt-1.5 text-amber-700">
                    <span class="mr-3"><?= $role_labels[$user['role']] ?? htmlspecialchars($user['role']) ?></span>
                    <?= $status_labels[$user['status']] ?? htmlspecialchars($user['status']) ?>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($can_edit): ?>
                <a href="<?= $base_url ?>users/edit.php?id=<?= $user_id ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i>
                    <span>ແກ້ໄຂຂໍ້ມູນ</span>
                </a>
                <?php endif; ?>
                
                <?php if ($can_change_status): ?>
                    <?php if ($user['status'] === 'active'): ?>
                    <a href="<?= $base_url ?>users/suspend.php?id=<?= $user_id ?>" 
                       class="btn bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md transition shadow-md flex items-center" 
                       onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລະງັບການໃຊ້ງານຜູ້ໃຊ້ນີ້?');">
                        <i class="fas fa-ban mr-2"></i> ລະງັບການໃຊ້ງານ
                    </a>
                    <?php elseif ($user['status'] === 'inactive' || $user['status'] === 'pending'): ?>
                    <a href="<?= $base_url ?>users/activate.php?id=<?= $user_id ?>" 
                       class="btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition shadow-md flex items-center" 
                       onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການເປີດໃຊ້ງານຜູໃຊ້ນີ້?');">
                        <i class="fas fa-check-circle mr-2"></i> ເປີດໃຊ້ງານ
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="<?= $base_url ?>users/" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>ກັບຄືນ</span>
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- คอลัมน์ซ้าย: ข้อมูลส่วนตัวและที่อยู่ -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ແຜງຂໍ້ມູນສ່ວນຕົວ - info-card แบบใหม่ -->
            <div class="info-card animate__animated animate__fadeIn animate__delay-1s">
                <div class="info-card-header">
                    <h2 class="info-card-title">
                        <div class="icon-circle <?= $role_colors[$user['role']] ?? 'amber' ?>">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        ຂໍ້ມູນສ່ວນຕົວ
                    </h2>
                </div>
                <div class="info-card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-4">
                                <span class="info-label">ຊື່ຜູ້ໃຊ້</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-user-tag text-amber-500 mr-2"></i>
                                    <?= htmlspecialchars($user['username']) ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ຊື່-ນາມສະກຸນ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-id-card text-amber-500 mr-2"></i>
                                    <?= htmlspecialchars($user['name']) ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ອີເມວ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-envelope text-amber-500 mr-2"></i>
                                    <?php if (!empty($user['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-amber-700 hover:text-amber-900 hover:underline">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ເບີໂທລະສັບ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-phone text-amber-500 mr-2"></i>
                                    <?php if (!empty($user['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="text-amber-700 hover:text-amber-900 hover:underline">
                                            <?= htmlspecialchars($user['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div>
                            <div class="mb-4">
                                <span class="info-label">ບົດບາດ</span>
                                <p class="info-value flex items-center">
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center mr-2 text-white"
                                          style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));">
                                        <?= $role_icons[$user['role']] ?? '<i class="fas fa-user"></i>' ?>
                                    </span>
                                    <?= $role_labels[$user['role']] ?? htmlspecialchars($user['role']) ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ວັດ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-gopuram text-amber-500 mr-2"></i>
                                    <?php if (!empty($user['temple_name'])): ?>
                                        <a href="<?= $base_url ?>temples/view.php?id=<?= $user['temple_id'] ?>" class="text-amber-700 hover:text-amber-900 hover:underline">
                                            <?= htmlspecialchars($user['temple_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ແຂວງ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-map-marker-alt text-amber-500 mr-2"></i>
                                    <?= !empty($user['province_name']) ? htmlspecialchars($user['province_name']) : '<span class="text-gray-400">ບໍ່ໄດ້ລະບຸ</span>' ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <span class="info-label">ວັນທີສ້າງ</span>
                                <p class="info-value flex items-center">
                                    <i class="fas fa-calendar-alt text-amber-500 mr-2"></i>
                                    <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ຂໍ້ມູນກິດຈະກຳລ່າສຸດ (ເพิ่มใหม่) -->
            <div class="info-card animate__animated animate__fadeIn animate__delay-2s" id="user-activity">
                <div class="info-card-header">
                    <div class="flex justify-between items-center">
                        <h2 class="info-card-title">
                            <div class="icon-circle green">
                                <i class="fas fa-history"></i>
                            </div>
                            ກິດຈະກຳລ່າສຸດ
                        </h2>
                        <button id="load-more-activity" class="text-amber-600 hover:text-amber-800 text-sm transition duration-300 ease-in-out flex items-center">
                            <span class="mr-1">ໂຫລດເພີ່ມເຕີມ</span> <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </div>
                <div class="info-card-body">
                    <div id="activity-container" class="space-y-3">
                        <!-- AJAX จะโหลดข้อมูลมาใส่ที่นี่ -->
                        <div class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-amber-500"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ປະຫວັດການປ່ຽນແປງສະຖານະ - ปรับเป็น info-card -->
            <?php if (!empty($logs) && ($is_superadmin || $is_admin || $is_province_admin)): ?>
            <div class="info-card animate__animated animate__fadeIn animate__delay-3s">
                <div class="info-card-header">
                    <h2 class="info-card-title">
                        <div class="icon-circle blue">
                            <i class="fas fa-history"></i>
                        </div>
                        ປະຫວັດການປ່ຽນແປງສະຖານະ
                    </h2>
                </div>
                <div class="info-card-body">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-amber-100">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ດຳເນີນການໂດຍ</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ປ່ຽນຈາກ</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ປ່ຽນເປັນ</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ບັນທຶກເພີ່ມເຕີມ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-amber-100">
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-amber-50 transition-colors">
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
            </div>
            <?php endif; ?>
        </div>

        <!-- คอลัมน์ขวา: sidebar -->
        <div class="space-y-6">
            <!-- ແຂວງທີ່ຮັບຜິດຊອບ (ສຳລັບ province_admin) - ปรับเป็น info-card -->
            <?php if ($user['role'] === 'province_admin' && !empty($managed_provinces)): ?>
            <div class="info-card animate__animated animate__fadeIn animate__delay-2s">
                <div class="info-card-header">
                    <h2 class="info-card-title">
                        <div class="icon-circle indigo">
                            <i class="fas fa-globe-asia"></i>
                        </div>
                        ແຂວງທີ່ຮັບຜິດຊອບ
                    </h2>
                </div>
                <div class="info-card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php foreach ($managed_provinces as $province): ?>
                            <div class="flex items-center p-3 bg-blue-50 rounded-lg border border-blue-100 hover:bg-blue-100 transition-colors">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-blue-600"><?= substr($province['province_code'], 0, 1) ?></span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">
                                        <?= htmlspecialchars($province['province_name']) ?>
                                    </p>
                                    <span class="text-xs text-gray-500">
                                        <?= htmlspecialchars($province['province_code']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ສະຖິຕິການເຂົ້າໃຊ້ງານ (ເพิ่มใหม่) -->
            <div class="info-card animate__animated animate__fadeIn animate__delay-3s">
                <div class="info-card-header">
                    <h2 class="info-card-title">
                        <div class="icon-circle amber">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        ສະຖິຕິການເຂົ້າໃຊ້ງານ
                    </h2>
                </div>
                <div class="info-card-body">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-amber-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-sign-in-alt text-amber-600"></i>
                                </div>
                                <span>ເຂົ້າສູ່ລະບົບຄັ້ງລ່າສຸດ</span>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-semibold text-amber-700">
                                    <?= !empty($user['last_login']) ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'ບໍ່ມີຂໍ້ມູນ' ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-calendar-check text-blue-600"></i>
                                </div>
                                <span>ວັນທີສ້າງບັນຊີ</span>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-semibold text-blue-700">
                                    <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-sync-alt text-green-600"></i>
                                </div>
                                <span>ອັບເດດລ່າສຸດ</span>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-semibold text-green-700">
                                    <?= !empty($user['updated_at']) ? date('d/m/Y H:i', strtotime($user['updated_at'])) : 'ບໍ່ມີຂໍ້ມູນ' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ຂໍ້ມູນຕິດຕໍ່ (ເพິ່มใหม่) -->
            <div class="info-card animate__animated animate__fadeIn animate__delay-4s">
                <div class="info-card-header">
                    <h2 class="info-card-title">
                        <div class="icon-circle green">
                            <i class="fas fa-address-book"></i>
                        </div>
                        ຂໍ້ມູນຕິດຕໍ່ດ່ວນ
                    </h2>
                </div>
                <div class="info-card-body">
                    <div class="space-y-3">
                        <?php if (!empty($user['phone'])): ?>
                        <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="flex items-center p-3 rounded-lg hover:bg-amber-50 transition-colors group">
                            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center mr-3 group-hover:bg-amber-200 transition-colors">
                                <i class="fas fa-phone text-amber-600"></i>
                            </div>
                            <span class="text-gray-800 group-hover:text-amber-700 transition-colors"><?= htmlspecialchars($user['phone']) ?></span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="flex items-center p-3 rounded-lg hover:bg-amber-50 transition-colors group">
                            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center mr-3 group-hover:bg-amber-200 transition-colors">
                                <i class="fas fa-envelope text-amber-600"></i>
                            </div>
                            <span class="text-gray-800 group-hover:text-amber-700 transition-colors"><?= htmlspecialchars($user['email']) ?></span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (empty($user['phone']) && empty($user['email'])): ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fas fa-info-circle text-amber-300 text-3xl mb-2"></i>
                            <p>ບໍ່ມີຂໍ້ມູນຕິດຕໍ່</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- เพิ่ม link ไปยัง Animate.css เพื่อใช้ animation -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ສຳລັບປິດຂໍ້ຄວາມແຈ້ງເຕືອນ
    const alerts = document.querySelectorAll('[role="alert"]');
    alerts.forEach(alert => {
        const closeButton = alert.querySelector('svg[role="button"]');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                alert.classList.add('animate__fadeOutUp');
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }
        
        // ປິດອັດຕະໂນມັດຫຼັງຈາກ 5 ວິນາທີ
        setTimeout(() => {
            alert.classList.add('animate__fadeOutUp');
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // AJAX เพื่อโหลดข้อมูลกิจกรรมผู้ใช้
    const userId = <?= $user_id ?>;
    const activityContainer = document.getElementById('activity-container');
    const loadMoreBtn = document.getElementById('load-more-activity');
    let currentPage = 1;
    
    function loadUserActivity(page = 1, replace = true) {
        // ในที่นี้เราจะจำลองการโหลดข้อมูลด้วย setTimeout
        // ในการใช้งานจริง คุณควรใช้ fetch() เพื่อเรียก API
        
        setTimeout(() => {
            // สร้างข้อมูลจำลอง (dummy data)
            const activities = [
                { type: 'login', message: 'ເຂົ້າສູ່ລະບົບສຳເລັດ', date: '20/06/2025 10:48', icon: 'sign-in-alt', color: 'green' },
                { type: 'update', message: 'ອັບເດດຂໍ້ມູນສ່ວນຕົວ', date: '19/06/2025 14:22', icon: 'user-edit', color: 'blue' },
                { type: 'report', message: 'ສ້າງລາຍງານຈຳນວນພະສົງ', date: '18/06/2025 09:15', icon: 'file-alt', color: 'amber' }
            ];
            
            let html = '';
            if (activities.length > 0) {
                activities.forEach(activity => {
                    html += `
                    <div class="flex items-center p-3 rounded-lg hover:bg-${activity.color}-50 transition-colors">
                        <div class="w-10 h-10 rounded-full bg-${activity.color}-100 flex items-center justify-center mr-3">
                            <i class="fas fa-${activity.icon} text-${activity.color}-600"></i>
                        </div>
                        <div class="flex-grow">
                            <p class="font-medium">${activity.message}</p>
                            <p class="text-sm text-gray-500">${activity.date}</p>
                        </div>
                    </div>`;
                });
            } else {
                html = `<div class="text-center py-6 text-gray-500">
                            <i class="fas fa-history text-amber-300 text-3xl mb-2"></i>
                            <p>ບໍ່ມີກິດຈະກຳລ່າສຸດ</p>
                        </div>`;
            }
            
            if (replace) {
                activityContainer.innerHTML = html;
            } else {
                activityContainer.insertAdjacentHTML('beforeend', html);
            }
            
            // แสดงหรือซ่อนปุ่มโหลดเพิ่มเติม
            if (page >= 3) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.style.display = 'flex';
            }
            
            currentPage = page;
        }, 1000); // จำลองการโหลดข้อมูล 1 วินาที
    }
    
    // เริ่มโหลดข้อมูลกิจกรรมเมื่อโหลดหน้าเสร็จ
    loadUserActivity();
    
    // เพิ่ม Event listener สำหรับปุ่มโหลดเพิ่มเติม
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            loadMoreBtn.innerHTML = '<span class="mr-1">ກຳລັງໂຫລດ...</span> <i class="fas fa-spinner fa-spin"></i>';
            loadUserActivity(currentPage + 1, false);
            setTimeout(() => {
                loadMoreBtn.innerHTML = '<span class="mr-1">ໂຫລດເພີ່ມເຕີມ</span> <i class="fas fa-sync"></i>';
            }, 1000);
        });
    }
    
    // เพิ่มเอฟเฟกต์ Parallax เล็กน้อย
    window.addEventListener('scroll', function() {
        const scrolled = window.scrollY;
        const headerSection = document.querySelector('.header-section');
        if (headerSection) {
            headerSection.style.backgroundPositionY = -(scrolled * 0.1) + 'px';
        }
    });
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>