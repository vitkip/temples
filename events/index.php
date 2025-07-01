<?php
// filepath: c:\xampp\htdocs\temples\events\index.php
ob_start();

$page_title = 'ຈັດການກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// --- Existing PHP Logic (No changes needed here) ---
$temple_filter = isset($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;
$params = [];
$query = "SELECT e.*, t.name as temple_name, t.id as temple_id, p.province_name 
          FROM events e 
          LEFT JOIN temples t ON e.temple_id = t.id
          LEFT JOIN provinces p ON t.province_id = p.province_id
          WHERE 1=1";

if ($_SESSION['user']['role'] === 'superadmin') {
    if ($temple_filter) {
        $query .= " AND e.temple_id = ?";
        $params[] = $temple_filter;
    }
} elseif ($_SESSION['user']['role'] === 'province_admin') {
    $province_stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $province_ids = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($province_ids) {
        $in = str_repeat('?,', count($province_ids) - 1) . '?';
        $query .= " AND t.province_id IN ($in)";
        $params = array_merge($params, $province_ids);
    } else {
        $query .= " AND 0";
    }
    if ($temple_filter) {
        $query .= " AND e.temple_id = ?";
        $params[] = $temple_filter;
    }
} elseif ($_SESSION['user']['role'] === 'admin') {
    $query .= " AND e.temple_id = ?";
    $params[] = $_SESSION['user']['temple_id'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $params = array_merge($params, [$search, $search, $search]);
}

$query .= " ORDER BY e.event_date DESC, e.event_time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

$temples = [];
if ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'province_admin') {
    $temple_query = "SELECT id, name FROM temples WHERE status = 'active'";
    $temple_params = [];
    if ($_SESSION['user']['role'] === 'province_admin' && !empty($province_ids)) {
        $in = str_repeat('?,', count($province_ids) - 1) . '?';
        $temple_query .= " AND province_id IN ($in)";
        $temple_params = $province_ids;
    }
    $temple_query .= " ORDER BY name";
    $temple_stmt = $pdo->prepare($temple_query);
    $temple_stmt->execute($temple_params);
    $temples = $temple_stmt->fetchAll();
}

$can_add = in_array($_SESSION['user']['role'], ['superadmin', 'admin', 'province_admin']);
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="header-section flex flex-col md:flex-row justify-between items-start md:items-center mb-6 p-6 rounded-lg" style="animation: fadeInUp 0.5s ease-out forwards;">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">ບັນທືກກິດນິມົນ</h1>
            <p class="text-sm text-gray-600 mt-1">ລາຍການກິດນິມົນທັງໝົດໃນລະບົບ</p>
        </div>
        <?php if ($can_add): ?>
        <a href="<?= $base_url ?>events/add.php" class="btn btn-primary mt-4 md:mt-0">
            <i class="fas fa-plus-circle"></i> ເພີ່ມກິດນິມົນໃໝ່
        </a>
        <?php endif; ?>
    </div>

    <!-- Filter Section -->
    <div class="filter-section bg-white rounded-lg shadow-sm mb-6 p-4 md:p-6" style="animation: fadeInUp 0.5s 0.1s ease-out forwards; opacity: 0;">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            <div class="lg:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-input w-full" placeholder="ຊື່ກິດຈະກຳ, ສະຖານທີ່...">
            </div>
            
            <?php if ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'province_admin'): ?>
            <div>
                <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ຕົວກອງຕາມວັດ</label>
                <select name="temple_id" id="temple_id" class="form-select w-full">
                    <option value="">-- ທຸກວັດ --</option>
                    <?php foreach($temples as $temple): ?>
                    <option value="<?= $temple['id'] ?>" <?= ($temple_filter == $temple['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($temple['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="flex space-x-2">
                <button type="submit" class="btn btn-primary w-full md:w-auto">
                    <i class="fas fa-search"></i> ຄົ້ນຫາ
                </button>
                <a href="<?= $base_url ?>events/" class="btn btn-secondary" title="ລ້າງຕົວກອງ">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-r-lg shadow-sm"><p class="text-sm text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm"><p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p></div>
    <?php endif; ?>

    <!-- Events List -->
    <div class="data-table-container" style="animation: fadeInUp 0.5s 0.2s ease-out forwards; opacity: 0;">
        <?php if (count($events) > 0): ?>
        
        <!-- Desktop Table -->
        <div class="hidden md:block bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full">
                <thead class="table-header">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ກິດຈະກຳ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ & ເວລາ</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ / ສະຖານທີ່</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">ພະສົງ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach($events as $event): 
                        $monk_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_monk WHERE event_id = ?");
                        $monk_stmt->execute([$event['id']]);
                        $monk_count = $monk_stmt->fetchColumn();
                    ?>
                    <tr class="table-row hover:bg-gray-50">
                        <td class="px-6 py-4"><div class="font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></div></td>
                        <td class="px-6 py-4"><div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($event['event_date'])) ?> <?= date('H:i', strtotime($event['event_time'])) ?></div></td>
                        <td class="px-6 py-4"><div class="text-sm text-gray-500"><?= htmlspecialchars($event['temple_name'] ?? '-') ?><br><small><?= htmlspecialchars($event['location'] ?? '') ?></small></div></td>
                        <td class="px-6 py-4 text-center"><span class="status-badge status-active"><?= $monk_count ?> ອົງ</span></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end space-x-2">
                                <a href="<?= $base_url ?>events/view.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i></a>
                                <?php if (in_array($_SESSION['user']['role'], ['superadmin', 'province_admin']) || ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id'])): ?>
                                <a href="<?= $base_url ?>events/edit.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                <a href="javascript:void(0)" class="btn btn-sm btn-danger delete-event" data-id="<?= $event['id'] ?>" data-title="<?= htmlspecialchars($event['title']) ?>"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Cards -->
        <div class="md:hidden grid grid-cols-1 gap-4">
            <?php foreach($events as $event): 
                $monk_stmt = $pdo->prepare("SELECT COUNT(*) FROM event_monk WHERE event_id = ?");
                $monk_stmt->execute([$event['id']]);
                $monk_count = $monk_stmt->fetchColumn();
            ?>
            <div class="card bg-white p-4">
                <div class="font-semibold text-lg mb-2 text-gray-800"><?= htmlspecialchars($event['title']) ?></div>
                <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($event['description']) ?></p>
                
                <div class="border-t border-gray-100 pt-3 space-y-2">
                    <div class="flex items-center text-sm"><i class="fas fa-calendar-alt w-5 text-gray-400"></i><span><?= date('d/m/Y, H:i', strtotime($event['event_date'] . ' ' . $event['event_time'])) ?></span></div>
                    <div class="flex items-center text-sm"><i class="fas fa-place-of-worship w-5 text-gray-400"></i><span><?= htmlspecialchars($event['temple_name'] ?? '-') ?></span></div>
                    <div class="flex items-center text-sm"><i class="fas fa-map-marker-alt w-5 text-gray-400"></i><span><?= htmlspecialchars($event['location'] ?? '-') ?></span></div>
                    <div class="flex items-center text-sm"><i class="fas fa-users w-5 text-gray-400"></i><span class="status-badge status-active ml-1"><?= $monk_count ?> ອົງ</span></div>
                </div>
                
                <div class="flex justify-end space-x-2 pt-4 mt-3 border-t border-gray-100">
                    <a href="<?= $base_url ?>events/view.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-secondary">ເບິ່ງ</a>
                    <?php if (in_array($_SESSION['user']['role'], ['superadmin', 'province_admin']) || ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $event['temple_id'])): ?>
                    <a href="<?= $base_url ?>events/edit.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-info">ແກ້ໄຂ</a>
                    <a href="javascript:void(0)" class="btn btn-sm btn-danger delete-event" data-id="<?= $event['id'] ?>" data-title="<?= htmlspecialchars($event['title']) ?>">ລຶບ</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="text-center py-12 bg-white rounded-lg shadow-sm">
            <i class="fas fa-calendar-times text-4xl mb-4 text-gray-300"></i>
            <h3 class="text-lg font-medium text-gray-700">ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ</h3>
            <p class="text-sm text-gray-500 mt-1">ລອງປ່ຽນຄໍາຄົ້ນຫາ ຫຼື ລ້າງຕົວກອງ</p>
            <a href="<?= $base_url ?>events/" class="btn btn-secondary mt-4">ລ້າງຕົວກອງທັງໝົດ</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal (No changes needed, but can be styled further) -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-60 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl" style="animation: fadeInUp 0.3s ease-out forwards;">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">ຢືນຢັນການລຶບຂໍ້ມູນ</h3>
        <p class="text-gray-700">ທ່ານຕ້ອງການລຶບກິດຈະກໍາ <strong id="deleteEventTitleDisplay" class="text-red-600"></strong> ແທ້ບໍ່?</p>
        <p class="text-sm text-gray-500 mt-1">ຂໍ້ມູນທີ່ຖືກລຶບບໍ່ສາມາດກູ້ຄືນໄດ້.</p>
        <div class="flex justify-end space-x-3 mt-6">
            <button id="cancelDelete" class="btn btn-secondary">ຍົກເລີກ</button>
            <a id="confirmDelete" href="#" class="btn btn-danger">ຢືນຢັນການລຶບ</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    const deleteEventTitleDisplay = document.getElementById('deleteEventTitleDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    const cancelDelete = document.getElementById('cancelDelete');
    
    document.querySelectorAll('.delete-event').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const eventId = this.getAttribute('data-id');
            const eventTitle = this.getAttribute('data-title');
            deleteEventTitleDisplay.textContent = `"${eventTitle}"`;
            confirmDelete.href = '<?= $base_url ?>events/delete.php?id=' + eventId;
            deleteModal.classList.remove('hidden');
        });
    });
    
    function closeModal() {
        deleteModal.classList.add('hidden');
    }

    cancelDelete.addEventListener('click', closeModal);
    deleteModal.addEventListener('click', function(e) {
        if (e.target === deleteModal) {
            closeModal();
        }
    });
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>