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

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງຜູ້ໃຊ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

$user_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນຜູ້ໃຊ້ພ້ອມກັບຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT u.*, t.name as temple_name, t.district, t.province 
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

// ກວດສອບສິດໃນການເຂົ້າເບິ່ງຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_self = $_SESSION['user']['id'] == $user_id;

$can_view = $is_superadmin || $is_self || 
           ($is_admin && $_SESSION['user']['temple_id'] == $user['temple_id']);

if (!$can_view) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເບິ່ງຂໍ້ມູນຜູ້ໃຊ້ນີ້";
    header('Location: ' . $base_url . 'users/');
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂແລະລຶບ
$can_edit = $is_superadmin || $is_self || 
           ($is_admin && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin');

$can_delete = ($is_superadmin || 
              ($is_admin && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')) 
              && !$is_self;
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ລາຍລະອຽດຜູ້ໃຊ້</h1>
            <p class="text-sm text-gray-600">ຂໍ້ມູນຂອງ <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>users/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <?php if ($can_edit): ?>
            <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
            </a>
            <?php endif; ?>
            <?php if ($can_delete): ?>
            <a href="<?= $base_url ?>users/delete.php?id=<?= $user['id'] ?>" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-trash mr-2"></i> ລຶບ
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນສຳເລັດ -->
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
    
    <!-- ຂໍ້ມູນຜູ້ໃຊ້ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນຜູ້ໃຊ້</h2>
            
            <div class="space-y-4">
                <!-- ຊື່ຜູ້ໃຊ້ແລະຊື່ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">ຊື່ຜູ້ໃຊ້</p>
                        <p class="mt-1 text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">ຊື່-ນາມສະກຸນ</p>
                        <p class="mt-1 text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                    </div>
                </div>
                
                <!-- ເພີ່ມຂໍ້ມູນອີເມວ ແລະ ເບີໂທລະສັບ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">ອີເມວ</p>
                        <?php if (!empty($user['email'])): ?>
                        <p class="mt-1 text-gray-800">
                            <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="hover:text-indigo-600">
                                <?= htmlspecialchars($user['email']) ?>
                            </a>
                        </p>
                        <?php else: ?>
                        <p class="mt-1 text-gray-500 italic">ບໍ່ມີຂໍ້ມູນ</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">ເບີໂທລະສັບ</p>
                        <?php if (!empty($user['phone'])): ?>
                        <p class="mt-1 text-gray-800">
                            <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="hover:text-indigo-600">
                                <?= htmlspecialchars($user['phone']) ?>
                            </a>
                        </p>
                        <?php else: ?>
                        <p class="mt-1 text-gray-500 italic">ບໍ່ມີຂໍ້ມູນ</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ບົດບາດແລະວັນທີສ້າງ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">ບົດບາດ</p>
                        <div class="mt-1">
                            <?php
                            $role_labels = [
                                'superadmin' => ['ຜູ້ດູແລລະບົບສູງສຸດ', 'bg-purple-100 text-purple-800'],
                                'admin' => ['ຜູ້ດູແລວັດ', 'bg-blue-100 text-blue-800'],
                                'user' => ['ຜູ້ໃຊ້ທົ່ວໄປ', 'bg-green-100 text-green-800']
                            ];
                            $role_data = $role_labels[$user['role']] ?? [$user['role'], 'bg-gray-100 text-gray-800'];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $role_data[1] ?>">
                                <?= $role_data[0] ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">ວັນທີສ້າງບັນຊີ</p>
                        <p class="mt-1 text-gray-800"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
                
                <!-- ສະແດງຂໍ້ມູນການເຂົ້າລະບົບຫຼ້າສຸດ (ຖ້າມີ) -->
                <?php if (!empty($user['last_login'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">ເຂົ້າລະບົບຫຼ້າສຸດ</p>
                        <p class="mt-1 text-gray-800"><?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ຂໍ້ມູນວັດ (ຖ້າມີ) -->
                <?php if (!empty($user['temple_id'])): ?>
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h3 class="text-lg font-medium text-gray-800 mb-3">ຂໍ້ມູນວັດ</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">ຊື່ວັດ</p>
                            <p class="mt-1 text-gray-800"><?= htmlspecialchars($user['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">ສະຖານທີ່</p>
                            <p class="mt-1 text-gray-800">
                                <?php
                                $location = [];
                                if (!empty($user['district'])) $location[] = htmlspecialchars($user['district']);
                                if (!empty($user['province'])) $location[] = htmlspecialchars($user['province']);
                                echo !empty($location) ? implode(', ', $location) : 'ບໍ່ມີຂໍ້ມູນ';
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($user['temple_id'])): ?>
                    <div class="mt-3">
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $user['temple_id'] ?>" class="text-indigo-600 hover:text-indigo-800 flex items-center text-sm">
                            <span>ເບິ່ງຂໍ້ມູນວັດ</span>
                            <i class="fas fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ຂໍ້ມູນການຕິດຕໍ່ -->
    <?php if (!empty($user['email']) || !empty($user['phone'])): ?>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">ຊ່ອງທາງການຕິດຕໍ່</h2>
            
            <div class="space-y-4">
                <?php if (!empty($user['email'])): ?>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-blue-100 rounded-md p-2">
                            <i class="fas fa-envelope text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ອີເມວ</p>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="mt-1 text-gray-800 hover:text-indigo-600">
                            <?= htmlspecialchars($user['email']) ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($user['phone'])): ?>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-green-100 rounded-md p-2">
                            <i class="fas fa-phone-alt text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">ເບີໂທລະສັບ</p>
                        <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="mt-1 text-gray-800 hover:text-indigo-600">
                            <?= htmlspecialchars($user['phone']) ?>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ຂໍ້ມູນເພີ່ມເຕີມ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">ຄວາມປອດໄພ</h2>
            
            <div class="space-y-4">
                <!-- ການປ່ຽນລະຫັດຜ່ານ -->
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-2">ລະຫັດຜ່ານ</p>
                    <?php if ($is_self || ($is_superadmin && $user['id'] != $_SESSION['user']['id'])): ?>
                    <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-800 flex items-center text-sm">
                        <i class="fas fa-key mr-1"></i>
                        <span>ປ່ຽນລະຫັດຜ່ານ</span>
                    </a>
                    <?php else: ?>
                    <p class="text-sm text-gray-500 italic">ທ່ານບໍ່ສາມາດປ່ຽນລະຫັດຜ່ານຂອງຜູ້ໃຊ້ນີ້ໄດ້</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($is_self): ?>
                <!-- ຄໍາແນະນໍາເພື່ອຄວາມປອດໄພ -->
                <div class="bg-yellow-50 rounded-md p-4 mt-2">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-lightbulb text-yellow-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">ຄໍາແນະນໍາເພື່ອຄວາມປອດໄພ</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>ປ່ຽນລະຫັດຜ່ານຂອງທ່ານຢ່າງສະໝໍ່າສະເໝີ</li>
                                    <li>ໃຊ້ລະຫັດຜ່ານທີ່ປອດໄພ ປະກອບດ້ວຍຕົວອັກສອນ, ຕົວເລກ ແລະ ສັນຍາລັກພິເສດ</li>
                                    <li>ບໍ່ຄວນໃຊ້ລະຫັດຜ່ານດຽວກັນກັບເວັບໄຊອື່ນ</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($is_superadmin && $user['id'] != $_SESSION['user']['id']): ?>
            <!-- ຂໍ້ມູນກ່ຽວກັບການຈັດການບັນຊີ (ສະເພາະ superadmin) -->
            <div class="border-t border-gray-200 pt-4 mt-4">
                <h3 class="text-lg font-medium text-gray-800 mb-3">ການຈັດການບັນຊີ</h3>
                
                <div class="flex space-x-3 mt-2">
                    <?php if ($user['role'] !== 'superadmin'): ?>
                    <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-user-shield mr-2"></i> ປ່ຽນບົດບາດ
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!$is_self): ?>
                    <a href="<?= $base_url ?>users/delete.php?id=<?= $user['id'] ?>" class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-trash mr-2"></i> ລຶບບັນຊີ
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>