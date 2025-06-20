<?php
// filepath: c:\xampp\htdocs\temples\monks\add.php
ob_start();

$page_title = 'ເພີ່ມພະສົງໃໝ່';
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

// Check if user has permission to add monks
if (!in_array($user_role, ['superadmin', 'admin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເພີ່ມພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// Get temples for dropdown based on user role
$temples = [];
if ($user_role === 'superadmin') {
    // superadmin เห็นวัดทั้งหมด
    $temple_stmt = $pdo->query("
        SELECT t.id, t.name, p.province_name 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.status = 'active' 
        ORDER BY p.province_name, t.name
    ");
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'admin') {
    // admin เห็นเฉพาะวัดของตัวเอง
    $temple_stmt = $pdo->prepare("
        SELECT t.id, t.name, p.province_name 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.id = ? AND t.status = 'active'
    ");
    $temple_stmt->execute([$user_temple_id]);
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    // province_admin เห็นวัดในแขวงที่รับผิดชอบ
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

// Initialize variables
$errors = [];
$form_data = [
    'prefix' => '',
    'name' => '',
    'lay_name' => '',
    'pansa' => '',
    'birth_date' => '',
    'birth_province' => '',
    'ordination_date' => '',
    'education' => '',
    'contact_number' => '',
    'temple_id' => $user_role === 'admin' ? $user_temple_id : '',
    'position' => '',
    'dharma_education' => '',
    'status' => 'active'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'monks/add.php');
        exit;
    }
    
    // Validate input
    $form_data = [
        'prefix' => trim($_POST['prefix'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'lay_name' => trim($_POST['lay_name'] ?? ''),
        'pansa' => trim($_POST['pansa'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'birth_province' => trim($_POST['birth_province'] ?? ''),
        'ordination_date' => trim($_POST['ordination_date'] ?? ''),
        'education' => trim($_POST['education'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0,
        'position' => trim($_POST['position'] ?? ''),
        'dharma_education' => trim($_POST['dharma_education'] ?? ''),
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // Validation rules
    if (empty($form_data['name'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ພະສົງ";
    }
    
    if (empty($form_data['pansa']) || !is_numeric($form_data['pansa'])) {
        $errors[] = "ກະລຸນາປ້ອນພັນສາເປັນຕົວເລກ";
    }
    
    if (empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ตรวจสอบสิทธิ์ในการเพิ่มพระในวัดนี้
    if ($user_role === 'admin' && $form_data['temple_id'] != $user_temple_id) {
        $errors[] = "ທ່ານບໍ່ມີສິດເພີ່ມພະສົງໃນວັດອື່ນ";
    } elseif ($user_role === 'province_admin') {
        // ตรวจสอบว่าวัดที่เลือกอยู่ในแขวงที่รับผิดชอบหรือไม่
        $check_temple = $pdo->prepare("
            SELECT COUNT(*) FROM temples t
            JOIN user_province_access upa ON t.province_id = upa.province_id
            WHERE t.id = ? AND upa.user_id = ?
        ");
        $check_temple->execute([$form_data['temple_id'], $user_id]);
        if ($check_temple->fetchColumn() == 0) {
            $errors[] = "ທ່ານບໍ່ມີສິດເພີ່ມພະສົງໃນວັດນີ້";
        }
    }
    
    // If validation passes
    if (empty($errors)) {
        try {
            // Handle photo upload if provided
            $photo_path = 'uploads/monks/default.png';
            
            if (!empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/monks/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "ກະລຸນາອັບໂຫລດຮູບພາບໃນຮູບແບບ JPG, JPEG, ຫຼື PNG";
                } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) { // 2MB limit
                    $errors[] = "ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 2MB";
                } else {
                    $new_filename = 'monk_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        $photo_path = 'uploads/monks/' . $new_filename;
                    } else {
                        $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການອັບໂຫລດຮູບພາບ";
                    }
                }
            }
            
            if (empty($errors)) {
                // Insert monk data
                $stmt = $pdo->prepare("
                    INSERT INTO monks (
                        prefix, name, lay_name, pansa, birth_date, birth_province, 
                        ordination_date, education, contact_number, temple_id, status,
                        position, dharma_education, photo, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                    )
                ");
                
                $stmt->execute([
                    $form_data['prefix'] ?: null,
                    $form_data['name'],
                    $form_data['lay_name'] ?: null,
                    $form_data['pansa'],
                    !empty($form_data['birth_date']) ? $form_data['birth_date'] : null,
                    !empty($form_data['birth_province']) ? $form_data['birth_province'] : null,
                    !empty($form_data['ordination_date']) ? $form_data['ordination_date'] : null,
                    $form_data['education'] ?: null,
                    $form_data['contact_number'] ?: null,
                    $form_data['temple_id'],
                    $form_data['status'],
                    $form_data['position'] ?: null,
                    $form_data['dharma_education'] ?: null,
                    $photo_path
                ]);
                
                $monk_id = $pdo->lastInsertId();
                
                $_SESSION['success'] = "ເພີ່ມຂໍ້ມູນພະສົງສໍາເລັດແລ້ວ";
                header('Location: ' . $base_url . 'monks/view.php?id=' . $monk_id);
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $e->getMessage();
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
                        <i class="fas fa-user-plus"></i>
                    </div>
                    ເພີ່ມພະສົງໃໝ່
                </h1>
                <p class="text-sm text-amber-700 mt-1">ຟອມເພີ່ມຂໍ້ມູນພະສົງໃໝ່</p>
                <?php if ($user_role === 'province_admin'): ?>
                    <p class="text-xs text-amber-600 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        ທ່ານສາມາດເພີ່ມພະສົງໃນວັດທີ່ຢູ່ໃນແຂວງທີ່ຮັບຜິດຊອບເທົ່ານັ້ນ
                    </p>
                <?php elseif ($user_role === 'admin'): ?>
                    <p class="text-xs text-amber-600 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        ທ່ານສາມາດເພີ່ມພະສົງໃນວັດຂອງທ່ານເທົ່ານັ້ນ
                    </p>
                <?php endif; ?>
            </div>
            <div>
                <a href="<?= $base_url ?>monks/" class="btn flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                    <i class="fas fa-arrow-left"></i> ກັບຄືນ
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
        
        <!-- Create Form -->
        <div class="card bg-white p-6">
            <form action="<?= $base_url ?>monks/add.php" method="post" enctype="multipart/form-data">
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
                                <option value="ພຣະ" <?= $form_data['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                                <option value="ຄຸນແມ່ຂາວ" <?= $form_data['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                                <option value="ສ.ນ" <?= $form_data['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                                <option value="ສັງກະລີ" <?= $form_data['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">ຊື່ <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" class="form-input w-full" value="<?= htmlspecialchars($form_data['name']) ?>" required>
                        </div>
                        
                        <div>
                            <label for="lay_name" class="block text-sm font-medium text-gray-700 mb-1">ນາມສະກຸນ</label>
                            <input type="text" name="lay_name" id="lay_name" class="form-input w-full" value="<?= htmlspecialchars($form_data['lay_name']) ?>">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="pansa" class="block text-sm font-medium text-gray-700 mb-1">ພັນສາ <span class="text-red-500">*</span></label>
                                <input type="number" name="pansa" id="pansa" class="form-input w-full" value="<?= htmlspecialchars($form_data['pansa']) ?>" required>
                            </div>
                            
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700 mb-1">ຕຳແໜ່ງ</label>
                                <input type="text" name="position" id="position" class="form-input w-full" value="<?= htmlspecialchars($form_data['position']) ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ <span class="text-red-500">*</span></label>
                            <select name="temple_id" id="temple_id" class="form-select w-full" required <?= $user_role === 'admin' ? 'disabled' : '' ?>>
                                <option value="">ເລືອກວັດ</option>
                                <?php foreach ($temples as $temple): ?>
                                <option value="<?= $temple['id'] ?>" <?= $form_data['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($temple['name']) ?>
                                    <?php if (!empty($temple['province_name'])): ?>
                                        (<?= htmlspecialchars($temple['province_name']) ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($user_role === 'admin'): ?>
                                <input type="hidden" name="temple_id" value="<?= $user_temple_id ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                            <select name="status" id="status" class="form-select w-full">
                                <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>ບວດຢູ່</option>
                                <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
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
                                <input type="date" name="birth_date" id="birth_date" class="form-input w-full" value="<?= htmlspecialchars($form_data['birth_date']) ?>">
                            </div>
                            
                            <div>
                                <label for="ordination_date" class="block text-sm font-medium text-gray-700 mb-1">ວັນບວດ</label>
                                <input type="date" name="ordination_date" id="ordination_date" class="form-input w-full" value="<?= htmlspecialchars($form_data['ordination_date']) ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="birth_province" class="block text-sm font-medium text-gray-700 mb-1">ແຂວງເກີດ</label>
                            <select name="birth_province" id="birth_province" class="form-select w-full">
                                <option value="">-- ເລືອກແຂວງ --</option>
                                <option value="ນະຄອນຫຼວງວຽງຈັນ" <?= $form_data['birth_province'] === 'ນະຄອນຫຼວງວຽງຈັນ' ? 'selected' : '' ?>>ນະຄອນຫຼວງວຽງຈັນ</option>
                                <option value="ຜົ້ງສາລີ" <?= $form_data['birth_province'] === 'ຜົ້ງສາລີ' ? 'selected' : '' ?>>ຜົ້ງສາລີ</option>
                                <option value="ຫຼວງນໍ້າທາ" <?= $form_data['birth_province'] === 'ຫຼວງນໍ້າທາ' ? 'selected' : '' ?>>ຫຼວງນໍ້າທາ</option>
                                <option value="ອຸດົມໄຊ" <?= $form_data['birth_province'] === 'ອຸດົມໄຊ' ? 'selected' : '' ?>>ອຸດົມໄຊ</option>
                                <option value="ບໍ່ແກ້ວ" <?= $form_data['birth_province'] === 'ບໍ່ແກ້ວ' ? 'selected' : '' ?>>ບໍ່ແກ້ວ</option>
                                <option value="ຫຼວງພະບາງ" <?= $form_data['birth_province'] === 'ຫຼວງພະບາງ' ? 'selected' : '' ?>>ຫຼວງພະບາງ</option>
                                <option value="ຫົວພັນ" <?= $form_data['birth_province'] === 'ຫົວພັນ' ? 'selected' : '' ?>>ຫົວພັນ</option>
                                <option value="ໄຊຍະບູລີ" <?= $form_data['birth_province'] === 'ໄຊຍະບູລີ' ? 'selected' : '' ?>>ໄຊຍະບູລີ</option>
                                <option value="ຊຽງຂວາງ" <?= $form_data['birth_province'] === 'ຊຽງຂວາງ' ? 'selected' : '' ?>>ຊຽງຂວາງ</option>
                                <option value="ວຽງຈັນ" <?= $form_data['birth_province'] === 'ວຽງຈັນ' ? 'selected' : '' ?>>ວຽງຈັນ</option>
                                <option value="ບໍລິຄໍາໄຊ" <?= $form_data['birth_province'] === 'ບໍລິຄໍາໄຊ' ? 'selected' : '' ?>>ບໍລິຄໍາໄຊ</option>
                                <option value="ຄໍາມ່ວນ" <?= $form_data['birth_province'] === 'ຄໍາມ່ວນ' ? 'selected' : '' ?>>ຄໍາມ່ວນ</option>
                                <option value="ສະຫວັນນະເຂດ" <?= $form_data['birth_province'] === 'ສະຫວັນນະເຂດ' ? 'selected' : '' ?>>ສະຫວັນນະເຂດ</option>
                                <option value="ສາລະວັນ" <?= $form_data['birth_province'] === 'ສາລະວັນ' ? 'selected' : '' ?>>ສາລະວັນ</option>
                                <option value="ເຊກອງ" <?= $form_data['birth_province'] === 'ເຊກອງ' ? 'selected' : '' ?>>ເຊກອງ</option>
                                <option value="ຈໍາປາສັກ" <?= $form_data['birth_province'] === 'ຈໍາປາສັກ' ? 'selected' : '' ?>>ຈໍາປາສັກ</option>
                                <option value="ອັດຕະປື" <?= $form_data['birth_province'] === 'ອັດຕະປື' ? 'selected' : '' ?>>ອັດຕະປື</option>
                                <option value="ໄຊສົມບູນ" <?= $form_data['birth_province'] === 'ໄຊສົມບູນ' ? 'selected' : '' ?>>ໄຊສົມບູນ</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="education" class="block text-sm font-medium text-gray-700 mb-1">ການສຶກສາສາມັນ</label>
                                <input type="text" name="education" id="education" class="form-input w-full" value="<?= htmlspecialchars($form_data['education']) ?>">
                            </div>
                            
                            <div>
                                <label for="dharma_education" class="block text-sm font-medium text-gray-700 mb-1">ການສຶກສາທາງທຳ</label>
                                <input type="text" name="dharma_education" id="dharma_education" class="form-input w-full" value="<?= htmlspecialchars($form_data['dharma_education']) ?>">
                            </div>
                        </div>
                        
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທຕິດຕໍ່</label>
                            <input type="text" name="contact_number" id="contact_number" class="form-input w-full" value="<?= htmlspecialchars($form_data['contact_number']) ?>">
                        </div>
                        
                        <div>
                            <label for="photo" class="block text-sm font-medium text-gray-700 mb-1">ຮູບພາບພະສົງ</label>
                            <input type="file" name="photo" id="photo" class="form-input w-full" accept="image/*">
                            <p class="text-xs text-gray-500 mt-1">ຮອງຮັບໄຟລ໌ JPG, JPEG, PNG (ສູງສຸດ 2MB)</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                    <a href="<?= $base_url ?>monks/" class="btn px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" class="btn btn-primary px-6 py-2 text-white rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>