<?php
// filepath: c:\xampp\htdocs\temples\users\delete.php
ob_start(); // เริ่ม output buffering
session_start(); // เริ่มต้น session

require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ບໍ່ອະນຸຍາດໃຫ້ລຶບບັນຊີຕົນເອງ
if ($user_id === $_SESSION['user']['id']) {
    $_SESSION['error'] = "ບໍ່ສາມາດລຶບບັນຊີຜູ້ໃຊ້ຂອງຕົນເອງໄດ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ຈະລຶບ ລວມທັງຂໍ້ມູນວັດແລະແຂວງ
$stmt = $pdo->prepare("
    SELECT u.*, t.name as temple_name, t.province_id, p.province_name 
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

// ກວດສອບສິດໃນການລຶບຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';
$can_delete = $is_superadmin || ($is_admin && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin');

// ກວດສອບການລຶບຂໍ້ມູນສຳລັບ province_admin
if ($is_province_admin && !$can_delete) {
    // province_admin ສາມາດລຶບໄດ້ສະເພາະຜູ້ໃຊ້ທີ່ຢູ່ໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຕົນເອງຮັບຜິດຊອບເທົ່ານັ້ນ
    // ແລະບໍ່ສາມາດລຶບ superadmin ຫຼື province_admin ຄົນອື່ນໄດ້
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'province_admin') {
        $check_stmt = $pdo->prepare("
            SELECT 1 FROM user_province_access upa
            JOIN temples t ON upa.province_id = t.province_id
            WHERE upa.user_id = ? AND t.id = ?
        ");
        $check_stmt->execute([$_SESSION['user']['id'], $user['temple_id']]);
        if ($check_stmt->fetchColumn()) {
            $can_delete = true;
        }
    }
}

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ເມື່ອມີການຢືນຢັນການລຶບ
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'users/');
        exit;
    }

    try {
        // ເລີ່ມ transaction ເພື່ອໃຫ້ແນ່ໃຈວ່າການລຶບຂໍ້ມູນທັງໝົດຈະສໍາເລັດສົມບູນ
        $pdo->beginTransaction();

        // ລຶບຂໍ້ມູນຄວາມສໍາພັນຈາກຕາຕະລາງ user_province_access ກ່ອນ (ຖ້າມີ)
        if ($user['role'] === 'province_admin') {
            $delete_access = $pdo->prepare("DELETE FROM user_province_access WHERE user_id = ?");
            $delete_access->execute([$user_id]);
        }

        // ລຶບຂໍ້ມູນຜູ້ໃຊ້
        $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->execute([$user_id]);

        if ($delete_stmt->rowCount() > 0) {
            // ຢືນຢັນການ transaction
            $pdo->commit();
            $_SESSION['success'] = "ລຶບຜູ້ໃຊ້ {$user['username']} ສໍາເລັດແລ້ວ";
        } else {
            // ຍົກເລີກການ transaction ຖ້າບໍ່ສາມາດລຶບຜູ້ໃຊ້ໄດ້
            $pdo->rollBack();
            $_SESSION['error'] = "ບໍ່ສາມາດລຶບຜູ້ໃຊ້ໄດ້";
        }
        
        header('Location: ' . $base_url . 'users/');
        exit;
    } catch (PDOException $e) {
        // ກໍລະນີມີຂໍ້ຜິດພາດແມ່ນໃຫ້ຍົກເລີກການ transaction
        $pdo->rollBack();
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        header('Location: ' . $base_url . 'users/');
        exit;
    }
}

// ດຶງຂໍ້ມູນແຂວງທີ່ຮັບຜິດຊອບ (ສຳລັບ province_admin)
$managed_provinces = [];
if ($user['role'] === 'province_admin') {
    $provinces_stmt = $pdo->prepare("
        SELECT p.province_name, p.province_code
        FROM user_province_access upa
        JOIN provinces p ON upa.province_id = p.province_id
        WHERE upa.user_id = ?
        ORDER BY p.province_name
    ");
    $provinces_stmt->execute([$user_id]);
    $managed_provinces = $provinces_stmt->fetchAll();
}

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ຖ້າບໍ່ມີການຢືນຢັນ, ສະແດງໜ້າຢືນຢັນການລຶບ
$page_title = 'ຢືນຢັນການລຶບຜູ້ໃຊ້';
require_once '../includes/header.php';
?>

<!-- ໜ້າຢືນຢັນການລຶບ -->
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ຢືນຢັນການລຶບຜູ້ໃຊ້</h1>
            <p class="text-sm text-gray-600">ກະລຸນາຢືນຢັນການລຶບຂໍ້ມູນຜູໃຊ້</p>
        </div>
        <div>
            <a href="<?= $base_url ?>users/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm overflow-hidden p-6">
        <div class="flex items-center mb-6">
            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">ທ່ານກໍາລັງຈະລຶບຜູ້ໃຊ້</h2>
                <p class="text-gray-600">ການລຶບຜູ້ໃຊ້ບໍ່ສາມາດຍົກເລີກໄດ້</p>
            </div>
        </div>
        
        <div class="bg-gray-50 rounded-md p-4 mb-6">
            <h3 class="font-medium text-gray-700 mb-2">ລາຍລະອຽດຜູ້ໃຊ້</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">ຊື່ຜູ້ໃຊ້:</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">ຊື່-ນາມສະກຸນ:</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">ບົດບາດ:</p>
                    <p class="font-medium text-gray-900">
                        <?php 
                        $role_labels = [
                            'superadmin' => 'ຜູ້ດູແລລະບົບສູງສຸດ',
                            'province_admin' => 'ຜູ້ດູແລລະດັບແຂວງ',
                            'admin' => 'ຜູ້ດູແລວັດ',
                            'user' => 'ຜູ້ໃຊ້ທົ່ວໄປ'
                        ];
                        echo $role_labels[$user['role']] ?? $user['role'];
                        ?>
                    </p>
                </div>
                
                <?php if (!empty($user['temple_name'])): ?>
                <div>
                    <p class="text-sm text-gray-500">ວັດ:</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($user['temple_name']) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($user['province_name'])): ?>
                <div>
                    <p class="text-sm text-gray-500">ແຂວງ:</p>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($user['province_name']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($user['role'] === 'province_admin' && !empty($managed_provinces)): ?>
            <!-- ສະແດງແຂວງທີ່ຮັບຜິດຊອບ (ສຳລັບຜູ້ດູແລລະດັບແຂວງ) -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-500 mb-2">ແຂວງທີ່ຮັບຜິດຊອບ:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($managed_provinces as $province): ?>
                    <li class="text-sm"><?= htmlspecialchars($province['province_name']) ?> (<?= htmlspecialchars($province['province_code']) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-sm text-yellow-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php if ($user['role'] === 'province_admin'): ?>
                    ການລຶບຜູ້ດູແລລະດັບແຂວງຈະລຶບຂໍ້ມູນການຈັດການແຂວງທັງໝົດຂອງຜູ້ໃຊ້ນີ້ນຳ
                    <?php else: ?>
                    ການລຶບຜູ້ໃຊ້ຈະລຶບທຸກຂໍ້ມູນທີ່ກ່ຽວຂ້ອງກັບຜູໃຊ້ນີ້
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <form action="<?= $base_url ?>users/delete.php?id=<?= $user_id ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="confirm_delete" value="yes">
            
            <div class="border-t border-gray-200 pt-4 flex justify-end space-x-3">
                <a href="<?= $base_url ?>users/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-trash mr-2"></i> ຢືນຢັນການລຶບ
                </button>
            </div>
        </form>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>