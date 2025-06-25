<?php
ob_start();
session_start();
$page_title = 'ຈັດການຜູ້ໃຊ້ງານ';
require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'auth/');
    exit;
}

// ກວດສອບສິດການໃຊ້ງານ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';

// ອະນຸຍາດໃຫ້ເບິ່ງລາຍການຜູໃຊ້ສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກວດສອບແທັບທີເລືອກ
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all_users';

// ກໍານົດຄ່າຕົວກັ່ນຕອງ
$search = isset($_GET['search']) ? $_GET['search'] : '';
$province_filter = isset($_GET['province']) ? (int)$_GET['province'] : 0;
$temple_filter = isset($_GET['temple']) ? (int)$_GET['temple'] : 0;
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ກວດສອບວ່າແທັບທີເລືອກແມ່ນ 'all_users' ຫຼື ບໍ່
if (!isset($_GET['ajax'])) {
    require_once '../includes/header.php';
}
// ເວລາຖືກຮ້ອງຂໍຂໍ້ມູນ AJAX
if (isset($_GET['ajax'])) {
    // อย่า require header/footer หรือ echo อะไรตรงนี้
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'manage_provinces' && isset($_GET['user_id']) && $is_superadmin) {
        $user_id = (int)$_GET['user_id'];
        
        // ດຶງຂໍ້ມູນຜູ້ໃຊ້
        $user_stmt = $pdo->prepare("SELECT id, name, username FROM users WHERE id = ? AND role = 'province_admin'");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'ບໍ່ພົບຜູ້ໃຊ້']);
            exit;
        }
        
        // ດຶງລາຍຊື່ແຂວງທັງໝົດ
        $provinces_stmt = $pdo->query("SELECT province_id, province_name, province_code FROM provinces ORDER BY province_name");
        $provinces = $provinces_stmt->fetchAll();
        
        // ດຶງແຂວງທີ່ຖືກມອບໝາຍໃຫ້ຜູໃຊ້ນີ້
        $assigned_stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
        $assigned_stmt->execute([$user_id]);
        $assigned_provinces = array_column($assigned_stmt->fetchAll(), 'province_id');
        
        echo json_encode([
            'status' => 'success',
            'user' => $user,
            'provinces' => $provinces,
            'assigned_provinces' => $assigned_provinces
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'save_provinces' && isset($_POST['user_id']) && $is_superadmin) {
        $user_id = (int)$_POST['user_id'];
        $provinces = isset($_POST['provinces']) ? $_POST['provinces'] : [];
        
        // ກວດສອບວ່າຜູໃຊ້ມີບົດບາດເປັນ province_admin ຫຼືບໍ່
        $user_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'province_admin'");
        $user_stmt->execute([$user_id]);
        if (!$user_stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'ຜູໃຊ້ນີ້ບໍ່ແມ່ນຜູ້ດູແລລະດັບແຂວງ']);
            exit;
        }
        
        try {
            // ເລີ່ມ transaction
            $pdo->beginTransaction();
            
            // ລຶບການມອບໝາຍເກົ່າ
            $delete_stmt = $pdo->prepare("DELETE FROM user_province_access WHERE user_id = ?");
            $delete_stmt->execute([$user_id]);
            
            // ເພີ່ມການມອບໝາຍໃໝ່
            if (!empty($provinces)) {
                $insert_stmt = $pdo->prepare("INSERT INTO user_province_access (user_id, province_id, assigned_by) VALUES (?, ?, ?)");
                foreach ($provinces as $province_id) {
                    $insert_stmt->execute([$user_id, $province_id, $_SESSION['user']['id']]);
                }
            }
            
            // ຢືນຢັນ transaction
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'message' => 'ບັນທຶກຂໍ້ມູນສຳເລັດ']);
        } catch (PDOException $e) {
            // ຍົກເລີກ transaction ກໍລະນີເກີດຂໍ້ຜິດພາດ
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()]);
        }
        exit;
    }
    exit; // สำคัญ!
}

// ສ້າງເງື່ອນໄຂ SQL ຕາມແທັບ ແລະ ຕົວກັ່ນຕອງ
$conditions = [];
$params = [];

// ເພີ່ມເງື່ອນໄຂຕາມແທັບທີເລືອກ
if ($tab === 'temple_admins') {
    $conditions[] = "u.role = 'admin'";
} elseif ($tab === 'province_admins') {
    $conditions[] = "u.role = 'province_admin'";
} elseif ($tab === 'superadmins') {
    $conditions[] = "u.role = 'superadmin'";
} elseif ($tab === 'regular_users') {
    $conditions[] = "u.role = 'user'";
}

// ຖ້າເປັນ admin ຈະສາມາດເບິ່ງສະເພາະຜູໃຊ້ໃນວັດຂອງຕົນເອງເທົ່ານັ້ນ
if ($is_admin) {
    $conditions[] = "u.temple_id = ?";
    $params[] = $_SESSION['user']['temple_id'];
}

// ຖ້າເປັນ province_admin ຈະສາມາດເບິ່ງສະເພາະຜູໃຊ້ໃນແຂວງຂອງຕົນເອງເທົ່ານັ້ນ
if ($is_province_admin) {
    $conditions[] = "t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";
    $params[] = $_SESSION['user']['id'];
}

// ເພີ່ມເງື່ອນໄຂຕົວກັ່ນຕອງ
if (!empty($search)) {
    $conditions[] = "(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($province_filter > 0) {
    $conditions[] = "t.province_id = ?";
    $params[] = $province_filter;
}

if ($temple_filter > 0) {
    $conditions[] = "u.temple_id = ?";
    $params[] = $temple_filter;
}

if (!empty($role_filter)) {
    $conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

// ສ້າງສ່ວນ WHERE ຂອງ SQL query
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ດຶງຂໍ້ມູນຜູໃຊ້
$sql = "
    SELECT 
        u.*,
        t.name as temple_name,
        t.province_id,
        p.province_name
    FROM 
        users u
    LEFT JOIN 
        temples t ON u.temple_id = t.id
    LEFT JOIN 
        provinces p ON t.province_id = p.province_id
    $where_clause
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ດຶງຂໍ້ມູນແຂວງທັງໝົດສຳລັບຕົວກັ່ນຕອງ
$provinces = [];
if ($is_superadmin) {
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll();
} elseif ($is_province_admin) {
    $province_stmt = $pdo->prepare("
        SELECT p.province_id, p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $provinces = $province_stmt->fetchAll();
}

// ດຶງຂໍ້ມູນວັດສຳລັບຕົວກັ່ນຕອງ
$temples = [];
if ($is_superadmin) {
    $temple_sql = "SELECT id, name FROM temples ORDER BY name";
    $temple_stmt = $pdo->query($temple_sql);
    $temples = $temple_stmt->fetchAll();
} elseif ($is_admin) {
    $temple_sql = "SELECT id, name FROM temples WHERE id = ?";
    $temple_stmt = $pdo->prepare($temple_sql);
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
} elseif ($is_province_admin) {
    $temple_sql = "
        SELECT t.id, t.name 
        FROM temples t
        JOIN provinces p ON t.province_id = p.province_id
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY t.name
    ";
    $temple_stmt = $pdo->prepare($temple_sql);
    $temple_stmt->execute([$_SESSION['user']['id']]);
    $temples = $temple_stmt->fetchAll();
}

// HTML ຫົວຂໍ້
$title = "ການຈັດການຜູ້ໃຊ້";
?>

<!-- เพิ่ม CSS เฉพาะสำหรับหน้านี้ -->
 <style src="<?= $base_url ?>assets/css/users.css"></style>
 
 <style>
.opacity-0 {
    opacity: 0;
}

.fixed {
    transition: opacity 0.2s ease;
}

#delete-confirm-modal .bg-white {
    animation: fadeInUp 0.3s ease forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>



<!-- Modal สำหรับการจัดการแขวง -->
<div id="manage-provinces-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden card">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-900">ຈັດການແຂວງ</h3>
                <button type="button" class="close-modal-btn text-gray-400 hover:text-gray-500">
                    <span class="sr-only">ປິດ</span>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div id="provinces-loading" class="p-6">
            <div class="flex justify-center items-center">
                <i class="fas fa-spinner fa-spin text-2xl text-primary"></i>
                <span class="ml-2">ກຳລັງໂຫຼດຂໍ້ມູນ...</span>
            </div>
        </div>
        
        <div id="provinces-error" class="p-6 hidden">
            <div class="bg-red-100 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">ເກີດຂໍ້ຜິດພາດ</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p id="error-message">ບໍ່ສາມາດໂຫຼດຂໍ້ມູນແຂວງໄດ້</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="provinces-content" class="p-6 hidden">
            <div class="mb-4 p-4 bg-light rounded-lg">
                <div class="flex items-center mb-3">
                    <div class="icon-circle">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <p class="text-dark"><strong>ຊື່ຜູ້ດູແລ:</strong> <span id="admin-name"></span></p>
                        <p class="text-muted"><strong>ຊື່ຜູ້ໃຊ້:</strong> <span id="admin-username"></span></p>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="text-lg font-medium mb-2 text-dark">ແຂວງທີ່ຮັບຜິດຊອບ</h4>
                <div class="flex space-x-2 mb-3">
                    <button id="select-all-btn" class="px-3 py-2 bg-accent text-dark rounded-md text-sm hover:bg-accent/80 transition">
                        <i class="fas fa-check-square mr-1"></i> ເລືອກທັງໝົດ
                    </button>
                    <button id="clear-all-btn" class="px-3 py-2 bg-light text-dark rounded-md text-sm hover:bg-light/80 transition">
                        <i class="fas fa-square mr-1"></i> ຍົກເລີກທັງໝົດ
                    </button>
                </div>
                <div id="provinces-list" class="max-h-64 overflow-y-auto p-4 border rounded-lg bg-lightest">
                    <!-- ລາຍການແຂວງ -->
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
            <button type="button" class="close-modal-btn px-4 py-2 bg-light text-dark rounded-md hover:bg-light/80 transition mr-2">
                ຍົກເລີກ
            </button>
            <button type="button" id="save-provinces-btn" class="btn btn-primary px-4 py-2 rounded-md transition">
                <i class="fas fa-save mr-1"></i> ບັນທຶກຂໍ້ມູນ
            </button>
        </div>
    </div>
</div>

<div class="container mx-auto px-4 py-8 page-container">
    <!-- ส่วนหัว -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 header-section rounded-xl p-6">
        <div class="mb-4 md:mb-0">
            <h1 class="text-2xl font-bold text-dark header-title"><?= $title ?></h1>
            <p class="text-muted">ລາຍຊື່ຜູ້ໃຊ້ທັງໝົດໃນລະບົບ</p>
        </div>
        
        <?php if ($is_superadmin || $is_admin): ?>
        <a href="<?= $base_url ?>users/add.php" class="btn btn-primary rounded-md w-full md:w-auto text-center">
            <i class="fas fa-plus mr-1"></i> ເພີ່ມຜູ້ໃຊ້
        </a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-100 border border-success text-success px-4 py-3 rounded-lg relative mb-4 shadow-sm" role="alert">
        <span class="block sm:inline"><?= $_SESSION['success'] ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3 close-alert">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-100 border border-danger text-danger px-4 py-3 rounded-lg relative mb-4 shadow-sm" role="alert">
        <span class="block sm:inline"><?= $_SESSION['error'] ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3 close-alert">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>
    
    <!-- แท็บเมนู -->
    <div class="border-b border-gray-200 mb-4 tab-scroll">
        <ul class="flex -mb-px">
            <li class="mr-1">
                <a href="?tab=all_users" class="inline-block p-4 <?= $tab === 'all_users' ? 'border-b-2 border-primary text-primary font-medium' : 'border-b-2 border-transparent hover:text-muted hover:border-muted' ?>">
                    <i class="fas fa-users mr-1"></i> ຜູໃຊ້ທັງໝົດ
                </a>
            </li>
            
            <?php if ($is_superadmin): ?>
            <li class="mr-1">
                <a href="?tab=superadmins" class="inline-block p-4 <?= $tab === 'superadmins' ? 'border-b-2 border-primary text-primary font-medium' : 'border-b-2 border-transparent hover:text-muted hover:border-muted' ?>">
                    <i class="fas fa-user-shield mr-1"></i> ຜູ້ດູແລລະບົບສູງສຸດ
                </a>
            </li>
            <?php endif; ?>
            
            <li class="mr-1">
                <a href="?tab=temple_admins" class="inline-block p-4 <?= $tab === 'temple_admins' ? 'border-b-2 border-primary text-primary font-medium' : 'border-b-2 border-transparent hover:text-muted hover:border-muted' ?>">
                    <i class="fas fa-place-of-worship mr-1"></i> ຜູ້ດູແລວັດ
                </a>
            </li>
            
            <?php if ($is_superadmin): ?>
            <li class="mr-1">
                <a href="?tab=province_admins" class="inline-block p-4 <?= $tab === 'province_admins' ? 'border-b-2 border-primary text-primary font-medium' : 'border-b-2 border-transparent hover:text-muted hover:border-muted' ?>">
                    <i class="fas fa-map-marked-alt mr-1"></i> ຜູ້ດູແລລະດັບແຂວງ
                </a>
            </li>
            <?php endif; ?>
            
            <li class="mr-1">
                <a href="?tab=regular_users" class="inline-block p-4 <?= $tab === 'regular_users' ? 'border-b-2 border-primary text-primary font-medium' : 'border-b-2 border-transparent hover:text-muted hover:border-muted' ?>">
                    <i class="fas fa-user mr-1"></i> ຜູໃຊ້ທົ່ວໄປ
                </a>
            </li>
        </ul>
    </div>
    
    <!-- ตัวกรองการค้นหา -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6 card filter-section">
        <div class="px-4 py-5 sm:p-6">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                
                <div>
                    <label for="search" class="block text-sm font-medium text-dark mb-1">ຄົ້ນຫາ</label>
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                        class="form-input w-full" 
                        placeholder="ຊື່ ຫຼື ອີເມວ">
                </div>
                
                <?php if (!empty($provinces)): ?>
                <div>
                    <label for="province" class="block text-sm font-medium text-dark mb-1">ແຂວງ</label>
                    <select name="province" id="province" class="form-select w-full">
                        <option value="0">ທັງໝົດ</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?= $province['province_id'] ?>" <?= $province_filter == $province['province_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($province['province_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($temples)): ?>
                <div>
                    <label for="temple" class="block text-sm font-medium text-dark mb-1">ວັດ</label>
                    <select name="temple" id="temple" class="form-select w-full">
                        <option value="0">ທັງໝົດ</option>
                        <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $temple_filter == $temple['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-dark mb-1">ສະຖານະ</label>
                    <select name="status" id="status" class="form-select w-full">
                        <option value="">ທັງໝົດ</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>ໃຊ້ງານໄດ້</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary w-full md:w-auto">
                        <i class="fas fa-search mr-1"></i> ຄົ້ນຫາ
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ตารางรายการผู้ใช้ -->
    <div class="bg-white rounded-lg shadow-md data-table">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-accent/30 responsive-table">
                <thead class="bg-light">
                    <tr class="table-header">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-dark uppercase tracking-wider">ຊື່ຜູ້ໃຊ້</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-dark uppercase tracking-wider">ວັດ / ແຂວງ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-dark uppercase tracking-wider">ບົດບາດ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-dark uppercase tracking-wider">ສະຖານະ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-dark uppercase tracking-wider hidden md:table-cell">ສ້າງເມື່ອ</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-dark uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-accent/10">
                    <?php if (empty($users)): ?>
                    <tr class="table-row">
                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-center text-muted">ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="table-row hover:bg-light transition-colors">
                            <td class="px-6 py-4" data-label="ຊື່ຜູ້ໃຊ້">
                                <div class="flex items-center">
                                    <div class="hidden md:block mr-3">
                                        <div class="category-icon">
                                            <i class="fas <?= $user['role'] === 'superadmin' ? 'fa-user-shield' : ($user['role'] === 'admin' ? 'fa-user-cog' : ($user['role'] === 'province_admin' ? 'fa-map-marker-alt' : 'fa-user')) ?>"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-dark">
                                            <?= htmlspecialchars($user['name']) ?>
                                        </div>
                                        <div class="text-sm text-muted">
                                            <?= htmlspecialchars($user['email'] ?? 'ບໍ່ມີອີເມວ') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4" data-label="ວັດ / ແຂວງ">
                                <?php if(!empty($user['temple_name'])): ?>
                                <div class="text-sm text-dark">
                                    <i class="fas fa-place-of-worship text-primary-dark mr-1"></i>
                                    <?= htmlspecialchars($user['temple_name']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($user['province_name'])): ?>
                                <div class="text-sm text-muted">
                                    <i class="fas fa-map-marker-alt text-secondary mr-1"></i>
                                    <?= htmlspecialchars($user['province_name']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if(empty($user['temple_name']) && empty($user['province_name'])): ?>
                                <div class="text-sm text-muted">
                                    <i class="fas fa-minus-circle mr-1"></i> ບໍ່ໄດ້ລະບຸ
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4" data-label="ບົດບາດ">
                                <?php if ($user['role'] === 'superadmin'): ?>
                                    <span class="status-badge bg-purple-100 text-purple-800 border border-purple-200">
                                        <i class="fas fa-user-shield"></i> ຜູ້ດູແລລະບົບສູງສຸດ
                                    </span>
                                <?php elseif ($user['role'] === 'admin'): ?>
                                    <span class="status-badge bg-blue-100 text-blue-800 border border-blue-200">
                                        <i class="fas fa-user-cog"></i> ຜູ້ດູແລວັດ
                                    </span>
                                <?php elseif ($user['role'] === 'province_admin'): ?>
                                    <span class="status-badge bg-indigo-100 text-indigo-800 border border-indigo-200">
                                        <i class="fas fa-map-marker-alt"></i> ຜູໍແລລະດັບແຂວງ
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge bg-gray-100 text-gray-800 border border-gray-200">
                                        <i class="fas fa-user"></i> ຜູໃຊ້ທົ່ວໄປ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4" data-label="ສະຖານະ">
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i> ໃຊ້ງານໄດ້
                                    </span>
                                <?php elseif ($user['status'] === 'pending'): ?>
                                    <span class="status-badge bg-yellow-100 text-yellow-800 border border-yellow-300">
                                        <i class="fas fa-clock"></i> ລໍຖ້າອະນຸມັດ
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge bg-red-100 text-red-800 border border-red-300">
                                        <i class="fas fa-ban"></i> ປິດໃຊ້ງານ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell text-sm text-muted" data-label="ສ້າງເມື່ອ">
                                <i class="far fa-calendar-alt mr-1"></i>
                                <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium" data-label="ຈັດການ">
                                <div class="hidden md:flex justify-end space-x-1 action-buttons">
                                    <!-- ปุ่มแบบไอคอน (Desktop) -->
                                    <a href="<?= $base_url ?>users/view.php?id=<?= $user['id'] ?>" 
                                       class="text-primary hover:text-primary-dark hover:bg-primary/10 p-2 rounded-full transition action-button" 
                                       title="ເບິ່ງຂໍ້ມູນ">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (($is_superadmin) || 
                                             ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id'] && $user['role'] != 'superadmin' && $user['role'] != 'province_admin') ||
                                             ($is_province_admin && isset($user['province_id']) && in_array($user['province_id'], array_column($provinces, 'province_id')) && $user['role'] != 'superadmin' && $user['role'] != 'admin')): ?>
                                        
                                        <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" 
                                           class="text-amber-600 hover:text-amber-800 hover:bg-amber-50 p-2 rounded-full transition action-button" 
                                           title="ແກ້ໄຂຂໍ້ມູນ">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                            <?php if ($user['status'] === 'pending'): ?>
                                                <a href="<?= $base_url ?>users/approve.php?id=<?= $user['id'] ?>" 
                                                   class="text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-full transition action-button" 
                                                   title="ອະນຸມັດ">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php elseif ($user['status'] === 'active'): ?>
                                                <a href="<?= $base_url ?>users/suspend.php?id=<?= $user['id'] ?>" 
                                                   class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-full transition action-button" 
                                                   title="ລະງັບການໃຊ້ງານ">
                                                    <i class="fas fa-ban"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $base_url ?>users/activate.php?id=<?= $user['id'] ?>" 
                                                   class="text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-full transition action-button" 
                                                   title="ເປີດໃຊ້ງານ">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="javascript:void(0);" 
                                               onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                               class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-full transition action-button" 
                                               title="ລຶບ">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_superadmin && $user['role'] === 'province_admin'): ?>
                                        <a href="javascript:void(0);" 
                                           class="text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 p-2 rounded-full transition action-button manage-provinces-btn" 
                                           data-user-id="<?= $user['id'] ?>" 
                                           title="ຈັດການແຂວງທີ່ຮັບຜິດຊອບ">
                                            <i class="fas fa-map-marked-alt"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <!-- Mobile: ปุ่มใหญ่แบบ card -->
                                <div class="flex flex-col md:hidden gap-2 mt-2 action-buttons">
                                    <a href="<?= $base_url ?>users/view.php?id=<?= $user['id'] ?>" 
                                       class="flex items-center justify-center p-2 px-3 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition action-button">
                                        <i class="fas fa-eye mr-2"></i> ເບິ່ງ
                                    </a>
                                    <?php if (($is_superadmin) || 
                                             ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id'] && $user['role'] != 'superadmin' && $user['role'] != 'province_admin') ||
                                             ($is_province_admin && isset($user['province_id']) && in_array($user['province_id'], array_column($provinces, 'province_id')) && $user['role'] != 'superadmin' && $user['role'] != 'admin')): ?>
                                        
                                        <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" 
                                           class="flex items-center justify-center p-2 px-3 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition action-button">
                                            <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                            <?php if ($user['status'] === 'pending'): ?>
                                                <a href="<?= $base_url ?>users/approve.php?id=<?= $user['id'] ?>" 
                                                   class="flex items-center justify-center p-2 px-3 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition action-button">
                                                    <i class="fas fa-check mr-2"></i> ອະນຸມັດ
                                                </a>
                                            <?php elseif ($user['status'] === 'active'): ?>
                                                <a href="<?= $base_url ?>users/suspend.php?id=<?= $user['id'] ?>" 
                                                   class="flex items-center justify-center p-2 px-3 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition action-button">
                                                    <i class="fas fa-ban mr-2"></i> ລະງັບ
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $base_url ?>users/activate.php?id=<?= $user['id'] ?>" 
                                                   class="flex items-center justify-center p-2 px-3 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition action-button">
                                                    <i class="fas fa-check mr-2"></i> ເປີດໃຊ້ງານ
                                                </a>
                                            <?php endif; ?>
                                            <button type="button"
                                               onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" 
                                               class="flex items-center justify-center p-2 px-3 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition action-button">
                                                <i class="fas fa-trash mr-2"></i> ລຶບ
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($is_superadmin && $user['role'] === 'province_admin'): ?>
                                        <button type="button"
                                           class="flex items-center justify-center p-2 px-3 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition action-button manage-provinces-btn" 
                                           data-user-id="<?= $user['id'] ?>">
                                            <i class="fas fa-map-marked-alt mr-2"></i> ແຂວງຮັບຜິດຊອບ
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript ຈັດການຟັງຊັນຕ່າງໆ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ปิดการแจ้งเตือน
        document.querySelectorAll('.close-alert').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentNode.classList.add('opacity-0');
                setTimeout(() => {
                    this.parentNode.style.display = 'none';
                }, 300);
            });
        });
        
        // เพิ่มเอฟเฟกต์ ripple ให้กับปุ่มทั้งหมด
        const buttons = document.querySelectorAll('.btn, .action-button');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const x = e.clientX - e.target.getBoundingClientRect().left;
                const y = e.clientY - e.target.getBoundingClientRect().top;
                
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.left = `${x}px`;
                ripple.style.top = `${y}px`;
                ripple.style.transform = 'translate(-50%, -50%) scale(0)';
                ripple.style.width = '200px';
                ripple.style.height = '200px';
                ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                ripple.style.borderRadius = '50%';
                ripple.style.pointerEvents = 'none';
                ripple.style.opacity = '0.6';
                ripple.style.transition = 'transform 0.5s, opacity 0.5s';
                
                if (!button.style.position || button.style.position === 'static') {
                    button.style.position = 'relative';
                }
                button.style.overflow = 'hidden';
                
                button.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.style.transform = 'translate(-50%, -50%) scale(4)';
                    ripple.style.opacity = '0';
                }, 1);
                
                setTimeout(() => {
                    ripple.remove();
                }, 500);
            });
        });
        
        // คงโค้ดเดิมสำหรับการจัดการฟังก์ชันการทำงานของ modal
        // Variables
        const modal = document.getElementById('manage-provinces-modal');
        const loadingState = document.getElementById('provinces-loading');
        const contentState = document.getElementById('provinces-content');
        const errorState = document.getElementById('provinces-error');
        const errorMessage = document.getElementById('error-message');
        const adminName = document.getElementById('admin-name');
        const adminUsername = document.getElementById('admin-username');
        const provincesList = document.getElementById('provinces-list');
        const selectAllBtn = document.getElementById('select-all-btn');
        const clearAllBtn = document.getElementById('clear-all-btn');
        const saveProvincesBtn = document.getElementById('save-provinces-btn');
        
        let currentUserId = null;
        let provinces = [];
        let assignedProvinces = [];
        
        // Close modal buttons
        document.querySelectorAll('.close-modal-btn').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });
        
        // Close modal when clicking outside
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }
        
        // Manage provinces buttons
        document.querySelectorAll('.manage-provinces-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                currentUserId = userId;
                openModal();
                showLoading();
                loadProvinceData(userId);
            });
        });
        
        // Select all provinces
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('#provinces-list input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
        }
        
        // Clear all provinces
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function() {
                document.querySelectorAll('#provinces-list input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            });
        }
        
        // Save provinces button
        if (saveProvincesBtn) {
            saveProvincesBtn.addEventListener('click', saveProvinces);
        }
        
        // Open modal
        function openModal() {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            
            // เพิ่มอนิเมชันแสดง modal
            setTimeout(() => {
                modal.querySelector('.card').classList.add('animate__animated', 'animate__fadeInUp', 'animate__faster');
            }, 10);
        }
        
        // Close modal
        function closeModal() {
            const card = modal.querySelector('.card');
            card.classList.add('animate__animated', 'animate__fadeOutDown', 'animate__faster');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                card.classList.remove('animate__animated', 'animate__fadeOutDown', 'animate__faster');
            }, 200);
        }
        
        // Show loading state
        function showLoading() {
            loadingState.style.display = 'block';
            contentState.style.display = 'none';
            errorState.style.display = 'none';
        }
        
        // Show error state
        function showError(message) {
            loadingState.style.display = 'none';
            contentState.style.display = 'none';
            errorState.style.display = 'block';
            errorMessage.textContent = message;
        }
        
        // Show content state
        function showContent() {
            loadingState.style.display = 'none';
            contentState.style.display = 'block';
            errorState.style.display = 'none';
        }
        
        // Load provinces data
        function loadProvinceData(userId) {
            const baseUrl = '<?= $base_url ?>';
            fetch(`${baseUrl}users/?tab=province_admins&ajax=manage_provinces&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update user info
                        adminName.textContent = data.user.name;
                        adminUsername.textContent = data.user.username;
                        
                        // Store data
                        provinces = data.provinces;
                        assignedProvinces = data.assigned_provinces;
                        
                        // Render provinces list
                        renderProvincesList();
                        
                        // Show content
                        showContent();
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading province data:', error);
                    showError('ບໍ່ສາມາດເຊື່ອມຕໍ່ກັບເຊີບເວີໄດ້');
                });
        }
        
        // Render provinces list
        function renderProvincesList() {
            provincesList.innerHTML = '';
            
            provinces.forEach(province => {
                const isChecked = assignedProvinces.includes(parseInt(province.province_id));
                provincesList.innerHTML += `
                    <div class="flex items-center mb-3 p-2 hover:bg-accent/10 rounded-md transition-colors">
                        <input type="checkbox" id="province-${province.province_id}" 
                               value="${province.province_id}" ${isChecked ? 'checked' : ''} 
                               class="mr-3 h-5 w-5 text-primary border-gray-300 rounded focus:ring-primary">
                        <label for="province-${province.province_id}" class="text-dark cursor-pointer flex-grow">
                            <span class="font-medium">${province.province_name}</span> 
                            <span class="text-muted ml-2">(${province.province_code})</span>
                        </label>
                    </div>
                `;
            });
            
            // เพิ่มการคลิกที่ label เพื่อเลือก checkbox
            document.querySelectorAll('#provinces-list label').forEach(label => {
                label.addEventListener('click', function() {
                    const checkbox = document.getElementById(this.getAttribute('for'));
                    checkbox.checked = !checkbox.checked;
                });
            });
        }
        
        // Save the selected provinces
        function saveProvinces() {
            // Show loading state
            saveProvincesBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ກຳລັງບັນທຶກ...';
            saveProvincesBtn.disabled = true;
            
            // Get selected province IDs
            const selectedProvinces = Array.from(
                document.querySelectorAll('#provinces-list input[type="checkbox"]:checked')
            ).map(checkbox => checkbox.value);
            
            // Create form data
            const formData = new FormData();
            formData.append('ajax', 'save_provinces');
            formData.append('user_id', currentUserId);
            selectedProvinces.forEach(id => {
                formData.append('provinces[]', id);
            });
            
            // Save data
            const baseUrl = '<?= $base_url ?>';
            fetch(`${baseUrl}users/?tab=province_admins`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Show success message
                        const successToast = document.createElement('div');
                        successToast.className = 'fixed bottom-4 right-4 bg-success text-white px-4 py-2 rounded-lg shadow-lg z-50 animate__animated animate__fadeInUp';
                        successToast.innerHTML = '<i class="fas fa-check-circle mr-2"></i> ບັນທຶກຂໍ້ມູນສຳເລັດແລ້ວ';
                        document.body.appendChild(successToast);
                        
                        setTimeout(() => {
                            successToast.classList.add('animate__fadeOutDown');
                            setTimeout(() => {
                                successToast.remove();
                            }, 300);
                        }, 3000);
                        
                        closeModal();
                        
                        // Reload page to show updated data
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert(data.message || 'ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກຂໍ້ມູນ');
                    }
                })
                .catch(error => {
                    console.error('Error saving province data:', error);
                    alert('ບໍ່ສາມາດເຊື່ອມຕໍ່ກັບເຊີບເວີໄດ້');
                })
                .finally(() => {
                    // Reset button state
                    saveProvincesBtn.innerHTML = '<i class="fas fa-save mr-1"></i> ບັນທຶກຂໍ້ມູນ';
                    saveProvincesBtn.disabled = false;
                });
        }
    });
    
    // ຟັງຊັນຢືນຢັນການລຶບຜູ້ໃຊ້
    function confirmDelete(userId, userName) {
        // ตรวจสอบว่ามี Modal อยู่แล้วหรือไม่
        let existingModal = document.getElementById('delete-confirm-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // สร้าง Modal ใหม่
        const confirmModal = document.createElement('div');
        confirmModal.id = 'delete-confirm-modal';
        confirmModal.className = 'fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50';
        confirmModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
                <div class="text-center mb-4">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <svg class="h-10 w-10 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">ຢືນຢັນການລຶບ</h3>
                    <p class="mt-2 text-gray-600">ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບຜູໃຊ້ "${userName}"?</p>
                </div>
                <div class="flex justify-end space-x-3">
                    <button id="cancel-delete-btn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition">
                        ຍົກເລີກ
                    </button>
                    <button id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
                        <svg class="inline-block h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg> ລຶບຂໍໍ້ມູນ
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(confirmModal);
        document.body.classList.add('overflow-hidden');
        
        // สร้าง form เพื่อส่งข้อมูลแทนการใช้ window.location
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = '<?= $base_url ?>users/delete.php';
        form.style.display = 'none';
        
        const idField = document.createElement('input');
        idField.type = 'hidden';
        idField.name = 'id';
        idField.value = userId;
        form.appendChild(idField);
        
        document.body.appendChild(form);
        
        // เพิ่ม event listeners
        document.getElementById('cancel-delete-btn').addEventListener('click', () => {
            closeDeleteModal(confirmModal);
        });
        
        document.getElementById('confirm-delete-btn').addEventListener('click', () => {
            // เปลี่ยนข้อความปุ่มและปิดการใช้งาน
            const confirmBtn = document.getElementById('confirm-delete-btn');
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> ກຳລັງລຶບ...';
            confirmBtn.disabled = true;
            
            // ส่ง form แทนการใช้ window.location
            form.submit();
        });
        
        // ปิด modal เมื่อคลิกพื้นหลัง
        confirmModal.addEventListener('click', (e) => {
            if (e.target === confirmModal) {
                closeDeleteModal(confirmModal);
            }
        });
        
        // เพิ่ม keyboard support
        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escHandler);
                closeDeleteModal(confirmModal);
            }
        });
        
        // ฟังก์ชันปิด modal
        function closeDeleteModal(modal) {
            modal.classList.add('opacity-0');
            setTimeout(() => {
                document.body.removeChild(modal);
                document.body.classList.remove('overflow-hidden');
                
                // ลบ form ที่สร้างด้วย
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 200);
        }
    }
    
    // เพิ่มฟังก์ชันสำหรับการจัดการ responsive table
    function initializeResponsiveTable() {
        const table = document.querySelector('.responsive-table');
        if (!table) return;
        
        // เพิ่ม touch scroll indicator
        let isScrolling = false;
        table.addEventListener('scroll', function() {
            if (!isScrolling) {
                table.style.boxShadow = 'inset -10px 0 10px -10px rgba(0,0,0,0.1)';
                isScrolling = true;
            }
            
            clearTimeout(window.scrollTimeout);
            window.scrollTimeout = setTimeout(() => {
                table.style.boxShadow = 'none';
                isScrolling = false;
            }, 150);
        });
    }
    
    // เพิ่มฟังก์ชันสำหรับ smooth scroll ใน tab navigation
    function initializeTabScroll() {
        const tabContainer = document.querySelector('.tab-scroll');
        if (!tabContainer) return;
        
        const activeTab = tabContainer.querySelector('a[class*="border-primary"]');
        if (activeTab && window.innerWidth <= 768) {
            activeTab.scrollIntoView({ 
                behavior: 'smooth', 
                inline: 'center',
                block: 'nearest'
            });
        }
    }
    
    // เพิ่มฟังก์ชันสำหรับ loading states
    function showLoadingState(element, originalText) {
        element.disabled = true;
        element.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>ກຳລັງໂຫຼດ...';
        
        return () => {
            element.disabled = false;
            element.innerHTML = originalText;
        };
    }
    
    // Initialize all functions
    initializeResponsiveTable();
    initializeTabScroll();
    
    // เพิ่ม keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Escape key closes modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.fixed:not(.hidden)');
            if (openModal) {
                const closeBtn = openModal.querySelector('.close-modal-btn');
                if (closeBtn) closeBtn.click();
            }
        }
    });
    
    // เพิ่ม accessibility improvements
    document.querySelectorAll('.action-button').forEach(button => {
        if (!button.getAttribute('aria-label') && button.title) {
            button.setAttribute('aria-label', button.title);
        }
    });
    
    // เพิ่ม auto-refresh functionality (optional)
    let autoRefreshInterval;
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(() => {
            // เฉพาะเมื่อไม่มี modal เปิดอยู่
            const hasOpenModal = document.querySelector('.fixed:not(.hidden)');
            if (!hasOpenModal) {
                window.location.reload();
            }
        }, 300000); // รีเฟรชทุก 5 นาที
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
    
    // เริ่ม auto-refresh (สามารถ comment out ได้ถ้าไม่ต้องการ)
    // startAutoRefresh();
    
    // หยุด auto-refresh เมื่อผู้ใช้ไม่ active
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            // startAutoRefresh();
        }
    });
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>