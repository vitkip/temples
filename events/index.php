<?php
// filepath: c:\xampp\htdocs\temples\events\index.php
ob_start();

$page_title = 'ຈັດການກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບການຕັ້ງຄ່າຕົວກອງ temple_id
$temple_filter = isset($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;

// ກຽມຄິວລີຕາມຕົວກອງ ແລະ ສິດທິຂອງຜູ້ໃຊ້
$params = [];
$query = "SELECT e.*, t.name as temple_name FROM events e 
          LEFT JOIN temples t ON e.temple_id = t.id WHERE 1=1";

// ນໍາໃຊ້ຕົວກອງວັດ ຖ້າມີການລະບຸ
if ($temple_filter) {
    $query .= " AND e.temple_id = ?";
    $params[] = $temple_filter;
}

// ຖ້າຜູ້ໃຊ້ເປັນຜູ້ດູແລວັດ, ສະແດງສະເພາະກິດຈະກໍາໃນວັດຂອງເຂົາເທົ່ານັ້ນ
if ($_SESSION['user']['role'] === 'admin') {
    $query .= " AND e.temple_id = ?";
    $params[] = $_SESSION['user']['temple_id'];
}

// ນໍາໃຊ້ການຄົ້ນຫາຖ້າມີການລະບຸ
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

// ຈັດລຽງຕາມວັນທີກິດຈະກໍາ (ລ່າສຸດກ່ອນ)
$query .= " ORDER BY e.event_date DESC, e.event_time DESC";

// ປະຕິບັດຄິວລີ
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// ດຶງຂໍ້ມູນວັດສຳລັບ dropdown ຕົວກອງ (ຖ້າຜູ້ໃຊ້ເປັນ superadmin)
$temples = [];
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}

// ກວດສອບສິດໃນການເພີ່ມ/ແກ້ໄຂກິດຈະກໍາ
$can_add = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ຈັດການກິດຈະກໍາ</h1>
        <p class="text-sm text-gray-600">ລາຍການກິດຈະກໍາທັງໝົດ</p>
    </div>
    <?php if ($can_add): ?>
    <div>
        <a href="<?= $base_url ?>events/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
            <i class="fas fa-plus-circle mr-2"></i> ເພີ່ມກິດຈະກໍາໃໝ່
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ສ່ວນຕົວກອງ -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
    <div class="p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- ຄົ້ນຫາ -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            
            <!-- ຕົວກອງວັດ (ສະເພາະ superadmin) -->
            <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
            <div>
                <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ</label>
                <select name="temple_id" id="temple_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                <a href="<?= $base_url ?>events/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg ml-2 transition">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ລາຍການກິດຈະກຳ -->
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

    <?php if (count($events) > 0): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ກິດຈະກຳ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ & ເວລາ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານທີ່</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ພະສົງ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach($events as $event): 
                // ຄົ້ນຫາຈຳນວນພະສົງທີ່ເຂົ້າຮ່ວມກິດຈະກຳ
                $monk_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_monk WHERE event_id = ?");
                $monk_stmt->execute([$event['id']]);
                $monk_count = $monk_stmt->fetchColumn();
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></div>
                    <div class="text-sm text-gray-500 line-clamp-1"><?= htmlspecialchars($event['description']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($event['event_date'])) ?></div>
                    <div class="text-sm text-gray-500"><?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ</div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($event['location'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($event['temple_name'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-500">
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                            <?= $monk_count ?> ອົງ
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-2">
                        <a href="<?= $base_url ?>events/view.php?id=<?= $event['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if ($_SESSION['user']['role'] === 'superadmin' || ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id'])): ?>
                        <a href="<?= $base_url ?>events/edit.php?id=<?= $event['id'] ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-event" data-id="<?= $event['id'] ?>" data-title="<?= htmlspecialchars($event['title']) ?>">
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
        <i class="fas fa-calendar-times text-4xl mb-4 text-gray-300"></i>
        <p>ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ</p>
        <?php if (!empty($_GET['search']) || !empty($_GET['temple_id'])): ?>
        <a href="<?= $base_url ?>events/" class="inline-block mt-2 text-indigo-600 hover:text-indigo-800">ລຶບຕົວກອງທັງໝົດ</a>
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
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບກິດຈະກໍາ <span id="deleteEventTitleDisplay" class="font-medium"></span> ແທ້ບໍ່?</p>
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
    const deleteEventTitleDisplay = document.getElementById('deleteEventTitleDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບ
    document.querySelectorAll('.delete-event').forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.getAttribute('data-id');
            const eventTitle = this.getAttribute('data-title');
            
            // ຕັ້ງຊື່ກິດຈະກໍາໃນ modal
            deleteEventTitleDisplay.textContent = eventTitle;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmDelete.href = '<?= $base_url ?>events/delete.php?id=' + eventId;
            
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
ob_end_flush();
require_once '../includes/footer.php';
?>