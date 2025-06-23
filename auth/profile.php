<?php
// filepath: c:\xampp\htdocs\temples\auth\profile.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header("Location: {$base_url}auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
try {
    $stmt = $pdo->prepare("SELECT u.*, t.name as temple_name FROM users u LEFT JOIN temples t ON u.temple_id = t.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
        header("Location: {$base_url}dashboard.php");
        exit;
    }

} catch (PDOException $e) {
    $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
}

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจสอบความถูกต้องของข้อมูล
    $errors = [];

    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່";
    }

    if (empty($email)) {
        $errors[] = "ກະລຸນາປ້ອນອີເມວ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    }

    // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นอีเมลของผู้ใช้เอง)
    if (!empty($email) && $email !== $user_data['email']) {
        $email_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $user_id]);
        if ($email_check->fetchColumn() > 0) {
            $errors[] = "ອີເມວນີ້ຖືກໃຊ້ແລ້ວ";
        }
    }

    // ตรวจสอบการเปลี่ยนรหัสผ่าน
    $update_password = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານປັດຈຸບັນ";
        } elseif (!password_verify($current_password, $user_data['password'])) {
            $errors[] = "ລະຫັດຜ່ານປັດຈຸບັນບໍ່ຖືກຕ້ອງ";
        }

        if (empty($new_password)) {
            $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານໃໝ່";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "ລະຫັດຜ່ານໃໝ່ຕ້ອງມີຢ່າງນ້ອຍ 6 ຕົວອັກສອນ";
        }

        if (empty($confirm_password)) {
            $errors[] = "ກະລຸນາຢືນຢັນລະຫັດຜ່ານໃໝ່";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "ລະຫັດຜ່ານຢືນຢັນບໍ່ຕົງກັນ";
        }

        if (empty($errors)) {
            $update_password = true;
        }
    }

    // ถ้าไม่มีข้อผิดพลาด ดำเนินการอัปเดตข้อมูล
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // อัปเดตข้อมูลพื้นฐาน
            $update_data = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($update_password) {
                // เข้ารหัสรหัสผ่านใหม่
                $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            // สร้าง SQL สำหรับอัปเดต
            $set_clauses = [];
            $params = [];
            foreach ($update_data as $key => $value) {
                $set_clauses[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $user_id;

            $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // อัปเดต session
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;

            $pdo->commit();
            $success_message = "ອັບເດດຂໍ້ມູນສຳເລັດແລ້ວ";

            // รีโหลดข้อมูลผู้ใช้
            $stmt = $pdo->prepare("SELECT u.*, t.name as temple_name FROM users u LEFT JOIN temples t ON u.temple_id = t.id WHERE u.id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$page_title = 'ຂໍ້ມູນສ່ວນຕົວ';
require_once '../includes/header.php';
?>

<div class="page-container">
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-4xl mx-auto">
            
            <!-- Header Section -->
            <div class="profile-header">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-6">
                    <div class="profile-header-content">
                        <div class="flex items-center mb-2">
                            <div class="profile-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="ml-4">
                                <h1 class="profile-title">ຂໍ້ມູນສ່ວນຕົວ</h1>
                                <p class="profile-subtitle">ຈັດການແລະອັບເດດຂໍ້ມູນຜູ້ໃຊ້ຂອງທ່ານ</p>
                            </div>
                        </div>
                    </div>
                    <div class="profile-actions mt-4 lg:mt-0">
                        <a href="<?= $base_url ?>dashboard.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            <span class="hidden sm:inline">ກັບໄປໜ້າຫຼັກ</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="profile-content">
                <form method="post" action="" id="profileForm">
                    
                    <!-- Account Information Card -->
                    <div class="info-card account-info">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="card-header-content">
                                <h2 class="card-title">ຂໍ້ມູນບັນຊີ</h2>
                                <p class="card-subtitle">ຂໍ້ມູນພື້ນຖານຂອງບັນຊີຜູ້ໃຊ້</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້</label>
                                    <div class="input-group disabled">
                                        <div class="input-icon">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <input type="text" id="username" value="<?= htmlspecialchars($user_data['username']) ?>" 
                                               class="form-control" disabled>
                                        <div class="input-badge">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                    </div>
                                    <p class="form-help">ບໍ່ສາມາດແກ້ໄຂຊື່ຜູ້ໃຊ້ໄດ້</p>
                                </div>

                                <div class="form-group">
                                    <label for="role" class="form-label">ສິດການໃຊ້ງານ</label>
                                    <div class="input-group disabled">
                                        <div class="input-icon">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <input type="text" id="role" 
                                               value="<?= htmlspecialchars($user_data['role'] === 'superadmin' ? 'ຜູ້ດູແລລະບົບ' : ($user_data['role'] === 'admin' ? 'ຜູ້ດູແລວັດ' : 'ຜູ້ໃຊ້ທົ່ວໄປ')) ?>" 
                                               class="form-control" disabled>
                                        <div class="status-badge <?= $user_data['role'] === 'superadmin' ? 'badge-admin' : 'badge-user' ?>">
                                            <?= htmlspecialchars($user_data['role']) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($user_data['temple_name'])): ?>
                                <div class="form-group md:col-span-2">
                                    <label for="temple" class="form-label">ວັດທີ່ຮັບຜິດຊອບ</label>
                                    <div class="input-group disabled">
                                        <div class="input-icon">
                                            <i class="fas fa-place-of-worship"></i>
                                        </div>
                                        <input type="text" id="temple" value="<?= htmlspecialchars($user_data['temple_name']) ?>" 
                                               class="form-control" disabled>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="form-group md:col-span-2">
                                    <div class="info-banner">
                                        <div class="info-banner-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="info-banner-content">
                                            <p><strong>ເຂົ້າສູ່ລະບົບຄັ້ງທຳອິດ:</strong> <?= date('d/m/Y H:i', strtotime($user_data['created_at'])) ?></p>
                                            <p><strong>ອັບເດດຫຼ້າສຸດ:</strong> <?= date('d/m/Y H:i', strtotime($user_data['updated_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Card -->
                    <div class="info-card personal-info">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="card-header-content">
                                <h2 class="card-title">ຂໍ້ມູນສ່ວນຕົວ</h2>
                                <p class="card-subtitle">ສາມາດແກ້ໄຂແລະອັບເດດໄດ້</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group md:col-span-2">
                                    <label for="name" class="form-label required">ຊື່-ນາມສະກຸນ</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-user-tag"></i>
                                        </div>
                                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_data['name']) ?>" 
                                               class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email" class="form-label required">ອີເມວ</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_data['email']) ?>" 
                                               class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="phone" class="form-label">ເບີໂທ</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user_data['phone']) ?>" 
                                               class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Change Card -->
                    <div class="info-card password-change">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="card-header-content">
                                <h2 class="card-title">ປ່ຽນລະຫັດຜ່ານ</h2>
                                <p class="card-subtitle">ປ່ອຍຫວ່າງໄວ້ຖ້າບໍ່ຕ້ອງການປ່ຽນ</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group md:col-span-2">
                                    <label for="current_password" class="form-label">ລະຫັດຜ່ານປັດຈຸບັນ</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                        <input type="password" id="current_password" name="current_password" 
                                               class="form-control" autocomplete="current-password">
                                        <button type="button" class="password-toggle" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password" class="form-label">ລະຫັດຜ່ານໃໝ່</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-lock-open"></i>
                                        </div>
                                        <input type="password" id="new_password" name="new_password" 
                                               class="form-control" autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="form-help">ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</p>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">ຢືນຢັນລະຫັດຜ່ານໃໝ່</label>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <input type="password" id="confirm_password" name="confirm_password" 
                                               class="form-control" autocomplete="new-password">
                                        <button type="button" class="password-toggle" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="<?= $base_url ?>dashboard.php" class="btn btn-secondary flex-1 sm:flex-none">
                                <i class="fas fa-times"></i>
                                <span>ຍົກເລີກ</span>
                            </a>
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fas fa-save"></i>
                                <span>ບັນທຶກຂໍ້ມູນ</span>
                            </button>
                        </div>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<!-- Custom CSS -->
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/profile.css">


<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Password Toggle Functionality
    document.querySelectorAll('.password-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
    
    // Form Validation
    const form = document.getElementById('profileForm');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const currentPasswordInput = document.getElementById('current_password');
    
    // Real-time password validation
    function validatePasswords() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (newPassword && confirmPassword) {
            if (newPassword !== confirmPassword) {
                confirmPasswordInput.setCustomValidity('ລະຫັດຜ່ານບໍ່ຕົງກັນ');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }
        
        if (newPassword && newPassword.length < 6) {
            newPasswordInput.setCustomValidity('ລະຫັດຜ່ານຕ້ອງມີຢ່າງນ້ອຍ 6 ຕົວອັກສອນ');
        } else {
            newPasswordInput.setCustomValidity('');
        }
    }
    
    newPasswordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);
    
    // Check if password change is required
    function checkPasswordRequirement() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const currentPassword = currentPasswordInput.value;
        
        if (newPassword || confirmPassword) {
            currentPasswordInput.required = true;
            newPasswordInput.required = true;
            confirmPasswordInput.required = true;
        } else {
            currentPasswordInput.required = false;
            newPasswordInput.required = false;
            confirmPasswordInput.required = false;
        }
    }
    
    [newPasswordInput, confirmPasswordInput, currentPasswordInput].forEach(input => {
        input.addEventListener('input', checkPasswordRequirement);
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>ກຳລັງບັນທຶກ...</span>';
        
        // Re-enable after 3 seconds (in case of errors)
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> <span>ບັນທຶກຂໍ້ມູນ</span>';
        }, 3000);
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'all 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                alert.remove();
            }, 500);
        });
    }, 5000);
    
    // Smooth scroll to error messages
    const errorAlert = document.querySelector('.alert-error');
    if (errorAlert) {
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>