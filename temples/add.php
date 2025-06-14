<?php
$page_title = 'ເພີ່ມວັດໃໝ່';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../auth/check_superadmin.php';
require_once '../includes/header.php';

// Check if user has permission
if ($_SESSION['user']['role'] !== 'superadmin') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເພີ່ມຂໍ້ມູນວັດ";
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
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo_name = time() . '_' . $_FILES['photo']['name'];
            $photo_tmp = $_FILES['photo']['tmp_name'];
            $photo_path = '../uploads/temples/' . $photo_name;
            
            // Create directory if it doesn't exist
            if (!is_dir('../uploads/temples/')) {
                mkdir('../uploads/temples/', 0777, true);
            }
            
            if (move_uploaded_file($photo_tmp, $photo_path)) {
                $photo = 'uploads/temples/' . $photo_name;
            }
        }
        
        // Process logo upload
        $logo = null;
        if (!empty($_FILES['logo']['name'])) {
            $logo_name = time() . '_logo_' . $_FILES['logo']['name'];
            $logo_tmp = $_FILES['logo']['tmp_name'];
            $logo_path = '../uploads/temples/' . $logo_name;
            
            if (move_uploaded_file($logo_tmp, $logo_path)) {
                $logo = 'uploads/temples/' . $logo_name;
            }
        }
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO temples (
                name, address, district, province, phone, email, 
                website, founding_date, abbot_name, description, 
                photo, logo, latitude, longitude, status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            $name, $address, $district, $province, $phone, $email, 
            $website, $founding_date, $abbot_name, $description, 
            $photo, $logo, $latitude, $longitude, $status
        ]);
        
        $success = 'ບັນທຶກຂໍ້ມູນວັດສຳເລັດແລ້ວ';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- เพิ่ม CSS ของหน้า -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<!-- Page Container -->
<div class="page-container">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-8 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-gopuram"></i>
                    </div>
                    ເພີ່ມວັດໃໝ່
                </h1>
                <p class="text-sm text-amber-700 mt-1">ປ້ອນຂໍ້ມູນລາຍລະອຽດຂອງວັດ</p>
            </div>
            <a href="<?= $base_url ?>temples/" class="btn flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                <i class="fas fa-arrow-left"></i> ກັບຄືນ
            </a>
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
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ທີ່ຢູ່</label>
                            <input 
                                type="text" 
                                name="address"
                                class="form-input w-full"
                            >
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເມືອງ</label>
                                <input 
                                    type="text" 
                                    name="district"
                                    class="form-input w-full"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ແຂວງ</label>
                                <input 
                                    type="text" 
                                    name="province"
                                    class="form-input w-full"
                                >
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເບີໂທ</label>
                                <input 
                                    type="tel" 
                                    name="phone"
                                    class="form-input w-full"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ອີເມວ</label>
                                <input 
                                    type="email" 
                                    name="email"
                                    class="form-input w-full"
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
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຊື່ເຈົ້າອະທິການ</label>
                            <input 
                                type="text" 
                                name="abbot_name"
                                class="form-input w-full"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ວັນທີສ້າງຕັ້ງ</label>
                            <input 
                                type="date" 
                                name="founding_date"
                                class="form-input w-full"
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
                            ></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນແວງ (Latitude)</label>
                                <input 
                                    type="text" 
                                    name="latitude"
                                    class="form-input w-full"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ເສັ້ນຂະໜານ (Longitude)</label>
                                <input 
                                    type="text" 
                                    name="longitude"
                                    class="form-input w-full"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ຮູບພາບວັດ</label>
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
                            <input 
                                type="file" 
                                name="logo"
                                accept="image/*"
                                class="form-input w-full"
                            >
                            <p class="text-xs text-gray-500 mt-1">ແນະນຳ: ຮູບພາບທີ່ມີພື້ນຫຼັກໂປ່ງໃສ, ສູງສຸດ 2MB</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                            <select 
                                name="status"
                                class="form-select w-full"
                            >
                                <option value="active">ເປີດໃຊ້ງານ</option>
                                <option value="inactive">ປິດໃຊ້ງານ</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                    <a href="<?= $base_url ?>temples/" class="btn px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
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

<?php require_once '../includes/footer.php'; ?>