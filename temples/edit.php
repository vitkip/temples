<?php
$page_title = 'ແກ້ໄຂຂໍ້ມູນວັດ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// Check if user has permission
if ($_SESSION['user']['role'] !== 'superadmin' && $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// Check if ID was provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . $base_url . 'temples/');
    exit;
}

$temple_id = (int)$_GET['id'];

// Get temple data
$stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch();

if (!$temple) {
    header('Location: ' . $base_url . 'temples/');
    exit;
}

$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection would go here
    
    try {
        // Extract form data with validation
        $name = trim($_POST['name']);
        $address = trim($_POST['address'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $founding_date = !empty($_POST['founding_date']) ? $_POST['founding_date'] : null;
        $abbot_name = trim($_POST['abbot_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        
        // Basic validation
        if (empty($name)) {
            throw new Exception('ກະລຸນາປ້ອນຊື່ວັດ');
        }
        
        // Process photo upload
        $photo = $temple['photo'];
        if (!empty($_FILES['photo']['name'])) {
            // ตรวจสอบนามสกุลไฟล์
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            
            if (in_array(strtolower($ext), $allowed)) {
                $photo_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_FILES['photo']['name']);
                $photo_tmp = $_FILES['photo']['tmp_name'];
                $photo_path = '../uploads/temples/' . $photo_name;
                
                // Create directory if it doesn't exist
                if (!is_dir('../uploads/temples/')) {
                    mkdir('../uploads/temples/', 0777, true);
                }
                
                if (move_uploaded_file($photo_tmp, $photo_path)) {
                    // Delete old photo if exists and is different from default
                    if (!empty($temple['photo']) && file_exists('../' . $temple['photo'])) {
                        // ป้องกันการลบไฟล์ที่อยู่นอกโฟลเดอร์ที่กำหนด
                        if (strpos($temple['photo'], 'uploads/temples/') === 0) {
                            unlink('../' . $temple['photo']);
                        }
                    }
                    $photo = 'uploads/temples/' . $photo_name;
                }
            } else {
                $error = 'ປະເພດໄຟລ໌ບໍ່ຮອງຮັບ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG ແລະ GIF ເທົ່ານັ້ນ';
            }
        }
        
        // Process logo upload
        $logo = $temple['logo'];
        if (!empty($_FILES['logo']['name'])) {
            // ตรวจสอบนามสกุลไฟล์
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            
            if (in_array(strtolower($ext), $allowed)) {
                $logo_name = time() . '_logo_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $_FILES['logo']['name']);
                $logo_tmp = $_FILES['logo']['tmp_name'];
                $logo_path = '../uploads/temples/' . $logo_name;
                
                if (move_uploaded_file($logo_tmp, $logo_path)) {
                    // Delete old logo if exists
                    if (!empty($temple['logo']) && file_exists('../' . $temple['logo'])) {
                        // ป้องกันการลบไฟล์ที่อยู่นอกโฟลเดอร์ที่กำหนด
                        if (strpos($temple['logo'], 'uploads/temples/') === 0) {
                            unlink('../' . $temple['logo']);
                        }
                    }
                    $logo = 'uploads/temples/' . $logo_name;
                }
            } else {
                $error = 'ປະເພດໄຟລ໌ບໍ່ຮອງຮັບ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG ແລະ GIF ເທົ່ານັ້ນ';
            }
        }
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE temples SET
                name = ?, address = ?, district = ?, province = ?, phone = ?, email = ?, 
                website = ?, founding_date = ?, abbot_name = ?, description = ?, 
                photo = ?, logo = ?, latitude = ?, longitude = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $address, $district, $province, $phone, $email, 
            $website, $founding_date, $abbot_name, $description, 
            $photo, $logo, $latitude, $longitude, $status,
            $temple_id
        ]);
        
        $success = 'ແກ້ໄຂຂໍ້ມູນວັດສຳເລັດແລ້ວ';
        
        // Refresh temple data
        $stmt = $pdo->prepare("SELECT * FROM temples WHERE id = ?");
        $stmt->execute([$temple_id]);
        $temple = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ແກ້ໄຂຂໍ້ມູນວັດ</h1>
        <p class="text-sm text-gray-600">ແກ້ໄຂລາຍລະອຽດຂອງວັດ <?= htmlspecialchars($temple['name']) ?></p>
    </div>
    <a href="<?= $base_url ?>temples/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
        <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
    </a>
</div>

<!-- Alert Messages -->
<?php if ($success): ?>
<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
    <div class="flex items-center">
        <i class="fas fa-check-circle text-green-500 mr-3"></i>
        <p class="text-green-700"><?= $success ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
    <div class="flex items-center">
        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
        <p class="text-red-700"><?= $error ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Temple Form -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <form method="POST" enctype="multipart/form-data" class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900">ຂໍ້ມູນພື້ນຖານ</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ຊື່ວັດ <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        name="name" 
                        required
                        value="<?= htmlspecialchars($temple['name']) ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ທີ່ຢູ່</label>
                    <input 
                        type="text" 
                        name="address"
                        value="<?= htmlspecialchars($temple['address'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ເມືອງ</label>
                        <input 
                            type="text" 
                            name="district"
                            value="<?= htmlspecialchars($temple['district'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ແຂວງ</label>
                        <input 
                            type="text" 
                            name="province"
                            value="<?= htmlspecialchars($temple['province'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທ</label>
                        <input 
                            type="tel" 
                            name="phone"
                            value="<?= htmlspecialchars($temple['phone'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ອີເມວ</label>
                        <input 
                            type="email" 
                            name="email"
                            value="<?= htmlspecialchars($temple['email'] ?? '') ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ເວັບໄຊທ໌</label>
                    <input 
                        type="url" 
                        name="website"
                        value="<?= htmlspecialchars($temple['website'] ?? '') ?>"
                        placeholder="https://"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ຊື່ເຈົ້າອະທິການ</label>
                    <input 
                        type="text" 
                        name="abbot_name"
                        value="<?= htmlspecialchars($temple['abbot_name'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ວັນທີສ້າງຕັ້ງ</label>
                    <input 
                        type="date" 
                        name="founding_date"
                        value="<?= $temple['founding_date'] ?? '' ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900">ຂໍ້ມູນເພີ່ມເຕີມ</h3>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ຄຳອະທິບາຍ</label>
                    <textarea 
                        name="description" 
                        rows="4" 
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    ><?= htmlspecialchars($temple['description'] ?? '') ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນແວງ (Latitude)</label>
                        <input 
                            type="text" 
                            name="latitude"
                            value="<?= $temple['latitude'] ?? '' ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນຂະໜານ (Longitude)</label>
                        <input 
                            type="text" 
                            name="longitude"
                            value="<?= $temple['longitude'] ?? '' ?>"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ຮູບພາບວັດ</label>
                    <?php if ($temple['photo']): ?>
                        <div class="mb-2">
                            <img src="<?= $base_url . $temple['photo'] ?>" alt="Temple photo" class="w-40 h-auto rounded">
                        </div>
                    <?php endif; ?>
                    <input 
                        type="file" 
                        name="photo"
                        accept="image/*"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    <p class="text-xs text-gray-500 mt-1">ແນະນຳ: ຮູບພາບຂະໜາດ 1200x800px, ສູງສຸດ 5MB</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ໂລໂກ້ວັດ</label>
                    <?php if ($temple['logo']): ?>
                        <div class="mb-2">
                            <img src="<?= $base_url . $temple['logo'] ?>" alt="Temple logo" class="w-20 h-auto rounded">
                        </div>
                    <?php endif; ?>
                    <input 
                        type="file" 
                        name="logo"
                        accept="image/*"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                    <p class="text-xs text-gray-500 mt-1">ແນະນຳ: ຮູບພາບທີ່ມີພື້ນຫຼັງໂປ່ງໃສ, ສູງສຸດ 2MB</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                    <select 
                        name="status"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="active" <?= $temple['status'] === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                        <option value="inactive" <?= $temple['status'] === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
            <a href="<?= $base_url ?>temples/" class="bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-6 rounded-lg transition">
                ຍົກເລີກ
            </a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-lg transition">
                ບັນທຶກການປ່ຽນແປງ
            </button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>