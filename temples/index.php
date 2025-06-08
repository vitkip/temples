<?php
$page_title = 'ຈັດການວັດ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// Filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR address LIKE ? OR abbot_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($province)) {
    $where_conditions[] = "province = ?";
    $params[] = $province;
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total for pagination
$count_query = "SELECT COUNT(*) FROM temples $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_temples = $count_stmt->fetchColumn();

$total_pages = ceil($total_temples / $limit);

// Get temples with pagination
$query = "SELECT * FROM temples $where_clause ORDER BY name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$temples = $stmt->fetchAll();

// Get provinces for filter
$province_stmt = $pdo->query("SELECT DISTINCT province FROM temples ORDER BY province");
$provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if user has edit permissions
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- Page Header -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ຈັດການວັດ</h1>
        <p class="text-sm text-gray-600">ເບິ່ງແລະຈັດການຂໍ້ມູນວັດທັງໝົດ</p>
    </div>
    <?php if ($can_edit): ?>
    <a href="<?= $base_url ?>temples/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
        <i class="fas fa-plus mr-2"></i> ເພີ່ມວັດໃໝ່
    </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ຄົ້ນຫາ</label>
            <input 
                type="text" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="ຊື່ວັດ, ທີ່ຢູ່..." 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວງ</label>
            <select 
                name="province" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">-- ທຸກແຂວງ --</option>
                <?php foreach($provinces as $prov): ?>
                <option value="<?= $prov ?>" <?= $province === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ສະຖານະ</label>
            <select 
                name="status" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">-- ທຸກສະຖານະ --</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-lg mr-2 transition">
                <i class="fas fa-filter mr-1"></i> ຕັງຄ່າຟິວເຕີ
            </button>
            
            <a href="<?= $base_url ?>temples/" class="bg-gray-100 hover:bg-gray-200 text-gray-800 py-2 px-4 rounded-lg transition">
                <i class="fas fa-sync-alt mr-1"></i> ລ້າງ
            </a>
        </div>
    </form>
</div>

<!-- Temples List -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <?php if (count($temples) > 0): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານທີ່</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ເຈົ້າອະທິການ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach($temples as $temple): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($temple['name']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500"><?= htmlspecialchars($temple['district']) ?>, <?= htmlspecialchars($temple['province']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500"><?= htmlspecialchars($temple['abbot_name'] ?? '-') ?></div>
                </td>
                <td class="px-6 py-4">
                    <?php if($temple['status'] === 'active'): ?>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded">ເປີດໃຊ້ງານ</span>
                    <?php else: ?>
                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">ປິດໃຊ້ງານ</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-2">
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if ($can_edit): ?>
                        <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-temple" data-id="<?= $temple['id'] ?>" data-name="<?= htmlspecialchars($temple['name']) ?>">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="px-6 py-4 bg-white border-t border-gray-200">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-500">
                ສະແດງ <?= count($temples) ?> ຈາກທັງໝົດ <?= $total_temples ?> ວັດ
            </div>
            <div class="flex space-x-1">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php 
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a 
                        href="?<?= $query_string ?>" 
                        class="<?= $i === $page ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> px-3 py-1 rounded"
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
        <div class="text-gray-500">ບໍ່ພົບລາຍການວັດ</div>
    </div>
    <?php endif; ?>
</div>
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
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
            <button id="cancelDelete" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <a id="confirmDelete" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for delete confirmation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php require_once '../includes/footer.php'; ?>