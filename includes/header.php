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
    <title>ລະບົບຈັດການຂໍ້ມູນວັດ - <?= $page_title ?? 'ໜ້າຫຼັກ' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" href="<?= $base_url ?>assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@100..900&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Noto Sans Lao', sans-serif;
            background-color: #F9FAFB;
        }
        .sidebar {
            background: linear-gradient(180deg, #4F46E5 0%, #4338CA 100%);
        }
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #FFF;
        }
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .temple-icon {
            background: linear-gradient(45deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
      /* Toast notification styles */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .toast-notification {
            animation: slideUp 0.3s ease-out forwards;
        }
        
.page-container {
  background-image: url('../images/thai-pattern.svg');
  background-repeat: repeat;
  background-size: 200px;
  background-opacity: 0.05;
}

.card {
  border-radius: 1rem;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(200, 169, 126, 0.15);
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
}
:root {
  /* สีหลัก - โทนอุ่น */
  --color-primary: #D4A762;
  --color-primary-dark: #B08542;
  --color-secondary: #9B7C59;
  --color-accent: #E9CDA8;
  
  /* สีพื้นหลัง */
  --color-light: #F9F5F0;
  --color-lightest: #FFFCF7;
  
  /* สีข้อความ */
  --color-dark: #4E3E2E;
  --color-muted: #8E7D6A;
  
  /* สีสถานะ */
  --color-success: #7A9B78;
  --color-danger: #C57B70;
}

body {
  color: var(--color-dark);
  background-color: var(--color-lightest);
}

.header-section {
  background: linear-gradient(135deg, #F0E5D3, #FFFBF5);
}
.data-table {
  border: none;
  box-shadow: 0 2px 20px rgba(138, 103, 57, 0.05);
  border-radius: 1rem;
  overflow: hidden;
}

.table-header {
  background: linear-gradient(90deg, rgba(212, 167, 98, 0.1), rgba(212, 167, 98, 0.05));
}

.table-row {
  border-bottom: 1px solid rgba(212, 167, 98, 0.1);
}

.table-row:last-child {
  border-bottom: none;
}
.form-input, .form-select {
  border: 2px solid rgba(212, 167, 98, 0.2);
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  transition: all 0.2s;
}

.form-input:focus, .form-select:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(212, 167, 98, 0.15);
}

.btn-primary {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
  border-radius: 0.75rem;
  box-shadow: 0 4px 12px rgba(212, 167, 98, 0.3);
  padding: 0.75rem 1.5rem;
  font-weight: 600;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 15px rgba(212, 167, 98, 0.35);
}
.icon-circle {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #F5EFE6, #E9DFC7);
  color: var(--color-primary-dark);
  margin-right: 1rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 1rem;
  border-radius: 2rem;
  font-size: 0.8rem;
  font-weight: 500;
  transition: all 0.2s;
}

.status-active {
  background-color: rgba(122, 155, 120, 0.15);
  color: #5C856A;
  border: 1px solid rgba(122, 155, 120, 0.3);
}
.sidebar {
  background: linear-gradient(180deg, #D4A762 0%, #B08542 100%);
}

.sidebar-link {
  border-radius: 0.75rem;
  margin: 0.25rem 0;
  transition: all 0.3s;
}

.sidebar-link:hover {
  background-color: rgba(255, 255, 255, 0.15);
  transform: translateX(3px);
}

.sidebar-link.active {
  background-color: rgba(255, 255, 255, 0.2);
  border-left: 4px solid #FFF;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}
.header-section {
  background-image: url('../images/temple-pattern-light.svg');
  background-position: right bottom;
  background-repeat: no-repeat;
  background-size: contain;
}

.category-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #D4A762, #B08542);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
}
/* เพิ่มอนิเมชันเมื่อโหลดเพจ */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.header-section {
  animation: fadeInUp 0.5s ease-out forwards;
}

.filter-section {
  animation: fadeInUp 0.5s 0.1s ease-out forwards;
  opacity: 0;
}

.data-table {
  animation: fadeInUp 0.5s 0.2s ease-out forwards;
  opacity: 0;
}

/* อนิเมชันสำหรับปุ่มและองค์ประกอบโต้ตอบ */
.btn {
  overflow: hidden;
  position: relative;
}

.btn::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 100%;
  height: 0;
  padding-bottom: 100%;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  transform: translate(-50%, -50%) scale(0);
  opacity: 0;
  transition: transform 0.4s, opacity 0.3s;
}

.btn:active::after {
  transform: translate(-50%, -50%) scale(1);
  opacity: 1;
  transition: 0s;
}
/* การปรับแต่งสำหรับหน้าจอขนาดเล็ก */
@media (max-width: 768px) {
  .page-container {
    padding: 0.5rem;
  }
  
  .header-section {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
    padding: 1rem;
  }
  
  .header-title {
    font-size: 1.5rem;
  }
  
  .filter-section .p-6 {
    padding: 1rem;
  }
  
  .data-table {
    border-radius: 0.5rem;
  }
  
  .btn-group {
    flex-direction: column;
    width: 100%;
  }
  
  .btn {
    width: 100%;
    margin-bottom: 0.5rem;
  }
}

    </style>
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
                    <i class="fas fa-home mr-3 w-5 text-center"></i>
                    <span>ໜ້າຫຼັກ</span>
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
                        <a href="<?= $base_url ?>auth/profile.php" class="sidebar-link <?= isActiveNav($current_path, '/admin/settings.php') ? 'active' : '' ?> flex items-center py-3 px-4 rounded-lg text-sm font-medium">
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