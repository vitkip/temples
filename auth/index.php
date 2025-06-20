<?php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';
// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Basic brute force protection
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5 && 
            time() - $_SESSION['last_attempt_time'] < 300) {
            $error = "ກະລຸນາລອງໃໝ່ພາຍຫຼັງ 5 ນາທີ";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ตรวจสอบสถานะผู้ใช้
                if ($user['status'] !== 'active') {
                    $error = "ບັນຊີຂອງທ່ານຍັງບໍ່ໄດ້ຮັບການອະນຸມັດ ຈາກຜູ້ດູແລລະບົບ ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ";
                    // หยุดการทำงาน ไม่สร้าง session และไม่ redirect ไปหน้า dashboard
                } else {
                    // เฉพาะผู้ใช้ที่มีสถานะ active เท่านั้นที่จะถูกสร้าง session
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'temple_id' => $user['temple_id'],
                        'name' => $user['name']
                    ];

                    header("Location: {$base_url}dashboard.php");
                    exit;
                }
            } else {
                $_SESSION['login_attempts'] = isset($_SESSION['login_attempts']) ? 
                                            $_SESSION['login_attempts'] + 1 : 1;
                $_SESSION['last_attempt_time'] = time();
                $error = "ຊື່ຜູ້ໃຊ້ ຫຼື ລະຫັດຜິດ";
            }
        }
    }
}
?>

<!-- HTML ແບບຟອມລ໋ອກອິນ -->
<!DOCTYPE html>
<html lang="lo">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>ເຂົ້າລະບົບ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
  <link rel="stylesheet" href="<?= $base_url ?>auth/css/auth-css.css">
</head>
<body>
  <div class="login-container">
    <div class="w-full max-w-md px-2 sm:px-6">
      <div class="login-card bg-white">
        <!-- Header -->
        <div class="login-header">
          <div class="temple-icon">
            <div class="icon-circle mx-auto w-16 h-16">
              <img class="h-8 w-auto" src="<?= $base_url ?>assets/images/logo.png" alt="<?= htmlspecialchars($site_name ?? 'ລະບົບຈັດການຂໍ້ມູນວັດ') ?>">
            </div>
          </div>
          <h2 class="text-2xl font-bold text-gray-800 mt-3">ເຂົ້າລະບົບ</h2>
          <p class="text-amber-700 mt-2">ລະບົບຈັດການຂໍ້ມູນວັດ</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
          <?php if (isset($error)) : ?>
            <div class="error-container">
              <i class="fas fa-exclamation-circle error-icon"></i>
              <p class="error-message"><?= $error ?></p>
            </div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- Username Input -->
            <div class="input-group">
              <div class="input-icon-wrapper">
                <div class="icon-circle">
                  <i class="fas fa-user text-sm"></i>
                </div>
              </div>
              <input 
                type="text" 
                name="username" 
                placeholder="ຊື່ຜູ້ໃຊ້" 
                required 
                class="input-field"
                value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
              >
            </div>
            
            <!-- Password Input -->
            <div class="input-group">
              <div class="input-icon-wrapper">
                <div class="icon-circle">
                  <i class="fas fa-lock text-sm"></i>
                </div>
              </div>
              <input 
                type="password" 
                name="password" 
                id="password" 
                placeholder="ລະຫັດຜ່ານ" 
                required 
                class="input-field"
              >
              <button 
                type="button" 
                id="togglePassword" 
                class="toggle-password"
              >
                <i class="fas fa-eye"></i>
              </button>
            </div>
            
            <!-- Remember me & Forgot password -->
            <div class="flex items-center justify-between mb-4">
              <label class="checkbox-container">
                <input type="checkbox" name="remember_me">
                <span class="text-sm text-gray-600">ຈົດຈໍາການເຂົ້າລະບົບ</span>
              </label>
              <a href="<?= $base_url ?>auth/reset_password.php" class="text-sm text-amber-600 hover:text-amber-800">ລືມລະຫັດຜ່ານ?</a>
            </div>
            
            <!-- Login Button -->
            <button 
              type="submit" 
              class="btn-login ripple"
            >
              <i class="fas fa-sign-in-alt mr-2"></i> ເຂົ້າລະບົບ
            </button>
          </form>

          <!-- Register Link -->
          <div class="mt-6 text-center">
            <p class="text-gray-600">
              ຍັງບໍ່ມີບັນຊີບໍ?
              <a href="<?= $base_url ?>auth/register.php" class="text-amber-600 hover:text-amber-800 font-medium">
                ລົງທະບຽນ
              </a>
            </p>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="bg-gray-50 py-4 px-6 border-t border-amber-100 text-center">
          <a href="<?= $base_url ?>" class="text-amber-600 hover:text-amber-800 flex items-center justify-center">
            <i class="fas fa-home mr-2"></i> ກັບໄປໜ້າຫຼັກ
          </a>
        </div>
      </div>
      
      <!-- Copyright Text -->
      <div class="text-center mt-4 mb-6">
        <p class="text-sm text-amber-800/70">
          © <?= date('Y') ?> ລະບົບຈັດການຂໍ້ມູນວັດ. ສະຫງວນລິຂະສິດທັງໝົດ.
        </p>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle password visibility
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Toggle eye icon
        const eyeIcon = this.querySelector('i');
        eyeIcon.classList.toggle('fa-eye');
        eyeIcon.classList.toggle('fa-eye-slash');
      });

      // Add focus animations
      const inputs = document.querySelectorAll('.input-field');
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentNode.querySelector('.icon-circle').style.background = 'linear-gradient(135deg, #D4A762, #B08542)';
          this.parentNode.querySelector('.icon-circle').style.color = 'white';
        });
        
        input.addEventListener('blur', function() {
          this.parentNode.querySelector('.icon-circle').style.background = 'linear-gradient(135deg, #F5EFE6, #E9DFC7)';
          this.parentNode.querySelector('.icon-circle').style.color = '#B08542';
        });
      });

      // Add ripple effect
      function createRipple(event) {
        const button = event.currentTarget;
        
        const circle = document.createElement("span");
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add("ripple");
        
        const ripple = button.getElementsByClassName("ripple")[0];
        
        if (ripple) {
          ripple.remove();
        }
        
        button.appendChild(circle);
      }
      
      const buttons = document.querySelectorAll('.ripple');
      buttons.forEach(button => {
        button.addEventListener('click', createRipple);
      });
    });
  </script>
</body>
</html>
