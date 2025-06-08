<?php
// filepath: c:\xampp\htdocs\temples\subscription_plans\index.php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງ (ສະເພາະ superadmin)
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
if (!$is_superadmin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກໍານົດຄ່າເລີ່ມຕົ້ນ
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'price';
$sort_dir = $_GET['dir'] ?? 'asc';

// ສ້າງເງື່ອນໄຂຄົ້ນຫາແລະຈັດຮຽງ
$params = [];
$where = [];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ກໍານົດການຈັດຮຽງ
$allowed_sort_columns = ['id', 'name', 'price', 'duration_months', 'status', 'created_at'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'price';
}

$sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

// ດຶງຈໍານວນລາຍການທັງຫມົດ
$count_sql = "SELECT COUNT(*) as total FROM subscription_plans $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// ດຶງຂໍ້ມູນແຜນສະມາຊິກ
$sql = "
    SELECT * FROM subscription_plans
    $where_clause
    ORDER BY $sort_by $sort_dir
    LIMIT $limit OFFSET $offset
";

$plans = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    // ຖ້າບໍ່ມີຕາຕະລາງ, ໃຫ້ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນ
    $_SESSION['error'] = "ບໍ່ພົບຕາຕະລາງແຜນການສະໝັກສະມາຊິກ: " . $e->getMessage();
    
    // ກວດສອບວ່າມີຕາຕະລາງຫຼືບໍ່
    try {
        $tables = $pdo->query("SHOW TABLES LIKE 'subscription_plans'")->fetchAll();
        if (empty($tables)) {
            // ສ້າງຕາຕະລາງຖ້າບໍ່ມີ
            $pdo->exec("
                CREATE TABLE `subscription_plans` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(100) NOT NULL,
                  `description` text DEFAULT NULL,
                  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
                  `duration_months` int(11) NOT NULL DEFAULT 1,
                  `features` text DEFAULT NULL,
                  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $_SESSION['success'] = "ສ້າງຕາຕະລາງແຜນການສະໝັກສະມາຊິກສໍາເລັດແລ້ວ";
        }
    } catch (PDOException $table_e) {
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການສ້າງຕາຕະລາງ: " . $table_e->getMessage();
    }
}

// ດຶງຈໍານວນຜູ້ສະໝັກສະມາຊິກຕາມແຜນ
$subscriptions_count = [];
try {
    $subs_sql = "
        SELECT plan_id, COUNT(*) as count 
        FROM subscriptions 
        WHERE plan_id IS NOT NULL 
        GROUP BY plan_id
    ";
    $subs_stmt = $pdo->query($subs_sql);
    while ($row = $subs_stmt->fetch()) {
        $subscriptions_count[$row['plan_id']] = $row['count'];
    }
} catch (PDOException $e) {
    // ຖ້າບໍ່ມີຕາຕະລາງ subscriptions ຫຼື ມີຂໍ້ຜິດພາດອື່ນໆ, ແມ່ນຂ້າມໄປ
}

$page_title = "ຈັດການແຜນການສະໝັກສະມາຊິກ";
require_once '../includes/header.php';
?>

<!-- ໜ້າຈັດການແຜນການສະໝັກສະມາຊິກ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ຈັດການແຜນການສະໝັກສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ຈັດການແຜນການສະໝັກສະມາຊິກທີ່ມີໃຫ້ຜູ້ໃຊ້ເລືອກ</p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <a href="<?= $base_url ?>subscription_plans/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-plus mr-2"></i> ເພີ່ມແຜນໃໝ່
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
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
    
    <!-- ຟອມຄົ້ນຫາແລະກອງ -->
    <div class="mb-6 bg-white rounded-lg shadow p-5">
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" class="form-input rounded-md w-full" value="<?= htmlspecialchars($search) ?>" placeholder="ຄົ້ນຫາຕາມຊື່ຫຼືລາຍລະອຽດ...">
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <select name="status" id="status" class="form-select rounded-md w-full">
                    <option value="">-- ທັງໝົດ --</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                </select>
            </div>
            
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">ຈັດຮຽງຕາມ</label>
                <div class="flex space-x-2">
                    <select name="sort" id="sort" class="form-select rounded-md w-full">
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>ຊື່</option>
                        <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>ລາຄາ</option>
                        <option value="duration_months" <?= $sort_by === 'duration_months' ? 'selected' : '' ?>>ໄລຍະເວລາ</option>
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>ວັນທີ່ສ້າງ</option>
                    </select>
                    
                    <select name="dir" id="dir" class="form-select rounded-md w-24">
                        <option value="asc" <?= $sort_dir === 'ASC' ? 'selected' : '' ?>>ຂຶ້ນ</option>
                        <option value="desc" <?= $sort_dir === 'DESC' ? 'selected' : '' ?>>ລົງ</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
                
                <a href="<?= $base_url ?>subscription_plans/" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                    <i class="fas fa-times mr-2"></i> ລ້າງ
                </a>
            </div>
        </form>
    </div>
    
    <!-- ຕາຕະລາງສະແດງຂໍ້ມູນ -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <?php if (empty($plans)): ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                    <i class="fas fa-tags text-indigo-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">ບໍ່ພົບຂໍ້ມູນແຜນການສະໝັກສະມາຊິກ</h3>
                <p class="text-gray-500 max-w-md mx-auto">
                    <?php if (!empty($search) || !empty($status)): ?>
                        ບໍ່ພົບລາຍການທີ່ຕົງກັບການຄົ້ນຫາຂອງທ່ານ. ລອງປ່ຽນເງື່ອນໄຂການຄົ້ນຫາແລ້ວລອງໃໝ່.
                    <?php else: ?>
                        ຍັງບໍ່ມີແຜນການສະໝັກສະມາຊິກໃນລະບົບ. ຄລິກປຸ່ມ "ເພີ່ມແຜນໃໝ່" ເພື່ອສ້າງແຜນການສະໝັກສະມາຊິກ.
                    <?php endif; ?>
                </p>
                <div class="mt-6">
                    <a href="<?= $base_url ?>subscription_plans/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg inline-flex items-center transition">
                        <i class="fas fa-plus mr-2"></i> ເພີ່ມແຜນໃໝ່
                    </a>
                </div>
            </div>
            <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ແຜນ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ລາຄາ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ໄລຍະເວລາ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຄຸນສົມບັດ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຜູ້ສະໝັກ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <i class="fas fa-tag text-indigo-500"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($plan['name']) ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php 
                                        $desc = htmlspecialchars($plan['description']);
                                        echo strlen($desc) > 50 ? substr($desc, 0, 50).'...' : $desc;
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= number_format($plan['price'], 0, ',', '.') ?> ກີບ
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?= $plan['duration_months'] ?> ເດືອນ
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">
                                <?php
                                $features = explode("\n", $plan['features']);
                                $feature_count = count($features);
                                if (!empty($features[0])) {
                                    echo '<span class="inline-block bg-blue-50 text-blue-700 rounded-full px-2 py-1 text-xs font-semibold mr-1">' . 
                                         $feature_count . ' ລາຍການ</span>';
                                } else {
                                    echo '<span class="text-gray-500 text-xs">ບໍ່ມີຄຸນສົມບັດ</span>';
                                }
                                ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($plan['status'] === 'active'): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                ເປີດໃຊ້ງານ
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                ປິດໃຊ້ງານ
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?= isset($subscriptions_count[$plan['id']]) ? $subscriptions_count[$plan['id']] : 0 ?> ຄົນ
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-3">
                                <a href="<?= $base_url ?>subscription_plans/view.php?id=<?= $plan['id'] ?>" class="text-indigo-600 hover:text-indigo-900" title="ເບິ່ງລາຍລະອຽດ">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= $base_url ?>subscription_plans/edit.php?id=<?= $plan['id'] ?>" class="text-blue-600 hover:text-blue-900" title="ແກ້ໄຂ">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?= $base_url ?>subscription_plans/delete.php?id=<?= $plan['id'] ?>" 
                                   class="text-red-600 hover:text-red-900" 
                                   title="ລຶບ"
                                   onclick="return confirm('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບແຜນການສະໝັກສະມາຊິກນີ້?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- ການແບ່ງໜ້າ (Pagination) -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        ກ່ອນໜ້າ
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        ຕໍ່ໄປ
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            ສະແດງ <span class="font-medium"><?= min(($page - 1) * $limit + 1, $total_records) ?></span> 
                            ຫາ <span class="font-medium"><?= min($page * $limit, $total_records) ?></span> 
                            ຈາກ <span class="font-medium"><?= $total_records ?></span> ລາຍການ
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            
                            if ($start_page > 1): ?>
                            <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                1
                            </a>
                            <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i === $page ? 'bg-indigo-50 text-indigo-600' : 'bg-white text-gray-700 hover:bg-gray-50' ?> text-sm font-medium">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                            </span>
                            <?php endif; ?>
                            <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <?= $total_pages ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // JavaScript ສໍາລັບສົ່ງຟອມໂດຍອັດຕະໂນມັດເມື່ອປ່ຽນຕົວເລືອກ
    document.addEventListener('DOMContentLoaded', function() {
        const statusSelect = document.getElementById('status');
        const sortSelect = document.getElementById('sort');
        const dirSelect = document.getElementById('dir');
        
        statusSelect.addEventListener('change', function() {
            this.form.submit();
        });
        
        sortSelect.addEventListener('change', function() {
            this.form.submit();
        });
        
        dirSelect.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>