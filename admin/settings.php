<?php
// filepath: c:\xampp\htdocs\temples\admin\setting.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ป้องกันการแคชหน้าเว็บ
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງການຕັ້ງຄ່າ (ສະເພາະ superadmin)
if ($_SESSION['user']['role'] !== 'superadmin') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງການຕັ້ງຄ່າລະບົບ";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ດຶງຂໍ້ມູນການຕັ້ງຄ່າທັງຫມົດຈາກຖານຂໍ້ມູນ
$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row;
    }
} catch (PDOException $e) {
    // ຖ້າບໍ່ມີຕາຕະລາງ ຫຼື ມີຂໍ້ຜິດພາດອື່ນໆ
    $settings = [];
    $_SESSION['error'] = "ບໍ່ສາມາດດຶງຂໍ້ມູນການຕັ້ງຄ່າໄດ້: " . $e->getMessage();
}

// ຖ້າບໍ່ມີຂໍ້ມູນການຕັ້ງຄ່າ, ໃຫ້ໃສ່ຂໍ້ມູນເລີ່ມຕົ້ນ
if (empty($settings)) {
    $default_settings = [
        // ການຕັ້ງຄ່າທົ່ວໄປ
        ['site_name', 'ລະບົບຈັດການວັດ', 'general', 'ຊື່ເວັບໄຊທ໌', 'text', ''], 
        ['site_description', 'ລະບົບຈັດການຂໍ້ມູນວັດ ແລະ ກິດຈະກໍາ', 'general', 'ຄໍາອະທິບາຍເວັບໄຊທ໌', 'textarea', ''],
        ['admin_email', 'admin@example.com', 'general', 'ອີເມລຜູ້ດູແລລະບົບ', 'email', ''],
        ['contact_phone', '', 'general', 'ເບີໂທຕິດຕໍ່', 'text', ''],
        ['footer_text', '© ' . date('Y') . ' ລະບົບຈັດການວັດ. ສະຫງວນລິຂະສິດ.', 'general', 'ຂໍ້ຄວາມສ່ວນລຸ່ມເວັບໄຊທ໌', 'textarea', ''],
        
        // ການຕັ້ງຄ່າລະບົບ
        ['items_per_page', '10', 'system', 'ຈໍານວນລາຍການຕໍ່ຫນ້າ', 'number', ''],
        ['date_format', 'd/m/Y', 'system', 'ຮູບແບບວັນທີ', 'text', ''],
        ['time_format', 'H:i', 'system', 'ຮູບແບບເວລາ', 'text', ''],
        ['timezone', 'Asia/Bangkok', 'system', 'ເຂດເວລາ', 'text', ''],
        ['maintenance_mode', '0', 'system', 'ໂຫມດບໍາລຸງຮັກສາ', 'checkbox', ''],
        ['maintenance_message', 'ລະບົບກໍາລັງຢູ່ໃນການບໍາລຸງຮັກສາ. ກະລຸນາກັບມາໃໝ່ໃນພາຍຫຼັງ.', 'system', 'ຂໍ້ຄວາມແຈ້ງເຕືອນບໍາລຸງຮັກສາ', 'textarea', ''],
        ['maintenance_end_time', '', 'system', 'ເວລາສິ້ນສຸດການບໍາລຸງຮັກສາ', 'datetime-local', ''],
        ['maintenance_allowed_ips', '', 'system', 'IP ທີ່ອະນຸຍາດໃຫ້ເຂົ້າເຖິງລະບົບໃນໂຫມດບໍາລຸງຮັກສາ (ຄັ່ນດ້ວຍເຄື່ອງໝາຍຈຸດ)', 'textarea', ''],
        
        // ການຕັ້ງຄ່າອີເມລ
        ['mail_driver', 'smtp', 'email', 'ຕົວຂັບເຄື່ອນອີເມລ', 'select', 'smtp,mail,sendmail'],
        ['mail_host', 'smtp.example.com', 'email', 'SMTP Host', 'text', ''],
        ['mail_port', '587', 'email', 'SMTP Port', 'number', ''],
        ['mail_username', '', 'email', 'SMTP Username', 'text', ''],
        ['mail_password', '', 'email', 'SMTP Password', 'password', ''],
        ['mail_encryption', 'tls', 'email', 'SMTP Encryption', 'select', 'tls,ssl,'],
        ['mail_from_address', 'noreply@example.com', 'email', 'ອີເມລຜູ້ສົ່ງ', 'email', ''],
        ['mail_from_name', 'ລະບົບຈັດການວັດ', 'email', 'ຊື່ຜູ້ສົ່ງ', 'text', ''],
        
        // ການຕັ້ງຄ່າຄວາມປອດໄພ
        ['password_min_length', '8', 'security', 'ຄວາມຍາວຂັ້ນຕ່ຳຂອງລະຫັດຜ່ານ', 'number', ''],
        ['password_require_special', '1', 'security', 'ຕ້ອງການຕົວອັກສອນພິເສດໃນລະຫັດຜ່ານ', 'checkbox', ''],
        ['password_require_number', '1', 'security', 'ຕ້ອງການຕົວເລກໃນລະຫັດຜ່ານ', 'checkbox', ''],
        ['password_require_uppercase', '1', 'security', 'ຕ້ອງການຕົວອັກສອນໃຫຍ່ໃນລະຫັດຜ່ານ', 'checkbox', ''],
        ['session_lifetime', '120', 'security', 'ເວລາໝົດອາຍຸຂອງເຊສຊັນ (ນາທີ)', 'number', ''],
        ['enable_2fa', '0', 'security', 'ເປີດໃຊ້ການຢືນຢັນສອງຂັ້ນຕອນ', 'checkbox', ''],
        
        // ການຕັ້ງຄ່າການລົງທະບຽນແລະເຂົ້າສູ່ລະບົບ
        ['allow_registration', '0', 'registration', 'ອະນຸຍາດໃຫ້ລົງທະບຽນຜູ່ໃຊ້ໃໝ່', 'checkbox', ''],
        ['default_user_role', 'user', 'registration', 'ບົດບາດເລີ່ມຕົ້ນຂອງຜູ່ໃຊ້ໃໝ່', 'select', 'user,admin'],
        ['require_email_verification', '1', 'registration', 'ຕ້ອງການການຢືນຢັນອີເມລ', 'checkbox', ''],
        ['max_login_attempts', '5', 'registration', 'ຈໍານວນສູງສຸດຂອງການພະຍາຍາມເຂົ້າສູ່ລະບົບ', 'number', ''],
        ['lockout_time', '30', 'registration', 'ເວລາລັອກ (ນາທີ)', 'number', '']
    ];
    
    try {
        // เริ่ม transaction
        $pdo->beginTransaction();
        
        // ตรวจสอบว่ามีตาราง settings หรือไม่
        $tables = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchAll();
        if (empty($tables)) {
            // สร้างตาราง settings
            $pdo->exec("
                CREATE TABLE settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(255) NOT NULL UNIQUE,
                    setting_value TEXT,
                    setting_group VARCHAR(100) NOT NULL,
                    description TEXT,
                    type VARCHAR(50) DEFAULT 'text',
                    options TEXT NULL,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // เพิ่มข้อมูลเริ่มต้น
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_group, description, type, options)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($default_settings as $setting) {
            $stmt->execute($setting);
        }
        
        // ยืนยัน transaction
        $pdo->commit();
        
        // ดึงข้อมูลการตั้งค่าทั้งหมดจากฐานข้อมูลอีกครั้ง
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
        }
        
    } catch (PDOException $e) {
        // ยกเลิก transaction ถ้ามีข้อผิดพลาด
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "ບໍ່ສາມາດສ້າງການຕັ້ງຄ່າເລີ່ມຕົ້ນໄດ້: " . $e->getMessage();
    }
}

// จัดกลุ่มการตั้งค่า
$grouped_settings = [];
foreach ($settings as $key => $setting) {
    $grouped_settings[$setting['setting_group']][] = $setting;
}

// กำหนดชื่อกลุ่มการตั้งค่า
$group_names = [
    'general' => 'ການຕັ້ງຄ່າທົ່ວໄປ',
    'system' => 'ການຕັ້ງຄ່າລະບົບ',
    'email' => 'ການຕັ້ງຄ່າອີເມລ',
    'security' => 'ການຕັ້ງຄ່າຄວາມປອດໄພ',
    'registration' => 'ການລົງທະບຽນແລະເຂົ້າສູ່ລະບົບ'
];

// ກໍານົດຕົວແປເພື່ອເກັບຂໍ້ຜິດພາດ
$errors = [];

// ตรวจสอบสถานะปัจจุบันของโหมดบำรุงรักษา
$maintenance_active = !empty($settings['maintenance_mode']['setting_value']) && $settings['maintenance_mode']['setting_value'] == '1';

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ประมวลผลการอัปเดตการตั้งค่า
    try {
        $pdo->beginTransaction();
        
        $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            // ข้าม csrf_token และปุ่มส่งฟอร์ม
            if ($key === 'csrf_token' || $key === 'submit') {
                continue;
            }
            
            // ถ้าเป็น checkbox ที่ไม่ได้เลือก
            if (!isset($_POST[$key]) && isset($settings[$key]) && $settings[$key]['type'] === 'checkbox') {
                $value = '0';
            }
            
            // อัปเดตการตั้งค่าในฐานข้อมูล
            if (isset($settings[$key])) {
                $update_stmt->execute([$value, $key]);
            }
        }
        
        // กรณีมีการเปลี่ยนแปลงโหมดบำรุงรักษา
        if (isset($_POST['maintenance_mode'])) {
            // เพิ่มการ update ค่าโดยตรงเพื่อให้แน่ใจว่าค่าถูกบันทึกถูกต้อง
            $maintenance_value = $_POST['maintenance_mode'] === '1' ? '1' : '0';
            $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
            $update_stmt->execute([$maintenance_value]);
            
            // ล้าง OPcache ถ้าเปิดใช้งาน
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // สร้างไฟล์เพื่อล้างแคชฝั่ง server
            $flush_file = '../tmp/flush_cache_' . time() . '.txt';
            if (!is_dir('../tmp')) {
                mkdir('../tmp', 0755, true);
            }
            file_put_contents($flush_file, date('Y-m-d H:i:s'));
            
            // กำหนดค่าพิเศษใน session
            $_SESSION['settings_updated'] = time();
            
            // แสดงข้อความตามค่าที่เปลี่ยน
            $_SESSION['success'] = $maintenance_value === '1' 
                ? "ເປີດໃຊ້ງານໂຫມດບຳລຸງຮັກສາສຳເລັດແລ້ວ" 
                : "ປິດໃຊ້ງານໂຫມດບຳລຸງຮັກສາສຳເລັດແລ້ວ";
        } else {
            $pdo->commit();
            $_SESSION['success'] = "ອັບເດດການຕັ້ງຄ່າສໍາເລັດແລ້ວ";
        }
        
        // ดึงข้อมูลการตั้งค่าใหม่
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
        }
        
        // จัดกลุ่มการตั้งค่าใหม่
        $grouped_settings = [];
        foreach ($settings as $key => $setting) {
            $grouped_settings[$setting['setting_group']][] = $setting;
        }
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "ບໍ່ສາມາດອັບເດດການຕັ້ງຄ່າໄດ້: " . $e->getMessage();
    }
    
    // รีไดเร็กเพื่อหลีกเลี่ยงการส่งฟอร์มซ้ำ
    header('Location: ' . $_SERVER['PHP_SELF'] . '?group=' . ($_GET['group'] ?? 'general') . '&t=' . time());
    exit;
}

// ກໍານົດຫົວຂໍ້ໜ້າ
$page_title = 'ຕັ້ງຄ່າລະບົບ';
require_once '../includes/header.php';
?>

<!-- เพิ่ม CSS เฉพาะสำหรับหน้านี้ -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/maintenace.css">

<?php if ($maintenance_active): ?>
<div class="maintenance-banner">
  <div class="maintenance-banner-text">
    <i class="fas fa-exclamation-triangle maintenance-banner-icon"></i>
    <div>
      <strong>ແຈ້ງເຕືອນ:</strong> 
      <span>ລະບົບກຳລັງຢູ່ໃນໂຫມດບຳລຸງຮັກສາ</span>
    </div>
  </div>
  <div class="maintenance-banner-actions">
    <a href="<?= $base_url ?>./maintenance.php" target="_blank" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
      <i class="fas fa-eye"></i> ເບິ່ງຫນ້າແຈ້ງບຳລຸງຮັກສາ
    </a>
  </div>
</div>
<?php endif; ?>

<div class="settings-container">
  <div class="settings-header">
    <div class="settings-title">
      <div class="settings-title-icon">
        <i class="fas fa-cog"></i>
      </div>
      ຕັ້ງຄ່າລະບົບ
    </div>
    <a href="<?= $base_url ?>admin/flush_cache.php" class="btn btn-secondary">
      <i class="fas fa-sync-alt"></i> ລ້າງແຄຊ
    </a>
  </div>
  
  <?php if (isset($_SESSION['success'])): ?>
  <div class="notification notification-success">
    <div class="notification-icon">
      <i class="fas fa-check-circle"></i>
    </div>
    <div class="notification-message">
      <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
  </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
  <div class="notification notification-error">
    <div class="notification-icon">
      <i class="fas fa-exclamation-circle"></i>
    </div>
    <div class="notification-message">
      <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="settings-nav">
    <div class="settings-nav-list">
      <?php $active_group = isset($_GET['group']) ? $_GET['group'] : 'general'; ?>
      <?php foreach ($group_names as $group_key => $group_name): ?>
      <a href="?group=<?= $group_key ?>" 
         class="settings-nav-item <?= $active_group === $group_key ? 'active' : '' ?>">
        <?php 
        $group_icons = [
          'general' => '<i class="fas fa-sliders-h mr-2"></i>',
          'system' => '<i class="fas fa-server mr-2"></i>',
          'email' => '<i class="fas fa-envelope mr-2"></i>',
          'security' => '<i class="fas fa-shield-alt mr-2"></i>',
          'registration' => '<i class="fas fa-user-plus mr-2"></i>'
        ];
        echo $group_icons[$group_key] ?? '';
        echo $group_name; 
        ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="settings-body">
    <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="settings-form">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      
      <?php
      $current_group = isset($_GET['group']) ? $_GET['group'] : 'general';
      if (isset($grouped_settings[$current_group])):
        
        // Group descriptions
        $group_descriptions = [
          'general' => 'ຕັ້ງຄ່າທົ່ວໄປຂອງເວັບໄຊທ໌ເຊັ່ນຊື່, ຄໍາອະທິບາຍ, ແລະ ຂໍ້ມູນຕິດຕໍ່.',
          'system' => 'ຕັ້ງຄ່າສໍາລັບການເຮັດວຽກຂອງລະບົບ ລວມເຖິງໂຫມດບໍາລຸງຮັກສາ ແລະ ການຕັ້ງຄ່າອື່ນໆ.',
          'email' => 'ຕັ້ງຄ່າລະບົບການສົ່ງອີເມລ ແລະ ການແຈ້ງເຕືອນ.',
          'security' => 'ຕັ້ງຄ່າຄວາມປອດໄພຂອງລະບົບ ລວມທັງນະໂຍບາຍຂອງລະຫັດຜ່ານ.',
          'registration' => 'ຕັ້ງຄ່າການລົງທະບຽນແລະເຂົ້າສູ່ລະບົບຂອງຜູ່ໃຊ້.',
        ];
        
        // Group icons for header
        $group_header_icons = [
          'general' => '<i class="fas fa-sliders-h"></i>',
          'system' => '<i class="fas fa-server"></i>',
          'email' => '<i class="fas fa-envelope"></i>',
          'security' => '<i class="fas fa-shield-alt"></i>',
          'registration' => '<i class="fas fa-user-plus"></i>'
        ];
      ?>
      
      <div class="settings-group" style="animation-delay: 0.1s;">
        <div class="settings-group-header">
          <h2 class="settings-group-title">
            <?= $group_header_icons[$current_group] ?? ''; ?>
            <?= $group_names[$current_group] ?? $current_group ?>
          </h2>
          <p class="settings-group-desc"><?= $group_descriptions[$current_group] ?? '' ?></p>
        </div>
        
        <div class="settings-group-body">
          <?php foreach ($grouped_settings[$current_group] as $index => $setting): ?>
          <div class="settings-item" style="animation-delay: <?= 0.2 + ($index * 0.05) ?>s;">
            <div>
              <label for="<?= $setting['setting_key'] ?>" class="settings-label">
                <?= htmlspecialchars($setting['description']) ?>
              </label>
              <p class="settings-description">
                <?php
                // เพิ่มคำอธิบายเพิ่มเติมสำหรับการตั้งค่า
                $extra_descriptions = [
                  'site_name' => 'ຊື່ເວັບໄຊທ໌ນີ້ຈະສະແດງໃນແຖບຊື່ຂອງບຣາວເຊີ.',
                  'site_description' => 'ຄໍາອະທິບາຍສັ້ນໆກ່ຽວກັບເວັບໄຊທ໌.',
                  'maintenance_mode' => 'ເມື່ອເປີດໃຊ້ງານ, ຜູ້ໃຊ້ທົ່ວໄປຈະບໍ່ສາມາດເຂົ້າໃຊ້ລະບົບໄດ້ ແລະ ຈະເຫັນຂໍ້ຄວາມແຈ້ງເຕືອນ.',
                  'mail_driver' => 'ເລືອກຕົວຂັບເຄື່ອນທີ່ຈະໃຊ້ສໍາລັບການສົ່ງອີເມລ.',
                  'password_min_length' => 'ຈໍານວນຕົວອັກສອນຂັ້ນຕ່ຳທີ່ຈໍາເປັນສໍາລັບລະຫັດຜ່ານ.',
                  'session_lifetime' => 'ໄລຍະເວລາໃນການເຂົ້າສູ່ລະບົບທີ່ຈະຫມົດອາຍຸຖ້າບໍ່ມີການໃຊ້ງານ.',
                ];
                echo $extra_descriptions[$setting['setting_key']] ?? '';
                ?>
              </p>
            </div>
            
            <div>
              <?php
              // สร้างช่องป้อนข้อมูลตามประเภท
              switch($setting['type']) {
                case 'textarea':
                  ?>
                  <textarea id="<?= $setting['setting_key'] ?>" 
                            name="<?= $setting['setting_key'] ?>" 
                            rows="3"
                            class="settings-textarea"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                  <?php
                  break;
                
                case 'checkbox':
                  ?>
                  <div class="settings-checkbox-wrapper">
                    <input type="checkbox" 
                           id="<?= $setting['setting_key'] ?>" 
                           name="<?= $setting['setting_key'] ?>" 
                           value="1" 
                           class="settings-checkbox"
                           <?= $setting['setting_value'] == '1' ? 'checked' : '' ?>>
                    <label for="<?= $setting['setting_key'] ?>" class="settings-checkbox-label">
                      ເປີດໃຊ້ງານ
                    </label>
                  </div>
                  <?php
                  break;
                
                case 'select':
                  $options = explode(',', $setting['options'] ?? '');
                  ?>
                  <select id="<?= $setting['setting_key'] ?>" 
                          name="<?= $setting['setting_key'] ?>" 
                          class="settings-select">
                    <?php foreach ($options as $option): ?>
                    <option value="<?= $option ?>" <?= $setting['setting_value'] === $option ? 'selected' : '' ?>>
                      <?= $option ? htmlspecialchars(ucfirst($option)) : 'ບໍ່ມີ' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <?php
                  break;
                  
                case 'number':
                  ?>
                  <input type="number" 
                         id="<?= $setting['setting_key'] ?>" 
                         name="<?= $setting['setting_key'] ?>" 
                         value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                         class="settings-input">
                  <?php
                  break;
                  
                case 'password':
                  ?>
                  <input type="password" 
                         id="<?= $setting['setting_key'] ?>" 
                         name="<?= $setting['setting_key'] ?>" 
                         value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                         class="settings-input">
                  <?php
                  break;
                  
                case 'email':
                  ?>
                  <input type="email" 
                         id="<?= $setting['setting_key'] ?>" 
                         name="<?= $setting['setting_key'] ?>" 
                         value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                         class="settings-input">
                  <?php
                  break;
                  
                case 'datetime-local':
                  ?>
                  <input type="datetime-local" 
                         id="<?= $setting['setting_key'] ?>" 
                         name="<?= $setting['setting_key'] ?>" 
                         value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                         class="settings-input">
                  <?php
                  break;
                  
                default: // text
                  ?>
                  <input type="text" 
                         id="<?= $setting['setting_key'] ?>" 
                         name="<?= $setting['setting_key'] ?>" 
                         value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                         class="settings-input">
                  <?php
                  break;
              }
              ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <?php else: ?>
      <div class="settings-group text-center py-12">
        <div class="flex flex-col items-center justify-center">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
            <i class="fas fa-exclamation-triangle text-gray-500 text-xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-900">ບໍ່ພົບການຕັ້ງຄ່າໃນກຸ່ມນີ້</h3>
          <p class="mt-1 text-gray-500">ກະລຸນາເລືອກກຸ່ມການຕັ້ງຄ່າອື່ນ ຫຼື ສ້າງການຕັ້ງຄ່າໃໝ່ໃນກຸ່ມນີ້.</p>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="settings-footer">
        <a href="<?= $base_url ?>dashboard.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> ກັບຄືນ
        </a>
        <button type="submit" name="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> ບັນທຶກການຕັ້ງຄ່າ
        </button>
      </div>
    </form>
  </div>
</div>

<!-- เพิ่ม JavaScript สำหรับปรับปรุง UX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // หากมีการเปลี่ยนแปลงในฟอร์ม
  const form = document.querySelector('.settings-form');
  const inputs = form.querySelectorAll('input, textarea, select');
  let formChanged = false;
  
  inputs.forEach(input => {
    input.addEventListener('change', function() {
      formChanged = true;
    });
  });
  
  // แจ้งเตือนเมื่อออกจากหน้าโดยไม่บันทึก
  window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
  
  // จัดการแสดงและซ่อนฟิลด์ที่เกี่ยวข้องกับ maintenance mode
  const maintenanceMode = document.getElementById('maintenance_mode');
  const maintenanceRelatedFields = [
    'maintenance_message',
    'maintenance_end_time',
    'maintenance_allowed_ips'
  ];
  
  if (maintenanceMode) {
    // ตรวจสอบสถานะเริ่มต้น
    const updateFieldsVisibility = function() {
      maintenanceRelatedFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          const fieldItem = field.closest('.settings-item');
          if (fieldItem) {
            if (maintenanceMode.checked) {
              fieldItem.style.display = 'grid';
              fieldItem.style.opacity = '0';
              setTimeout(() => {
                fieldItem.style.opacity = '1';
              }, 100);
            } else {
              fieldItem.style.opacity = '0';
              setTimeout(() => {
                fieldItem.style.display = 'none';
              }, 300);
            }
          }
        }
      });
    };
    
    // ตั้งค่าเริ่มต้น
    updateFieldsVisibility();
    
    // เมื่อมีการเปลี่ยนแปลง checkbox
    maintenanceMode.addEventListener('change', updateFieldsVisibility);
  }
  
  // เลือกเมนูที่กำลังใช้งาน
  const currentGroup = '<?= $current_group ?>';
  const menuItems = document.querySelectorAll('.settings-nav-item');
  menuItems.forEach(item => {
    if (item.getAttribute('href').includes(currentGroup)) {
      item.classList.add('active');
    }
  });
  
  // เพิ่มเอฟเฟกต์ scroll ที่นุ่มนวลสำหรับ navigation
  const navLinks = document.querySelectorAll('.settings-nav-item');
  navLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      // ถ้ามีการเปลี่ยนแปลงฟอร์ม ให้ถามก่อนเปลี่ยนหน้า
      if (formChanged) {
        if (!confirm('ທ່ານມີການປ່ຽນແປງທີ່ຍັງບໍ່ໄດ້ບັນທຶກ. ຕ້ອງການອອກຈາກໜ້ານີ້ບໍ?')) {
          e.preventDefault();
        }
      }
    });
  });
});
</script>

<?php
require_once '../includes/footer.php';
?>