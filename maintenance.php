<?php
// ถ้ายังไม่มีการเริ่มต้น session ให้เริ่มต้น session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่ามีการรวมไฟล์ config แล้วหรือยัง
if (!defined('BASE_URL')) {
    require_once 'config/base_url.php';
}

// ดึงการตั้งค่าระบบจากฐานข้อมูล (ถ้ามี)
if (!isset($settings)) {
    $settings = [];
    if (file_exists('config/db.php')) {
        require_once 'config/db.php';
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // ไม่ต้องแสดงข้อผิดพลาดในหน้า maintenance
        }
    }
}

// กำหนดค่าตั้งต้นจากการตั้งค่าระบบ
$site_name = $settings['site_name'] ?? 'ລະບົບຈັດການວັດ';
$site_description = $settings['site_description'] ?? 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ກິດຈະກໍາ';
$contact_phone = $settings['contact_phone'] ?? '+856 21 XXXXXX';
$contact_email = $settings['admin_email'] ?? 'contact@example.com';
$maintenance_message = $settings['maintenance_message'] ?? 'ລະບົບກໍາລັງຢູ່ໃນການບໍາລຸງຮັກສາ. ກະລຸນາກັບມາໃໝ່ໃນພາຍຫຼັງ.';
$maintenance_end_time = $settings['maintenance_end_time'] ?? '';
$footer_text = $settings['footer_text'] ?? '© ' . date('Y') . ' ລະບົບຈັດການວັດ. ສະຫງວນລິຂະສິດ.';

// คำนวณเวลาเหลือถึงสิ้นสุดการบำรุงรักษา (ถ้ามี)
$countdown_active = false;
$time_left = '';
if (!empty($maintenance_end_time) && strtotime($maintenance_end_time) > time()) {
    $countdown_active = true;
    $time_diff = strtotime($maintenance_end_time) - time();
    $hours = floor($time_diff / 3600);
    $minutes = floor(($time_diff % 3600) / 60);
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#B08542">
    <meta name="robots" content="noindex, nofollow">
    <title>ບໍາລຸງຮັກສາລະບົບ - <?= htmlspecialchars($site_name) ?></title>
    
    <!-- Preload critical fonts -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap"></noscript>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_url ?? '' ?>assets/css/monk-style.css">
    
    <style>
        /* Custom animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .floating { 
            animation: float 6s ease-in-out infinite; 
        }
        
        @keyframes pulse-soft {
            0% { opacity: 0.8; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 0.8; transform: scale(1); }
        }
        
        .pulse-animation {
            animation: pulse-soft 3s ease-in-out infinite;
        }
        
        .maintenance-bg {
            background: linear-gradient(135deg, #f7e3b4 0%, #ebd197 100%);
            background-size: cover;
            position: relative;
            min-height: 100vh;
        }
        
        .maintenance-bg::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1IiBoZWlnaHQ9IjUiPgo8cmVjdCB3aWR0aD0iNSIgaGVpZ2h0PSI1IiBmaWxsPSIjZmZmIiBmaWxsLW9wYWNpdHk9IjAuMSI+PC9yZWN0Pgo8L3N2Zz4=');
            opacity: 0.4;
            z-index: 0;
        }
        
        .temple-icon {
            width: 150px;
            height: 150px;
            background-color: #B08542;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 60px;
            margin: 0 auto 2rem auto;
            box-shadow: 0 10px 25px rgba(176, 133, 66, 0.3), 0 0 0 15px rgba(176, 133, 66, 0.1);
        }
        
        @media (max-width: 640px) {
            .temple-icon {
                width: 100px;
                height: 100px;
                font-size: 40px;
                margin-bottom: 1.5rem;
            }
        }
        
        .countdown-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .countdown-box {
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            min-width: 80px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="maintenance-bg">
    <div class="relative z-10 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-center sm:justify-start">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-place-of-worship text-white text-lg"></i>
                        </div>
                        <span class="ml-3 text-xl font-semibold text-amber-800"><?= htmlspecialchars($site_name) ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl w-full">
                <div class="text-center">
                    <div class="temple-icon floating">
                        <i class="fas fa-tools"></i>
                    </div>
                    
                    <h1 class="text-3xl sm:text-4xl font-bold text-amber-800 mb-4">
                        ບໍາລຸງຮັກສາລະບົບ
                    </h1>
                    
                    <div class="bg-white bg-opacity-70 backdrop-blur-sm rounded-2xl p-8 shadow-xl">
                        <p class="text-lg text-gray-700 mb-6">
                            <?= nl2br(htmlspecialchars($maintenance_message)) ?>
                        </p>
                        
                        <?php if ($countdown_active): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-amber-700 mb-2">ຄາດວ່າຈະສຳເລັດໃນ:</h3>
                            <div class="countdown-container" id="countdown">
                                <div class="countdown-box">
                                    <div class="text-2xl font-bold text-amber-700" id="hours"><?= $hours ?></div>
                                    <div class="text-xs text-amber-600">ຊົ່ວໂມງ</div>
                                </div>
                                <div class="countdown-box">
                                    <div class="text-2xl font-bold text-amber-700" id="minutes"><?= $minutes ?></div>
                                    <div class="text-xs text-amber-600">ນາທີ</div>
                                </div>
                                <div class="countdown-box">
                                    <div class="text-2xl font-bold text-amber-700" id="seconds">00</div>
                                    <div class="text-xs text-amber-600">ວິນາທີ</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="space-y-5 mb-6">
                            <h3 class="text-lg font-medium text-amber-700">ເຫດຜົນຂອງການບຳລຸງຮັກສາ:</h3>
                            <ul class="space-y-3 text-left">
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center mt-1 mr-3">
                                        <i class="fas fa-check text-amber-600 text-xs"></i>
                                    </div>
                                    <span>ປັບປຸງຄວາມສາມາດແລະປະສິດທິພາບຂອງລະບົບ</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center mt-1 mr-3">
                                        <i class="fas fa-check text-amber-600 text-xs"></i>
                                    </div>
                                    <span>ປັບປຸງຄວາມປອດໄພຂອງລະບົບ</span>
                                </li>
                                <li class="flex items-start">
                                    <div class="flex-shrink-0 w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center mt-1 mr-3">
                                        <i class="fas fa-check text-amber-600 text-xs"></i>
                                    </div>
                                    <span>ເພີ່ມຄຸນນະສົມບັດໃໝ່ແລະແກ້ໄຂບັນຫາ</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                                <a href="<?= $base_url ?? '' ?>" id="refresh-btn" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-amber-600 hover:bg-amber-700 shadow-md transition pulse-animation">
                                    <i class="fas fa-sync-alt mr-2"></i> ລອງໃໝ່ອີກຄັ້ງ
                                </a>
                                <?php if (!empty($settings['contact_page'])): ?>
                                <a href="<?= $base_url ?? '' ?><?= $settings['contact_page'] ?>" class="inline-flex items-center justify-center px-6 py-3 border border-amber-600 text-base font-medium rounded-lg text-amber-700 hover:bg-amber-50 shadow-sm transition">
                                    <i class="fas fa-envelope mr-2"></i> ຕິດຕໍ່ພວກເຮົາ
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="mt-8 flex flex-wrap justify-center gap-4 text-amber-800">
                        <?php if (!empty($contact_email)): ?>
                        <div class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="hover:text-amber-600 transition">
                                <?= htmlspecialchars($contact_email) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contact_phone)): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone mr-2"></i>
                            <a href="tel:<?= htmlspecialchars($contact_phone) ?>" class="hover:text-amber-600 transition">
                                <?= htmlspecialchars($contact_phone) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center text-amber-800">
                    <p><?= htmlspecialchars($footer_text) ?></p>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Background decoration -->
    <div class="absolute top-20 right-10 w-20 h-20 bg-amber-400 opacity-10 rounded-full floating" style="animation-delay: -2s;"></div>
    <div class="absolute top-80 left-10 w-16 h-16 bg-amber-600 opacity-10 rounded-full floating" style="animation-delay: -3s;"></div>
    <div class="absolute bottom-20 right-20 w-24 h-24 bg-amber-500 opacity-10 rounded-full floating" style="animation-delay: -5s;"></div>
    
    <?php if ($countdown_active): ?>
    <script>
        // นับถอยหลังเวลา
        const targetDate = new Date('<?= $maintenance_end_time ?>').getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;
            
            if (distance <= 0) {
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                
                // Refresh the page after countdown is complete
                setTimeout(function() {
                    location.reload();
                }, 5000);
                return;
            }
            
            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('hours').textContent = hours < 10 ? '0' + hours : hours;
            document.getElementById('minutes').textContent = minutes < 10 ? '0' + minutes : minutes;
            document.getElementById('seconds').textContent = seconds < 10 ? '0' + seconds : seconds;
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
    <?php endif; ?>
    
    <script>
        // Add click event to refresh button
        document.getElementById('refresh-btn').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show loading effect on button
            this.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> ກຳລັງກວດສອບ...';
            this.classList.add('opacity-75', 'cursor-not-allowed');
            
            // Reload the page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        });
    </script>
</body>
</html>