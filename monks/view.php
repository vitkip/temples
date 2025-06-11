<?php
// filepath: c:\xampp\htdocs\temples\monks\view.php
ob_start(); // เพิ่ม output buffering เพื่อป้องกัน headers already sent

$page_title = 'ລາຍລະອຽດພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນພະສົງພ້ອມກັບຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT m.*, t.name as temple_name, t.district, t.province 
    FROM monks m
    LEFT JOIN temples t ON m.temple_id = t.id
    WHERE m.id = ?
");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || 
            ($_SESSION['user']['role'] === 'admin' && 
             $_SESSION['user']['temple_id'] == $monk['temple_id']));
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
           
           <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($monk['prefix']) ?>  <?= htmlspecialchars($monk['name']) ?></h1>
            <?php if (!empty($monk['lay_name'])): ?>
            <p class="text-sm text-gray-600">ນາມສະກຸນ: <?= htmlspecialchars($monk['lay_name']) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>monks/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            
            <?php if ($can_edit): ?>
            <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- ຂໍ້ມູນພະສົງ -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ຂໍ້ມູນພື້ນຖານ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນພື້ນຖານ</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                        <div>
                            <h3 class="text-sm text-gray-500">ວັດ</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($monk['temple_name'] ?? '-') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ພັນສາ</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($monk['pansa'] ?? '-') ?> ພັນສາ</p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ຕຳແໜ່ງ</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($monk['position'] ?? '-') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ວັນເດືອນປີເກີດ</h3>
                            <p class="text-gray-800">
                                <?= $monk['birth_date'] ? date('d/m/Y', strtotime($monk['birth_date'])) : '-' ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ວັນບວດ</h3>
                            <p class="text-gray-800">
                                <?= $monk['ordination_date'] ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-' ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ເບີໂທຕິດຕໍ່</h3>
                            <p class="text-gray-800">
                                <?= htmlspecialchars($monk['contact_number'] ?? '-') ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ສະຖານະ</h3>
                            <p>
                                <?php if($monk['status'] === 'active'): ?>
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">ບວດຢູ່</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">ສິກແລ້ວ</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ວັນທີປັບປຸງຂໍ້ມູນລ່າສຸດ</h3>
                            <p class="text-gray-800">
                                <?= date('d/m/Y H:i', strtotime($monk['updated_at'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ຂໍ້ມູນການສຶກສາ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນການສຶກສາ</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                        <div>
                            <h3 class="text-sm text-gray-500">ການສຶກສາສາມັນ</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($monk['education'] ?? '-') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm text-gray-500">ການສຶກສາທາງທຳ</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($monk['dharma_education'] ?? '-') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ຂໍ້ມູນວັດ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນວັດ</h2>
                    
                    <div class="flex items-center space-x-4 mb-4">
                        <i class="fas fa-gopuram text-3xl text-indigo-600"></i>
                        <div>
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($monk['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></h3>
                            <p class="text-sm text-gray-500">
                                <?= htmlspecialchars($monk['district'] ?? '') ?>
                                <?= !empty($monk['district']) && !empty($monk['province']) ? ', ' : '' ?>
                                <?= htmlspecialchars($monk['province'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($monk['temple_id'])): ?>
                    <div class="mt-4">
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $monk['temple_id'] ?>" class="text-indigo-600 hover:text-indigo-800 flex items-center">
                            <span>ເບິ່ງລາຍລະອຽດວັດ</span>
                            <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ສ່ວນແຖບຂ້າງ -->
        <div class="space-y-6">
            <!-- ຮູບພາບພະສົງ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຮູບພາບ</h2>
                    <?php if (!empty($monk['photo']) && $monk['photo'] != 'uploads/monks/default.png'): ?>
                        <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="w-full h-auto rounded-lg">
                    <?php else: ?>
                        <div class="bg-gray-100 rounded-lg p-8 flex items-center justify-center">
                            <span class="text-gray-400"><i class="fas fa-user-circle fa-5x"></i></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ຂໍ້ມູນໂດຍຫຍໍ້ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນໂດຍຫຍໍ້</h2>
                    
                    <div class="space-y-3">
                        <?php if (!empty($monk['pansa'])): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                <i class="fas fa-dharmachakra text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ພັນສາ</p>
                                <p class="font-medium"><?= htmlspecialchars($monk['pansa']) ?> ພັນສາ</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($monk['ordination_date'])): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                                <i class="fas fa-calendar-alt text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ບວດເມື່ອ</p>
                                <p class="font-medium"><?= date('d/m/Y', strtotime($monk['ordination_date'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($monk['birth_date'])): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                <i class="fas fa-birthday-cake text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ອາຍຸ</p>
                                <p class="font-medium">
                                    <?php
                                    $birth = new DateTime($monk['birth_date']);
                                    $now = new DateTime();
                                    $age = $birth->diff($now)->y;
                                    echo $age . ' ປີ';
                                    ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($monk['position'])): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-user-shield text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ຕຳແໜ່ງ</p>
                                <p class="font-medium"><?= htmlspecialchars($monk['position']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>