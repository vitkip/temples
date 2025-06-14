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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ເຂົ້າລະບົບ</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@400;500;600;700&display=swap');
    body {
      font-family: 'Noto Sans Lao', sans-serif;
    }
    .login-container {
      background-image: url('../assets/images/thai-pattern.svg');
      background-color: #F9F5F0;
      background-repeat: repeat;
      background-size: 200px;
      background-opacity: 0.05;
    }
    .input-group {
      position: relative;
    }
    .input-icon-wrapper {
      position: absolute;
      left: 2px;
      top: 50%;
      transform: translateY(-50%);
      margin-left: 10px;
    }
    .input-field {
      padding-left: 60px;
      border: 2px solid rgba(212, 167, 98, 0.2);
      border-radius: 0.75rem;
      transition: all 0.3s ease;
    }
    .input-field:focus {
      border-color: #D4A762;
      box-shadow: 0 0 0 3px rgba(212, 167, 98, 0.15);
    }
    .btn-login {
      background: linear-gradient(135deg, #D4A762, #B08542);
      box-shadow: 0 4px 12px rgba(212, 167, 98, 0.3);
      transition: all 0.3s ease;
    }
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(212, 167, 98, 0.35);
    }
    .login-card {
      animation: fadeInUp 0.5s ease-out forwards;
      overflow: hidden;
    }
    .login-header {
      position: relative;
      overflow: hidden;
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
    .temple-icon {
      position: relative;
      z-index: 1;
      font-size: 2.5rem;
      margin-bottom: 1rem;
      color: #B08542;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center py-6 login-container">
  <div class="w-full max-w-md px-6">
    <div class="card login-card bg-white rounded-lg shadow-xl overflow-hidden">
      <div class="login-header p-8 sm:p-10 bg-gradient-to-br from-amber-50 to-amber-100 text-center">
        <div class="temple-icon">
          <div class="icon-circle mx-auto w-16 h-16">
            <img class="h-8 w-auto" src="<?= $base_url ?>assets/images/logo.png" alt="<?= htmlspecialchars($site_name) ?>">
          </div>
        </div>
        <h2 class="text-3xl font-bold text-gray-800">ເຂົ້າລະບົບ</h2>
        <p class="text-amber-700 mt-2">ລະບົບຈັດການຂໍ້ມູນວັດ</p>
      </div>

      <div class="p-8 sm:p-10">
        <?php if (isset($error)) : ?>
          <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
            <div class="flex items-center">
              <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
              <p class="text-red-700"><?= $error ?></p>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          
          <div class="input-group">
            <div class="input-icon-wrapper">
              <div class="icon-circle w-8 h-8">
                <i class="fas fa-user text-sm"></i>
              </div>
            </div>
            <input 
              type="text" 
              name="username" 
              placeholder="ຊື່ຜູ້ໃຊ້" 
              required 
              class="input-field w-full px-4 py-3 focus:outline-none"
              value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
            >
          </div>
          
          <div class="input-group">
            <div class="input-icon-wrapper">
              <div class="icon-circle w-8 h-8">
                <i class="fas fa-lock text-sm"></i>
              </div>
            </div>
            <input 
              type="password" 
              name="password" 
              id="password" 
              placeholder="ລະຫັດຜ່ານ" 
              required 
              class="input-field w-full px-4 py-3 focus:outline-none"
            >
            <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-amber-700">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          
          <div class="flex items-center justify-between">
            <label class="flex items-center">
              <input type="checkbox" name="remember_me" class="h-4 w-4 text-amber-600 focus:ring-amber-500 rounded">
              <span class="ml-2 text-sm text-gray-600">ຈົດຈໍາການເຂົ້າລະບົບ</span>
            </label>
            <a href="#" class="text-sm text-amber-600 hover:text-amber-800">ລືມລະຫັດຜ່ານ?</a>
          </div>
          
          <button 
            type="submit" 
            class="btn-login w-full text-white py-3 rounded-lg font-medium focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
          >
            <i class="fas fa-sign-in-alt mr-2"></i> ເຂົ້າລະບົບ
          </button>
        </form>

        <div class="mt-8 text-center">
          <p class="text-gray-600">
            ຍັງບໍ່ມີບັນຊີບໍ?
            <a href="<?= $base_url ?>auth/register.php" class="text-amber-600 hover:text-amber-800 font-medium">
              ລົງທະບຽນ
            </a>
          </p>
        </div>
      </div>
      
      <div class="bg-gray-50 py-4 px-8 border-t border-amber-100 text-center">
        <a href="<?= $base_url ?>" class="text-amber-600 hover:text-amber-800 flex items-center justify-center">
          <i class="fas fa-home mr-2"></i> ກັບໄປໜ້າຫຼັກ
        </a>
      </div>
    </div>
    <div class="text-center mt-6">
      <p class="text-sm text-amber-800/70">
        © <?= date('Y') ?> ລະບົບຈັດການຂໍ້ມູນວັດ. ສະຫງວນລິຂະສິດທັງໝົດ.
      </p>
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
    });
  </script>
</body>
</html>
