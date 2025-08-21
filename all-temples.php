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

// Pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$records_per_page = 12; // จำนวนรายการต่อหน้า

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
    $where_clauses[] = "t.province_id = ?";
    $params[] = $province;
}

if (!empty($district)) {
    $where_clauses[] = "t.district_id = ?";
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
    // สร้าง query สำหรับนับจำนวนรายการทั้งหมด
    $count_sql = "SELECT COUNT(*) as total 
                  FROM temples t 
                  LEFT JOIN provinces p ON t.province_id = p.province_id 
                  LEFT JOIN districts d ON t.district_id = d.district_id 
                  $where_clause";
    
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $index => $param) {
        $count_stmt->bindValue($index + 1, $param);
    }
    $count_stmt->execute();
    $total_temples = $count_stmt->fetchColumn();
    $total_pages = ceil($total_temples / $records_per_page);
    
    // Ensure page doesn't exceed available pages
    if ($total_temples > 0 && $page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $records_per_page;
    
    // สร้าง query สำหรับดึงข้อมูลแบบแบ่งหน้า
    $sql = "SELECT t.*, 
            p.province_name as province, 
            d.district_name as district 
            FROM temples t 
            LEFT JOIN provinces p ON t.province_id = p.province_id 
            LEFT JOIN districts d ON t.district_id = d.district_id 
            $where_clause 
            ORDER BY $order_by
            LIMIT $records_per_page OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->execute();
    $temples = $stmt->fetchAll();
    
    // ดึงรายชื่อจังหวัดทั้งหมด
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงรายชื่ออำเภอตามจังหวัดที่เลือก
    $districts = [];
    if (!empty($province)) {
        $district_stmt = $pdo->prepare("SELECT district_id, district_name FROM districts WHERE province_id = ? ORDER BY district_name");
        $district_stmt->execute([$province]);
        $districts = $district_stmt->fetchAll();
    }
    
    // คำขอ API สำหรับข้อมูล districts
    if (isset($_GET['get_districts']) && !empty($_GET['province_id'])) {
        $province_id = (int)$_GET['province_id'];
        $api_district_stmt = $pdo->prepare("
            SELECT district_id, district_name 
            FROM districts 
            WHERE province_id = ? 
            ORDER BY district_name
        ");
        $api_district_stmt->execute([$province_id]);
        $api_districts = $api_district_stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode(['districts' => $api_districts]);
        exit;
    }
} catch (PDOException $e) {
    $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
}

// รับค่าตัวกรองจาก GET แบบละเอียด เหมือนใน monks/index.php
$province_filter = isset($_GET['province']) && is_numeric($_GET['province']) ? (int)$_GET['province'] : null;
$district_filter = isset($_GET['district']) && is_numeric($_GET['district']) ? (int)$_GET['district'] : null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// เริ่มสร้าง query
$params = [];
$query = "SELECT t.*, 
        p.province_name as province, 
        d.district_name as district 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        LEFT JOIN districts d ON t.district_id = d.district_id 
        WHERE t.status = 'active'";

// การกรองจากฟอร์ม
if (!empty($search_term)) {
    $query .= " AND (t.name LIKE ? OR t.description LIKE ? OR t.abbot_name LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($province_filter) {
    $query .= " AND t.province_id = ?";
    $params[] = $province_filter;
}
// ตรวจสอบว่ามีการเลือกอำเภอหรือไม่
$district_stmt = $pdo->prepare("SELECT district_id, district_name FROM districts WHERE province_id = ? ORDER BY district_name");

if ($district_filter) {
    $query .= " AND t.district_id = ?";
    $params[] = $district_filter;
}

if ($status_filter) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}

// สร้างเงื่อนไขการเรียงลำดับ
$sort_options = [
    'name_asc' => 't.name ASC',
    'name_desc' => 't.name DESC',
    'date_asc' => 't.created_at ASC',
    'date_desc' => 't.created_at DESC',
    'province_asc' => 'p.province_name ASC, t.name ASC',
    'province_desc' => 'p.province_name DESC, t.name ASC',
];

$order_by = isset($sort_options[$sort]) ? $sort_options[$sort] : 't.name ASC';
$query .= " ORDER BY $order_by";

?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
     <meta name="description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta name="keywords" content="ວັດ, ລະບົບຈັດການວັດ, ພຣະສົງ, ພຣະສົງລາວ ກິດຈະກໍາທາງສາສນາ">
    <meta name="robots" content="index, follow">
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="laotemples - ລະບົບຈັດການຂໍ້ມູນວັດ">
    <meta property="og:description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta property="og:image" content="https://laotemples.com/assets/images/og-image.jpg">
    <meta property="og:url" content="https://laotemples.com">
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.png" type="image/x-icon">
    <meta name="theme-color" content="#B08542">
    <title><?= $page_title ?> | <?= htmlspecialchars($site_name) ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#D4A762',
            'primary-dark': '#B08542',
            secondary: '#9B7C59',
            accent: '#E9CDA8',
            light: '#F9F5F0',
            lightest: '#FFFCF7',
          },
          boxShadow: {
            'temple': '0 8px 30px rgba(176, 133, 66, 0.15)'
          }
        }
      }
    }
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/all-temples.css">
    
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
        <a href="<?= $base_url ?>auth/register.php" class="mobile-nav-item">
            <i class="fas fa-user-plus"></i>
            <span>ລົງທະບຽນ</span>
        </a>
        <?php if ($logged_in): ?>
        <a href="<?= $base_url ?>dashboard.php" class="mobile-nav-item">
            <i class="fas fa-tachometer-alt"></i>
            <span>ແຜງຄວບຄຸມ</span>
        </a>
        <?php else: ?>
        <a href="<?= $base_url ?>auth/" class="mobile-nav-item">
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
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="<?= $base_url ?>" class="text-gray-900 hover:text-amber-600 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            ໜ້າຫຼັກ
                        </a>
                        <a href="<?= $base_url ?>all-temples.php" class="border-b-2 border-amber-500 text-amber-600 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            ວັດທັງໝົດ
                        </a>
                        <a href="<?= $base_url ?>auth/register.php" class="text-gray-900 hover:text-amber-600 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            ລົງທະບຽນ
                        </a>
                        <a href="<?= $base_url ?>about.php" class="text-gray-900 hover:text-amber-600 inline-flex items-center px-1 pt-1 text-sm font-medium">
                            ກ່ຽວກັບໂຄງການ
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if ($logged_in): ?>
                        <a href="<?= $base_url ?>dashboard.php" class="text-amber-700 hover:text-amber-800 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-1"></i> ແຜງຄວບຄຸມ
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

    <!-- Hero Section -->
    <section class="hero-section py-16 md:py-32 relative">
        <div class="hero-overlay"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center md:text-left md:max-w-2xl">
                <h1 class="text-3xl md:text-5xl font-bold text-white leading-tight">
                    ວັດທັງໝົດໃນລະບົບ
                </h1>
                <p class="mt-4 text-base md:text-lg text-gray-100">
                    ຄົ້ນຫາ ແລະ ສຳຫຼວດວັດທັງໝົດ <?= number_format($total_temples) ?> ແຫ່ງ ໃນລະບົບຂອງພວກເຮົາ
                </p>
                <div class="mt-6 sm:mt-8 flex flex-col sm:flex-row gap-3 justify-center md:justify-start">
                    <a href="#search-filters" class="w-full sm:w-auto btn-primary text-center">
                        <i class="fas fa-search mr-1"></i> ຄົ້ນຫາວັດ
                    </a>
                    <a href="<?= $base_url ?>" class="w-full sm:w-auto px-6 py-3 border border-transparent rounded-lg text-base font-medium text-white bg-gray-800 bg-opacity-60 hover:bg-opacity-70 text-center">
                        <i class="fas fa-arrow-left mr-1"></i> ກັບຄືນໜ້າຫຼັກ
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div id="search-filters" class="page-container py-8 md:py-12 bg-temple-pattern">
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
                <form method="GET" class="hidden sm:block" id="searchForm">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm text-gray-700 mb-1">ຄົ້ນຫາ</label>
                            <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" 
                                placeholder="ຄົ້ນຫາຊື່ວັດ, ລາຍລະອຽດ..." 
                                class="w-full search-input">
                        </div>
                        
                        <div>
                            <label for="province" class="block text-sm text-gray-700 mb-1">ແຂວງ</label>
                            <select name="province" id="province" class="w-full search-input">
                                <option value="">-- ທັງໝົດ --</option>
                                <?php foreach ($provinces as $p): ?>
                                    <option value="<?= $p['province_id'] ?>" <?= $province == $p['province_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['province_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="district" class="block text-sm text-gray-700 mb-1">ເມືອງ</label>
                            <select name="district" id="district" class="w-full search-input" <?= empty($province) ? 'disabled' : '' ?>>
                                <option value="">-- ທັງໝົດ --</option>
                                <?php foreach ($districts as $d): ?>
                                    <option value="<?= $d['district_id'] ?>" <?= $district == $d['district_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['district_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="sort" class="block text-sm text-gray-700 mb-1">ຮຽງຕາມ</label>
                            <div class="flex space-x-2">
                                <select name="sort" id="sort" class="w-full search-input">
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
            <div class="bg-amber-50 rounded-lg p-4 mb-6 border border-amber-200 animate-fadeIn">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-amber-800">ຕົວກອງທີ່ໃຊ່:</span>
                    
                    <?php if (!empty($search)): ?>
                    <div class="filter-badge">
                        <span>ຄົ້ນຫາ: <?= htmlspecialchars($search) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="badge-close">
                            <i class="fas fa-times text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($province)): ?>
                    <div class="filter-badge">
                        <span>ແຂວງ: <?= htmlspecialchars($province) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['province' => '', 'district' => ''])) ?>" class="badge-close">
                            <i class="fas fa-times text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($district)): ?>
                    <div class="filter-badge">
                        <span>ເມືອງ: <?= htmlspecialchars($district) ?></span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['district' => ''])) ?>" class="badge-close">
                            <i class="fas fa-times text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <a href="<?= $base_url ?>all-temples.php" class="text-amber-600 text-sm hover:underline ml-auto">
                        <i class="fas fa-times-circle mr-1"></i> ລ້າງຕົວກອງທັງໝົດ
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Results Count -->
            <div class="header-section mb-6">
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
                    <?php if ($total_temples > 0): ?>
                        <span class="text-sm ml-2">
                            (ສະແດງ <?= (($page - 1) * $records_per_page) + 1 ?> - <?= min($page * $records_per_page, $total_temples) ?> ຈາກທັງໝົດ <?= $total_temples ?> ລາຍການ)
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Temple Grid - Mobile Scrollable View -->
            <?php if (count($temples) > 0): ?>
                <!-- Mobile scrollable grid (visible only on small screens) -->
                <div class="mobile-scroll-container sm:hidden">
                    <div class="flex" id="mobileTempleList">
                        <?php foreach($temples as $temple): ?>
                        <div class="mobile-scroll-item">
                            <div class="temple-card">
                                <div class="temple-img-container h-36">
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
                                    <div class="location-badge">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?= htmlspecialchars($temple['province']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 card-body">
                                    <h3 class="text-base font-semibold text-gray-900 mb-1 line-clamp-2"><?= htmlspecialchars($temple['name']) ?></h3>
                                    
                                    <div class="flex items-center text-xs text-gray-500 mb-2">
                                        <i class="fas fa-map-marker-alt mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars(isset($temple['district']) && $temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></span>
                                    </div>
                                    
                                    <?php if(!empty($temple['abbot_name'])): ?>
                                    <div class="flex items-center text-xs text-gray-500 mb-2">
                                        <i class="fas fa-user mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span class="truncate"><?= htmlspecialchars($temple['abbot_name']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($temple['phone'])): ?>
                                    <div class="flex items-center text-xs text-gray-500 mb-2">
                                        <i class="fas fa-phone mr-1 text-amber-600 flex-shrink-0"></i>
                                        <span><?= htmlspecialchars($temple['phone']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-footer mt-auto">
                                        <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                                           class="btn-primary w-full flex items-center justify-center text-sm py-2">
                                            <i class="fas fa-info-circle mr-1"></i> ເບິ່ງລາຍລະອຽດ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Desktop temple grid (hidden on mobile) -->
                <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="desktopTempleList">
                    <?php foreach($temples as $temple): ?>
                    <div class="temple-card">
                        <div class="temple-img-container h-48 relative">
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
                            <div class="location-badge">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?= htmlspecialchars($temple['province']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4 card-body">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2"><?= htmlspecialchars($temple['name']) ?></h3>
                            
                            <div class="flex items-center text-sm text-gray-500 mb-2">
                                <i class="fas fa-map-marker-alt mr-2 text-amber-600 flex-shrink-0"></i>
                                <span class="truncate"><?= htmlspecialchars(isset($temple['district']) && $temple['district'] ? $temple['district'] . ', ' : '') . htmlspecialchars($temple['province'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></span>
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
                            
                            <div class="card-footer mt-auto">
                                <a href="<?= $base_url ?>temples/view-detaile.php?id=<?= $temple['id'] ?>" 
                                   class="btn-primary w-full flex items-center justify-center">
                                    <i class="fas fa-info-circle mr-2"></i> ເບິ່ງລາຍລະອຽດ
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Loading skeletons template (hidden by default) -->
                <div id="templeSkeleton" class="hidden">
                    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php for($i = 0; $i < 8; $i++): ?>
                        <div class="skeleton-temple-card">
                            <div class="skeleton-image skeleton"></div>
                            <div class="skeleton-content">
                                <div class="skeleton-line title skeleton"></div>
                                <div class="skeleton-line medium skeleton"></div>
                                <div class="skeleton-line short skeleton"></div>
                                <div class="skeleton-btn skeleton"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- No temples found -->
                <div class="card p-8 text-center">
                    <div class="mb-4">
                        <div class="w-20 h-20 rounded-full bg-amber-100 mx-auto flex items-center justify-center">
                            <i class="fas fa-place-of-worship text-amber-400 text-3xl"></i>
                        </div>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">ບໍ່ພົບຂໍ້ມູນວັດ</h3>
                    <p class="text-gray-600 mb-6">ບໍ໚ພົບຂໍ້ມູນວັດທີ່ກົງກັບເງື່ອນໄຂການຄົ້ນຫາຂອງທ່ານ</p>
                    <a href="<?= $base_url ?>all-temples.php" class="btn-primary inline-flex items-center">
                        <i class="fas fa-redo mr-2"></i> ເບິ່ງວັດທັງໝົດ
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- Pagination Navigation -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-8 bg-white rounded-lg border border-gray-200 px-4 py-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex justify-between flex-1 sm:hidden">
                        <!-- Mobile pagination -->
                        <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-white border border-amber-300 rounded-md hover:bg-amber-50">
                          ກ່ອນໜ້າ
                        </a>
                        <?php else: ?>
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
                          ກ່ອນໜ້າ
                        </span>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-white border border-amber-300 rounded-md hover:bg-amber-50">
                          ຕໍ່ໄປ
                        </a>
                        <?php else: ?>
                        <span class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
                          ຕໍ່ໄປ
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-amber-700">
                                ສະແດງ <span class="font-medium"><?= (($page - 1) * $records_per_page) + 1 ?></span> ເຖິງ <span class="font-medium"><?= min($page * $records_per_page, $total_temples) ?></span> 
                                ຈາກທັງໝົດ <span class="font-medium"><?= $total_temples ?></span> ລາຍການ
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <!-- Previous Page Link -->
                                <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-amber-300 bg-white text-sm font-medium text-amber-500 hover:bg-amber-50">
                                  <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php else: ?>
                                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                  <i class="fas fa-chevron-left"></i>
                                </span>
                                <?php endif; ?>

                                <?php
                                // Calculate pagination range
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                // Show first page if not in range
                                if ($start_page > 1): ?>
                                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                                     class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                                    1
                                  </a>
                                  <?php if ($start_page > 2): ?>
                                    <span class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-gray-500">
                                      ...
                                    </span>
                                  <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                  <?php if ($i == $page): ?>
                                    <span class="relative inline-flex items-center px-3 py-2 border border-amber-500 bg-amber-100 text-sm font-medium text-amber-600">
                                      <?= $i ?>
                                    </span>
                                  <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                                      <?= $i ?>
                                    </a>
                                  <?php endif; ?>
                                <?php endfor; ?>

                                <?php
                                // Show last page if not in range
                                if ($end_page < $total_pages): ?>
                                  <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-gray-500">
                                      ...
                                    </span>
                                  <?php endif; ?>
                                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                                     class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                                    <?= $total_pages ?>
                                  </a>
                                <?php endif; ?>

                                <!-- Next Page Link -->
                                <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-amber-300 bg-white text-sm font-medium text-amber-500 hover:bg-amber-50">
                                  <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php else: ?>
                                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
                                  <i class="fas fa-chevron-right"></i>
                                </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
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
        
        <form method="GET" class="space-y-4" id="mobileSearchForm">
            <div>
                <label for="mobile-search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="mobile-search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="ຄົ້ນຫາຊື່ວັດ, ລາຍລະອຽດ..." 
                       class="w-full search-input">
            </div>
            
            <div>
                <label for="mobile-province" class="block text-sm font-medium text-gray-700 mb-1">ແຂວງ</label>
                <select name="province" id="mobile-province" class="w-full search-input">
                    <option value="">-- ທັງໝົດ --</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?= $p['province_id'] ?>" <?= $province == $p['province_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['province_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="mobile-district" class="block text-sm font-medium text-gray-700 mb-1">ເມືອງ</label>
                <select name="district" id="mobile-district" class="w-full search-input" <?= empty($province) ? 'disabled' : '' ?>>
                    <option value="">-- ທັງໝົດ --</option>
                    <?php foreach ($districts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $district === $d ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="mobile-sort" class="block text-sm font-medium text-gray-700 mb-1">ຮຽງຕາມ</label>
                <select name="sort" id="mobile-sort" class="w-full search-input">
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

    <!-- Back to top button -->
    <button id="backToTop" class="hidden fixed bottom-20 right-5 z-30 p-3 bg-amber-600 text-white rounded-full shadow-lg hover:bg-amber-700 focus:outline-none">
        <i class="fas fa-arrow-up"></i>
    </button>

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
                
                // แก้ไขการเรียก API
                fetch(`${window.location.origin}/temples/api/get-districts.php?province_id=${encodeURIComponent(selectedProvince)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response failed');
                        }
                        return response.json();
                    })
                    .then(data => {
                        districtElem.innerHTML = '<option value="">-- ທັງໝົດ --</option>';
                        
                        if (data.districts) {
                            data.districts.forEach(district => {
                                const option = document.createElement('option');
                                option.value = district.district_id;
                                option.textContent = district.district_name;
                                districtElem.appendChild(option);
                            });
                            
                            districtElem.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching districts:', error);
                        // ใช้ API แบบ inline เป็นแผนสำรอง
                        fetch(`${window.location.pathname}?get_districts=1&province_id=${encodeURIComponent(selectedProvince)}`)
                            .then(response => response.json())
                            .then(data => {
                                districtElem.innerHTML = '<option value="">-- ທັງໝົດ --</option>';
                                if (data.districts) {
                                    data.districts.forEach(district => {
                                        const option = document.createElement('option');
                                        option.value = district.district_id; 
                                        option.textContent = district.district_name;
                                        districtElem.appendChild(option);
                                    });
                                    districtElem.disabled = false;
                                }
                            });
                    });
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

    // เพิ่มฟังก์ชันเหมือนใน monks/index.php
document.addEventListener('DOMContentLoaded', function() {
    // ฟังก์ชันสำหรับแสดง loading state
    function showLoadingState(selectElement, message = 'ກຳລັງໂຫຼດຂໍ້ມູນ...') {
        selectElement.innerHTML = `<option value="" class="loading-indicator">${message}</option>`;
        selectElement.disabled = true;
    }

    // ฟังก์ชันโหลดข้อมูลเมืองตามแขวงที่เลือก
    function loadDistricts(provinceId) {
        const districtSelect = document.getElementById('district');
        const mobileDistrictSelect = document.getElementById('mobile-district');
        
        // รีเซ็ต dropdown ถ้าไม่ได้เลือกแขวง
        if (!provinceId) {
            if (districtSelect) {
                districtSelect.innerHTML = '<option value="">-- ທຸກເມືອງ --</option>';
                districtSelect.disabled = true;
            }
            if (mobileDistrictSelect) {
                mobileDistrictSelect.innerHTML = '<option value="">-- ທຸກເມືອງ --</option>';
                mobileDistrictSelect.disabled = true;
            }
            return;
        }
        
        // แสดง loading state
        if (districtSelect) {
            showLoadingState(districtSelect);
        }
        if (mobileDistrictSelect) {
            showLoadingState(mobileDistrictSelect);
        }
        
        // ส่ง request ไปยัง API
        fetch(`<?= $base_url ?>api/get-districts.php?province_id=${provinceId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const options = '<option value="">-- ທຸກເມືອງ --</option>' + 
                        data.districts.map(district => 
                            `<option value="${district.district_id}">${district.district_name}</option>`
                        ).join('');
                    
                    if (districtSelect) {
                        districtSelect.innerHTML = options;
                        districtSelect.disabled = false;
                    }
                    if (mobileDistrictSelect) {
                        mobileDistrictSelect.innerHTML = options;
                        mobileDistrictSelect.disabled = false;
                    }
                } else {
                    console.error('Error loading districts:', data.message);
                }
            })
            .catch(error => {
                console.error('API Error:', error);
                const errorOption = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
                if (districtSelect) {
                    districtSelect.innerHTML = errorOption;
                }
                if (mobileDistrictSelect) {
                    mobileDistrictSelect.innerHTML = errorOption;
                }
            });
    }

    // ตั้งค่า event listeners สำหรับ province selects
    const provinceSelect = document.getElementById('province');
    const mobileProvinceSelect = document.getElementById('mobile-province');
    
    if (provinceSelect) {
        provinceSelect.addEventListener('change', function() {
            loadDistricts(this.value);
        });
    }
    
    if (mobileProvinceSelect) {
        mobileProvinceSelect.addEventListener('change', function() {
            loadDistricts(this.value);
        });
    }
    
    // โหลดข้อมูลเริ่มต้นเมื่อหน้าเว็บโหลด
    const provinceId = '<?= $province_filter ?>';
    if (provinceId) {
        loadDistricts(provinceId);
    }
    
    // เพิ่ม event listener สำหรับ auto-submit เมื่อเปลี่ยนค่าใน dropdown
    const autoSubmitSelects = document.querySelectorAll('#sort, #mobile-sort');
    autoSubmitSelects.forEach(select => {
        select.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});

// สร้าง style element สำหรับ loading indicator
const styleTag = document.createElement('style');
styleTag.innerHTML = `
    .loading-indicator {
        position: relative;
        padding-left: 25px;
    }
    .loading-indicator:before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        width: 15px;
        height: 15px;
        margin-top: -7px;
        border: 2px solid #D4A762;
        border-radius: 50%;
        border-top-color: transparent;
        animation: loader-spin 0.6s linear infinite;
    }
    @keyframes loader-spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(styleTag);
    </script>
</body>
</html>