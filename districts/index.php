<?php
// ป้องกัน warning/notice ที่อาจปนมากับ JSON response
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$page_title = 'ຈັດການເມືອງ';
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

// อนุญาตเฉพาะ superadmin และ province_admin
if (!in_array($user_role, ['superadmin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ดึงแขวงที่ province_admin ดูแล
$user_provinces = [];
if ($user_role === 'province_admin') {
    $province_stmt = $pdo->prepare("
        SELECT p.province_id, p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $user_provinces = $province_stmt->fetchAll();
} else {
    // superadmin เห็นทุกแขวง
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $user_provinces = $province_stmt->fetchAll();
}

// สร้างตาราง districts ถ้ายังไม่มี
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS districts (
            district_id INT AUTO_INCREMENT PRIMARY KEY,
            district_name VARCHAR(100) NOT NULL,
            district_code VARCHAR(10),
            province_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (province_id) REFERENCES provinces(province_id) ON DELETE CASCADE
        )
    ");
    
    // เพิ่ม column district_id ให้ตาราง temples ถ้ายังไม่มี
    $pdo->exec("ALTER TABLE temples ADD COLUMN district_id INT NULL");
    $pdo->exec("ALTER TABLE temples ADD FOREIGN KEY (district_id) REFERENCES districts(district_id) ON DELETE SET NULL");
    
} catch (PDOException $e) {
    // ถ้าตารางหรือคอลัมน์มีอยู่แล้วจะไม่ทำอะไร
    // หรือถ้า foreign key มีอยู่แล้ว
}

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province_filter = isset($_GET['province']) ? (int)$_GET['province'] : 0;

// Build query based on user role
$where_conditions = [];
$params = [];

if ($user_role === 'province_admin') {
    // กรองเฉพาะแขวงที่ province_admin ดูแล
    $province_ids = array_column($user_provinces, 'province_id');
    if (!empty($province_ids)) {
        $placeholders = str_repeat('?,', count($province_ids) - 1) . '?';
        $where_conditions[] = "d.province_id IN ($placeholders)";
        $params = array_merge($params, $province_ids);
    } else {
        // ถ้าไม่มีแขวงที่ดูแล ให้แสดงข้อมูลว่าง
        $where_conditions[] = "1=0";
    }
}

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $where_conditions[] = "d.district_name LIKE ?";
    $params[] = "%{$search}%";
}

if (!empty($province_filter)) {
    $where_conditions[] = "d.province_id = ?";
    $params[] = $province_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// ดึงข้อมูลเมือง - แก้ไข query
$query = "
    SELECT 
        d.*,
        p.province_name,
        COALESCE(temple_counts.temple_count, 0) as temple_count
    FROM districts d 
    JOIN provinces p ON d.province_id = p.province_id 
    LEFT JOIN (
        SELECT district_id, COUNT(*) as temple_count 
        FROM temples 
        WHERE district_id IS NOT NULL 
        GROUP BY district_id
    ) temple_counts ON d.district_id = temple_counts.district_id
    $where_clause
    ORDER BY p.province_name, d.district_name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$districts = $stmt->fetchAll();

// Handle AJAX requests
if (isset($_POST['action'])) {
    // ล้าง output buffer เพื่อให้แน่ใจว่าไม่มี output อื่นปนมา
    ob_clean();
    
    // ตั้งค่า header เป็น JSON
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_district') {
        $district_name = trim($_POST['district_name']);
        $district_code = trim($_POST['district_code'] ?? '');
        $province_id = (int)$_POST['province_id'];
        
        // ตรวจสอบว่า province_admin มีสิทธิ์เพิ่มในแขวงนี้
        if ($user_role === 'province_admin') {
            $check_access = $pdo->prepare("SELECT COUNT(*) FROM user_province_access WHERE user_id = ? AND province_id = ?");
            $check_access->execute([$user_id, $province_id]);
            if ($check_access->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເພີ່ມເມືອງໃນແຂວงນີ້']);
                exit;
            }
        }
        
        try {
            $insert_stmt = $pdo->prepare("INSERT INTO districts (district_name, district_code, province_id) VALUES (?, ?, ?)");
            $insert_stmt->execute([$district_name, $district_code, $province_id]);
            
            if ($insert_stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'ເພີ່ມເມືອງສຳເລັດແລ້ວ']);
            } else {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ສາມາດເພີ່ມເມືອງໄດ້']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'edit_district') {
        $district_id = (int)$_POST['district_id'];
        $district_name = trim($_POST['district_name']);
        $district_code = trim($_POST['district_code'] ?? '');
        $province_id = (int)$_POST['province_id'];
        
        // ตรวจสอบถ้าเป็น province_admin ต้องมีสิทธิ์แก้ไขในแขวงนั้น
        if ($user_role === 'province_admin') {
            $check_access = $pdo->prepare("SELECT COUNT(*) FROM user_province_access WHERE user_id = ? AND province_id = ?");
            $check_access->execute([$user_id, $province_id]);
            if ($check_access->fetchColumn() == 0) {
                echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນໃນແຂວງນີ້']);
                exit;
            }
            
            // ตรวจสอบเพิ่มเติมว่าเมืองนี้อยู่ในแขวงที่มีสิทธิ์
            $check_district = $pdo->prepare("SELECT province_id FROM districts WHERE district_id = ?");
            $check_district->execute([$district_id]);
            $current_province = $check_district->fetchColumn();
            
            if ($current_province != $province_id) {
                echo json_encode(['success' => false, 'message' => 'ບໍ່ສາມາດຍ້າຍເມືອງໄປແຂວງອື່ນໄດ້']);
                exit;
            }
        }
        
        try {
            // อัพเดตรวมถึง province_id ถ้าเป็น superadmin
            if ($user_role === 'superadmin') {
                $update_stmt = $pdo->prepare("UPDATE districts SET district_name = ?, district_code = ?, province_id = ? WHERE district_id = ?");
                $update_stmt->execute([$district_name, $district_code, $province_id, $district_id]);
            } else {
                // province_admin ไม่สามารถเปลี่ยนแขวงได้
                $update_stmt = $pdo->prepare("UPDATE districts SET district_name = ?, district_code = ? WHERE district_id = ?");
                $update_stmt->execute([$district_name, $district_code, $district_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'ອັບເດດເມືອງສຳເລັດແລ້ວ']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_district') {
        $district_id = (int)$_POST['district_id'];
        
        try {
            $check_temples = $pdo->prepare("SELECT COUNT(*) FROM temples WHERE district_id = ?");
            $check_temples->execute([$district_id]);
            $temple_count = $check_temples->fetchColumn();
            
            if ($temple_count > 0) {
                echo json_encode(['success' => false, 'message' => "ບໍ່ສາມາດລຶບເມືອງນີ້ໄດ້ ເພາະວ່າມີວັດ $temple_count ວັດຢູໃນເມືອງນີ້"]);
                exit;
            }
            
            $delete_stmt = $pdo->prepare("DELETE FROM districts WHERE district_id = ?");
            $delete_stmt->execute([$district_id]);
            echo json_encode(['success' => true, 'message' => 'ລຶບເມືອງສຳເລັດແລ້ວ']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()]);
        }
        exit;
    }

    // ถ้าไม่มี action ที่รองรับ
    echo json_encode(['success' => false, 'message' => 'ຄຳສັ່ງບໍ່ຖືກຕ້ອງ']);
    exit;
}
?>

<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<div class="page-container">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-6 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-city"></i>
                    </div>
                    ຈັດການເມືອງ
                    <?php if ($user_role === 'province_admin'): ?>
                        <span class="text-sm font-normal text-amber-700 ml-2">(ແຂວงທີ່ຮັບຜິດຊອບ)</span>
                    <?php endif; ?>
                </h1>
                <p class="text-sm text-amber-700 mt-1">
                    <?php if ($user_role === 'superadmin'): ?>
                        ເບິ່ງແລະຈັດການເມືອງທັງໝົດ
                    <?php elseif ($user_role === 'province_admin'): ?>
                        ເບິ່ງແລະຈັດການເມືອງໃນແຂວງທີ່ທ່ານຮັບຜິດຊອບ
                    <?php endif; ?>
                </p>
            </div>
            <button id="addDistrictBtn" class="btn-primary flex items-center gap-2">
                <i class="fas fa-plus"></i> ເພີ່ມເມືອງໃໝ່
            </button>
        </div>

        <!-- แสดงข้อมูลสถิติ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card p-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">ເມືອງທັງໝົດ</p>
                        <p class="text-2xl font-bold"><?= count($districts) ?></p>
                    </div>
                    <i class="fas fa-city text-3xl text-purple-200"></i>
                </div>
            </div>
            
            <div class="card p-4 bg-gradient-to-r from-green-500 to-green-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">ແຂວງທີ່ດູແລ</p>
                        <p class="text-2xl font-bold"><?= count($user_provinces) ?></p>
                    </div>
                    <i class="fas fa-map-marker-alt text-3xl text-green-200"></i>
                </div>
            </div>
            
            <?php
            $total_temples = array_sum(array_column($districts, 'temple_count'));
            ?>
            <div class="card p-4 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-100">ວັດທັງໝົດ</p>
                        <p class="text-2xl font-bold"><?= $total_temples ?></p>
                    </div>
                    <i class="fas fa-gopuram text-3xl text-amber-200"></i>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-section p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ຄົ້ນຫາເມືອງ</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search) ?>" 
                        placeholder="ຊື່ເມືອງ..." 
                        class="form-input w-full"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວງ</label>
                    <select name="province" class="form-select w-full">
                        <option value="">-- ທຸກແຂວງ --</option>
                        <?php foreach($user_provinces as $prov): ?>
                        <option value="<?= $prov['province_id'] ?>" <?= $province_filter == $prov['province_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['province_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg mr-2 transition">
                        <i class="fas fa-filter mr-1"></i> ຕັງຄ່າຟິວເຕີ
                    </button>
                    
                    <a href="<?= $base_url ?>districts/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                        <i class="fas fa-sync-alt mr-1"></i> ລ້າງ
                    </a>
                </div>
            </form>
        </div>

        <!-- Districts List -->
        <div class="card overflow-hidden">
            <?php if (count($districts) > 0): ?>
            <table class="w-full data-table">
                <thead class="table-header">
                    <tr>
                        <th class="px-6 py-3 text-left">ຊື່ເມືອງ</th>
                        <th class="px-6 py-3 text-left">ລະຫັດເມືອງ</th>
                        <th class="px-6 py-3 text-left">ແຂວງ</th>
                        <th class="px-6 py-3 text-left">ຈຳນວນວັດ</th>
                        <th class="px-6 py-3 text-left">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($districts as $district): ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="category-icon mr-3">
                                    <i class="fas fa-city"></i>
                                </div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($district['district_name']) ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            <?= htmlspecialchars($district['district_code'] ?? '-') ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            <?= htmlspecialchars($district['province_name']) ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?= $district['temple_count'] ?> ວັດ
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div class="flex space-x-3">
                                <button class="text-blue-600 hover:text-blue-800 edit-district" 
                                   data-id="<?= $district['district_id'] ?>"
                                   data-name="<?= htmlspecialchars($district['district_name']) ?>"
                                   data-code="<?= htmlspecialchars($district['district_code'] ?? '') ?>"
                                   data-province="<?= $district['province_id'] ?>"
                                   title="ແກ້ໄຂ">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if ($district['temple_count'] == 0): ?>
                                <button class="text-red-500 hover:text-red-700 delete-district" 
                                   data-id="<?= $district['district_id'] ?>"
                                   data-name="<?= htmlspecialchars($district['district_name']) ?>"
                                   title="ລຶບ">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-gray-400" title="ບໍ່ສາມາດລຶບໄດ້ ມີວັດຢູໃນເມືອງ">
                                    <i class="fas fa-trash"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-6 text-center">
                <div class="text-gray-500 mb-4">
                    <i class="fas fa-city text-4xl text-gray-300 mb-2"></i>
                    <p>ບໍ່ພົບລາຍການເມືອງ</p>
                    <button id="addFirstDistrictBtn" class="mt-3 btn-primary">
                        <i class="fas fa-plus mr-2"></i> ເພີ່ມເມືອງແລກ
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit District Modal -->
<div id="districtModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="card bg-white max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">ເພີ່ມເມືອງໃໝ່</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="districtForm">
            <input type="hidden" id="districtId" name="district_id">
            <input type="hidden" id="formAction" name="action" value="add_district">
            <input type="hidden" id="hiddenProvinceId" name="province_id"> <!-- เพิ่มบรรทัดนี้ -->
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">ຊື່ເມືອງ *</label>
                <input type="text" id="districtName" name="district_name" required class="form-input w-full">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">ລະຫັດເມືອງ</label>
                <input type="text" id="districtCode" name="district_code" class="form-input w-full">
            </div>
            
            <div class="mb-4" id="provinceSelectDiv">
                <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວง *</label>
                <select id="provinceSelect" name="province_id" required class="form-select w-full">
                    <option value="">-- ເລືອກແຂວງ --</option>
                    <?php foreach($user_provinces as $prov): ?>
                    <option value="<?= $prov['province_id'] ?>"><?= htmlspecialchars($prov['province_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" class="close-modal btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                    ຍົກເລີກ
                </button>
                <button type="submit" class="btn-primary px-4 py-2">
                    <i class="fas fa-save mr-1"></i> ບັນທຶກ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const districtModal = document.getElementById('districtModal');
    const districtForm = document.getElementById('districtForm');
    const modalTitle = document.getElementById('modalTitle');
    
    // เปิด modal เพิ่มเมือง
    document.querySelectorAll('#addDistrictBtn, #addFirstDistrictBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            modalTitle.textContent = 'ເພີ່ມເມືອງໃໝ່';
            document.getElementById('formAction').value = 'add_district';
            document.getElementById('districtId').value = '';
            document.getElementById('districtName').value = '';
            document.getElementById('districtCode').value = '';
            document.getElementById('provinceSelect').value = '';
            document.getElementById('provinceSelectDiv').style.display = 'block';
            districtModal.classList.remove('hidden');
        });
    });
    
    // เปิด modal แก้ไขเมือง
    document.querySelectorAll('.edit-district').forEach(btn => {
        btn.addEventListener('click', function() {
            modalTitle.textContent = 'ແກ້ໄຂຂໍ້ມູນເມືອງ';
            document.getElementById('formAction').value = 'edit_district';
            document.getElementById('districtId').value = this.dataset.id;
            document.getElementById('districtName').value = this.dataset.name;
            document.getElementById('districtCode').value = this.dataset.code;
            
            // เก็บ province_id ใน hidden field
            document.getElementById('hiddenProvinceId').value = this.dataset.province;
            
            // ที่ยังเลือกแขวงได้ แต่ตั้งค่าเริ่มต้นเป็นแขวงเดิม (ทางเลือก)
            document.getElementById('provinceSelect').value = this.dataset.province;
            
            // ซ่อน provinceSelect สำหรับ province_admin แต่สำหรับ superadmin ยังเลือกได้
            if ('<?= $user_role ?>' === 'province_admin') {
                document.getElementById('provinceSelectDiv').style.display = 'none';
            } else {
                document.getElementById('provinceSelectDiv').style.display = 'block';
            }
            
            districtModal.classList.remove('hidden');
        });
    });
    
    // ปิด modal
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            districtModal.classList.add('hidden');
        });
    });
    
    // ส่งฟอร์ม
    districtForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // แสดง Loading
        Swal.fire({
            title: 'ກຳລັງປະມວນຜົນ...',
            text: 'ກະລຸນາລໍຖ້າ',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // ตรวจสอบสถานะ response
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            // ลองอ่านข้อมูล response เป็น text ก่อน
            return response.text();
        })
        .then(text => {
            // ลองแปลง text เป็น JSON
            try {
                const data = JSON.parse(text);
                
                // ถ้าแปลงสำเร็จและข้อมูลถูกต้อง
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ສຳເລັດ!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ເກີດຂໍ້ຜິດພາດ!',
                        text: data.message || 'ເກີດຂໍ້ຜິດພາດທີ່ບໍ່ຮູ້ສາເຫດ'
                    });
                }
            } catch (e) {
                // ถ้าแปลงไม่สำเร็จ แสดง error และข้อมูล response ดิบ
                console.error('Error parsing JSON:', e);
                console.log('Raw response:', text);
                
                // ตรวจสอบว่ามีคำว่า "success" ในข้อความหรือไม่ (กรณีที่ JSON ไม่สมบูรณ์แต่บันทึกสำเร็จ)
                if (text.includes('"success":true')) {
                    Swal.fire({
                        icon: 'success',
                        title: 'ສຳເລັດ!',
                        html: 'ການບັນທຶກຂໍ້ມູນສຳເລັດແລ້ວ<br><small class="text-gray-500">ແຕ່ມີຂໍ້ຜິດພາດບາງຢ່າງກັບຂໍ້ມູນທີ່ສົ່ງກັບມາ</small>',
                        showConfirmButton: true
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ບໍ່ແນ່ໃຈວ່າບັນທຶກສຳເລັດຫຼືບໍ່',
                        html: 'ລະບົບບໍ່ສາມາດຢືນຢັນໄດ້ວ່າການບັນທຶກສຳເລັດຫຼືບໍ່<br>ກົດ "ໂຫຼດຂໍ້ມູນໃໝ່" ເພື່ອກວດສອບຂໍ້ມູນ',
                        confirmButtonText: 'ໂຫຼດຂໍ້ມູນໃໝ່',
                        showCancelButton: true,
                        cancelButtonText: 'ປິດ'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            location.reload();
                        }
                    });
                }
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'ເກີດຂໍ້ຜິດພາດ!',
                text: `ເກີດຂໍ້ຜິດພາດໃນການສົ່ງຂໍ້ມູນ: ${error.message}`
            });
        });
    });
    
    // ลบเมือง
    document.querySelectorAll('.delete-district').forEach(btn => {
        btn.addEventListener('click', function() {
            const districtId = this.dataset.id;
            const districtName = this.dataset.name;
            
            Swal.fire({
                title: 'ຢືນຢັນການລຶບ?',
                text: `ທ່ານຕ້ອງການລຶບເມືອງ "${districtName}" ແທ້ບໍ່?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ລຶບ',
                cancelButtonText: 'ຍົກເລີກ'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_district');
                    formData.append('district_id', districtId);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ສຳເລັດ!',
                                text: data.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ເກີດຂໍ້ຜິດພາດ!',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'ເກີດຂໍ້ຜິດພາດ!',
                            text: 'ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ'
                        });
                    });
                }
            });
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>