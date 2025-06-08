<?php
// filepath: c:\xampp\htdocs\temples\admin\setting.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

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

// ຕາຕະລາງສໍາລັບເກັບການຕັ້ງຄ່າ
// ຫມາຍເຫດ: ຖ້າຍັງບໍ່ມີຕາຕະລາງນີ້, ໃຫ້ສ້າງດ້ວຍຄໍາສັ່ງ SQL:
/*
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(100) NOT NULL,
    description TEXT,
    type VARCHAR(50) DEFAULT 'text',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

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
        ['site_name', 'ລະບົບຈັດການວັດ', 'general', 'ຊື່ເວັບໄຊທ໌', 'text', ''], // เพิ่มพารามิเตอร์ที่ 6 เป็นค่าว่าง
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
        // ເລີ່ມຕົ້ນທໍາລຸກໍາການເພີ່ມຂໍ້ມູນເລີ່ມຕົ້ນ
        $pdo->beginTransaction();
        
        // ກວດສອບວ່າມີຕາຕະລາງ settings ຫຼືບໍ່
        $tables = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchAll();
        if (empty($tables)) {
            // ສ້າງຕາຕະລາງ settings
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
        
        // ເພີ່ມຂໍ້ມູນເລີ່ມຕົ້ນ
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_group, description, type, options)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($default_settings as $setting) {
            $stmt->execute($setting);
        }
        
        // ຢືນຢັນທໍາລຸກໍາ
        $pdo->commit();
        
        // ດຶງຂໍ້ມູນການຕັ້ງຄ່າທັງຫມົດຈາກຖານຂໍ້ມູນອີກຄັ້ງ
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id");
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
        }
        
    } catch (PDOException $e) {
        // ຍົກເລີກທໍາລຸກໍາຖ້າມີຂໍ້ຜິດພາດ
        try {
            // ก่อนทำการ rollback ต้องตรวจสอบก่อนว่ามี active transaction หรือไม่
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = "ບໍ່ສາມາດສ້າງການຕັ້ງຄ່າເລີ່ມຕົ້ນໄດ້: " . $e->getMessage();
        } catch (PDOException $e) {
            // จัดการกรณีที่เกิดข้อผิดพลาดซ้อน
            $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດຫຼາຍຢ່າງ: " . $e->getMessage();
        }
    }
}

// ຈັດກຸ່ມການຕັ້ງຄ່າ
$grouped_settings = [];
foreach ($settings as $key => $setting) {
    $grouped_settings[$setting['setting_group']][] = $setting;
}

// ກໍານົດຊື່ກຸ່ມການຕັ້ງຄ່າ
$group_names = [
    'general' => 'ການຕັ້ງຄ່າທົ່ວໄປ',
    'system' => 'ການຕັ້ງຄ່າລະບົບ',
    'email' => 'ການຕັ້ງຄ່າອີເມລ',
    'security' => 'ການຕັ້ງຄ່າຄວາມປອດໄພ',
    'registration' => 'ການລົງທະບຽນແລະເຂົ້າສູ່ລະບົບ'
];

// ກໍານົດຕົວແປເພື່ອເກັບຂໍ້ຜິດພາດ
$errors = [];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // ປະມວນຜົນການອັບເດດການຕັ້ງຄ່າ
    try {
        $pdo->beginTransaction();
        
        $update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        
        foreach ($_POST as $key => $value) {
            // ຂ້າມ csrf_token ແລະ ປຸ່ມສົ່ງຟອມ
            if ($key === 'csrf_token' || $key === 'submit') {
                continue;
            }
            
            // ຖ້າເປັນ checkbox ທີ່ບໍ່ໄດ້ເລືອກ
            if (!isset($_POST[$key]) && isset($settings[$key]) && $settings[$key]['type'] === 'checkbox') {
                $value = '0';
            }
            
            // ອັບເດດການຕັ້ງຄ່າໃນຖານຂໍ້ມູນ
            if (isset($settings[$key])) {
                $update_stmt->execute([$value, $key]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "ອັບເດດການຕັ້ງຄ່າສໍາເລັດແລ້ວ";
        
        // ດຶງຂໍ້ມູນການຕັ້ງຄ່າໃໝ່
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group, id");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row;
        }
        
        // ຈັດກຸ່ມການຕັ້ງຄ່າໃໝ່
        $grouped_settings = [];
        foreach ($settings as $key => $setting) {
            $grouped_settings[$setting['setting_group']][] = $setting;
        }
        
    } catch (PDOException $e) {
        // ก่อนทำการ rollback ต้องตรวจสอบก่อนว่ามี active transaction หรือไม่
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "ບໍ່ສາມາດອັບເດດການຕັ້ງຄ່າໄດ້: " . $e->getMessage();
    }
    
    // ຣີໄດເຣັກເພື່ອຫຼີກລ່ຽງການສົ່ງຟອມຊໍ້າ
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ກໍານົດຫົວຂໍ້ໜ້າ
$page_title = 'ຕັ້ງຄ່າລະບົບ';
require_once '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ຕັ້ງຄ່າລະບົບ</h1>
            <p class="text-sm text-gray-600">ຈັດການການຕັ້ງຄ່າທັງໝົດຂອງລະບົບ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
    <!-- ຂໍ້ຄວາມແຈ້ງສໍາເລັດ -->
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <!-- ຂໍ້ຄວາມແຈ້ງຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ຟອມຕັ້ງຄ່າ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <!-- ແຖບຕັ້ງຄ່າ -->
        <div class="border-b border-gray-200">
            <nav class="flex overflow-x-auto py-3 px-4">
                <?php $active_group = isset($_GET['group']) ? $_GET['group'] : 'general'; ?>
                <?php foreach ($group_names as $group_key => $group_name): ?>
                <a href="?group=<?= $group_key ?>" 
                   class="whitespace-nowrap px-4 py-2 mr-2 rounded-md text-sm font-medium 
                         <?= $active_group === $group_key ? 
                             'bg-indigo-100 text-indigo-700 hover:bg-indigo-200' : 
                             'text-gray-500 hover:text-gray-700 hover:bg-gray-100' ?>">
                    <?= $group_name ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <!-- ຟອມຕັ້ງຄ່າ -->
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- ຕັ້ງຄ່າຕາມກຸ່ມທີ່ເລືອກ -->
            <?php
            $current_group = isset($_GET['group']) ? $_GET['group'] : 'general';
            if (isset($grouped_settings[$current_group])):
            ?>
            
            <div class="space-y-8">
                <!-- ຫົວຂໍ້ກຸ່ມການຕັ້ງຄ່າ -->
                <div>
                    <h2 class="text-lg font-medium text-gray-900"><?= $group_names[$current_group] ?? $current_group ?></h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php
                        $group_descriptions = [
                            'general' => 'ຕັ້ງຄ່າທົ່ວໄປຂອງເວັບໄຊທ໌ເຊັ່ນຊື່, ຄໍາອະທິບາຍ, ແລະ ຂໍ້ມູນຕິດຕໍ່.',
                            'system' => 'ຕັ້ງຄ່າສໍາລັບການເຮັດວຽກຂອງລະບົບ.',
                            'email' => 'ຕັ້ງຄ່າລະບົບການສົ່ງອີເມລ.',
                            'security' => 'ຕັ້ງຄ່າຄວາມປອດໄພຂອງລະບົບ ລວມທັງນະໂຍບາຍຂອງລະຫັດຜ່ານ.',
                            'registration' => 'ຕັ້ງຄ່າການລົງທະບຽນແລະເຂົ້າສູ່ລະບົບຂອງຜູ່ໃຊ້.',
                        ];
                        echo $group_descriptions[$current_group] ?? '';
                        ?>
                    </p>
                </div>
                
                <!-- ລາຍການຕັ້ງຄ່າ -->
                <?php foreach ($grouped_settings[$current_group] as $setting): ?>
                <div class="border-t border-gray-200 pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <label for="<?= $setting['setting_key'] ?>" class="block text-sm font-medium text-gray-700">
                                <?= htmlspecialchars($setting['description']) ?>
                            </label>
                            <?php if ($setting['setting_key'] === 'site_name'): ?>
                            <p class="mt-1 text-sm text-gray-500">ຊື່ເວັບໄຊທ໌ນີ້ຈະສະແດງໃນແຖບຊື່ຂອງບຣາວເຊີ.</p>
                            <?php endif; ?>
                        </div>
                        <div class="md:col-span-2">
                            <?php
                            // ສ້າງຊ່ອງປ້ອນຂໍ້ມູນຕາມປະເພດ
                            switch($setting['type']) {
                                case 'textarea':
                                    ?>
                                    <textarea id="<?= $setting['setting_key'] ?>" 
                                              name="<?= $setting['setting_key'] ?>" 
                                              rows="3"
                                              class="form-textarea rounded-md shadow-sm mt-1 block w-full"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                    <?php
                                    break;
                                
                                case 'checkbox':
                                    ?>
                                    <div class="mt-1">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" 
                                                   id="<?= $setting['setting_key'] ?>" 
                                                   name="<?= $setting['setting_key'] ?>" 
                                                   value="1" 
                                                   class="form-checkbox rounded text-indigo-600"
                                                   <?= $setting['setting_value'] == '1' ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-600">ເປີດໃຊ້ງານ</span>
                                        </label>
                                    </div>
                                    <?php
                                    break;
                                
                                case 'select':
                                    $options = explode(',', $setting['options'] ?? '');
                                    ?>
                                    <select id="<?= $setting['setting_key'] ?>" 
                                            name="<?= $setting['setting_key'] ?>" 
                                            class="form-select rounded-md shadow-sm mt-1 block w-full">
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
                                           class="form-input rounded-md shadow-sm mt-1 block w-full">
                                    <?php
                                    break;
                                    
                                case 'password':
                                    ?>
                                    <input type="password" 
                                           id="<?= $setting['setting_key'] ?>" 
                                           name="<?= $setting['setting_key'] ?>" 
                                           value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                           class="form-input rounded-md shadow-sm mt-1 block w-full">
                                    <?php
                                    break;
                                    
                                case 'email':
                                    ?>
                                    <input type="email" 
                                           id="<?= $setting['setting_key'] ?>" 
                                           name="<?= $setting['setting_key'] ?>" 
                                           value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                           class="form-input rounded-md shadow-sm mt-1 block w-full">
                                    <?php
                                    break;
                                    
                                default: // text
                                    ?>
                                    <input type="text" 
                                           id="<?= $setting['setting_key'] ?>" 
                                           name="<?= $setting['setting_key'] ?>" 
                                           value="<?= htmlspecialchars($setting['setting_value']) ?>" 
                                           class="form-input rounded-md shadow-sm mt-1 block w-full">
                                    <?php
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- ຖ້າບໍ່ມີການຕັ້ງຄ່າໃນກຸ່ມນີ້ -->
            <?php else: ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-gray-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900">ບໍ່ພົບການຕັ້ງຄ່າໃນກຸ່ມນີ້</h3>
                <p class="mt-1 text-gray-500">ກະລຸນາເລືອກກຸ່ມການຕັ້ງຄ່າອື່ນ ຫຼື ສ້າງການຕັ້ງຄ່າໃໝ່ໃນກຸ່ມນີ້.</p>
            </div>
            <?php endif; ?>
            
            <!-- ປຸ່ມບັນທຶກ -->
            <div class="mt-8 pt-5 border-t border-gray-200">
                <div class="flex justify-end">
                    <button type="button" onclick="window.location.href='<?= $base_url ?>dashboard.php'" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-2">
                        ຍົກເລີກ
                    </button>
                    <button type="submit" name="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-save mr-2"></i> ບັນທຶກການຕັ້ງຄ່າ
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>