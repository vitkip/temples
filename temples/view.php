<?php
$page_title = 'ລາຍລະອຽດວັດ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';


// แก้ไขตัวแปร can_edit ให้เป็นเฉพาะ superadmin เท่านั้น
$can_edit = ($_SESSION['user']['role'] === 'superadmin');

// Check if ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

$temple_id = (int)$_GET['id'];

// Get temple data
$stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch();

if (!$temple) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// Get monks from this temple
$monk_stmt = $pdo->prepare("SELECT * FROM monks WHERE temple_id = ? AND status = 'active' ORDER BY pansa DESC LIMIT 5");
$monk_stmt->execute([$temple_id]);
$monks = $monk_stmt->fetchAll();

// Get upcoming events at this temple
$event_stmt = $pdo->prepare("
    SELECT * FROM events 
    WHERE temple_id = ? AND event_date >= CURDATE() 
    ORDER BY event_date ASC 
    LIMIT 5
");
$event_stmt->execute([$temple_id]);
$events = $event_stmt->fetchAll();
?>

<!-- เพิ่ม link เพื่อนำเข้า monk-style.css -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<div class="page-container">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-6 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-gopuram"></i>
                    </div>
                    <?= htmlspecialchars($temple['name']) ?>
                </h1>
                <p class="text-sm text-amber-700 mt-1">
                    <?= htmlspecialchars($temple['district'] ?? '') ?>, 
                    <?= htmlspecialchars($temple['province'] ?? '') ?>
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="<?= $base_url ?>temples/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center transition">
                    <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
                </a>
                
                <?php if ($can_edit): ?>
                <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" class="btn-primary flex items-center">
                    <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
                </a>
                <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
                <a href="<?= $base_url ?>temples/delete.php?id=<?= $temple['id'] ?>" class="btn px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg flex items-center transition">
                    <i class="fas fa-trash-alt mr-2"></i> ລຶບ
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Temple Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info -->
                <div class="card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            ຂໍ້ມູນພື້ນຖານ
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                            <div>
                                <h3 class="text-sm text-gray-500">ເຈົ້າອະທິການ</h3>
                                <p class="text-gray-800"><?= htmlspecialchars($temple['abbot_name'] ?? '-') ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ວັນທີສ້າງຕັ້ງ</h3>
                                <p class="text-gray-800">
                                    <?= $temple['founding_date'] ? date('d/m/Y', strtotime($temple['founding_date'])) : '-' ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ທີ່ຢູ່</h3>
                                <p class="text-gray-800">
                                    <?= htmlspecialchars($temple['address'] ?? '-') ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ເບີໂທ</h3>
                                <p class="text-gray-800">
                                    <?= htmlspecialchars($temple['phone'] ?? '-') ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ອີເມວ</h3>
                                <p class="text-gray-800">
                                    <?= htmlspecialchars($temple['email'] ?? '-') ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ເວັບໄຊທ໌</h3>
                                <p class="text-gray-800">
                                    <?php if (!empty($temple['website'])): ?>
                                        <a href="<?= htmlspecialchars($temple['website']) ?>" target="_blank" class="text-amber-600 hover:text-amber-800">
                                            <?= htmlspecialchars($temple['website']) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ສະຖານະ</h3>
                                <p>
                                    <?php if($temple['status'] === 'active'): ?>
                                        <span class="status-badge status-active">
                                            <i class="fas fa-circle text-xs mr-1"></i>
                                            ເປີດໃຊ້ງານ
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge bg-gray-100 text-gray-600 border border-gray-200">
                                            <i class="fas fa-circle-notch text-xs mr-1"></i>
                                            ປິດໃຊ້ງານ
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm text-gray-500">ວັນທີປັບປຸງຂໍ້ມູນລ່າສຸດ</h3>
                                <p class="text-gray-800">
                                    <?= date('d/m/Y H:i', strtotime($temple['updated_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <?php if (!empty($temple['description'])): ?>
                <div class="card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-align-left"></i>
                            </div>
                            ລາຍລະອຽດ
                        </h2>
                        <div class="prose prose-amber">
                            <p class="text-gray-700"><?= nl2br(htmlspecialchars($temple['description'])) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Monks -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <div class="icon-circle">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                ພະສົງ
                            </h2>
                            <a href="<?= $base_url ?>monks/?temple_id=<?= $temple_id ?>" class="text-amber-600 hover:text-amber-800 text-sm">
                                ເບິ່ງທັງໝົດ <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        
                        <?php if (count($monks) > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach($monks as $monk): ?>
                            <div class="py-3 flex items-center">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($monk['photo'])): ?>
                                        <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="w-10 h-10 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                            <i class="fas fa-user text-amber-600"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-gray-800"><?= htmlspecialchars($monk['name']) ?></p>
                                    <p class="text-sm text-gray-500">ພັນສາ: <?= $monk['pansa'] ?? '-' ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="py-4 text-center text-gray-500">
                            ບໍ່ມີຂໍ້ມູນພະສົງ
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Events -->
                <div class="card">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <div class="icon-circle">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                ກິດຈະກໍາ
                            </h2>
                            <a href="<?= $base_url ?>events/?temple_id=<?= $temple_id ?>" class="text-amber-600 hover:text-amber-800 text-sm">
                                ເບິ່ງທັງໝົດ <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        
                        <?php if (count($events) > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach($events as $event): ?>
                            <div class="py-3">
                                <div class="flex justify-between">
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></h4>
                                    <span class="text-sm text-amber-600">
                                        <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 truncate">
                                    <?= substr(htmlspecialchars($event['description']), 0, 100) . (strlen($event['description']) > 100 ? '...' : '') ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="py-4 text-center text-gray-500">
                            ບໍ່ມີກິດຈະກໍາທີ່ຈະມາເຖິງ
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Temple Sidebar -->
            <div class="space-y-6">
                <!-- Temple Photo -->
                <div class="card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-image"></i>
                            </div>
                            ຮູບພາບ
                        </h2>
                        <?php if (!empty($temple['photo'])): ?>
                            <img src="<?= $base_url . $temple['photo'] ?>" alt="<?= htmlspecialchars($temple['name']) ?>" class="w-full h-auto rounded-lg">
                        <?php else: ?>
                            <div class="bg-gray-100 rounded-lg p-8 flex items-center justify-center">
                                <span class="text-gray-400"><i class="fas fa-temple fa-3x"></i></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Location Map -->
                <?php if (!empty($temple['latitude']) && !empty($temple['longitude'])): ?>
                <div class="card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            ທີ່ຕັ້ງ
                        </h2>
                        <div id="map" class="h-64 rounded-lg"></div>
                    </div>
                </div>
                
                <!-- Add Leaflet CSS and JS if not already in header -->
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const map = L.map('map').setView([<?= $temple['latitude'] ?>, <?= $temple['longitude'] ?>], 15);
                        
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(map);
                        
                        L.marker([<?= $temple['latitude'] ?>, <?= $temple['longitude'] ?>])
                            .addTo(map)
                            .bindPopup("<?= htmlspecialchars($temple['name']) ?>")
                            .openPopup();
                    });
                </script>
                <?php endif; ?>
                
                <!-- Contact Information -->
                <div class="card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-address-book"></i>
                            </div>
                            ຕິດຕໍ່
                        </h2>
                        
                        <div class="space-y-3">
                            <?php if (!empty($temple['phone'])): ?>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-phone text-amber-600"></i>
                                </div>
                                <p class="text-gray-800"><?= htmlspecialchars($temple['phone']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($temple['email'])): ?>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-envelope text-amber-600"></i>
                                </div>
                                <p class="text-gray-800"><?= htmlspecialchars($temple['email']) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($temple['website'])): ?>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                    <i class="fas fa-globe text-amber-600"></i>
                                </div>
                                <a href="<?= htmlspecialchars($temple['website']) ?>" target="_blank" class="text-amber-600 hover:text-amber-800">
                                    <?= htmlspecialchars($temple['website']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($temple['phone']) && empty($temple['email']) && empty($temple['website'])): ?>
                        <div class="py-4 text-center text-gray-500">
                            ບໍ່ມີຂໍ້ມູນຕິດຕໍ່
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>