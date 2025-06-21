<?php
// filepath: c:\xampp\htdocs\temples\users\edit.php
ob_start();

$page_title = 'ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້';
require_once '../config/db.php';
require_once '../config/base_url.php';

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

// ອະນຸຍາດໃຫ້ສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກວດສອບ ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ມີຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ພ້ອມຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT u.*, t.name as temple_name, t.province_id 
    FROM users u 
    LEFT JOIN temples t ON u.temple_id = t.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂ
$has_permission = false;

// ຜູ້ໃຊ້ສາມາດແກ້ໄຂຂໍ້ມູນຕົນເອງໄດ້
if ($user_id == $current_user_id) {
    $has_permission = true;
} elseif ($is_superadmin) {
    // superadmin ສາມາດແກ້ໄຂຜູ້ໃຊ້ໄດ້ທຸກຄົນ
    $has_permission = true;
} elseif ($is_admin && $user['temple_id'] == $_SESSION['user']['temple_id']) {
    // admin ສາມາດແກ້ໄຂໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນເອງ
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'province_admin') {
        $has_permission = true;
    }
} elseif ($is_province_admin && !empty($user['province_id'])) {
    // province_admin ສາມາດແກ້ໄຂໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຕົນເອງຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_province_access 
        WHERE user_id = ? AND province_id = ?
    ");
    $province_stmt->execute([$current_user_id, $user['province_id']]);
    
    if ($province_stmt->fetchColumn() > 0 && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin') {
        $has_permission = true;
    }
}

if (!$has_permission) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ດຶງຂໍ້ມູນວັດຕາມສິດການເຂົ້າເຖິງ
$temples = [];
if ($is_superadmin) {
    // Superadmin ສາມາດເລືອກວັດໃດກໍໄດ້
    $temple_stmt = $pdo->query("SELECT id, name, province_id FROM temples ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} elseif ($is_province_admin) {
    // Province admin ສາມາດເລືອກວັດໃນແຂວງທີ່ຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT t.id, t.name, t.province_id
        FROM temples t 
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY t.name
    ");
    $province_stmt->execute([$current_user_id]);
    $temples = $province_stmt->fetchAll();
} elseif ($is_admin) {
    // Admin ສາມາດເລືອກສະເພາະວັດຂອງຕົນເອງ
    $temple_stmt = $pdo->prepare("SELECT id, name, province_id FROM temples WHERE id = ?");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ດຶງຂໍ້ມູນແຂວງ (ສຳລັບ province_admin)
$provinces = [];
$assigned_provinces = [];

if ($is_superadmin) {
    $province_stmt = $pdo->query("SELECT * FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll();
    
    // ຖ້າຜູ້ທີ່ຖືກແກ້ໄຂເປັນ province_admin ໃຫ້ດຶງແຂວງທີ່ຮັບຜິດຊອບ
    if ($user['role'] === 'province_admin') {
        $assigned_stmt = $pdo->prepare("
            SELECT province_id FROM user_province_access WHERE user_id = ?
        ");
        $assigned_stmt->execute([$user_id]);
        $assigned_provinces = $assigned_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} elseif ($is_province_admin) {
    $province_stmt = $pdo->prepare("
        SELECT p.*
        FROM provinces p
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$current_user_id]);
    $provinces = $province_stmt->fetchAll();
}

// ເມື່ອສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ຮັບຂໍ້ມູນຈາກຟອມ
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = isset($_POST['role']) ? $_POST['role'] : $user['role'];
    $temple_id = isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : $user['status'];
    $change_password = isset($_POST['change_password']) && $_POST['change_password'] == '1';
    
    // ຮັບຂໍ້ມູນແຂວง (ສຳລັບ province_admin)
    $selected_provinces = $_POST['provinces'] ?? [];
    
    $errors = [];
    
    // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    if (empty($username)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    }
    
    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່-ນາມສະກຸນ";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    }
    
    // ກວດສອບວັດ
    if (($role === 'admin' || $role === 'user') && empty($temple_id)) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ກວດສອບລະຫັດຜ່ານໃໝ່ ຖ້າມີການປ່ຽນ
    if ($change_password) {
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (empty($password)) {
            $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານ";
        } elseif ($password !== $confirm_password) {
            $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
        } elseif (strlen($password) < 6) {
            $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
        }
    }
    
    // ກວດສອບສິດໃນການປ່ຽນບົດບາດ
    if ($role !== $user['role']) {
        if ($role === 'superadmin' && !$is_superadmin) {
            $errors[] = "ທ່ານບໍ່ສາມາດປ່ຽນບົດບາດເປັນຜູ້ດູແລລະບົບສູງສຸດໄດ້";
        }
        
        if ($role === 'province_admin' && !$is_superadmin) {
            $errors[] = "ທ່ານບໍ່ສາມາດປ່ຽນບົດບາດເປັນຜູ້ດູແລລະດັບແຂວງໄດ້";
        }
        
        // Province admin ບໍ່ສາມາດປ່ຽນບົດບາດຄົນອື່ນເປັນ admin ໄດ້
        if ($is_province_admin && $role === 'admin') {
            $errors[] = "ທ່ານບໍ່ສາມາດປ່ຽນບົດບາດເປັນຜູ້ດູແລວັດໄດ້";
        }
    }
    
    // ກວດສອບວ່າຊື່ຜູ້ໃຊ້ມີແລ້ວຫຼືບໍ່ (ເວັ້ນເສຍແຕ່ເປັນຊື່ຜູ້ໃຊ້ຄົນເດີມ)
    if ($username !== $user['username']) {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$username, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
        }
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ບັນທຶກຂໍ້ມູນ
    if (empty($errors)) {
        try {
            // ເລີ່ມ transaction
            $pdo->beginTransaction();
            
            // ກະກຽມ SQL ສຳລັບການອັບເດດ
            $sql = "UPDATE users SET username = ?, name = ?, email = ?, phone = ?, updated_at = NOW()";
            $params = [$username, $name, $email, $phone];
            
            // ຖ້າມີການປ່ຽນລະຫັດຜ່ານ
            if ($change_password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
            }
            
            // ຖ້າມີສິດປ່ຽນບົດບາດ
            if (($is_superadmin || ($is_admin && $user_id != $current_user_id)) && $role !== 'superadmin') {
                $sql .= ", role = ?";
                $params[] = $role;
            }
            
            // ຖ້າມີສິດປ່ຽນວັດ
            if ($is_superadmin || $is_province_admin || ($is_admin && $user_id != $current_user_id)) {
                if ($role === 'admin' || $role === 'user') {
                    $sql .= ", temple_id = ?";
                    $params[] = $temple_id;
                } elseif ($role === 'superadmin' || $role === 'province_admin') {
                    $sql .= ", temple_id = NULL";
                }
            }
            
            // ຖ້າມີສິດປ່ຽນສະຖານະ
            if (($is_superadmin || $is_admin || $is_province_admin) && $user_id != $current_user_id) {
                $sql .= ", status = ?";
                $params[] = $status;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $user_id;
            
            // ອັບເດດຂໍ້ມູນ
            $update_stmt = $pdo->prepare($sql);
            $update_stmt->execute($params);
            
            // ຖ້າເປັນ province_admin ແລະ ຜູ້ແກ້ໄຂເປັນ superadmin, ອັບເດດແຂວງທີ່ຮັບຜິດຊອບ
            if ($role === 'province_admin' && $is_superadmin && !empty($selected_provinces)) {
                // ລຶບຂໍ້ມູນເກົ່າ
                $delete_stmt = $pdo->prepare("DELETE FROM user_province_access WHERE user_id = ?");
                $delete_stmt->execute([$user_id]);
                
                // ເພີ່ມຂໍ້ມູນໃໝ່
                $insert_stmt = $pdo->prepare("
                    INSERT INTO user_province_access (user_id, province_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                foreach ($selected_provinces as $province_id) {
                    $insert_stmt->execute([$user_id, $province_id, $current_user_id]);
                }
            }
            
            // ຢືນຢັນ transaction
            $pdo->commit();
            
            $_SESSION['success'] = "ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້ສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'users/view.php?id=' . $user_id);
            exit;
            
        } catch (PDOException $e) {
            // ຍົກເລີກ transaction ກໍລະນີເກີດຂໍ້ຜິດພາດ
            $pdo->rollBack();
            
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}

$page_title = 'ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້';
require_once '../includes/header.php';

// รับค่าสีตามบทบาทผู้ใช้
$role_colors = [
    'superadmin' => 'amber',
    'province_admin' => 'blue',
    'admin' => 'green',
    'user' => 'indigo'
];

$role_color = $role_colors[$user['role']] ?? 'amber';

// กำหนดไอคอนตามบทบาท
$role_icons = [
    'superadmin' => '<i class="fas fa-crown"></i>',
    'province_admin' => '<i class="fas fa-map-marked-alt"></i>', 
    'admin' => '<i class="fas fa-gopuram"></i>',
    'user' => '<i class="fas fa-user"></i>'
];

// สถานะแปลเป็นภาษาลาว
$status_labels = [
    'active' => 'ໃຊ້ງານ',
    'inactive' => 'ປິດໃຊ້ງານ',
    'pending' => 'ລໍຖ້າອະນຸມັດ'
];

// บทบาทแปลเป็นภาษาลาว
$role_labels = [
    'superadmin' => 'ຜູ້ດູແລລະບົບສູງສຸດ',
    'province_admin' => 'ຜູ້ດູແລລະດັບແຂວງ',
    'admin' => 'ຜູ້ດູແລວັດ',
    'user' => 'ຜູ້ໃຊ້ທົ່ວໄປ'
];

// เพิ่ม CSS พิเศษ
echo '<link rel="stylesheet" href="' . $base_url . 'assets/css/monk-style.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">';
?>

<div class="page-container bg-temple-pattern">
    <!-- Header Section -->
    <div class="header-section mb-8 animate__animated animate__fadeInDown">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div class="flex items-center">
                <div class="category-icon bg-<?= $role_color ?>-600">
                    <?= $role_icons[$user['role']] ?? '<i class="fas fa-user-edit"></i>' ?>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <?= $page_title ?>: 
                    <span class="text-<?= $role_color ?>-600"><?= htmlspecialchars($user['name']) ?></span>
                </h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= $base_url ?>users/view.php?id=<?= $user_id ?>" class="btn btn-back">
                    <i class="fas fa-eye"></i>
                    <span>ເບິ່ງລາຍລະອຽດ</span>
                </a>
                <a href="<?= $base_url ?>users/" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <span>ກັບຄືນ</span>
                </a>
            </div>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md shadow-md animate__animated animate__fadeIn" role="alert">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="font-medium">ກະລຸນາແກ້ໄຂຂໍ້ຜິດພາດຕໍ່ໄປນີ້:</h3>
                <ul class="mt-2 list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ແບບຟອມແກ້ໄຂ - info-card style -->
    <div class="info-card animate__animated animate__fadeIn">
        <div class="info-card-header">
            <h2 class="info-card-title">
                <div class="icon-circle <?= $role_color ?>">
                    <i class="fas fa-user-edit"></i>
                </div>
                ຂໍ້ມູນຜູ້ໃຊ້
            </h2>
        </div>
        <div class="info-card-body">
            <form method="POST" action="" id="userForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ຂໍ້ມູນພື້ນຖານ -->
                    <div>
                        <div class="mb-4">
                            <label for="username" class="info-label">ຊື່ຜູ້ໃຊ້ <span class="text-red-600">*</span></label>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                                class="w-full px-4 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="name" class="info-label">ຊື່-ນາມສະກຸນ <span class="text-red-600">*</span></label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                                class="w-full px-4 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="email" class="info-label">ອີເມວ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                    class="w-full pl-10 pr-4 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="phone" class="info-label">ເບີໂທລະສັບ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                    class="w-full pl-10 pr-4 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all">
                            </div>
                        </div>
                    </div>

                    <!-- ຂໍ້ມູນສິດການໃຊ້ງານ -->
                    <div>
                        <?php if (($is_superadmin && $user_id != $current_user_id) || ($is_admin && $user_id != $current_user_id && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin')): ?>
                        <div class="mb-4">
                            <label for="role" class="info-label">ບົດບາດ <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <select id="role" name="role" 
                                    class="w-full pl-10 pr-10 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent appearance-none transition-all" required>
                                    <?php if ($is_superadmin): ?>
                                        <option value="superadmin" <?= $user['role'] === 'superadmin' ? 'selected' : '' ?>>ຜູ້ດູແລລະບົບສູງສຸດ (Superadmin)</option>
                                        <option value="province_admin" <?= $user['role'] === 'province_admin' ? 'selected' : '' ?>>ຜູ້ດູແລລະດັບແຂວງ (Province Admin)</option>
                                    <?php endif; ?>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ (Temple Admin)</option>
                                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>ຜູ້ໃຊ້ທົ່ວໄປ (User)</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="status" class="info-label">ສະຖານະ <span class="text-red-600">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                    <i class="fas fa-toggle-on"></i>
                                </div>
                                <select id="status" name="status" 
                                    class="w-full pl-10 pr-10 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent appearance-none transition-all" required>
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>ໃຊ້ງານ</option>
                                    <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>ປິດການໃຊ້ງານ</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- สถานะปัจจุบัน (แสดงเฉพาะกรณีไม่มีสิทธิ์แก้ไข) -->
                        <?php if (!($is_superadmin && $user_id != $current_user_id) && !($is_admin && $user_id != $current_user_id && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin')): ?>
                        <div class="mb-4">
                            <span class="info-label">ບົດບາດປັດຈຸບັນ</span>
                            <div class="p-2.5 bg-gray-50 rounded-md border border-gray-200">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 text-white bg-<?= $role_color ?>-500">
                                        <?= $role_icons[$user['role']] ?? '<i class="fas fa-user"></i>' ?>
                                    </div>
                                    <span class="font-medium"><?= $role_labels[$user['role']] ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <span class="info-label">ສະຖານະປັດຈຸບັນ</span>
                            <div class="p-2.5 bg-gray-50 rounded-md border border-gray-200">
                                <?php
                                switch ($user['status']) {
                                    case 'active':
                                        echo '<span class="status-badge status-active"><i class="fas fa-circle text-xs mr-1"></i>' . $status_labels[$user['status']] . '</span>';
                                        break;
                                    case 'pending':
                                        echo '<span class="status-badge bg-yellow-100 text-yellow-800 border border-yellow-200"><i class="fas fa-clock text-xs mr-1"></i>' . $status_labels[$user['status']] . '</span>';
                                        break;
                                    case 'inactive':
                                        echo '<span class="status-badge bg-red-100 text-red-800 border border-red-200"><i class="fas fa-ban text-xs mr-1"></i>' . $status_labels[$user['status']] . '</span>';
                                        break;
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- เปลี่ยนรหัสผ่าน -->
                        <div class="p-4 bg-amber-50 rounded-lg border border-amber-200 mb-4">
                            <div class="flex items-center mb-3">
                                <input type="checkbox" id="change_password" name="change_password" value="1" 
                                    class="w-5 h-5 text-amber-600 rounded border-amber-300 focus:ring-amber-500 focus:ring-2">
                                <label for="change_password" class="ml-2 text-sm font-medium text-gray-700 flex items-center">
                                    <i class="fas fa-key text-amber-500 mr-2"></i> ປ່ຽນລະຫັດຜ່ານ
                                </label>
                            </div>
                            
                            <div id="password_fields" class="grid grid-cols-1 gap-4 hidden pt-2 border-t border-amber-200">
                                <div>
                                    <label for="password" class="info-label">ລະຫັດຜ່ານໃໝ່ <span class="text-red-600">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <input type="password" id="password" name="password" 
                                            class="w-full pl-10 pr-10 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all">
                                        <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 password-toggle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="info-label">ຢືນຢັນລະຫັດຜ່ານໃໝ່ <span class="text-red-600">*</span></label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                            class="w-full pl-10 pr-10 py-2.5 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all">
                                        <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 password-toggle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ສ່ວນເລືອກແຂວງ (ສຳລັບ province_admin) -->
                <?php if ($is_superadmin && (isset($role) && $role === 'province_admin' || $user['role'] === 'province_admin')): ?>
                <div id="province-section" class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200 animate__animated animate__fadeInUp">
                    <h3 class="font-medium text-blue-800 mb-3 flex items-center">
                        <i class="fas fa-globe-asia text-blue-600 mr-2"></i>
                        ແຂວງທີ່ຮັບຜິດຊອບ <span class="text-red-600 ml-1">*</span>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($provinces as $province): ?>
                            <div class="flex items-center p-2 hover:bg-blue-100 rounded-md transition-colors">
                                <input type="checkbox" 
                                       id="province-<?= $province['province_id'] ?>" 
                                       name="provinces[]" 
                                       value="<?= $province['province_id'] ?>" 
                                       <?= in_array($province['province_id'], $assigned_provinces) ? 'checked' : '' ?>
                                       class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500">
                                <label for="province-<?= $province['province_id'] ?>" class="ml-2 text-sm text-gray-700 flex items-center w-full cursor-pointer">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2 text-blue-600 text-xs font-medium">
                                        <?= substr($province['province_code'], 0, 2) ?>
                                    </div>
                                    <span><?= htmlspecialchars($province['province_name']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($provinces)): ?>
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-info-circle text-blue-300 text-xl mb-2"></i>
                            <p>ບໍ່ພົບຂໍ້ມູນແຂວງ</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- ສ່ວນເລືອກວັດ (ສຳລັບ admin ຫຼື user) -->
                <?php if (($is_superadmin || $is_province_admin || ($is_admin && $user_id != $current_user_id)) && 
                           ($user['role'] === 'admin' || $user['role'] === 'user' || 
                            (isset($role) && ($role === 'admin' || $role === 'user')))): ?>
                <div id="temple-section" class="mt-6 p-4 bg-amber-50 rounded-lg border border-amber-200 animate__animated animate__fadeInUp">
                    <h3 class="font-medium text-amber-800 mb-3 flex items-center">
                        <i class="fas fa-gopuram text-amber-600 mr-2"></i>
                        ເລືອກວັດ <span class="text-red-600 ml-1">*</span>
                    </h3>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-amber-500">
                            <i class="fas fa-search"></i>
                        </div>
                        <select id="temple_id" name="temple_id" 
                            class="w-full pl-10 pr-10 py-3 border border-amber-200 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent transition-all">
                            <option value="">-- ກະລຸນາເລືອກວັດ --</option>
                            <?php foreach ($temples as $temple): ?>
                                <option value="<?= $temple['id'] ?>" <?= $user['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($temple['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <?php if (empty($temples)): ?>
                        <div class="text-center py-2 text-gray-500 text-sm mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            ບໍ່ພົບຂໍ້ມູນວັດທີ່ສາມາດເລືອກໄດ້
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ປຸ່ມການດຳເນີນການ -->
                <div class="mt-8 flex flex-wrap gap-3 animate__animated animate__fadeInUp">
                    <button type="submit" class="btn btn-edit group">
                        <i class="fas fa-save mr-1 group-hover:animate-bounce"></i>
                        <span>ບັນທຶກການປ່ຽນແປງ</span>
                    </button>
                    <a href="<?= $base_url ?>users/view.php?id=<?= $user_id ?>" class="btn btn-back">
                        <i class="fas fa-times mr-1"></i>
                        <span>ຍົກເລີກ</span>
                    </a>

                    <!-- แสดงข้อความช่วยเหลือ -->
                    <div class="ml-auto flex items-center text-gray-500 text-sm">
                        <i class="fas fa-info-circle mr-1 text-amber-500"></i>
                        <span>ຊ່ອງທີ່ມີ <span class="text-red-600">*</span> ແມ່ນຈຳເປັນຕ້ອງປ້ອນ</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- แสดงข้อมูล History การเปลี่ยนแปลง (ถ้ามี) -->
    <?php if ($is_superadmin && isset($change_history) && !empty($change_history)): ?>
    <div class="info-card mt-6 animate__animated animate__fadeIn animate__delay-1s">
        <div class="info-card-header">
            <h2 class="info-card-title">
                <div class="icon-circle blue">
                    <i class="fas fa-history"></i>
                </div>
                ປະຫວັດການປ່ຽນແປງ
            </h2>
        </div>
        <div class="info-card-body overflow-x-auto">
            <table class="min-w-full divide-y divide-amber-100">
                <thead>
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">ວັນທີ</th>
                        <th class="px-6 py-3">ຜູ້ດຳເນີນການ</th>
                        <th class="px-6 py-3">ການປ່ຽນແປງ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-amber-100">
                    <tr class="text-gray-500 text-center">
                        <td colspan="3" class="py-4">ບໍ່ພົບຂໍ້ມູນ</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // script ปัจจุบันจากไฟล์เดิม
    const roleSelect = document.getElementById('role');
    const templeSection = document.getElementById('temple-section');
    const provinceSection = document.getElementById('province-section');
    const changePasswordCheckbox = document.getElementById('change_password');
    const passwordFields = document.getElementById('password_fields');
    
    // แสดง/ซ่อนช่องรหัสผ่าน
    if (changePasswordCheckbox) {
        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFields.classList.remove('hidden');
                passwordFields.classList.add('animate__animated', 'animate__fadeIn');
                document.getElementById('password').required = true;
                document.getElementById('confirm_password').required = true;
            } else {
                passwordFields.classList.add('animate__fadeOut');
                setTimeout(() => {
                    passwordFields.classList.add('hidden');
                    passwordFields.classList.remove('animate__fadeOut');
                    document.getElementById('password').required = false;
                    document.getElementById('confirm_password').required = false;
                }, 300);
            }
        });
    }
    
    // ฟังก์ชันอัพเดตฟอร์มตามบทบาท
    function updateFormByRole() {
        if (!roleSelect) return;
        
        const selectedRole = roleSelect.value;
        
        // จัดการส่วนเลือกวัด
        if (templeSection) {
            if (selectedRole === 'admin' || selectedRole === 'user') {
                if (templeSection.classList.contains('hidden')) {
                    templeSection.classList.remove('hidden');
                    templeSection.classList.add('animate__animated', 'animate__fadeIn');
                }
                document.getElementById('temple_id').required = true;
            } else {
                templeSection.classList.add('animate__fadeOut');
                setTimeout(() => {
                    templeSection.classList.add('hidden');
                    templeSection.classList.remove('animate__fadeOut');
                    document.getElementById('temple_id').required = false;
                }, 300);
            }
        }
        
        // จัดการส่วนเลือกแขวง
        if (provinceSection) {
            if (selectedRole === 'province_admin') {
                if (provinceSection.classList.contains('hidden')) {
                    provinceSection.classList.remove('hidden');
                    provinceSection.classList.add('animate__animated', 'animate__fadeIn');
                }
            } else {
                provinceSection.classList.add('animate__fadeOut');
                setTimeout(() => {
                    provinceSection.classList.add('hidden');
                    provinceSection.classList.remove('animate__fadeOut');
                }, 300);
            }
        }
    }
    
    // เพิ่ม event listener สำหรับการเปลี่ยนบทบาท
    if (roleSelect) {
        roleSelect.addEventListener('change', updateFormByRole);
    }
    
    // ตรวจสอบรหัสผ่าน
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function(event) {
            if (changePasswordCheckbox && changePasswordCheckbox.checked) {
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('confirm_password');
                
                if (passwordInput.value !== confirmInput.value) {
                    event.preventDefault();
                    showAlert('ລະຫັດຜ່ານບໍ່ກົງກັນ', 'error');
                    confirmInput.focus();
                    return;
                }
                
                if (passwordInput.value.length < 6) {
                    event.preventDefault();
                    showAlert('ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ', 'error');
                    passwordInput.focus();
                    return;
                }
            }
        });
    }
    
    // สลับการแสดงรหัสผ่าน
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // แสดงการแจ้งเตือนแบบสวยงาม
    function showAlert(message, type = 'info') {
        // สร้าง element สำหรับแสดงการแจ้งเตือน
        const alertEl = document.createElement('div');
        alertEl.className = `fixed top-4 right-4 px-4 py-3 rounded-lg shadow-lg z-50 animate__animated animate__fadeInDown max-w-md`;
        
        // กำหนดสีตามประเภท
        switch(type) {
            case 'error':
                alertEl.classList.add('bg-red-100', 'border-l-4', 'border-red-500', 'text-red-700');
                break;
            case 'success':
                alertEl.classList.add('bg-green-100', 'border-l-4', 'border-green-500', 'text-green-700');
                break;
            default:
                alertEl.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500', 'text-blue-700');
        }
        
        // สร้างเนื้อหา
        alertEl.innerHTML = `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'} text-${type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue'}-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium">${message}</p>
                </div>
                <button class="ml-auto text-${type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue'}-500 hover:text-${type === 'error' ? 'red' : type === 'success' ? 'green' : 'blue'}-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // เพิ่มลงในหน้าเว็บ
        document.body.appendChild(alertEl);
        
        // เพิ่ม event listener สำหรับปุ่มปิด
        alertEl.querySelector('button').addEventListener('click', () => {
            alertEl.classList.replace('animate__fadeInDown', 'animate__fadeOutUp');
            setTimeout(() => alertEl.remove(), 500);
        });
        
        // ลบการแจ้งเตือนหลังจาก 5 วินาที
        setTimeout(() => {
            if (document.body.contains(alertEl)) {
                alertEl.classList.replace('animate__fadeInDown', 'animate__fadeOutUp');
                setTimeout(() => alertEl.remove(), 500);
            }
        }, 5000);
    }
    
    // เรียกใช้ฟังก์ชันเมื่อโหลดหน้าเสร็จ
    updateFormByRole();
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>