<?php
// filepath: c:\xampp\htdocs\temples\users\add.php
ob_start();

$page_title = 'ເພີ່ມຜູ້ໃຊ້ໃໝ່';
require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດການໃຊ້ງານ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$is_province_admin = $_SESSION['user']['role'] === 'province_admin';

// ອະນຸຍາດໃຫ້ສະເພາະ superadmin, admin, ແລະ province_admin ເທົ່ານັ້ນ
if (!$is_superadmin && !$is_admin && !$is_province_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃຊ້ງານສ່ວນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງຂໍ້ມູນວັດຕາມສິດການເຂົ້າເຖິງ
$temples = [];
if ($is_superadmin) {
    // Superadmin ສາມາດເລືອກວັດໃດກໍໄດ້
    $temple_stmt = $pdo->query("SELECT id, name, province_id FROM temples ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} elseif ($is_province_admin) {
    // Province admin ສາມາດເລືອກວັດໃນແຂວງທີ່ຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT t.id, t.name, t.province_id
        FROM temples t 
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY t.name
    ");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $temples = $province_stmt->fetchAll();
} elseif ($is_admin) {
    // Admin ສາມາດເລືອກສະເພາະວັດຂອງຕົນເອງ
    $temple_stmt = $pdo->prepare("SELECT id, name, province_id FROM temples WHERE id = ?");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ດຶງຂໍ້ມູນແຂວງ (ສຳລັບ province_admin)
$provinces = [];
if ($is_superadmin) {
    $province_stmt = $pdo->query("SELECT * FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll();
} elseif ($is_province_admin) {
    $province_stmt = $pdo->prepare("
        SELECT p.*
        FROM provinces p
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ?
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $provinces = $province_stmt->fetchAll();
}

// ເມື່ອສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ຮັບຂໍ້ມູນຈາກຟອມ
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'];
    $temple_id = isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : null;
    $province_id = isset($_POST['province_id']) ? (int)$_POST['province_id'] : null;
    $status = $_POST['status'];
    
    $errors = [];
    
    // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    if (empty($username)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້";
    }
    
    if (empty($password)) {
        $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານ";
    } elseif ($password !== $confirm_password) {
        $errors[] = "ລະຫັດຜ່ານບໍ່ກົງກັນ";
    } elseif (strlen($password) < 6) {
        $errors[] = "ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ";
    }
    
    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່-ນາມສະກຸນ";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    }
    
    // ກວດສອບວັດ
    if (($role === 'admin' || $role === 'user') && empty($temple_id)) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ກວດສອບແຂວງ (ສຳລັບ province_admin)
    if ($role === 'province_admin' && empty($province_id)) {
        $errors[] = "ກະລຸນາເລືອກແຂວງ";
    }
    
    // ກວດສອບສິດໃນການສ້າງບົດບາດ
    if ($role === 'superadmin' && !$is_superadmin) {
        $errors[] = "ທ່ານບໍ່ສາມາດສ້າງຜູ້ໃຊ້ທີ່ມີບົດບາດສູງສຸດໄດ້";
    }
    
    if ($role === 'province_admin' && !$is_superadmin) {
        $errors[] = "ທ່ານບໍ່ສາມາດສ້າງຜູ້ດູແລລະດັບແຂວງໄດ້";
    }
    
    // ກວດສອບວ່າຊື່ຜູ້ໃຊ້ມີແລ້ວຫຼືບໍ່
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->execute([$username]);
    if ($check_stmt->rowCount() > 0) {
        $errors[] = "ຊື່ຜູ້ໃຊ້ນີ້ມີຢູ່ໃນລະບົບແລ້ວ";
    }
    
    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ, ບັນທຶກຂໍ້ມູນ
    if (empty($errors)) {
        try {
            // ເຂົ້າລະຫັດຜ່ານ
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // ເລີ່ມ transaction
            $pdo->beginTransaction();
            
            // ບັນທຶກຂໍ້ມູນຜູ້ໃຊ້ໃໝ່
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, name, email, phone, role, temple_id, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            // ກຳນົດ temple_id ຕາມບົດບາດ
            $assigned_temple = null;
            if ($role === 'admin' || $role === 'user') {
                $assigned_temple = $temple_id;
            }
            
            $stmt->execute([
                $username,
                $hashed_password,
                $name,
                $email,
                $phone,
                $role,
                $assigned_temple,
                $status
            ]);
            
            $new_user_id = $pdo->lastInsertId();
            
            // ຖ້າເປັນ province_admin, ບັນທຶກສິດການເຂົ້າເຖິງແຂວງ
            if ($role === 'province_admin' && !empty($province_id)) {
                $province_access_stmt = $pdo->prepare("
                    INSERT INTO user_province_access (user_id, province_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, NOW())
                ");
                
                $province_access_stmt->execute([
                    $new_user_id,
                    $province_id,
                    $_SESSION['user']['id']
                ]);
            }
            
            // ຢືນຢັນ transaction
            $pdo->commit();
            
            $_SESSION['success'] = "ເພີ່ມຜູ້ໃຊ້ສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'users/view.php?id=' . $new_user_id);
            exit;
            
        } catch (PDOException $e) {
            // ຍົກເລີກ transaction ກໍລະນີເກີດຂໍ້ຜິດພາດ
            $pdo->rollBack();
            
            $errors[] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    }
}

// เพิ่ม header ก่อนกำหนดคอนเทนต์
require_once '../includes/header.php';
?>
<!-- เพิ่ม CSS เฉพาะหน้านี้ -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/useradd.css">
<div class="page-container bg-temple-pattern">
  <div class="temple-form-container">
    <!-- ส่วนหัวหน้า -->
    <div class="view-header">
      <div>
        <h1 class="monk-title"><?= $page_title ?></h1>
        <p class="text-sm text-gray-600">ເພີ່ມບັນຊີຜູ້ໃຊ້ງານໃໝ່ເຂົ້າສູ່ລະບົບ</p>
      </div>
     
    </div>
    
    <!-- แสดงข้อผิดพลาด (ถ้ามี) -->
    <?php if (!empty($errors)): ?>
      <div class="form-error" role="alert">
        <div class="form-error-title">
          <i class="fas fa-exclamation-circle"></i> ເກີດຂໍ້ຜິດພາດ!
        </div>
        <ul class="form-error-list">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    
    <!-- การ์ดฟอร์ม -->
    <div class="temple-card">
      <div class="temple-header">
        <div class="temple-title">
          <div class="temple-icon">
            <i class="fas fa-user-plus"></i>
          </div>
          ຂໍ້ມູນຜູ້ໃຊ້ໃໝ່
        </div>
         <a href="<?= $base_url ?>users/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ກັບຄືນ
                </a>
      </div>
      
      <div class="temple-body">
        <form method="POST" action="" id="userForm">
          <div class="temple-form-grid">
            <!-- ชื่อผู้ใช้ -->
            <div class="form-group" style="animation-delay: 0.1s;">
              <label for="username" class="form-label">ຊື່ຜູ້ໃຊ້<span class="required-star">*</span></label>
                
              <input type="text" id="username" name="username" value="<?= isset($username) ? htmlspecialchars($username) : '' ?>" 
                     class="form-input" required autocomplete="username">
              <div class="form-hint">ໃຊ້ສຳລັບເຂົ້າສູ່ລະບົບ ແລະ ບໍ່ສາມາດປ່ຽນແປງໄດ້ໃນພາຍຫຼັງ</div>
            </div>
            
            <!-- ชื่อ-นามสกุล -->
            <div class="form-group" style="animation-delay: 0.2s;">
              <label for="name" class="form-label">ຊື່-ນາມສະກຸນ<span class="required-star">*</span></label>
              <input type="text" id="name" name="name" value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" 
                     class="form-input" required>
            </div>
            
            <!-- รหัสผ่าน -->
            <div class="form-group" style="animation-delay: 0.3s;">
              <label for="password" class="form-label">ລະຫັດຜ່ານ<span class="required-star">*</span></label>
              <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-input" required autocomplete="new-password">
                <button type="button" class="password-toggle" id="togglePassword">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
              <div class="form-hint">ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ</div>
            </div>
            
            <!-- ยืนยันรหัสผ่าน -->
            <div class="form-group" style="animation-delay: 0.4s;">
              <label for="confirm_password" class="form-label">ຢືນຢັນລະຫັດຜ່ານ<span class="required-star">*</span></label>
              <div class="password-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required autocomplete="new-password">
                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            
            <!-- อีเมล -->
            <div class="form-group" style="animation-delay: 0.5s;">
              <label for="email" class="form-label">ອີເມວ</label>
              <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" 
                     class="form-input" autocomplete="email">
              <div class="form-hint">ໃຊ້ສຳລັບການແຈ້ງເຕືອນແລະການຢືນຢັນ</div>
            </div>
            
            <!-- เบอร์โทร -->
            <div class="form-group" style="animation-delay: 0.6s;">
              <label for="phone" class="form-label">ເບີໂທລະສັບ</label>
              <input type="text" id="phone" name="phone" value="<?= isset($phone) ? htmlspecialchars($phone) : '' ?>" 
                     class="form-input" autocomplete="tel">
            </div>
            
            <!-- บทบาท -->
            <div class="form-group" style="animation-delay: 0.7s;">
              <label for="role" class="form-label">ບົດບາດ<span class="required-star">*</span></label>
              <select id="role" name="role" class="form-select" required>
                <?php if ($is_superadmin): ?>
                  <option value="superadmin" <?= isset($role) && $role === 'superadmin' ? 'selected' : '' ?>>ຜູ້ດູແລລະບົບສູງສຸດ (Superadmin)</option>
                  <option value="province_admin" <?= isset($role) && $role === 'province_admin' ? 'selected' : '' ?>>ຜູ້ດູແລລະດັບແຂວງ (Province Admin)</option>
                <?php endif; ?>
                <option value="admin" <?= isset($role) && $role === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ (Temple Admin)</option>
                <option value="user" <?= isset($role) && $role === 'user' ? 'selected' : '' ?>>ຜູ້ໃຊ້ທົ່ວໄປ (User)</option>
              </select>
              <div class="form-hint">ກຳນົດສິດໃນການເຂົ້າເຖິງສ່ວນຕ່າງໆຂອງລະບົບ</div>
            </div>
            
            <!-- สถานะ -->
            <div class="form-group" style="animation-delay: 0.8s;">
              <label for="status" class="form-label">ສະຖານະ<span class="required-star">*</span></label>
              <select id="status" name="status" class="form-select" required>
                <option value="active" <?= isset($status) && $status === 'active' ? 'selected' : '' ?>>ໃຊ້ງານ</option>
                <option value="pending" <?= isset($status) && $status === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                <option value="inactive" <?= isset($status) && $status === 'inactive' ? 'selected' : '' ?>>ປິດການໃຊ້ງານ</option>
              </select>
            </div>
            
            <!-- ส่วนเลือกแขวง (สำหรับ province_admin) -->
            <div id="province-section" class="form-group <?= isset($role) && $role === 'province_admin' ? '' : 'hidden' ?>" style="animation-delay: 0.9s;">
              <label for="province_id" class="form-label">ແຂວງ<span class="required-star">*</span></label>
              <select id="province_id" name="province_id" class="form-select">
                <option value="">-- ກະລຸນາເລືອກແຂວງ --</option>
                <?php foreach ($provinces as $province): ?>
                  <option value="<?= $province['province_id'] ?>" <?= isset($province_id) && $province_id == $province['province_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($province['province_name']) ?> (<?= htmlspecialchars($province['province_code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint">ກໍານົດແຂວງທີ່ຜູ້ໃຊ້ຈະສາມາດຈັດການໄດ້</div>
            </div>
            
            <!-- ส่วนเลือกวัด (สำหรับ admin หรือ user) -->
            <div id="temple-section" class="form-group <?= !isset($role) || $role === 'admin' || $role === 'user' ? '' : 'hidden' ?>" style="animation-delay: 1s;">
              <label for="temple_id" class="form-label">ວັດ<span class="required-star">*</span></label>
              <select id="temple_id" name="temple_id" class="form-select">
                <option value="">-- ກະລຸນາເລືອກວັດ --</option>
                <?php foreach ($temples as $temple): ?>
                  <option value="<?= $temple['id'] ?>" <?= isset($temple_id) && $temple_id == $temple['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($temple['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-hint">ກໍານົດວັດທີ່ຜູ້ໃຊ້ຈະຈັດການຂໍ້ມູນໄດ້</div>
            </div>
          </div>
          
          <div class="form-footer">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ
            </button>
            <a href="<?= $base_url ?>users/" class="btn btn-secondary">
              <i class="fas fa-times"></i> ຍົກເລີກ
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // การแสดง/ซ่อนฟิลด์ตามบทบาท
  const roleSelect = document.getElementById('role');
  const templeSection = document.getElementById('temple-section');
  const provinceSection = document.getElementById('province-section');
  const templeSelect = document.getElementById('temple_id');
  const provinceSelect = document.getElementById('province_id');
  
  // อนิเมชันสำหรับฟอร์ม
  const formGroups = document.querySelectorAll('.form-group');
  formGroups.forEach((group, index) => {
    setTimeout(() => {
      group.style.opacity = '1';
    }, 100 + (index * 100));
  });
  
  // ฟังก์ชันอัปเดตฟอร์มตามบทบาท
  function updateFormByRole() {
    const selectedRole = roleSelect.value;
    
    // จัดการกับส่วนเลือกวัด
    if (selectedRole === 'admin' || selectedRole === 'user') {
      templeSection.classList.remove('hidden');
      templeSelect.required = true;
      fadeIn(templeSection);
    } else {
      fadeOut(templeSection, function() {
        templeSection.classList.add('hidden');
        templeSelect.required = false;
      });
    }
    
    // จัดการกับส่วนเลือกแขวง
    if (selectedRole === 'province_admin') {
      provinceSection.classList.remove('hidden');
      provinceSelect.required = true;
      fadeIn(provinceSection);
    } else {
      fadeOut(provinceSection, function() {
        provinceSection.classList.add('hidden');
        provinceSelect.required = false;
      });
    }
  }
  
  // ฟังก์ชันสำหรับ fade-in
  function fadeIn(element) {
    element.style.opacity = '0';
    element.style.display = 'block';
    
    setTimeout(() => {
      element.style.opacity = '1';
    }, 10);
  }
  
  // ฟังก์ชันสำหรับ fade-out
  function fadeOut(element, callback) {
    element.style.opacity = '0';
    
    setTimeout(() => {
      if (callback) callback();
    }, 300);
  }
  
  // เพิ่ม event listener สำหรับการเปลี่ยนบทบาท
  roleSelect.addEventListener('change', updateFormByRole);
  
  // ตรวจสอบ password matching
  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('confirm_password');
  const userForm = document.getElementById('userForm');
  
  userForm.addEventListener('submit', function(event) {
    // ตรวจสอบความยาวรหัสผ่าน
    if (passwordInput.value.length < 6) {
      event.preventDefault();
      showFormError('ລະຫັດຜ່ານຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ');
      passwordInput.focus();
      return;
    }
    
    // ตรวจสอบรหัสผ่านตรงกัน
    if (passwordInput.value !== confirmInput.value) {
      event.preventDefault();
      showFormError('ລະຫັດຜ່ານບໍ່ກົງກັນ');
      confirmInput.focus();
      return;
    }
    
    // ตรวจสอบว่าเลือกวัดแล้วหรือยัง (สำหรับ admin และ user)
    if ((roleSelect.value === 'admin' || roleSelect.value === 'user') && templeSelect.value === '') {
      event.preventDefault();
      showFormError('ກະລຸນາເລືອກວັດ');
      templeSelect.focus();
      return;
    }
    
    // ตรวจสอบว่าเลือกแขวงแล้วหรือยัง (สำหรับ province_admin)
    if (roleSelect.value === 'province_admin' && provinceSelect.value === '') {
      event.preventDefault();
      showFormError('ກະລຸນາເລືອກແຂວງ');
      provinceSelect.focus();
      return;
    }
  });
  
  // ฟังก์ชันแสดงข้อผิดพลาด
  function showFormError(message) {
    // ตรวจสอบว่ามีแถบข้อผิดพลาดอยู่แล้วหรือไม่
    let errorDiv = document.querySelector('.form-error');
    
    if (!errorDiv) {
      // สร้างแถบข้อผิดพลาดใหม่
      errorDiv = document.createElement('div');
      errorDiv.className = 'form-error';
      errorDiv.setAttribute('role', 'alert');
      
      const errorTitle = document.createElement('div');
      errorTitle.className = 'form-error-title';
      errorTitle.innerHTML = '<i class="fas fa-exclamation-circle"></i> ເກີດຂໍ້ຜິດພາດ!';
      
      const errorList = document.createElement('ul');
      errorList.className = 'form-error-list';
      
      errorDiv.appendChild(errorTitle);
      errorDiv.appendChild(errorList);
      
      // เพิ่มแถบข้อผิดพลาดก่อนฟอร์ม
      const formContainer = document.querySelector('.temple-form-container');
      const templeCard = document.querySelector('.temple-card');
      formContainer.insertBefore(errorDiv, templeCard);
    }
    
    // เพิ่มข้อความผิดพลาด
    const errorList = errorDiv.querySelector('.form-error-list');
    const errorItem = document.createElement('li');
    errorItem.textContent = message;
    errorList.appendChild(errorItem);
    
    // เลื่อนไปที่แถบข้อผิดพลาด
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  
  // สลับการแสดงรหัสผ่าน
  const togglePassword = document.getElementById('togglePassword');
  const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
  
  togglePassword.addEventListener('click', function() {
    togglePasswordVisibility(passwordInput, this);
  });
  
  toggleConfirmPassword.addEventListener('click', function() {
    togglePasswordVisibility(confirmInput, this);
  });
  
  function togglePasswordVisibility(input, button) {
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    
    // เปลี่ยนไอคอน
    if (type === 'text') {
      button.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
      button.innerHTML = '<i class="fas fa-eye"></i>';
    }
  }
  
  // เอี้ยนใช้ฟังก์ชันเมื่อโหลดหน้า
  updateFormByRole();
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>