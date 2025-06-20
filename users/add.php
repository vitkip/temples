<?php
// filepath: c:\xampp\htdocs\temples\users\add.php
ob_start();

$page_title = 'ເພີ່ມຜູ້ໃຊ້ໃໝ່';
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

// ອະນຸຍາດໃຫ້ສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
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
    $province_stmt->execute([$_SESSION['user']['id']]);
    $temples = $province_stmt->fetchAll();
} elseif ($is_admin) {
    // Admin ສາມາດເລືອກສະເພາະວັດຂອງຕົນເອງ
    $temple_stmt = $pdo->prepare("SELECT id, name, province_id FROM temples WHERE id = ?");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ດຶງຂໍ້ມູນແຂວງ (ສຳລັບ province_admin)
$provinces = [];
if ($is_superadmin) {
    $province_stmt = $pdo->query("SELECT * FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll();
} elseif ($is_province_admin) {
    $province_stmt = $pdo->prepare("
        SELECT p.*
        FROM provinces p
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $provinces = $province_stmt->fetchAll();
}

// ເມື່ອສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ຮັບຂໍ້ມູນຈາກຟອມ
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $temple_id = isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : null;
    $province_id = isset($_POST['province_id']) ? (int)$_POST['province_id'] : null;
    $status = $_POST['status'];
    
    $errors = [];
    
    // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    if (empty($username)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    }
    
    if (empty($password)) {
        $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານ";
    } elseif ($password !== $confirm_password) {
        $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
    } elseif (strlen($password) < 6) {
        $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
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
    
    // ກວດສອບແຂວງ (ສຳລັບ province_admin)
    if ($role === 'province_admin' && empty($province_id)) {
        $errors[] = "ກະລຸນາເລືອກແຂວງ";
    }
    
    // ກວດສອບສິດໃນການສ້າງບົດບາດ
    if ($role === 'superadmin' && !$is_superadmin) {
        $errors[] = "ທ່ານບໍ່ສາມາດສ້າງຜູ້ໃຊ້ທີ່ມີບົດບາດສູງສຸດໄດ້";
    }
    
    if ($role === 'province_admin' && !$is_superadmin) {
        $errors[] = "ທ່ານບໍ່ສາມາດສ້າງຜູ້ດູແລລະດັບແຂວງໄດ້";
    }
    
    // ກວດສອບວ່າຊື່ຜູ້ໃຊ້ມີແລ້ວຫຼືບໍ່
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->execute([$username]);
    if ($check_stmt->rowCount() > 0) {
        $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ບັນທຶກຂໍ້ມູນ
    if (empty($errors)) {
        try {
            // ເຂົ້າລະຫັດຜ່ານ
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // ເລີ່ມ transaction
            $pdo->beginTransaction();
            
            // ບັນທຶກຂໍ້ມູນຜູ້ໃຊ້ໃໝ່
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, name, email, phone, role, temple_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            // ກຳນົດ temple_id ຕາມບົດບາດ
            $assigned_temple = null;
            if ($role === 'admin' || $role === 'user') {
                $assigned_temple = $temple_id;
            }
            
            $stmt->execute([
                $username,
                $hashed_password,
                $name,
                $email,
                $phone,
                $role,
                $assigned_temple,
                $status
            ]);
            
            $new_user_id = $pdo->lastInsertId();
            
            // ຖ້າເປັນ province_admin, ບັນທຶກສິດການເຂົ້າເຖິງແຂວງ
            if ($role === 'province_admin' && !empty($province_id)) {
                $province_access_stmt = $pdo->prepare("
                    INSERT INTO user_province_access (user_id, province_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                $province_access_stmt->execute([
                    $new_user_id,
                    $province_id,
                    $_SESSION['user']['id']
                ]);
            }
            
            // ຢືນຢັນ transaction
            $pdo->commit();
            
            $_SESSION['success'] = "ເພີ່ມຜູ້ໃຊ້ສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'users/view.php?id=' . $new_user_id);
            exit;
            
        } catch (PDOException $e) {
            // ຍົກເລີກ transaction ກໍລະນີເກີດຂໍ້ຜິດພາດ
            $pdo->rollBack();
            
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}

// HTML ຫົວຂໍ້
$title = "ເພີ່ມຜູ້ໃຊ້ໃໝ່";
require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?= $title ?></h1>
        <a href="<?= $base_url ?>users/" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-1"></i> ກັບຄືນ
        </a>
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
                    <input type="text" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ຊື່-ນາມສະກຸນ <span class="text-red-600">*</span></label>
                    <input type="text" id="name" name="name" value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">ລະຫັດຜ່ານ <span class="text-red-600">*</span></label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <p class="text-sm text-gray-500 mt-1">ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">ຢືນຢັນລະຫັດຜ່ານ <span class="text-red-600">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">ອີເມວ</label>
                    <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທລະສັບ</label>
                    <input type="text" id="phone" name="phone" value="<?= isset($phone) ? htmlspecialchars($phone) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">ບົດບາດ <span class="text-red-600">*</span></label>
                    <select id="role" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <?php if ($is_superadmin): ?>
                            <option value="superadmin" <?= isset($role) && $role === 'superadmin' ? 'selected' : '' ?>>ຜູ້ດູແລລະບົບສູງສຸດ (Superadmin)</option>
                            <option value="province_admin" <?= isset($role) && $role === 'province_admin' ? 'selected' : '' ?>>ຜູ້ດູແລລະດັບແຂວງ (Province Admin)</option>
                        <?php endif; ?>
                        <option value="admin" <?= isset($role) && $role === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ (Temple Admin)</option>
                        <option value="user" <?= isset($role) && $role === 'user' ? 'selected' : '' ?>>ຜູ້ໃຊ້ທົ່ວໄປ (User)</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ <span class="text-red-600">*</span></label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="active" <?= isset($status) && $status === 'active' ? 'selected' : '' ?>>ໃຊ້ງານ</option>
                        <option value="pending" <?= isset($status) && $status === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                        <option value="inactive" <?= isset($status) && $status === 'inactive' ? 'selected' : '' ?>>ປິດການໃຊ້ງານ</option>
                    </select>
                </div>
                
                <!-- ສ່ວນເລືອກແຂວງ (ສະແດງເມື່ອບົດບາດເປັນ province_admin) -->
                <div id="province-section" class="<?= isset($role) && $role === 'province_admin' ? '' : 'hidden' ?>">
                    <label for="province_id" class="block text-sm font-medium text-gray-700 mb-1">ແຂວງ <span class="text-red-600">*</span></label>
                    <select id="province_id" name="province_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- ກະລຸນາເລືອກແຂວງ --</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?= $province['province_id'] ?>" <?= isset($province_id) && $province_id == $province['province_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($province['province_name']) ?> (<?= htmlspecialchars($province['province_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- ສ່ວນເລືອກວັດ (ສະແດງເມື່ອບົດບາດເປັນ admin ຫຼື user) -->
                <div id="temple-section" class="<?= !isset($role) || $role === 'admin' || $role === 'user' ? '' : 'hidden' ?>">
                    <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ <span class="text-red-600">*</span></label>
                    <select id="temple_id" name="temple_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- ກະລຸນາເລືອກວັດ --</option>
                        <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= isset($temple_id) && $temple_id == $temple['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mt-8">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-1"></i> ບັນທຶກ
                </button>
                <a href="<?= $base_url ?>users/" class="ml-2 bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
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
    const templeSelect = document.getElementById('temple_id');
    const provinceSelect = document.getElementById('province_id');
    
    // ຟັງຊັນອັບເດດຟອມຕາມບົດບາດ
    function updateFormByRole() {
        const selectedRole = roleSelect.value;
        
        // ຈັດການກັບສ່ວນເລືອກວັດ
        if (selectedRole === 'admin' || selectedRole === 'user') {
            templeSection.classList.remove('hidden');
            templeSelect.required = true;
        } else {
            templeSection.classList.add('hidden');
            templeSelect.required = false;
        }
        
        // ຈັດການກັບສ່ວນເລືອກແຂວງ
        if (selectedRole === 'province_admin') {
            provinceSection.classList.remove('hidden');
            provinceSelect.required = true;
        } else {
            provinceSection.classList.add('hidden');
            provinceSelect.required = false;
        }
    }
    
    // ເພີ່ມ event listener ສຳລັບການປ່ຽນບົດບາດ
    roleSelect.addEventListener('change', updateFormByRole);
    
    // ກວດສອບ password matching
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const userForm = document.getElementById('userForm');
    
    userForm.addEventListener('submit', function(event) {
        if (passwordInput.value !== confirmInput.value) {
            event.preventDefault();
            alert('ລະຫັດຜ່ານບໍ່ກົງກັນ');
            confirmInput.focus();
        }
        
        if (passwordInput.value.length < 6) {
            event.preventDefault();
            alert('ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ');
            passwordInput.focus();
        }
    });
    
    // ເອີ້ນໃຊ້ຟັງຊັນເມື່ອໂຫຼດໜ້າ
    updateFormByRole();
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>