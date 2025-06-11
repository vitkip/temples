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

// ກວດສອບສິດໃນການເພີ່ມ/ແກ້ໄຂພະສົງ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- ສ່ວນຫົວຂອງໜ້າ (ปรับปรุง) -->
<div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0 mb-8">
    <div>
        <h1 class="text-3xl font-bold text-indigo-800 flex items-center">
            <i class="fas fa-users-class mr-3"></i> ຈັດການພະສົງ
        </h1>
        <p class="text-sm text-gray-600 mt-1">ຈັດການຂໍໍາູນທັງໝົດຂອງພະສົງ</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <!-- เพิ่มปุ่มส่งออก PDF -->
        <a href="<?= $base_url ?>reports/generate_pdf_monks.php" target="_blank" 
           class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex items-center transition duration-200 shadow-md">
            <i class="fas fa-file-pdf mr-2"></i> ສົ່ງອອກ PDF
        </a>
        
        <?php if ($can_edit): ?>
        <a href="<?= $base_url ?>monks/add.php" 
           class="bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white py-2 px-4 rounded-lg flex items-center transition duration-200 shadow-md">
            <i class="fas fa-plus-circle mr-2"></i> ເພີ່ມພະສົງໃໝ່
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ສ່ວນຕົວກອງ (ปรับปรุง) -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6 border border-gray-100">
    <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-indigo-800">
            <i class="fas fa-filter mr-2"></i> ຕົວກອງຂໍໍາູນ
        </h2>
    </div>
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- ค้นหา -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-search text-indigo-600 mr-1"></i> ຄົ້ນຫາ
                </label>
                <input type="text" name="search" id="search" 
                       value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" 
                       placeholder="ພິມຊື່ພະສົງ..." 
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition">
            </div>
            <!-- ตัวกรอง prefix -->
            <div>
                <label for="prefix" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-user-tag text-indigo-600 mr-1"></i> ຄຳນຳໜ້າ
                </label>
                <select name="prefix" id="prefix" 
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition">
                    <option value="">-- ທັງໝົດ --</option>
                    <option value="ພະ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ພະ' ? 'selected' : '' ?>>ພະ</option>
                    <option value="ຄຸນແມ່ຂາວ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                    <option value="ສ.ນ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                    <option value="ພະອາຈານ" <?= isset($_GET['prefix']) && $_GET['prefix'] === 'ສັງກະລີ' ? 'selected' : '' ?>>ສັງກະລີ</option>
                </select>
            </div>
            <!-- ตัวกรองสถานะ -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-toggle-on text-indigo-600 mr-1"></i> ສະຖານະ
                </label>
                <select name="status" id="status" 
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition">
                    <option value="all" <?= isset($_GET['status']) && $_GET['status'] === 'all' ? 'selected' : '' ?>>ທັງໝົດ</option>
                    <option value="active" <?= (!isset($_GET['status']) || $_GET['status'] === 'active') ? 'selected' : '' ?>>ບວດຢູ່</option>
                    <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                </select>
            </div>
            
            <!-- ตัวกรองวัด (เฉพาะ superadmin) -->
            <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
            <div>
                <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">
                    <i class="fas fa-place-of-worship text-indigo-600 mr-1"></i> ວັດ
                </label>
                <select name="temple_id" id="temple_id" 
                        class="temple-select block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition">
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
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 px-5 rounded-lg transition duration-200 shadow flex-grow flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                    </button>
                    <a href="<?= $base_url ?>monks/" 
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 px-3 rounded-lg transition duration-200 shadow flex items-center justify-center" 
                       title="ລ້າງຕົວກອງທັງໝົດ">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ตารางรายการพระสงฆ์ (ปรับปรุง) -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
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
    <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <div class="text-gray-600">
                <i class="fas fa-users-class mr-2"></i> ພົບຂໍ້ມູນ <span class="font-semibold text-indigo-700"><?= count($monks) ?></span> ລາຍການ
            </div>
            <!-- เพิ่มปุ่มส่งออก PDF ซ้ำ -->
            <a href="<?= $base_url ?>reports/generate_excel_monks.php" target="_blank" 
               class="text-indigo-600 hover:text-indigo-800 text-sm flex items-center">
                <i class="fas fa-file-export mr-1"></i> ສົ່ງອອກ Excel
            </a>
        </div>
    </div>

    <?php if (count($monks) > 0): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gradient-to-r from-indigo-50 to-blue-50">
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ຮູບພາບ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ຄຳນຳໜ້າ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ຊື່ ແລະ ນາມສະກຸນ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ພັນສາ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ວັດ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ສະຖານະ</th>
                    <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ຈັດການ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach($monks as $monk): ?>
                <tr class="hover:bg-gray-50 transition duration-150">
                    <td class="px-6 py-4">
                        <div class="flex-shrink-0 h-12 w-12">
                            <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                                <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" 
                                     class="w-12 h-12 rounded-full object-cover shadow-sm border-2 border-indigo-100">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-indigo-100 to-blue-100 flex items-center justify-center shadow-sm border-2 border-indigo-100">
                                    <i class="fas fa-user text-indigo-500"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                       <td class="px-6 py-4">
                        <div class="text-gray-500 font-medium"><?= htmlspecialchars($monk['prefix'] ?? '-') ?> <span class="text-xs"></span></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900 hover:text-indigo-700">
                            <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>"><?= htmlspecialchars($monk['name']) ?></a>
                        </div>
                        <?php if (!empty($monk['lay_name'])): ?>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-gray-500 font-medium"><?= htmlspecialchars($monk['pansa'] ?? '-') ?> <span class="text-xs">ພັນສາ</span></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-gray-700 flex items-center">
                            <i class="fas fa-place-of-worship text-gray-400 mr-1.5 text-xs"></i>
                            <?= htmlspecialchars($monk['temple_name'] ?? '-') ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
        <button type="button" class="toggle-status-btn w-full text-left" data-monk-id="<?= $monk['id'] ?>" data-current-status="<?= $monk['status'] ?>">
            <?php if($monk['status'] === 'active'): ?>
                <span class="status-badge bg-green-100 text-green-800 text-xs font-medium px-2.5 py-1 rounded-full border border-green-200 hover:bg-green-200 transition-all duration-200">
                    <i class="fas fa-circle text-xs mr-1 text-green-500"></i> ບວດຢູ່
                    <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                </span>
            <?php else: ?>
                <span class="status-badge bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-1 rounded-full border border-gray-200 hover:bg-gray-200 transition-all duration-200">
                    <i class="fas fa-circle text-xs mr-1 text-gray-500"></i> ສິກແລ້ວ
                    <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>
                </span>
            <?php endif; ?>
        </button>
    <?php else: ?>
        <div>
            <?php if($monk['status'] === 'active'): ?>
                <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-1 rounded-full border border-green-200">
                    <i class="fas fa-circle text-xs mr-1 text-green-500"></i> ບວດຢູ່
                </span>
            <?php else: ?>
                <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-1 rounded-full border border-gray-200">
                    <i class="fas fa-circle text-xs mr-1 text-gray-500"></i> ສິກແລ້ວ
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</td>
                    <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                        <div class="flex items-center space-x-3">
                            <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" 
                               class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-1.5 rounded-full transition">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
                            <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" 
                               class="text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 p-1.5 rounded-full transition">
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
    <?php else: ?>
    <!-- แสดงข้อความเมื่อไม่พบข้อมูล -->
    <div class="py-12 px-8 text-center">
        <div class="bg-gray-50 rounded-xl py-10 max-w-md mx-auto">
            <i class="fas fa-pray text-5xl mb-4 text-gray-300"></i>
            <p class="text-gray-500 mb-4">ບໍ່ພົບຂໍໍາູນພະສົງ</p>
            <?php if (!empty($_GET['search']) || !empty($_GET['temple_id']) || (isset($_GET['status']) && $_GET['status'] !== 'active')): ?>
            <a href="<?= $base_url ?>monks/" 
               class="inline-block mt-2 text-indigo-600 hover:text-indigo-800 border border-indigo-300 hover:border-indigo-400 px-4 py-2 rounded-lg transition">
               <i class="fas fa-redo mr-1"></i> ລຶບຕົວກອງທັງໝົດ
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal ยืนยันการลบ (ปรับปรุง) -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in backdrop-blur-sm">
    <div class="bg-white rounded-xl max-w-md w-full p-6 shadow-2xl transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> ຢືນຢັນການລຶບຂໍໍາູນ
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
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-times mr-1.5"></i> ຍົກເລີກ
            </button>
            <a id="confirmDelete" href="#" 
               class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg transition duration-200 flex items-center">
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
        const statusBadge = this.querySelector('.status-badge');
        
        // แสดงการโหลด
        const originalHTML = statusBadge.innerHTML;
        statusBadge.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງປ່ຽນ...';
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
                // อัพเดทหน้าเว็บเมื่อเปลี่ยนสถานะสำเร็จ
                if (newStatus === 'active') {
                    statusBadge.className = 'status-badge bg-green-100 text-green-800 text-xs font-medium px-2.5 py-1 rounded-full border border-green-200 hover:bg-green-200 transition-all duration-200';
                    statusBadge.innerHTML = '<i class="fas fa-circle text-xs mr-1 text-green-500"></i> ບວດຢູ່ <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>';
                } else {
                    statusBadge.className = 'status-badge bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-1 rounded-full border border-gray-200 hover:bg-gray-200 transition-all duration-200';
                    statusBadge.innerHTML = '<i class="fas fa-circle text-xs mr-1 text-gray-500"></i> ສິກແລ້ວ <i class="fas fa-exchange-alt ml-1 text-xs opacity-70"></i>';
                }
                
                // อัพเดทแอตทริบิวต์ data-current-status
                this.setAttribute('data-current-status', newStatus);
                
                // สร้าง toast notification พร้อมปุ่มให้ไปที่มุมมอง "ทั้งหมด" เมื่อเปลี่ยนสถานะ
                if ('<?= $status_filter ?>' !== 'all') {
                    createActionToast(
                        result.message, 
                        'success', 
                        'ເບິ່ງທັງໝົດ', 
                        '<?= $base_url ?>monks/?status=all'
                    );
                } else {
                    showToast(result.message, 'success');
                }
                
                // ไฮไลท์แถวที่เปลี่ยนแปลง
                const row = this.closest('tr');
                row.style.backgroundColor = '#FFEDD5'; 
                row.style.boxShadow = '0 0 8px rgba(251, 146, 60, 0.7)';

                // เพิ่มการกระพริบ
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
                // กลับสู่สถานะเดิมเมื่อเกิดข้อผิดพลาด
                statusBadge.innerHTML = originalHTML;
                showToast(result.message || 'ເກີດຂໍ້ຜິດພາດໃນການປ່ຽນສະຖານະ', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            statusBadge.innerHTML = originalHTML;
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