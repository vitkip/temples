<?php
// filepath: c:\xampp\htdocs\temples\subscriptions\index.php
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

// ກວດສອບສິດໃນການເຂົ້າເຖິງ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
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
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';

// ສ້າງເງື່ອນໄຂຄົ້ນຫາແລະຈັດຮຽງ
$params = [];
$where = [];

if (!$is_superadmin && $is_admin && $temple_id) {
    $where[] = "s.temple_id = ?";
    $params[] = $temple_id;
}

if (!empty($search)) {
    $search_term = "%$search%";
    $where[] = "(u.username LIKE ? OR u.name LIKE ? OR t.name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status)) {
    $where[] = "s.status = ?";
    $params[] = $status;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// ຕັ້ງຄ່າການຈັດຮຽງ
$allowed_sort_columns = ['id', 'username', 'name', 'temple_name', 'plan_name', 'status', 'start_date', 'end_date', 'amount', 'created_at'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'created_at';
}

$sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

// ດຶງຈຳນວນລາຍການທັງຫມົດ
$count_sql = "
    SELECT COUNT(*) as total 
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN temples t ON s.temple_id = t.id
    LEFT JOIN subscription_plans p ON s.plan_id = p.id
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// ດຶງຂໍ້ມູນການສະໝັກສະມາຊິກ
$sql = "
    SELECT 
        s.*, 
        u.username, 
        u.name as user_name, 
        t.name as temple_name,
        p.name as plan_name, 
        p.description as plan_description
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN temples t ON s.temple_id = t.id
    LEFT JOIN subscription_plans p ON s.plan_id = p.id
    $where_clause
    ORDER BY $sort_by $sort_dir
    LIMIT $limit OFFSET $offset
";

$subscriptions = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll();
} catch (PDOException $e) {
    // ຖ້າບໍ່ມີຕາຕະລາງ, ໃຫ້ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນ
    $_SESSION['error'] = "ບໍ່ພົບຕາຕະລາງການສະໝັກສະມາຊິກ: " . $e->getMessage();
}

$page_title = "ຈັດການການສະໝັກສະມາຊິກ";
require_once '../includes/header.php';
?>

<!-- ໜ້າຈັດການການສະໝັກສະມາຊິກ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ຈັດການການສະໝັກສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ຈັດການແລະຕິດຕາມການສະໝັກສະມາຊິກຂອງຜູ້ໃຊ້</p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            <a href="<?= $base_url ?>subscriptions/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-plus mr-2"></i> ເພີ່ມການສະໝັກສະມາຊິກ
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
    
    <!-- ແຖບຄົ້ນຫາແລະກອງ -->
    <div class="mb-6 bg-white rounded-lg shadow p-5">
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" class="form-input rounded-md w-full" value="<?= htmlspecialchars($search) ?>" placeholder="ຄົ້ນຫາຕາມຊື່, ຊື່ຜູ້ໃຊ້, ຫຼື ວັດ...">
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <select name="status" id="status" class="form-select rounded-md w-full">
                    <option value="">-- ທັງໝົດ --</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ໃຊ້ງານ</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>ໝົດອາຍຸ</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ຍົກເລີກ</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>ລໍຖ້າ</option>
                </select>
            </div>
            
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">ຈັດຮຽງຕາມ</label>
                <div class="flex space-x-2">
                    <select name="sort" id="sort" class="form-select rounded-md w-full">
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>ວັນທີ່ສ້າງ</option>
                        <option value="username" <?= $sort_by === 'username' ? 'selected' : '' ?>>ຊື່ຜູ້ໃຊ້</option>
                        <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>ສະຖານະ</option>
                        <option value="start_date" <?= $sort_by === 'start_date' ? 'selected' : '' ?>>ວັນທີ່ເລີ່ມ</option>
                        <option value="end_date" <?= $sort_by === 'end_date' ? 'selected' : '' ?>>ວັນທີ່ໝົດອາຍຸ</option>
                        <option value="amount" <?= $sort_by === 'amount' ? 'selected' : '' ?>>ຈໍານວນເງິນ</option>
                    </select>
                    
                    <select name="dir" id="dir" class="form-select rounded-md w-24">
                        <option value="desc" <?= $sort_dir === 'DESC' ? 'selected' : '' ?>>ລົງ</option>
                        <option value="asc" <?= $sort_dir === 'ASC' ? 'selected' : '' ?>>ຂຶ້ນ</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
                
                <a href="<?= $base_url ?>subscriptions/" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                    <i class="fas fa-times mr-2"></i> ລ້າງ
                </a>
            </div>
        </form>
    </div>
    
    <!-- ຕາຕະລາງສະແດງຂໍ້ມູນ -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <?php if (empty($subscriptions)): ?>
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-100 mb-4">
                    <i class="fas fa-ticket-alt text-indigo-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">ບໍ່ພົບຂໍ້ມູນການສະໝັກສະມາຊິກ</h3>
                <p class="text-gray-500 max-w-md mx-auto">
                    <?php if (!empty($search) || !empty($status)): ?>
                        ບໍ່ພົບລາຍການທີ່ຕົງກັບການຄົ້ນຫາຂອງທ່ານ. ລອງປ່ຽນເງື່ອນໄຂການຄົ້ນຫາແລ້ວລອງໃໝ່.
                    <?php else: ?>
                        ຍັງບໍ່ມີຂໍ້ມູນການສະໝັກສະມາຊິກ. ຄລິກປຸ່ມ "ເພີ່ມການສະໝັກສະມາຊິກ" ເພື່ອເພີ່ມລາຍການໃໝ່.
                    <?php endif; ?>
                </p>
                <div class="mt-6">
                    <a href="<?= $base_url ?>subscriptions/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg inline-flex items-center transition">
                        <i class="fas fa-plus mr-2"></i> ເພີ່ມການສະໝັກສະມາຊິກ
                    </a>
                </div>
            </div>
            <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ລະຫັດ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຜູ້ໃຊ້</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ແຜນ / ວັດ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ໄລຍະເວລາ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈໍານວນເງິນ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            #<?= $subscription['id'] ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <i class="fas fa-user text-indigo-500"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($subscription['user_name']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        @<?= htmlspecialchars($subscription['username']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($subscription['plan_name'] ?? 'N/A') ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($subscription['temple_name'] ?? 'N/A') ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_classes = [
                                'active' => 'bg-green-100 text-green-800',
                                'expired' => 'bg-yellow-100 text-yellow-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                'pending' => 'bg-blue-100 text-blue-800'
                            ];
                            $status_labels = [
                                'active' => 'ໃຊ້ງານ',
                                'expired' => 'ໝົດອາຍຸ',
                                'cancelled' => 'ຍົກເລີກ',
                                'pending' => 'ລໍຖ້າ'
                            ];
                            $status_class = $status_classes[$subscription['status']] ?? 'bg-gray-100 text-gray-800';
                            $status_label = $status_labels[$subscription['status']] ?? $subscription['status'];
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_class ?>">
                                <?= $status_label ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-500">
                                <?= date('d/m/Y', strtotime($subscription['start_date'])) ?> - 
                                <?= date('d/m/Y', strtotime($subscription['end_date'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?= number_format($subscription['amount'], 0, ',', '.') ?> ກີບ
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= $base_url ?>subscriptions/edit.php?id=<?= $subscription['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?= $base_url ?>subscriptions/delete.php?id=<?= $subscription['id'] ?>" class="text-red-600 hover:text-red-900">
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
    // JavaScript ເພື່ອກວດຈັບການປ່ຽນແປງຂອງຕົວເລືອກແລະສົ່ງຟອມໂດຍອັດຕະໂນມັດ
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