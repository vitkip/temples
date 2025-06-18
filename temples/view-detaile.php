<?php
// filepath: c:\xampp\htdocs\temples\temples\view-detaile.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// เช็คสถานะการเข้าสู่ระบบ (เฉพาะเพื่อแสดงปุ่มที่เหมาะสม)
$logged_in = isset($_SESSION['user']);

// Check if ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . $base_url);
    exit;
}

$temple_id = (int)$_GET['id'];

// Get temple data
$stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ? AND status = 'active'");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch();

if (!$temple) {
    header('Location: ' . $base_url);
    exit;
}

// ดึงการตั้งค่าระบบจากฐานข้อมูล (สำหรับชื่อเว็บไซต์และอื่นๆ)
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // จัดการข้อผิดพลาด
}

// กำหนดชื่อเว็บไซต์
$site_name = $settings['site_name'] ?? 'ລະບົບຈັດການວັດ';
$site_description = $settings['site_description'] ?? 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ກິດຈະກໍາ';
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#B08542">
    <title><?= htmlspecialchars($temple['name']) ?> - <?= htmlspecialchars($site_name) ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- นำเข้า monk-style.css -->
    <link rel="stylesheet" href="<?= $base_url ?>../assets/css/monk-style.css">

    <!-- Leaflet CSS (สำหรับแผนที่) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <style>
        body {
            font-family: 'Noto Sans Lao', sans-serif;
        }
        
        .temple-card {
            transition: all 0.3s ease;
        }
        
        .temple-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(176, 133, 66, 0.2);
        }
        
        .hero-section {
            background-image: url('<?= !empty($temple['photo']) ? $base_url . $temple['photo'] : 'assets/images/temple-bg.jpg' ?>');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(176, 133, 66, 0.9) 0%, rgba(212, 167, 98, 0.8) 100%);
        }

        .icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(236, 201, 154, 0.2);
            color: #B08542;
            margin-right: 10px;
        }

        .card {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(229, 231, 235, 0.5);
        }

        .category-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(236, 201, 154, 0.2);
            color: #B08542;
            margin-right: 10px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        /* Mobile-specific styling */
        @media (max-width: 640px) {
            .mobile-navbar {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                z-index: 100;
                height: 3.5rem;
            }
            
            .mobile-nav-item {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-size: 0.7rem;
                color: #666;
                padding: 0.25rem;
            }
            
            .mobile-nav-item.active {
                color: #B08542;
            }
            
            .mobile-nav-item i {
                font-size: 1.2rem;
                margin-bottom: 0.25rem;
            }
            
            .has-mobile-nav {
                padding-bottom: 4rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 has-mobile-nav">
    <!-- Mobile navigation (visible only on small screens) -->
    <nav class="mobile-navbar sm:hidden">
        <a href="<?= $base_url ?>" class="mobile-nav-item">
            <i class="fas fa-home"></i>
            <span>ໜ້າຫຼັກ</span>
        </a>
        <a href="all-temples.php" class="mobile-nav-item active">
            <i class="fas fa-place-of-worship"></i>
            <span>ວັດ</span>
        </a>
        <a href="<?= $base_url ?>events/" class="mobile-nav-item">
            <i class="fas fa-calendar-alt"></i>
            <span>ກິດຈະກໍາ</span>
        </a>
        <?php if ($logged_in): ?>
        <a href="<?= $base_url ?>dashboard.php" class="mobile-nav-item">
            <i class="fas fa-tachometer-alt"></i>
            <span>ແຜງຄວບຄຸມ</span>
        </a>
        <?php else: ?>
        <a href="<?= $base_url ?>auth/login.php" class="mobile-nav-item">
            <i class="fas fa-sign-in-alt"></i>
            <span>ເຂົ້າລະບົບ</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- Desktop navigation (hidden on mobile) -->
    <nav class="bg-white shadow-sm hidden sm:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <img class="h-8 w-auto" src="<?= $base_url ?>assets/images/logo.png" alt="<?= htmlspecialchars($site_name) ?>">
                        <span class="ml-3 text-xl font-semibold text-gray-800"><?= htmlspecialchars($site_name) ?></span>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="<?= $base_url ?>" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-home mr-1"></i> ໜ້າຫຼັກ
                    </a>
                    <a href="<?= $base_url ?>all-temples.php" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                        <i class="fas fa-place-of-worship mr-1"></i> ວັດທັງໝົດ
                    </a>
                    <?php if ($logged_in): ?>
                        <a href="<?= $base_url ?>dashboard.php" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-1"></i> ໜ້າຄວບຄຸມ
                        </a>
                        <a href="<?= $base_url ?>auth/logout.php" class="ml-4 btn-primary">
                            <i class="fas fa-sign-out-alt mr-1"></i> ອອກຈາກລະບົບ
                        </a>
                    <?php else: ?>
                        <a href="<?= $base_url ?>auth/login.php" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-in-alt mr-1"></i> ເຂົ້າສູ່ລະບົບ
                        </a>
                        <a href="<?= $base_url ?>auth/register.php" class="ml-4 btn-primary">
                            <i class="fas fa-user-plus mr-1"></i> ລົງທະບຽນ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section with temple photo -->
    <section class="hero-section py-12 md:py-24 relative">
        <div class="hero-overlay"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center md:text-left md:max-w-2xl">
                <h1 class="text-3xl md:text-5xl font-bold text-white leading-tight">
                    <?= htmlspecialchars($temple['name']) ?>
                </h1>
                <p class="mt-4 text-base md:text-lg text-gray-100">
                    <?= htmlspecialchars($temple['district'] ?? '') ?>, 
                    <?= htmlspecialchars($temple['province'] ?? '') ?>
                </p>
                <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-3 justify-center md:justify-start">
                    <a href="<?= $base_url ?>" class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gray-800 bg-opacity-60 hover:bg-opacity-70 text-center">
                        <i class="fas fa-arrow-left mr-1"></i> ກັບສູ່ໜ້າຫຼັກ
                    </a>
                    <a href="<?= $base_url ?>all-temples.php" class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-amber-700 bg-opacity-80 hover:bg-opacity-100 text-center">
                        <i class="fas fa-place-of-worship mr-1"></i> ເບິ່ງວັດອື່ນໆ
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="page-container py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
                                    <span class="text-gray-400"><i class="fas fa-place-of-worship fa-3x"></i></span>
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

    <!-- Footer -->
    <footer class="bg-gray-800 pt-10 pb-16 sm:pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">ກ່ຽວກັບພວກເຮົາ</h3>
                    <p class="mt-4 text-base text-gray-300">
                        <?= htmlspecialchars($site_description) ?>
                    </p>
                    <div class="mt-4 flex space-x-6">
                        <a href="#" class="text-gray-400 hover:text-amber-300">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-amber-300">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-amber-300">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">ລິ້ງຄ໌ດ່ວນ</h3>
                    <ul class="mt-4 space-y-4">
                        <li>
                            <a href="<?= $base_url ?>about.php" class="text-base text-gray-300 hover:text-amber-200">
                                ກ່ຽວກັບລະບົບ
                            </a>
                        </li>
                        <li>
                            <a href="<?= $base_url ?>all-temples.php" class="text-base text-gray-300 hover:text-amber-200">
                                ລາຍຊື່ວັດ
                            </a>
                        </li>
                        <li>
                            <a href="<?= $base_url ?>events/" class="text-base text-gray-300 hover:text-amber-200">
                                ກິດຈະກໍາ
                            </a>
                        </li>
                        <li>
                            <a href="<?= $base_url ?>contact.php" class="text-base text-gray-300 hover:text-amber-200">
                                ຕິດຕໍ່ພວກເຮົາ
                            </a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">ຕິດຕໍ່</h3>
                    <ul class="mt-4 space-y-4">
                        <li class="flex">
                            <i class="fas fa-map-marker-alt text-amber-500 mt-1 mr-2"></i>
                            <span class="text-gray-300">
                                ນະຄອນຫຼວງວຽງຈັນ, ສປປລາວ
                            </span>
                        </li>
                        <li class="flex">
                            <i class="fas fa-phone text-amber-500 mt-1 mr-2"></i>
                            <span class="text-gray-300">
                                <?= htmlspecialchars($settings['contact_phone'] ?? '+856 21 XXXXXX') ?>
                            </span>
                        </li>
                        <li class="flex">
                            <i class="fas fa-envelope text-amber-500 mt-1 mr-2"></i>
                            <span class="text-gray-300">
                                <?= htmlspecialchars($settings['admin_email'] ?? 'contact@example.com') ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-10 border-t border-gray-700 pt-6">
                <p class="text-sm text-gray-400 text-center">
                    <?= htmlspecialchars($settings['footer_text'] ?? '© ' . date('Y') . ' ລະບົບຈັດການຂໍ້ມູນວັດ . ສະຫງວນລິຂະສິດ.') ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- Leaflet JS for map -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <?php if (!empty($temple['latitude']) && !empty($temple['longitude'])): ?>
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
</body>
</html>