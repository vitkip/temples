<?php
$page_title = 'ຈັດການວັດ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบบทบาทผู้ใช้
$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// อนุญาตเฉพาะ superadmin, admin, และ province_admin
if (!in_array($user_role, ['superadmin', 'admin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query based on user role
$where_conditions = [];
$params = [];

// กำหนดเงื่อนไขตามสิทธิ์ผู้ใช้
if ($user_role === 'admin') {
    // admin วัด: เห็นเฉพาะวัดของตัวเอง
    $where_conditions[] = "t.id = ?";
    $params[] = $user_temple_id;
} elseif ($user_role === 'province_admin') {
    // province_admin: เห็นเฉพาะวัดในแขวงที่ตัวเองดูแล
    $where_conditions[] = "t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";
    $params[] = $user_id;
}
// superadmin: เห็นทุกวัด (ไม่มีเงื่อนไขเพิ่มเติม)

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $where_conditions[] = "(t.name LIKE ? OR t.address LIKE ? OR t.abbot_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($province)) {
    if ($user_role === 'province_admin') {
        // ตรวจสอบว่า province_admin มีสิทธิ์ดูแลแขวงนี้หรือไม่
        $check_province = $pdo->prepare("
            SELECT COUNT(*) FROM user_province_access upa 
            JOIN provinces p ON upa.province_id = p.province_id 
            WHERE upa.user_id = ? AND p.province_name = ?
        ");
        $check_province->execute([$user_id, $province]);
        if ($check_province->fetchColumn() > 0) {
            $where_conditions[] = "p.province_name = ?";
            $params[] = $province;
        }
    } else {
        $where_conditions[] = "p.province_name = ?";
        $params[] = $province;
    }
}

if (!empty($status)) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status;
}

// รับค่า district จาก GET
$district = isset($_GET['district']) ? (int)$_GET['district'] : '';

// ดึงรายชื่อเมืองตามจังหวัด
$districts = [];
if (!empty($province)) {
    $district_stmt = $pdo->prepare("
        SELECT d.district_id, d.district_name 
        FROM districts d
        JOIN provinces p ON d.province_id = p.province_id
        WHERE p.province_name = ?
        ORDER BY d.district_name
    ");
    $district_stmt->execute([$province]);
    $districts = $district_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// เพิ่มเงื่อนไขเลือกเมือง
if (!empty($district)) {
    $where_conditions[] = "t.district_id = ?";
    $params[] = $district;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query หลักสำหรับดึงข้อมูลวัด
$base_query = "
    FROM temples t 
    LEFT JOIN provinces p ON t.province_id = p.province_id 
    $where_clause
";

// Count total for pagination
$count_query = "SELECT COUNT(*) " . $base_query;
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_temples = $count_stmt->fetchColumn();

$total_pages = ceil($total_temples / $limit);

// Get temples with pagination
$query = "
    SELECT 
        t.*,
        p.province_name,
        p.province_code
    " . $base_query . "
    ORDER BY t.name ASC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$temples = $stmt->fetchAll();

// Get provinces for filter dropdown
$provinces = [];
if ($user_role === 'superadmin') {
    // superadmin เห็นทุกแขวง
    $province_stmt = $pdo->query("SELECT DISTINCT province_name FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($user_role === 'province_admin') {
    // province_admin เห็นเฉพาะแขวงที่ตัวเองดูแล
    $province_stmt = $pdo->prepare("
        SELECT DISTINCT p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($user_role === 'admin') {
    // admin วัด เห็นเฉพาะแขวงของวัดตัวเอง
    $province_stmt = $pdo->prepare("
        SELECT DISTINCT p.province_name 
        FROM temples t 
        JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.id = ?
    ");
    $province_stmt->execute([$user_temple_id]);
    $provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Check edit permissions - เพิ่ม province_admin
$can_edit = in_array($user_role, ['superadmin', 'admin', 'province_admin']);
$can_add_temple = in_array($user_role, ['superadmin', 'province_admin']); // ให้ province_admin เพิ่มวัดได้
$can_delete_temple = ($user_role === 'superadmin');
?>

<!-- เพิ่ม link เพื่อนำเข้า monk-style.css -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
<link rel="stylesheet" href="<?= $base_url ?>assets/css/temples-style.css">

<div class="page-container">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-6 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-gopuram"></i>
                    </div>
                    ຈັດການວັດ
                    <?php if ($user_role === 'province_admin'): ?>
                        <span class="text-sm font-normal text-amber-700 ml-2">(ແຂວງທີ່ຮັບຜິດຊອບ)</span>
                    <?php elseif ($user_role === 'admin'): ?>
                        <span class="text-sm font-normal text-amber-700 ml-2">(ວັດຂອງທ່ານ)</span>
                    <?php endif; ?>
                </h1>
                <p class="text-sm text-amber-700 mt-1">
                    <?php if ($user_role === 'superadmin'): ?>
                        ເບິ່ງແລະຈັດການຂໍ້ມູນວັດທັງໝົດ
                    <?php elseif ($user_role === 'province_admin'): ?>
                        ເບິ່ງແລະຈັດການວັດໃນແຂວງທີ່ທ່ານຮັບຜິດຊອບ
                    <?php elseif ($user_role === 'admin'): ?>
                        ເບິ່ງແລະຈັດການຂໍ້ມູນວັດຂອງທ່ານ
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex space-x-2">
                <?php if ($can_add_temple): ?>
                <a href="<?= $base_url ?>temples/add.php" class="btn-primary flex items-center gap-2">
                    <i class="fas fa-plus"></i> ເພີ່ມວັດໃໝ່
                </a>
                <?php endif; ?>
                
                <!-- เพิ่มปุ่มส่งออก -->
                <div class="relative">
                    <button type="button" id="exportBtn" class="btn-primary  flex items-center gap-2">
                        <i class="fas fa-download"></i> ສົ່ງອອກ
                    </button>
                    <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-md overflow-hidden z-10 border border-gray-200">
                        <div class="py-1">
                            <a href="<?= $base_url ?>reports/temples_pdf.php?<?= http_build_query($_GET) ?>" class="block px-4 py-3 text-sm hover:bg-gray-100 flex items-center">
                                <i class="far fa-file-pdf text-red-500 mr-2 w-5"></i> ສົ່ງອອກເປັນ PDF
                            </a>
                            <hr class="border-gray-100">
                            <a href="<?= $base_url ?>reports/temples_excel.php?<?= http_build_query($_GET) ?>" class="block px-4 py-3 text-sm hover:bg-gray-100 flex items-center">
                                <i class="far fa-file-excel text-green-600 mr-2 w-5"></i> ສົ່ງອອກເປັນ Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- แสดงข้อมูลสถิติ -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100">ວັດທັງໝົດ</p>
                        <p class="text-2xl font-bold"><?= $total_temples ?></p>
                    </div>
                    <i class="fas fa-gopuram text-3xl text-blue-200"></i>
                </div>
            </div>
            
            <?php if (count($provinces) > 0): ?>
            <div class="card p-4 bg-gradient-to-r from-green-500 to-green-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">ແຂວງທີ່ດູແລ</p>
                        <p class="text-2xl font-bold"><?= count($provinces) ?></p>
                    </div>
                    <i class="fas fa-map-marker-alt text-3xl text-green-200"></i>
                </div>
            </div>
            <?php endif; ?>
            
            <?php
            // นับจำนวนวัดที่ active
            $active_count_query = "
                SELECT COUNT(*) 
                FROM temples t 
                LEFT JOIN provinces p ON t.province_id = p.province_id 
                WHERE t.status = 'active'
            ";

            // เพิ่มเงื่อนไขตามสิทธิ์ผู้ใช้
            if ($user_role === 'admin') {
                $active_count_query .= " AND t.id = ?";
                $active_params = [$user_temple_id];
            } elseif ($user_role === 'province_admin') {
                $active_count_query .= " AND t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";
                $active_params = [$user_id];
            } else {
                $active_params = [];
            }

            // เพิ่มเงื่อนไขการค้นหา
            if (!empty($search)) {
                $active_count_query .= " AND (t.name LIKE ? OR t.address LIKE ? OR t.abbot_name LIKE ?)";
                $active_params[] = "%{$search}%";
                $active_params[] = "%{$search}%";
                $active_params[] = "%{$search}%";
            }

            // เพิ่มเงื่อนไขแขวง
            if (!empty($province)) {
                $active_count_query .= " AND p.province_name = ?";
                $active_params[] = $province;
            }

            // เพิ่มเงื่อนไขเมือง
            if (!empty($district)) {
                $active_count_query .= " AND t.district_id = ?";
                $active_params[] = $district;
            }

            $active_count_stmt = $pdo->prepare($active_count_query);
            $active_count_stmt->execute($active_params);
            $active_temples = $active_count_stmt->fetchColumn();
            ?>
            <div class="card p-4 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-amber-100">ວັດທີ່ເປີດໃຊ້ງານ</p>
                        <p class="text-2xl font-bold"><?= $active_temples ?></p>
                    </div>
                    <i class="fas fa-check-circle text-3xl text-amber-200"></i>
                </div>
            </div>
        </div>

        <!-- เพิ่มเมนูสำหรับ province_admin -->
        <?php if ($user_role === 'province_admin'): ?>
        <div class="card p-4 mb-6 bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-200">
            <h3 class="text-lg font-semibold text-indigo-800 mb-3 flex items-center">
                <i class="fas fa-tools mr-2"></i>
                ເຄື່ອງມືການຈັດການແຂວງ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="<?= $base_url ?>districts/index.php" class="flex items-center p-3 bg-white rounded-lg border border-indigo-200 hover:border-indigo-400 hover:shadow-md transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-city text-indigo-600"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">ຈັດການເມືອງ</h4>
                        <p class="text-sm text-gray-500">ເພີ່ມ, ແກ້ໄຂ, ລຶບເມືອງໃນແຂວງ</p>
                    </div>
                </a>
                <a href="<?= $base_url ?>temples/add.php" class="flex items-center p-3 bg-white rounded-lg border border-indigo-200 hover:border-indigo-400 hover:shadow-md transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-plus text-indigo-600"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">ເພີ່ມວັດໃໝ່</h4>
                        <p class="text-sm text-gray-500">ສ້າງວັດໃໝ່ໃນແຂວງຂອງທ່ານ</p>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card filter-section p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ຄົ້ນຫາ</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search) ?>" 
                        placeholder="ຊື່ວັດ, ທີ່ຢູ່, ເຈົ້າອາວາດ..." 
                        class="form-input w-full"
                    >
                </div>
                
                <?php if (count($provinces) > 0): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວງ</label>
                    <select name="province" id="province-select" class="form-select w-full">
                        <option value="">-- ທຸກແຂວງ --</option>
                        <?php foreach($provinces as $prov): ?>
                        <option value="<?= htmlspecialchars($prov) ?>" <?= $province === $prov ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- เพิ่มฟิลด์เลือกเมือง (district) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ເມືອງ</label>
                    <select name="district" id="district-select" class="form-select w-full" <?= empty($province) ? 'disabled' : '' ?>>
                        <option value="">-- ທຸກເມືອງ --</option>
                        <?php foreach($districts as $d): ?>
                        <option value="<?= $d['district_id'] ?>" <?= $district == $d['district_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['district_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ສະຖານະ</label>
                    <select name="status" class="form-select w-full">
                        <option value="">-- ທຸກສະຖານະ --</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                    </select>
                </div>
                
                <div class="flex items-end col-span-1 md:col-span-4">
                    <button type="submit" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg mr-2 transition">
                        <i class="fas fa-filter mr-1"></i> ຕັງຄ່າຟິວເຕີ
                    </button>
                    
                    <a href="<?= $base_url ?>temples/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                        <i class="fas fa-sync-alt mr-1"></i> ລ້າງ
                    </a>
                </div>
            </form>
        </div>

        <!-- Temples List -->
        <div class="card overflow-hidden">
            <?php if (count($temples) > 0): ?>
            <!-- Responsive Table: Desktop view -->
            <table class="w-full data-table hidden md:table">
            <thead class="table-header">
                <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ແຂວງ/ເມືອງ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ເຈົ້າອາວາດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($temples as $temple): ?>
                <tr class="table-row hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <div class="category-icon mr-3">
                            <i class="fas fa-gopuram"></i>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900"><?= htmlspecialchars($temple['name']) ?></div>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($temple['address'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500">
                        <div class="font-medium"><?= htmlspecialchars($temple['province_name'] ?? $temple['province'] ?? '-') ?></div>
                        <div class="text-sm"><?= htmlspecialchars($temple['district'] ?? '-') ?></div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500"><?= htmlspecialchars($temple['abbot_name'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <?php if($can_edit && ($user_role === 'superadmin' || ($user_role === 'admin' && $temple['id'] == $user_temple_id) || ($user_role === 'province_admin'))): ?>
                    <!-- ปุ่มสลับสถานะแบบ toggle switch -->
                    <label class="status-toggle relative inline-block">
                        <input type="checkbox" 
                        class="temple-status-toggle hidden" 
                        data-id="<?= $temple['id'] ?>" 
                        data-name="<?= htmlspecialchars($temple['name']) ?>"
                        <?= $temple['status'] === 'active' ? 'checked' : '' ?>>
                        <span class="toggle-slider <?= $temple['status'] === 'active' ? 'bg-amber-500' : 'bg-gray-300' ?>">
                        <span class="status-text">
                            <?php if($temple['status'] === 'active'): ?>
                            <i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ
                            <?php else: ?>
                            <i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ
                            <?php endif; ?>
                        </span>
                        </span>
                    </label>
                    <?php else: ?>
                    <?php if($temple['status'] === 'active'): ?>
                        <span class="status-badge status-active">
                        <i class="fas fa-circle text-xs mr-1"></i>
                        ເປີດໃຊ້ງານ
                        </span>
                    <?php else: ?>
                        <span class="status-badge bg-gray-100 text-gray-600 border border-gray-200">
                        <i class="fas fa-circle-notch text-xs mr-1"></i>
                        ປິດໃຊ້ງານ
                        </span>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-3">
                    <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" class="text-amber-600 hover:text-amber-800" title="ເບິ່ງລາຍລະອຽດ">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php if ($can_edit && ($user_role === 'superadmin' || ($user_role === 'admin' && $temple['id'] == $user_temple_id) || ($user_role === 'province_admin'))): ?>
                    <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" 
                       class="flex items-center text-blue-600 hover:text-blue-800">
                        <i class="fas fa-edit mr-1"></i> <span class="text-xs">ແກ້ໄຂ</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($can_delete_temple): ?>
                    <a href="javascript:void(0)" class="text-red-500 hover:text-red-700 delete-temple" 
                       data-id="<?= $temple['id'] ?>" 
                       data-name="<?= htmlspecialchars($temple['name']) ?>"
                       title="ລຶບວັດ">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                    </div>
                </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
            
            <!-- Mobile view: Card-based layout -->
            <div class="md:hidden">
            <?php foreach($temples as $temple): ?>
            <div class="border-b p-4">
                <div class="flex justify-between items-start mb-2">
                <h3 class="font-bold text-gray-900 flex items-center">
                    <div class="category-icon mr-2" style="width: 1.5rem; height: 1.5rem;">
                        <i class="fas fa-gopuram text-xs"></i>
                    </div>
                    <?= htmlspecialchars($temple['name']) ?>
                </h3>
                <div>
                    <?php if($temple['status'] === 'active'): ?>
                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                        <i class="fas fa-circle text-xs"></i> ເປີດໃຊ້ງານ
                    </span>
                    <?php else: ?>
                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                        <i class="fas fa-circle-notch text-xs"></i> ປິດໃຊ້ງານ
                    </span>
                    <?php endif; ?>
                </div>
                </div>
                
                <div class="text-sm text-gray-600 mb-1">
                <i class="fas fa-map-marker-alt mr-1"></i> 
                <?= htmlspecialchars($temple['province_name'] ?? $temple['province'] ?? '-') ?>
                <?php if (!empty($temple['district'])): ?>
                    , <?= htmlspecialchars($temple['district']) ?>
                <?php endif; ?>
                </div>
                
                <?php if (!empty($temple['abbot_name'])): ?>
                <div class="text-sm text-gray-600 mb-3">
                <i class="fas fa-user mr-1"></i> 
                <?= htmlspecialchars($temple['abbot_name']) ?>
                </div>
                <?php endif; ?>
                
                <div class="flex justify-between items-center mt-2">
                <!-- Status toggle for mobile -->
                <?php if($can_edit && ($user_role === 'superadmin' || ($user_role === 'admin' && $temple['id'] == $user_temple_id))): ?>
                <div class="flex-grow">
                    <label class="status-toggle relative inline-block">
                    <input type="checkbox" 
                        class="temple-status-toggle hidden" 
                        data-id="<?= $temple['id'] ?>" 
                        data-name="<?= htmlspecialchars($temple['name']) ?>"
                        <?= $temple['status'] === 'active' ? 'checked' : '' ?>>
                    <span class="toggle-slider <?= $temple['status'] === 'active' ? 'bg-amber-500' : 'bg-gray-300' ?>">
                        <span class="status-text">
                        <?php if($temple['status'] === 'active'): ?>
                            <i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ
                        <?php else: ?>
                            <i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ
                        <?php endif; ?>
                        </span>
                    </span>
                    </label>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="flex space-x-4">
                    <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" 
                       class="flex items-center text-amber-600 hover:text-amber-800">
                    <i class="fas fa-eye mr-1"></i> <span class="text-xs">ເບິ່ງ</span>
                    </a>
                    
                    <?php if ($can_edit && ($user_role === 'superadmin' || ($user_role === 'admin' && $temple['id'] == $user_temple_id) || ($user_role === 'province_admin'))): ?>
                    <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" 
                       class="flex items-center text-blue-600 hover:text-blue-800">
                    <i class="fas fa-edit mr-1"></i> <span class="text-xs">ແກ້ໄຂ</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($can_delete_temple): ?>
                    <a href="javascript:void(0)" 
                       class="flex items-center text-red-500 hover:text-red-700 delete-temple" 
                       data-id="<?= $temple['id'] ?>" 
                       data-name="<?= htmlspecialchars($temple['name']) ?>">
                    <i class="fas fa-trash mr-1"></i> <span class="text-xs">ລຶບ</span>
                    </a>
                    <?php endif; ?>
                </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="px-4 md:px-6 py-4 bg-white border-t border-gray-200">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <div class="text-sm text-gray-500 mb-2 md:mb-0">
                ສະແດງ <?= count($temples) ?> ຈາກທັງໝົດ <?= $total_temples ?> ວັດ
                </div>
                <div class="flex flex-wrap gap-1 justify-center md:justify-end">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php 
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a 
                    href="?<?= $query_string ?>" 
                    class="<?= $i === $page ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> px-3 py-1 rounded text-center min-w-[2rem]"
                    >
                    <?= $i ?>
                    </a>
                <?php endfor; ?>
                </div>
            </div>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="p-6 text-center">
                <div class="text-gray-500 mb-4">
                    <i class="fas fa-gopuram text-4xl text-gray-300 mb-2"></i>
                    <p>ບໍ່ພົບລາຍການວັດ</p>
                    <?php if ($user_role === 'province_admin'): ?>
                    <p class="text-sm">ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບເພື່ອມອບໝາຍແຂວງໃຫ້ທ່ານ</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="card bg-white max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການລຶບຂໍ້ມູນ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບວັດ <span id="deleteTempleNameDisplay" class="font-medium"></span> ແທ້ບໍ່?</p>
            <p class="text-sm text-red-600 mt-2">ຂໍ້ມູນທີ່ຖືກລຶບບໍ່ສາມາດກູ້ຄືນໄດ້.</p>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button id="cancelDelete" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <a id="confirmDelete" href="#" class="btn-primary px-4 py-2">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete modal functionality
    const deleteModal = document.getElementById('deleteModal');
    const deleteTempleNameDisplay = document.getElementById('deleteTempleNameDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    
    // Open modal when delete button is clicked
    document.querySelectorAll('.delete-temple').forEach(button => {
        button.addEventListener('click', function() {
            const templeId = this.getAttribute('data-id');
            const templeName = this.getAttribute('data-name');
            
            // Set temple name in modal
            deleteTempleNameDisplay.textContent = templeName;
            
            // Set the confirmation link
            confirmDelete.href = '<?= $base_url ?>temples/delete.php?id=' + templeId;
            
            // Display modal
            deleteModal.classList.remove('hidden');
        });
    });
    
    // Close modal
    document.querySelectorAll('.close-modal, #cancelDelete').forEach(element => {
        element.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
    });
    
    // Status toggle functionality
    const toggles = document.querySelectorAll('.temple-status-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const templeId = this.getAttribute('data-id');
            const templeName = this.getAttribute('data-name');
            const newStatus = this.checked ? 'active' : 'inactive';
            const toggleLabel = this.closest('.status-toggle');
            const slider = toggleLabel.querySelector('.toggle-slider');
            const statusText = toggleLabel.querySelector('.status-text');
            
            // แสดงสถานะกำลังโหลด
            toggleLabel.classList.add('loading');
            
            // ส่งคำขอ Ajax เพื่อเปลี่ยนสถานะ
            fetch('<?= $base_url ?>temples/toggle-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    temple_id: templeId,
                    status: newStatus
                }),
                credentials: 'include'
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    return response.json().then(data => {
                        return { ...data, status: response.status };
                    });
                } else {
                    return response.text().then(text => {
                        return { 
                            success: false, 
                            message: text || 'ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່ກັບເຊີບເວີ', 
                            status: response.status 
                        };
                    });
                }
            })
            .then(data => {
                console.log('Response data:', data);
                toggleLabel.classList.remove('loading');
                
                if (data.success) {
                    // อัปเดต UI
                    if (newStatus === 'active') {
                        slider.classList.remove('bg-gray-300');
                        slider.classList.add('bg-amber-500');
                        statusText.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ';
                    } else {
                        slider.classList.remove('bg-amber-500');
                        slider.classList.add('bg-gray-300');
                        statusText.innerHTML = '<i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ';
                    }
                    
                    showNotification('ອັບເດດສະຖານະວັດ ' + templeName + ' ສຳເລັດແລ້ວ', 'success');
                } else {
                    if (data.status >= 400) {
                        this.checked = !this.checked;
                        showNotification('ເກີດຂໍ້ຜິດພາດ: ' + (data.message || 'ບໍ່ສາມາດອັບເດດສະຖານະໄດ້'), 'error');
                    } else {
                        showNotification('ອັບເດດສະຖານະສຳເລັດແລ້ວ ແຕ່ມີຂໍໜິດພາດບາງຢ່າງ', 'warning');
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                toggleLabel.classList.remove('loading');
                
                // Fallback to form submission
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= $base_url ?>temples/direct-update-status.php';
                form.style.display = 'none';
                
                const templeIdInput = document.createElement('input');
                templeIdInput.name = 'temple_id';
                templeIdInput.value = templeId;
                
                const statusInput = document.createElement('input');
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                const redirectInput = document.createElement('input');
                redirectInput.name = 'redirect';
                redirectInput.value = window.location.href;
                
                form.appendChild(templeIdInput);
                form.appendChild(statusInput);
                form.appendChild(redirectInput);
                document.body.appendChild(form);
                
                showNotification('ກຳລັງໃຊ້ການອັບເດດສຳຮອງ...', 'info');
                setTimeout(() => form.submit(), 500);
            });
        });
    });
    
    // Notification function
    function showNotification(message, type = 'info') {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'fixed top-4 right-4 z-50 flex flex-col space-y-2';
            document.body.appendChild(container);
        }
        
        const notification = document.createElement('div');
        notification.className = `notification ${type} px-4 py-2 rounded shadow-lg flex items-center transition-all transform translate-x-full`;
        
        if (type === 'success') {
            notification.classList.add('bg-green-100', 'border-l-4', 'border-green-500', 'text-green-700');
            notification.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
        } else if (type === 'error') {
            notification.classList.add('bg-red-100', 'border-l-4', 'border-red-500', 'text-red-700');
            notification.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
        } else {
            notification.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500', 'text-blue-700');
            notification.innerHTML = '<i class="fas fa-info-circle mr-2"></i>' + message;
        }
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
            notification.classList.add('translate-x-0');
        }, 10);
        
        setTimeout(() => {
            notification.classList.remove('translate-x-0');
            notification.classList.add('translate-x-full');
            
            setTimeout(() => {
                container.removeChild(notification);
            }, 300);
        }, 3000);
    }

    // เพิ่มการทำงานของปุ่มส่งออก
    const exportBtn = document.getElementById('exportBtn');
    const exportMenu = document.getElementById('exportMenu');

    if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function() {
            exportMenu.classList.toggle('hidden');
        });
        
        // ซ่อนเมนูเมื่อคลิกที่อื่น
        document.addEventListener('click', function(event) {
            if (!exportBtn.contains(event.target) && !exportMenu.contains(event.target)) {
                exportMenu.classList.add('hidden');
            }
        });
    }

    // เพิ่มฟังก์ชันสำหรับโหลดข้อมูลเมือง (district) เมื่อเลือกแขวง
    const provinceSelect = document.getElementById('province-select');
    const districtSelect = document.getElementById('district-select');

    if (provinceSelect && districtSelect) {
        // ฟังก์ชัน loading state
        function showLoadingState(elem, message = 'ກຳລັງໂຫຼດຂໍ້ມູນ...') {
            elem.innerHTML = `<option value="" class="loading-indicator">${message}</option>`;
            elem.disabled = true;
        }
        
        // เมื่อเลือกแขวง (province) ให้โหลดข้อมูลเมือง (district)
        provinceSelect.addEventListener('change', function() {
            const selectedProvince = this.value;
            console.log("เลือกแขวง:", selectedProvince); // debug
            
            // รีเซ็ต dropdown ถ้าไม่ได้เลือกแขวง
            if (!selectedProvince) {
                districtSelect.innerHTML = '<option value="">-- ທຸກເມືອງ --</option>';
                districtSelect.disabled = true;
                return;
            }
            
            // แสดง loading state
            showLoadingState(districtSelect);
            
            const apiUrl = `<?= $base_url ?>api/get-districts-by-province-name.php?province_name=${encodeURIComponent(selectedProvince)}`;
            console.log("กำลังเรียก API:", apiUrl); // debug
            
            // ส่ง request ไปยัง API
            fetch(apiUrl)
                .then(response => {
                    console.log("API Response Status:", response.status);
                    return response.json();
                })
                .then(data => {
                    console.log("API Data:", data); // debug
                    
                    if (data.success) {
                        const options = '<option value="">-- ທຸກເມືອງ --</option>' + 
                            data.districts.map(district => 
                                `<option value="${district.district_id}">${district.district_name}</option>`
                            ).join('');
                        
                        districtSelect.innerHTML = options;
                        districtSelect.disabled = false;
                    } else {
                        console.error('Error loading districts:', data.message);
                        districtSelect.innerHTML = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
                        
                        // แสดง error notification
                        showNotification('ບໍ່ສາມາດໂຫຼດຂໍ້ມູນເມືອງໄດ້: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('API Error:', error);
                    districtSelect.innerHTML = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
                    
                    // แสดง error notification
                    showNotification('ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນເມືອງ', 'error');
                });
        });
        
        // เพิ่ม CSS สำหรับ loading indicator
        const styleTag = document.createElement('style');
        styleTag.innerHTML = `
            .loading-indicator {
                position: relative;
                padding-left: 25px;
            }
            .loading-indicator:before {
                content: "";
                position: absolute;
                left: 0;
                top: 50%;
                width: 15px;
                height: 15px;
                margin-top: -7px;
                border: 2px solid #D4A762;
                border-radius: 50%;
                border-top-color: transparent;
                animation: loader-spin 0.6s linear infinite;
            }
            @keyframes loader-spin {
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(styleTag);
    }
});

</script>

<?php require_once '../includes/footer.php'; ?>