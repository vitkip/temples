<?php
// filepath: c:\xampp\htdocs\temples\reports\index.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึง
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ดึงข้อมูลสถิติสำหรับการแสดงผล
try {
    // จำนวนพระสงฆ์ทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM monks");
    $total_monks = $stmt->fetchColumn();
    
    // จำนวนวัดทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM temples");
    $total_temples = $stmt->fetchColumn();
    
    // จำนวนกิจกรรมทั้งหมด
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $total_events = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    // จัดการข้อผิดพลาด
    $error_message = $e->getMessage();
}

$page_title = "ລະບົບລາຍງານ";
include_once '../includes/header.php';
?>

<!-- เพิ่ม link เพื่อนำเข้า monk-style.css -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<!-- Main Content -->
<div class="page-container min-h-screen">
    <!-- Hero Banner -->
    <div class="header-section py-12 px-4 sm:px-6 lg:px-8 mb-8 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <pattern id="dotPattern" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="3" cy="3" r="1.5" fill="#B08542"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dotPattern)"/>
            </svg>
        </div>
        
        <div class="relative max-w-7xl mx-auto">
            <div class="flex items-center justify-between flex-wrap">
                <div class="w-full md:w-3/4">
                    <h1 class="text-3xl md:text-4xl font-bold mb-2 flex items-center text-gray-800">
                        <div class="category-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        ລະບົບລາຍງານ & ວິເຄາະຂໍ້ມູນ
                    </h1>
                    <p class="text-lg md:text-xl text-amber-700 mb-6">
                        ສ້າງ, ສົ່ງອອກ ແລະ ວິເຄາະລາຍງານຂໍ້ມູນທັງໝົດໃນລະບົບ
                    </p>
                </div>
                <div class="w-full md:w-1/4 flex justify-end mt-4 md:mt-0">
                    <a href="<?= $base_url ?>dashboard.php" class="btn px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg shadow-sm transition flex items-center">
                        <i class="fas fa-home mr-2"></i> ໄປໜ້າຫຼັກ
                    </a>
                </div>
            </div>
            
            <!-- Stats Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8">
                <div class="card p-4 border-amber-200">
                    <div class="flex items-center">
                        <div class="icon-circle mr-4">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-amber-700">ພຣະສົງທັງໝົດ</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($total_monks) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card p-4 border-amber-200">
                    <div class="flex items-center">
                        <div class="icon-circle mr-4">
                            <i class="fas fa-place-of-worship text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-amber-700">ວັດທັງໝົດ</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($total_temples) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card p-4 border-amber-200">
                    <div class="flex items-center">
                        <div class="icon-circle mr-4">
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-amber-700">ກິດຈະກໍາທັງໝົດ</p>
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($total_events) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">
                            ເກີດຂໍ້ຜິດພາດ: <?= htmlspecialchars($error_message) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Monk Reports -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                ລາຍງານຂໍ້ມູນພຣະສົງ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-file-export text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ສົ່ງອອກຂໍ້ມູນພຣະສົງ</h3>
                    <p class="text-gray-600 mb-4">
                        ສົ່ງອອກຂໍ້ມູນພຣະສົງທັງໝົດ ຫຼື ກັ່ນຕອງຕາມເງື່ອນໄຂ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_export_monks.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-user-graduate text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ການສຶກສາຂອງພຣະສົງ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານຂໍ້ມູນການສຶກສາຂອງພຣະສົງ ທັງການສຶກສາທາງໂລກ ແລະ ທາງທຳ ລວມທັງພັນສາ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_monks_education.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-chart-pie text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ສະຖິຕິພຣະສົງ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານສະຖິຕິພຣະສົງຕາມຕຳແໜ່ງ, ອາຍຸ, ພັນສາ, ແລະ ຂໍ້ມູນສະຖິຕິອື່ນໆທີ່ສຳຄັນ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_monks_statistics.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Temple Reports -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-place-of-worship"></i>
                </div>
                ລາຍງານຂໍ້ມູນວັດ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-file-export text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ສົ່ງອອກຂໍ້ມູນວັດ</h3>
                    <p class="text-gray-600 mb-4">
                        ສົ່ງອອກຂໍ້ມູນວັດທັງໝົດ ຫຼື ກັ່ນຕອງຕາມແຂວງ, ເມືອງ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_export_temples.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-map-marked-alt text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ຂໍ້ມູນວັດຕາມພູມິສາດ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານຂໍ້ມູນວັດຕາມແຂວງ, ເມືອງ ແລະ ການກະຈາຍຕົວທາງພູມິສາດຂອງວັດທົ່ວປະເທດ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_temples_geography.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-landmark text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ຂໍ້ມູນໂຄງສ້າງວັດ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານຂໍ້ມູນໂຄງສ້າງພື້ນຖານຂອງວັດ, ສິ່ງປຸກສ້າງ, ພື້ນທີ່ ແລະ ຂໍ້ມູນສຳຄັນອື່ນໆ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_temples_infrastructure.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Events & Activities Reports -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                ລາຍງານກິດຈະກໍາ & ງານບຸນ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-calendar-check text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ກິດຈະກໍາປະຈໍາປີ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານກິດຈະກໍາທາງພຸດທະສາສະໜາປະຈໍາປີ ແລະ ງານບຸນຕ່າງໆທີ່ຈັດຂຶ້ນໃນວັດ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_yearly_events.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ສະຖິຕິການຈັດກິດຈະກໍາ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານສະຖິຕິກິດຈະກໍາ, ການເຂົ້າຮ່ວມ, ແລະ ຂໍ້ມູນວິເຄາະອື່ນໆທີ່ກ່ຽວຂ້ອງ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_events_statistics.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-file-export text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ສົ່ງອອກຂໍ້ມູນກິດຈະກໍາ</h3>
                    <p class="text-gray-600 mb-4">
                        ສົ່ງອອກຂໍ້ມູນກິດຈະກໍາທັງໝົດຕາມຊ່ວງເວລາ ຫຼື ປະເພດ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_export_events.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Comprehensive Reports -->
        <div>
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <div class="category-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                ລາຍງານພາບລວມລະບົບ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-tachometer-alt text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ລາຍງານພາບລວມລະບົບ</h3>
                    <p class="text-gray-600 mb-4">
                        ລາຍງານພາບລວມທັງໝົດຂອງລະບົບ ລວມທັງຂໍ້ມູນວັດ, ພຣະສົງ, ກິດຈະກໍາ ແລະ ຂໍ້ມູນສຳຄັນອື່ນໆ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_system_overview.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ວິເຄາະຂໍ້ມູນລະບົບ</h3>
                    <p class="text-gray-600 mb-4">
                        ວິເຄາະຂໍ້ມູນສຳຄັນຕ່າງໆຂອງລະບົບ, ສະຖິຕິ, ແລະ ແນວໂນ້ມການປ່ຽນແປງ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_system_analytics.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card p-5">
                    <div class="icon-circle mb-4">
                        <i class="fas fa-file-pdf text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">ລາຍງານປະຈຳປີ</h3>
                    <p class="text-gray-600 mb-4">
                        ສ້າງລາຍງານປະຈຳປີທີ່ຄົບຖ້ວນ ລວມທັງສະຖິຕິຕ່າງໆຂອງວັດ, ພຣະສົງ ແລະ ກິດຈະກໍາ.
                    </p>
                    <div class="flex justify-end mt-2">
                        <a href="<?= $base_url ?>reports/report_annual.php" class="text-amber-600 hover:text-amber-800 font-medium flex items-center">
                            ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- CSS เพิ่มเติมสำหรับอนิเมชัน -->
<style>
    /* อนิเมชัน fadeInUp เมื่อโหลดหน้า */
    .card {
        animation: fadeInUp 0.5s ease-out forwards;
        animation-delay: calc(0.1s * var(--animation-order, 0));
        opacity: 0;
    }
    
    .header-section {
        animation: fadeInUp 0.5s ease-out forwards;
    }
    
    /* กำหนดลำดับการแสดง */
    .card:nth-child(1) { --animation-order: 1; }
    .card:nth-child(2) { --animation-order: 2; }
    .card:nth-child(3) { --animation-order: 3; }
    
    /* เอฟเฟกต์เมื่อ hover ที่ icon-circle */
    .icon-circle {
        transition: transform 0.3s ease;
    }
    
    .card:hover .icon-circle {
        transform: scale(1.1) rotate(5deg);
    }
    
    /* เปลี่ยนสีลิงก์เมื่อ hover */
    a.text-amber-600:hover i {
        transform: translateX(5px);
        transition: transform 0.2s ease;
    }
</style>

<?php include_once '../includes/footer.php'; ?>