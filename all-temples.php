<?php
session_start();
require_once 'config/db.php';
require_once 'config/base_url.php';

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

// กำหนดชื่อเว็บไซต์
$site_name = $settings['site_name'] ?? 'ລະບົບຈັດການວັດ';
$page_title = 'ວັດທັງໝົດ';

// เช็คสถานะการเข้าสู่ระบบ
$logged_in = isset($_SESSION['user']);

// การค้นหาและกรอง
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$district = isset($_GET['district']) ? trim($_GET['district']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// สร้างเงื่อนไขสำหรับการค้นหา
$where_clauses = ["status = 'active'"]; // แสดงวัดที่มีสถานะเป็น active เท่านั้น
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR description LIKE ? OR abbot_name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($province)) {
    $where_clauses[] = "province = ?";
    $params[] = $province;
}

if (!empty($district)) {
    $where_clauses[] = "district = ?";
    $params[] = $district;
}

// สร้างเงื่อนไขการเรียงลำดับ
$sort_options = [
    'name_asc' => 'name ASC',
    'name_desc' => 'name DESC',
    'date_asc' => 'created_at ASC',
    'date_desc' => 'created_at DESC',
    'province_asc' => 'province ASC, name ASC',
    'province_desc' => 'province DESC, name ASC',
];

$order_by = isset($sort_options[$sort]) ? $sort_options[$sort] : 'name ASC';

// สร้าง WHERE clause
$where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// ดึงข้อมูลวัดทั้งหมด
try {
    $sql = "SELECT * FROM temples $where_clause ORDER BY $order_by";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $temples = $stmt->fetchAll();
    
    // นับจำนวนวัดทั้งหมด
    $total_temples = count($temples);
    
    // ดึงรายชื่อจังหวัดทั้งหมด
    $province_stmt = $pdo->query("SELECT DISTINCT province FROM temples WHERE status = 'active' ORDER BY province");
    $provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ดึงรายชื่ออำเภอตามจังหวัดที่เลือก
    $districts = [];
    if (!empty($province)) {
        $district_stmt = $pdo->prepare("SELECT DISTINCT district FROM temples WHERE province = ? AND status = 'active' ORDER BY district");
        $district_stmt->execute([$province]);
        $districts = $district_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // คำขอ API สำหรับข้อมูล districts
    if (isset($_GET['get_districts']) && !empty($_GET['province'])) {
        $get_province = $_GET['province'];
        $api_district_stmt = $pdo->prepare("SELECT DISTINCT district FROM temples WHERE province = ? AND status = 'active' ORDER BY district");
        $api_district_stmt->execute([$get_province]);
        $api_districts = $api_district_stmt->fetchAll(PDO::FETCH_COLUMN);
        header('Content-Type: application/json');
        echo json_encode($api_districts);
        exit;
    }
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#B08542">
    <title><?= $page_title ?> | <?= htmlspecialchars($site_name) ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
    
    <style>
        body {
            font-family: 'Noto Sans Lao', sans-serif;
            -webkit-tap-highlight-color: transparent; /* ป้องกันการไฮไลต์สีฟ้าเมื่อแตะบนมือถือ */
            padding-bottom: env(safe-area-inset-bottom, 0); /* รองรับ iPhone X และรุ่นใหม่กว่า */
        }
        
        /* Hover effect for temple cards */
        .temple-card {
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .temple-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(176, 133, 66, 0.2);
        }
        
        .temple-card .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .temple-card .card-footer {
            margin-top: auto;
        }
        
        /* Hero section */
        .hero-section {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?= $base_url ?>assets/images/temple-bg.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(176, 133, 66, 0.8) 0%, rgba(212, 167, 98, 0.7) 100%);
        }
        
        /* Filters section on mobile */
        .filter-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            z-index: 50;
            padding: 1.25rem 1rem;
            box-shadow: 0 -4px 12px -1px rgba(0, 0, 0, 0.15);
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            max-height: 85vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* สำหรับการเลื่อนเรียบบน iOS */
            padding-bottom: env(safe-area-inset-bottom, 1rem);
        }
        
        .filter-drawer.open {
            transform: translateY(0);
        }
        
        .backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            -webkit-backdrop-filter: blur(2px); /* เพิ่ม blur effect บน Safari */
            backdrop-filter: blur(2px);
        }
        
        .backdrop.open {
            opacity: 1;
            pointer-events: auto;
        }
        
        .drawer-handle {
            width: 40px;
            height: 5px;
            background: #d1d5db;
            border-radius: 9999px;
            margin: 0 auto 16px;
        }
        
        /* ปุ่ม Floating Action Button */
        .fab {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #D4A762;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 30;
            border: none;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        
        .fab:active {
            transform: scale(0.95);
            background: #B08542;
        }
        
        /* Mobile navigation bar */
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
        
        /* Mobile scroll container */
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

        /* Media queries สำหรับอุปกรณ์มือถือ */
        @media (max-width: 640px) {
            /* ลดขนาดของ Hero Section บนมือถือ */
            .hero-section {
                height: 180px;
                padding: 0 1rem;
                text-align: center;
            }
            
            /* ลดขนาดของข้อความใน Hero Section */
            .hero-section h1 {
                font-size: 1.75rem;
                margin-bottom: 0.5rem;
                line-height: 1.2;
            }
            
            .hero-section p {
                font-size: 1rem;
            }
            
            /* ปรับการแสดงผลของการ์ดวัด */
            .temple-card .h-48 {
                height: 160px; /* ลดความสูงของรูปภาพ */
            }
            
            /* เพิ่ม padding ให้กับ container หลัก */
            .max-w-7xl {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* ปรับ padding ของ footer ให้น้อยลง */
            footer {
                padding-top: 2rem;
                padding-bottom: 5rem; /* เพิ่มพื้นที่สำหรับ bottom nav */
            }
            
            /* ปรับปรุงขนาดของ touch targets */
            select, input, button {
                min-height: 44px;
            }
            
            /* เพิ่ม margin สำหรับ temple grid */
            .grid {
                margin-bottom: 4rem;
            }
            
            /* ปรับขนาด filter button */
            #filterBtn {
                min-width: 120px;
                padding: 0.75rem 1rem;
            }
            
            .temple-card {
                margin-bottom: 1rem;
            }
            
            /* ซ่อน nav item text ในกรณีหน้าจอเล็กมาก */
            @media (max-width: 350px) {
                .mobile-nav-item span {
                    display: none;
                }
            }
        }
        
        /* ปรับปรุงเมนูนำทางบนมือถือ */
        @media (max-width: 768px) {
            .temple-card {
                will-change: transform; /* เพิ่มประสิทธิภาพ animation */
            }
            
            .nav-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            
            .nav-container::-webkit-scrollbar {
                display: none;
            }
        }
        
        /* Improvements for iOS devices */
        @supports (-webkit-touch-callout: none) {
            .filter-drawer, .mobile-navbar {
                padding-bottom: env(safe-area-inset-bottom, 1rem);
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
        <a href="<?= $base_url ?>all-temples.php" class="mobile-nav-item active">
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
                        <a href="<?= $base_url ?>auth/" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
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

    <!-- Hero Section - ใช้รูปแบบเดียวกับ index.php -->
    <section class="hero-section py-16 md:py-32 relative">
        <div class="hero-overlay"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center md:text-left md:max-w-2xl">
                <h1 class="text-2xl md:text-5xl font-bold text-white leading-tight">
                    ວັດທັງໝົດໃນລະບົບ
                </h1>
                <p class="mt-4 text-base md:text-lg text-gray-100">
                    ຄົ້ນຫາ ແລະ ສຳຫຼວດວັດທັງໝົດ <?= number_format($total_temples) ?> ແຫ່ງ ໃນລະບົບຂອງພວກເຮົາ
                </p>
                <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-3 justify-center md:justify-start">
                    <a href="#search-filters" class="w-full sm:w-auto btn-primary text-center">
                        <i class="fas fa-search mr-1"></i> ຄົ້ນຫາແລະຕົວກອງ
                    </a>
                    <a href="<?= $base_url ?>" class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gray-800 bg-opacity-60 hover:bg-opacity-70 text-center">
                        <i class="fas fa-arrow-left mr-1"></i> ກັບຄືນໜ້າຫຼັກ
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div id="search-filters" class="page-container py-8 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Filter & Search Section -->
            <div class="card p-4 md:p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0 flex items-center">
                        <div class="category-icon">
                            <i class="fas fa-search"></i>
                        </div> 
                        ຄົ້ນຫາ ແລະ ກັ່ນຕອງ
                    </h2>
                    <button id="filterBtn" class="btn-primary sm:hidden">
                        <i class="fas fa-filter mr-2"></i> ຕົວກອງ
                    </button>
                </div>
                
                <!-- Desktop Filters (hidden on mobile) -->
                <form method="GET" class="hidden sm:block">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm text-gray-700 mb-1">ຄົ້ນຫາ</label>
                            <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                                placeholder="ຄົ້ນຫາຊື່ວັດ, ລາຍລະອຽດ..." 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label for="province" class="block text-sm text-gray-700 mb-1">ແຂວງ</label>
                            <select name="province" id="province" class="w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="this.form.submit()">
                                <option value="">-- ທັງໝົດ --</option>
                                <?php foreach ($provinces as $p): ?>
                                    <option value="<?= htmlspecialchars($p) ?>" <?= $province === $p ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="district" class="block text-sm text-gray-700 mb-1">ເມືອງ</label>
                            <select name="district" id="district" class="w-full px-4 py-2 border border-gray-300 rounded-lg" <?= empty($province) ? 'disabled' : '' ?>>
                                <option value="">-- ທັງໝົດ --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= htmlspecialchars($d) ?>" <?= $district === $d ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="sort" class="block text-sm text-gray-700 mb-1">ຮຽງຕາມ</label>
                            <div class="flex space-x-2">
                                <select name="sort" id="sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ຊື່ (A-Z)</option>
                                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ຊື່ (Z-A)</option>
                                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>ຂໍ້ມູນໃໝ່ກ່ອນ</option>
                                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>ຂໍ້ມູນເກົ່າກ່ອນ</option>
                                    <option value="province_asc" <?= $sort === 'province_asc' ? 'selected' : '' ?>>ແຂວງ (A-Z)</option>
                                    <option value="province_desc" <?= $sort === 'province_desc' ? 'selected' : '' ?>>ແຂວງ (Z-A)</option>
                                </select>
                                <button type="submit" class="btn-primary px-4">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Active Filters (if any) -->
            <?php if (!empty($search) || !empty($province) || !empty($district)): ?>
            <div class="bg-amber-50 rounded-lg p-4 mb-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-amber-800">ຕົວກອງທີ່ໃຊ້:</span>
                    
                    <?php if (!empty($search)): ?>
                    <div class="bg-white rounded-full px-3 py-1 text-sm flex items-center">
                        <span class="mr-2">ຄົ້ນຫາ: <?= htmlspecialchars($search) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($province)): ?>
                    <div class="bg-white rounded-full px-3 py-1 text-sm flex items-center">
                        <span class="mr-2">ແຂວງ: <?= htmlspecialchars($province) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['province' => '', 'district' => ''])) ?>" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($district)): ?>
                    <div class="bg-white rounded-full px-3 py-1 text-sm flex items-center">
                        <span class="mr-2">ເມືອງ: <?= htmlspecialchars($district) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['district' => ''])) ?>" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?= $base_url ?>all-temples.php" class="text-amber-600 text-sm hover:underline ml-2">
                        ລ້າງຕົວກອງທັງໝົດ
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Results Count -->
            <div class="header-section mb-6 p-4 md:p-6">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-place-of-worship"></i>
                    </div>
                    <?php if (!empty($search) || !empty($province) || !empty($district)): ?>
                        ຜົນການຄົ້ນຫາ: <?= number_format($total_temples) ?> ວັດ
                    <?php else: ?>
                        ວັດທັງໝົດ: <?= number_format($total_temples) ?> ແຫ່ງ
                    <?php endif; ?>
                </h2>
                <p class="mt-2 text-amber-700">
                    <?php if (!empty($search) || !empty($province) || !empty($district)): ?>
                        ຜົນການຄົ້ນຫາພົບວັດທີ່ກົງກັບເງື່ອນໄຂ
                    <?php else: ?>
                        ລາຍຊື່ວັດທີ່ມີໃນລະບົບທັງໝົດ
                    <?php endif; ?>
                </p>
            </div>

            <!-- Temple Grid - แสดงผลแบบ scroll ในมือถือ เหมือนใน index.php -->
            <?php if (count($temples) > 0): ?>
                <!-- Mobile scrollable grid (visible only on small screens) -->
                <div class="mobile-scroll-container sm:hidden">
                    <div class="flex">
                        <?php foreach($temples as $temple): ?>
                        <div class="mobile-scroll-item">
                            <div class="card overflow-hidden h-full temple-card">
                                <div class="h-36 overflow-hidden">
                                    <?php if(!empty($temple['photo'])): ?>
                                        <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                            alt="<?= htmlspecialchars($temple['name']) ?>" 
                                            class="w-full h-full object-cover"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-amber-50">
                                            <i class="fas fa-place-of-worship text-amber-300 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Province Badge -->
                                    <?php if(!empty($temple['province'])): ?>
                                    <span class="absolute top-2 right-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded-full backdrop-blur-sm">
                                        <?= htmlspecialchars($temple['province']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 card-body">
                                    <h3 class="text-base font-semibold text-gray-900 mb-1 line-clamp-2"><?= htmlspecialchars($temple['name']) ?></h3>
                                    
                                    <div class="flex items-center text-xs text-gray-500 mb-1">
                                        <i class="fas fa-map-marker-alt mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></span>
                                    </div>
                                    
                                    <?php if(!empty($temple['abbot_name'])): ?>
                                    <div class="flex items-center text-xs text-gray-500 mb-1">
                                        <i class="fas fa-user mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($temple['abbot_name']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($temple['phone'])): ?>
                                    <div class="flex items-center text-xs text-gray-500 mb-1">
                                        <i class="fas fa-phone mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span><?= htmlspecialchars($temple['phone']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-footer mt-2">
                                        <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                                        class="btn-primary w-full flex items-center justify-center text-xs py-2">
                                            <i class="fas fa-info-circle mr-1"></i> ເບິ່ງລາຍລະອຽດ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Desktop grid layout (hidden on mobile) -->
                <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach($temples as $temple): ?>
                    <div class="card overflow-hidden temple-card">
                        <div class="h-48 overflow-hidden relative">
                            <?php if(!empty($temple['photo'])): ?>
                                <img src="<?= $base_url . htmlspecialchars($temple['photo']) ?>" 
                                    alt="<?= htmlspecialchars($temple['name']) ?>" 
                                    class="w-full h-full object-cover"
                                    loading="lazy">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-amber-50">
                                    <i class="fas fa-place-of-worship text-amber-300 text-4xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Province Badge -->
                            <?php if(!empty($temple['province'])): ?>
                            <span class="absolute top-2 right-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded-full backdrop-blur-sm">
                                <?= htmlspecialchars($temple['province']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4 card-body">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2"><?= htmlspecialchars($temple['name']) ?></h3>
                            
                            <div class="flex items-center text-sm text-gray-500 mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-amber-600 flex-shrink-0"></i>
                                <span class="truncate"><?= htmlspecialchars($temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></span>
                            </div>
                            
                            <?php if(!empty($temple['abbot_name'])): ?>
                            <div class="flex items-center text-sm text-gray-500 mb-2">
                                <i class="fas fa-user mr-2 text-amber-600 flex-shrink-0"></i>
                                <span class="truncate"><?= htmlspecialchars($temple['abbot_name']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($temple['phone'])): ?>
                            <div class="flex items-center text-sm text-gray-500 mb-3">
                                <i class="fas fa-phone mr-2 text-amber-600 flex-shrink-0"></i>
                                <span><?= htmlspecialchars($temple['phone']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="card-footer">
                                <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                                class="btn-primary w-full flex items-center justify-center">
                                    <i class="fas fa-info-circle mr-2"></i> ເບິ່ງລາຍລະອຽດ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No temples found -->
                <div class="card p-8 text-center">
                    <div class="mb-4">
                        <i class="fas fa-place-of-worship text-amber-300 text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">ບໍ່ພົບຂໍ້ມູນວັດ</h3>
                    <p class="text-gray-600 mb-6">ບໍ່ພົບຂໍ້ມູນວັດທີ່ກົງກັບເງື່ອນໄຂການຄົ້ນຫາຂອງທ່ານ</p>
                    <a href="<?= $base_url ?>all-temples.php" class="btn-primary inline-flex items-center">
                        <i class="fas fa-redo mr-2"></i> ເບິ່ງວັດທັງໝົດ
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Mobile Filters Drawer -->
    <div id="filterDrawer" class="filter-drawer">
        <div class="drawer-handle"></div>
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex justify-between items-center">
            <span><i class="fas fa-filter text-amber-600 mr-2"></i> ຕົວກອງ</span>
            <button id="closeDrawer" class="text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </h3>
        
        <form method="GET" class="space-y-4">
            <div>
                <label for="mobile-search" class="block text-sm text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="mobile-search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="ຄົ້ນຫາຊື່ວັດ, ລາຍລະອຽດ..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            
            <div>
                <label for="mobile-province" class="block text-sm text-gray-700 mb-1">ແຂວງ</label>
                <select name="province" id="mobile-province" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">-- ທັງໝົດ --</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $province === $p ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="mobile-district" class="block text-sm text-gray-700 mb-1">ເມືອງ</label>
                <select name="district" id="mobile-district" class="w-full px-4 py-2 border border-gray-300 rounded-lg" <?= empty($province) ? 'disabled' : '' ?>>
                    <option value="">-- ທັງໝົດ --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $district === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="mobile-sort" class="block text-sm text-gray-700 mb-1">ຮຽງຕາມ</label>
                <select name="sort" id="mobile-sort" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ຊື່ (A-Z)</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ຊື່ (Z-A)</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>ຂໍ້ມູນໃໝ່ກ່ອນ</option>
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>ຂໍ້ມູນເກົ່າກ່ອນ</option>
                    <option value="province_asc" <?= $sort === 'province_asc' ? 'selected' : '' ?>>ແຂວງ (A-Z)</option>
                    <option value="province_desc" <?= $sort === 'province_desc' ? 'selected' : '' ?>>ແຂວງ (Z-A)</option>
                </select>
            </div>
            
            <div class="pt-2">
                <button type="submit" class="btn-primary w-full flex items-center justify-center">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
            </div>
            
            <?php if (!empty($search) || !empty($province) || !empty($district) || $sort !== 'name_asc'): ?>
            <div class="pt-2">
                <a href="<?= $base_url ?>all-temples.php" class="block w-full text-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-redo mr-2"></i> ລ້າງຕົວກອງ
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Backdrop for mobile filters -->
    <div id="backdrop" class="backdrop"></div>

    <!-- Footer -->
    <footer class="bg-gray-800 pt-10 pb-16 sm:pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-sm font-semibold text-gray-400 tracking-wider uppercase">ກ່ຽວກັບພວກເຮົາ</h3>
                    <p class="mt-4 text-base text-gray-300">
                        <?= htmlspecialchars($settings['site_description'] ?? 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ພະສົງ ເພື່ອເປັນແຫຼ່ງຂໍ້ມູນທາງພຣະພຸດທະສາສະໜາ.') ?>
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

    <!-- Floating Action Button สำหรับการค้นหา (มือถือ) -->
    <button class="fab md:hidden" id="fabFilter">
        <i class="fas fa-search text-xl"></i>
    </button>

    <!-- Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // แสดง Mobile Navbar
        const mobileNavbar = document.querySelector('.mobile-navbar');
        if (mobileNavbar) {
            mobileNavbar.style.transform = 'translateY(0)';
        }
        
        // Mobile Filter Drawer
        const filterBtn = document.getElementById('filterBtn');
        const fabFilter = document.getElementById('fabFilter');
        const closeDrawer = document.getElementById('closeDrawer');
        const filterDrawer = document.getElementById('filterDrawer');
        const backdrop = document.getElementById('backdrop');
        
        function openFilterDrawer() {
            filterDrawer.classList.add('open');
            backdrop.classList.add('open');
            document.body.style.overflow = 'hidden';
            
            // Add animation for the drawer handle
            const handle = filterDrawer.querySelector('.drawer-handle');
            if (handle) {
                handle.style.animation = 'pulse 1.5s infinite';
            }
        }
        
        function closeFilterDrawer() {
            filterDrawer.classList.remove('open');
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        }
        
        // เพิ่ม event listeners สำหรับทุกปุ่มที่เกี่ยวข้อง
        if (filterBtn) filterBtn.addEventListener('click', openFilterDrawer);
        if (fabFilter) fabFilter.addEventListener('click', openFilterDrawer);
        if (closeDrawer) closeDrawer.addEventListener('click', closeFilterDrawer);
        if (backdrop) backdrop.addEventListener('click', closeFilterDrawer);
        
        // เพิ่มการปิด drawer ด้วยการ swipe down
        let touchStartY = 0;
        let touchEndY = 0;
        
        filterDrawer.addEventListener('touchstart', function(e) {
            touchStartY = e.changedTouches[0].screenY;
        }, false);
        
        filterDrawer.addEventListener('touchend', function(e) {
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        }, false);
        
        function handleSwipe() {
            if (touchEndY - touchStartY > 50) {
                closeFilterDrawer();
            }
        }
        
        // Province-District Dependency
        const provinceSelect = document.getElementById('province');
        const districtSelect = document.getElementById('district');
        const mobileProvinceSelect = document.getElementById('mobile-province');
        const mobileDistrictSelect = document.getElementById('mobile-district');
        
        function updateDistricts(provinceElem, districtElem) {
            if (!provinceElem || !districtElem) return;
            
            provinceElem.addEventListener('change', function() {
                const selectedProvince = this.value;
                
                if (!selectedProvince) {
                    districtElem.innerHTML = '<option value="">-- ທັງໝົດ --</option>';
                    districtElem.disabled = true;
                    return;
                }
                
                // Fetch districts via AJAX
                fetch(`${window.location.origin}${window.location.pathname}?get_districts=1&province=${encodeURIComponent(selectedProvince)}`)
                    .then(response => response.json())
                    .then(districts => {
                        districtElem.innerHTML = '<option value="">-- ທັງໝົດ --</option>';
                        
                        districts.forEach(district => {
                            const option = document.createElement('option');
                            option.value = district;
                            option.textContent = district;
                            districtElem.appendChild(option);
                        });
                        
                        districtElem.disabled = false;
                    })
                    .catch(error => console.error('Error fetching districts:', error));
            });
        }
        
        updateDistricts(provinceSelect, districtSelect);
        updateDistricts(mobileProvinceSelect, mobileDistrictSelect);
        
        // ทำให้การ์ดวัดมีการแสดงผลที่ดีขึ้น
        const templeCards = document.querySelectorAll('.temple-card');
        templeCards.forEach(card => {
            // เพิ่ม Ripple Effect เมื่อกดที่การ์ด
            card.addEventListener('touchstart', function(e) {
                this.style.transform = 'scale(0.98)';
            });
            
            card.addEventListener('touchend', function(e) {
                this.style.transform = '';
            });
        });
        
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
        
        // ปรับปรุงการแสดงผลเมื่อเลื่อน (scroll)
        let lastScrollTop = 0;
        const fab = document.getElementById('fabFilter');
        
        window.addEventListener('scroll', function() {
            const st = window.pageYOffset || document.documentElement.scrollTop;
            
            // ซ่อน FAB เมื่อเลื่อนลง แสดงเมื่อเลื่อนขึ้น
            if (st > lastScrollTop && st > 300) {
                // เลื่อนลง
                if (fab) fab.style.transform = 'translateY(80px)';
            } else {
                // เลื่อนขึ้น
                if (fab) fab.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = st <= 0 ? 0 : st;
        }, false);
    });

    // เพิ่ม keyframe animation สำหรับปุ่ม drawer
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>