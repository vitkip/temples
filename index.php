<?php
session_start();
require_once 'config/db.php';
require_once 'config/base_url.php';

// เช็คสถานะการเข้าสู่ระบบ
$logged_in = isset($_SESSION['user']);

// ถ้าผู้ใช้เข้าสู่ระบบแล้วและเข้าที่หน้าแรก ให้ redirect ไปที่ dashboard
if ($logged_in && !isset($_GET['stay'])) {
    header("Location: {$base_url}dashboard.php");
    exit;
}

// ดึงการตั้งค่าระบบจากฐานข้อมูล
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // จัดการข้อผิดพลาด
}

// ดึงสถิติจากฐานข้อมูล
$stats = [
    'temples' => 0,
    'monks' => 0,
    'events' => 0,
    'provinces' => 0
];

try {
    // จำนวนวัดทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM temples WHERE status = 'active'");
    $stats['temples'] = $stmt->fetchColumn();
    
    // จำนวนพระสงฆ์ทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM monks WHERE status = 'active'");
    $stats['monks'] = $stmt->fetchColumn();
    
    // จำนวนกิจกรรมทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $stats['events'] = $stmt->fetchColumn();
    
    // จำนวนจังหวัดที่มีวัดในระบบ
    $stmt = $pdo->query("SELECT COUNT(DISTINCT province) FROM temples WHERE status = 'active'");
    $stats['provinces'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // จัดการข้อผิดพลาด
}

// ดึงข้อมูลวัดล่าสุด
$recent_temples = [];
try {
    $stmt = $pdo->query("SELECT * FROM temples WHERE status = 'active' ORDER BY created_at DESC LIMIT 4");
    $recent_temples = $stmt->fetchAll();
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
    <title><?= htmlspecialchars($site_name) ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- นำเข้า monk-style.css -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
    
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
            background-image: url('assets/images/temple-bg.jpg');
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
            background: linear-gradient(to right, rgba(176, 133, 66, 0.8) 0%, rgba(212, 167, 98, 0.7) 100%);
        }
        
        /* Mobile-specific enhancements */
        @media (max-width: 640px) {
            .temple-card {
                margin-bottom: 1rem;
            }
            
            .hero-section {
                padding: 4rem 0;
                text-align: center;
            }
            
            .hero-section h1 {
                font-size: 2rem !important;
                line-height: 1.2;
            }
            
            .stats-card {
                margin-bottom: 0.75rem;
            }
            
            .mobile-scroll-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 1rem;
                margin: 0 -1rem;
                padding-left: 1rem;
                padding-right: 1rem;
                scroll-snap-type: x mandatory;
            }
            
            .mobile-scroll-item {
                scroll-snap-align: start;
                flex-shrink: 0;
                width: 85%;
                margin-right: 0.75rem;
            }
            
            .mobile-full-width {
                width: 100vw;
                position: relative;
                left: 50%;
                right: 50%;
                margin-left: -50vw;
                margin-right: -50vw;
            }
            
            .feature-icon {
                margin-bottom: 0.5rem !important;
            }
            
            /* Bottom navigation bar */
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
            
            /* Add padding to bottom to account for mobile navbar */
            .has-mobile-nav {
                padding-bottom: 4rem;
            }
            
            /* Improved touch targets */
            .btn, button, .card a {
                padding: 0.75rem 1rem;
            }
            
            /* Fix for charts on mobile */
            .chart-container {
                height: 250px !important;
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 has-mobile-nav">
    <!-- Mobile navigation (visible only on small screens) -->
    <nav class="mobile-navbar sm:hidden">
        <a href="<?= $base_url ?>" class="mobile-nav-item active">
            <i class="fas fa-home"></i>
            <span>ໜ້າຫຼັກ</span>
        </a>
        <a href="all-temples.php" class="mobile-nav-item">
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

    <!-- Hero Section -->
    <section class="hero-section py-16 md:py-32 relative">
        <div class="hero-overlay"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center md:text-left md:max-w-2xl">
                <h1 class="text-2xl md:text-5xl font-bold text-white leading-tight">
                    ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ພະສົງ
                </h1>
                <p class="mt-4 text-base md:text-lg text-gray-100">
                    <?= htmlspecialchars($site_description) ?>
                </p>
                <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-3 justify-center md:justify-start">
                    <?php if (!$logged_in): ?>
                        <a href="<?= $base_url ?>auth/register.php" class="w-full sm:w-auto btn-primary text-center">
                            <i class="fas fa-user-plus mr-1"></i> ເລີ່ມໃຊ້ງານເລີຍ
                        </a>
                        <a href="<?= $base_url ?>about.php" class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gray-800 bg-opacity-60 hover:bg-opacity-70 text-center">
                            <i class="fas fa-info-circle mr-1"></i> ຮຽນຮູ້ເພີ່ມເຕີມ
                        </a>
                    <?php else: ?>
                        <a href="<?= $base_url ?>dashboard.php" class="btn-primary">
                            <i class="fas fa-tachometer-alt mr-1"></i> ໄປທີ່ໜ້າຄວບຄຸມ
                        </a>
                        <a href="<?= $base_url ?>about.php" class="px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gray-800 bg-opacity-60 hover:bg-opacity-70">
                            <i class="fas fa-info-circle mr-1"></i> ຮຽນຮູ້ເພີ່ມເຕີມ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section - Convert to horizontal scroll on mobile -->
    <div class="page-container py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8 md:mb-12">
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">ສະຖິຕິໂດຍລວມ</h2>
                <p class="mt-3 max-w-2xl text-base md:text-xl text-gray-500 mx-auto">
                    ລະບົບຈັດການຂໍ້ມູນວັດຊ່ວຍໃຫ້ການບໍລິຫານຂໍ້ມູນພຣະພຸດທະສາສະໜາເປັນລະບົບ ແລະ ມີປະສິດທິພາບ.
                </p>
            </div>

            <!-- Convert to horizontally scrolling cards on mobile -->
            <div class="mobile-scroll-container sm:hidden">
                <div class="flex">
                    <!-- Temple Stats -->
                    <div class="mobile-scroll-item stats-card">
                        <div class="card p-4 text-center bg-gradient-to-br from-amber-50 to-amber-100 h-full">
                            <div class="flex justify-center mb-3">
                                <div class="icon-circle">
                                    <i class="fas fa-place-of-worship"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-bold text-amber-800"><?= number_format($stats['temples']) ?></div>
                            <div class="mt-1 text-amber-700 font-medium">ວັດທັງໝົດ</div>
                        </div>
                    </div>

                    <!-- Monks Stats -->
                    <div class="mobile-scroll-item stats-card">
                        <div class="card p-4 text-center bg-gradient-to-br from-amber-50 to-amber-100 h-full">
                            <div class="flex justify-center mb-3">
                                <div class="icon-circle">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-bold text-amber-800"><?= number_format($stats['monks']) ?></div>
                            <div class="mt-1 text-amber-700 font-medium">ພຣະສົງທັງໝົດ</div>
                        </div>
                    </div>

                    <!-- Events Stats -->
                    <div class="mobile-scroll-item stats-card">
                        <div class="card p-4 text-center bg-gradient-to-br from-amber-50 to-amber-100 h-full">
                            <div class="flex justify-center mb-3">
                                <div class="icon-circle">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-bold text-amber-800"><?= number_format($stats['events']) ?></div>
                            <div class="mt-1 text-amber-700 font-medium">ກິດຈະກຳທັງໝົດ</div>
                        </div>
                    </div>

                    <!-- Provinces Stats -->
                    <div class="mobile-scroll-item stats-card">
                        <div class="card p-4 text-center bg-gradient-to-br from-amber-50 to-amber-100 h-full">
                            <div class="flex justify-center mb-3">
                                <div class="icon-circle">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-bold text-amber-800"><?= number_format($stats['provinces']) ?></div>
                            <div class="mt-1 text-amber-700 font-medium">ແຂວງທີ່ມີວັດໃນລະບົບ</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop grid layout (hidden on mobile) -->
            <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Temple Stats -->
                <div class="card p-6 text-center bg-gradient-to-br from-amber-50 to-amber-100">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-place-of-worship"></i>
                        </div>
                    </div>
                    <div class="text-2xl sm:text-4xl font-bold text-amber-800"><?= number_format($stats['temples']) ?></div>
                    <div class="mt-2 text-amber-700 font-medium">ວັດທັງໝົດ</div>
                </div>

                <!-- Monks Stats -->
                <div class="card p-6 text-center bg-gradient-to-br from-amber-50 to-amber-100">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="text-2xl sm:text-4xl font-bold text-amber-800"><?= number_format($stats['monks']) ?></div>
                    <div class="mt-2 text-amber-700 font-medium">ພຣະສົງທັງໝົດ</div>
                </div>

                <!-- Events Stats -->
                <div class="card p-6 text-center bg-gradient-to-br from-amber-50 to-amber-100">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="text-2xl sm:text-4xl font-bold text-amber-800"><?= number_format($stats['events']) ?></div>
                    <div class="mt-2 text-amber-700 font-medium">ກິດຈະກຳທັງໝົດ</div>
                </div>

                <!-- Provinces Stats -->
                <div class="card p-6 text-center bg-gradient-to-br from-amber-50 to-amber-100">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                    <div class="text-2xl sm:text-4xl font-bold text-amber-800"><?= number_format($stats['provinces']) ?></div>
                    <div class="mt-2 text-amber-700 font-medium">ແຂວງທີ່ມີວັດໃນລະບົບ</div>
                </div>
            </div>

            <!-- Data visualization with improved mobile display -->
            <div class="mt-8 md:mt-12 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Temple distribution by province -->
                <div class="card">
                    <div class="px-4 py-4 md:px-6 md:py-5 flex justify-between items-center border-b border-amber-100">
                        <h3 class="text-md md:text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <div class="category-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            ການກະຈາຍຂອງວັດຕາມແຂວງ
                        </h3>
                    </div>
                    <div class="p-4 md:px-6 md:py-5">
                        <div class="chart-container h-64">
                            <canvas id="templesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Activities -->
                <div class="card">
                    <div class="px-4 py-4 md:px-6 md:py-5 flex justify-between items-center border-b border-amber-100">
                        <h3 class="text-md md:text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <div class="category-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            ກິດຈະກໍາປະຈໍາເດືອນ
                        </h3>
                    </div>
                    <div class="p-4 md:px-6 md:py-5">
                        <div class="chart-container h-64">
                            <canvas id="activitiesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Temples Section - Convert to scrollable cards on mobile -->
    <section class="page-container py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="header-section p-4 md:p-6 mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-place-of-worship"></i>
                    </div>
                    ວັດລ່າສຸດໃນລະບົບ
                </h2>
                <p class="mt-2 text-amber-700">
                    ຂໍ້ມູນວັດທີ່ຫາກໍ່ຖືກເພີ່ມເຂົ້າລະບົບ
                </p>
            </div>

            <!-- Horizontal scrolling temples on mobile -->
            <div class="mobile-scroll-container sm:hidden">
                <div class="flex">
                    <?php foreach($recent_temples as $temple): ?>
                    <div class="mobile-scroll-item">
                        <div class="card overflow-hidden h-full">
                            <div class="h-36 overflow-hidden">
                                <?php if($temple['photo']): ?>
                                    <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                        alt="<?= htmlspecialchars($temple['name']) ?>" 
                                        class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-amber-50">
                                        <i class="fas fa-place-of-worship text-amber-300 text-4xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-3">
                                <h3 class="text-base font-semibold text-gray-900 mb-1"><?= htmlspecialchars($temple['name']) ?></h3>
                                <div class="flex items-center text-xs text-gray-500 mb-1">
                                    <i class="fas fa-map-marker-alt mr-1 text-amber-600"></i>
                                    <?= htmlspecialchars($temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                                </div>
                                <div class="flex items-center text-xs text-gray-500 mb-1">
                                    <i class="fas fa-user mr-1 text-amber-600"></i>
                                    <?= htmlspecialchars($temple['abbot_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                                </div>
                                <div class="mt-2">
                                    <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" class="btn-primary w-full flex items-center justify-center text-xs py-2">
                                        <i class="fas fa-info-circle mr-1"></i> ເບິ່ງລາຍລະອຽດ
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if(empty($recent_temples)): ?>
                    <div class="mobile-scroll-item">
                        <div class="card p-6 text-center">
                            <i class="fas fa-temple text-amber-300 text-4xl mb-4"></i>
                            <p class="text-gray-500">ຍັງບໍ່ມີຂໍ້ມູນວັດໃນລະບົບ</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desktop grid layout (hidden on mobile) -->
            <div class="hidden sm:grid gap-6 lg:grid-cols-4 md:grid-cols-2">
                <?php foreach($recent_temples as $temple): ?>
                <div class="card overflow-hidden">
                    <div class="h-48 overflow-hidden">
                        <?php if($temple['photo']): ?>
                            <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                alt="<?= htmlspecialchars($temple['name']) ?>" 
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-amber-50">
                                <i class="fas fa-place-of-worship text-amber-300 text-4xl"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($temple['name']) ?></h3>
                        <div class="flex items-center text-sm text-gray-500 mb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-amber-600"></i>
                            <?= htmlspecialchars($temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 mb-2">
                            <i class="fas fa-user mr-2 text-amber-600"></i>
                            <?= htmlspecialchars($temple['abbot_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 mb-4">
                            <i class="fas fa-phone mr-2 text-amber-600"></i>
                            <?= htmlspecialchars($temple['phone'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
                        </div>
                        
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" class="btn-primary w-full flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i> ເບິ່ງລາຍລະອຽດ
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($recent_temples)): ?>
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-temple text-amber-300 text-5xl mb-4"></i>
                    <p class="text-gray-500 text-lg">ຍັງບໍ່ມີຂໍ້ມູນວັດໃນລະບົບ</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="mt-8 text-center">
                <a href="all-temples.php" class="btn px-6 py-3 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg inline-flex items-center">
                    <i class="fas fa-list mr-2"></i> ເບິ່ງວັດທັງໝົດ
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section - Improved for mobile -->
    <section class="page-container py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="header-section p-4 md:p-6 mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-list-check"></i>
                    </div>
                    ຄຸນສົມບັດຂອງລະບົບ
                </h2>
                <p class="mt-2 text-amber-700">
                    ລະບົບຈັດການຂໍ້ມູນວັດຊ່ວຍໃຫ້ການບໍລິຫານມີປະສິດທິພາບ ແລະ ທັນສະໄໝ
                </p>
            </div>

            <!-- Horizontal scrolling features on mobile -->
            <div class="mobile-scroll-container sm:hidden">
                <div class="flex">
                    <!-- Feature 1: Temple Management -->
                    <div class="mobile-scroll-item">
                        <div class="card p-4 h-full">
                            <div class="icon-circle mb-3 w-10 h-10 feature-icon">
                                <i class="fas fa-place-of-worship"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">ຈັດການຂໍ້ມູນວັດ</h3>
                            <p class="text-gray-600 text-sm">
                                ເກັບກຳຂໍ້ມູນວັດ ແລະ ສະຖານທີໍາຄັນທາງພຸດທະສາສະໜາຢ່າງເປັນລະບົບ.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Feature 2: Monk Database -->
                    <div class="mobile-scroll-item">
                        <div class="card p-4 h-full">
                            <div class="icon-circle mb-3 w-10 h-10 feature-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">ຖານຂໍ້ມູນພຣະສົງ</h3>
                            <p class="text-gray-600 text-sm">
                                ບັນທຶກປະຫວັດ, ການສຶກສາ, ແລະ ຂໍ້ມູນສໍາຄັນຂອງພຣະສົງ.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Feature 3: Event Management -->
                    <div class="mobile-scroll-item">
                        <div class="card p-4 h-full">
                            <div class="icon-circle mb-3 w-10 h-10 feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">ຈັດການກິດຈະກໍາ</h3>
                            <p class="text-gray-600 text-sm">
                                ວາງແຜນ ແລະ ຈັດການກິດຈະກໍາທາງສາສະໜາ ແລະ ງານບຸນຕ່າງໆ.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Additional features -->
                    <div class="mobile-scroll-item">
                        <div class="card p-4 h-full">
                            <div class="icon-circle mb-3 w-10 h-10 feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">ແຜງຄວບຄຸມ</h3>
                            <p class="text-gray-600 text-sm">
                                ເຂົ້າເຖິບຂໍ້ມູນສະຖິຕິ ແລະ ການວິເຄາະທີໍາຄັນເພື່ອການຕັດສິນໃຈ.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop grid layout (hidden on mobile) -->
            <div class="hidden sm:grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1: Temple Management -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-place-of-worship"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຈັດການຂໍ້ມູນວັດ</h3>
                    <p class="text-gray-600">
                        ເກັບກຳຂໍ້ມູນວັດ ແລະ ສະຖານທີໍາຄັນທາງພຸດທະສາສະໜາຢ່າງເປັນລະບົບ, ພ້ອມລາຍລະອຽດ ແລະ ຮູບພາບ.
                    </p>
                </div>

                <!-- Feature 2: Monk Database -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຖານຂໍ້ມູນພຣະສົງ</h3>
                    <p class="text-gray-600">
                        ບັນທຶກປະຫວັດ, ການສຶກສາ, ແລະ ຂໍ້ມູນສໍາຄັນຂອງພຣະສົງ ເພື່ອໃຊ້ໃນການບໍລິຫານ ແລະ ຕິດຕາມ.
                    </p>
                </div>

                <!-- Feature 3: Event Management -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຈັດການກິດຈະກໍາ</h3>
                    <p class="text-gray-600">
                        ວາງແຜນ ແລະ ຈັດການກິດຈະກໍາທາງສາສະໜາ, ງານບຸນ, ແລະ ພິທີກໍາຕ່າງໆ ຢ່າງເປັນລະບົບ.
                    </p>
                </div>

                <!-- Feature 4: Dashboard & Analytics -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ແຜງຄວບຄຸມ ແລະ ການວິເຄາະ</h3>
                    <p class="text-gray-600">
                        ເຂົ້າເຖິບຂໍ້ມູນສະຖິຕິ ແລະ ການວິເຄາະທີໍາຄັນເພື່ອຊ່ວຍໃນການວາງແຜນ ແລະ ຕັດສິນໃຈ.
                    </p>
                </div>

                <!-- Feature 5: Reports -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ລາຍງານ</h3>
                    <p class="text-gray-600">
                        ສ້າງລາຍງານຫຼາກຫຼາຍຮູບແບບເພື່ອສະຫຼຸບຂໍ້ມູນກ່ຽວກັບວັດ, ພຣະສົງ, ແລະ ກິດຈະກໍາຕ່າງໆ.
                    </p>
                </div>

                <!-- Feature 6: Mobile Responsive -->
                <div class="card p-6">
                    <div class="icon-circle mb-4 w-12 h-12">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ໃຊ້ງານໄດ້ທຸກອຸປະກອນ</h3>
                    <p class="text-gray-600">
                        ເຂົ້າເຖິບລະບົບໄດ້ທຸກທີ່ທຸກເວລາ ໂດຍຜ່ານຄອມພິວເຕີ, ແທັບເລັດ, ຫຼື ສະມາດໂຟນ.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section - Mobile optimized -->
    <section class="bg-gradient-to-r from-amber-700 to-amber-600 py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white sm:text-4xl text-center lg:text-left">
                <span class="block">ພ້ອມຕົ້ນແລ້ວບໍ?</span>
                <span class="block text-amber-200 text-xl md:text-2xl mt-2">ລົງທະບຽນເຂົ້າໃຊ້ງານລະບົບຟຣີ</span>
            </h2>
            <div class="mt-6 lg:mt-0 lg:flex-shrink-0 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                <a href="<?= $base_url ?>auth/register.php" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-amber-700 bg-white hover:bg-amber-50 shadow-md">
                    <i class="fas fa-user-plus mr-2"></i> ລົງທະບຽນ
                </a>
                <a href="<?= $base_url ?>auth/login.php" class="w-full sm:w-auto btn-primary inline-flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i> ເຂົ້າສູ່ລະບົບ
                </a>
            </div>
        </div>
    </section>

    <!-- Footer - Mobile optimized -->
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
                            <a href="<?= $base_url ?>temples/" class="text-base text-gray-300 hover:text-amber-200">
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

    <script>
        // Temples distribution by province chart
        async function loadTempleData() {
            try {
                const response = await fetch('<?= $base_url ?>api/stats/temples_by_province.php');
                const data = await response.json();
                
                const ctx = document.getElementById('templesChart').getContext('2d');
                const isMobile = window.innerWidth < 640;
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.map(item => item.province),
                        datasets: [{
                            data: data.map(item => item.count),
                            backgroundColor: [
                                '#D4A762', '#B08542', '#9B7C59', '#E9CDA8', 
                                '#F0E5D3', '#E8D8B8', '#C6AA7B', '#D9BA85',
                                '#CEB394', '#B6965A', '#E1C394', '#BEA575'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: isMobile ? 'bottom' : 'right',
                                labels: {
                                    boxWidth: isMobile ? 12 : 20,
                                    padding: isMobile ? 10 : 20,
                                    font: {
                                        size: isMobile ? 10 : 12
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading temple data:', error);
                fallbackTempleChart();
            }
        }

        // Fallback chart with sample data if API fails
        function fallbackTempleChart() {
            const ctx = document.getElementById('templesChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['ນະຄອນຫຼວງວຽງຈັນ', 'ຫຼວງພຣະບາງ', 'ໄຊຍະບູລີ', 'ຄໍາມ່ວນ', 'ອຸດົມໄຊ'],
                    datasets: [{
                        data: [5, 3, 2, 1, 1],
                        backgroundColor: [
                            '#D4A762', '#B08542', '#9B7C59', '#E9CDA8', '#F0E5D3'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
        }

        // Activities by month chart
        async function loadActivitiesData() {
            try {
                const response = await fetch('<?= $base_url ?>api/stats/events_by_month.php');
                const data = await response.json();

                const ctx = document.getElementById('activitiesChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(item => item.month),
                        datasets: [{
                            label: 'ກິດຈະກຳ',
                            data: data.map(item => item.count),
                            borderColor: '#D4A762',
                            backgroundColor: 'rgba(212, 167, 98, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading activities data:', error);
                fallbackActivitiesChart();
            }
        }

        // Fallback activities chart
        function fallbackActivitiesChart() {
            const months = ['ມັງກອນ', 'ກຸມພາ', 'ມີນາ', 'ເມສາ', 'ພຶດສະພາ', 'ມິຖຸນາ', 
                            'ກໍລະກົດ', 'ສິງຫາ', 'ກັນຍາ', 'ຕຸລາ', 'ພະຈິກ', 'ທັນວາ'];
            
            const ctx = document.getElementById('activitiesChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'ກິດຈະກຳ',
                        data: [3, 2, 5, 4, 6, 3, 2, 4, 5, 7, 4, 3],
                        borderColor: '#D4A762',
                        backgroundColor: 'rgba(212, 167, 98, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Load charts when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            loadTempleData();
            loadActivitiesData();
        });
        
        // Add scroll snap behavior for mobile scrolling components
        document.addEventListener('DOMContentLoaded', () => {
            // Add smooth scrolling behavior to mobile containers
            const mobileContainers = document.querySelectorAll('.mobile-scroll-container');
            mobileContainers.forEach(container => {
                let isDown = false;
                let startX;
                let scrollLeft;
                
                container.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - container.offsetLeft;
                    scrollLeft = container.scrollLeft;
                });
                
                container.addEventListener('mouseleave', () => {
                    isDown = false;
                });
                
                container.addEventListener('mouseup', () => {
                    isDown = false;
                });
                
                container.addEventListener('mousemove', (e) => {
                    if(!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - container.offsetLeft;
                    const walk = (x - startX) * 2;
                    container.scrollLeft = scrollLeft - walk;
                });
            });
        });
        
        // Adjust chart options for mobile
        function adjustChartForScreenSize() {
            const isMobile = window.innerWidth < 640;
            
            // Modify chart options if needed based on screen size
            if (templesChart && activitiesChart) {
                templesChart.options.plugins.legend.position = isMobile ? 'bottom' : 'right';
                templesChart.update();
                
                activitiesChart.options.scales.y.ticks.maxTicksLimit = isMobile ? 5 : 10;
                activitiesChart.update();
            }
        }
        
        // Call this function when window resizes
        window.addEventListener('resize', adjustChartForScreenSize);
    </script>
</body>
</html>