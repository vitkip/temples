<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: {$base_url}auth/login.php");
    exit;
}


?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta name="keywords" content="ວັດ, ລະບົບຈັດການວັດ, ພຣະສົງ, ພຣະສົງລາວ ກິດຈະກໍາທາງສາສນາ">
    <meta name="robots" content="index, follow">
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="laotemples - ລະບົບຈັດການຂໍ້ມູນວັດ">
    <meta property="og:description" content="ລະບົບຈັດການຂໍ້ມູນວັດ ພຣະສົງສາມະເນນ ແລະກິດຈະກຳທາງສາສະໜາ">
    <meta property="og:image" content="https://laotemples.com/assets/images/og-image.jpg">
    <meta property="og:url" content="https://laotemples.com">
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.png" type="image/x-icon">
    <title>ລະບົບຈັດການຂໍ້ມູນວັດ - <?= $page_title ?? 'ໜ້າຫຼັກ' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/head.css">
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="hidden md:flex md:flex-col w-64 sidebar text-white">
            <div class="flex items-center justify-center h-20 border-b border-indigo-800">
                <img class="h-8 w-auto" src="<?= $base_url ?>assets/images/logo.png" alt="<?= htmlspecialchars($site_name) ?>">
                <h1 class="text-xl font-semibold">ລະບົບຈັດການຂໍ້ມູນວັດ</h1>
            </div>
            
            <nav class="mt-6 px-4 flex-1 overflow-y-auto">
                <?php 
                $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $base_path = dirname($_SERVER['SCRIPT_NAME']);
                
                // Function to check if current path matches navigation item
                function isActiveNav($path, $navPath) {
                    if ($navPath === '/dashboard.php') {
                        return $path === '/dashboard.php' || $path === '/' || $path === '/index.php' || basename($path) === 'dashboard.php';
                    }
                    return strpos($path, $navPath) !== false;
                }
                ?>
                
                <!-- Main Navigation -->
                <div class="space-y-1">
                  <a href="<?= $base_url ?>dashboard.php" class="sidebar-link <?= isActiveNav($current_path, '/dashboard.php') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                    <i class="fas fa-tachometer-alt mr-3 w-5 text-center"></i>
                    <span>ແຜງຄວບຄຸມ</span>
                  </a>
                  

                  <a href="<?= $base_url ?>monks/" class="sidebar-link <?= isActiveNav($current_path, '/monks/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                    <i class="fas fa-pray mr-3 w-5 text-center"></i>
                    <span>ຈັດການພະສົງ</span>
                  </a>
                   <a href="<?= $base_url ?>temples/" class="sidebar-link <?= isActiveNav($current_path, '/temples/') ? 'temples' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                      <i class="fas fa-gopuram mr-3 w-5 text-center"></i>
                      <span>ຈັດການວັດ</span>
                    </a>
                  <a href="<?= $base_url ?>events/" class="sidebar-link <?= isActiveNav($current_path, '/events/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                    <i class="fas fa-calendar-alt mr-3 w-5 text-center"></i>
                    <span>ກິດຈະກໍາ</span>
                  </a>
                
                </div>
                
                <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
                <!-- Admin Section -->
                <div class="mt-8">
                  <div class="px-4 mb-3">
                    <h3 class="text-xs font-semibold text-indigo-200 uppercase tracking-wider">ການຈັດການລະບົບ</h3>
                  </div>
                  <div class="space-y-1">
                    <a href="<?= $base_url ?>subscriptions/" class="sidebar-link <?= isActiveNav($current_path, '/subscriptions/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                      <i class="fas fa-credit-card mr-3 w-5 text-center"></i>
                      <span>ການສະໝັກໃຊ້</span>
                    </a>
                    <a href="<?= $base_url ?>subscription_plans/" class="sidebar-link <?= isActiveNav($current_path, '/subscription_plans/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                      <i class="fas fa-list mr-3 w-5 text-center"></i>
                      <span>ຈັດແພກເກດ</span>
                    </a>
                    <a href="<?= $base_url ?>subscription_payments/" class="sidebar-link <?= isActiveNav($current_path, '/subscription_payments/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                      <i class="fas fa-money-bill-wave mr-3 w-5 text-center"></i>
                      <span>ຈັດການການຊຳລະເງິນ</span>
                    </a>
                     <a href="<?= $base_url ?>reports/" class="sidebar-link <?= isActiveNav($current_path, '/reports/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                        <i class="fas fa-chart-bar mr-3 w-5 text-center"></i>
                        <span>ລາຍງານ</span>
                      </a>
                    <a href="<?= $base_url ?>users/" class="sidebar-link <?= isActiveNav($current_path, '/users/') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                      <i class="fas fa-users mr-3 w-5 text-center"></i>
                      <span>ຜູ້ໃຊ້ງານລະບົບ</span>
                    </a>
                  </div>
                </div>
                <!-- Admin Section End -->
                <div class="mt-8">
                    <div class="space-y-1">
                        <a href="<?= $base_url ?>admin/settings.php" class="sidebar-link <?= isActiveNav($current_path, '/admin/settings.php') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
                            <i class="fas fa-cog mr-3 w-5 text-center"></i>
                            <span>ຕັ້ງຄ່າ</span>
                        </a>
                    </div>
                </div>
                 <?php endif; ?>
            </nav>
            
            <div class="border-t border-indigo-800 p-4">
                <a href="<?= $base_url ?>auth/logout.php" class="flex items-center text-white hover:text-gray-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span>ອອກຈາກລະບົບ</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <button id="toggleSidebar" class="text-gray-500 focus:outline-none md:hidden">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="text-xl font-semibold text-gray-800 ml-4"><?= $page_title ?? 'ໜ້າຫຼັກ' ?></h2>
                </div>
                
                <div class="flex items-center">
                    <!-- Add Alpine.js first -->
                    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center text-gray-700 focus:outline-none hover:bg-gray-100 rounded-full px-3 py-1">
                            <div class="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white">
                                <?= substr($_SESSION['user']['name'], 0, 1) ?>
                            </div>
                            <span class="ml-2"><?= $_SESSION['user']['name'] ?></span>
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" 
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50" style="display: none;">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-100">
                                <div class="font-medium"><?= $_SESSION['user']['name'] ?></div> 
                            </div>
                            <a href="<?= $base_url ?>auth/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>
                                ຂໍ້ມູນສ່ວນຕົວ
                            </a>
                            <a href="<?= $base_url ?>auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                ອອກຈາກລະບົບ
                            </a>
                        </div>
                    </div>
                </div>
            </header>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebarBtn = document.getElementById('toggleSidebar');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (toggleSidebarBtn && sidebar && overlay) {
                // ฟังก์ชันเปิด sidebar
                function openSidebar() {
                    sidebar.classList.add('active');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden'; // ป้องกันการ scroll ของ body
                }
                
                // ฟังก์ชันปิด sidebar
                function closeSidebar() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = ''; // คืนค่า scroll ของ body
                }
                
                // เมื่อคลิกปุ่ม toggle
                toggleSidebarBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (sidebar.classList.contains('active')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
                
                // เมื่อคลิก overlay ให้ปิด sidebar
                overlay.addEventListener('click', function() {
                    closeSidebar();
                });
                
                // เมื่อปรับขนาดหน้าจอ
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
                        // ถ้าเป็น desktop ให้ปิด mobile sidebar
                        closeSidebar();
                    }
                });
                
                // เมื่อคลิกลิงก์ใน sidebar ให้ปิด sidebar (สำหรับ mobile)
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            closeSidebar();
                        }
                    });
                });
            }
        });
    </script>
            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto"></div>