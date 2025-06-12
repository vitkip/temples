<?php
// filepath: c:\xampp\htdocs\temples\monks\view.php
ob_start(); // เพิ่ม output buffering เพื่อป้องกัน headers already sent

$page_title = 'ລາຍລະອຽດພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

$monk_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນພະສົງພ້ອມກັບຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT m.*, t.name as temple_name, t.district, t.province 
    FROM monks m
    LEFT JOIN temples t ON m.temple_id = t.id
    WHERE m.id = ?
");
$stmt->execute([$monk_id]);
$monk = $stmt->fetch();

if (!$monk) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນພະສົງ";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ກວດສອບສິດໃນການແກ້ໄຂ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || 
            ($_SESSION['user']['role'] === 'admin' && 
             $_SESSION['user']['temple_id'] == $monk['temple_id']));
?>

<!-- เพิ่ม CSS นี้ไว้ในส่วนหัวของ view.php หลังจาก require header.php -->
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
  
  body {
    background-color: var(--color-lightest);
    color: #5a4631;
  }
  
  .page-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem;
  }
  
  /* สไตล์หัวเรื่อง */
  .view-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    background: linear-gradient(to right, #f3e9dd, #f5efe6);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(200, 169, 126, 0.2);
  }
  
  .monk-title {
    color: var(--color-secondary);
    font-weight: 700;
    font-size: 1.75rem;
    margin-bottom: 0.25rem;
  }
  
  /* การ์ดข้อมูล */
  .info-card {
    background-color: #fff;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(200, 169, 126, 0.2);
    overflow: hidden;
    margin-bottom: 1.5rem;
  }
  
  .info-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(200, 169, 126, 0.1);
    background: linear-gradient(to right, rgba(200, 169, 126, 0.08), rgba(212, 182, 143, 0.05));
  }
  
  .info-card-title {
    color: var(--color-secondary);
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  
  .info-card-body {
    padding: 1.5rem;
  }
  
  /* รูปภาพพระสงฆ์ */
  .monk-image-container {
    width: 100%;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
  }
  
  .monk-image-container img {
    width: 100%;
    height: auto;
    object-fit: cover;
  }
  
  .monk-image-placeholder {
    width: 100%;
    aspect-ratio: 1 / 1;
    background: linear-gradient(135deg, #f3e9dd, #e5d9c8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary-dark);
    font-size: 4rem;
    border-radius: var(--border-radius);
  }
  
  /* รายละเอียดข้อมูล */
  .info-label {
    display: block;
    font-size: 0.875rem;
    color: #8d7766;
    margin-bottom: 0.25rem;
  }
  
  .info-value {
    font-weight: 500;
    color: #453525;
    margin-bottom: 1rem;
  }
  
  /* ปุ่มกด */
  .btn {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s;
    gap: 0.5rem;
  }
  
  .btn-back {
    background-color: #f0e6d9;
    color: var(--color-secondary);
    box-shadow: 0 2px 4px rgba(162, 132, 85, 0.1);
  }
  
  .btn-back:hover {
    background-color: #e5d9c8;
    transform: translateY(-1px);
  }
  
  .btn-edit {
    background: linear-gradient(to bottom right, var(--color-primary), var(--color-primary-dark));
    color: #fff;
    box-shadow: 0 2px 4px rgba(162, 132, 85, 0.2);
  }
  
  .btn-edit:hover {
    background: linear-gradient(to bottom right, #d4b68f, #bb9c6a);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(162, 132, 85, 0.3);
  }
  
  /* สถานะพระ */
  .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
  }
  
  .status-active {
    background-color: rgba(126, 159, 126, 0.15);
    color: #4d7a4d;
    border: 1px solid rgba(126, 159, 126, 0.3);
  }
  
  .status-inactive {
    background-color: rgba(169, 169, 169, 0.15);
    color: #696969;
    border: 1px solid rgba(169, 169, 169, 0.3);
  }
  
  /* ไอคอนการ์ด */
  .icon-circle {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-right: 1rem;
  }
  
  .icon-circle.amber {
    background-color: #FEF3C7;
    color: #92400E;
  }
  
  .icon-circle.indigo {
    background-color: #E0E7FF;
    color: #4338CA;
  }
  
  .icon-circle.green {
    background-color: #D1FAE5;
    color: #047857;
  }
  
  .icon-circle.blue {
    background-color: #DBEAFE;
    color: #1D4ED8;
  }
  
  /* ปรับให้รองรับการแสดงผลบนอุปกรณ์พกพา */
  @media (max-width: 768px) {
    .view-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    
    .info-grid {
      grid-template-columns: 1fr !important;
    }
    
    .page-sidebar {
      order: -1; /* แสดงก่อนส่วนเนื้อหาบนมือถือ */
      margin-bottom: 1.5rem;
    }
  }
  
  /* Background pattern */
  .bg-temple-pattern {
    background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgb3BhY2l0eT0iMC4wNSI+PHBhdGggZD0iTTMwIDMwIEwwIDYwIEw2MCA2MCBaIiBmaWxsPSIjQzhhOTdlIi8+PC9zdmc+');
    background-repeat: repeat;
  }
</style>

<!-- แทนที่เนื้อหา HTML เดิมด้วยเนื้อหาที่มีการออกแบบใหม่ -->
<div class="page-container bg-temple-pattern">
  <!-- ส่วนหัวของหน้า -->
  <div class="view-header">
    <div>
      <h1 class="monk-title">
        <?= htmlspecialchars($monk['prefix']) ?> <?= htmlspecialchars($monk['name']) ?>
      </h1>
      <?php if (!empty($monk['lay_name'])): ?>
      <p class="text-amber-700">ນາມສະກຸນ: <?= htmlspecialchars($monk['lay_name']) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex space-x-2">
      <a href="<?= $base_url ?>monks/" class="btn btn-back">
        <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
      </a>
      
      <?php if ($can_edit): ?>
      <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" class="btn btn-edit">
        <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['success'])): ?>
  <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນສຳເລັດ -->
  <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
    <div class="flex">
      <div class="flex-shrink-0">
        <i class="fas fa-check-circle text-green-500"></i>
      </div>
      <div class="ml-3">
        <p class="text-sm text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- ຂໍ້ມູນພະສົງ - ส่วนซ้าย/บน -->
    <div class="lg:col-span-2 space-y-6">
      
      <!-- ຂໍ້ມູນພື້ນຖານ -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">
            <i class="fas fa-user-circle text-amber-600"></i> ຂໍ້ມູນພື້ນຖານ
          </h2>
        </div>
        <div class="info-card-body">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 info-grid">
            <div>
              <span class="info-label">ວັດ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-place-of-worship text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['temple_name'] ?? '-') ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ພັນສາ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-dharmachakra text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['pansa'] ?? '-') ?> ພັນສາ
              </p>
            </div>
            
            <div>
              <span class="info-label">ຕຳແໜ່ງ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-user-shield text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['position'] ?? '-') ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ວັນເດືອນປີເກີດ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-birthday-cake text-amber-500 mr-2"></i>
                <?= $monk['birth_date'] ? date('d/m/Y', strtotime($monk['birth_date'])) : '-' ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ວັນບວດ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-calendar-alt text-amber-500 mr-2"></i>
                <?= $monk['ordination_date'] ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-' ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ເບີໂທຕິດຕໍ່</span>
              <p class="info-value flex items-center">
                <i class="fas fa-phone-alt text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['contact_number'] ?? '-') ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ສະຖານະ</span>
              <p class="info-value">
                <?php if($monk['status'] === 'active'): ?>
                  <span class="status-badge status-active">
                    <i class="fas fa-circle text-xs mr-1"></i> ບວດຢູ່
                  </span>
                <?php else: ?>
                  <span class="status-badge status-inactive">
                    <i class="fas fa-circle text-xs mr-1"></i> ສິກແລ້ວ
                  </span>
                <?php endif; ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ວັນທີປັບປຸງຂໍ້ມູນລ່າສຸດ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-clock text-amber-500 mr-2"></i>
                <?= date('d/m/Y H:i', strtotime($monk['updated_at'])) ?>
              </p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- ຂໍ້ມູນການສຶກສາ -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">
            <i class="fas fa-graduation-cap text-amber-600"></i> ຂໍ້ມູນການສຶກສາ
          </h2>
        </div>
        <div class="info-card-body">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 info-grid">
            <div>
              <span class="info-label">ການສຶກສາສາມັນ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-book text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['education'] ?? '-') ?>
              </p>
            </div>
            
            <div>
              <span class="info-label">ການສຶກສາທາງທຳ</span>
              <p class="info-value flex items-center">
                <i class="fas fa-book-open text-amber-500 mr-2"></i>
                <?= htmlspecialchars($monk['dharma_education'] ?? '-') ?>
              </p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- ຂໍ້ມູນວັດ -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">
            <i class="fas fa-gopuram text-amber-600"></i> ຂໍ້ມູນວັດ
          </h2>
        </div>
        <div class="info-card-body">
          <div class="flex items-center mb-4">
            <div class="icon-circle amber">
              <i class="fas fa-gopuram"></i>
            </div>
            <div>
              <h3 class="text-lg font-medium text-amber-900">
                <?= htmlspecialchars($monk['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?>
              </h3>
              <p class="text-sm text-amber-700">
                <?= htmlspecialchars($monk['district'] ?? '') ?>
                <?= !empty($monk['district']) && !empty($monk['province']) ? ', ' : '' ?>
                <?= htmlspecialchars($monk['province'] ?? '') ?>
              </p>
            </div>
          </div>
          
          <?php if (!empty($monk['temple_id'])): ?>
          <div class="mt-4">
            <a href="<?= $base_url ?>temples/view.php?id=<?= $monk['temple_id'] ?>" 
               class="text-amber-700 hover:text-amber-900 inline-flex items-center 
               border border-amber-200 hover:border-amber-300 px-4 py-2 rounded-lg transition">
              <i class="fas fa-gopuram mr-2"></i>
              <span>ເບິ່ງລາຍລະອຽດວັດ</span>
              <i class="fas fa-arrow-right ml-2 text-sm"></i>
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ສ່ວນແຖບຂ້າງ - ส่วนขวา/ล่าง -->
    <div class="space-y-6 page-sidebar">
      <!-- ຮູບພາບພະສົງ -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">
            <i class="fas fa-image text-amber-600"></i> ຮູບພາບ
          </h2>
        </div>
        <div class="info-card-body">
          <?php if (!empty($monk['photo']) && $monk['photo'] != 'uploads/monks/default.png'): ?>
            <div class="monk-image-container">
              <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="rounded-lg shadow-sm">
            </div>
          <?php else: ?>
            <div class="monk-image-placeholder">
              <i class="fas fa-user-circle"></i>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ຂໍ້ມູນໂດຍຫຍໍ້ -->
      <div class="info-card">
        <div class="info-card-header">
          <h2 class="info-card-title">
            <i class="fas fa-info-circle text-amber-600"></i> ຂໍ້ມູນໂດຍຫຍໍ້
          </h2>
        </div>
        <div class="info-card-body">
          <div class="space-y-4">
            <?php if (!empty($monk['pansa'])): ?>
            <div class="flex items-center">
              <div class="icon-circle amber">
                <i class="fas fa-dharmachakra"></i>
              </div>
              <div>
                <p class="text-sm text-gray-500">ພັນສາ</p>
                <p class="font-medium text-amber-900"><?= htmlspecialchars($monk['pansa']) ?> ພັນສາ</p>
              </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($monk['ordination_date'])): ?>
            <div class="flex items-center">
              <div class="icon-circle indigo">
                <i class="fas fa-calendar-alt"></i>
              </div>
              <div>
                <p class="text-sm text-gray-500">ບວດເມື່ອ</p>
                <p class="font-medium text-indigo-900"><?= date('d/m/Y', strtotime($monk['ordination_date'])) ?></p>
              </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($monk['birth_date'])): ?>
            <div class="flex items-center">
              <div class="icon-circle green">
                <i class="fas fa-birthday-cake"></i>
              </div>
              <div>
                <p class="text-sm text-gray-500">ອາຍຸ</p>
                <p class="font-medium text-green-900">
                  <?php
                  $birth = new DateTime($monk['birth_date']);
                  $now = new DateTime();
                  $age = $birth->diff($now)->y;
                  echo $age . ' ປີ';
                  ?>
                </p>
              </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($monk['position'])): ?>
            <div class="flex items-center">
              <div class="icon-circle blue">
                <i class="fas fa-user-shield"></i>
              </div>
              <div>
                <p class="text-sm text-gray-500">ຕຳແໜ່ງ</p>
                <p class="font-medium text-blue-900"><?= htmlspecialchars($monk['position']) ?></p>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>