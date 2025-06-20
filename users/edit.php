<?php
// filepath: c:\xampp\htdocs\temples\users\edit.php
ob_start();

$page_title = 'ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້';
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
    
    // ຮັບຂໍ້ມູນແຂວງ (ສຳລັບ province_admin)
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
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?= $page_title ?></h1>
        <div>
            <a href="<?= $base_url ?>users/view.php?id=<?= $user_id ?>" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition mr-2">
                <i class="fas fa-eye mr-1"></i> ເບິ່ງລາຍລະອຽດ
            </a>
            <a href="<?= $base_url ?>users/" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-1"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">ເກີດຂໍ້ຜິດພາດ!</strong>
            <ul class="mt-2 list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="" id="userForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">ຊື່ຜູ້ໃຊ້ <span class="text-red-600">*</span></label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ຊື່-ນາມສະກຸນ <span class="text-red-600">*</span></label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">ອີເມວ</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທລະສັບ</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="md:col-span-2">
                    <div class="flex items-center mb-2">
                        <input type="checkbox" id="change_password" name="change_password" value="1" class="mr-2">
                        <label for="change_password" class="text-sm font-medium text-gray-700">ປ່ຽນລະຫັດຜ່ານ</label>
                    </div>
                </div>
                
                <div id="password_fields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 hidden">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">ລະຫັດຜ່ານໃໝ່ <span class="text-red-600">*</span></label>
                        <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-sm text-gray-500 mt-1">ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">ຢືນຢັນລະຫັດຜ່ານໃໝ່ <span class="text-red-600">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <?php if (($is_superadmin && $user_id != $current_user_id) || ($is_admin && $user_id != $current_user_id && $user['role'] !== 'superadmin' && $user['role'] !== 'province_admin')): ?>
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">ບົດບາດ <span class="text-red-600">*</span></label>
                    <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if ($is_superadmin): ?>
                            <option value="superadmin" <?= $user['role'] === 'superadmin' ? 'selected' : '' ?>>ຜູ້ດູແລລະບົບສູງສຸດ (Superadmin)</option>
                            <option value="province_admin" <?= $user['role'] === 'province_admin' ? 'selected' : '' ?>>ຜູ້ດູແລລະດັບແຂວງ (Province Admin)</option>
                        <?php endif; ?>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ (Temple Admin)</option>
                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>ຜູ້ໃຊ້ທົ່ວໄປ (User)</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ <span class="text-red-600">*</span></label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>ໃຊ້ງານ</option>
                        <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>ປິດການໃຊ້ງານ</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- ສ່ວນເລືອກແຂວງ (ສຳລັບ province_admin) -->
                <?php if ($is_superadmin && (isset($role) && $role === 'province_admin' || $user['role'] === 'province_admin')): ?>
                <div id="province-section" class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວງທີ່ຮັບຜິດຊອບ <span class="text-red-600">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <?php foreach ($provinces as $province): ?>
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="province-<?= $province['province_id'] ?>" 
                                       name="provinces[]" 
                                       value="<?= $province['province_id'] ?>" 
                                       <?= in_array($province['province_id'], $assigned_provinces) ? 'checked' : '' ?>
                                       class="mr-2">
                                <label for="province-<?= $province['province_id'] ?>" class="text-sm text-gray-700">
                                    <?= htmlspecialchars($province['province_name']) ?> (<?= htmlspecialchars($province['province_code']) ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ສ່ວນເລືອກວັດ (ສຳລັບ admin ຫຼື user) -->
                <?php if (($is_superadmin || $is_province_admin || ($is_admin && $user_id != $current_user_id)) && 
                           ($user['role'] === 'admin' || $user['role'] === 'user' || 
                            (isset($role) && ($role === 'admin' || $role === 'user')))): ?>
                <div id="temple-section" class="md:col-span-2">
                    <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ <span class="text-red-600">*</span></label>
                    <select id="temple_id" name="temple_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- ກະລຸນາເລືອກວັດ --</option>
                        <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $user['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-8">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-1"></i> ບັນທຶກການປ່ຽນແປງ
                </button>
                <a href="<?= $base_url ?>users/view.php?id=<?= $user_id ?>" class="ml-2 bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
                    ຍົກເລີກ
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const templeSection = document.getElementById('temple-section');
    const provinceSection = document.getElementById('province-section');
    const changePasswordCheckbox = document.getElementById('change_password');
    const passwordFields = document.getElementById('password_fields');
    
    // ແສດງຫຼືຊ່ອນຊ່ອງປ້ອນລະຫັດຜ່ານ
    if (changePasswordCheckbox) {
        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFields.classList.remove('hidden');
                document.getElementById('password').required = true;
                document.getElementById('confirm_password').required = true;
            } else {
                passwordFields.classList.add('hidden');
                document.getElementById('password').required = false;
                document.getElementById('confirm_password').required = false;
            }
        });
    }
    
    // ຟັງຊັນອັບເດດຟອມຕາມບົດບາດ
    function updateFormByRole() {
        if (!roleSelect) return;
        
        const selectedRole = roleSelect.value;
        
        // ຈັດການກັບສ່ວນເລືອກວັດ
        if (templeSection) {
            if (selectedRole === 'admin' || selectedRole === 'user') {
                templeSection.classList.remove('hidden');
                document.getElementById('temple_id').required = true;
            } else {
                templeSection.classList.add('hidden');
                document.getElementById('temple_id').required = false;
            }
        }
        
        // ຈັດການກັບສ່ວນເລືອກແຂວງ
        if (provinceSection) {
            if (selectedRole === 'province_admin') {
                provinceSection.classList.remove('hidden');
            } else {
                provinceSection.classList.add('hidden');
            }
        }
    }
    
    // ເພີ່ມ event listener ສຳລັບການປ່ຽນບົດບາດ
    if (roleSelect) {
        roleSelect.addEventListener('change', updateFormByRole);
    }
    
    // ກວດສອບລະຫັດຜ່ານ
    const userForm = document.getElementById('userForm');
    
    if (userForm) {
        userForm.addEventListener('submit', function(event) {
            if (changePasswordCheckbox && changePasswordCheckbox.checked) {
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('confirm_password');
                
                if (passwordInput.value !== confirmInput.value) {
                    event.preventDefault();
                    alert('ລະຫັດຜ່ານບໍ່ກົງກັນ');
                    confirmInput.focus();
                    return;
                }
                
                if (passwordInput.value.length < 6) {
                    event.preventDefault();
                    alert('ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ');
                    passwordInput.focus();
                    return;
                }
            }
        });
    }
    
    // ເອີ້ນໃຊ້ຟັງຊັນເມື່ອໂຫຼດໜ້າ
    updateFormByRole();
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>