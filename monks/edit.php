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
    $id_card = trim($_POST['id_card'] ?? '');
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
    
    $new_status = $_POST['status'] ?? 'active';

    // ถ้า validation ผ่าน
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
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "ກະລຸນາອັບໂຫລດຮູບພາບໃນຮູບແບບ JPG, JPEG, PNG, GIF ຫຼື WebP";
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
                        id_card = ?, prefix = ?, name = ?, lay_name = ?, pansa = ?, 
                        birth_date = ?, birth_province = ?, ordination_date = ?, education = ?, 
                        contact_number = ?, temple_id = ?, status = ?, position = ?, 
                        dharma_education = ?, photo = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $id_card ?: null,
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
                
                // อัปเดตสถานะและ resignation_date
                if ($new_status === 'inactive') {
                    $resignation_date = date('Y-m-d');
                    $stmt = $pdo->prepare("UPDATE monks SET status = ?, resignation_date = ? WHERE id = ?");
                    $stmt->execute([$new_status, $resignation_date, $monk_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE monks SET status = ?, resignation_date = NULL WHERE id = ?");
                    $stmt->execute([$new_status, $monk_id]);
                }

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
<link rel="stylesheet" href="<?= $base_url ?>assets/css/addmonks.css">

<div class="page-container">
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-6xl mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-6">
                <div>
                    <h1 class="monk-title">ແກ້ໄຂຂໍ້ມູນພະສົງ</h1>
                    <p class="text-gray-600">ແກ້ໄຂຂໍ້ມູນສຳລັບ <?= htmlspecialchars($monk['prefix'] ?? '') ?> <?= htmlspecialchars($monk['name'] ?? '') ?></p>
                </div>
                <div class="flex gap-3 mt-4 lg:mt-0">
                    <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> ເບິ່ງລາຍລະອຽດ
                    </a>
                    <a href="<?= $base_url ?>monks/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> ກັບໄປລາຍການ
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <h3 class="text-sm font-medium text-red-800">ພົບຂໍ້ຜິດພາດ <?= count($errors) ?> ລາຍການ</h3>
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <div class="info-card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="card-header-content">
                        <h2 class="card-title">ແກ້ໄຂຂໍ້ມູນພະສົງ</h2>
                        <p class="card-subtitle">ກະລຸນາຕື່ມຂໍ້ມູນທີ່ຕ້ອງການແກ້ໄຂ</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data" id="editMonkForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <!-- ข้อมูลพื้นฐาน -->
                            <div class="lg:col-span-2">
                                <h3 class="section-title">
                                    <i class="fas fa-user"></i>
                                    ຂໍ້ມູນພື້ນຖານ
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label for="prefix" class="form-label">
                                            <i class="fas fa-tag"></i>
                                            ຄຳນຳໜ້າ
                                        </label>
                                        <select name="prefix" id="prefix" class="form-control">
                                            <option value="">-- ເລືອກຄຳນຳໜ້າ --</option>
                                            <option value="ພຣະ" <?= $monk['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                                            <option value="ຄຸນແມ່ຂາວ" <?= $monk['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                                            <option value="ສ.ນ" <?= $monk['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                                            <option value="ສັງກະລີ" <?= $monk['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="id_card" class="form-label">
                                            <i class="fas fa-id-card"></i>
                                            ເລກບັດປະຊາຊົນ
                                        </label>
                                        <input type="text" name="id_card" id="id_card" 
                                            value="<?= htmlspecialchars($monk['id_card'] ?? '') ?>" 
                                            class="form-control" 
                                            placeholder="1234567890"
                                            pattern="[0-9]{10}"
                                            maxlength="10">
                                        <small class="form-text text-muted">10 ຫຼັກ (ໃສ່ພຽງຕົວເລກ)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="name" class="form-label required">
                                            <i class="fas fa-signature"></i>
                                            ຊື່ພຣະສົງ
                                        </label>
                                        <input type="text" name="name" id="name" 
                                               value="<?= htmlspecialchars($monk['name'] ?? '') ?>" 
                                               class="form-control" required 
                                               placeholder="ຊື່ພຣະສົງໃນລະບົບ">
                                    </div>

                                    <div class="form-group">
                                        <label for="lay_name" class="form-label">
                                            <i class="fas fa-user-circle"></i>
                                            ຊື່ຄົນທົ່ວໄປ
                                        </label>
                                        <input type="text" name="lay_name" id="lay_name" 
                                               value="<?= htmlspecialchars($monk['lay_name'] ?? '') ?>" 
                                               class="form-control" 
                                               placeholder="ຊື່ກ່ອນບວດ">
                                    </div>

                                    <div class="form-group">
                                        <label for="pansa" class="form-label required">
                                            <i class="fas fa-calendar-alt"></i>
                                            ຈຳນວນພັນສາ
                                        </label>
                                        <input type="number" name="pansa" id="pansa" 
                                               value="<?= htmlspecialchars($monk['pansa'] ?? '0') ?>" 
                                               class="form-control" required min="0" max="100"
                                               placeholder="ຈຳນວນພັນສາ">
                                        <small class="form-text text-muted">ລະບົບຈະຄິດໄລ່ອັດຕະໂນມັດຈາກວັນບວດ</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="birth_date" class="form-label">
                                            <i class="fas fa-birthday-cake"></i>
                                            ວັນເກີດ
                                        </label>
                                        <input type="date" name="birth_date" id="birth_date" 
                                               value="<?= $monk['birth_date'] ? date('Y-m-d', strtotime($monk['birth_date'])) : '' ?>" 
                                               class="form-control"
                                               max="<?= date('Y-m-d') ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="birth_province" class="form-label">
                                            <i class="fas fa-map-marker-alt"></i>
                                            ແຂວງເກີດ
                                        </label>
                                        <select name="birth_province" id="birth_province" class="form-control">
                                            <option value="">-- ເລືອກແຂວງ --</option>
                                             <option value="ນະຄອນຫຼວງວຽງຈັນ" <?= $monk['birth_province'] === 'ນະຄອນຫຼວງວຽງຈັນ' ? 'selected' : '' ?>>ນະຄອນຫຼວງວຽງຈັນ</option>
                                            <option value="ວຽງຈັນ" <?= $monk['birth_province'] === 'ວຽງຈັນ' ? 'selected' : '' ?>>ວຽງຈັນ</option>
                                            <option value="ຫຼວງພະບາງ" <?= $monk['birth_province'] === 'ຫຼວງພະບາງ' ? 'selected' : '' ?>>ຫຼວງພະບາງ</option>
                                            <option value="ສະຫວັນນະເຂດ" <?= $monk['birth_province'] === 'ສະຫວັນນະເຂດ' ? 'selected' : '' ?>>ສະຫວັນນະເຂດ</option>
                                            <option value="ຈໍາປາສັກ" <?= $monk['birth_province'] === 'ຈໍາປາສັກ' ? 'selected' : '' ?>>ຈໍາປາສັກ</option>
                                            <option value="ອຸດົມໄຊ" <?= $monk['birth_province'] === 'ອຸດົມໄຊ' ? 'selected' : '' ?>>ອຸດົມໄຊ</option>
                                            <option value="ບໍແກ້ວ" <?= $monk['birth_province'] === 'ບໍແກ້ວ' ? 'selected' : '' ?>>ບໍແກ້ວ</option>
                                            <option value="ສາລະວັນ" <?= $monk['birth_province'] === 'ສາລະວັນ' ? 'selected' : '' ?>>ສາລະວັນ</option>
                                            <option value="ເຊກອງ" <?= $monk['birth_province'] === 'ເຊກອງ' ? 'selected' : '' ?>>ເຊກອງ</option>
                                            <option value="ອັດຕະປື" <?= $monk['birth_province'] === 'ອັດຕະປື' ? 'selected' : '' ?>>ອັດຕະປື</option>
                                            <option value="ຜົ້ງສາລີ" <?= $monk['birth_province'] === 'ຜົ້ງສາລີ' ? 'selected' : '' ?>>ຜົ້ງສາລີ</option>
                                            <option value="ຫົວພັນ" <?= $monk['birth_province'] === 'ຫົວພັນ' ? 'selected' : '' ?>>ຫົວພັນ</option>
                                            <option value="ຄໍາມ່ວນ" <?= $monk['birth_province'] === 'ຄໍາມ່ວນ' ? 'selected' : '' ?>>ຄໍາມ່ວນ</option>
                                            <option value="ບໍລິຄໍາໄຊ" <?= $monk['birth_province'] === 'ບໍລິຄໍາໄຊ' ? 'selected' : '' ?>>ບໍລິຄໍາໄຊ</option>
                                            <option value="ຫຼວງນ້ຳທາ" <?= $monk['birth_province'] === 'ຫຼວງນ້ຳທາ' ? 'selected' : '' ?>>ຫຼວງນ້ຳທາ</option>
                                            <option value="ໄຊຍະບູລີ" <?= $monk['birth_province'] === 'ໄຊຍະບູລີ' ? 'selected' : '' ?>>ໄຊຍະບູລີ</option>
                                            <option value="ໄຊສົມບູນ" <?= $monk['birth_province'] === 'ໄຊສົມບູນ' ? 'selected' : '' ?>>ໄຊສົມບູນ</option>
                                            <option value="ຊຽງຂວາງ" <?= $monk['birth_province'] === 'ຊຽງຂວາງ' ? 'selected' : '' ?>>ຊຽງຂວາງ</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="ordination_date" class="form-label">
                                            <i class="fas fa-pray"></i>
                                            ວັນບວດ
                                        </label>
                                        <input type="date" name="ordination_date" id="ordination_date" 
                                               value="<?= $monk['ordination_date'] ? date('Y-m-d', strtotime($monk['ordination_date'])) : '' ?>" 
                                               class="form-control"
                                               max="<?= date('Y-m-d') ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="temple_id" class="form-label required">
                                            <i class="fas fa-place-of-worship"></i>
                                            ວັດ
                                        </label>
                                        <select name="temple_id" id="temple_id" class="form-control" required <?= $user_role === 'admin' ? 'disabled' : '' ?>>
                                            <option value="">-- ເລືອກວັດ --</option>
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

                                    <div class="form-group">
                                        <label for="position" class="form-label">
                                            <i class="fas fa-user-tie"></i>
                                            ຕຳແໜ່ງໃນວັດ
                                        </label>
                                        <input type="text" name="position" id="position" 
                                               value="<?= htmlspecialchars($monk['position'] ?? '') ?>" 
                                               class="form-control" 
                                               placeholder="ຕຳແໜ່ງ ຫຼື ໜ້າທີ່ຮັບຜິດຊອບ"
                                               list="position-suggestions">
                                        <datalist id="position-suggestions">
                                            <option value="ເຈົ້າອາວາດ">
                                            <option value="ຮອງເຈົ້າອາວາດ">
                                            <option value="ພະຄູ">
                                            <option value="ຄູສອນ">
                                            <option value="ພະສົງທົ່ວໄປ">
                                        </datalist>
                                    </div>
                                    <div class="form-group">
                                        <label for="contact_number" class="form-label">
                                            <i class="fas fa-phone"></i>
                                            ເບີໂທຕິດຕໍ່
                                        </label>
                                        <input type="tel" name="contact_number" id="contact_number" 
                                               value="<?= htmlspecialchars($monk['contact_number'] ?? '') ?>" 
                                               class="form-control" placeholder="020 12345678"
                                               pattern="[0-9\s\-\+\(\)]+">
                                    </div>
                                    <div class="form-group">
                                        <label for="status" class="form-label">
                                            <i class="fas fa-info-circle"></i>
                                            ສະຖານະ
                                        </label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="active" <?= $monk['status'] === 'active' ? 'selected' : '' ?>>
                                                <i class="fas fa-check-circle"></i> ຍັງບວດຢູ່
                                            </option>
                                            <option value="inactive" <?= $monk['status'] === 'inactive' ? 'selected' : '' ?>>
                                                <i class="fas fa-times-circle"></i> ສິກແລ້ວ
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <h3 class="section-title">
                                    <i class="fas fa-address-book"></i>
                                    ຂໍ້ມູນຕິດຕໍ່ແລະການສຶກສາ
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    

                                    <div class="form-group">
                                        <label for="education" class="form-label">
                                            <i class="fas fa-graduation-cap"></i>
                                            ການສຶກສາທົ່ວໄປ
                                        </label>
                                        <input type="text" name="education" id="education" 
                                               value="<?= htmlspecialchars($monk['education'] ?? '') ?>" 
                                               class="form-control" 
                                               placeholder="ປະຖົມ, ມັດທະຍົມ, ອານຸປະລິນຍາ, ປະລິນຍາຕີ..."
                                               list="education-suggestions">
                                        <datalist id="education-suggestions">
                                            <option value="ປະຖົມ">
                                            <option value="ມັດທະຍົມຕອນຕົ້ນ">
                                            <option value="ມັດທະຍົມຕອນປາຍ">
                                            <option value="ອານຸປະລິນຍາ">
                                            <option value="ປະລິນຍາຕີ">
                                            <option value="ປະລິນຍາໂທ">
                                            <option value="ປະລິນຍາເອກ">
                                        </datalist>
                                    </div>

                                    <div class="form-group">
                                        <label for="dharma_education" class="form-label">
                                            <i class="fas fa-book"></i>
                                            ການສຶກສາທາງທຳມະ
                                        </label>
                                        <input type="text" name="dharma_education" id="dharma_education" 
                                               value="<?= htmlspecialchars($monk['dharma_education'] ?? '') ?>" 
                                               class="form-control" 
                                               placeholder="ນັກທັມຕີ, ນັກທັມໂທ, ນັກທັມເອກ..."
                                               list="dharma-suggestions">
                                        <datalist id="dharma-suggestions">
                                            <option value="ນັກທັມຕີ">
                                            <option value="ນັກທັມໂທ">
                                            <option value="ນັກທັມເອກ">
                                            
                                        </datalist>
                                    </div>
                                </div>
                            </div>

                            <!-- อัปโหลดรูปภาพ -->
                            <div class="lg:col-span-1">
                                <h3 class="section-title">
                                    <i class="fas fa-camera"></i>
                                    ຮູບພາບ
                                </h3>
                                
                                <div class="photo-upload-container">
                                    <div class="photo-preview <?= !empty($monk['photo']) ? 'has-image' : '' ?>" id="photoPreview" role="button" tabindex="0">
                                        <?php if (!empty($monk['photo']) && file_exists('../' . $monk['photo'])): ?>
                                            <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="preview-image" id="currentPhoto">
                                        <?php else: ?>
                                            <div class="photo-placeholder">
                                                <i class="fas fa-camera"></i>
                                                <p>ກົດເພື່ອເລືອກຮູບ</p>
                                                <span class="text-sm text-gray-500">JPG, PNG, GIF ຫຼື WebP</span>
                                                <span class="text-xs text-gray-400">ຂະໜາດສູງສຸດ 5MB</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" name="photo" id="photo" accept="image/*" class="photo-input" aria-label="ເລືອກຮູບພາບ">
                                    <button type="button" class="btn btn-secondary btn-sm mt-3" id="removePhoto" style="<?= !empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png' ? '' : 'display: none;' ?>">
                                        <i class="fas fa-trash"></i> ລຶບຮູບ
                                    </button>
                                </div>

                                <!-- คำแนะนำ -->
                                <div class="info-box">
                                    <h4>
                                        <i class="fas fa-info-circle"></i> 
                                        ຄຳແນະນຳ
                                    </h4>
                                    <ul class="space-y-2">
                                        <li><i class="fas fa-star text-red-500"></i> ຟິວທີ່ມີເຄື່ອງໝາຍ * ແມ່ນຈຳເປັນ</li>
                                        <li><i class="fas fa-calculator text-blue-500"></i> ຈຳນວນພັນສາຈະຄິດໄລ່ອັດຕະໂນມັດ</li>
                                        <li><i class="fas fa-image text-green-500"></i> ຮູບພາບຂະໜາດບໍ່ເກີນ 5MB</li>
                                        <li><i class="fas fa-shield-alt text-purple-500"></i> ເລກບັດປະຊາຊົນຕ້ອງບໍ່ຊ້ຳກັນ</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="btn btn-secondary flex-1 sm:flex-none order-2 sm:order-1">
                                    <i class="fas fa-times"></i> ຍົກເລີກ
                                </a>
                                <button type="submit" class="btn btn-primary flex-1 order-1 sm:order-2" id="submitBtn">
                                    <i class="fas fa-save"></i> ບັນທຶກການແກ້ໄຂ
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ຟັງຊັນສຳລັບ preview ຮູບພາບ
function previewImage(input) {
    const preview = document.getElementById('photoPreview');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // ເຊັກປະເພດໄຟລ໌
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('ກະລຸນາເລືອກໄຟລ໌ຮູບພາບທີ່ຖືກຕ້ອງ (JPG, JPEG, PNG, GIF, WebP)');
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
            // ລຶບ placeholder ຖ້າມີ
            const placeholder = preview.querySelector('.photo-placeholder');
            if (placeholder) {
                preview.removeChild(placeholder);
            }
            
            // ຕັດ image ກ່ອນຫນ້າ ຖ້າມີ
            const oldImage = preview.querySelector('img');
            if (oldImage) {
                preview.removeChild(oldImage);
            }
            
            // ສ້າງ image ໃຫມ່
            const image = document.createElement('img');
            image.src = e.target.result;
            image.className = 'preview-image';
            image.alt = 'Preview';
            preview.appendChild(image);
            
            // ໃສ່ class ເພື່ອສະແດງວ່າມີຮູບ
            preview.classList.add('has-image');
            
            // ສະແດງປຸ່ມລຶບ
            document.getElementById('removePhoto').style.display = '';
        };
        reader.readAsDataURL(file);
    }
}

// ຟັງຊັນສຳລັບລຶບຮູບພາບປະຈຸບັນ ແລະ ກັບໄປໃຊ້ຮູບພາບເລີ່ມຕົ້ນ
function removePhoto() {
    const photoInput = document.getElementById('photo');
    const preview = document.getElementById('photoPreview');
    
    photoInput.value = '';
    
    // ລຶບຮູບພາບ
    const image = preview.querySelector('img');
    if (image) {
        preview.removeChild(image);
    }
    
    // ສ້າງ placeholder ຖ້າຍັງບໍ່ມີ
    if (!preview.querySelector('.photo-placeholder')) {
        const placeholder = document.createElement('div');
        placeholder.className = 'photo-placeholder';
        placeholder.innerHTML = `
            <i class="fas fa-camera"></i>
            <p>ກົດເພື່ອເລືອກຮູບ</p>
            <span class="text-sm text-gray-500">JPG, PNG, GIF ຫຼື WebP</span>
            <span class="text-xs text-gray-400">ຂະໜາດສູງສຸດ 5MB</span>
        `;
        preview.appendChild(placeholder);
    }
    
    // ລຶບ class
    preview.classList.remove('has-image');
    
    // ເຊື່ອງປຸ່ມລຶບ
    document.getElementById('removePhoto').style.display = 'none';
    
    // ແຈ້ງເຕືອນການລຶບສຳເລັດ
    alert('ໄດ້ລຶບຮູບພາບແລ້ວ. ຮູບເລີ່ມຕົ້ນຈະຖືກນຳໃຊ້ຫຼັງຈາກບັນທຶກ.');
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Click on photo preview to trigger file input
    document.getElementById('photoPreview').addEventListener('click', function() {
        document.getElementById('photo').click();
    });
    
    // Preview image when file is selected
    document.getElementById('photo').addEventListener('change', function() {
        previewImage(this);
    });
    
    // Remove photo button
    document.getElementById('removePhoto').addEventListener('click', removePhoto);
    
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
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>