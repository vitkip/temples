<?php
// filepath: c:\xampp\htdocs\temples\auth\reset_password_sms.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../config/sms_functions.php';

$error = '';
$success = '';
$step = 'request'; // สถานะเริ่มต้น: request, verify_otp, reset

// ตรวจสอบว่ามี session OTP หรือไม่
if (isset($_SESSION['reset_user_id']) && !isset($_GET['resend'])) {
    $step = 'verify_otp';
    
    if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified']) {
        $step = 'reset';
    }
}

// ส่วนประมวลผลการขอรีเซ็ตรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $phone = trim($_POST['phone']);
    
    // ฟอร์แมตเบอร์โทรให้อยู่ในรูปแบบสากล
    $phone = format_phone_number($phone);
    
    if (empty($phone)) {
        $error = 'ກະລຸນາປ້ອນເບີໂທລະສັບ';
    } else {
        // ตรวจสอบว่ามีเบอร์โทรในระบบหรือไม่
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if ($user) {
            // สร้าง OTP
            $otp = generate_otp();
            
            // บันทึก OTP ลงในฐานข้อมูล
            if (save_otp($user['id'], $otp)) {
                // ส่ง SMS
                if (send_otp_sms($phone, $otp)) {
                    // เก็บ user_id ใน session
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_phone'] = $phone;
                    $success = 'ສົ່ງລະຫັດຢືນຢັນໄປຍັງເບີໂທຂອງທ່ານແລ້ວ';
                    $step = 'verify_otp';
                } else {
                    $error = 'ບໍ່ສາມາດສົ່ງລະຫັດຢືນຢັນໄດ້ ກະລຸນາລອງໃໝ່ພາຍຫຼັງ';
                }
            } else {
                $error = 'ເກີດຂໍ້ຜິດພາດ ກະລຸນາລອງໃໝ່ພາຍຫຼັງ';
            }
        } else {
            // เพื่อความปลอดภัย ไม่ควรเปิดเผยว่ามีหรือไม่มีเบอร์โทรนี้ในระบบ
            $error = 'ເບີໂທນີ້ບໍ່ໄດ້ລົງທະບຽນໃນລະບົບ';
        }
    }
}

// ส่วนประมวลผลการยืนยัน OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    
    if (empty($otp)) {
        $error = 'ກະລຸນາປ້ອນລະຫັດຢືນຢັນ';
    } else if (!isset($_SESSION['reset_user_id'])) {
        $error = 'ເກີດຂໍ້ຜິດພາດ ກະລຸນາລອງໃໝ່';
        $step = 'request';
    } else {
        // ตรวจสอบ OTP
        if (verify_otp($_SESSION['reset_user_id'], $otp)) {
            $_SESSION['reset_verified'] = true;
            $success = 'ຢືນຢັນສຳເລັດແລ້ວ';
            $step = 'reset';
        } else {
            $error = 'ລະຫັດຢືນຢັນບໍ່ຖືກຕ້ອງ ຫຼື ໝົດອາຍຸແລ້ວ';
        }
    }
}

// ส่วนประมวลผลการตั้งรหัสผ่านใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // ตรวจสอบรหัสผ่าน
    if (empty($password)) {
        $error = 'ກະລຸນາປ້ອນລະຫັດຜ່ານ';
    } elseif (strlen($password) < 6) {
        $error = 'ລະຫັດຜ່ານຕ້ອງມີຢ່າງນ້ອຍ 6 ຕົວອັກສອນ';
    } elseif ($password !== $confirm_password) {
        $error = 'ລະຫັດຜ່ານບໍ່ຕົງກັນ';
    } elseif (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
        $error = 'ເກີດຂໍ້ຜິດພາດ ກະລຸນາລອງໃໝ່';
        $step = 'request';
    } else {
        // อัปเดตรหัสผ่าน
        $user_id = $_SESSION['reset_user_id'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // ลบข้อมูลรีเซ็ตรหัสผ่านใน session
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_phone']);
            unset($_SESSION['reset_verified']);
            
            $success = 'ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ ທ່ານສາມາດເຂົ້າສູ່ລະບົບໄດ້ດ້ວຍລະຫັດຜ່ານໃໝ່';
            $step = 'completed';
        } catch (PDOException $e) {
            $error = 'ເກີດຂໍ້ຜິດພາດ ກະລຸນາລອງໃໝ່ພາຍຫຼັງ';
        }
    }
}

// ส่วนการขอส่ง OTP ใหม่
if (isset($_GET['resend']) && isset($_SESSION['reset_user_id']) && isset($_SESSION['reset_phone'])) {
    $user_id = $_SESSION['reset_user_id'];
    $phone = $_SESSION['reset_phone'];
    
    // สร้าง OTP ใหม่
    $otp = generate_otp();
    
    // บันทึก OTP ใหม่ลงในฐานข้อมูล
    if (save_otp($user_id, $otp)) {
        // ส่ง SMS
        if (send_otp_sms($phone, $otp)) {
            $success = 'ສົ່ງລະຫັດຢືນຢັນໃໝ່ໄປຍັງເບີໂທຂອງທ່ານແລ້ວ';
        } else {
            $error = 'ບໍ່ສາມາດສົ່ງລະຫັດຢືນຢັນໄດ້ ກະລຸນາລອງໃໝ່ພາຍຫຼັງ';
        }
    } else {
        $error = 'ເກີດຂໍ້ຜິດພາດ ກະລຸນາລອງໃໝ່ພາຍຫຼັງ';
    }
    
    $step = 'verify_otp';
}

/**
 * ฟอร์แมตเบอร์โทรให้อยู่ในรูปแบบสากล
 * 
 * @param string $phone เบอร์โทรศัพท์
 * @return string เบอร์โทรในรูปแบบสากล
 */
function format_phone_number($phone) {
    // ลบอักขระที่ไม่ใช่ตัวเลข
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // ถ้าเริ่มต้นด้วย 0 ให้เปลี่ยนเป็น +856 (รหัสประเทศลาว)
    if (substr($phone, 0, 1) === '0') {
        $phone = '856' . substr($phone, 1);
    }
    
    // ถ้ายังไม่มีเครื่องหมาย + ให้เพิ่มเข้าไป
    if (substr($phone, 0, 1) !== '+') {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

$page_title = 'ປ່ຽນລະຫັດຜ່ານຜ່ານ SMS';
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= $settings['site_name'] ?? 'ລະບົບຈັດການວັດ' ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: {
                50: '#fcf9f2',
                100: '#f8f0db',
                200: '#f0deb1',
                300: '#e7c782',
                400: '#dfb45d',
                500: '#d4a762',
                600: '#b08542',
                700: '#8e6b35',
                800: '#6d521f',
                900: '#4b3612',
              }
            },
            animation: {
              'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            }
          }
        }
      }
    </script>
    
    <!-- นำเข้า temples-style.css -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/temples-style.css">
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/sms-style.css">
    
</head>
<body>
    <!-- Mobile Header (Displays only on small screens) -->
    <div class="mobile-header sm:hidden">
        <a href="<?= $base_url ?>" class="flex items-center">
            <img src="<?= $base_url ?>assets/images/logo.png" alt="Logo" class="h-8 w-auto mr-2">
            <span class="text-gray-800 font-medium text-lg"><?= $settings['site_name'] ?? 'ລະບົບຈັດການວັດ' ?></span>
        </a>
    </div>


    <div class="auth-container">
        <div class="form-card">
            <div class="form-header">
                <h1 class="text-2xl font-bold text-center"><?= $page_title ?></h1>
                <?php if ($step === 'request'): ?>
                <p class="text-sm md:text-base text-white text-center mt-2 opacity-90">
                    ກະລຸນາປ້ອນເບີໂທລະສັບທີ່ລົງທະບຽນໄວ້
                </p>
                <?php elseif ($step === 'verify_otp'): ?>
                <p class="text-sm md:text-base text-white text-center mt-2 opacity-90">
                    ກະລຸນາປ້ອນລະຫັດຢືນຢັນທີ່ສົ່ງໄປທາງ SMS
                </p>
                <?php elseif ($step === 'reset'): ?>
                <p class="text-sm md:text-base text-white text-center mt-2 opacity-90">
                    ກະລຸນາຕັ້ງລະຫັດຜ່ານໃໝ່ສຳລັບບັນຊີຂອງທ່ານ
                </p>
                <?php else: ?>
                <p class="text-sm md:text-base text-white text-center mt-2 opacity-90">
                    ດຳເນີນການປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ
                </p>
                <?php endif; ?>
            </div>
            
            <div class="form-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?= ($step === 'request' || $step === 'verify_otp' || $step === 'reset' || $step === 'completed') ? 'step-completed' : '' ?>">
                        <div class="step-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-label">ເບີໂທລະສັບ</div>
                    </div>
                    <div class="step <?= ($step === 'verify_otp' || $step === 'reset' || $step === 'completed') ? 'step-completed' : ($step === 'request' ? '' : 'step-active') ?>">
                        <div class="step-icon">
                            <?php if ($step === 'verify_otp' || $step === 'reset' || $step === 'completed'): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <i class="fas fa-sms"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-label">ຢືນຢັນ OTP</div>
                    </div>
                    <div class="step <?= ($step === 'reset') ? 'step-active' : (($step === 'completed') ? 'step-completed' : '') ?>">
                        <div class="step-icon">
                            <?php if ($step === 'completed'): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <i class="fas fa-key"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-line"></div>
                        <div class="step-label">ລະຫັດຜ່ານໃໝ່</div>
                    </div>
                    <div class="step <?= ($step === 'completed') ? 'step-completed' : '' ?>">
                        <div class="step-icon">
                            <?php if ($step === 'completed'): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <i class="fas fa-flag-checkered"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-label">ສຳເລັດ</div>
                    </div>
                </div>
            
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'completed'): ?>
                    <div class="text-center">
                        <div class="success-checkmark">
                            <svg viewBox="0 0 52 52">
                                <circle class="check-circle" cx="26" cy="26" r="23" fill="none" stroke="#10B981" stroke-width="4" />
                                <path class="check-icon" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" stroke="#10B981" stroke-width="4" />
                            </svg>
                        </div>
                        
                        <h2 class="text-2xl font-bold text-gray-900 mt-4">
                            ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ
                        </h2>
                        <p class="mt-3 text-md text-gray-600">
                            ທ່ານສາມາດເຂົ້າສູ່ລະບົບດ້ວຍລະຫັດຜ່ານໃໝ່ຂອງທ່ານໄດ້ແລ້ວ
                        </p>
                        
                        <div class="mt-6">
                            <a href="<?= $base_url ?>auth/login.php" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>ເຂົ້າສູ່ລະບົບ</span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($step === 'verify_otp'): ?>
                    <form method="POST" action="" id="verifyOtpForm">
                        <div class="text-center mb-4">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-50 rounded-full mb-2">
                                <i class="fas fa-sms text-3xl text-primary-600"></i>
                            </div>
                            <p class="text-center text-gray-700 mb-1">
                                ລະຫັດ OTP ໄດ້ຖືກສົ່ງໄປທີ່ເບີໂທ
                            </p>
                            <p class="text-lg font-semibold text-gray-900">
                                <?= isset($_SESSION['reset_phone']) ? htmlspecialchars($_SESSION['reset_phone']) : '' ?>
                            </p>
                        </div>
                        
                        <div class="text-center mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                ປ້ອນລະຫັດ 6 ຫຼັກທີ່ໄດ້ຮັບ
                            </label>
                            <div class="otp-inputs">
                                <input type="number" id="otp1" class="otp-input" min="0" max="9" required>
                                <input type="number" id="otp2" class="otp-input" min="0" max="9" required>
                                <input type="number" id="otp3" class="otp-input" min="0" max="9" required>
                                <input type="number" id="otp4" class="otp-input" min="0" max="9" required>
                                <input type="number" id="otp5" class="otp-input" min="0" max="9" required>
                                <input type="number" id="otp6" class="otp-input" min="0" max="9" required>
                            </div>
                            <input type="hidden" id="otp" name="otp" required>
                            
                            <div class="countdown">
                                <span id="countdown-text">ລະຫັດຈະໝົດອາຍຸໃນ <span id="countdown">15:00</span> ນາທີ</span>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="verify_otp" class="btn-primary" id="verifyOtpBtn">
                                <span class="loading-spinner"></span>
                                <span class="btn-text">
                                    <i class="fas fa-check-circle"></i>
                                    ຢືນຢັນລະຫັດ
                                </span>
                            </button>
                            
                            <div class="mt-5 text-center">
                                <p class="text-gray-600 mb-3">ບໍ່ໄດ້ຮັບ SMS?</p>
                                <a href="?resend=1" class="link-primary">
                                    <i class="fas fa-sync-alt"></i> ສົ່ງລະຫັດຢືນຢັນໃໝ່
                                </a>
                                
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <a href="<?= $base_url ?>auth/login.php" class="link-primary">
                                        <i class="fas fa-arrow-left"></i> 
                                        ກັບໄປຫາໜ້າເຂົ້າສູ່ລະບົບ
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php elseif ($step === 'reset'): ?>
                    <form method="POST" action="" id="passwordResetForm">
                        <div class="text-center mb-5">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-50 rounded-full mb-2">
                                <i class="fas fa-key text-3xl text-primary-600"></i>
                            </div>
                            <p class="text-gray-700">
                                ຍືນຍັນຕົວຕົນສຳເລັດແລ້ວ. ກະລຸນາຕັ້ງລະຫັດຜ່ານໃໝ່ຂອງທ່ານ.
                            </p>
                        </div>
                    
                        <div class="input-group">
                            <div class="relative">
                                <input type="password" id="password" name="password" class="input-field" placeholder=" " required minlength="6" autocomplete="new-password">
                                <label for="password" class="floating-label">ລະຫັດຜ່ານໃໝ່</label>
                                <span class="toggle-password" onclick="togglePasswordVisibility('password', 'passwordToggleIcon')">
                                    <i id="passwordToggleIcon" class="fas fa-eye"></i>
                                </span>
                            </div>
                            
                            <div class="password-strength">
                                <div id="passwordStrengthBar" class="password-strength-bar"></div>
                            </div>
                            
                            <div id="passwordStrengthText" class="password-strength-text text-gray-500">
                                <i class="fas fa-info-circle"></i>
                                <span>ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</span>
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder=" " required autocomplete="new-password">
                                <label for="confirm_password" class="floating-label">ຢືນຢັນລະຫັດຜ່ານໃໝ່</label>
                                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'confirmPasswordToggleIcon')">
                                    <i id="confirmPasswordToggleIcon" class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div id="passwordMatchText" class="password-strength-text"></div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="reset_password" class="btn-primary" id="resetPasswordBtn">
                                <span class="loading-spinner"></span>
                                <span class="btn-text">
                                    <i class="fas fa-save"></i>
                                    ບັນທຶກລະຫັດຜ່ານໃໝ່
                                </span>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <form method="POST" action="" id="requestResetForm">
                        <div class="text-center mb-5">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-50 rounded-full mb-2">
                                <i class="fas fa-mobile-alt text-3xl text-primary-600"></i>
                            </div>
                            <p class="text-gray-700">
                                ເພື່ອຢືນຢັນຕົວຕົນຂອງທ່ານ ກະລຸນາປ້ອນເບີໂທລະສັບທີ່ລົງທະບຽນໄວ້ໃນລະບົບ
                            </p>
                        </div>

                        <div class="input-group">
                            <div class="relative">
                                <input type="tel" id="phone" name="phone" class="input-field" placeholder=" " required>
                                <label for="phone" class="floating-label">ເບີໂທລະສັບ</label>
                                <i class="fas fa-mobile-alt absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">ຕົວຢ່າງ: 020XXXXXXXX ຫຼື +85620XXXXXXXX</p>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="request_reset" class="btn-primary" id="requestResetBtn">
                                <span class="loading-spinner"></span>
                                <span class="btn-text">
                                    <i class="fas fa-paper-plane"></i>
                                    ສົ່ງລະຫັດຢືນຢັນ
                                </span>
                            </button>
                            
                            <div class="mt-5 flex flex-col space-y-4 text-center">
                                <a href="<?= $base_url ?>auth/reset_password.php" class="link-primary">
                                    <i class="fas fa-envelope"></i> ໃຊ້ອີເມວແທນ
                                </a>
                                
                                <div class="pt-4 border-t border-gray-200">
                                    <a href="<?= $base_url ?>auth/" class="link-primary">
                                        <i class="fas fa-arrow-left"></i> 
                                        ກັບໄປຫາໜ້າເຂົ້າສູ່ລະບົບ
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

   
    <script src="<?= $base_url ?>assets/js/sms.js"></script>
</body>
</html>