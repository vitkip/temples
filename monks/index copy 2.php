<?php
ob_start();
session_start();

$page_title = 'ຈັດການຂໍ້ມູນພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user'])) {
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ดึงข้อมูลผู้ใช้จาก session
$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// รับค่าตัวกรองจาก GET
$province_filter = isset($_GET['province_id']) && is_numeric($_GET['province_id']) ? (int)$_GET['province_id'] : null;
$district_filter = isset($_GET['district_id']) && is_numeric($_GET['district_id']) ? (int)$_GET['district_id'] : null;
$temple_filter = isset($_GET['temple_id']) && is_numeric($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$pansa_filter = isset($_GET['pansa']) ? $_GET['pansa'] : '';

// เริ่มสร้าง query
$params = [];
$query = "SELECT m.*, t.name as temple_name, p.province_name 
          FROM monks m 
          LEFT JOIN temples t ON m.temple_id = t.id 
          LEFT JOIN provinces p ON t.province_id = p.province_id
          WHERE 1=1";

// การกรองตามสิทธิ์ผู้ใช้
if ($user_role === 'admin') {
    $query .= " AND m.temple_id = ?";
    $params[] = $user_temple_id;
} elseif ($user_role === 'province_admin') {
    $query .= " AND t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";

    $params[] = $user_id;
}

// การกรองจากฟอร์ม
if ($province_filter) {
    $query .= " AND t.province_id = ?";
    $params[] = $province_filter;
}
if ($district_filter) {
    $query .= " AND t.district_id = ?";
    $params[] = $district_filter;
}
if ($temple_filter) {
    $query .= " AND m.temple_id = ?";
    $params[] = $temple_filter;
}
if ($search_term) {
    $query .= " AND (m.name LIKE ? OR m.lay_name LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}
if ($status_filter) {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}
if ($pansa_filter) {
    switch ($pansa_filter) {
        case '0-5': $query .= " AND m.pansa BETWEEN 0 AND 5"; break;
        case '6-10': $query .= " AND m.pansa BETWEEN 6 AND 10"; break;
        case '11-20': $query .= " AND m.pansa BETWEEN 11 AND 20"; break;
        case '21+': $query .= " AND m.pansa > 20"; break;
    }
}

$query .= " ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$monks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลสำหรับ dropdown
$provinces = [];
if ($user_role === 'superadmin') {
    $provinces = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role === 'province_admin') {
    $stmt = $pdo->prepare("SELECT p.province_id, p.province_name FROM provinces p JOIN user_province_access upa ON p.province_id = upa.province_id WHERE upa.user_id = ? ORDER BY p.province_name");
    $stmt->execute([$user_id]);
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$can_add = in_array($user_role, ['superadmin', 'admin', 'province_admin']);
?>

<style>
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #4A5568; }
    .form-select, .form-input { width: 100%; padding: 0.5rem; border: 1px solid #CBD5E0; border-radius: 0.375rem; }
    .btn { padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 600; text-align: center; transition: background-color 0.2s; }
    .btn-primary { background-color: #4C51BF; color: white; }
    .btn-primary:hover { background-color: #434190; }
    .btn-secondary { background-color: #E2E8F0; color: #2D3748; }
    .btn-secondary:hover { background-color: #CBD5E0; }
    .loading-indicator::before {
        content: ""; position: absolute; left: 5px; top: 50%; width: 16px; height: 16px;
        margin-top: -8px; border: 2px solid #718096; border-top-color: transparent;
        border-radius: 50%; animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-indicator { position: relative; padding-left: 25px; color: #718096; }
</style>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">ຈັດການຂໍ້ມູນພະສົງ</h1>
        <?php if ($can_add): ?>
            <a href="<?= $base_url ?>monks/add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> ເພີ່ມພະສົງໃໝ່
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">ຕົວກອງຂໍ້ມູນ</h2>
        <form action="" method="GET" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Search -->
                <div>
                    <label for="search" class="form-label">ຄົ້ນຫາ</label>
                    <input type="text" name="search" id="search" class="form-input" placeholder="ຊື່, ນາມສະກຸນ..." value="<?= htmlspecialchars($search_term) ?>">
                </div>

                <!-- Province (for superadmin/province_admin) -->
                <?php if (in_array($user_role, ['superadmin', 'province_admin'])): ?>
                <div>
                    <label for="province_id" class="form-label">ແຂວງ</label>
                    <select name="province_id" id="province_id" class="form-select">
                        <option value="">-- ທຸກແຂວງ --</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?= $province['province_id'] ?>" <?= ($province_filter == $province['province_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($province['province_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- District -->
                <div id="district-container" style="<?= $province_filter ? '' : 'display:none;' ?>">
                    <label for="district_id" class="form-label">ເມືອງ</label>
                    <select name="district_id" id="district_id" class="form-select">
                        <option value="">-- ເລືອກແຂວງກ່ອນ --</option>
                    </select>
                </div>

                <!-- Temple -->
                <div id="temple-container" style="<?= $district_filter ? '' : 'display:none;' ?>">
                    <label for="temple_id" class="form-label">ວັດ</label>
                    <select name="temple_id" id="temple_id" class="form-select">
                        <option value="">-- ເລືອກເມືອງກ່ອນ --</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="form-label">ສະຖານະ</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">-- ທຸກສະຖານະ --</option>
                        <option value="active" <?= ($status_filter == 'active') ? 'selected' : '' ?>>ຍັງບວດຢູ່</option>
                        <option value="inactive" <?= ($status_filter == 'inactive') ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                    </select>
                </div>

                <!-- Pansa -->
                <div>
                    <label for="pansa" class="form-label">ພັນສາ</label>
                    <select name="pansa" id="pansa" class="form-select">
                        <option value="">-- ທຸກພັນສາ --</option>
                        <option value="0-5" <?= ($pansa_filter == '0-5') ? 'selected' : '' ?>>0-5 ພັນສາ</option>
                        <option value="6-10" <?= ($pansa_filter == '6-10') ? 'selected' : '' ?>>6-10 ພັນສາ</option>
                        <option value="11-20" <?= ($pansa_filter == '11-20') ? 'selected' : '' ?>>11-20 ພັນສາ</option>
                        <option value="21+" <?= ($pansa_filter == '21+') ? 'selected' : '' ?>>21+ ພັນສາ</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-end space-x-2">
                    <button type="submit" class="btn btn-primary w-full"><i class="fas fa-search"></i> ຄົ້ນຫາ</button>
                    <a href="<?= $base_url ?>monks/" class="btn btn-secondary w-full"><i class="fas fa-redo"></i> ລ້າງ</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Monks Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 border-b">
            <p class="text-gray-600">ພົບຂໍ້ມູນທັງໝົດ <span class="font-bold text-gray-800"><?= count($monks) ?></span> ລາຍການ</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຮູບ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ ແລະ ນາມສະກຸນ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ພັນສາ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ / ແຂວງ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($monks) > 0): ?>
                        <?php foreach ($monks as $monk): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="<?= $base_url . htmlspecialchars($monk['photo'] ?? 'uploads/monks/default.png') ?>" alt="Photo" class="h-10 w-10 rounded-full object-cover">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($monk['prefix'] ?? '') . ' ' . htmlspecialchars($monk['name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($monk['pansa']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($monk['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['province_name'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($monk['status'] == 'active'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">ບວດຢູ່</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">ສິກແລ້ວ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3"><i class="fas fa-eye"></i></a>
                                    <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">ບໍ່ພົບຂໍ້ມູນ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const provinceSelect = document.getElementById('province_id');
    const districtSelect = document.getElementById('district_id');
    const templeSelect = document.getElementById('temple_id');
    const districtContainer = document.getElementById('district-container');
    const templeContainer = document.getElementById('temple-container');
    const form = document.getElementById('filterForm');

    const baseUrl = '<?= $base_url ?>';
    const initialDistrictId = '<?= $district_filter ?? '' ?>';
    const initialTempleId = '<?= $temple_filter ?? '' ?>';

    function setSelectLoading(selectElement, message) {
        selectElement.innerHTML = `<option value="" class="loading-indicator">${message}</option>`;
        selectElement.disabled = true;
    }

    function populateSelect(selectElement, data, defaultOptionText, valueField, textField, selectedValue) {
        selectElement.innerHTML = `<option value="">-- ${defaultOptionText} --</option>`;
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueField];
            option.textContent = item[textField];
            if (selectedValue && item[valueField] == selectedValue) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });
        selectElement.disabled = false;
    }

    async function fetchDistricts(provinceId) {
        if (!provinceId) {
            districtContainer.style.display = 'none';
            templeContainer.style.display = 'none';
            districtSelect.innerHTML = '';
            templeSelect.innerHTML = '';
            return;
        }
        districtContainer.style.display = 'block';
        templeContainer.style.display = 'none';
        setSelectLoading(districtSelect, 'ກຳລັງໂຫຼດເມືອງ...');
        
        try {
            const response = await fetch(`${baseUrl}api/get-districts.php?province_id=${provinceId}`);
            const data = await response.json();
            if (data.success) {
                populateSelect(districtSelect, data.districts, 'ທຸກເມືອງ', 'district_id', 'district_name', initialDistrictId);
                if (initialDistrictId) {
                    fetchTemples(initialDistrictId);
                }
            } else {
                districtSelect.innerHTML = '<option value="">ບໍ່ພົບຂໍ້ມູນເມືອງ</option>';
            }
        } catch (error) {
            console.error('Error fetching districts:', error);
            districtSelect.innerHTML = '<option value="">ເກີດຂໍ້ຜິດພາດ</option>';
        }
    }

    async function fetchTemples(districtId) {
        if (!districtId) {
            templeContainer.style.display = 'none';
            templeSelect.innerHTML = '';
            return;
        }
        templeContainer.style.display = 'block';
        setSelectLoading(templeSelect, 'ກຳລັງໂຫຼດວັດ...');

        try {
            const response = await fetch(`${baseUrl}api/get-temples.php?district_id=${districtId}`);
            const data = await response.json();
            if (data.success) {
                populateSelect(templeSelect, data.temples, 'ທຸກວັດ', 'id', 'name', initialTempleId);
            } else {
                templeSelect.innerHTML = '<option value="">ບໍ່ພົບຂໍ້ມູນວັດ</option>';
            }
        } catch (error) {
            console.error('Error fetching temples:', error);
            templeSelect.innerHTML = '<option value="">ເກີດຂໍ້ຜິດພາດ</option>';
        }
    }

    if (provinceSelect) {
        provinceSelect.addEventListener('change', function() {
            fetchDistricts(this.value);
        });
    }

    if (districtSelect) {
        districtSelect.addEventListener('change', function() {
            fetchTemples(this.value);
        });
    }
    
    if (templeSelect) {
        templeSelect.addEventListener('change', function() {
            // เมื่อเลือกวัด ให้ส่งฟอร์มเพื่อกรองข้อมูลทันที
            if (this.value) {
                form.submit();
            }
        });
    }

    // Initial load if province is pre-selected
    if (provinceSelect && provinceSelect.value) {
        fetchDistricts(provinceSelect.value);
    }
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>