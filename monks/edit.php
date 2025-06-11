<?php
// filepath: c:\xampp\htdocs\temples\monks\edit.php
ob_start(); // เพิ่ม output buffering เหมือน add.php

$page_title = 'ແກ້ໄຂຂໍ້ມູນພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນພະສົງ
$stmt = $pdo->prepare("SELECT * FROM monks WHERE id = ?");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ກວດສອບສິດທິໃນການແກ້ໄຂ - ສະເພາະ superadmin ຫຼື admin ຂອງວັດນີ້ເທົ່ານັ້ນ
$can_edit = $_SESSION['user']['role'] === 'superadmin' || 
           ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $monk['temple_id']);

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນພະສົງນີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ດຶງຂໍ້ມູນວັດສໍາລັບ dropdown
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} else {
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບຄວາມຖືກຕ້ອງຂອງ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'monks/edit.php?id=' . $monk_id);
        exit;
    }
    
    // ກວດສອບຂໍ້ມູນທີ່ປ້ອນເຂົ້າມາ
    $prefix = trim($_POST['prefix'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $lay_name = trim($_POST['lay_name'] ?? '');
    $pansa = trim($_POST['pansa'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $ordination_date = trim($_POST['ordination_date'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $temple_id = isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0;
    $status = $_POST['status'] ?? 'active';
    $position = trim($_POST['position'] ?? '');
    $dharma_education = trim($_POST['dharma_education'] ?? '');
    
    // ກົດລະບຽບການກວດສອບຂໍ້ມູນ
    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ພະສົງ";
    }
    
    if (empty($pansa) || !is_numeric($pansa)) {
        $errors[] = "ກະລຸນາປ້ອນພັນສາເປັນຕົວເລກ";
    }
    
    if (empty($temple_id)) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ຖ້າການກວດສອບຜ່ານ
    if (empty($errors)) {
        try {
            // ຈັດການກັບການອັບໂຫລດຮູບພາບ
            $photo_path = $monk['photo']; // ໃຊ້ຮູບພາບເດີມ
            
            if (!empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/monks/';
                
                // ສ້າງໂຟລເດີຖ້າບໍ່ມີ
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
                        
                        // ລຶບຮູບພາບເກົ່າຖ້າມີ ແລະ ບໍ່ແມ່ນຮູບພາບເລີ່ມຕົ້ນ
                        if (!empty($monk['photo']) && file_exists('../' . $monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png') {
                            unlink('../' . $monk['photo']);
                        }
                    } else {
                        $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການອັບໂຫລດຮູບພາບ";
                    }
                }
            }
            
            if (empty($errors)) {
                // ອັບເດດຂໍ້ມູນພະສົງ
                $stmt = $pdo->prepare("
                    UPDATE monks SET 
                    prefix = ?,
                    name = ?, 
                    lay_name = ?, 
                    pansa = ?, 
                    birth_date = ?, 
                    ordination_date = ?, 
                    education = ?, 
                    contact_number = ?,
                    temple_id = ?, 
                    status = ?,
                    position = ?,
                    dharma_education = ?,
                    photo = ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $prefix,
                    $name,
                    $lay_name,
                    $pansa,
                    $birth_date ? $birth_date : null,
                    $ordination_date ? $ordination_date : null,
                    $education,
                    $contact_number,
                    $temple_id,
                    $status,
                    $position,
                    $dharma_education,
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

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂຂໍ້ມູນພະສົງ</h1>
            <p class="text-sm text-gray-600">ຟອມແກ້ໄຂຂໍ້ມູນພະສົງ</p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>monks/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg transition flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition flex items-center">
                <i class="fas fa-eye mr-2"></i> ເບິ່ງລາຍລະອຽດ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <!-- ສະແດງຂໍ້ຜິດພາດ -->
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
    
    <!-- ຟອມແກ້ໄຂຂໍ້ມູນ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>monks/edit.php?id=<?= $monk_id ?>" method="post" enctype="multipart/form-data" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- ຂໍ້ມູນພື້ນຖານ -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນພື້ນຖານ</h2>
                   <div class="mb-4">
                        <label for="prefix" class="block text-sm font-medium text-gray-700 mb-2">ຄຳນຳໜ້າ</label>
                        <select name="prefix" id="prefix" class="form-select rounded-md w-full">
                            <option value="">-- ເລືອກຄຳນຳໜ້າ --</option>
                            <option value="ພຣະ" <?= $monk['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                            <option value="ຄຸນແມ່ຂາວ" <?= $monk['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                            <option value="ສ.ນ" <?= $monk['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                            <option value="ສັງກະລີ" <?= $monk['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">ຊື່ <span class="text-red-600">*</span></label>
                        <input type="text" name="name" id="name" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['name']) ?>" required>
                    </div>
                    
                  <div class="mb-4">
                        <label for="lay_name" class="block text-sm font-medium text-gray-700 mb-2">ນາມສະກຸນ</label>
                        <input type="text" name="lay_name" id="lay_name" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['lay_name'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="pansa" class="block text-sm font-medium text-gray-700 mb-2">ພັນສາ <span class="text-red-600">*</span></label>
                        <input type="number" name="pansa" id="pansa" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['pansa']) ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="position" class="block text-sm font-medium text-gray-700 mb-2">ຕຳແໜ່ງ</label>
                        <input type="text" name="position" id="position" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['position'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-2">ວັດ <span class="text-red-600">*</span></label>
                        <select name="temple_id" id="temple_id" class="form-select rounded-md w-full" required>
                            <option value="">ເລືອກວັດ</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $temple['id'] == $monk['temple_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">ສະຖານະ</label>
                        <select name="status" id="status" class="form-select rounded-md w-full">
                            <option value="active" <?= $monk['status'] === 'active' ? 'selected' : '' ?>>ຍັງບວດຢູ່</option>
                            <option value="inactive" <?= $monk['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                        </select>
                    </div>
                </div>
                
                <!-- ຂໍ້ມູນເພີ່ມເຕີມ -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນເພີ່ມເຕີມ</h2>
                    
                    <div class="mb-4">
                        <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນເດືອນປີເກີດ</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-input rounded-md w-full" value="<?= $monk['birth_date'] ? date('Y-m-d', strtotime($monk['birth_date'])) : '' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="ordination_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນບວດ</label>
                        <input type="date" name="ordination_date" id="ordination_date" class="form-input rounded-md w-full" value="<?= $monk['ordination_date'] ? date('Y-m-d', strtotime($monk['ordination_date'])) : '' ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="education" class="block text-sm font-medium text-gray-700 mb-2">ການສຶກສາສາມັນ</label>
                        <input type="text" name="education" id="education" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['education'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="dharma_education" class="block text-sm font-medium text-gray-700 mb-2">ການສຶກສາທາງທຳ</label>
                        <input type="text" name="dharma_education" id="dharma_education" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['dharma_education'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">ເບີໂທຕິດຕໍ່</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-input rounded-md w-full" value="<?= htmlspecialchars($monk['contact_number'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">ຮູບພາບພະສົງ</label>
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <?php if (!empty($monk['photo'])): ?>
                                <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="w-16 h-16 object-cover rounded-full">
                                <?php else: ?>
                                <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <input type="file" name="photo" id="photo" class="form-input rounded-md w-full">
                                <p class="mt-1 text-xs text-gray-500">ຮອງຮັບໄຟລ໌ JPG, JPEG, PNG (ສູງສຸດ 2MB)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ປຸ່ມດຳເນີນການ -->
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk_id ?>" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
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