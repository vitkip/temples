<?php
// filepath: c:\xampp\htdocs\temples\monks\index.php
ob_start(); // เพิ่ม output buffering เพื่อป้องกัน headers already sent

$page_title = 'ຈັດການພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບການຕັ້ງຄ່າຕົວກອງ temple_id
$temple_filter = isset($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;

// ກຽມຄິວລີຕາມຕົວກອງ ແລະ ສິດທິຂອງຜູ່ໃຊ້
$params = [];
$query = "SELECT m.*, t.name as temple_name FROM monks m 
          LEFT JOIN temples t ON m.temple_id = t.id WHERE 1=1";

// ນໍາໃຊ້ຕົວກອງວັດ ຖ້າມີການລະບຸ
if ($temple_filter) {
    $query .= " AND m.temple_id = ?";
    $params[] = $temple_filter;
}

// ຖ້າຜູໃຊເປັນຜູ້ດູແລວັດ, ສະແດງສະເພາະພະສົງໃນວັດຂອງເຂົາເທົ່ານັ້ນ
if ($_SESSION['user']['role'] === 'admin') {
    $query .= " AND m.temple_id = ?";
    $params[] = $_SESSION['user']['temple_id'];
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

// ຈັດລຽງຕາມພັນສາ (ຫຼຸດລົງ) ແລະ ຊື່
$query .= " ORDER BY m.pansa DESC, m.name ASC";

// ປະຕິບັດຄິວລີ
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$monks = $stmt->fetchAll();

// ດຶງຂໍ້ມູນວັດສຳລັບ dropdown ຕົວກອງ (ຖ້າຜູໃຊເປັນ superadmin)
$temples = [];
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}
// ກວດສອບຕົວກອງເກີດ ແລະ ຕັ້ງໃນ WHERE
if (!empty($_GET['birth_province'])) {
    $where_conditions[] = "birth_province LIKE ?";
    $params[] = "%" . $_GET['birth_province'] . "%";
}
// ກວດສອບສິດໃນການເພີ່ມ/ແກ້ໄຂພະສົງ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- เพิ่ม CSS นี้ในส่วนหัวของไฟล์ หรือในไฟล์ CSS แยก -->
 <link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
<style>
  /* นำเข้าฟอนต์ภาษาไทย/ลาว */
  @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap');
  @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;400;500;600;700&display=swap');
  
  :root {
    --color-primary: #C8A97E;        /* สีทองอ่อน */
    --color-primary-dark: #A38455;   /* สีทองเข้ม */
    --color-secondary: #8E6F4D;      /* สีน้ำตาล */
    --color-accent: #D4B68F;         /* สีทองนวล */
    --color-light: #F5EFE6;          /* สีครีมอ่อน */
    --color-lightest: #FAF8F4;       /* สีครีมสว่าง */
    --color-dark: #453525;           /* สีน้ำตาลเข้ม */
    --color-success: #7E9F7E;        /* สีเขียวอ่อนนุ่ม */
    --color-danger: #D68F84;         /* สีแดงอ่อนนุ่ม */
    --shadow-sm: 0 2px 8px rgba(138, 103, 57, 0.08);
    --shadow-md: 0 4px 12px rgba(138, 103, 57, 0.12);
    --shadow-lg: 0 8px 24px rgba(138, 103, 57, 0.15);
    --border-radius: 0.75rem;
  }
  
  * {
    font-family: 'Noto Sans Thai', 'Noto Sans Lao', sans-serif;
  }
  
  /* การปรับแต่งส่วนประกอบต่างๆ */
  body {
    background-color: var(--color-lightest);
    color: #5a4631;
  }
  
  .page-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
  }
  
  /* ส่วนหัว */
  .header-section {
    border-radius: var(--border-radius);
    background: linear-gradient(to right, #f3e9dd, #f5efe6);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(200, 169, 126, 0.2);
  }
  
  .header-title {
    color: var(--color-secondary);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.75rem;
  }
  
  /* ตัวกรอง */
  .filter-section {
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(200, 169, 126, 0.2);
    margin-bottom: 1.5rem;
    overflow: hidden;
  }
  
  .filter-header {
    background: linear-gradient(to right, rgba(200, 169, 126, 0.15), rgba(212, 182, 143, 0.1));
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(200, 169, 126, 0.2);
  }
  
  .filter-title {
    color: var(--color-secondary);
    font-weight: 600;
    display: flex;
    align-items: center;
    font-size: 1.125rem;
  }
  
  /* ตาราง */
  .data-table {
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(200, 169, 126, 0.2);
    overflow: hidden;
  }
  
  .table-header {
    background: linear-gradient(to right, rgba(200, 169, 126, 0.15), rgba(212, 182, 143, 0.1));
  }
  
  .table-header th {
    color: var(--color-dark);
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
  }
  
  .table-row {
    transition: all 0.2s ease;
  }
  
  .table-row:hover {
    background-color: var(--color-lightest);
  }
  
  .table-cell {
    padding: 1rem;
    border-bottom: 1px solid rgba(200, 169, 126, 0.1);
  }
  
  /* ปุ่มต่างๆ */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s;
    gap: 0.5rem;
    font-size: 0.875rem;
  }
  
  .btn-primary {
    background: linear-gradient(to bottom right, var(--color-primary), var(--color-primary-dark));
    color: #fff;
    box-shadow: 0 2px 4px rgba(162, 132, 85, 0.2);
  }
  
  .btn-primary:hover {
    background: linear-gradient(to bottom right, #d4b68f, #bb9c6a);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(162, 132, 85, 0.3);
  }
  
  .btn-secondary {
    background-color: #f0e6d9;
    color: var(--color-secondary);
    box-shadow: 0 2px 4px rgba(162, 132, 85, 0.1);
  }
  
  .btn-secondary:hover {
    background-color: #e5d9c8;
    transform: translateY(-1px);
  }
  
  .btn-danger {
    background-color: var(--color-danger);
    color: white;
  }
  
  .btn-danger:hover {
    background-color: #c57b70;
  }
  
  /* สถานะพระ */
  .status-active {
    background-color: rgba(126, 159, 126, 0.15);
    color: #4d7a4d;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgba(126, 159, 126, 0.3);
  }
  
  .status-inactive {
    background-color: rgba(169, 169, 169, 0.15);
    color: #696969;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgba(169, 169, 169, 0.3);
  }
  
  /* Input fields และ select */
  .form-input,
  .form-select {
    width: 100%;
    padding: 0.5rem;
    border-radius: 0.5rem;
    border: 1px solid #e0d3c3;
    background-color: #fff;
    transition: all 0.2s;
  }
  
  .form-input:focus,
  .form-select:focus {
    border-color: var(--color-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(200, 169, 126, 0.2);
  }
  
  .form-label {
    font-size: 0.875rem;
    color: var(--color-secondary);
    margin-bottom: 0.25rem;
    display: block;
    font-weight: 500;
  }
  
  /* Modal */
  .modal-overlay {
    background-color: rgba(69, 53, 37, 0.5);
    backdrop-filter: blur(4px);
  }
  
  .modal-container {
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(200, 169, 126, 0.3);
  }
  
  /* Animations */
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  .animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
  }
  
  /* Responsive */
  @media (max-width: 768px) {
    .form-grid {
      grid-template-columns: 1fr;
    }
    
    .btn-group {
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .header-section {
      flex-direction: column;
      gap: 1rem;
      align-items: flex-start;
    }
    
    .data-table {
      overflow-x: auto;
    }
  }
  
  /* รูปภาพพระสงฆ์ */
  .monk-image {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--color-accent);
  }
  
  .monk-placeholder {
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: linear-gradient(135deg, #f3e9dd, #e5d9c8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary-dark);
  }
  
  /* ไอคอนเพิ่มเติม */
  .icon {
    color: var(--color-primary);
    display: inline-flex;
  }
  
  /* Toast notifications */
  .toast {
    background: linear-gradient(to right, var(--color-primary-dark), var(--color-primary));
    color: white;
    border-radius: 0.5rem;
    padding: 1rem;
    margin: 1rem;
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease-out forwards;
  }
  
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  
  /* พื้นหลังพิเศษ */
  .bg-temple-pattern {
    background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTMwIDMwIEwwIDYwIEw2MCA2MCBaIiBmaWxsPSIjQzhhOTdlIi8+PC9zdmc+');
    background-repeat: repeat;
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
    </div>
    <div class="flex flex-wrap gap-3">
      <!-- ปุ่มส่งออก PDF -->
      <a href="<?= $base_url ?>reports/generate_pdf_monks.php" target="_blank" 
         class="btn btn-secondary">
        <i class="fas fa-file-pdf text-amber-700"></i> ສົ່ງອອກ PDF
      </a>
      
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
      <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 form-grid">
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
        <!-- ตัวกรอง prefix -->
        <div>
          <label for="prefix" class="form-label">
            <i class="fas fa-user-tag text-amber-700 mr-1"></i> ຄຳນຳໜ້າ
          </label>
          <select name="prefix" id="prefix" class="form-select">
            <option value="">-- ທັງໝົດ --</option>
            <option value="ພຣະ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
            <option value="ຄຸນແມ່ຂາວ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
            <option value="ສ.ນ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
            <option value="ສັງກະລີ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
          </select>
        </div>
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
        <div class="mb-4">
            <label for="birth_province" class="info-label">ແຂວງເກີດ</label>
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
        <!-- ตัวกรองวัด (เฉพาะ superadmin) -->
        <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
        <div>
          <label for="temple_id" class="form-label">
            <i class="fas fa-place-of-worship text-amber-700 mr-1"></i> ວັດ
          </label>
          <select name="temple_id" id="temple_id" class="form-select">
            <option value="">-- ທັງໝົດ --</option>
            <?php foreach($temples as $temple): ?>
            <option value="<?= $temple['id'] ?>" <?= isset($_GET['temple_id']) && $_GET['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($temple['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        
        <!-- ปุ่มส่งค้นหา -->
        <div class="self-end">
          <div class="flex space-x-2 btn-group">
            <button type="submit" class="btn btn-primary flex-grow">
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
    <div class="px-6 py-4 bg-amber-50 border-b border-amber-200">
      <div class="flex flex-wrap justify-between items-center">
        <div class="text-amber-900 mb-2 sm:mb-0">
          <i class="fas fa-users-class mr-2"></i> ພົບຂໍ້ມູນ <span class="font-semibold text-amber-700"><?= count($monks) ?></span> ລາຍການ
        </div>
        <!-- เพิ่มปุ่มส่งออก Excel -->
        <a href="<?= $base_url ?>reports/generate_excel_monks.php" target="_blank" 
           class="text-amber-800 hover:text-amber-900 text-sm flex items-center">
          <i class="fas fa-file-export mr-1"></i> ສົ່ງອອກ Excel
        </a>
      </div>
    </div>

    <?php if (count($monks) > 0): ?>
    <!-- Table for medium and large screens -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full">
        <thead class="table-header">
          <tr>
            <th class="px-6 py-3.5 text-left">ຮູບພາບ</th>
            <th class="px-6 py-3.5 text-left">ຄຳນຳໜ້າ</th>
            <th class="px-6 py-3.5 text-left">ຊື່ ແລະ ນາມສະກຸນ</th>
            <th class="px-6 py-3.5 text-left">ພັນສາ</th>
            <th class="px-6 py-3.5 text-left">ວັດ</th>
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
              <div class="font-medium"><?= htmlspecialchars($monk['prefix'] ?? '-') ?></div>
            </td>
            <td class="table-cell">
              <div class="font-medium text-amber-900">
                <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" class="hover:text-amber-700 transition-colors"><?= htmlspecialchars($monk['name']) ?></a>
              </div>
              <?php if (!empty($monk['lay_name'])): ?>
              <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
              <?php endif; ?>
            </td>
            <td class="table-cell">
              <div class="text-gray-700"><?= htmlspecialchars($monk['pansa'] ?? '-') ?> <span class="text-xs">ພັນສາ</span></div>
            </td>
            <td class="table-cell">
              <div class="text-gray-700 flex items-center">
                <i class="fas fa-place-of-worship text-amber-500 mr-1.5 text-xs"></i>
                <?= htmlspecialchars($monk['temple_name'] ?? '-') ?>
              </div>
            </td>
            <td class="table-cell">
              <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
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
                
                <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
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
    
    <!-- Card layout for mobile -->
    <div class="md:hidden">
      <?php foreach($monks as $monk): ?>
      <div class="bg-white border border-amber-100 rounded-lg mb-4 overflow-hidden shadow-sm">
        <div class="p-4 flex items-center border-b border-amber-50">
          <div class="mr-3">
            <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
              <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" 
                   class="w-16 h-16 rounded-full object-cover border-2 border-amber-200">
            <?php else: ?>
              <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-50 to-amber-100 flex items-center justify-center">
                <i class="fas fa-user text-amber-300 text-2xl"></i>
              </div>
            <?php endif; ?>
          </div>
          <div class="flex-grow">
            <div class="font-medium text-sm text-amber-700"><?= htmlspecialchars($monk['prefix'] ?? '-') ?></div>
            <div class="font-semibold text-lg text-amber-900">
              <?= htmlspecialchars($monk['name']) ?>
            </div>
            <?php if (!empty($monk['lay_name'])): ?>
            <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
            <?php endif; ?>
          </div>
          
          <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
            <button type="button" class="toggle-status-btn" data-monk-id="<?= $monk['id'] ?>" data-current-status="<?= $monk['status'] ?>">
              <?php if($monk['status'] === 'active'): ?>
                <span class="status-active whitespace-nowrap">
                  <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                  <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                </span>
              <?php else: ?>
                <span class="status-inactive whitespace-nowrap">
                  <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                  <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                </span>
              <?php endif; ?>
            </button>
          <?php else: ?>
            <div>
              <?php if($monk['status'] === 'active'): ?>
                <span class="status-active whitespace-nowrap">
                  <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                </span>
              <?php else: ?>
                <span class="status-inactive whitespace-nowrap">
                  <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="p-4 bg-amber-50 bg-opacity-40">
          <div class="flex justify-between items-center mb-2">
            <div class="text-sm">
              <span class="font-medium text-amber-800">ພັນສາ:</span> 
              <span class="text-gray-700"><?= htmlspecialchars($monk['pansa'] ?? '-') ?></span>
            </div>
            
            <div class="text-sm">
              <span class="font-medium text-amber-800">ວັດ:</span> 
              <span class="text-gray-700"><?= htmlspecialchars($monk['temple_name'] ?? '-') ?></span>
            </div>
          </div>
          
          <div class="flex justify-end space-x-2 pt-2">
            <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" 
               class="inline-flex items-center justify-center px-3 py-1.5 bg-amber-100 text-amber-800 rounded-md text-sm">
              <i class="fas fa-eye mr-1.5"></i> ເບິ່ງ
            </a>
            
            <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
            <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" 
               class="inline-flex items-center justify-center px-3 py-1.5 bg-amber-500 text-white rounded-md text-sm">
              <i class="fas fa-edit mr-1.5"></i> ແກ້ໄຂ
            </a>
            
            <a href="javascript:void(0)" 
               class="inline-flex items-center justify-center px-3 py-1.5 bg-red-100 text-red-700 rounded-md text-sm delete-monk" 
               data-id="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>">
              <i class="fas fa-trash mr-1.5"></i> ລຶບ
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- แสดงข้อความเมื่อไม่พบข้อมูล -->
    <div class="py-12 px-8 text-center">
      <div class="bg-amber-50 rounded-xl py-10 max-w-md mx-auto">
        <i class="fas fa-pray text-5xl mb-4 text-amber-300"></i>
        <p class="text-amber-800 mb-4">ບໍ່ພົບຂໍໍາູນພະສົງ</p>
        <?php if (!empty($_GET['search']) || !empty($_GET['temple_id']) || (isset($_GET['status']) && $_GET['status'] !== 'active')): ?>
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
                const row = this.closest('tr');
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
    
    // เพิ่มประเภท 'info' สีฟ้า
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