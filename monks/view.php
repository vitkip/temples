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

// ດຶງຂໍ້ມູນພະສົງພ້ອມກັບຂໍ້ມູນວັດ - ปรับ query ให้ใช้ JOIN กับ districts และ provinces
$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        t.name as temple_name,
        d.district_name,
        p.province_name
    FROM monks m
    LEFT JOIN temples t ON m.temple_id = t.id
    LEFT JOIN districts d ON t.district_id = d.district_id
    LEFT JOIN provinces p ON t.province_id = p.province_id
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
                <?php
                  if (!empty($monk['ordination_date'])) {
                  $ordination = new DateTime($monk['ordination_date']);
                  $now = new DateTime();
                  $years = $ordination->diff($now)->y;
                  echo $years . ' ພັນສາ';
                  } else {
                  echo htmlspecialchars($monk['pansa']) . ' ພັນສາ';
                  }
                  ?>
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
            
            <!-- เพิ่มหลังจากส่วนแสดงวันเดือนปีเกิด -->
            <div>
                <span class="info-label">ແຂວງເກີດ</span>
                <p class="info-value flex items-center">
                    <i class="fas fa-map-marker-alt text-amber-500 mr-2"></i>
                    <?= htmlspecialchars($monk['birth_province'] ?? '-') ?>
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
                <?= htmlspecialchars($monk['district_name'] ?? '') ?>
                <?= !empty($monk['district_name']) && !empty($monk['province_name']) ? ', ' : '' ?>
                <?= htmlspecialchars($monk['province_name'] ?? '') ?>
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
                <p class="font-medium text-amber-900">
                  <?php
                  if (!empty($monk['ordination_date'])) {
                  $ordination = new DateTime($monk['ordination_date']);
                  $now = new DateTime();
                  $years = $ordination->diff($now)->y;
                  echo $years . ' ພັນສາ';
                  } else {
                  echo htmlspecialchars($monk['pansa']) . ' ພັນສາ';
                  }
                  ?>
                </p>
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