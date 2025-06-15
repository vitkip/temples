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
                // Set remember me cookie if requested
                if ($user['status'] !== 'active') {
                    $error = "ບັນຊີຂອງທ່ານຍັງບໍ່ໄດ້ຮັບການອະນຸມັດ ຈາກຜູ້ດູແລລະບົບ ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ";                   
                }

                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'temple_id' => $user['temple_id'],
                    'name' => $user['name']
                ];

                header("Location: {$base_url}dashboard.php");
                exit;
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
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@400;500;600;700&display=swap');
    
    :root {
      --primary-color: #D4A762;
      --primary-dark: #B08542;
      --background-color: #F9F5F0;
      --text-color: #333333;
    }
    
    body {
      font-family: 'Noto Sans Lao', sans-serif;
      -webkit-tap-highlight-color: transparent; /* ลบไฮไลท์สีฟ้าเมื่อแตะบนมือถือ */
    }
    
    .login-container {
      background-image: url('../assets/images/thai-pattern.svg');
      background-color: var(--background-color);
      background-repeat: repeat;
      background-size: 200px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    
    .login-card {
      width: 100%;
      max-width: 420px;
      margin: 0 auto;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      animation: fadeInUp 0.5s ease-out forwards;
    }
    
    .login-header {
      position: relative;
      padding: 2rem 1.5rem;
      background: linear-gradient(135deg, #F5EFE6, #E9DFC7);
      text-align: center;
    }
    
    .login-header::before {
      content: "";
      position: absolute;
      top: -50px;
      left: -50px;
      width: 200px;
      height: 200px;
      background-image: url('../assets/images/temple-pattern-light.svg');
      background-size: cover;
      background-position: center;
      opacity: 0.1;
      z-index: 0;
    }
    
    .form-container {
      padding: 1.5rem;
    }
    
    /* Input styling */
    .input-group {
      position: relative;
      margin-bottom: 1.25rem;
    }
    
    .input-icon-wrapper {
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      margin-left: 12px;
      z-index: 2;
    }
    
    .icon-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #F5EFE6, #E9DFC7);
      color: var(--primary-dark);
      transition: all 0.3s ease;
    }
    
    /* แก้ไขส่วนของ input field */
    .input-field {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 58px; /* ปรับ padding ให้มีค่าคงที่ */
      border: 2px solid rgba(212, 167, 98, 0.2);
      border-radius: 0.75rem;
      font-size: 1rem;
      line-height: 1.5;
      transition: all 0.3s ease;
      background-color: #fff;
      outline: none;
      -webkit-appearance: none;
    }
    
    .input-field:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(212, 167, 98, 0.15);
    }
    
    /* ปุ่ม toggle password */
    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--primary-dark);
      padding: 8px;
      border-radius: 50%;
      background-color: transparent;
      z-index: 2;
    }
    
    /* ปุ่ม login */
    .btn-login {
      width: 100%;
      padding: 0.875rem 1.5rem;
      border-radius: 0.75rem;
      font-weight: 600;
      font-size: 1rem;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
      color: white;
      box-shadow: 0 4px 12px rgba(212, 167, 98, 0.3);
      transition: all 0.3s ease;
      outline: none;
      margin-top: 0.75rem;
      -webkit-appearance: none;
    }
    
    .btn-login:active {
      transform: translateY(1px);
      box-shadow: 0 2px 8px rgba(212, 167, 98, 0.25);
    }
    
    /* ปรับ checkbox สำหรับมือถือ */
    .checkbox-container {
      display: flex;
      align-items: center;
    }
    
    .checkbox-container input[type="checkbox"] {
      height: 18px;
      width: 18px;
      margin-right: 8px;
      accent-color: var(--primary-color);
    }
    
    /* ปรับปรุงลิงก์ */
    a {
      color: var(--primary-dark);
      text-decoration: none;
      transition: color 0.2s;
    }
    
    a:active {
      color: var(--primary-color);
      transform: scale(0.98);
    }
    
    /* ข้อความแสดงข้อผิดพลาด */
    .error-container {
      background-color: rgba(254, 226, 226, 1);
      border-left: 4px solid rgba(239, 68, 68, 1);
      padding: 1rem;
      margin-bottom: 1.5rem;
      border-radius: 0.375rem;
      display: flex;
      align-items: flex-start;
    }
    
    .error-icon {
      color: rgba(239, 68, 68, 1);
      margin-right: 0.75rem;
      flex-shrink: 0;
    }
    
    .error-message {
      color: rgba(185, 28, 28, 1);
      font-size: 0.875rem;
    }
    
    /* เพิ่ม media query สำหรับหน้าจอมือถือ */
    @media screen and (max-width: 480px) {
      .login-card {
        border-radius: 12px;
      }
      
      .login-header {
        padding: 1.75rem 1rem;
      }
      
      .form-container {
        padding: 1.25rem;
      }
      
      .input-field {
        padding: 0.75rem 1rem 0.75rem 52px;
        font-size: 0.95rem;
      }
      
      .icon-circle {
        width: 32px;
        height: 32px;
      }
      
      .input-icon-wrapper {
        margin-left: 10px;
      }
      
      .btn-login {
        padding: 0.75rem 1rem;
      }
      
      .temple-icon .icon-circle {
        width: 56px !important; 
        height: 56px !important;
      }
    }
    
    /* Animation */
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
    
    /* Ripple effect for buttons */
    .ripple {
      position: relative;
      overflow: hidden;
    }
    
    .ripple:after {
      content: "";
      display: block;
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      pointer-events: none;
      background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
      background-repeat: no-repeat;
      background-position: 50%;
      transform: scale(10, 10);
      opacity: 0;
      transition: transform .5s, opacity 1s;
    }
    
    .ripple:active:after {
      transform: scale(0, 0);
      opacity: .3;
      transition: 0s;
    }
  </style>
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
              <a href="#" class="text-sm text-amber-600 hover:text-amber-800">ລືມລະຫັດຜ່ານ?</a>
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
