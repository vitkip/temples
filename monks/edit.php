<?php
ob_start(); // เพิ่ม output buffering

$page_title = 'ແກ້ໄຂຂໍ້ມູນພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// Check if ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// Get monk data with temple and province info
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        t.name as temple_name,
        d.district_name,
        p.province_name
    FROM monks m
    LEFT JOIN temples t ON m.temple_id = t.id
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE m.id = ?
");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// Check edit permissions
$can_edit = false;
if ($user_role === 'superadmin') {
    $can_edit = true;
} elseif ($user_role === 'admin' && $user_temple_id == $monk['temple_id']) {
    $can_edit = true;
} elseif ($user_role === 'province_admin') {
    // Check if monk's temple is in admin's province
    $check_access = $pdo->prepare("
        SELECT COUNT(*) FROM temples t
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE t.id = ? AND upa.user_id = ?
    ");
    $check_access->execute([$monk['temple_id'], $user_id]);
    $can_edit = ($check_access->fetchColumn() > 0);
}

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນພະສົງນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// Get temples for dropdown based on user role
$temples = [];
if ($user_role === 'superadmin') {
    $temple_stmt = $pdo->query("
        SELECT t.id, t.name, p.province_name 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.status = 'active' 
        ORDER BY p.province_name, t.name
    ");
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'admin') {
    $temple_stmt = $pdo->prepare("
        SELECT t.id, t.name, p.province_name 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.id = ? AND t.status = 'active'
    ");
    $temple_stmt->execute([$user_temple_id]);
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    $temple_stmt = $pdo->prepare("
        SELECT t.id, t.name, p.province_name 
        FROM temples t
        JOIN provinces p ON t.province_id = p.province_id
        JOIN user_province_access upa ON p.province_id = upa.province_id
        WHERE upa.user_id = ? AND t.status = 'active'
        ORDER BY p.province_name, t.name
    ");
    $temple_stmt->execute([$user_id]);
    $temples = $temple_stmt->fetchAll();
}

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'monks/edit.php?id=' . $monk_id);
        exit;
    }
    
    // Validate input
    $prefix = trim($_POST['prefix'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $lay_name = trim($_POST['lay_name'] ?? '');
    $pansa = trim($_POST['pansa'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $birth_province = trim($_POST['birth_province'] ?? '');
    $ordination_date = trim($_POST['ordination_date'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $temple_id = isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0;
    $status = $_POST['status'] ?? 'active';
    $position = trim($_POST['position'] ?? '');
    $dharma_education = trim($_POST['dharma_education'] ?? '');
    
    // Validation rules
    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ພະສົງ";
    }
    
    if (empty($pansa) || !is_numeric($pansa)) {
        $errors[] = "ກະລຸນາປ້ອນພັນສາເປັນຕົວເລກ";
    }
    
    if (empty($temple_id)) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // Check permission to edit in this temple
    if ($user_role === 'admin' && $temple_id != $user_temple_id) {
        $errors[] = "ທ່ານບໍ່ມີສິດແກ້ໄຂພະສົງໃນວັດອື່ນ";
    } elseif ($user_role === 'province_admin') {
        $check_temple = $pdo->prepare("
            SELECT COUNT(*) FROM temples t
            JOIN user_province_access upa ON t.province_id = upa.province_id
            WHERE t.id = ? AND upa.user_id = ?
        ");
        $check_temple->execute([$temple_id, $user_id]);
        if ($check_temple->fetchColumn() == 0) {
            $errors[] = "ທ່ານບໍ່ມີສິດແກ້ໄຂພະສົງໃນວັດນີ້";
        }
    }
    
    // If validation passes
    if (empty($errors)) {
        try {
            // Handle photo upload
            $photo_path = $monk['photo'];
            
            if (!empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/monks/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "ກະລຸນາອັບໂຫລດຮູບພາບໃນຮູບແບບ JPG, JPEG, PNG ຫຼື GIF";
                } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) { // 5MB
                    $errors[] = "ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 5MB";
                } else {
                    $new_filename = 'monk_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // ตรวจสอบและสร้างไดเรกทอรี่ถ้าไม่มี
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        $photo_path = 'uploads/monks/' . $new_filename;
                        
                        // Delete old photo if exists and not default
                        if (!empty($monk['photo']) && file_exists('../' . $monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png') {
                            @unlink('../' . $monk['photo']);
                        }
                    } else {
                        $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການອັບໂຫລດຮູບພາບ";
                    }
                }
            }
            
            if (empty($errors)) {
                // Update monk data
                $stmt = $pdo->prepare("
                    UPDATE monks SET 
                        prefix = ?, name = ?, lay_name = ?, pansa = ?, birth_date = ?, 
                        birth_province = ?, ordination_date = ?, education = ?, contact_number = ?,
                        temple_id = ?, status = ?, position = ?, dharma_education = ?,
                        photo = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $prefix ?: null,
                    $name,
                    $lay_name ?: null,
                    $pansa,
                    $birth_date ?: null,
                    $birth_province ?: null,
                    $ordination_date ?: null,
                    $education ?: null,
                    $contact_number ?: null,
                    $temple_id,
                    $status,
                    $position ?: null,
                    $dharma_education ?: null,
                    $photo_path,
                    $monk_id
                ]);
                
                $_SESSION['success'] = "ແກ້ໄຂຂໍ້ມູນພະສົງສໍາເລັດແລ້ວ";
                header('Location: ' . $base_url . 'monks/view.php?id=' . $monk_id);
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການແກ້ໄຂຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<!-- Page Header -->
<div class="page-container">
    <div class="max-w-4xl mx-auto p-4">
        <div class="header-section flex justify-between items-center mb-8 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    ແກ້ໄຂຂໍ້ມູນພະສົງ
                </h1>
                <p class="text-sm text-amber-700 mt-1">
                    ຟອມແກ້ໄຂຂໍ້ມູນພະສົງ <?= htmlspecialchars($monk['prefix'] . ' ' . $monk['name']) ?>
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="<?= $base_url ?>monks/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center transition">
                    <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
                </a>
                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="btn btn-primary px-4 py-2 text-white rounded-lg flex items-center transition">
                    <i class="fas fa-eye mr-2"></i> ເບິ່ງລາຍລະອຽດ
                </a>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
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
        
        <!-- Edit Form -->
        <div class="card bg-white p-6">
            <form action="<?= $base_url ?>monks/edit.php?id=<?= $monk_id ?>" method="post" enctype="multipart/form-data" id="editMonkForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            ຂໍ້ມູນພື້ນຖານ
                        </h3>
                        
                        <div>
                            <label for="prefix" class="block text-sm font-medium text-gray-700 mb-1">ຄຳນຳໜ້າ</label>
                            <select name="prefix" id="prefix" class="form-select w-full">
                                <option value="">-- ເລືອກຄຳນຳໜ້າ --</option>
                                <option value="ພຣະ" <?= $monk['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                                <option value="ຄຸນແມ່ຂາວ" <?= $monk['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                                <option value="ສ.ນ" <?= $monk['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                                <option value="ສັງກະລີ" <?= $monk['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ຊື່ <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" class="form-input w-full" value="<?= htmlspecialchars($monk['name']) ?>" required>
                        </div>
                        
                        <div>
                            <label for="lay_name" class="block text-sm font-medium text-gray-700 mb-1">ນາມສະກຸນ</label>
                            <input type="text" name="lay_name" id="lay_name" class="form-input w-full" value="<?= htmlspecialchars($monk['lay_name'] ?? '') ?>">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="pansa" class="block text-sm font-medium text-gray-700 mb-1">ພັນສາ <span class="text-red-500">*</span></label>
                                <input type="number" name="pansa" id="pansa" class="form-input w-full" value="<?= htmlspecialchars($monk['pansa']) ?>" required min="0" max="100">
                            </div>
                            
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700 mb-1">ຕຳແໜ່ງ</label>
                                <input type="text" name="position" id="position" class="form-input w-full" value="<?= htmlspecialchars($monk['position'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ <span class="text-red-500">*</span></label>
                            <select name="temple_id" id="temple_id" class="form-select w-full" required <?= $user_role === 'admin' ? 'disabled' : '' ?>>
                                <option value="">ເລືອກວັດ</option>
                                <?php foreach ($temples as $temple): ?>
                                <option value="<?= $temple['id'] ?>" <?= $temple['id'] == $monk['temple_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($temple['name']) ?>
                                    <?php if (!empty($temple['province_name'])): ?>
                                        (<?= htmlspecialchars($temple['province_name']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($user_role === 'admin'): ?>
                                <input type="hidden" name="temple_id" value="<?= $monk['temple_id'] ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                            <select name="status" id="status" class="form-select w-full">
                                <option value="active" <?= $monk['status'] === 'active' ? 'selected' : '' ?>>ບວດຢູ່</option>
                                <option value="inactive" <?= $monk['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            ຂໍ້ມູນເພີ່ມເຕີມ
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນເດືອນປີເກີດ</label>
                                <input type="date" name="birth_date" id="birth_date" class="form-input w-full" value="<?= $monk['birth_date'] ? date('Y-m-d', strtotime($monk['birth_date'])) : '' ?>">
                            </div>
                            
                            <div>
                                <label for="ordination_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນບວດ</label>
                                <input type="date" name="ordination_date" id="ordination_date" class="form-input w-full" value="<?= $monk['ordination_date'] ? date('Y-m-d', strtotime($monk['ordination_date'])) : '' ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="birth_province" class="block text-sm font-medium text-gray-700 mb-1">ແຂວງເກີດ</label>
                            <select name="birth_province" id="birth_province" class="form-select w-full">
                                <option value="">-- ເລືອກແຂວງ --</option>
                                <option value="ນະຄອນຫຼວງວຽງຈັນ" <?= $monk['birth_province'] === 'ນະຄອນຫຼວງວຽງຈັນ' ? 'selected' : '' ?>>ນະຄອນຫຼວງວຽງຈັນ</option>
                                <option value="ຜົ້ງສາລີ" <?= $monk['birth_province'] === 'ຜົ້ງສາລີ' ? 'selected' : '' ?>>ຜົ້ງສາລີ</option>
                                <option value="ຫຼວງນ້ຳທາ" <?= $monk['birth_province'] === 'ຫຼວງນ້ຳທາ' ? 'selected' : '' ?>>ຫຼວງນ້ຳທາ</option>
                                <option value="ອຸດົມໄຊ" <?= $monk['birth_province'] === 'ອຸດົມໄຊ' ? 'selected' : '' ?>>ອຸດົມໄຊ</option>
                                <option value="ບໍ່ແກ້ວ" <?= $monk['birth_province'] === 'ບໍ່ແກ້ວ' ? 'selected' : '' ?>>ບໍ່ແກ້ວ</option>
                                <option value="ຫຼວງພະບາງ" <?= $monk['birth_province'] === 'ຫຼວງພະບາງ' ? 'selected' : '' ?>>ຫຼວງພະບາງ</option>
                                <option value="ຫົວພັນ" <?= $monk['birth_province'] === 'ຫົວພັນ' ? 'selected' : '' ?>>ຫົວພັນ</option>
                                <option value="ໄຊຍະບູລີ" <?= $monk['birth_province'] === 'ໄຊຍະບູລີ' ? 'selected' : '' ?>>ໄຊຍະບູລີ</option>
                                <option value="ຊຽງຂວາງ" <?= $monk['birth_province'] === 'ຊຽງຂວາງ' ? 'selected' : '' ?>>ຊຽງຂວາງ</option>
                                <option value="ວຽງຈັນ" <?= $monk['birth_province'] === 'ວຽງຈັນ' ? 'selected' : '' ?>>ວຽງຈັນ</option>
                                <option value="ບໍລິຄໍາໄຊ" <?= $monk['birth_province'] === 'ບໍລິຄໍາໄຊ' ? 'selected' : '' ?>>ບໍລິຄໍາໄຊ</option>
                                <option value="ຄໍາມ່ວນ" <?= $monk['birth_province'] === 'ຄໍາມ່ວນ' ? 'selected' : '' ?>>ຄໍາມ່ວນ</option>
                                <option value="ສະຫວັນນະເຂດ" <?= $monk['birth_province'] === 'ສະຫວັນນະເຂດ' ? 'selected' : '' ?>>ສະຫວັນນະເຂດ</option>
                                <option value="ສາລະວັນ" <?= $monk['birth_province'] === 'ສາລະວັນ' ? 'selected' : '' ?>>ສາລະວັນ</option>
                                <option value="ເຊກອງ" <?= $monk['birth_province'] === 'ເຊກອງ' ? 'selected' : '' ?>>ເຊກອງ</option>
                                <option value="ຈໍາປາສັກ" <?= $monk['birth_province'] === 'ຈໍາປາສັກ' ? 'selected' : '' ?>>ຈໍາປາສັກ</option>
                                <option value="ອັດຕະປື" <?= $monk['birth_province'] === 'ອັດຕະປື' ? 'selected' : '' ?>>ອັດຕະປື</option>
                                <option value="ໄຊສົມບູນ" <?= $monk['birth_province'] === 'ໄຊສົມບູນ' ? 'selected' : '' ?>>ໄຊສົມບູນ</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="education" class="block text-sm font-medium text-gray-700 mb-1">ການສຶກສາສາມັນ</label>
                                <input type="text" name="education" id="education" class="form-input w-full" value="<?= htmlspecialchars($monk['education'] ?? '') ?>">
                            </div>
                            
                            <div>
                                <label for="dharma_education" class="block text-sm font-medium text-gray-700 mb-1">ການສຶກສາທາງທຳ</label>
                                <input type="text" name="dharma_education" id="dharma_education" class="form-input w-full" value="<?= htmlspecialchars($monk['dharma_education'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທຕິດຕໍ່</label>
                            <input type="text" name="contact_number" id="contact_number" class="form-input w-full" value="<?= htmlspecialchars($monk['contact_number'] ?? '') ?>">
                        </div>
                        
                        <!-- Photo Upload Section -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຮູບພາບພະສົງ</label>
                            
                            <!-- Current Photo Display -->
                            <div class="flex items-center space-x-4 mb-4">
                                <div class="flex-shrink-0" id="currentPhotoContainer">
                                    <?php if (!empty($monk['photo']) && file_exists('../' . $monk['photo'])): ?>
                                        <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" 
                                             class="w-20 h-20 object-cover rounded-lg border-2 border-amber-200" id="currentPhoto">
                                    <?php else: ?>
                                        <div class="w-20 h-20 rounded-lg bg-gray-200 flex items-center justify-center border-2 border-gray-300" id="currentPhoto">
                                            <i class="fas fa-user text-gray-400 text-2xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <p class="font-medium">ຮູບພາບປັດຈຸບັນ</p>
                                    <p>ເລືອກໄຟລ໌ໃໝ່ເພື່ອປ່ຽນຮູບພາບ</p>
                                </div>
                            </div>
                            
                            <!-- File Input -->
                            <div class="mt-4">
                                <input type="file" name="photo" id="photo" accept="image/*" class="form-input w-full" onchange="previewImage(this)">
                                <p class="text-xs text-gray-500 mt-1">
                                    ສະໜັບສະໜູນ: JPG, JPEG, PNG, GIF | ຂະໜາດສູງສຸດ: 5MB
                                </p>
                            </div>
                            
                            <!-- Preview New Image -->
                            <div id="imagePreview" class="mt-4 hidden">
                                <p class="text-sm font-medium text-gray-700 mb-2">ຕົວຢ່າງຮູບໃໝ່:</p>
                                <div class="flex items-center space-x-4">
                                    <img id="previewImg" src="" alt="Preview" class="w-20 h-20 object-cover rounded-lg border-2 border-green-200">
                                    <button type="button" onclick="clearImagePreview()" class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-times mr-1"></i> ຍົກເລີກ
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                    <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition flex items-center">
                        <i class="fas fa-save mr-2"></i> ບັນທຶກການແກ້ໄຂ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ຟັງຊັນສຳລັບ preview ຮູບພາບ
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // ເຊັກປະເພດໄຟລ໌
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('ກະລຸນາເລືອກໄຟລ໌ຮູບພາບທີ່ຖືກຕ້ອງ (JPG, JPEG, PNG, GIF)');
            input.value = '';
            return;
        }
        
        // ເຊັກຂະໜາດໄຟລ໌ (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 5MB');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
}

// ຟັງຊັນສຳລັບລົບ preview
function clearImagePreview() {
    const preview = document.getElementById('imagePreview');
    const input = document.getElementById('photo');
    const previewImg = document.getElementById('previewImg');
    
    preview.classList.add('hidden');
    input.value = '';
    previewImg.src = '';
}

// Form validation
document.getElementById('editMonkForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const pansa = document.getElementById('pansa').value.trim();
    const templeId = document.getElementById('temple_id').value;
    
    if (!name) {
        e.preventDefault();
        alert('ກະລຸນາປ້ອນຊື່ພະສົງ');
        document.getElementById('name').focus();
        return;
    }
    
    if (!pansa || isNaN(pansa) || parseInt(pansa) < 0) {
        e.preventDefault();
        alert('ກະລຸນາປ້ອນພັນສາທີ່ຖືກຕ້ອງ');
        document.getElementById('pansa').focus();
        return;
    }
    
    if (!templeId) {
        e.preventDefault();
        alert('ກະລຸນາເລືອກວັດ');
        document.getElementById('temple_id').focus();
        return;
    }
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>