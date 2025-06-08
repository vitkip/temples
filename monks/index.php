<?php
// filepath: c:\xampp\htdocs\temples\monks\index.php
ob_start(); // เพิ่ม output buffering เพื่อป้องกัน headers already sent

$page_title = 'ຈັດການພະສົງ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບການຕັ້ງຄ່າຕົວກອງ temple_id
$temple_filter = isset($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;

// ກຽມຄິວລີຕາມຕົວກອງ ແລະ ສິດທິຂອງຜູ້ໃຊ້
$params = [];
$query = "SELECT m.*, t.name as temple_name FROM monks m 
          LEFT JOIN temples t ON m.temple_id = t.id WHERE 1=1";

// ນໍາໃຊ້ຕົວກອງວັດ ຖ້າມີການລະບຸ
if ($temple_filter) {
    $query .= " AND m.temple_id = ?";
    $params[] = $temple_filter;
}

// ຖ້າຜູ້ໃຊ້ເປັນຜູ້ດູແລວັດ, ສະແດງສະເພາະພະສົງໃນວັດຂອງເຂົາເທົ່ານັ້ນ
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
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
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

// ດຶງຂໍ້ມູນວັດສຳລັບ dropdown ຕົວກອງ (ຖ້າຜູ້ໃຊ້ເປັນ superadmin)
$temples = [];
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}

// ກວດສອບສິດໃນການເພີ່ມ/ແກ້ໄຂພະສົງ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ຈັດການພະສົງ</h1>
        <p class="text-sm text-gray-600">ລາຍການພະສົງທັງໝົດ</p>
    </div>
    <?php if ($can_edit): ?>
    <div>
        <a href="<?= $base_url ?>monks/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
            <i class="fas fa-plus-circle mr-2"></i> ເພີ່ມພະສົງໃໝ່
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ສ່ວນຕົວກອງ -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <!-- ຄົ້ນຫາ -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <!-- ຕົວກອງສະຖານະ -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <select name="status" id="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all" <?= isset($_GET['status']) && $_GET['status'] === 'all' ? 'selected' : '' ?>>ທັງໝົດ</option>
                    <option value="active" <?= (!isset($_GET['status']) || $_GET['status'] === 'active') ? 'selected' : '' ?>>ບວດຢູ່</option>
                    <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>ສິກແລ້ວ</option>
                </select>
            </div>
            
            <!-- ຕົວກອງວັດ (ສະເພາະ superadmin) -->
            <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
            <div>
                <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ</label>
                <select name="temple_id" id="temple_id" class="temple-select block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- ທັງໝົດ --</option>
                    <?php foreach($temples as $temple): ?>
                    <option value="<?= $temple['id'] ?>" <?= isset($_GET['temple_id']) && $_GET['temple_id'] == $temple['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($temple['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <!-- ປຸ່ມສົ່ງຄົ້ນຫາ -->
            <div class="self-end">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
                <a href="<?= $base_url ?>monks/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg ml-2 transition">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ຕາຕະລາງລາຍການພະສົງ -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <?php if (isset($_SESSION['success'])): ?>
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນສຳເລັດ -->
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
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

    <?php if (isset($_SESSION['error'])): ?>
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (count($monks) > 0): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຮູບພາບ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ພະສົງ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ພັນສາ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach($monks as $monk): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="flex-shrink-0 h-10 w-10">
                        <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                            <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['name']) ?>" class="w-10 h-10 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <i class="fas fa-user text-indigo-600"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($monk['name']) ?></div>
                    <?php if (!empty($monk['lay_name'])): ?> <!-- แก้ไขจาก buddhist_name เป็น lay_name -->
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($monk['lay_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500"><?= htmlspecialchars($monk['pansa'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500"><?= htmlspecialchars($monk['temple_name'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <?php if($monk['status'] === 'active'): ?>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">ບວດຢູ່</span>
                    <?php else: ?>
                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">ສິກແລ້ວ</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-2">
                        <a href="<?= $base_url ?>monks/view.php?id=<?= $monk['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if ($can_edit && ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['temple_id'] == $monk['temple_id'])): ?>
                        <a href="<?= $base_url ?>monks/edit.php?id=<?= $monk['id'] ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-monk" data-id="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <!-- ສະແດງຂໍ້ຄວາມເມື່ອບໍ່ພົບຂໍ້ມູນ -->
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-pray text-4xl mb-4 text-gray-300"></i>
        <p>ບໍ່ພົບຂໍ້ມູນພະສົງ</p>
        <?php if (!empty($_GET['search']) || !empty($_GET['temple_id']) || (isset($_GET['status']) && $_GET['status'] !== 'active')): ?>
        <a href="<?= $base_url ?>monks/" class="inline-block mt-2 text-indigo-600 hover:text-indigo-800">ລຶບຕົວກອງທັງໝົດ</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal ຢືນຢັນການລຶບ -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການລຶບຂໍ້ມູນ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບຂໍ້ມູນພະສົງ <span id="deleteMonkNameDisplay" class="font-medium"></span> ແທ້ບໍ່?</p>
            <p class="text-sm text-red-600 mt-2">ຂໍ້ມູນທີ່ຖືກລຶບບໍ່ສາມາດກູ້ຄືນໄດ້.</p>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button id="cancelDelete" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <a id="confirmDelete" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- JavaScript ສຳລັບການຢືນຢັນການລຶບ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    const deleteMonkNameDisplay = document.getElementById('deleteMonkNameDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບ
    document.querySelectorAll('.delete-monk').forEach(button => {
        button.addEventListener('click', function() {
            const monkId = this.getAttribute('data-id');
            const monkName = this.getAttribute('data-name');
            
            // ຕັ້ງຊື່ພະສົງໃນ modal
            deleteMonkNameDisplay.textContent = monkName;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmDelete.href = '<?= $base_url ?>monks/delete.php?id=' + monkId;
            
            // ສະແດງ modal
            deleteModal.classList.remove('hidden');
        });
    });
    
    // ປິດ modal
    document.querySelectorAll('.close-modal, #cancelDelete').forEach(element => {
        element.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
    });
});
</script>

<?php
// ສິ້ນສຸດການ buffer ທີ່ທ້າຍຂອງໄຟລ໌
ob_end_flush();
require_once '../includes/footer.php';
?>