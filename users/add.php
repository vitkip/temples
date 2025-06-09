<?php
// filepath: c:\xampp\htdocs\temples\users\add.php
ob_start();

$page_title = 'ເພີ່ມຜູ້ໃຊ້ໃໝ່';
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

// ກວດສອບສິດໃນການເພີ່ມຜູ້ໃຊ້ໃໝ່
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';

// ກວດສອບສິດ - ສະເພາະ superadmin ແລະ admin ເທົ່ານັ້ນທີ່ສາມາດເພີ່ມຜູ້ໃຊ້ໃໝ່
if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເພີ່ມຜູ້ໃຊ້ໃໝ່";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ດຶງຂໍ້ມູນວັດສໍາລັບ dropdown
$temples = [];
if ($is_superadmin) {
    // Superadmin ສາມາດເລືອກວັດໄດ້ທັງໝົດ
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} else if ($is_admin) {
    // Admin ເຫັນສະເພາະວັດຂອງຕົນເອງ
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'username' => '',
    'name' => '',
    'email' => '',    // เพิ่มฟิลด์อีเมล
    'phone' => '',    // เพิ่มฟิลด์เบอร์โทรศัพท์
    'role' => $is_admin ? 'user' : '', // Admin ສາມາດເພີ່ມໄດ້ສະເພາະ user
    'temple_id' => $is_admin ? $_SESSION['user']['temple_id'] : '',
    'password' => '',
    'confirm_password' => ''
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'users/add.php');
        exit;
    }
    
    // ກວດສອບຂໍ້ມູນທີ່ສົ່ງມາ
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),    // เพิ่มการรับค่าอีเมล
        'phone' => trim($_POST['phone'] ?? ''),    // เพิ่มการรับค่าเบอร์โทรศัพท์
        'role' => $_POST['role'] ?? ($is_admin ? 'user' : ''),
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : ($is_admin ? $_SESSION['user']['temple_id'] : ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // ກວດສອບ username
    if (empty($form_data['username'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    } else {
        // ກວດສອບວ່າ username ຊໍ້າກັນຫຼືບໍ່
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check_stmt->execute([$form_data['username']]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
        }
    }
    
    // ກວດສອບຊື່
    if (empty($form_data['name'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່-ນາມສະກຸນ";
    }
    
    // ກວດສອບອີເມລ (ຖ້າມີ)
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    }
    
    // ກວດສອບເບີໂທລະສັບ (ຖ້າມີ)
    if (!empty($form_data['phone']) && !preg_match('/^[0-9]{8,10}$/', $form_data['phone'])) {
        $errors[] = "ຮູບແບບເບີໂທລະສັບບໍ່ຖືກຕ້ອງ (8-10 ຕົວເລກ)";
    }
    
    // ກວດສອບລະຫັດຜ່ານ
    if (empty($form_data['password'])) {
        $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານ";
    } else if (strlen($form_data['password']) < 6) {
        $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
    } else if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
    }
    
    // ກວດສອບບົດບາດ (role)
    if ($is_superadmin && empty($form_data['role'])) {
        $errors[] = "ກະລຸນາເລືອກບົດບາດ";
    }
    
    // ກວດສອບວ່າ admin ພະຍາຍາມສ້າງ admin ຫຼື superadmin ຫຼືບໍ່
    if ($is_admin && $form_data['role'] !== 'user') {
        $errors[] = "ທ່ານສາມາດເພີ່ມໄດ້ສະເພາະຜູ້ໃຊ້ທົ່ວໄປເທົ່ານັ້ນ";
    }
    
    // ກວດສອບວັດ (ສໍາລັບ admin ແລະ user)
    if ($is_superadmin && in_array($form_data['role'], ['admin', 'user']) && empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ
    if (empty($errors)) {
        try {
            // ສ້າງ SQL ສໍາລັບການເພີ່ມຜູ້ໃຊ້ໃໝ່
            if ($form_data['role'] === 'superadmin') {
                // Superadmin ບໍ່ມີ temple_id
                $sql = "INSERT INTO users (username, password, name, email, phone, role, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $form_data['username'],
                    password_hash($form_data['password'], PASSWORD_DEFAULT),
                    $form_data['name'],
                    $form_data['email'],    // เพิ่มอีเมล
                    $form_data['phone'],    // เพิ่มเบอร์โทรศัพท์
                    $form_data['role']
                ];
            } else {
                // Admin ແລະ User ມີ temple_id
                $sql = "INSERT INTO users (username, password, name, email, phone, role, temple_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $form_data['username'],
                    password_hash($form_data['password'], PASSWORD_DEFAULT),
                    $form_data['name'],
                    $form_data['email'],    // เพิ่มอีเมล
                    $form_data['phone'],    // เพิ่มเบอร์โทรศัพท์
                    $form_data['role'],
                    $form_data['temple_id']
                ];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['success'] = "ເພີ່ມຜູ້ໃຊ້ " . $form_data['username'] . " ສໍາເລັດແລ້ວ";
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
            <h1 class="text-2xl font-bold text-gray-800">ເພີ່ມຜູ້ໃຊ້ໃໝ່</h1>
            <p class="text-sm text-gray-600">ເພີ່ມບັນຊີຜູ້ໃຊ້ໃໝ່ໃນລະບົບ</p>
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
    
    <!-- ຟອມເພີ່ມຜູ້ໃຊ້ໃໝ່ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>users/add.php" method="POST" class="p-6">
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
                
                <!-- เพิ่มฟิลด์อีเมล -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">ອີເມວ</label>
                    <input type="email" name="email" id="email" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['email']) ?>" placeholder="example@domain.com">
                </div>
                
                <!-- เพิ่มฟิลด์เบอร์โทรศัพท์ -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">ເບີໂທລະສັບ</label>
                    <input type="tel" name="phone" id="phone" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['phone']) ?>" placeholder="02012345678">
                    <p class="text-xs text-gray-500 mt-1">ປ້ອນແຕ່ຕົວເລກ 8-10 ຕົວ</p>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">ລະຫັດຜ່ານ <span class="text-red-600">*</span></label>
                    <input type="password" name="password" id="password" class="form-input rounded-md w-full" minlength="6" required>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">ຢືນຢັນລະຫັດຜ່ານ <span class="text-red-600">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input rounded-md w-full" minlength="6" required>
                </div>
                
                <?php if ($is_superadmin): ?>
                <!-- ບົດບາດ (ສະເພາະ superadmin) -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">ບົດບາດ <span class="text-red-600">*</span></label>
                    <select name="role" id="role" class="form-select rounded-md w-full" required>
                        <option value="">-- ເລືອກບົດບາດ --</option>
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
                <!-- ສະແດງບົດບາດ (ສໍາລັບ admin) -->
                <input type="hidden" name="role" value="user">
                <input type="hidden" name="temple_id" value="<?= $_SESSION['user']['temple_id'] ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ບົດບາດ</label>
                    <div class="py-2 px-3 bg-gray-50 rounded-md">ຜູ້ໃຊ້ທົ່ວໄປ</div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ວັດ</label>
                    <div class="py-2 px-3 bg-gray-50 rounded-md">
                        <?php 
                        if (!empty($temples)) {
                            echo htmlspecialchars($temples[0]['name']);
                        } else {
                            echo 'ບໍ່ມີຂໍ້ມູນ';
                        }
                        ?>
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