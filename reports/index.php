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

<!-- Main Content -->
<div class="bg-gray-50 min-h-screen">
    <!-- Hero Banner -->
    <div class="bg-gradient-to-r from-indigo-600 to-blue-500 text-white py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <pattern id="dotPattern" width="20" height="20" patternUnits="userSpaceOnUse">
                        <circle cx="3" cy="3" r="1.5" fill="#fff"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#dotPattern)"/>
            </svg>
        </div>
        
        <div class="relative max-w-7xl mx-auto">
            <div class="flex items-center justify-between flex-wrap">
                <div class="w-full md:w-3/4">
                    <h1 class="text-3xl md:text-4xl font-bold mb-2 flex items-center">
                        <i class="fas fa-chart-line mr-3"></i> ລະບົບລາຍງານ & ວິເຄາະຂໍ້ມູນ
                    </h1>
                    <p class="text-lg md:text-xl opacity-90 mb-6">
                        ສ້າງ, ສົ່ງອອກ ແລະ ວິເຄາະລາຍງານຂໍ້ມູນທັງໝົດໃນລະບົບ
                    </p>
                </div>
                <div class="w-full md:w-1/4 flex justify-end mt-4 md:mt-0">
                    <a href="<?= $base_url ?>dashboard.php" class="bg-white text-indigo-700 hover:bg-indigo-100 px-5 py-2.5 rounded-lg shadow-md transition duration-200 flex items-center">
                        <i class="fas fa-home mr-2"></i> ໄປໜ້າຫຼັກ
                    </a>
                </div>
            </div>
            
            <!-- Stats Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 border border-white/30">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-white/25 mr-4">
                            <i class="fas fa-user text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-80">ພຣະສົງທັງໝົດ</p>
                            <p class="text-2xl font-bold"><?= number_format($total_monks) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 border border-white/30">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-white/25 mr-4">
                            <i class="fas fa-place-of-worship text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-80">ວັດທັງໝົດ</p>
                            <p class="text-2xl font-bold"><?= number_format($total_temples) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 border border-white/30">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-white/25 mr-4">
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm opacity-80">ກິດຈະກໍາທັງໝົດ</p>
                            <p class="text-2xl font-bold"><?= number_format($total_events) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8">
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
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-user-friends text-indigo-600 mr-2"></i>
                ລາຍງານຂໍ້ມູນພຣະສົງ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center mb-4">
                            <i class="fas fa-file-export text-indigo-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ສົ່ງອອກຂໍ້ມູນພຣະສົງ</h3>
                        <p class="text-gray-600 mb-4">
                            ສົ່ງອອກຂໍ້ມູນພຣະສົງທັງໝົດ ຫຼື ກັ່ນຕອງຕາມເງື່ອນໄຂ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_export_monks.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                            <i class="fas fa-user-graduate text-emerald-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ການສຶກສາຂອງພຣະສົງ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານຂໍ້ມູນການສຶກສາຂອງພຣະສົງ ທັງການສຶກສາທາງໂລກ ແລະ ທາງທຳ ລວມທັງພັນສາ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_monks_education.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                            <i class="fas fa-chart-pie text-amber-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ສະຖິຕິພຣະສົງ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານສະຖິຕິພຣະສົງຕາມຕຳແໜ່ງ, ອາຍຸ, ພັນສາ, ແລະ ຂໍ້ມູນສະຖິຕິອື່ນໆທີ່ສຳຄັນ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_monks_statistics.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Temple Reports -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-place-of-worship text-indigo-600 mr-2"></i>
                ລາຍງານຂໍ້ມູນວັດ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center mb-4">
                            <i class="fas fa-file-export text-violet-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ສົ່ງອອກຂໍ້ມູນວັດ</h3>
                        <p class="text-gray-600 mb-4">
                            ສົ່ງອອກຂໍ້ມູນວັດທັງໝົດ ຫຼື ກັ່ນຕອງຕາມແຂວງ, ເມືອງ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_export_temples.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-sky-100 flex items-center justify-center mb-4">
                            <i class="fas fa-map-marked-alt text-sky-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ຂໍ້ມູນວັດຕາມພູມິສາດ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານຂໍ້ມູນວັດຕາມແຂວງ, ເມືອງ ແລະ ການກະຈາຍຕົວທາງພູມິສາດຂອງວັດທົ່ວປະເທດ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_temples_geography.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center mb-4">
                            <i class="fas fa-landmark text-rose-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ຂໍ້ມູນໂຄງສ້າງວັດ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານຂໍ້ມູນໂຄງສ້າງພື້ນຖານຂອງວັດ, ສິ່ງປຸກສ້າງ, ພື້ນທີ່ ແລະ ຂໍ້ມູນສຳຄັນອື່ນໆ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_temples_infrastructure.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Events & Activities Reports -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                ລາຍງານກິດຈະກໍາ & ງານບຸນ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-pink-100 flex items-center justify-center mb-4">
                            <i class="fas fa-calendar-check text-pink-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ກິດຈະກໍາປະຈໍາປີ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານກິດຈະກໍາທາງພຸດທະສາສະໜາປະຈໍາປີ ແລະ ງານບຸນຕ່າງໆທີ່ຈັດຂຶ້ນໃນວັດ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_yearly_events.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-cyan-100 flex items-center justify-center mb-4">
                            <i class="fas fa-chart-line text-cyan-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ສະຖິຕິການຈັດກິດຈະກໍາ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານສະຖິຕິກິດຈະກໍາ, ການເຂົ້າຮ່ວມ, ແລະ ຂໍ້ມູນວິເຄາະອື່ນໆທີ່ກ່ຽວຂ້ອງ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_events_statistics.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center mb-4">
                            <i class="fas fa-file-export text-orange-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ສົ່ງອອກຂໍ້ມູນກິດຈະກໍາ</h3>
                        <p class="text-gray-600 mb-4">
                            ສົ່ງອອກຂໍ້ມູນກິດຈະກໍາທັງໝົດຕາມຊ່ວງເວລາ ຫຼື ປະເພດ. ສາມາດສົ່ງອອກເປັນ PDF ຫຼື Excel ໄດ້.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_export_events.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Comprehensive Reports -->
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>
                ລາຍງານພາບລວມລະບົບ
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                            <i class="fas fa-tachometer-alt text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ລາຍງານພາບລວມລະບົບ</h3>
                        <p class="text-gray-600 mb-4">
                            ລາຍງານພາບລວມທັງໝົດຂອງລະບົບ ລວມທັງຂໍ້ມູນວັດ, ພຣະສົງ, ກິດຈະກໍາ ແລະ ຂໍ້ມູນສຳຄັນອື່ນໆ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_system_overview.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-teal-100 flex items-center justify-center mb-4">
                            <i class="fas fa-chart-bar text-teal-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ວິເຄາະຂໍ້ມູນລະບົບ</h3>
                        <p class="text-gray-600 mb-4">
                            ວິເຄາະຂໍ້ມູນສຳຄັນຕ່າງໆຂອງລະບົບ, ສະຖິຕິ, ແລະ ແນວໂນ້ມການປ່ຽນແປງ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_system_analytics.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition duration-300 overflow-hidden border border-gray-100">
                    <div class="p-5">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                            <i class="fas fa-file-pdf text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">ລາຍງານປະຈຳປີ</h3>
                        <p class="text-gray-600 mb-4">
                            ສ້າງລາຍງານປະຈຳປີທີ່ຄົບຖ້ວນ ລວມທັງສະຖິຕິຕ່າງໆຂອງວັດ, ພຣະສົງ ແລະ ກິດຈະກໍາ.
                        </p>
                        <div class="flex justify-end mt-2">
                            <a href="<?= $base_url ?>reports/report_annual.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                ເປີດລາຍງານ <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- CSS Animations and Extra Styles -->
<style>
    /* Pulse Animation for Hero Banner */
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
        100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
    }
    
    /* Card Hover Effect */
    .bg-white.rounded-xl {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        will-change: transform;
    }
    
    .bg-white.rounded-xl:hover {
        transform: translateY(-5px);
    }
    
    /* Icon Animation */
    .w-12.h-12.rounded-full {
        transition: transform 0.3s ease;
    }
    
    .bg-white.rounded-xl:hover .w-12.h-12.rounded-full {
        transform: scale(1.1) rotate(5deg);
    }
</style>

<?php include_once '../includes/footer.php'; ?>