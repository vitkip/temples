<?php
session_start();
require_once 'config/db.php';
require_once 'config/base_url.php';

// Track visitor
require_once 'includes/track_visitor.php';

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
    error_log('Settings error: ' . $e->getMessage());
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
    $total_temples = $stats['temples']; // ใช้ค่าเดียวกันแทนการ query ซ้ำ
    
    // จำนวนพระสงฆ์ทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM monks WHERE status = 'active'");
    $stats['monks'] = $stmt->fetchColumn();
    
    // จำนวนกิจกรรมทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
    $stats['events'] = $stmt->fetchColumn();
    
    // จำนวนจังหวัดที่มีวัดในระบบ
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT t.province_id) 
        FROM temples t 
        JOIN provinces p ON t.province_id = p.province_id
        WHERE t.status = 'active'
    ");
    $stats['provinces'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Stats error: ' . $e->getMessage());
}

// ดึงข้อมูลวัดล่าสุดพร้อมข้อมูลแขวง/เมือง
$recent_temples = [];
try {
    $stmt = $pdo->query("
        SELECT 
            t.*, 
            d.district_name, 
            p.province_name 
        FROM temples t 
        LEFT JOIN districts d ON t.district_id = d.district_id
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE t.status = 'active' 
        ORDER BY t.created_at DESC 
        LIMIT 6
    ");
    $recent_temples = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Recent temples error: ' . $e->getMessage());
}

// ดึงกิจกรรมที่จะมาถึงเร็วๆ นี้
$upcoming_events = [];
try {
    $stmt = $pdo->query("
        SELECT 
            e.*, 
            t.name as temple_name,
            p.province_name
        FROM events e
        LEFT JOIN temples t ON e.temple_id = t.id
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $upcoming_events = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Upcoming events error: ' . $e->getMessage());
}

// กำหนดชื่อเว็บไซต์
$site_name = $settings['site_name'] ?? 'ລະບົບຈັດການວັດ';
$site_description = $settings['site_description'] ?? 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ກິດຈະກໍາ';
// นับจำนวนวัดทั้งหมด
$total_temples = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM temples WHERE status = 'active'");
    $total_temples = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Count temples error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#B08542">
    <meta name="description" content="<?= htmlspecialchars($site_description) ?>">
    <meta name="description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta name="keywords" content="ວັດ, ລະບົບຈັດການວັດ, ພຣະສົງ, ພຣະສົງລາວ ກິດຈະກໍາທາງສາສນາ">
    <meta name="robots" content="index, follow">
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="laotemples - ລະບົບຈັດການຂໍ້ມູນວັດ">
    <meta property="og:description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta property="og:image" content="https://laotemples.com/assets/images/og-image.jpg">
    <meta property="og:url" content="https://laotemples.com">
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.png" type="image/x-icon">
    
    <title><?= htmlspecialchars($site_name) ?></title>
    
    <!-- Preload critical fonts -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap"></noscript>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/index.css">
    
</head>
<body class="bg-gray-50 mobile-safe-area">
    <!-- Mobile Navigation -->
    <nav class="mobile-nav sm:hidden">
        <a href="<?= $base_url ?>" class="mobile-nav-item active">
            <i class="fas fa-home"></i>
            <span>ໜ້າຫຼັກ</span>
        </a>
        <a href="<?= $base_url ?>all-temples.php" class="mobile-nav-item">
            <i class="fas fa-place-of-worship"></i>
            <span>ວັດ</span>
        </a>
      <a href="<?= $base_url ?>auth/register.php" class="mobile-nav-item">
            <i class="fas fa-user-plus"></i>
            <span>ລົງທະບຽນ</span>
        </a>
        <?php if ($logged_in): ?>
        <a href="<?= $base_url ?>dashboard.php" class="mobile-nav-item">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <?php else: ?>
        <a href="<?= $base_url ?>auth/" class="mobile-nav-item">
            <i class="fas fa-sign-in-alt"></i>
            <span>ເຂົ້າລະບົບ</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- Desktop Navigation -->
    <nav class="bg-white/95 backdrop-blur-sm shadow-sm sticky top-0 z-50 hidden sm:block">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-place-of-worship text-white text-lg"></i>
                        </div>
                        <span class="ml-3 text-xl font-semibold text-gray-800"><?= htmlspecialchars($site_name) ?></span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?= $base_url ?>all-temples.php" class="text-gray-600 hover:text-amber-600 px-3 py-2 rounded-md text-sm font-medium transition">
                        <i class="fas fa-place-of-worship mr-1"></i> ວັດ
                    </a>
                    
                    <?php if ($logged_in): ?>
                        <a href="<?= $base_url ?>dashboard.php" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                        </a>
                        <a href="<?= $base_url ?>auth/logout.php" class="btn-primary">
                            <i class="fas fa-sign-out-alt mr-1"></i> ອອກຈາກລະບົບ
                        </a>
                    <?php else: ?>
                        <a href="<?= $base_url ?>auth/" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-in-alt mr-1"></i> ເຂົ້າສູ່ລະບົບ
                        </a>
                        <a href="<?= $base_url ?>auth/register.php" class="btn-primary">
                            <i class="fas fa-user-plus mr-1"></i> ລົງທະບຽນ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section py-12 md:py-20 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center">
                <h1 class="hero-title text-3xl md:text-5xl font-bold text-white leading-tight fade-in-up">
                    ລະບົບຈັດການຂໍ້ມູນວັດ
                </h1>
                <p class="hero-subtitle text-lg md:text-xl text-gray-100 mt-4 max-w-3xl mx-auto fade-in-up" style="animation-delay: 0.2s">
                    <?= htmlspecialchars($site_description) ?>
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center fade-in-up" style="animation-delay: 0.4s">
                    <?php if (!$logged_in): ?>
                        <a href="<?= $base_url ?>auth/register.php" class="btn-primary inline-flex items-center justify-center px-6 py-3">
                            <i class="fas fa-user-plus mr-2"></i> ເລີ່ມໃຊ້ງານ
                        </a>
                        <a href="<?= $base_url ?>all-temples.php" class="px-6 py-3 border-2 border-white text-white hover:bg-white hover:text-amber-700 rounded-lg font-medium transition inline-flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i> ຄົ້ນຫາວັດ
                        </a>
                    <?php else: ?>
                        <a href="<?= $base_url ?>dashboard.php" class="btn-primary inline-flex items-center justify-center px-6 py-3">
                            <i class="fas fa-tachometer-alt mr-2"></i> ໄປ Dashboard
                        </a>
                        <a href="<?= $base_url ?>temples/add.php" class="px-6 py-3 border-2 border-white text-white hover:bg-white hover:text-amber-700 rounded-lg font-medium transition inline-flex items-center justify-center">
                            <i class="fas fa-plus mr-2"></i> ເພີ່ມວັດໃໝ່
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-8 md:py-12 -mt-16 relative z-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Mobile: Horizontal scroll -->
            <div class="stats-mobile sm:hidden">
                <div class="stat-card-mobile card p-4 text-center bg-white shadow-lg">
                    <div class="text-2xl font-bold text-amber-600"><?= number_format($stats['temples']) ?></div>
                    <div class="text-sm text-gray-600 mt-1">ວັດທັງໝົດ</div>
                    <i class="fas fa-place-of-worship text-amber-400 mt-2"></i>
                </div>
                <div class="stat-card-mobile card p-4 text-center bg-white shadow-lg">
                    <div class="text-2xl font-bold text-amber-600"><?= number_format($stats['monks']) ?></div>
                    <div class="text-sm text-gray-600 mt-1">ພຣະສົງ</div>
                    <i class="fas fa-user text-amber-400 mt-2"></i>
                </div>
                <div class="stat-card-mobile card p-4 text-center bg-white shadow-lg">
                    <div class="text-2xl font-bold text-amber-600"><?= number_format($stats['events']) ?></div>
                    <div class="text-sm text-gray-600 mt-1">ກິດຈະກໍາ</div>
                    <i class="fas fa-calendar-alt text-amber-400 mt-2"></i>
                </div>
                <div class="stat-card-mobile card p-4 text-center bg-white shadow-lg">
                    <div class="text-2xl font-bold text-amber-600"><?= number_format($stats['provinces']) ?></div>
                    <div class="text-sm text-gray-600 mt-1">ແຂວງ</div>
                    <i class="fas fa-map-marker-alt text-amber-400 mt-2"></i>
                </div>
            </div>

            <!-- Desktop: Grid layout -->
            <div class="hidden sm:grid grid-cols-2 md:grid-cols-4 gap-6">
                <div class="card p-6 text-center bg-white shadow-lg">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-place-of-worship"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-amber-600"><?= number_format($stats['temples']) ?></div>
                    <div class="text-gray-600 mt-2">ວັດທັງໝົດ</div>
                </div>
                <div class="card p-6 text-center bg-white shadow-lg">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-amber-600"><?= number_format($stats['monks']) ?></div>
                    <div class="text-gray-600 mt-2">ພຣະສົງທັງໝົດ</div>
                </div>
                <div class="card p-6 text-center bg-white shadow-lg">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-amber-600"><?= number_format($stats['events']) ?></div>
                    <div class="text-gray-600 mt-2">ກິດຈະກໍາທີ່ຈະມາ</div>
                </div>
                <div class="card p-6 text-center bg-white shadow-lg">
                    <div class="flex justify-center mb-4">
                        <div class="icon-circle">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                    <div class="text-3xl font-bold text-amber-600"><?= number_format($stats['provinces']) ?></div>
                    <div class="text-gray-600 mt-2">ແຂວງທີ່ມີວັດ</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Charts Section -->
    <section class="py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Temple Distribution Chart -->
                <div class="card bg-white shadow-lg">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="category-icon mr-3">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            ການກະຈາຍວັດຕາມແຂວງ
                        </h3>
                        <!-- แสดงสถานะข้อมูล -->
                        <div id="temple-chart-status" class="hidden mt-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                ຂໍ້ມູນຕົວຢ່າງ
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="chart-mobile h-64">
                            <canvas id="templesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Website Visitor Statistics Chart -->
                <div class="card bg-white shadow-lg">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <div class="category-icon mr-3">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            ສະຖິຕິຜູ້ເຂົ້າຊົມເວັບໄຊທ໌
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">ຈຳນວນຜູ້ເຂົ້າຊົມປະຈຳເດືອນ</p>
                        <!-- แสดงสถานะข้อมูล -->
                        <div id="visitor-chart-status" class="hidden mt-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                ຂໍ້ມູນຕົວຢ່າງ
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="chart-mobile h-64">
                            <canvas id="visitorChart"></canvas>
                        </div>
                        <!-- Visitor stats summary -->
                        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <div class="text-lg font-bold text-blue-600" id="todayVisitors">-</div>
                                <div class="text-xs text-gray-600">ມື້ນີ້</div>
                            </div>
                            <div class="bg-green-50 p-3 rounded-lg">
                                <div class="text-lg font-bold text-green-600" id="weekVisitors">-</div>
                                <div class="text-xs text-gray-600">ອາທິດນີ້</div>
                            </div>
                            <div class="bg-purple-50 p-3 rounded-lg">
                                <div class="text-lg font-bold text-purple-600" id="monthVisitors">-</div>
                                <div class="text-xs text-gray-600">ເດືອນນີ້</div>
                            </div>
                            <div class="bg-orange-50 p-3 rounded-lg">
                                <div class="text-lg font-bold text-orange-600" id="totalVisitors">-</div>
                                <div class="text-xs text-gray-600">ທັງໝົດ</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Recent Temples Section -->
    <section class="py-16 md:py-24 gradient-bg relative overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 left-10 w-20 h-20 bg-white rounded-full floating-animation"></div>
            <div class="absolute top-32 right-20 w-16 h-16 bg-white rounded-full floating-animation" style="animation-delay: -2s;"></div>
            <div class="absolute bottom-20 left-1/4 w-12 h-12 bg-white rounded-full floating-animation" style="animation-delay: -4s;"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center mb-16 fade-in">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white bg-opacity-20 rounded-full mb-6 icon-bounce">
                    <i class="fas fa-place-of-worship text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl md:text-5xl font-bold text-white mb-4 drop-shadow-lg">
                    ວັດລ່າສຸດໃນລະບົບ
                </h2>
                <p class="text-white text-lg md:text-xl opacity-90 max-w-2xl mx-auto">
                    ຂໍ້ມູນວັດທີ່ຫາກໍ່ຖືກເພີ່ມເຂົ້າລະບົບ ພ້ອມລາຍລະອຽດທີ່ຄົບຖ້ວນ
                </p>
                <div class="mt-6 text-white/80">
                    <span class="inline-flex items-center bg-white/20 rounded-full px-4 py-2">
                        <i class="fas fa-list-ul mr-2"></i>
                        ທັງໝົດ <?= number_format($total_temples) ?> ວັດ
                    </span>
                </div>
            </div>

            <?php if (!empty($recent_temples)): ?>
                <!-- Desktop Grid Layout -->
                <div class="hidden sm:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php 
                    $stagger_classes = ['stagger-1', 'stagger-2', 'stagger-3', 'stagger-4', 'stagger-5', 'stagger-6'];
                    foreach($recent_temples as $index => $temple): 
                        $stagger_class = $stagger_classes[$index % 6];
                    ?>
                    <div class="temple-card glass-card rounded-2xl overflow-hidden fade-in <?= $stagger_class ?>">
                        <div class="h-52 overflow-hidden relative">
                            <?php if(!empty($temple['photo']) && file_exists($temple['photo'])): ?>
                                <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                     alt="<?= htmlspecialchars($temple['name']) ?>" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-100 to-amber-200">
                                    <i class="fas fa-place-of-worship text-amber-400 text-5xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4 bg-white bg-opacity-90 px-3 py-1 rounded-full text-xs font-medium text-amber-700">
                                ໃໝ່
                            </div>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4"><?= htmlspecialchars($temple['name']) ?></h3>
                            <div class="space-y-3 mb-6">
                                <div class="text-sm text-gray-600 flex items-center">
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-amber-600 text-xs"></i>
                                    </div>
                                    <span>
                                        <?= htmlspecialchars($temple['district_name'] ?? 'ບໍ່ລະບຸເມືອງ') ?>
                                        <?= !empty($temple['province_name']) ? ', ' . htmlspecialchars($temple['province_name']) : '' ?>
                                    </span>
                                </div>
                                <?php if(!empty($temple['abbot_name'])): ?>
                                <div class="text-sm text-gray-600 flex items-center">
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-amber-600 text-xs"></i>
                                    </div>
                                    <span><?= htmlspecialchars($temple['abbot_name']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($temple['phone'])): ?>
                                <div class="text-sm text-gray-600 flex items-center">
                                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-phone text-amber-600 text-xs"></i>
                                    </div>
                                    <span><?= htmlspecialchars($temple['phone']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                               class="btn-modern w-full text-white font-medium py-3 px-6 rounded-xl transition-all duration-300 block text-center">
                                <i class="fas fa-eye mr-2"></i> ເບິ່ງລາຍລະອຽດ
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mobile Scroll View -->
                <div class="temples-mobile sm:hidden">
                    <?php foreach($recent_temples as $temple): ?>
                    <div class="temple-card-mobile glass-card rounded-2xl overflow-hidden">
                        <div class="h-40 overflow-hidden relative">
                            <?php if(!empty($temple['photo']) && file_exists($temple['photo'])): ?>
                                <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                     alt="<?= htmlspecialchars($temple['name']) ?>" 
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-100 to-amber-200">
                                    <i class="fas fa-place-of-worship text-amber-400 text-4xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute top-3 right-3 bg-white bg-opacity-90 px-2 py-1 rounded-full text-xs font-medium text-amber-700">
                                ໃໝ່
                            </div>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900 mb-2 truncate"><?= htmlspecialchars($temple['name']) ?></h3>
                            <div class="text-sm text-gray-600 mb-1 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-amber-500"></i>
                                <span class="truncate">
                                    <?= htmlspecialchars($temple['district_name'] ?? 'ບໍ່ລະບຸເມືອງ') ?>
                                    <?= !empty($temple['province_name']) ? ', ' . htmlspecialchars($temple['province_name']) : '' ?>
                                </span>
                            </div>
                            <?php if(!empty($temple['abbot_name'])): ?>
                            <div class="text-sm text-gray-600 mb-3 flex items-center">
                                <i class="fas fa-user mr-2 text-amber-500"></i>
                                <span class="truncate"><?= htmlspecialchars($temple['abbot_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                               class="btn-modern w-full text-center text-white font-medium py-2 text-sm rounded-lg block">
                                <i class="fas fa-eye mr-1"></i> ເບິ່ງລາຍລະອຽດ
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Data State -->
                <div class="text-center py-16">
                    <div class="glass-card rounded-2xl p-12 max-w-md mx-auto">
                        <i class="fas fa-place-of-worship text-amber-400 text-6xl mb-6"></i>
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">ຍັງບໍ່ມີຂໍ້ມູນວັດ</h3>
                        <p class="text-gray-600 mb-6">ຂໍ້ມູນວັດຍັງບໍ່ໄດ້ຖືກເພີ່ມເຂົ້າລະບົບ</p>
                        <?php if ($logged_in): ?>
                        <a href="<?= $base_url ?>temples/add.php" 
                           class="btn-modern text-white font-medium py-3 px-6 rounded-xl transition-all duration-300 inline-flex items-center">
                            <i class="fas fa-plus mr-2"></i> ເພີ່ມວັດໃໝ່
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View all button -->
            <div class="mt-12 text-center fade-in">
                <a href="<?= $base_url ?>all-temples.php" 
                   class="glass-card px-8 py-4 text-amber-800 font-medium rounded-2xl hover:bg-white hover:bg-opacity-100 transition-all duration-300 inline-flex items-center shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-list mr-3"></i> 
                    <span>ເບິ່ງວັດທັງໝົດ (<?= number_format($total_temples) ?> ວັດ)</span>
                    <i class="fas fa-arrow-right ml-3 text-sm"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-8 md:py-12 bg-gradient-to-br from-amber-50 to-orange-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800">ຄຸນສົມບັດຂອງລະບົບ</h2>
                <p class="text-gray-600 mt-2 max-w-2xl mx-auto">
                    ລະບົບຈັດການຂໍ້ມູນວັດທີ່ທັນສະໄໝ ແລະ ມີປະສິດທິພາບ
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-place-of-worship"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຈັດການຂໍ້ມູນວັດ</h3>
                    <p class="text-gray-600">
                        ເກັບກຳຂໍ້ມູນວັດຢ່າງເປັນລະບົບ ພ້ອມລາຍລະອຽດ ແລະ ຮູບພາບ
                    </p>
                </div>

                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຖານຂໍ້ມູນພຣະສົງ</h3>
                    <p class="text-gray-600">
                        ບັນທຶກປະຫວັດ ການສຶກສາ ແລະ ຂໍ້ມູນສໍາຄັນຂອງພຣະສົງ
                    </p>
                </div>

                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຈັດການກິດຈະກໍາ</h3>
                    <p class="text-gray-600">
                        ວາງແຜນ ແລະ ຈັດການກິດຈະກໍາທາງສາສະໜາຢ່າງເປັນລະບົບ
                    </p>
                </div>

                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ແຜງຄວບຄຸມ</h3>
                    <p class="text-gray-600">
                        ເຂົ້າເຖິບຂໍ້ມູນສະຖິຕິ ແລະ ການວິເຄາະທີໍາຄັນ
                    </p>
                </div>

                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ໃຊ້ງານໄດ້ທຸກອຸປະກອນ</h3>
                    <p class="text-gray-600">
                        ເຂົ້າເຖິບລະບົບໄດ້ທຸກທີ່ທຸກເວລາ ຜ່ານມືຖື ແລະ ຄອມພິວເຕີ
                    </p>
                </div>

                <div class="card bg-white shadow-lg p-6 text-center">
                    <div class="icon-circle mx-auto mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">ຄວາມປອດໄພສູງ</h3>
                    <p class="text-gray-600">
                        ລະບົບຄຸ້ມຄອງຂໍ້ມູນທີ່ປອດໄພ ແລະ ການຈັດການສິດນຳໃຊ້
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-gradient-to-r from-red-600 to-red-700 py-12 md:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
                ລົງທະບຽນໃຊ້ງານ ລະບົບບໍລິຫານຈັດການ ພຣະສົງພາຍໃນວັດ
            </h2>
            <p class="text-amber-100 text-lg mb-8 max-w-2xl mx-auto">
                ລົງທະບຽນໃຊ້ງານຟຣີ ຕິດຕໍ່ລົງທະບຽນ
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= htmlspecialchars($settings['whatsapp_url'] ?? '#') ?>" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-red-700 bg-white hover:bg-red-50 shadow-lg transition">
                    <i class="fab fa-youtube text-2xl"></i> ລົງທະບຽນຟຣີ
                </a>
                <a href="<?= htmlspecialchars($settings['whatsapp_url'] ?? '#') ?>" target="_blank" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-green-700 bg-white hover:bg-green-50 shadow-lg transition">
                    <i class="fab fa-whatsapp text-2xl"></i> ຕິດຕໍ່ລົງທະບຽນ WhatsApp
                </a>
                <a href="<?= $base_url ?>all-temples.php" class="inline-flex items-center justify-center px-6 py-3 border-2 border-white text-base font-medium rounded-lg text-white hover:bg-white hover:text-red-700 transition">
                    <i class="fas fa-search mr-2"></i> ສຳຫຼວດວັດ
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-place-of-worship text-white"></i>
                        </div>
                        <span class="ml-3 text-xl font-semibold"><?= htmlspecialchars($site_name) ?></span>
                    </div>
                    <p class="text-gray-300 mb-4">
                        <?= htmlspecialchars($site_description) ?>
                    </p>
                      <div class="flex space-x-4">
                        <a href="<?= htmlspecialchars($settings['facebook_url'] ?? '#') ?>" target="_blank" class="text-gray-400 hover:text-amber-400 transition">
                            <i class="fab fa-facebook-f text-2xl"></i>
                        </a>
                        <a href="<?= htmlspecialchars($settings['whatsapp_url'] ?? '#') ?>" target="_blank" class="text-gray-400 hover:text-amber-400 transition">
                            <i class="fab fa-whatsapp text-2xl"></i>
                        </a>
                        <a href="<?= htmlspecialchars($settings['youtube_url'] ?? '#') ?>" target="_blank" class="text-gray-400 hover:text-amber-400 transition">
                            <i class="fab fa-youtube text-2xl"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">ລິ້ງຄ໌ດ່ວນ</h3>
                    <ul class="space-y-2">
                        <li><a href="<?= $base_url ?>all-temples.php" class="text-gray-300 hover:text-amber-400 transition">ລາຍຊື່ວັດ</a></li>
                        <li><a href="<?= $base_url ?>./auth/register.php" class="text-gray-300 hover:text-amber-400 transition">ລົງທະບຽນໃຊ້ງານ</a></li>
                        <li><a href="<?= $base_url ?>about.php" class="text-gray-300 hover:text-amber-400 transition">ກ່ຽວກັບລະບົບ</a></li>
                        <li><a href="<?= $base_url ?>contact.php" class="text-gray-300 hover:text-amber-400 transition">ຕິດຕໍ່ພວກເຮົາ</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">ຕິດຕໍ່</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2 text-amber-400"></i>
                            ນະຄອນຫຼວງວຽງຈັນ, ສປປລາວ
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-2 text-amber-400"></i>
                            <?= htmlspecialchars($settings['contact_phone'] ?? '+856 21 XXXXXX') ?>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2 text-amber-400"></i>
                            <?= htmlspecialchars($settings['admin_email'] ?? 'contact@example.com') ?>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <?= htmlspecialchars($settings['footer_text'] ?? '© ' . date('Y') . ' ລະບົບຈັດການຂໍ້ມູນວັດ . ສະຫງວນລິຂະສິດ.') ?>
            </div>
        </div>
    </footer>

    <script>
        // Debug function to check API status
        async function checkAllAPIs() {
            console.log('🔍 Checking All APIs Status...');
            
            try {
                // Test temple distribution API
                const templeResponse = await fetch('<?= $base_url ?>api/temple_distribution.php');
                console.log('🏛️ temple_distribution.php:', templeResponse.ok ? '✅ OK' : '❌ Error', templeResponse.status);
                
                // Test visitor_summary.php
                const summaryResponse = await fetch('<?= $base_url ?>api/visitor_summary.php');
                console.log('📊 visitor_summary.php:', summaryResponse.ok ? '✅ OK' : '❌ Error', summaryResponse.status);
                
                // Test visitor_stats.php  
                const statsResponse = await fetch('<?= $base_url ?>api/visitor_stats.php');
                console.log('📈 visitor_stats.php:', statsResponse.ok ? '✅ OK' : '❌ Error', statsResponse.status);
                
                // Test legacy temple_stats.php (if exists)
                try {
                    const legacyResponse = await fetch('<?= $base_url ?>api/temple_stats.php');
                    console.log('🏛️ temple_stats.php (legacy):', legacyResponse.ok ? '✅ OK' : '❌ Error', legacyResponse.status);
                } catch (e) {
                    console.log('🏛️ temple_stats.php (legacy): ❌ Not Found');
                }
                
            } catch (error) {
                console.error('❌ API Check Error:', error);
            }
        }

        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Check all APIs first (for debugging)
            checkAllAPIs();
            
            loadTempleChart();
            loadVisitorChart();
            
            // Observer for animation effects
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0% 0% -10% 0%'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if(entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.temple-card').forEach(card => {
                observer.observe(card);
            });

            // Mobile smooth scroll
            document.querySelectorAll('.temples-mobile, .mobile-scroll').forEach(element => {
                let isDown = false;
                let startX;
                let scrollLeft;

                element.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - element.offsetLeft;
                    scrollLeft = element.scrollLeft;
                });

                element.addEventListener('mouseleave', () => {
                    isDown = false;
                });

                element.addEventListener('mouseup', () => {
                    isDown = false;
                });

                element.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - element.offsetLeft;
                    const walk = (x - startX) * 2;
                    element.scrollLeft = scrollLeft - walk;
                });
            });
            
            // Mobile nav active state
            document.querySelectorAll('.mobile-nav-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.mobile-nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });

        // Temple distribution chart
        async function loadTempleChart() {
            try {
                console.log('🏛️ Loading temple distribution chart...');
                
                const response = await fetch('<?= $base_url ?>api/temple_distribution.php');
                console.log('📡 Temple distribution API response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('📊 Temple distribution data:', result);
                
                if (!result || !result.success || !result.data || result.data.length === 0) {
                    console.log('⚠️ No temple distribution data available, using fallback');
                    fallbackTempleChart();
                    return;
                }
                
                const data = result.data;
                const ctx = document.getElementById('templesChart').getContext('2d');
                const isMobile = window.innerWidth < 640;
                
                // สีสำหรับกราฟ (รองรับได้หลายแขวง)
                const colors = [
                    '#D4A762', '#B08542', '#9B7C59', '#E9CDA8', 
                    '#F0E5D3', '#E8D8B8', '#C6AA7B', '#D9BA85',
                    '#A67C4A', '#C19A6B', '#E6D5B8', '#F2E9D3',
                    '#8B6F47', '#B8956D', '#DCC5A0', '#F5EFE0'
                ];
                
                console.log('✅ Creating temple distribution chart with real data');
                console.log(`📈 Showing ${data.length} provinces with ${result.summary?.total_temples || 0} total temples`);
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.map(item => item.province || item.label || 'ບໍ່ລະບຸ'),
                        datasets: [{
                            data: data.map(item => parseInt(item.count) || 0),
                            backgroundColor: colors.slice(0, data.length),
                            borderWidth: 2,
                            borderColor: '#fff',
                            hoverBorderWidth: 3,
                            hoverBorderColor: '#333'
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
                                    padding: isMobile ? 8 : 15,
                                    font: {
                                        size: isMobile ? 10 : 12,
                                        family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                    },
                                    usePointStyle: true,
                                    generateLabels: function(chart) {
                                        const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                        const labels = original.call(this, chart);
                                        
                                        // เพิ่มจำนวนวัดในป้ายกำกับ
                                        labels.forEach((label, index) => {
                                            const count = data[index]?.count || 0;
                                            label.text = `${label.text} (${count})`;
                                        });
                                        
                                        return labels;
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#D4A762',
                                borderWidth: 1,
                                titleFont: {
                                    family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif",
                                    size: 14
                                },
                                bodyFont: {
                                    family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif",
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return `${label}: ${value} ວັດ (${percentage}%)`;
                                    },
                                    afterLabel: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        return `สัดส่วน: ${((context.parsed / total) * 100).toFixed(1)}% ของทั้งหมด`;
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true,
                            duration: 1000
                        }
                    }
                });
                
                // แสดงข้อมูลสรุปใน console
                if (result.summary) {
                    console.log(`📊 Summary: ${result.summary.total_temples} temples across ${result.summary.total_provinces} provinces`);
                }
                
            } catch (error) {
                console.error('❌ Error loading temple distribution chart:', error.message);
                console.log('⚠️ Using fallback chart with sample data');
                fallbackTempleChart();
            }
        }

        // Visitor statistics chart
        async function loadVisitorChart() {
            try {
                console.log('📈 Loading visitor chart...');
                
                // First load visitor stats summary
                loadVisitorStats();
                
                const response = await fetch('<?= $base_url ?>api/visitor_stats.php');
                console.log('📡 Visitor stats API response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📊 Visitor chart data:', data);

                // ตรวจสอบว่ามีข้อมูลหรือไม่
                if (!data || !Array.isArray(data) || data.length === 0) {
                    console.log('⚠️ No visitor chart data available, using fallback');
                    fallbackVisitorChart();
                    return;
                }

                const ctx = document.getElementById('visitorChart').getContext('2d');
                const isMobile = window.innerWidth < 640;
                
                console.log('✅ Creating visitor chart with real data');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(item => item.date || item.month),
                        datasets: [{
                            label: 'ຜູ້ເຂົ້າຊົມ',
                            data: data.map(item => parseInt(item.visitors) || 0),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#1D4ED8',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: isMobile ? 3 : 4,
                            pointHoverRadius: isMobile ? 5 : 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    font: {
                                        size: isMobile ? 10 : 12
                                    },
                                    callback: function(value) {
                                        return value + ' ຄົນ';
                                    }
                                },
                                grid: {
                                    color: 'rgba(0,0,0,0.05)'
                                }
                            },
                            x: {
                                ticks: {
                                    font: {
                                        size: isMobile ? 9 : 11
                                    },
                                    maxTicksLimit: isMobile ? 6 : 10
                                },
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: '#3B82F6',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        return `ຜູ້ເຂົ້າຊົມ: ${context.parsed.y} ຄົນ`;
                                    }
                                }
                            }
                        },
                        elements: {
                            line: {
                                borderWidth: 3
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('❌ Error loading visitor chart:', error.message);
                console.log('⚠️ Using fallback chart with sample data');
                fallbackVisitorChart();
            }
        }

        // Load visitor statistics summary
        async function loadVisitorStats() {
            try {
                console.log('🔍 Loading visitor summary stats...');
                const response = await fetch('<?= $base_url ?>api/visitor_summary.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📊 Visitor summary data:', data);
                
                if (data && data.success) {
                    // แสดงข้อมูลจริงจาก API
                    document.getElementById('todayVisitors').textContent = data.today || '0';
                    document.getElementById('weekVisitors').textContent = data.week || '0';
                    document.getElementById('monthVisitors').textContent = data.month || '0';
                    document.getElementById('totalVisitors').textContent = data.total || '0';
                    console.log('✅ Visitor stats loaded successfully');
                } else {
                    throw new Error('Invalid API response format');
                }
            } catch (error) {
                console.error('❌ Error loading visitor stats:', error);
                console.log('⚠️ Using fallback visitor summary data');
                
                // ใช้ข้อมูลสำรองคงที่
                document.getElementById('todayVisitors').textContent = '23';
                document.getElementById('weekVisitors').textContent = '162';
                document.getElementById('monthVisitors').textContent = '720';
                document.getElementById('totalVisitors').textContent = '1,234';
                
                // แสดงข้อความแจ้งเตือนใน console
                console.log('📝 Note: Displaying sample data due to API error');
            }
        }

        // Fallback charts
        function fallbackTempleChart() {
            const ctx = document.getElementById('templesChart').getContext('2d');
            const isMobile = window.innerWidth < 640;
            
            // ข้อมูลตัวอย่างที่สมจริงมากขึ้น
            const sampleData = [
                { label: 'ນະຄອນຫຼວງວຽງຈັນ', count: 8 },
                { label: 'ຫຼວງພຣະບາງ', count: 5 },
                { label: 'ຈຳປາສັກ', count: 4 },
                { label: 'ສະຫວັນນະເຂດ', count: 3 },
                { label: 'ອື່ນໆ', count: 2 }
            ];
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: sampleData.map(item => item.label),
                    datasets: [{
                        data: sampleData.map(item => item.count),
                        backgroundColor: [
                            '#D4A762', '#B08542', '#9B7C59', '#E9CDA8', '#F0E5D3'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverBorderWidth: 3
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
                                padding: isMobile ? 8 : 15,
                                font: {
                                    size: isMobile ? 10 : 12,
                                    family: "'Noto Sans Lao', 'Phetsarath OT', 'Saysettha OT', Arial, sans-serif"
                                },
                                usePointStyle: true,
                                generateLabels: function(chart) {
                                    const original = Chart.defaults.plugins.legend.labels.generateLabels;
                                    const labels = original.call(this, chart);
                                    
                                    // เพิ่มจำนวนวัดในป้ายกำกับ
                                    labels.forEach((label, index) => {
                                        const count = sampleData[index]?.count || 0;
                                        label.text = `${label.text} (${count})`;
                                    });
                                    
                                    return labels;
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#D4A762',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} ວັດ (${percentage}%) - ຕົວຢ່າງ`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 1000
                    }
                }
            });
            
            // แสดงข้อความแจ้งเตือนว่าเป็นข้อมูลตัวอย่าง
            const statusElement = document.getElementById('temple-chart-status');
            if (statusElement) {
                statusElement.classList.remove('hidden');
            }
            
            console.log('📝 Note: Displaying sample temple distribution data');
        }

        function fallbackVisitorChart() {
            const ctx = document.getElementById('visitorChart').getContext('2d');
            
            // ใช้ข้อมูลตัวอย่างคงที่ (ไม่สุ่ม) สำหรับ 30 วันที่ผ่านมา
            const fixedVisitors = [
                12, 18, 15, 22, 19, 25, 28, 31, 27, 24,
                20, 16, 21, 26, 30, 33, 29, 25, 22, 18,
                24, 28, 32, 35, 31, 28, 25, 21, 17, 23
            ]; // ข้อมูลคงที่ 30 วัน
            
            const dates = [];
            const today = new Date();
            
            for (let i = 29; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                dates.push(date.toLocaleDateString('lo-LA', { month: 'short', day: 'numeric' }));
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'ຜູ້ເຂົ້າຊົມ (ຕົວຢ່າງ)',
                        data: fixedVisitors, // ← ใช้ข้อมูลคงที่
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#1D4ED8',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                stepSize: 10,
                                callback: function(value) {
                                    return value + ' ຄົນ';
                                }
                            }
                        },
                        x: {
                            ticks: {
                                maxTicksLimit: window.innerWidth < 640 ? 6 : 10
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `ຜູ້ເຂົ້າຊົມ: ${context.parsed.y} ຄົນ (ຕົວຢ່າງ)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // ตั้งค่าสถิติสรุปคงที่
            document.getElementById('todayVisitors').textContent = '23';
            document.getElementById('weekVisitors').textContent = '162';
            document.getElementById('monthVisitors').textContent = '720';
            document.getElementById('totalVisitors').textContent = '1,234';
            
            // แสดงข้อความแจ้งเตือนว่าเป็นข้อมูลตัวอย่าง
            const statusElement = document.getElementById('visitor-chart-status');
            if (statusElement) {
                statusElement.classList.remove('hidden');
            }
            
            console.log('📝 Note: Displaying fixed sample data to prevent random changes');
        }

        // Smooth scrolling for mobile
        let isScrolling = false;
        document.querySelectorAll('.mobile-scroll').forEach(element => {
            element.addEventListener('scroll', () => {
                if (!isScrolling) {
                    window.requestAnimationFrame(() => {
                        isScrolling = false;
                    });
                    isScrolling = true;
                }
            });
        });

        // Update mobile nav active state
        document.querySelectorAll('.mobile-nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.mobile-nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
          // Add scroll reveal animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0% 0% -10% 0%'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.temple-card').forEach(card => {
            observer.observe(card);
        });

        // Add hover effects
        document.querySelectorAll('.temple-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-12px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Mobile nav active state
        document.querySelectorAll('.mobile-nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.mobile-nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Smooth scroll for mobile
        document.querySelectorAll('.temples-mobile').forEach(element => {
            let isDown = false;
            let startX;
            let scrollLeft;

            element.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - element.offsetLeft;
                scrollLeft = element.scrollLeft;
            });

            element.addEventListener('mouseleave', () => {
                isDown = false;
            });

            element.addEventListener('mouseup', () => {
                isDown = false;
            });

            element.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - element.offsetLeft;
                const walk = (x - startX) * 2;
                element.scrollLeft = scrollLeft - walk;
            });
        });
    </script>
</body>
</html>