<?php
// filepath: c:\xampp\htdocs\temples\users\edit.php
ob_start();

$page_title = 'ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂຂໍ້ມູນ - superadmin ສາມາດແກ້ໄຂທຸກຄົນ, admin ແກ້ໄຂໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນຜູ້ໃຊ້
$stmt = $pdo->prepare("
    SELECT u.*, t.name as temple_name 
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

// ກວດສອບສິດໃນການແກ້ໄຂຂໍ້ມູນ
$can_edit = $is_superadmin || 
           ($is_admin && $_SESSION['user']['temple_id'] == $user['temple_id']) || 
           $_SESSION['user']['id'] == $user_id;

// ກວດສອບວ່າ admin ບໍ່ສາມາດແກ້ໄຂ superadmin ໄດ້
if ($is_admin && $user['role'] === 'superadmin') {
    $can_edit = false;
}

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ດຶງຂໍ້ມູນວັດສໍາລັບ dropdown (ສະເພາະ superadmin)
$temples = [];
if ($is_superadmin) {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'username' => $user['username'],
    'name' => $user['name'],
    'role' => $user['role'],
    'temple_id' => $user['temple_id'],
    'password' => '',
    'confirm_password' => ''
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'users/edit.php?id=' . $user_id);
        exit;
    }
    
    // ກວດສອບຂໍ້ມູນທີ່ສົ່ງມາ
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'role' => $_POST['role'] ?? $user['role'],
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : $user['temple_id'],
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // ກວດສອບ username
    if (empty($form_data['username'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    } else if ($form_data['username'] !== $user['username']) {
        // ກວດສອບວ່າ username ຊໍ້າກັນຫຼືບໍ່
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$form_data['username'], $user_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
        }
    }
    
    // ກວດສອບຊື່
    if (empty($form_data['name'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່";
    }
    
    // ກວດສອບລະຫັດຜ່ານ (ຖ້າມີການປ່ຽນແປງ)
    if (!empty($form_data['password'])) {
        if (strlen($form_data['password']) < 6) {
            $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
        } else if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
        }
    }
    
    // ກວດສອບບົດບາດ (role)
    if ($is_superadmin && empty($form_data['role'])) {
        $errors[] = "ກະລຸນາເລືອກບົດບາດ";
    }
    
    // ກວດສອບວັດ (ສໍາລັບ admin ແລະ user)
    if ($is_superadmin && in_array($form_data['role'], ['admin', 'user']) && empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ
    if (empty($errors)) {
        try {
            // ກຽມຄໍາສັ່ງ SQL ສໍາລັດການອັບເດດ
            $sql_fields = [
                "username = ?",
                "name = ?"
            ];
            $sql_params = [
                $form_data['username'],
                $form_data['name']
            ];
            
            // ອັບເດດລະຫັດຜ່ານຖ້າມີການປ່ຽນແປງ
            if (!empty($form_data['password'])) {
                $sql_fields[] = "password = ?";
                $sql_params[] = password_hash($form_data['password'], PASSWORD_DEFAULT);
            }
            
            // ອັບເດຕບົດບາດ (role) ແລະ temple_id ຖ້າເປັນ superadmin
            if ($is_superadmin) {
                $sql_fields[] = "role = ?";
                $sql_params[] = $form_data['role'];
                
                if (in_array($form_data['role'], ['admin', 'user'])) {
                    $sql_fields[] = "temple_id = ?";
                    $sql_params[] = $form_data['temple_id'];
                } else if ($form_data['role'] === 'superadmin') {
                    $sql_fields[] = "temple_id = NULL";
                }
            }
            
            // ເພີ່ມ user_id ໃນພາຣາມິເຕີສຸດທ້າຍ
            $sql_params[] = $user_id;
            
            // ດໍາເນີນການອັບເດຕ
            $sql = "UPDATE users SET " . implode(", ", $sql_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($sql_params);
            
            // ຖ້າອັບເດຕສໍາເລັດ
            $_SESSION['success'] = "ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້ສໍາເລັດແລ້ວ";
            
            // ຖ້າຜູ້ໃຊ້ແກ້ໄຂຂໍ້ມູນຕົນເອງ ໃຫ້ອັບເດຕ session
            if ($_SESSION['user']['id'] == $user_id) {
                $_SESSION['user']['username'] = $form_data['username'];
                $_SESSION['user']['name'] = $form_data['name'];
                if ($is_superadmin) {
                    $_SESSION['user']['role'] = $form_data['role'];
                    if (in_array($form_data['role'], ['admin', 'user'])) {
                        $_SESSION['user']['temple_id'] = $form_data['temple_id'];
                    } else {
                        $_SESSION['user']['temple_id'] = null;
                    }
                }
            }
            
            header('Location: ' . $base_url . 'users/');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້</h1>
            <p class="text-sm text-gray-600">ປັບປຸງຂໍ້ມູນຜູ້ໃຊ້ໃນລະບົບ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>users/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <!-- ສະແດງຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
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
    
    <!-- ຟອມແກ້ໄຂຂໍ້ມູນຜູ້ໃຊ້ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>users/edit.php?id=<?= $user_id ?>" method="POST" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">ຊື່ຜູ້ໃຊ້ <span class="text-red-600">*</span></label>
                    <input type="text" name="username" id="username" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['username']) ?>" required>
                </div>
                
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">ຊື່-ນາມສະກຸນ <span class="text-red-600">*</span></label>
                    <input type="text" name="name" id="name" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['name']) ?>" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">ລະຫັດຜ່ານໃໝ່</label>
                    <input type="password" name="password" id="password" class="form-input rounded-md w-full" minlength="6">
                    <p class="text-sm text-gray-500 mt-1">ຖ້າບໍ່ຕ້ອງການປ່ຽນລະຫັດຜ່ານ ໃຫ້ປະຫວ່າງໄວ້</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">ຢືນຢັນລະຫັດຜ່ານໃໝ່</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input rounded-md w-full" minlength="6">
                </div>
                
                <?php if ($is_superadmin): ?>
                <!-- ບົດບາດ (ສະເພາະ superadmin) -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">ບົດບາດ <span class="text-red-600">*</span></label>
                    <select name="role" id="role" class="form-select rounded-md w-full" required>
                        <option value="superadmin" <?= $form_data['role'] === 'superadmin' ? 'selected' : '' ?>>ຜູ້ດູແລລະບົບສູງສຸດ</option>
                        <option value="admin" <?= $form_data['role'] === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ</option>
                        <option value="user" <?= $form_data['role'] === 'user' ? 'selected' : '' ?>>ຜູ້ໃຊ້ທົ່ວໄປ</option>
                    </select>
                </div>
                
                <!-- ວັດ (ສະເພາະ superadmin) -->
                <div id="temple-container" class="<?= in_array($form_data['role'], ['admin', 'user']) ? '' : 'hidden' ?>">
                    <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-2">ວັດ <span class="text-red-600">*</span></label>
                    <select name="temple_id" id="temple_id" class="form-select rounded-md w-full" <?= in_array($form_data['role'], ['admin', 'user']) ? 'required' : '' ?>>
                        <option value="">-- ເລືອກວັດ --</option>
                        <?php foreach ($temples as $temple): ?>
                        <option value="<?= $temple['id'] ?>" <?= $form_data['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($temple['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <!-- ສະແດງຂໍ້ມູນບົດບາດແລະວັດ (ສໍາລັບ admin ແລະ user) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ບົດບາດ</label>
                    <div class="py-2 px-3 bg-gray-50 rounded-md">
                        <?php
                        $role_labels = [
                            'superadmin' => 'ຜູ້ດູແລລະບົບສູງສຸດ',
                            'admin' => 'ຜູ້ດູແລວັດ',
                            'user' => 'ຜູ້ໃຊ້ທົ່ວໄປ'
                        ];
                        echo $role_labels[$user['role']] ?? $user['role'];
                        ?>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ວັດ</label>
                    <div class="py-2 px-3 bg-gray-50 rounded-md">
                        <?= htmlspecialchars($user['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                <a href="<?= $base_url ?>users/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($is_superadmin): ?>
<!-- JavaScript ສໍາລັບຈັດການຟອມ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelector = document.getElementById('role');
    const templeContainer = document.getElementById('temple-container');
    const templeSelector = document.getElementById('temple_id');
    
    // ຟັງການປ່ຽນແປງຂອງບົດບາດ
    roleSelector.addEventListener('change', function() {
        if (this.value === 'admin' || this.value === 'user') {
            templeContainer.classList.remove('hidden');
            templeSelector.required = true;
        } else {
            templeContainer.classList.add('hidden');
            templeSelector.required = false;
            templeSelector.value = '';
        }
    });
});
</script>
<?php endif; ?>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>