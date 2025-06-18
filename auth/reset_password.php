<?php
// filepath: c:\xampp\htdocs\temples\auth\reset_password.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// การใช้งาน PHPMailer สำหรับส่งอีเมล
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

// ดึงการตั้งค่าอีเมลจากฐานข้อมูล
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // ไม่ต้องทำอะไร
}

$error = '';
$success = '';
$step = 'request'; // สถานะเริ่มต้น: request หรือ reset

// ตรวจสอบว่าเป็นการเข้าถึงหน้ารีเซ็ตรหัสผ่านผ่าน token หรือไม่
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $step = 'reset';
    
    // ตรวจสอบความถูกต้องของ token
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'ລິ້ງຄ໌ບໍ່ຖືກຕ້ອງ ຫຼື ໝົດອາຍຸແລ້ວ ກະລຸນາຂໍລິ້ງຄ໌ໃໝ່';
        $step = 'request'; // กลับไปที่หน้าขอรีเซ็ตรหัสผ่าน
    }
}

// ส่วนประมวลผลการขอรีเซ็ตรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'ກະລຸນາປ້ອນທີ່ຢູ່ອີເມວ';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ';
    } else {
        // ตรวจสอบว่ามีอีเมลในระบบหรือไม่
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // สร้าง token สำหรับรีเซ็ตรหัสผ่าน
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // หมดอายุใน 1 ชั่วโมง
            
            // บันทึก token ลงในฐานข้อมูล
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
            $stmt->execute([$token, $expires, $email]);
            
            // สร้าง URL สำหรับรีเซ็ตรหัสผ่าน
            $reset_url = $base_url . 'auth/reset_password.php?token=' . $token;
            
            // ส่งอีเมลที่มีลิงก์สำหรับรีเซ็ตรหัสผ่าน
            try {
                // ตรวจสอบสภาพแวดล้อมและปรับการตั้งค่า SMTP
                $is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['SERVER_ADDR'], '127.0.0.1') !== false);
                
                $mail = new PHPMailer(true);
                
                if ($is_local) {
                    // ตั้งค่าสำหรับเซิร์ฟเวอร์จำลอง
                    $mail->isSMTP();
                    $mail->SMTPDebug = 2;
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'phathasyla@gmail.com';
                    $mail->Password = 'zjxm uwez opww eprf';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                } else {
                    // ตั้งค่าสำหรับเซิร์ฟเวอร์จริง - ใช้ SMTP ของโฮสติ้ง
                    $mail->isSMTP();
                    $mail->SMTPDebug = 0; // เปลี่ยนเป็น 2 เพื่อดีบัก
                    $mail->Host = 'mail.laotemples.com'; // เปลี่ยนเป็นโฮสต์ SMTP ของโฮสติ้งคุณ
                    $mail->SMTPAuth = true;
                    $mail->Username = 'info@laotemples.com'; // เปลี่ยนเป็นอีเมลที่มีในโฮสติ้ง
                    $mail->Password = '@DKvon0328117'; // รหัสผ่านของอีเมลในโฮสติ้ง
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                    
                    // เพิ่มตัวเลือกสำหรับการเชื่อมต่อ SSL ที่มีปัญหา
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                }
                
                $mail->CharSet = 'UTF-8';
                
                // ตั้งค่าผู้ส่งให้ตรงกับอีเมลที่ใช้ส่ง
                if ($is_local) {
                    $mail->setFrom('phathasyla@gmail.com', $settings['site_name'] ?? 'ລະບົບຈັດການວັດ');
                } else {
                    $mail->setFrom('info@laotemples.com', $settings['site_name'] ?? 'ລະບົບຈັດການວັດ');
                }
                
                $mail->addAddress($email, $user['name']);
                
                // ทางเลือกที่ 2 - ใช้ mail() ฟังก์ชันของ PHP หากระบบโฮสติ้งสนับสนุน
                // ถ้า SMTP ล้มเหลว ให้ลองใช้ mail() แทน
                /*
                if (!$is_local && !$mail->send()) {
                    $mail = new PHPMailer(true);
                    $mail->isMail();
                    $mail->setFrom('info@yourdomain.com', $settings['site_name'] ?? 'ລະບົບຈັດການວັດ');
                    $mail->addAddress($email, $user['name']);
                }
                */
                
                $mail->isHTML(true);
                $mail->Subject = 'ຄຳຂໍປ່ຽນລະຫັດຜ່ານ';
                $mail->Body = '
                    <div style="font-family: Phetsarath OT, Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px;">
                        <h2>ຄຳຂໍປ່ຽນລະຫັດຜ່ານ</h2>
                        <p>ສະບາຍດີ ' . htmlspecialchars($user['name']) . ',</p>
                        <p>ທ່ານໄດ້ຂໍປ່ຽນລະຫັດຜ່ານສຳລັບບັນຊີຂອງທ່ານ. ເພື່ອປ່ຽນລະຫັດຜ່ານ, ກະລຸນາກົດທີ່ປຸ່ມຂ້າງລຸ່ມ:</p>
                        <p style="text-align: center;">
                            <a href="' . $reset_url . '" style="display: inline-block; padding: 10px 20px; background-color: #B08542; color: white; text-decoration: none; border-radius: 5px;">ປ່ຽນລະຫັດຜ່ານ</a>
                        </p>
                        <p>ຫຼືທ່ານສາມາດວາງລິ້ງຄ໌ນີ້ໃນຊ່ອງທີ່ຢູ່ເວັບໄຊທ໌ຂອງທ່ານ:</p>
                        <p>' . $reset_url . '</p>
                        <p>ໝາຍເຫດ: ລິ້ງຄ໌ນີ້ຈະໝົດອາຍຸໃນ 1 ຊົ່ວໂມງ.</p>
                        <p>ຖ້າຫາກທ່ານບໍ່ໄດ້ຮ້ອງຂໍການປ່ຽນລະຫັດຜ່ານນີ້, ກະລຸນາບໍ່ຕ້ອງສົນໃຈຂໍ້ຄວາມນີ້.</p>
                        <p>ຂໍຂອບໃຈ,<br>' . ($settings['site_name'] ?? 'ລະບົບຈັດການວັດ') . '</p>
                    </div>
                ';
                $mail->AltBody = 'ສະບາຍດີ ' . $user['name'] . ', ທ່ານໄດ້ຂໍປ່ຽນລະຫັດຜ່ານສຳລັດບັນຊີຂອງທ່ານ. ເພື່ອປ່ຽນລະຫັດຜ່ານ, ກະລຸນາເຂົ້າເບິ່ງ: ' . $reset_url;
                
                $mail->send();
                $success = 'ສົ່ງຄຳແນະນຳໃນການປ່ຽນລະຫັດຜ່ານໄປຫາອີເມວຂອງທ່ານແລ້ວ. ກະລຸນາກວດເບິ່ງກ່ອງຈົດໝາຍຂອງທ່ານ.';
    
                // บันทึก log สำเร็จ
                error_log("Email sent successfully to: " . $email . " from: " . ($is_local ? 'localhost' : 'production server'));
            } catch (Exception $e) {
                // บันทึก log อย่างละเอียด
                error_log("EMAIL ERROR DETAILS - To: " . $email);
                error_log("Server type: " . ($is_local ? 'localhost' : 'production server'));
                error_log("Mail error: " . $mail->ErrorInfo);
                error_log("Exception: " . $e->getMessage());
                
                $error = 'ບໍ່ສາມາດສົ່ງອີເມວໄດ້. ກະລຸນາລອງໃໝ່ພາຍຫຼັງ ຫຼື ຕິດຕໍ່ຜູ້ດູແລລະບົບ.';
            }
        } else {
            // เพื่อความปลอดภัย ไม่ควรเปิดเผยว่ามีหรือไม่มีอีเมลนี้ในระบบ
            $success = 'ສົ່ງຄຳແນະນຳໃນການປ່ຽນລະຫັດຜ່ານໄປຫາອີເມວຂອງທ່ານແລ້ວ. ກະລຸນາກວດເບິ່ງກ່ອນຈົດໝາຍຂອງທ່ານ.';
        }
    }
}

// ส่วนประมวลผลการตั้งรหัสผ่านใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // ตรวจสอบรหัสผ่าน
    if (empty($password)) {
        $error = 'ກະລຸນາປ້ອນລະຫັດຜ່ານ';
    } elseif (strlen($password) < 6) {
        $error = 'ລະຫັດຜ່ານຕ້ອງມີຢ່າງນ້ອຍ 6 ຕົວອັກສອນ';
    } elseif ($password !== $confirm_password) {
        $error = 'ລະຫັດຜ່ານບໍ່ຕົງກັນ';
    } else {
        // ตรวจสอบความถูกต้องของ token
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // อัปเดตรหัสผ่านและลบ token
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            
            $success = 'ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ ທ່ານສາມາດເຂົ້າສູ່ລະບົບໄດ້ດ້ວຍລະຫັດຜ່ານໃໝ່';
            $step = 'completed'; // เปลี่ยนสถานะเป็นเสร็จสมบูรณ์
        } else {
            $error = 'ລິ້ງຄ໌ບໍ່ຖືກຕ້ອງ ຫຼື ໝົດອາຍຸແລ້ວ ກະລຸນາຂໍລິ້ງຄ໌ໃໝ່';
            $step = 'request'; // กลับไปที่หน้าขอรีเซ็ตรหัสผ่าน
        }
    }
}

$page_title = 'ປ່ຽນລະຫັດຜ່ານ';
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= $settings['site_name'] ?? 'ລະບົບຈັດການວັດ' ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- นำเข้า temples-style.css -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/temples-style.css">
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/reset-pass.css">
    
</head>
<body>
    

    <div class="auth-container">
        <div class="form-card">
            <div class="form-header">
                <h1 class="text-xl font-bold text-center"><?= $page_title ?></h1>
                <?php if ($step === 'request'): ?>
                <p class="text-xs md:text-sm text-white text-center mt-2 opacity-90">
                    ກະລຸນາປ້ອນອີເມວທີ່ໃຊ້ລົງທະບຽນ ເພື່ອຮັບຄຳແນະນຳໃນການປ່ຽນລະຫັດຜ່ານ
                </p>
                <?php elseif ($step === 'reset'): ?>
                <p class="text-xs md:text-sm text-white text-center mt-2 opacity-90">
                    ກະລຸນາຕັ້ງລະຫັດຜ່ານໃໝ່ສຳລັບບັນຊີຂອງທ່ານ
                </p>
                <?php else: ?>
                <p class="text-xs md:text-sm text-white text-center mt-2 opacity-90">
                    ດຳເນີນການປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ
                </p>
                <?php endif; ?>
            </div>
            
            <div class="form-body">
                <?php if ($step === 'completed'): ?>
                    <div class="text-center">
                        <div class="success-checkmark">
                            <svg class="w-full h-full" viewBox="0 0 52 52">
                                <circle class="check-circle" cx="26" cy="26" r="25" fill="none" />
                                <path class="check-icon" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" stroke="#10B981" stroke-width="2" />
                            </svg>
                        </div>
                        
                        <h2 class="text-xl font-semibold text-gray-900 mt-4">
                            ປ່ຽນລະຫັດຜ່ານສຳເລັດແລ້ວ
                        </h2>
                        <p class="mt-2 text-sm text-gray-600">
                            ທ່ານສາມາດເຂົ້າສູ່ລະບົບດ້ວຍລະຫັດຜ່ານໃໝ່ຂອງທ່ານໄດ້ແລ້ວ
                        </p>
                        
                        <div class="mt-6">
                            <a href="<?= $base_url ?>auth/login.php" class="btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>ເຂົ້າສູ່ລະບົບ</span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($step === 'reset'): ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?= $error ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="passwordResetForm">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        
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
                                    <i class="fas fa-key"></i>
                                    ບັນທຶກລະຫັດຜ່ານໃໝ່
                                </span>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
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
                    
                    <form method="POST" action="" id="requestResetForm">
                        <div class="input-group">
                            <div class="relative">
                                <input type="email" id="email" name="email" class="input-field" placeholder=" " required autocomplete="email">
                                <label for="email" class="floating-label">ອີເມວ</label>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="request_reset" class="btn-primary" id="requestResetBtn">
                                <span class="loading-spinner"></span>
                                <span class="btn-text">
                                    <i class="fas fa-envelope"></i>
                                    ສົ່ງຄຳແນະນຳ
                                </span>
                            </button>
                            
                            <div class="mt-4 text-center">
                                <a href="<?= $base_url ?>auth/login.php" class="text-amber-600 hover:text-amber-700 text-sm font-medium inline-flex items-center">
                                    <i class="fas fa-arrow-left mr-1"></i> 
                                    ກັບໄປຫາໜ້າເຂົ້າສູ່ລະບົບ
                                </a>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
                
                <!-- เพิ่มตัวเลือกในส่วนด้านล่างของฟอร์ม -->
                <div class="mt-4 text-center">
                    <div class="py-2 border-t border-gray-200">
                        <p class="text-sm text-gray-600 my-2">ຫຼື</p>
                        <a href="<?= $base_url ?>auth/reset_password_sms.php" class="text-amber-600 hover:text-amber-700 font-medium">
                            <i class="fas fa-mobile-alt mr-1"></i> ປ່ຽນລະຫັດຜ່ານດ້ວຍການຢືນຢັນຜ່ານ SMS
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>    
    <script src="<?= $base_url ?>assets/js/reset-pass.js"></script>
</body>
</html>