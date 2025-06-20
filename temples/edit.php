<?php
$page_title = 'ແກ້ໄຂຂໍ້ມູນວັດ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ตรวจสอบสิทธิ์การเข้าถึง
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
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

$temple_id = (int)$_GET['id'];

// ตรวจสอบสิทธิ์การแก้ไข
$can_edit = false;
if ($user_role === 'superadmin') {
    $can_edit = true;
} elseif ($user_role === 'admin' && $temple_id == $user_temple_id) {
    $can_edit = true;
} elseif ($user_role === 'province_admin') {
    // ตรวจสอบว่าวัดนี้อยู่ในแขวงที่ province_admin ดูแลหรือไม่
    $check_access = $pdo->prepare("
        SELECT COUNT(*) FROM temples t
        JOIN user_province_access upa ON t.province_id = upa.province_id
        WHERE t.id = ? AND upa.user_id = ?
    ");
    $check_access->execute([$temple_id, $user_id]);
    $can_edit = ($check_access->fetchColumn() > 0);
}

if (!$can_edit) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດແກ້ໄຂວັດນີ້";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// Get temple data
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        d.district_name,
        p.province_name
    FROM temples t 
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
    WHERE t.id = ?
");
$stmt->execute([$temple_id]);
$temple = $stmt->fetch();

if (!$temple) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນວັດ";
    header('Location: ' . $base_url . 'temples/');
    exit;
}

// ดึงแขวงที่ผู้ใช้สามารถแก้ไขได้
$available_provinces = [];
if ($user_role === 'superadmin') {
    // superadmin เห็นทุกแขวง
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $available_provinces = $province_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    // province_admin เห็นเฉพาะแขวงที่ตัวเองดูแล
    $province_stmt = $pdo->prepare("
        SELECT p.province_id, p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $available_provinces = $province_stmt->fetchAll();
} elseif ($user_role === 'admin') {
    // admin เห็นเฉพาะแขวงของวัดตัวเอง
    $province_stmt = $pdo->prepare("
        SELECT DISTINCT p.province_id, p.province_name 
        FROM temples t 
        JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.id = ?
    ");
    $province_stmt->execute([$temple_id]);
    $available_provinces = $province_stmt->fetchAll();
}

$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Extract form data with validation
        $name = trim($_POST['name']);
        $address = trim($_POST['address'] ?? '');
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
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
        
        // ตรวจสอบสิทธิ์ province_admin
        if ($user_role === 'province_admin' && $province_id) {
            $check_access = $pdo->prepare("SELECT COUNT(*) FROM user_province_access WHERE user_id = ? AND province_id = ?");
            $check_access->execute([$user_id, $province_id]);
            if ($check_access->fetchColumn() == 0) {
                throw new Exception('ທ່ານບໍ່ມີສິດແກ້ໄຂວັດໃນແຂວງນີ້');
            }
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
                throw new Exception('ປະເພດໄຟລ໌ບໍ່ຮອງຮັບ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG ແລະ GIF ເທົ່ານັ້ນ');
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
                throw new Exception('ປະເພດໄຟລ໌ບໍ່ຮອງຮັບ. ອະນຸຍາດສະເພາະ JPG, JPEG, PNG ແລະ GIF ເທົ່ານັ້ນ');
            }
        }
        
        // Update database
        $stmt = $pdo->prepare("
            UPDATE temples SET
                name = ?, address = ?, district_id = ?, province_id = ?, phone = ?, email = ?, 
                website = ?, founding_date = ?, abbot_name = ?, description = ?, 
                photo = ?, logo = ?, latitude = ?, longitude = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $address, $district_id, $province_id, $phone, $email, 
            $website, $founding_date, $abbot_name, $description, 
            $photo, $logo, $latitude, $longitude, $status,
            $temple_id
        ]);
        
        $success = 'ແກ້ໄຂຂໍ້ມູນວັດສຳເລັດແລ້ວ';
        
        // Refresh temple data
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                d.district_name,
                p.province_name
            FROM temples t 
            LEFT JOIN districts d ON t.district_id = d.district_id
            LEFT JOIN provinces p ON t.province_id = p.province_id
            WHERE t.id = ?
        ");
        $stmt->execute([$temple_id]);
        $temple = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- เพิ่ม CSS ของหน้า -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<div class="page-container">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-6 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    ແກ້ໄຂຂໍ້ມູນວັດ
                </h1>
                <p class="text-sm text-amber-700 mt-1">ແກ້ໄຂລາຍລະອຽດຂອງວັດ <?= htmlspecialchars($temple['name']) ?></p>
            </div>
            <div class="flex space-x-2">
                <a href="<?= $base_url ?>temples/view.php?id=<?= $temple_id ?>" class="btn px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-lg flex items-center transition">
                    <i class="fas fa-eye mr-2"></i> ເບິ່ງ
                </a>
                <a href="<?= $base_url ?>temples/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center transition">
                    <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                <p class="text-green-700"><?= $success ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?= $error ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Temple Form -->
        <div class="card bg-white p-6">
            <form method="POST" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            ຂໍ້ມູນພື້ນຖານ
                        </h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຊື່ວັດ <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                name="name" 
                                required
                                class="form-input w-full"
                                value="<?= htmlspecialchars($temple['name']) ?>"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ທີ່ຢູ່</label>
                            <input 
                                type="text" 
                                name="address"
                                class="form-input w-full"
                                value="<?= htmlspecialchars($temple['address'] ?? '') ?>"
                            >
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ແຂວງ <span class="text-red-500">*</span></label>
                                <select 
                                    name="province_id"
                                    id="province_id"
                                    class="form-select w-full"
                                    required
                                    onchange="loadDistricts(this.value)"
                                    <?= $user_role === 'admin' ? 'disabled' : '' ?>
                                >
                                    <option value="">ເລືອກແຂວງ</option>
                                    <?php foreach($available_provinces as $province): ?>
                                    <option value="<?= $province['province_id'] ?>" 
                                        <?= $temple['province_id'] == $province['province_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($province['province_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($user_role === 'admin'): ?>
                                    <input type="hidden" name="province_id" value="<?= $temple['province_id'] ?>">
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເມືອງ</label>
                                <select 
                                    name="district_id"
                                    id="district_id"
                                    class="form-select w-full"
                                >
                                    <option value="">ເລືອກແຂວງກ່ອນ</option>
                                    <?php if ($temple['district_id']): ?>
                                    <option value="<?= $temple['district_id'] ?>" selected>
                                        <?= htmlspecialchars($temple['district_name'] ?? '') ?>
                                    </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທ</label>
                                <input 
                                    type="tel" 
                                    name="phone"
                                    class="form-input w-full"
                                    value="<?= htmlspecialchars($temple['phone'] ?? '') ?>"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ອີເມວ</label>
                                <input 
                                    type="email" 
                                    name="email"
                                    class="form-input w-full"
                                    value="<?= htmlspecialchars($temple['email'] ?? '') ?>"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ເວັບໄຊທ໌</label>
                            <input 
                                type="url" 
                                name="website"
                                placeholder="https://"
                                class="form-input w-full"
                                value="<?= htmlspecialchars($temple['website'] ?? '') ?>"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຊື່ເຈົ້າອະທິການ</label>
                            <input 
                                type="text" 
                                name="abbot_name"
                                class="form-input w-full"
                                value="<?= htmlspecialchars($temple['abbot_name'] ?? '') ?>"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ວັນທີສ້າງຕັ້ງ</label>
                            <input 
                                type="date" 
                                name="founding_date"
                                class="form-input w-full"
                                value="<?= $temple['founding_date'] ?? '' ?>"
                            >
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium flex items-center">
                            <div class="icon-circle">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            ຂໍ້ມູນເພີ່ມເຕີມ
                        </h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຄຳອະທິບາຍ</label>
                            <textarea 
                                name="description" 
                                rows="4" 
                                class="form-input w-full"
                            ><?= htmlspecialchars($temple['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນແວງ (Latitude)</label>
                                <input 
                                    type="text" 
                                    name="latitude"
                                    class="form-input w-full"
                                    value="<?= $temple['latitude'] ?? '' ?>"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນຂະໜານ (Longitude)</label>
                                <input 
                                    type="text" 
                                    name="longitude"
                                    class="form-input w-full"
                                    value="<?= $temple['longitude'] ?? '' ?>"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຮູບພາບວັດ</label>
                            <?php if ($temple['photo']): ?>
                                <div class="mb-3">
                                    <img src="<?= $base_url . $temple['photo'] ?>" alt="Temple photo" class="w-40 h-auto rounded-lg shadow-sm">
                                </div>
                            <?php endif; ?>
                            <input 
                                type="file" 
                                name="photo"
                                accept="image/*"
                                class="form-input w-full"
                            >
                            <p class="text-xs text-gray-500 mt-1">ແນະນຳ: ຮູບພາບຂະໜາດ 1200x800px, ສູງສຸດ 5MB</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ໂລໂກ້ວັດ</label>
                            <?php if ($temple['logo']): ?>
                                <div class="mb-3">
                                    <img src="<?= $base_url . $temple['logo'] ?>" alt="Temple logo" class="w-20 h-auto rounded shadow-sm">
                                </div>
                            <?php endif; ?>
                            <input 
                                type="file" 
                                name="logo"
                                accept="image/*"
                                class="form-input w-full"
                            >
                            <p class="text-xs text-gray-500 mt-1">ແນະນຳ: ຮູບພາບທີ່ມີພື້ນຫຼັງໂປ່ງໃສ, ສູງສຸດ 2MB</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                            <select 
                                name="status"
                                class="form-select w-full"
                            >
                                <option value="active" <?= $temple['status'] === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                                <option value="inactive" <?= $temple['status'] === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                    <a href="<?= $base_url ?>temples/" class="btn px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" class="btn btn-primary px-6 py-2 text-white rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-save"></i> ບັນທຶກການແກ້ໄຂ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// โหลดรายการเมืองตามแขวงที่เลือก
function loadDistricts(provinceId) {
    const districtSelect = document.getElementById('district_id');
    const currentDistrictId = <?= $temple['district_id'] ?? 'null' ?>;
    
    // Clear existing options except current one
    districtSelect.innerHTML = '<option value="">ກຳລັງໂຫຼດ...</option>';
    
    if (!provinceId) {
        districtSelect.innerHTML = '<option value="">ເລືອກແຂວງກ່ອນ</option>';
        return;
    }
    
    // Fetch districts
    fetch('<?= $base_url ?>api/get-districts.php?province_id=' + provinceId)
        .then(response => response.json())
        .then(data => {
            districtSelect.innerHTML = '<option value="">-- ເລືອກເມືອງ --</option>';
            
            if (data.success && data.districts) {
                data.districts.forEach(district => {
                    const option = document.createElement('option');
                    option.value = district.district_id;
                    option.textContent = district.district_name;
                    
                    // ถ้าเป็นเมืองปัจจุบัน ให้เลือกไว้
                    if (district.district_id == currentDistrictId) {
                        option.selected = true;
                    }
                    
                    districtSelect.appendChild(option);
                });
            } else {
                districtSelect.innerHTML = '<option value="">ບໍ່ມີເມືອງໃນແຂວງນີ້</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            districtSelect.innerHTML = '<option value="">ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນ</option>';
        });
}

// Auto-load districts เมื่อโหลดหน้า
document.addEventListener('DOMContentLoaded', function() {
    const provinceSelect = document.getElementById('province_id');
    if (provinceSelect.value) {
        loadDistricts(provinceSelect.value);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>