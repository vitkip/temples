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

// Pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$records_per_page = 20; // จำนวนรายการต่อหน้า

// เริ่มสร้าง query สำหรับนับจำนวนรายการทั้งหมด
$count_params = [];
$count_query = "SELECT COUNT(*) as total 
                FROM monks m 
                LEFT JOIN temples t ON m.temple_id = t.id 
                LEFT JOIN provinces p ON t.province_id = p.province_id
                WHERE 1=1";

// เริ่มสร้าง query สำหรับดึงข้อมูล
$params = [];
$query = "SELECT m.*, t.name as temple_name, t.province_id, p.province_name 
          FROM monks m 
          LEFT JOIN temples t ON m.temple_id = t.id 
          LEFT JOIN provinces p ON t.province_id = p.province_id
          WHERE 1=1";

// การกรองตามสิทธิ์ผู้ใช้ - ใช้กับทั้ง count และ data query
$role_condition = "";
if ($user_role === 'admin') {
    $role_condition = " AND m.temple_id = ?";
    $role_params = [$user_temple_id];
} elseif ($user_role === 'province_admin') {
    $role_condition = " AND t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";
    $role_params = [$user_id];
} else {
    $role_params = [];
}

$count_query .= $role_condition;
$query .= $role_condition;
$count_params = array_merge($count_params, $role_params);
$params = array_merge($params, $role_params);

// การกรองจากฟอร์ม - ใช้กับทั้ง count และ data query
$filter_conditions = "";
$filter_params = [];

if ($province_filter) {
    $filter_conditions .= " AND t.province_id = ?";
    $filter_params[] = $province_filter;
}
if ($district_filter) {
    $filter_conditions .= " AND t.district_id = ?";
    $filter_params[] = $district_filter;
}
if ($temple_filter) {
    $filter_conditions .= " AND m.temple_id = ?";
    $filter_params[] = $temple_filter;
}
if ($search_term) {
    $filter_conditions .= " AND (m.name LIKE ? OR m.lay_name LIKE ?)";
    $filter_params[] = "%$search_term%";
    $filter_params[] = "%$search_term%";
}
if ($status_filter) {
    $filter_conditions .= " AND m.status = ?";
    $filter_params[] = $status_filter;
}
if ($pansa_filter) {
    switch ($pansa_filter) {
        case '0-5': $filter_conditions .= " AND m.pansa BETWEEN 0 AND 5"; break;
        case '6-10': $filter_conditions .= " AND m.pansa BETWEEN 6 AND 10"; break;
        case '11-20': $filter_conditions .= " AND m.pansa BETWEEN 11 AND 20"; break;
        case '21+': $filter_conditions .= " AND m.pansa > 20"; break;
    }
}

// เพิ่ม filter conditions ลงใน queries
$count_query .= $filter_conditions;
$query .= $filter_conditions;
$count_params = array_merge($count_params, $filter_params);
$params = array_merge($params, $filter_params);

// นับจำนวนรายการทั้งหมด
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Ensure page doesn't exceed available pages and calculate offset
if ($total_records > 0 && $page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $records_per_page;

// เพิ่ม ORDER BY และ LIMIT ลงใน data query
$query .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$monks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลสำหรับ dropdown
$provinces = [];
$temples = [];

if ($user_role === 'superadmin') {
    $provinces = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name")->fetchAll(PDO::FETCH_ASSOC);
    $temples = $pdo->query("SELECT t.id, t.name, p.province_name FROM temples t LEFT JOIN provinces p ON t.province_id = p.province_id WHERE t.status = 'active' ORDER BY t.name")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role === 'province_admin') {
    $stmt = $pdo->prepare("SELECT p.province_id, p.province_name FROM provinces p JOIN user_province_access upa ON p.province_id = upa.province_id WHERE upa.user_id = ? ORDER BY p.province_name");
    $stmt->execute([$user_id]);
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT t.id, t.name, p.province_name FROM temples t LEFT JOIN provinces p ON t.province_id = p.province_id JOIN user_province_access upa ON t.province_id = upa.province_id WHERE upa.user_id = ? AND t.status = 'active' ORDER BY t.name");
    $stmt->execute([$user_id]);
    $temples = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$can_add = in_array($user_role, ['superadmin', 'admin', 'province_admin']);
$can_edit = in_array($user_role, ['superadmin', 'admin', 'province_admin']);
$can_export = in_array($user_role, ['superadmin', 'admin', 'province_admin']);
?>


<!-- เพิ่ม CSS นี้ในส่วนหัวของไฟล์ หรือในไฟล์ CSS แยก -->
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/style-monks.css">

<style>
/* Pagination Styles */
.pagination-container {
    background: white;
    border-top: 1px solid #d97706;
}

.pagination-nav a, .pagination-nav span {
    transition: all 0.2s ease;
}

.pagination-nav a:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(217, 119, 6, 0.1);
}

.pagination-nav .current-page {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    font-weight: 600;
}

@media (max-width: 640px) {
    .pagination-nav {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<!-- ปรับคลาส HTML เพื่อใช้สไตล์ใหม่ -->
<div class="page-container bg-temple-pattern">
  <!-- ส่วนหัวของหน้า -->
  <div class="header-section flex justify-between items-center mb-8">
    <div>
      <h1 class="header-title">
        <i class="fas fa-pray text-amber-700"></i> ຈັດການພະສົງ
      </h1>
      <p class="text-sm text-amber-800 mt-1">ຈັດການຂໍໍາູນທັງໝົດຂອງພະສົງ</p>
      <?php if ($user_role === 'province_admin'): ?>
        <p class="text-xs text-amber-600 mt-1">
          <i class="fas fa-map-marker-alt mr-1"></i>
          ສະແດງພະສົງໃນແຂວງທີ່ທ່ານຮັບຜິດຊອບເທົ່ານັ້ນ
        </p>
      <?php elseif ($user_role === 'admin'): ?>
        <p class="text-xs text-amber-600 mt-1">
          <i class="fas fa-place-of-worship mr-1"></i>
          ສະແດງພະສົງໃນວັດຂອງທ່ານເທົ່ານັ້ນ
        </p>
      <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-3">
      <!-- ปุ่มส่งออก PDF -->
      <?php if ($can_export): ?>
      <a href="../reports/generate_pdf_monks.php?<?= http_build_query($_GET) ?>" target="_blank" 
         class="btn btn-secondary">
        <i class="fas fa-file-pdf text-amber-700"></i> ສົ່ງອອກ PDF
      </a>
      
      <!-- ปุ่มส่งออก Excel -->
      <a href="../reports/generate_excel_monks.php?<?= http_build_query($_GET) ?>" 
         class="btn btn-secondary">
        <i class="fas fa-file-excel text-amber-700"></i> ສົ່ງອອກ Excel
      </a>
      <?php endif; ?>
      
      <?php if ($can_edit): ?>
      <!-- ปุ่มเพิ่มพระสงฆ์ใหม่ -->
      <a href="<?= $base_url ?>monks/add.php" 
         class="btn btn-primary">
        <i class="fas fa-plus-circle"></i> ເພີ່ມພະສົງໃໝ່
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ส่วนตัวกรอง -->
  <div class="filter-section">
    <div class="filter-header">
      <h2 class="filter-title">
        <i class="fas fa-filter text-amber-700 mr-2"></i> ຕົວກອງຂໍໍາູນ
      </h2>
    </div>
    <div class="p-6">
      <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 form-grid">
        <!-- ค้นหา -->
        <div class="xl:col-span-2">
          <label for="search" class="form-label">
            <i class="fas fa-search text-amber-700 mr-1"></i> ຄົ້ນຫາ
          </label>
          <input type="text" name="search" id="search" 
                 value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                 class="form-select" placeholder="ຄົ້ນຫາຊື່ພະ, ຊື່ແຜ່ນດິນ...">
        </div>

        <?php if ($user_role === 'superadmin' || $user_role === 'province_admin'): ?>
        <!-- แสดงตัวกรองแขวงเฉพาะ superadmin -->
        <div>
          <label for="province_id" class="form-label">
            <i class="fas fa-map-marker-alt text-amber-700 mr-1"></i> ແຂວງ
          </label>
            <select name="province_id" id="province_id" class="form-select province-select" onchange="loadDistricts(this.value)">
            <option value="">-- ທຸກແຂວງ --</option>
            <?php foreach ($provinces as $province): ?>
            <option value="<?= $province['province_id'] ?>" <?= isset($_GET['province_id']) && $_GET['province_id'] == $province['province_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($province['province_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
         <!-- เพิ่มส่วนเลือกเมือง -->
        <div id="district-container" style="<?= isset($_GET['province_id']) && !empty($_GET['province_id']) ? '' : 'display:none;' ?>">
          <label for="district_id" class="form-label">
            <i class="fas fa-map text-amber-700 mr-1"></i> ເມືອງ
          </label>
          <select name="district_id" id="district_id" class="form-select district-select" onchange="loadTemplesByDistrict(this.value)">
            <option value="">-- ທຸກເມືອງ --</option>
            <!-- จะเติมด้วย JavaScript -->
          </select>
        </div>
        <?php endif; ?>
     
        <?php if ($user_role === 'superadmin' || $user_role === 'province_admin'): ?>
        <!-- วัด สำหรับ superadmin และ province_admin -->
        <div>
          <label for="temple_id" class="form-label">
            <i class="fas fa-place-of-worship text-amber-700 mr-1"></i> ວັດ
          </label>
          <select name="temple_id" id="temple_id" class="form-select temple-select">
            <option value="">-- ທຸກວັດ --</option>
            <?php foreach ($temples as $temple): ?>
            <option value="<?= $temple['id'] ?>" <?= isset($_GET['temple_id']) && $_GET['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($temple['name']) ?>
              <?php if ($user_role === 'superadmin'): ?>
              <small>(<?= htmlspecialchars($temple['province_name']) ?>)</small>
              <?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <!-- ສະຖານະ (ทุกบทบาทใช้ได้) -->
        <div>
          <label for="status" class="form-label">
            <i class="fas fa-info-circle text-amber-700 mr-1"></i> ສະຖານະ
          </label>
          <select name="status" id="status" class="form-select">
            <option value="">-- ທຸກສະຖານະ --</option>
            <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>ຍັງບວດຢູ່</option>
            <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
          </select>
        </div>

        <!-- ປະເພດ (ทุกบทบาทใช้ได้) -->
        <div>
          <label for="prefix" class="form-label">
            <i class="fas fa-user-circle text-amber-700 mr-1"></i> ປະເພດ
          </label>
          <select name="prefix" id="prefix" class="form-select">
            <option value="">-- ທຸກປະເພດ --</option>
            <option value="ພຣະ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
            <option value="ຄຸນແມ່ຂາວ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
            <option value="ສ.ນ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
            <option value="ສັງກະລີ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສັງກະລี' ? 'selected' : '' ?>>ສັງກະລີ</option>
          </select>
        </div>

        <!-- ແຂວງເກີດ (ทุกบทบาทใช้ได้) -->
        <div>
          <label for="birth_province" class="form-label">
            <i class="fas fa-child text-amber-700 mr-1"></i> ແຂວງເກີດ
          </label>
          <select name="birth_province" id="birth_province" class="form-select">
            <option value="">-- ທຸກແຂວງ --</option>
            <?php 
            $birthProvinces = [
              'ວຽງຈັນ', 'ຫຼວງພະບາງ', 'ສະຫວັນນະເຂດ', 'ຈໍາປາສັກ', 'ອຸດົມໄຊ', 'ບໍ່ແກ້ວ',
              'ສາລະວັນ', 'ເຊກອງ', 'ອັດຕະປື', 'ຜົ້ງສາລີ', 'ຫົວພັນ', 'ຄໍາມ່ວນ', 'ບໍລິຄໍາໄຊ',
              'ຫຼວງນ້ຳທາ', 'ໄຊຍະບູລີ', 'ໄຊສົມບູນ', 'ຊຽງຂວາງ'
            ];
            foreach ($birthProvinces as $province): 
            ?>
            <option value="<?= $province ?>" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === $province ? 'selected' : '' ?>>
              <?= $province ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- ຈໍານວນພັນສາ -->
        <div>
          <label for="pansa" class="form-label">
            <i class="fas fa-calendar-alt text-amber-700 mr-1"></i> ພັນສາ
          </label>
          <select name="pansa" id="pansa" class="form-select">
            <option value="">-- ທຸກພັນສາ --</option>
            <option value="0-5" <?= isset($_GET['pansa']) && $_GET['pansa'] === '0-5' ? 'selected' : '' ?>>0-5 ພັນສາ</option>
            <option value="6-10" <?= isset($_GET['pansa']) && $_GET['pansa'] === '6-10' ? 'selected' : '' ?>>6-10 ພັນສາ</option>
            <option value="11-20" <?= isset($_GET['pansa']) && $_GET['pansa'] === '11-20' ? 'selected' : '' ?>>11-20 ພັນສາ</option>
            <option value="21+" <?= isset($_GET['pansa']) && $_GET['pansa'] === '21+' ? 'selected' : '' ?>>21+ ພັນສາ</option>
          </select>
        </div>

        <!-- ປຸ່ມການກະທຳ -->
        <div class="flex items-end gap-2 lg:col-span-1 xl:col-span-2">
          <button type="submit" class="btn btn-primary flex-1">
            <i class="fas fa-search"></i> ຄົ້ນຫາ
          </button>
          <a href="<?= $base_url ?>monks/" class="btn btn-secondary">
            <i class="fas fa-redo"></i>
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- ตรวจสอบว่าเราอยู่หน้า superadmin และแสดงตัวเลือกเพิ่มเติม (เพิ่มไว้หลังส่วนฟิลเตอร์แขวง) -->
  <?php if ($user_role === 'superadmin'): ?>
  <div class="filter-info bg-yellow-50 p-3 rounded-lg border border-yellow-200 mb-4 mt-2">
    <div class="flex items-center text-amber-700">
      <i class="fas fa-info-circle mr-2 text-amber-600"></i>
      <span class="font-medium">ຄຳແນະນຳສຳລັບ Superadmin:</span>
    </div>
    <p class="text-amber-600 text-sm mt-1 ml-6">
      ທ່ານສາມາດເລືອກແຂວງເພື່ອສົ່ງອອກຂໍໍາູນໄດ້ຈາກຕົວກອງແຂວງດ້ານເທິງ. ຖ້າບໍ່ເລືອກແຂວງ ລະບົບຈະສົ່ງອອກທຸກຂໍໍາູນພະສົງທັງໝົດ.
    </p>
  </div>
  <?php endif; ?>
  
  <!-- ตารางรายการพระสงฆ์ -->
  <div class="data-table">
    <?php if (isset($_SESSION['success'])): ?>
    <!-- แสดงข้อความแจ้งเตือนสำเร็จ -->
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4 mx-4 mt-4 rounded-lg">
      <div class="flex">
        <div class="flex-shrink-0">
          <i class="fas fa-check-circle text-green-500 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <!-- แสดงข้อความแจ้งเตือนข้อผิดพลาด -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 mx-4 mt-4 rounded-lg">
      <div class="flex">
        <div class="flex-shrink-0">
          <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
        </div>
        <div class="ml-3">
          <p class="text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- สรุปจำนวนรายการ -->
    <div class="px-4 sm:px-6 py-4 bg-amber-50 border-b border-amber-200">
      <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="text-amber-900">
          <i class="fas fa-users-class mr-2"></i> ພົບຂໍ້ມູນ <span class="font-semibold text-amber-700"><?= $total_records ?></span> ລາຍການ
          <span class="text-sm ml-2">
            (ສະແດງ <?= (($page - 1) * $records_per_page) + 1 ?> - <?= min($page * $records_per_page, $total_records) ?> ຈາກທັງໝົດ <?= $total_records ?> ລາຍການ)
          </span>
          <?php if ($user_role === 'province_admin' && !empty($provinces)): ?>
            <span class="text-xs ml-2">
              (ໃນແຂວງທີ່ຮັບຜິດຊອບ: <?= count($provinces) ?> ແຂວງ)
            </span>
          <?php endif; ?>
        </div>
        <!-- เพิ่มปุ่มส่งออก Excel -->
      <a href="../reports/generate_excel_monks.php?<?= http_build_query($_GET) ?>" 
         class="btn btn-secondary">
        <i class="fas fa-file-excel text-amber-700"></i> ສົ່ງອອກ Excel
      </a>
      </div>
    </div>

    <?php if (count($monks) > 0): ?>
    <!-- ตารางสำหรับหน้าจอใหญ่ (ซ่อนบนมือถือ) -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full">
        <thead class="table-header">
          <tr>
            <th class="px-6 py-3.5 text-left">ຮູບພາບ</th>
            <th class="px-6 py-3.5 text-left">ຊື່ ແລະ ນາມສະກຸນ</th>
            <th class="px-6 py-3.5 text-left">ພັນສາ</th>
            <th class="px-6 py-3.5 text-left">ວັດ / ແຂວງ</th>
            <th class="px-6 py-3.5 text-left">ສະຖານະ</th>
            <th class="px-6 py-3.5 text-left">ຈັດການ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($monks as $monk): ?>
          <tr class="table-row">
            <td class="table-cell">
              <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" 
                     class="monk-image">
              <?php else: ?>
                <div class="monk-placeholder">
                  <i class="fas fa-user"></i>
                </div>
              <?php endif; ?>
            </td>
            <td class="table-cell">
              <div class="font-medium text-amber-900">
                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" class="hover:text-amber-700 transition-colors">
                  <?= htmlspecialchars($monk['prefix'] ?? '') ?> <?= htmlspecialchars($monk['name']) ?>
                </a>
              </div>
              <?php if (!empty($monk['lay_name'])): ?>
              <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="table-cell">
              <div class="text-gray-700"> <?php
                  if (!empty($monk['ordination_date'])) {
                  $ordination = new DateTime($monk['ordination_date']);
                  $now = new DateTime();
                  $years = $ordination->diff($now)->y;
                  echo $years . ' ພັນສາ';
                  } else {
                  echo htmlspecialchars($monk['pansa']) . ' ພັນສາ';
                  }
                  ?> <span class="text-xs"></span></div>
            </td>
            <td class="table-cell">
              <div class="text-gray-700 flex items-center mb-1">
                <i class="fas fa-place-of-worship text-amber-500 mr-1.5 text-xs"></i>
                <?= htmlspecialchars($monk['temple_name'] ?? '-') ?>
              </div>
              <?php if (!empty($monk['province_name'])): ?>
              <div class="text-xs text-gray-500 flex items-center">
                <i class="fas fa-map-marker-alt text-amber-400 mr-1"></i>
                <?= htmlspecialchars($monk['province_name']) ?>
              </div>
              <?php endif; ?>
            </td>
            <td class="table-cell">
              <?php
              $can_edit_monk = ($user_role === 'superadmin') || 
                              ($user_role === 'admin' && $user_temple_id == $monk['temple_id']) ||
                              ($user_role === 'province_admin' && !empty($monk['province_id']) && in_array($monk['province_id'], array_column($provinces, 'province_id')));
              ?>
              <?php if ($can_edit_monk): ?>
                <button type="button" class="toggle-status-btn w-full text-left" data-monk-id="<?= $monk['id'] ?>" data-current-status="<?= $monk['status'] ?>">
                  <?php if($monk['status'] === 'active'): ?>
                    <span class="status-active">
                      <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                      <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                    </span>
                  <?php else: ?>
                    <span class="status-inactive">
                      <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                      <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                    </span>
                  <?php endif; ?>
                </button>
              <?php else: ?>
                <div>
                  <?php if($monk['status'] === 'active'): ?>
                    <span class="status-active">
                      <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                    </span>
                  <?php else: ?>
                    <span class="status-inactive">
                      <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                    </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <td class="table-cell">
              <div class="flex items-center space-x-3">
                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" 
                   class="text-amber-600 hover:text-amber-800 hover:bg-amber-50 p-1.5 rounded-full transition">
                  <i class="fas fa-eye"></i>
                </a>
                
                <?php if ($can_edit_monk): ?>
                <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" 
                   class="text-amber-600 hover:text-amber-800 hover:bg-amber-50 p-1.5 rounded-full transition">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="javascript:void(0)" 
                   class="text-red-600 hover:text-red-800 hover:bg-red-50 p-1.5 rounded-full transition delete-monk" 
                   data-id="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>">
                  <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <!-- การ์ดสำหรับมือถือ (ซ่อนบนหน้าจอใหญ่) -->
    <div class="md:hidden">
      <?php foreach($monks as $monk): ?>
        <div class="p-4 border-b border-amber-200 last:border-b-0">
          <div class="flex items-center gap-3 mb-3">
            <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
              <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" 
                   class="monk-image w-12 h-12">
            <?php else: ?>
              <div class="monk-placeholder w-12 h-12">
                <i class="fas fa-user"></i>
              </div>
            <?php endif; ?>
            
            <div class="flex-1">
              <div class="font-medium text-amber-900 text-lg">
                <?= htmlspecialchars($monk['prefix'] ?? '') ?> <?= htmlspecialchars($monk['name']) ?>
              </div>
              <?php if (!empty($monk['lay_name'])): ?>
                <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-sm mb-3">
            <div>
              <span class="text-gray-500">ພັນສາ:</span> 
              <span class="font-medium"><?= htmlspecialchars($monk['pansa'] ?? '-') ?></span>
            </div>
            <div class="col-span-2">
              <span class="text-gray-500">ວັດ:</span> 
              <span class="font-medium"><?= htmlspecialchars($monk['temple_name'] ?? '-') ?></span>
              <?php if (!empty($monk['province_name'])): ?>
                <div class="text-xs text-gray-400 mt-1">
                  <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($monk['province_name']) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="col-span-2">
              <span class="text-gray-500">ສະຖານະ:</span> 
              <?php
              $can_edit_monk = ($user_role === 'superadmin') || 
                              ($user_role === 'admin' && $user_temple_id == $monk['temple_id']) ||
                              ($user_role === 'province_admin' && !empty($monk['province_id']) && in_array($monk['province_id'], array_column($provinces, 'province_id')));
              ?>
              <?php if ($can_edit_monk): ?>
                <button type="button" class="toggle-status-btn inline-flex" data-monk-id="<?= $monk['id'] ?>" data-current-status="<?= $monk['status'] ?>">
                  <?php if($monk['status'] === 'active'): ?>
                    <span class="status-active">
                      <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                      <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                    </span>
                  <?php else: ?>
                    <span class="status-inactive">
                      <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                      <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                    </span>
                  <?php endif; ?>
                </button>
              <?php else: ?>
                <div class="inline-flex">
                  <?php if($monk['status'] === 'active'): ?>
                    <span class="status-active">
                      <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                    </span>
                  <?php else: ?>
                    <span class="status-inactive">
                      <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                    </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="flex justify-end gap-2 pt-2 border-t border-amber-100">
            <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" 
               class="flex items-center justify-center p-2 px-3 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
              <i class="fas fa-eye mr-2"></i> ເບິ່ງ
            </a>
            
            <?php
            // province_admin (ระดับแขวง) สามารถแก้ไข/ลบได้ถ้าอยู่ในแขวงที่ตัวเองดูแล
            $can_edit_monk = ($user_role === 'superadmin')
              || ($user_role === 'admin' && $user_temple_id == $monk['temple_id'])
              || ($user_role === 'province_admin' && !empty($monk['province_id']) && in_array($monk['province_id'], array_column($provinces, 'province_id')));
            ?>
            <?php if ($can_edit_monk): ?>
            <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" 
               class="flex items-center justify-center p-2 px-3 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
              <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
            </a>
            
            <button type="button"
               class="flex items-center justify-center p-2 px-3 rounded-lg bg-red-50 text-red-700 hover:bg-red-100 transition delete-monk" 
               data-id="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>">
              <i class="fas fa-trash mr-2"></i> ລຶບ
            </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- แสดงข้อความเมื่อไม่พบข้อมูล -->
    <div class="py-8 sm:py-12 px-4 sm:px-8 text-center">
      <div class="bg-amber-50 rounded-xl py-8 sm:py-10 max-w-md mx-auto">
        <i class="fas fa-pray text-4xl sm:text-5xl mb-4 text-amber-300"></i>
        <p class="text-amber-800 mb-4">ບໍ່ມີຂໍ້ມູນພຣະສົງໃນວັດນີ້</p>
        <?php if (!empty($_GET['search']) || !empty($_GET['temple_id']) || !empty($_GET['province_id']) || (isset($_GET['status']) && $_GET['status'] !== 'active')): ?>
        <a href="<?= $base_url ?>monks/" 
           class="inline-block mt-2 text-amber-600 hover:text-amber-800 border border-amber-300 hover:border-amber-400 px-4 py-2 rounded-lg transition">
           <i class="fas fa-redo mr-1"></i> ລຶບຕົວກອງທັງໝົດ
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pagination Navigation -->
  <?php if ($total_pages > 1): ?>
  <div class="bg-white px-4 py-3 border-t border-amber-200 sm:px-6">
    <div class="flex items-center justify-between">
      <div class="flex justify-between flex-1 sm:hidden">
        <!-- Mobile pagination -->
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
           class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-white border border-amber-300 rounded-md hover:bg-amber-50">
          ກ່ອນໜ້າ
        </a>
        <?php else: ?>
        <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
          ກ່ອນໜ້າ
        </span>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
           class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-white border border-amber-300 rounded-md hover:bg-amber-50">
          ຕໍ່ໄປ
        </a>
        <?php else: ?>
        <span class="relative ml-3 inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">
          ຕໍ່ໄປ
        </span>
        <?php endif; ?>
      </div>

      <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-amber-700">
            ສະແດງ <span class="font-medium"><?= (($page - 1) * $records_per_page) + 1 ?></span> ເຖິງ <span class="font-medium"><?= min($page * $records_per_page, $total_records) ?></span> 
            ຈາກທັງໝົດ <span class="font-medium"><?= $total_records ?></span> ລາຍການ
          </p>
        </div>
        <div>
          <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <!-- Previous Page Link -->
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-amber-300 bg-white text-sm font-medium text-amber-500 hover:bg-amber-50">
              <i class="fas fa-chevron-left"></i>
            </a>
            <?php else: ?>
            <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
              <i class="fas fa-chevron-left"></i>
            </span>
            <?php endif; ?>

            <?php
            // Calculate pagination range
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            // Show first page if not in range
            if ($start_page > 1): ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                 class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                1
              </a>
              <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-gray-500">
                  ...
                </span>
              <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
              <?php if ($i == $page): ?>
                <span class="relative inline-flex items-center px-3 py-2 border border-amber-500 bg-amber-100 text-sm font-medium text-amber-600">
                  <?= $i ?>
                </span>
              <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                   class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                  <?= $i ?>
                </a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php
            // Show last page if not in range
            if ($end_page < $total_pages): ?>
              <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-gray-500">
                  ...
                </span>
              <?php endif; ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" 
                 class="relative inline-flex items-center px-3 py-2 border border-amber-300 bg-white text-sm font-medium text-amber-700 hover:bg-amber-50">
                <?= $total_pages ?>
              </a>
            <?php endif; ?>

            <!-- Next Page Link -->
            <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-amber-300 bg-white text-sm font-medium text-amber-500 hover:bg-amber-50">
              <i class="fas fa-chevron-right"></i>
            </a>
            <?php else: ?>
            <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed">
              <i class="fas fa-chevron-right"></i>
            </span>
            <?php endif; ?>
          </nav>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal ยืนยันการลบ (ปรับปรุง) -->
<div id="deleteModal" class="hidden fixed inset-0 modal-overlay flex items-center justify-center z-50 animate-fade-in">
  <div class="modal-container max-w-md w-full p-6 transform transition-all">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold text-amber-900 flex items-center">
        <i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i> ຢືນຢັນການລຶບຂໍໍາູນ
      </h3>
      <button type="button" class="close-modal text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full p-1.5 transition">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="py-3">
      <p class="text-gray-700">ທ່ານຕ້ອງການລຶບຂໍໍາູນພະສົງ <span id="deleteMonkNameDisplay" class="font-medium text-red-600"></span> ແທ້ບໍ່?</p>
      <p class="text-sm text-red-600 mt-2 bg-red-50 p-3 rounded border border-red-100 flex items-center">
        <i class="fas fa-info-circle mr-1.5"></i> ຂໍ້ມູນທີ່ຖືກລຶບບໍ່ສາມາດກູ້ຄືນໄດ້.
      </p>
    </div>
    <div class="flex justify-end space-x-3 mt-5">
      <button id="cancelDelete" 
             class="btn btn-secondary">
        <i class="fas fa-times mr-1.5"></i> ຍົກເລີກ
      </button>
      <a id="confirmDelete" href="#" 
         class="btn btn-danger">
        <i class="fas fa-trash-alt mr-1.5"></i> ຢືນຢັນການລຶບ
      </a>
    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Original modal code
    const deleteModal = document.getElementById('deleteModal');
    const deleteMonkNameDisplay = document.getElementById('deleteMonkNameDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    
    document.querySelectorAll('.delete-monk').forEach(button => {
        button.addEventListener('click', function() {
            const monkId = this.getAttribute('data-id');
            const monkName = this.getAttribute('data-name');
            
            deleteMonkNameDisplay.textContent = monkName;
            confirmDelete.href = '<?= $base_url ?>monks/delete.php?id=' + monkId;
            deleteModal.classList.remove('hidden');
        });
    });
    
    document.querySelectorAll('.close-modal, #cancelDelete').forEach(element => {
        element.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
    });
    
    // Additional code to close modal when clicking outside
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            deleteModal.classList.add('hidden');
        }
    });
});

// ระบบเปลี่ยนสถานะพระสงฆ์แบบ AJAX
document.querySelectorAll('.toggle-status-btn').forEach(button => {
    button.addEventListener('click', async function() {
        const monkId = this.getAttribute('data-monk-id');
        const currentStatus = this.getAttribute('data-current-status');
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        // Find the correct status element (either .status-active or .status-inactive)
        const statusElement = this.querySelector(currentStatus === 'active' ? '.status-active' : '.status-inactive');
        
        if (!statusElement) {
            console.error('Status element not found:', currentStatus);
            return;
        }
        
        // Save original HTML
        const originalHTML = statusElement.innerHTML;
        statusElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງປ່ຽນ...';
        button.disabled = true;
        
        try {
            const response = await fetch('<?= $base_url ?>monks/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    monk_id: monkId,
                    status: newStatus
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update with the correct classes
                if (newStatus === 'active') {
                    statusElement.className = 'status-active';
                    statusElement.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່ <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>';
                } else {
                    statusElement.className = 'status-inactive';
                    statusElement.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>';
                }
                
                // Update the current status attribute
                this.setAttribute('data-current-status', newStatus);
                
                // Show success message
                showToast(result.message, 'success');
                
                // Highlight the changed row
                const row = this.closest('tr, .p-4');
                if (row) {
                    row.style.backgroundColor = '#FFEDD5'; 
                    row.style.boxShadow = '0 0 8px rgba(251, 146, 60, 0.7)';

                    // Add blinking effect
                    let flash = 0;
                    const flashInterval = setInterval(() => {
                      if (flash >= 3) {
                        clearInterval(flashInterval);
                        row.style.transition = 'all 0.5s ease-out';
                        row.style.backgroundColor = '';
                        row.style.boxShadow = '';
                        return;
                      }
                      
                      row.style.backgroundColor = flash % 2 === 0 ? '#ffffff' : '#FFEDD5';
                      flash++;
                    }, 500);
                }
            } else {
                // Revert to original HTML on error
                statusElement.innerHTML = originalHTML;
                showToast(result.message || 'ເກີດຂໍ້ຜິດພາດໃນການປ່ຽນສະຖານະ', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            statusElement.innerHTML = originalHTML;
            showToast('ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່ກັບເຊີບເວີ', 'error');
        }
        
        button.disabled = false;
    });
});

// ຟັງຊັນສະແດງ Toast notification
function showToast(message, type = 'success') {
    // ส้าง toast notification
    const toast = document.createElement('div');
    
    let bgColor = 'bg-green-600';
    let iconClass = 'fa-check-circle';
    
    if (type === 'error') {
        bgColor = 'bg-red-600';
        iconClass = 'fa-exclamation-circle';
    } else if (type === 'info') {
        bgColor = 'bg-blue-600';
        iconClass = 'fa-info-circle';
    }
    
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white flex items-center z-50 ${bgColor}`;
    
    const icon = document.createElement('i');
    icon.className = `fas ${iconClass} mr-2`;
    
    const text = document.createElement('span');
    text.textContent = message;
    
    toast.appendChild(icon);
    toast.appendChild(text);
    document.body.appendChild(toast);
    
    // เพิ่มการเคลื่อนไหว
    requestAnimationFrame(() => {
        toast.style.transition = 'all 0.3s ease-in-out';
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });
    
    // ลบ toast หลังจาก 3 วินาที
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// เพิ่มฟังก์ชันนี้ในโค้ด JavaScript
function createActionToast(message, type = 'success', actionText = null, actionURL = null) {
    // สร้าง toast notification พร้อมปุ่มกด
    const toast = document.createElement('div');
    
    let bgColor = 'bg-green-600';
    let iconClass = 'fa-check-circle';
    
    if (type === 'error') {
        bgColor = 'bg-red-600';
        iconClass = 'fa-exclamation-circle';
    } else if (type === 'info') {
        bgColor = 'bg-blue-600';
        iconClass = 'fa-info-circle';
    }
    
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white flex items-center z-50 ${bgColor}`;
    
    const content = document.createElement('div');
    content.className = 'flex items-center';
    
    const icon = document.createElement('i');
    icon.className = `fas ${iconClass} mr-2`;
    
    const text = document.createElement('span');
    text.textContent = message;
    
    content.appendChild(icon);
    content.appendChild(text);
    toast.appendChild(content);
    
    // เพิ่มปุ่มกดถ้ามีข้อความปุ่ม
    if (actionText && actionURL) {
        const button = document.createElement('a');
        button.href = actionURL;
        button.className = 'ml-4 px-3 py-1 bg-white bg-opacity-25 rounded-lg text-sm font-medium transition hover:bg-opacity-50 flex items-center';
        button.innerHTML = `<i class="fas fa-arrow-right mr-1"></i> ${actionText}`;
        toast.appendChild(button);
    }
    
    document.body.appendChild(toast);
    
    // เพิ่มการเคลื่อนไหว
    requestAnimationFrame(() => {
        toast.style.transition = 'all 0.3s ease-in-out';
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });
    
    // ลบ toast หลังจาก 3 วินาที
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

function reloadTable() {
    // เพิ่มตัวแสดงการโหลด
    const table = document.querySelector('table');
    table.style.opacity = '0.6';
    
    // ดึงข้อมูลใหม่
        fetch('<?= $base_url ?>monks/get_monks_data.php?status=all')
            .then(response => response.json())
            .then(data => {
                // อัพเดตตารางด้วยข้อมูลใหม่...
                table.style.opacity = '1';
            })
            .catch(error => {
                console.error('Error:', error);
                table.style.opacity = '1';
            });
}

// เพิ่มฟังก์ชันสำหรับแสดง loading state
function showLoadingState(selectElement, message = 'ກຳລັງໂຫຼດຂໍ້ມູນ...') {
  selectElement.innerHTML = `<option value="" class="loading-indicator">${message}</option>`;
  selectElement.disabled = true;
}

// ใช้ฟังก์ชันนี้แทนการเขียนโค้ดซ้ำๆ
function loadDistricts(provinceId) {
  const districtContainer = document.getElementById('district-container');
  const districtSelect = document.getElementById('district_id');
  const templeSelect = document.getElementById('temple_id');
  
  // ซ่อน district dropdown และรีเซ็ต
  if (!provinceId) {
    districtContainer.style.display = 'none';
    districtSelect.innerHTML = '<option value="">-- ທຸກເມືອງ --</option>';
    
    // โหลดวัดทั้งหมดในแขวงทั้งหมด
    loadAllTemples();
    return;
  }
  
  // แสดง dropdown เมืองและเพิ่ม loading state
  districtContainer.style.display = 'block';
  showLoadingState(districtSelect);
  
  // ส่ง request ไปยัง API
  fetch(`<?= $base_url ?>api/get-districts.php?province_id=${provinceId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // เพิ่มตัวเลือกเริ่มต้น
        districtSelect.innerHTML = '<option value="">-- ທຸກເມືອງ --</option>';
        
        // เพิ่มรายการเมือง
        data.districts.forEach(district => {
          const option = document.createElement('option');
          option.value = district.district_id;
          option.textContent = district.district_name;
          
          // ตรวจสอบค่าที่เลือกไว้ก่อนหน้า (ถ้ามี)
          if ('<?= isset($_GET['district_id']) ? $_GET['district_id'] : '' ?>' == district.district_id) {
            option.selected = true;
          }
          
          districtSelect.appendChild(option);
        });
        
        // เปิดใช้งาน dropdown
        districtSelect.disabled = false;
        
        // โหลดวัดตามแขวงที่เลือก
        loadTemplesByProvince(provinceId);
      } else {
        districtSelect.innerHTML = '<option value="">-- ไม่พบข้อมูล --</option>';
        console.error('Error loading districts:', data.message);
      }
    })
    .catch(error => {
      console.error('API Error:', error);
      districtSelect.innerHTML = '<option value="">-- เกิดข้อผิดพลาด --</option>';
    });
}

// ฟังก์ชันโหลดวัดตามเมืองที่เลือก
function loadTemplesByDistrict(districtId) {
  const templeSelect = document.getElementById('temple_id');
  const provinceId = document.getElementById('province_id').value;
  
  if (!districtId) {
    // ถ้าไม่ได้เลือกเมือง ให้โหลดวัดทั้งหมดในแขวง
    loadTemplesByProvince(provinceId);
    return;
  }
  
  // แสดงสถานะกำลังโหลด
  templeSelect.innerHTML = '<option value="" class="loading-indicator">ກຳລັງໂຫຼດຂໍ້ມູນ...</option>';
  templeSelect.disabled = true;
  
  // เพิ่ม debug info
  console.log(`Loading temples for district ID: ${districtId}`);
  
  // ส่ง request ไปยัง API เพื่อดึงวัดตามเมืองที่เลือก
  fetch(`<?= $base_url ?>api/get-temples.php?district_id=${districtId}`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Temple data received:', data);
      
      if (data.success) {
        // เพิ่มตัวเลือกเริ่มต้น
        templeSelect.innerHTML = '<option value="">-- ທຸກວັດ --</option>';
        
        // เพิ่มรายการวัด
        if (data.temples && data.temples.length > 0) {
          data.temples.forEach(temple => {
            const option = document.createElement('option');
            option.value = temple.id;
            option.textContent = temple.name;
            
            templeSelect.appendChild(option);
          });
          
          // เพิ่มตรงนี้: เมื่อเลือกวัด ให้ส่งฟอร์มอัตโนมัติ
          templeSelect.addEventListener('change', function() {
            if (this.value) {
              // แสดงข้อความกำลังโหลด
              showToast('ກຳລັງໂຫຼດຂໍ້ມູນພະສົງ...', 'info');
              // ส่งฟอร์มอัตโนมัติ
              this.closest('form').submit();
            }
          });
          
        } else {
          templeSelect.innerHTML += '<option value="" disabled>-- ບໍ່ພົບຂໍ້ມູນວັດໃນເມືອງນີ້ --</option>';
        }
      } else {
        templeSelect.innerHTML = '<option value="">-- ບໍ່ພົບຂໍ້ມູນ --</option>';
        console.error('API reported error:', data.message);
        showToast(`ບໍ່ສາມາດໂຫຼດຂໍ້ມູນວັດໄດ້: ${data.message}`, 'error');
      }
      
      templeSelect.disabled = false;
    })
    .catch(error => {
      console.error('API Error:', error);
      templeSelect.innerHTML = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
      templeSelect.disabled = false;
      showToast('ເກີດຂໍ້ຜິດພາດໃນການໂຫຼດຂໍ້ມູນວັດ', 'error');
    });
}

// ฟังก์ชันโหลดวัดตามแขวง
function loadTemplesByProvince(provinceId) {
  if (!provinceId) return;
  
  const templeSelect = document.getElementById('temple_id');
  
  // แสดงสถานะกำลังโหลด
  templeSelect.innerHTML = '<option value="" class="loading-indicator">ກຳລັງໂຫຼດຂໍ້ມູນ...</option>';
  templeSelect.disabled = true;
  
  // เพิ่ม debug info
  console.log(`Loading temples for province ID: ${provinceId}`);
  
  fetch(`<?= $base_url ?>api/get-temples.php?province_id=${provinceId}`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      console.log('Temple data received:', data); // Debug: แสดงข้อมูลที่ได้รับ
      
      if (data.success) {
        templeSelect.innerHTML = '<option value="">-- ທຸກວັດ --</option>';
        
        if (data.temples && data.temples.length > 0) {
          data.temples.forEach(temple => {
            const option = document.createElement('option');
            option.value = temple.id;
            option.textContent = temple.name;
            
            if ('<?= isset($_GET['temple_id']) ? $_GET['temple_id'] : '' ?>' == temple.id) {
              option.selected = true;
            }
            
            templeSelect.appendChild(option);
          });
          console.log(`Added ${data.temples.length} temples to dropdown`);
        } else {
          console.log('No temples found for this province');
          templeSelect.innerHTML += '<option value="" disabled>-- ບໍ່ພົບຂໍ້ມູນວັດໃນແຂວງນີ້ --</option>';
        }
      } else {
        templeSelect.innerHTML = '<option value="">-- ບໍ່ພົບຂໍ້ມູນ --</option>';
        console.error('API reported error:', data.message);
      }
      
      templeSelect.disabled = false;
    })
    .catch(error => {
      console.error('API Error:', error);
      templeSelect.innerHTML = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
      templeSelect.disabled = false;
    });
}

// ฟังก์ชันโหลดวัดทั้งหมด
function loadAllTemples() {
  const templeSelect = document.getElementById('temple_id');
  
  // ตรวจสอบว่ามีวัดอยู่แล้วหรือไม่
  if (templeSelect.options.length > 1) return;
  
  templeSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
  templeSelect.disabled = true;
  
  fetch(`<?= $base_url ?>api/get-temples.php`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        templeSelect.innerHTML = '<option value="">-- ທຸກວັດ --</option>';
        
        data.temples.forEach(temple => {
          const option = document.createElement('option');
          option.value = temple.id;
          option.textContent = temple.name;
          
          if ('<?= isset($_GET['temple_id']) ? $_GET['temple_id'] : '' ?>' == temple.id) {
            option.selected = true;
          }
          
          templeSelect.appendChild(option);
        });
      } else {
        templeSelect.innerHTML = '<option value="">-- ບໍ່ພົບຂໍ້ມູນ --</option>';
      }
      
      templeSelect.disabled = false;
    })
    .catch(error => {
      console.error('API Error:', error);
      templeSelect.innerHTML = '<option value="">-- ເກີດຂໍ້ຜິດພາດ --</option>';
      templeSelect.disabled = false;
    });
}

// โหลดข้อมูลเริ่มต้นเมื่อหน้าเว็บโหลด
document.addEventListener('DOMContentLoaded', function() {
  const provinceId = '<?= isset($_GET['province_id']) ? $_GET['province_id'] : '' ?>';
  const districtId = '<?= isset($_GET['district_id']) ? $_GET['district_id'] : '' ?>';
  
  // เพิ่มเติม: event listener สำหรับ temple_id
  const templeSelect = document.getElementById('temple_id');
  if (templeSelect) {
    templeSelect.addEventListener('change', function() {
      if (this.value) {
        showToast('ກຳລັງໂຫຼດຂໍ້ມູນພະສົງ...', 'info');
        this.closest('form').submit();
      }
    });
  }
  
  if (provinceId) {
    loadDistricts(provinceId);
    
    // เพิ่มเงื่อนไขเพื่อรอให้ district โหลดเสร็จก่อน แล้วค่อยเลือก district_id
    if (districtId) {
      // ตรวจสอบซ้ำทุก 100ms ว่า district dropdown พร้อมหรือยัง
      const checkDistrictReady = setInterval(function() {
        const districtSelect = document.getElementById('district_id');
        if (districtSelect && !districtSelect.disabled && districtSelect.options.length > 1) {
          clearInterval(checkDistrictReady);
          districtSelect.value = districtId;
          loadTemplesByDistrict(districtId);
        }
      }, 100);
      
      // ตั้งเวลายกเลิกการตรวจสอบหากเกิน 5 วินาที
      setTimeout(function() {
        clearInterval(checkDistrictReady);
      }, 5000);
    }
  }
});

// สร้าง style element และเพิ่มเข้าไปใน DOM
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
</script>
<?php
ob_end_flush();
require_once '../includes/footer.php';