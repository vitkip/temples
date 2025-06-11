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

// Check if user has permission to add monks
if (!in_array($_SESSION['user']['role'], ['superadmin', 'admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເພີ່ມພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// Get temples for dropdown
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} else {
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// Initialize variables
$errors = [];
$form_data = [
    'prefix' => '',  // เพิ่มฟิลด์ prefix
    'name' => '',
    'lay_name' => '',
    'pansa' => '',
    'birth_date' => '',
    'ordination_date' => '',
    'education' => '',
    'contact_number' => '',
    'temple_id' => $_SESSION['user']['role'] === 'admin' ? $_SESSION['user']['temple_id'] : '',
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
        'prefix' => trim($_POST['prefix'] ?? ''),  // เพิ่มรับค่า prefix
        'name' => trim($_POST['name'] ?? ''),
        'lay_name' => trim($_POST['lay_name'] ?? ''),
        'pansa' => trim($_POST['pansa'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
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
    
    // If validation passes
    if (empty($errors)) {
        try {
            // Handle photo upload if provided
            $photo_path = 'uploads/monks/default.png'; // Default photo path
            
            if (!empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/monks/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "ກະລຸນາອັບໂຫລດຮູບພາບໃນຮູບແບບ JPG, JPEG, ຫຼື PNG";
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
                        prefix,      
                        name, 
                        lay_name, 
                        pansa, 
                        birth_date, 
                        ordination_date, 
                        education, 
                        contact_number,
                        temple_id, 
                        status,
                        position,
                        dharma_education,
                        photo,
                        created_at,
                        updated_at
                    ) VALUES (
                        :prefix,    
                        :name,
                        :lay_name,
                        :pansa,
                        :birth_date,
                        :ordination_date,
                        :education,
                        :contact_number,
                        :temple_id,
                        :status,
                        :position,
                        :dharma_education,
                        :photo,
                        NOW(),
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    ':prefix' => $form_data['prefix'] ?: null,  // ใช้ค่า prefix ถ้ามี ไม่งั้นใช้ null
                    ':name' => $form_data['name'],
                    ':lay_name' => $form_data['lay_name'],
                    ':pansa' => $form_data['pansa'],
                    ':birth_date' => !empty($form_data['birth_date']) ? $form_data['birth_date'] : null,
                    ':ordination_date' => !empty($form_data['ordination_date']) ? $form_data['ordination_date'] : null,
                    ':education' => $form_data['education'],
                    ':contact_number' => $form_data['contact_number'],
                    ':temple_id' => $form_data['temple_id'],
                    ':status' => $form_data['status'],
                    ':position' => $form_data['position'],
                    ':dharma_education' => $form_data['dharma_education'],
                    ':photo' => $photo_path
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

<!-- Page Header -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ເພີ່ມພະສົງໃໝ່</h1>
            <p class="text-sm text-gray-600">ຟອມເພີ່ມຂໍ້ມູນພະສົງໃໝ່</p>
        </div>
        <div>
            <a href="<?= $base_url ?>monks/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
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
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>monks/add.php" method="post" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນພື້ນຖານ</h2>
                <div class="mb-4">
                    <label for="prefix" class="block text-sm font-medium text-gray-700 mb-2">ຄຳນຳໜ້າ</label>
                    <select name="prefix" id="prefix" class="form-select rounded-md w-full">
                        <option value="">-- ເລືອກຄຳນຳໜ້າ --</option>
                        <option value="ພຣະ" <?= $form_data['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                        <option value="ຄຸນແມ່ຂາວ" <?= $form_data['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                        <option value="ສ.ນ" <?= $form_data['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                        <option value="ສັງກະລີ" <?= $form_data['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                    </select>
                </div>
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">ຊື່ <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['name']) ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="lay_name" class="block text-sm font-medium text-gray-700 mb-2">ນາມສະກຸນ</label>
                        <input type="text" name="lay_name" id="lay_name" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['lay_name']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="pansa" class="block text-sm font-medium text-gray-700 mb-2">ພັນສາ <span class="text-red-600">*</span></label>
                        <input type="number" name="pansa" id="pansa" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['pansa']) ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="position" class="block text-sm font-medium text-gray-700 mb-2">ຕຳແໜ່ງ</label>
                        <input type="text" name="position" id="position" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['position']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-2">ວັດ <span class="text-red-600">*</span></label>
                        <select name="temple_id" id="temple_id" class="form-select rounded-md w-full" required>
                            <option value="">ເລືອກວັດ</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $form_data['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">ສະຖານະ</label>
                        <select name="status" id="status" class="form-select rounded-md w-full">
                            <option value="active" <?= $form_data['status'] === 'active' ? 'selected' : '' ?>>ບວດຢູ່</option>
                            <option value="inactive" <?= $form_data['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                        </select>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນເພີ່ມເຕີມ</h2>
                    
                    <div class="mb-4">
                        <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນເດືອນປີເກີດ</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['birth_date']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="ordination_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນບວດ</label>
                        <input type="date" name="ordination_date" id="ordination_date" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['ordination_date']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="education" class="block text-sm font-medium text-gray-700 mb-2">ການສຶກສາສາມັນ</label>
                        <input type="text" name="education" id="education" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['education']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="dharma_education" class="block text-sm font-medium text-gray-700 mb-2">ການສຶກສາທາງທຳ</label>
                        <input type="text" name="dharma_education" id="dharma_education" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['dharma_education']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">ເບີໂທຕິດຕໍ່</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['contact_number']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">ຮູບພາບພະສົງ</label>
                        <input type="file" name="photo" id="photo" class="form-input rounded-md w-full">
                        <p class="mt-1 text-xs text-gray-500">ຮອງຮັບໄຟລ໌ JPG, JPEG, PNG (ສູງສຸດ 2MB)</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                <a href="<?= $base_url ?>monks/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>