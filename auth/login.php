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
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@400;500;600;700&display=swap');
    body {
      font-family: 'Noto Sans Lao', sans-serif;
    }
    .login-container {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .input-group {
      position: relative;
    }
    .input-icon {
      position: absolute;
      left: 2px;
      top: 50%;
      transform: translateY(-50%);
      color: #4B5563;
    }
    .input-field {
      padding-left: 60px;
      transition: all 0.3s ease;
    }
    .input-field:focus {
      border-color: #4F46E5;
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
    }
    .btn-login {
      transition: transform 0.2s ease;
    }
    .btn-login:hover {
      transform: translateY(-2px);
    }
    .animated {
      animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .temple-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      background: linear-gradient(45deg, #FFD700, #FFA500);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-6 login-container">
  <div class="w-full max-w-md px-6">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden animated">
      <div class="p-8 sm:p-10">
        <div class="text-center mb-8">
          <i class="fas fa-landmark temple-icon"></i>
          <h2 class="text-3xl font-bold text-gray-800">ເຂົ້າລະບົບ</h2>
          <p class="text-gray-500 mt-2">ລະບົບຈັດການຂໍ້ມູນວັດ</p>
        </div>

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
            <i class="fas fa-user input-icon"></i>
            <input 
              type="text" 
              name="username" 
              placeholder="ຊື່ຜູ້ໃຊ້" 
              required 
              class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
              value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
            >
          </div>
          
          <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <input 
              type="password" 
              name="password" 
              id="password" 
              placeholder="ລະຫັດຜ່ານ" 
              required 
              class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none"
            >
            <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          
          <div class="flex items-center justify-between">
            <label class="flex items-center">
              <input type="checkbox" name="remember_me" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 rounded">
              <span class="ml-2 text-sm text-gray-600">ຈົດຈໍາການເຂົ້າລະບົບ</span>
            </label>
            <a href="#" class="text-sm text-indigo-600 hover:text-indigo-800">ລືມລະຫັດຜ່ານ?</a>
          </div>
          
          <button 
            type="submit" 
            class="btn-login w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            ເຂົ້າລະບົບ
          </button>
        </form>
      </div>
      
      <div class="bg-gray-50 py-4 px-8 border-t border-gray-100 text-center">
        <p class="text-sm text-gray-500">
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
          this.parentNode.querySelector('.input-icon').style.color = '#4F46E5';
        });
        
        input.addEventListener('blur', function() {
          this.parentNode.querySelector('.input-icon').style.color = '#4B5563';
        });
      });
    });
  </script>
</body>
</html>
