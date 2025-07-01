<?php
// filepath: c:\xampp\htdocs\temples\events\view.php
ob_start(); // เพิ่ม output buffering

$page_title = 'ລາຍລະອຽດກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນກິດຈະກໍາພ້ອມກັບຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT e.*, 
           t.name as temple_name, 
           t.id as temple_id,
           d.district_name as district, 
           p.province_name as province
    FROM events e
    LEFT JOIN temples t ON e.temple_id = t.id
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ດຶງລາຍຊື່ພະສົງທີ່ເຂົ້າຮ່ວມໃນກິດຈະກໍານີ້
$monk_stmt = $pdo->prepare("
    SELECT em.*, m.name as monk_name, m.photo, m.position, m.pansa
    FROM event_monk em
    LEFT JOIN monks m ON em.monk_id = m.id
    WHERE em.event_id = ?
    ORDER BY em.role, m.pansa DESC, m.name
");
$monk_stmt->execute([$event_id]);
$monks = $monk_stmt->fetchAll();

// ກວດສອບສິດໃນການແກ້ໄຂ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || 
            ($_SESSION['user']['role'] === 'admin' && 
             $_SESSION['user']['temple_id'] == $event['temple_id']));
?>
<link rel="stylesheet" href="<?= $base_url ?>assets/css/events-view.css">
<div class="page-container bg-temple-pattern">
    <!-- 1. ส่วนหัวของหน้า - แบ่งให้ชัดเจน -->
    <div class="view-header bg-white/80 backdrop-blur-sm p-6 rounded-lg mb-6" style="animation: fadeInUp 0.5s ease-out forwards;">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="monk-title border-b-2 border-amber-500 pb-1 inline-block"><?= htmlspecialchars($event['title']) ?></h1>
                <p class="text-gray-600 mt-1">
                    <i class="fas fa-place-of-worship text-amber-600 mr-2"></i>
                    <?= htmlspecialchars($event['temple_name']) ?> · 
                    <i class="fas fa-calendar-alt text-amber-600 mx-2"></i>
                    <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                </p>
            </div>
            <div class="flex space-x-2 shrink-0">
                <a href="<?= $base_url ?>events/" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> ກັບຄືນ
                </a>
                
                <?php if ($can_edit): ?>
                <a href="<?= $base_url ?>events/edit.php?id=<?= $event['id'] ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> ແກ້ໄຂ
                </a>
                <a href="javascript:void(0)" class="btn btn-danger delete-event-link" 
                   data-id="<?= $event['id'] ?>" 
                   data-title="<?= htmlspecialchars($event['title']) ?>">
                    <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. ข้อความแจ้งเตือน - จัดกลุ่มให้ชัดเจน -->
    <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): ?>
    <div class="notification-container mb-6">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg shadow-sm animate-fade-in">
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
        <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg shadow-sm animate-fade-in">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 3. เนื้อหาหลักแบบตาราง 2 คอลัมน์: 70/30 -->
    <div class="grid grid-cols-1 lg:grid-cols-7 gap-6">
        <!-- ส่วนซ้าย: คอลัมน์หลัก (70%) -->
        <div class="lg:col-span-5 space-y-6" style="animation: fadeInUp 0.5s 0.1s ease-out forwards; opacity: 0;">
            
            <!-- 3.1 การ์ดรายละเอียดกิจกรรม - หัวข้อชัดเจน -->
            <div class="info-card">
                <div class="info-card-header border-b-2 border-amber-200 flex items-center py-4 px-6" style="min-height:64px;">
                    <h2 class="info-card-title flex items-center text-lg md:text-xl font-semibold text-amber-800">
                        <span class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mr-4 text-xl">
                            <i class="fas fa-calendar-check text-amber-700"></i>
                        </span>
                        ລາຍລະອຽດກິດນິມົນ
                    </h2>
                </div>
                <div class="info-card-body p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-amber-50/50 rounded-lg p-4">
                            <span class="info-label text-amber-700 text-xs uppercase font-medium">ວັນທີ</span>
                            <p class="info-value flex items-center mt-1 text-lg font-medium">
                                <i class="fas fa-calendar-alt w-5 text-amber-600 mr-2"></i>
                                <?= date('d/m/Y', strtotime($event['event_date'])) ?> 
                            </p>
                        </div>
                        
                        <div class="bg-amber-50/50 rounded-lg p-4">
                            <span class="info-label text-amber-700 text-xs uppercase font-medium">ເວລາ</span>
                            <p class="info-value flex items-center mt-1 text-lg font-medium">
                                <i class="fas fa-clock w-5 text-amber-600 mr-2"></i>
                                <?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ
                            </p>
                        </div>
                        
                        <?php if (!empty($event['location'])): ?>
                        <div class="md:col-span-2 bg-amber-50/50 rounded-lg p-4">
                            <span class="info-label text-amber-700 text-xs uppercase font-medium">ສະຖານທີ່</span>
                            <p class="info-value flex items-center mt-1">
                                <i class="fas fa-map-marker-alt w-5 text-amber-600 mr-2"></i>
                                <?= htmlspecialchars($event['location']) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="md:col-span-2 mt-2 pt-6 border-t border-amber-200">
                            <span class="info-label text-amber-700 text-sm uppercase font-medium mb-3 block">ລາຍລະອຽດ</span>
                            <div class="prose max-w-none bg-white/70 p-4 rounded-lg shadow-sm">
                                <?php if (!empty($event['description'])): ?>
                                    <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($event['description']) ?></p>
                                <?php else: ?>
                                    <p class="text-gray-500 italic text-center py-4">ບໍ່ມີລາຍລະອຽດເພີ່ມເຕີມ</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3.2 การ์ดพระสงฆ์ที่เข้าร่วม - หัวข้อชัดเจน -->
            <div class="info-card">
                <div class="info-card-header border-b-2 border-amber-200 flex justify-between items-center py-4 px-6 bg-gradient-to-r from-amber-50 to-white" style="min-height:72px;">
                    <h2 class="info-card-title flex items-center">
                        <span class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mr-4 shadow-sm">
                            <i class="fas fa-users text-amber-700 text-lg"></i>
                        </span>
                        <div>
                            <span class="text-lg md:text-xl font-semibold text-amber-800">ພະສົງທີເຂົ້າຮ່ວມ</span>
                            <span class="ml-2 inline-block px-2 py-1 text-xs font-medium leading-none text-center text-amber-800 bg-amber-100 rounded-full"><?= count($monks) ?></span>
                        </div>
                    </h2>
                    <?php if ($can_edit): ?>
                    <a href="<?= $base_url ?>events/add_monk.php?event_id=<?= $event_id ?>" class="btn btn-edit text-sm py-2 px-4 flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> ເພີ່ມພະສົງ
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if (count($monks) > 0): ?>
                <div class="overflow-x-auto">
                    <!-- Desktop view - Table -->
                    <table class="w-full hidden md:table">
                        <thead class="bg-gradient-to-r from-amber-50 to-amber-100/30">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-amber-700 uppercase tracking-wider">ພະສົງ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-amber-700 uppercase tracking-wider">ຕຳແໜ່ງ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-amber-700 uppercase tracking-wider">ໝາຍເຫດ</th>
                                <?php if ($can_edit): ?>
                                <th class="px-6 py-3 text-right text-xs font-medium text-amber-700 uppercase tracking-wider">ຈັດການ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100">
                            <?php foreach ($monks as $monk): ?>
                            <tr class="hover:bg-amber-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 mr-4">
                                            <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                                                <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['monk_name']) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-amber-100">
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                                    <i class="fas fa-user text-amber-600"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($monk['monk_name']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($monk['pansa'] ?? '') ?> ພັນສາ</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($monk['role'])): ?>
                                    <span class="inline-block bg-amber-100 text-amber-800 text-xs font-medium px-2 py-0.5 rounded">
                                        <?= htmlspecialchars($monk['role']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?= !empty($monk['note']) ? htmlspecialchars($monk['note']) : '<span class="text-gray-400">-</span>' ?>
                                </td>
                                <?php if ($can_edit): ?>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <a href="<?= $base_url ?>events/edit_monk.php?id=<?= $monk['id'] ?>&event_id=<?= $event_id ?>" class="btn-icon edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="btn-icon delete delete-monk-link" 
                                           data-id="<?= $monk['id'] ?>" 
                                           data-name="<?= htmlspecialchars($monk['monk_name']) ?>"
                                           data-event-id="<?= $event_id ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile view - Cards: รูปแบบที่ชัดเจนขึ้น -->
                    <div class="md:hidden divide-y divide-gray-200">
                        <?php foreach ($monks as $monk): ?>
                        <div class="py-4 px-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3">
                                    <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                                        <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['monk_name']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-amber-100">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
                                            <i class="fas fa-user text-amber-600"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($monk['monk_name']) ?></h3>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($monk['pansa'] ?? '') ?> ພັນສາ</p>
                                            
                                            <?php if (!empty($monk['role'])): ?>
                                            <div class="mt-1 inline-block bg-amber-100 text-amber-800 text-xs font-medium px-2 py-0.5 rounded">
                                                <?= htmlspecialchars($monk['role']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($monk['note'])): ?>
                                            <p class="mt-2 text-sm text-gray-600">
                                                <?= htmlspecialchars($monk['note']) ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($can_edit): ?>
                                        <div class="flex space-x-2">
                                            <a href="<?= $base_url ?>events/edit_monk.php?id=<?= $monk['id'] ?>&event_id=<?= $event_id ?>" class="btn-icon edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0)" class="btn-icon delete delete-monk-link" 
                                               data-id="<?= $monk['id'] ?>" 
                                               data-name="<?= htmlspecialchars($monk['monk_name']) ?>"
                                               data-event-id="<?= $event_id ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500 border-t border-gray-100">
                    <div class="bg-gray-50 rounded-lg p-8 inline-block">
                        <i class="fas fa-pray text-4xl mb-3 text-gray-300"></i>
                        <p class="mb-4">ຍັງບໍ່ມີພະສົງເຂົ້າຮ່ວມໃນກິດຈະກໍານີ້</p>
                        <?php if ($can_edit): ?>
                        <a href="<?= $base_url ?>events/add_monk.php?event_id=<?= $event_id ?>" class="btn btn-edit mt-2">
                            <i class="fas fa-plus-circle"></i> ເພີ່ມພະສົງເຂົ້າຮ່ວມ
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ส่วนขวา: sidebar (30%) - กำหนดสไตล์ชัดเจน -->
        <div class="lg:col-span-2 space-y-6" style="animation: fadeInUp 0.5s 0.2s ease-out forwards; opacity: 0;">
            <!-- 3.3 ข้อมูลวัด - เพิ่มสไตล์ที่ชัดเจน -->
            <div class="info-card border-t-4 border-amber-500">
                <div class="info-card-header border-b-2 border-amber-100 flex items-center py-4 px-6" style="min-height:64px;">
                    <h2 class="info-card-title flex items-center text-lg md:text-xl font-semibold text-amber-800">
                        <span class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center mr-3 shadow-sm">
                            <i class="fas fa-place-of-worship text-amber-600"></i>
                        </span>
                        ຂໍ້ມູນວັດ
                    </h2>
                </div>
                <div class="info-card-body p-5">
                    <div class="flex items-center mb-4">
                        <div class="icon-circle amber mr-4">
                            <i class="fas fa-gopuram"></i>
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($event['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></h3>
                            <p class="text-sm text-gray-500 mt-1">
                                <?= htmlspecialchars($event['district'] ?? '') ?>
                                <?= !empty($event['district']) && !empty($event['province']) ? ', ' : '' ?>
                                <?= htmlspecialchars($event['province'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($event['temple_id'])): ?>
                    <div class="mt-5 pt-3 border-t border-amber-100">
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $event['temple_id'] ?>" class="btn btn-back w-full flex justify-center items-center py-2.5">
                            <span>ເບິ່ງລາຍລະອຽດວັດ</span>
                            <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3.4 ข้อมูลสรุป - เพิ่มสไตล์ที่ชัดเจน -->
            <div class="info-card border-t-4 border-indigo-500">
                <div class="info-card-header border-b-2 border-indigo-100 flex items-center py-4 px-6" style="min-height:64px;">
                    <h2 class="info-card-title flex items-center text-lg md:text-xl font-semibold text-indigo-800">
                        <span class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3 shadow-sm">
                            <i class="fas fa-info-circle text-indigo-600"></i>
                        </span>
                        ຂໍ້ມູນສະຫຼຸບ
                    </h2>
                </div>
                <div class="info-card-body p-5">
                    <div class="grid grid-cols-1 gap-4">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="icon-circle indigo mr-3 flex-shrink-0">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-medium">ວັນທີຈັດງານ</p>
                                <p class="font-medium text-gray-800"><?= date('d/m/Y', strtotime($event['event_date'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="icon-circle amber mr-3 flex-shrink-0">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-medium">ເວລາ</p>
                                <p class="font-medium text-gray-800"><?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($event['location'])): ?>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="icon-circle green mr-3 flex-shrink-0">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-medium">ສະຖານທີ່</p>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($event['location']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="icon-circle blue mr-3 flex-shrink-0">
                                <i class="fas fa-pray"></i>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-medium">ພະສົງເຂົ້າຮ່ວມ</p>
                                <p class="font-medium text-gray-800"><?= count($monks) ?> ອົງ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal ຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ -->
<div id="removeMonkModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="card bg-white rounded-lg max-w-md w-full p-6 shadow-2xl" style="animation: fadeInUp 0.3s ease-out forwards;">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບພະສົງ <span id="removeMonkNameDisplay" class="font-medium text-amber-700"></span> ອອກຈາກການເຂົ້າຮ່ວມກິດຈະກໍານີ້ແທ້ບໍ່?</p>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button id="cancelRemove" class="btn btn-back">
                ຍົກເລີກ
            </button>
            <a id="confirmRemove" href="#" class="btn btn-danger">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- Modal ຢືນຢັນການລຶບກິດຈະກໍາ -->
<div id="removeEventModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-60 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="card bg-white rounded-lg max-w-md w-full p-6 shadow-2xl" style="animation: fadeInUp 0.3s ease-out forwards;">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການລຶບກິດຈະກໍາ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບກິດຈະກໍາ <span id="removeEventTitleDisplay" class="font-medium text-red-600"></span> ແທ້ບໍ່?</p>
            <p class="text-sm text-gray-500 mt-2">ຂໍ້ມູນທັງໝົດທີ່ກ່ຽວຂໍ້ງກັບກິດຈະກໍານີ້ ລວມທັງການເຂົ້າຮ່ວມຂອງພະສົງ ຈະຖືກລຶບໄປນຳ.</p>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button class="btn btn-back close-modal">
                ຍົກເລີກ
            </button>
            <a id="confirmEventDelete" href="#" class="btn btn-danger">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- JavaScript ສຳລັບການຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal ລຶບພະສົງ
    const removeMonkModal = document.getElementById('removeMonkModal');
    const removeMonkNameDisplay = document.getElementById('removeMonkNameDisplay');
    const confirmRemove = document.getElementById('confirmRemove');
    
    // Modal ລຶບກິດຈະກໍາ
    const removeEventModal = document.getElementById('removeEventModal');
    const removeEventTitleDisplay = document.getElementById('removeEventTitleDisplay');
    const confirmEventDelete = document.getElementById('confirmEventDelete');
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບພະສົງ
    document.querySelectorAll('.delete-monk-link').forEach(button => {
        button.addEventListener('click', function() {
            const monkId = this.getAttribute('data-id');
            const monkName = this.getAttribute('data-name');
            const eventId = this.getAttribute('data-event-id');
            
            // ຕັ້ງຊື່ພະສົງໃນ modal
            removeMonkNameDisplay.textContent = monkName;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmRemove.href = '<?= $base_url ?>events/remove_monk.php?id=' + monkId + '&event_id=' + eventId;
            
            // ສະແດງ modal
            removeMonkModal.classList.remove('hidden');
        });
    });
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບກິດຈະກໍາ
    document.querySelectorAll('.delete-event-link').forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            const eventTitle = this.getAttribute('data-title');
            
            // ຕັ້ງຊື່ກິດຈະກໍາໃນ modal
            removeEventTitleDisplay.textContent = eventTitle;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmEventDelete.href = '<?= $base_url ?>events/delete.php?id=' + eventId;
            
            // ສະແດງ modal
            removeEventModal.classList.remove('hidden');
        });
    });
    
    // ປິດ modal
    document.querySelectorAll('.close-modal, #cancelRemove').forEach(element => {
        element.addEventListener('click', function() {
            removeMonkModal.classList.add('hidden');
            removeEventModal.classList.add('hidden');
        });
    });
    
    // ປິດ modal ເມື່ອກົດພື້ນທີ່ນອກ modal
    window.addEventListener('click', function(event) {
        if (event.target === removeMonkModal) {
            removeMonkModal.classList.add('hidden');
        }
        if (event.target === removeEventModal) {
            removeEventModal.classList.add('hidden');
        }
    });
});
</script>

<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>