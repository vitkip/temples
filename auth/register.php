<?php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ກວດສອບວ່າເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່ ຖ້າເຂົ້າສູ່ລະບົບແລ້ວ ໃຫ້ນໍາທາງໄປໜ້າຫຼັກ
if (isset($_SESSION['user'])) {
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງຂໍ້ມູນວັດສໍາລັບ dropdown
try {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} catch (PDOException $e) {
    $temples = [];
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'username' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'temple_id' => '',
    'password' => '',
    'confirm_password' => '',
    'accept_terms' => false
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'auth/register.php');
        exit;
    }
    
    // ກວດສອບຂໍ້ມູນທີ່ສົ່ງມາ
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'accept_terms' => isset($_POST['accept_terms'])
    ];
    
    // ກວດສອບ username
    if (empty($form_data['username'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    } else {
        // ກວດສອບວ່າ username ຊໍ້າກັນຫຼືບໍ່
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check_stmt->execute([$form_data['username']]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
        }
    }
    
    // ກວດສອບຊື່
    if (empty($form_data['name'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່-ນາມສະກຸນ";
    }
    
    // ກວດສອບອີເມລ 
    if (empty($form_data['email'])) {
        $errors[] = "ກະລຸນາປ້ອນອີເມວ";
    } else if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    } else {
        // ກວດສອບວ່າອີເມວຊໍ້າກັນຫຼືບໍ່
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check_stmt->execute([$form_data['email']]);
        if ($check_stmt->fetchColumn() > 0) {
            $errors[] = "ອີເມວນີ້ຖືກໃຊ້ໃນລະບົບແລ້ວ";
        }
    }
    
    // ກວດສອບເບີໂທລະສັບ (ຖ້າມີ)
    if (!empty($form_data['phone']) && !preg_match('/^[0-9]{8,10}$/', $form_data['phone'])) {
        $errors[] = "ຮູບແບບເບີໂທລະສັບບໍ່ຖືກຕ້ອງ (8-10 ຕົວເລກ)";
    }
    
    // ກວດສອບລະຫັດຜ່ານ
    if (empty($form_data['password'])) {
        $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານ";
    } else if (strlen($form_data['password']) < 6) {
        $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
    } else if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
    }
    
    // ກວດສອບວັດ
    if (empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ກວດສອບການຍອມຮັບເງື່ອນໄຂ
    if (!$form_data['accept_terms']) {
        $errors[] = "ທ່ານຕ້ອງຍອມຮັບເງື່ອນໄຂການໃຊ້ງານ";
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ
    if (empty($errors)) {
        try {
            // ຜູ້ລົງທະບຽນໃໝ່ຈະເປັນຜູ້ໃຊ້ທົ່ວໄປເທົ່ານັ້ນ
            $sql = "INSERT INTO users (username, password, name, email, phone, role, temple_id, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $params = [
                $form_data['username'],
                password_hash($form_data['password'], PASSWORD_DEFAULT),
                $form_data['name'],
                $form_data['email'],
                $form_data['phone'],
                'user',  // ກໍານົດ role ເປັນ 'user' ສໍາລັບຜູໃຊ່ໃໝ່
                $form_data['temple_id'],
                'pending'  // ຕັ້ງສະຖານະເປັນ 'pending' ລໍຖ້າການອະນຸມັດ
            ];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // ສົ່ງອີເມວແຈ້ງເຕືອນຜູ້ດູແລລະບົບ (ໃນໂຄດຕົວຈິງຄວນເພີ່ມສ່ວນນີ້)
            
            $_SESSION['success'] = "ລົງທະບຽນສໍາເລັດແລ້ວ ກະລຸນາລໍຖ້າການອະນຸມັດຈາກຜູ່ດູແລລະບົບ";
            header('Location: ' . $base_url . 'auth/login.php');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ລົງທະບຽນຜູ້ໃຊ້ໃໝ່ - Temple Management System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font - Noto Sans Lao -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- นำเข้า monk-style.css -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
   <style>
        body {
            font-family: 'Noto Sans Lao', sans-serif;
            background-color: #F9F5F0;
        }
        
        .register-container {
            background-image: url('../assets/images/thai-pattern.svg');
            background-repeat: repeat;
            background-size: 200px;
            background-opacity: 0.05;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #B08542;
            pointer-events: none;
        }
        
        .input-with-icon {
            padding-left: 2.5rem !important;
        }
        
        .form-input:focus {
            box-shadow: 0 0 0 2px rgba(212, 167, 98, 0.2);
            border-color: #D4A762;
        }
        
        .form-card {
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(200, 169, 126, 0.15);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #D4A762, #B08542);
            box-shadow: 0 4px 12px rgba(212, 167, 98, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(212, 167, 98, 0.35);
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .animated {
            animation: fadeIn 0.6s ease-out;
        }
        
        .divider {
            position: relative;
            height: 1px;
            background-color: rgba(212, 167, 98, 0.2);
        }
        
        .divider-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 0 1rem;
            color: #8E7D6A;
        }
        
        .form-checkbox {
            border-radius: 0.25rem;
            border-color: rgba(212, 167, 98, 0.4);
        }
        
        .form-checkbox:checked {
            background-color: #D4A762;
            border-color: #B08542;
        }
        
        .tooltip {
            position: relative;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .tooltiptext {
            visibility: hidden;
            opacity: 0;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            transition: opacity 0.3s;
        }

        .register-header {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #F0E5D3, #FFFBF5);
            border-bottom: 1px solid rgba(212, 167, 98, 0.2);
        }
        
        .register-header::before {
            content: "";
            position: absolute;
            top: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            background-image: url('../assets/images/temple-pattern-light.svg');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: 0;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-center py-6 register-container">
    <div class="sm:mx-auto sm:w-full sm:max-w-md mb-6 animated">
        <div class="text-center">
            <div class="icon-circle mx-auto w-20 h-20 mb-2">
                <img class="h-8 w-auto" src="<?= $base_url ?>assets/images/logo.png" alt="<?= htmlspecialchars($site_name) ?>">
            </div>
            <h2 class="text-center text-3xl font-bold text-gray-900 drop-shadow-sm">ລົງທະບຽນຜູ້ໃຊ້ໃໝ່</h2>
            <p class="mt-2 text-center text-amber-700">
                ສ້າງບັນຊີໃໝ່ເພື່ອເຂົ້າໃຊ້ລະບົບຈັດການຂໍ້ມູນວັດວາອາຣາມ
            </p>
        </div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-xl mb-6">
        <?php if (!empty($errors)): ?>
        <!-- ສະແດງຂໍ້ຜິດພາດ -->
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg shadow-sm animated">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">ພົບຂໍ້ຜິດພາດ <?= count($errors) ?> ລາຍການ</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ຟອມລົງທະບຽນ -->
        <div class="card animated">
            <div class="register-header p-6 text-center">
                <h3 class="text-xl font-semibold text-gray-800">ປ້ອນຂໍ້ມູນຜູ້ໃຊ້</h3>
                <p class="text-sm text-amber-700 mt-1">ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນທັງໝົດໃຫ້ຄົບຖ້ວນ</p>
            </div>
            
            <form action="<?= $base_url ?>auth/register.php" method="POST" class="p-6 space-y-6" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ຊື່ຜູ້ໃຊ້ -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            ຊື່ຜູ້ໃຊ້ <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" name="username" id="username" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                value="<?= htmlspecialchars($form_data['username']) ?>" required>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">ຊື່ຜູໃຊ້ສໍາລັບເຂົ້າສູ່ລະບົບ</p>
                    </div>
                    
                    <!-- ຊື່-ນາມສະກຸນ -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            ຊື່-ນາມສະກຸນ <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-id-card"></i>
                            </span>
                            <input type="text" name="name" id="name" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                value="<?= htmlspecialchars($form_data['name']) ?>" required>
                        </div>
                    </div>
                    
                    <!-- ອີເມວ -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            ອີເມວ <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" name="email" id="email" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                value="<?= htmlspecialchars($form_data['email']) ?>" 
                                placeholder="example@domain.com" required>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">ໃຊ້ສໍາລັບການຕິດຕໍ່ ແລະ ແຈ້ງເຕືອນຈາກລະບົບ</p>
                    </div>
                    
                    <!-- ເບີໂທລະສັບ -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">
                            ເບີໂທລະສັບ
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-phone"></i>
                            </span>
                            <input type="tel" name="phone" id="phone" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                value="<?= htmlspecialchars($form_data['phone']) ?>" 
                                placeholder="02012345678">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">ປ້ອນແຕ່ຕົວເລກ 8-10 ຕົວ</p>
                    </div>
                </div>
                
                <!-- ວັດ -->
                <div>
                    <label for="temple_id" class="block text-sm font-medium text-gray-700">
                        ວັດ <span class="text-red-600">*</span>
                    </label>
                    <div class="mt-1 input-group">
                        <span class="input-icon">
                            <i class="fas fa-place-of-worship"></i>
                        </span>
                        <select name="temple_id" id="temple_id" 
                            class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                            required>
                            <option value="">-- ເລືອກວັດ --</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $form_data['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="divider relative my-6">
                    <span class="divider-text text-xs font-medium">ຂໍ້ມູນການເຂົ້າສູ່ລະບົບ</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- ລະຫັດຜ່ານ -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            ລະຫັດຜ່ານ <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="password" id="password" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                minlength="6" required>
                            <span id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer">
                                <i class="fas fa-eye text-amber-600 hover:text-amber-800"></i>
                            </span>
                        </div>
                        <div class="mt-1">
                            <div class="text-xs text-gray-500">ຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ</div>
                            <div id="passwordStrength" class="mt-1 h-1 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500 transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ຢືນຢັນລະຫັດຜ່ານ -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                            ຢືນຢັນລະຫັດຜ່ານ <span class="text-red-600">*</span>
                        </label>
                        <div class="mt-1 input-group">
                            <span class="input-icon">
                                <i class="fas fa-shield-alt"></i>
                            </span>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                class="shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full sm:text-sm border-gray-300 rounded-lg input-with-icon form-input transition-all" 
                                minlength="6" required>
                            <span id="toggleConfirmPassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer">
                                <i class="fas fa-eye text-amber-600 hover:text-amber-800"></i>
                            </span>
                        </div>
                        <div id="passwordMatch" class="mt-1 text-xs invisible">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>
                            <span class="text-green-500">ລະຫັດຜ່ານກົງກັນ</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center p-4 bg-amber-50 rounded-lg">
                    <input id="accept_terms" name="accept_terms" type="checkbox" 
                        class="h-5 w-5 text-amber-600 focus:ring-amber-500 border-gray-300 rounded-md transition-all cursor-pointer" 
                        <?= $form_data['accept_terms'] ? 'checked' : '' ?> required>
                    <label for="accept_terms" class="ml-3 block text-sm text-gray-700">
                        ຂ້າພະເຈົ້າຍອມຮັບ <a href="#" class="font-medium text-amber-600 hover:text-amber-800 border-b border-amber-600">ເງື່ອນໄຂການໃຊ້ງານ</a> ແລະ <a href="#" class="font-medium text-amber-600 hover:text-amber-800 border-b border-amber-600">ນະໂຍບາຍຄວາມເປັນສ່ວນຕົວ</a>
                    </label>
                </div>
                
                <div>
                    <button type="submit" class="w-full btn-register py-3 px-4 border border-transparent rounded-lg shadow-sm text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all duration-300">
                        <i class="fas fa-user-plus mr-2"></i> ລົງທະບຽນ
                    </button>
                </div>
            </form>
            
            <div class="border-t border-amber-100 p-6">
                <div class="text-sm text-center">
                    <p class="text-gray-600">
                        ມີບັນຊີແລ້ວບໍ?
                        <a href="<?= $base_url ?>auth/login.php" class="font-medium text-amber-600 hover:text-amber-800 transition-colors duration-300 ml-1">
                            <i class="fas fa-sign-in-alt mr-1"></i> ເຂົ້າສູ່ລະບົບ
                        </a>
                    </p>
                </div>
                <div class="mt-4 text-center">
                    <a href="<?= $base_url ?>" class="text-amber-600 hover:text-amber-800 flex items-center justify-center">
                        <i class="fas fa-home mr-2"></i> ກັບໄປໜ້າຫຼັກ
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-4 text-center text-sm text-amber-800/70 animated">
        <p>&copy; <?= date('Y') ?> ລະບົບຈັດການຂໍ້ມູນວັດ. ສະຫງວນລິຂະສິດ.</p>
    </div>

    <!-- JavaScript for enhanced form validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Basic elements
            const form = document.getElementById('registerForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength').querySelector('div');
            const passwordMatch = document.getElementById('passwordMatch');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
            
            // Password strength meter
            password.addEventListener('input', function() {
                const val = this.value;
                let strength = 0;
                let color = '#C57B70'; // Use amber color scheme
                let width = '0%';
                
                if (val.length >= 6) {
                    strength += 1;
                }
                if (val.length >= 8) {
                    strength += 1;
                }
                if (val.match(/[0-9]/)) {
                    strength += 1;
                }
                if (val.match(/[a-z]/)) {
                    strength += 1;
                }
                if (val.match(/[A-Z]/)) {
                    strength += 1;
                }
                if (val.match(/[^a-zA-Z0-9]/)) {
                    strength += 1;
                }
                
                switch (strength) {
                    case 0:
                        width = '0%';
                        color = '#C57B70';
                        break;
                    case 1:
                    case 2:
                        width = '20%';
                        color = '#C57B70';
                        break;
                    case 3:
                        width = '40%';
                        color = '#E9B949';
                        break;
                    case 4:
                        width = '60%';
                        color = '#D4A762';
                        break;
                    case 5:
                        width = '80%';
                        color = '#B08542';
                        break;
                    case 6:
                        width = '100%';
                        color = '#7A9B78';
                        break;
                }
                
                passwordStrength.style.width = width;
                passwordStrength.style.backgroundColor = color;
                
                // Check if passwords match when typing in password field
                if (confirmPassword.value.length > 0) {
                    checkPasswordMatch();
                }
            });
            
            // Password match checker
            confirmPassword.addEventListener('input', checkPasswordMatch);
            
            function checkPasswordMatch() {
                if (password.value === confirmPassword.value && confirmPassword.value !== '') {
                    passwordMatch.classList.remove('invisible');
                } else {
                    passwordMatch.classList.add('invisible');
                }
            }
            
            // Form validation before submit
            form.addEventListener('submit', function(event) {
                if (password.value !== confirmPassword.value) {
                    event.preventDefault();
                    
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'bg-red-50 border-l-4 border-red-500 p-4 mt-4 rounded-md animated';
                    errorMessage.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">ລະຫັດຜ່ານບໍ່ກົງກັນ</p>
                            </div>
                        </div>
                    `;
                    
                    // Remove any existing error message
                    const existingError = document.querySelector('.bg-red-50.border-l-4.border-red-500.p-4.mt-4');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    // Add the error message
                    form.appendChild(errorMessage);
                    
                    // Scroll to the error message
                    errorMessage.scrollIntoView({behavior: 'smooth'});
                }
            });
            
            // Add focus effects for all inputs with icon highlighting
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const icon = this.parentElement.querySelector('.input-icon i');
                    if (icon) {
                        icon.style.color = '#B08542';
                    }
                    this.parentElement.classList.add('ring-2', 'ring-amber-200', 'ring-opacity-50', 'rounded-lg');
                });
                
                input.addEventListener('blur', function() {
                    const icon = this.parentElement.querySelector('.input-icon i');
                    if (icon) {
                        icon.style.color = '#B08542';
                    }
                    this.parentElement.classList.remove('ring-2', 'ring-amber-200', 'ring-opacity-50', 'rounded-lg');
                });
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>