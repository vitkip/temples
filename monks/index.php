<?php
// filepath: c:\xampp\htdocs\temples\monks\index.php
ob_start(); // เพิ่ม output buffering เพื่อป้องกัน headers already sent

$page_title = 'ຈັດການພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບການຕັ້ງຄ່າຕົວກອງ temple_id
$temple_filter = isset($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;
$province_filter = isset($_GET['province_id']) ? (int)$_GET['province_id'] : null;

// ກວດສອບສິດທິຜູ້ໃຊ້
$user_role = $_SESSION['user']['role'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;
$user_id = $_SESSION['user']['id'];

// ກຽມຄິວລີຕາມຕົວກອງ ແລະ ສິດທິຂອງຜູ່ໃຊ້
$params = [];
$query = "SELECT m.*, t.name as temple_name, t.province_id, p.province_name 
          FROM monks m 
          LEFT JOIN temples t ON m.temple_id = t.id 
          LEFT JOIN provinces p ON t.province_id = p.province_id
          WHERE 1=1";

// ກຳນົດການເຂົ້າເຖິງຂໍໍາູນຕາມບົດບາດ
if ($user_role === 'admin') {
    // admin ສາມາດເບິ່ງພະສົງໃນວັດຂອງຕົນເອງເທົ່ານັ້ນ
    $query .= " AND m.temple_id = ?";
    $params[] = $user_temple_id;
} elseif ($user_role === 'province_admin') {
    // province_admin ສາມາດເບິ່ງພະສົງໃນແຂວງທີ່ຮັບຜິດຊອບເທົ່ານັ້ນ
    $query .= " AND t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";

    $params[] = $user_id;
}
// superadmin ສາມາດເບິ່ງທັງໝົດ (ບໍ່ມີເງື່ອນໄຂເພີ່ມ)

// ນໍາໃຊ້ຕົວກອງແຂວງ ຖ້າມີການລະບຸ
if ($province_filter) {
    $query .= " AND t.province_id = ?";
    $params[] = $province_filter;
}

// ນໍາໃຊ້ຕົວກອງວັດ ຖ້າມີການລະບຸ
if ($temple_filter) {
    $query .= " AND m.temple_id = ?";
    $params[] = $temple_filter;
}

// ນໍາໃຊ້ການຄົ້ນຫາຖ້າມີການລະບຸ
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query .= " AND (m.name LIKE ? OR m.lay_name LIKE ?)"; // แก้ไขจาก buddhist_name เป็น lay_name
    $params[] = $search;
    $params[] = $search;
}

// ນໍາໃຊ້ຕົວກອງສະຖານະຖ້າມີການລະບຸ
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
if ($status_filter !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
}

// ຕົວກອງແຂວງເກີດ
if (!empty($_GET['birth_province'])) {
    $query .= " AND m.birth_province LIKE ?";
    $params[] = "%" . $_GET['birth_province'] . "%";
}

// ຈັດລຽງຕາມພັນສາ (ຫຼຸດລົງ) ແລະ ຊື່
$query .= " ORDER BY m.pansa DESC, m.name ASC";

// ປະຕິບັດຄິວລີ
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$monks = $stmt->fetchAll();

// ດຶງຂໍ້ມູນແຂວງສຳລັບ dropdown ຕົວກອງ (ຖ້າຜູໃຊເປັນ superadmin)
$provinces = [];
if ($user_role === 'superadmin') {
    // superadmin ເບິ່ງແຂວງທັງໝົດ
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $provinces = $province_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    // province_admin ເບິ່ງສະເພາະແຂວງທີ່ຮັບຜິດຊອບ
    $province_stmt = $pdo->prepare("
        SELECT p.province_id, p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $provinces = $province_stmt->fetchAll();
}

// ດຶງຂໍ້ມູນວັດສຳລັບ dropdown ຕົວກອງ
$temples = [];
if ($user_role === 'superadmin') {
    // superadmin ເບິ່ງວັດທັງໝົດ
    $temple_sql = "SELECT t.id, t.name, p.province_name 
                   FROM temples t 
                   LEFT JOIN provinces p ON t.province_id = p.province_id 
                   WHERE t.status = 'active' 
                   ORDER BY p.province_name, t.name";
    $temple_stmt = $pdo->query($temple_sql);
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'admin') {
    // admin ເບິ່ງສະເພາະວັດຂອງຕົນເອງ
    $temple_stmt = $pdo->prepare("
        SELECT t.id, t.name, p.province_name 
        FROM temples t 
        LEFT JOIN provinces p ON t.province_id = p.province_id 
        WHERE t.id = ?
    ");
    $temple_stmt->execute([$user_temple_id]);
    $temples = $temple_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    // province_admin ເບິ່ງວັດໃນແຂວງທີ່ຮັບຜິດຊອບ
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

// ກວດສອບສິດໃນການເພີ່ມ/ແກ້ໄຂພະສົງ
$can_edit = ($user_role === 'superadmin' || $user_role === 'admin' || $user_role === 'province_admin');
$can_export = false;
if (isset($_SESSION['user'])) {
    $user_role = $_SESSION['user']['role'] ?? '';
    
    // Superadmin, province_admin และ admin (วัด) สามารถส่งออกได้
    if ($user_role === 'superadmin' || $user_role === 'province_admin' || $user_role === 'admin') {
        $can_export = true;
    }
}
?>

<!-- เพิ่ม CSS นี้ในส่วนหัวของไฟล์ หรือในไฟล์ CSS แยก -->
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/style-monks.css">

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
      <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-6 form-grid">
        <!-- ค้นหา -->
        <div>
          <label for="search" class="form-label">
            <i class="fas fa-search text-amber-700 mr-1"></i> ຄົ້ນຫາ
          </label>
          <input type="text" name="search" id="search" 
                 value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" 
                 placeholder="ພິມຊື່ພະສົງ..." 
                 class="form-input">
        </div>

        <!-- ตัวกรองแขวง (เฉพาะ superadmin และ province_admin) -->
        <?php if (!empty($provinces)): ?>
        <div>
          <label for="province_id" class="form-label">
            <i class="fas fa-map-marked-alt text-amber-700 mr-1"></i> ແຂວງ
          </label>
          <select name="province_id" id="province_id" class="form-select">
            <option value="">-- ທັງໝົດ --</option>
            <?php foreach($provinces as $province): ?>
            <option value="<?= $province['province_id'] ?>" <?= isset($_GET['province_id']) && $_GET['province_id'] == $province['province_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($province['province_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <!-- ตัวกรองวัด -->
        <?php if (!empty($temples)): ?>
        <div>
          <label for="temple_id" class="form-label">
            <i class="fas fa-place-of-worship text-amber-700 mr-1"></i> ວັດ
          </label>
          <select name="temple_id" id="temple_id" class="form-select">
            <option value="">-- ທັງໝົດ --</option>
            <?php foreach($temples as $temple): ?>
            <option value="<?= $temple['id'] ?>" <?= isset($_GET['temple_id']) && $_GET['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($temple['name']) ?>
              <?php if (!empty($temple['province_name'])): ?>
                (<?= htmlspecialchars($temple['province_name']) ?>)
              <?php endif; ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <!-- ตัวกรองสถานะ -->
        <div>
          <label for="status" class="form-label">
            <i class="fas fa-toggle-on text-amber-700 mr-1"></i> ສະຖານະ
          </label>
          <select name="status" id="status" class="form-select">
            <option value="all" <?= isset($_GET['status']) && $_GET['status'] === 'all' ? 'selected' : '' ?>>ທັງໝົດ</option>
            <option value="active" <?= (!isset($_GET['status']) || $_GET['status'] === 'active') ? 'selected' : '' ?>>ບວດຢູ່</option>
            <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
          </select>
        </div>

        <!-- ตัวกรองแขวงเกิด -->
        <div>
            <label for="birth_province" class="form-label">
                <i class="fas fa-baby text-amber-700 mr-1"></i> ແຂວງເກີດ
            </label>
            <select name="birth_province" id="birth_province" class="form-select">
              <option value="">-- ທັງໝົດ --</option>
              <option value="ນະຄອນຫຼວງວຽງຈັນ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ນະຄອນຫຼວງວຽງຈັນ' ? 'selected' : '' ?>>ນະຄອນຫຼວງວຽງຈັນ</option>
              <option value="ຫຼວງພະບາງ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຫຼວງພະບາງ' ? 'selected' : '' ?>>ຫຼວງພະບາງ</option>
              <option value="ຈຳປາສັກ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຈຳປາສັກ' ? 'selected' : '' ?>>ຈຳປາສັກ</option>
              <option value="ສະຫວັນນະເຂດ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ສະຫວັນນະເຂດ' ? 'selected' : '' ?>>ສະຫວັນນະເຂດ</option>
              <option value="ຊຽງຂວາງ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຊຽງຂວາງ' ? 'selected' : '' ?>>ຊຽງຂວາງ</option>
              <option value="ຫົວພັນ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຫົວພັນ' ? 'selected' : '' ?>>ຫົວພັນ</option>
              <option value="ອຸດົມໄຊ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ອຸດົມໄຊ' ? 'selected' : '' ?>>ອຸດົມໄຊ</option>
              <option value="ບໍ່ແກ້ວ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ບໍ່ແກ້ວ' ? 'selected' : '' ?>>ບໍ່ແກ້ວ</option>
              <option value="ຫຼວງນ້ຳທາ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຫຼວງນ້ຳທາ' ? 'selected' : '' ?>>ຫຼວງນ້ຳທາ</option>
              <option value="ຜົ້ງສາລີ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຜົ້ງສາລີ' ? 'selected' : '' ?>>ຜົ້ງສາລີ</option>
              <option value="ໄຊຍະບູລີ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ໄຊຍະບູລີ' ? 'selected' : '' ?>>ໄຊຍະບູລີ</option>
              <option value="ບໍລິຄຳໄຊ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ບໍລິຄຳໄຊ' ? 'selected' : '' ?>>ບໍລິຄຳໄຊ</option>
              <option value="ຄຳມ່ວນ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ຄຳມ່ວນ' ? 'selected' : '' ?>>ຄຳມ່ວນ</option>
              <option value="ສາລະວັນ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ສາລະວັນ' ? 'selected' : '' ?>>ສາລະວັນ</option>
              <option value="ເຊກອງ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ເຊກອງ' ? 'selected' : '' ?>>ເຊກອງ</option>
              <option value="ອັດຕະປື" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ອັດຕະປື' ? 'selected' : '' ?>>ອັດຕະປື</option>
              <option value="ໄຊສົມບູນ" <?= isset($_GET['birth_province']) && $_GET['birth_province'] === 'ໄຊສົມບູນ' ? 'selected' : '' ?>>ໄຊສົມບູນ</option>
            </select>
        </div>
        
        <!-- ปุ่มส่งค้นหา -->
        <div class="md:col-span-5 flex justify-end">
          <div class="flex space-x-2 btn-group">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
            </button>
            <a href="<?= $base_url ?>monks/" class="btn btn-secondary flex items-center justify-center" title="ລ້າງຕົວກອງທັງໝົດ">
              <i class="fas fa-sync-alt"></i>
            </a>
          </div>
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
      ທ່ານສາມາດເລືອກແຂວງເພື່ອສົ່ງອອກຂໍ້ມູນໄດ້ຈາກຕົວກອງແຂວງດ້ານເທິງ. ຖ້າບໍ່ເລືອກແຂວງ ລະບົບຈະສົ່ງອອກທຸກຂໍ້ມູນພະສົງທັງໝົດ.
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
          <i class="fas fa-users-class mr-2"></i> ພົບຂໍ້ມູນ <span class="font-semibold text-amber-700"><?= count($monks) ?></span> ລາຍການ
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
                              ($user_role === 'province_admin' && !empty($monk['province_id']) && 
                               in_array($monk['province_id'], array_column($provinces, 'province_id')));
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
                              ($user_role === 'province_admin' && !empty($monk['province_id']) && 
                               in_array($monk['province_id'], array_column($provinces, 'province_id')));
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
        <p class="text-amber-800 mb-4">ບໍ່ພົບຂໍໍາູນພະສົງ</p>
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

<!-- เพิ่ม animation และปรับแต่ง CSS -->
<style>
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.animate-fade-in {
  animation: fadeIn 0.2s ease-out;
}

.bg-gradient-to-r {
  background-size: 200% 200%;
  animation: gradientAnimation 5s ease infinite;
}

@keyframes gradientAnimation {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

.shadow-lg {
  box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.05), 0 8px 10px -6px rgba(59, 130, 246, 0.01);
}

.shadow-2xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.backdrop-blur-sm {
  backdrop-filter: blur(4px);
}
</style>

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
</script>