<?php
ob_start();
session_start();

$page_title = 'ຈັດການການຊຳລະເງິນ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິງຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$temple_id = $_SESSION['user']['temple_id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ການຈັດການການຄົ້ນຫາແລະການຕັ້ງຄ່າ
$status_filter = $_GET['status'] ?? 'all';
$temple_filter = isset($_GET['temple_id']) && is_numeric($_GET['temple_id']) ? (int)$_GET['temple_id'] : null;
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// ຄຳສັ່ງ SQL ພື້ນຖານ
$sql = "
    SELECT p.*, 
           s.status as subscription_status,
           u.username, u.name as user_name,
           t.name as temple_name
    FROM subscription_payments p
    LEFT JOIN subscriptions s ON p.subscription_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN temples t ON s.temple_id = t.id
    WHERE 1=1
";

$count_sql = "SELECT COUNT(*) FROM subscription_payments p 
              LEFT JOIN subscriptions s ON p.subscription_id = s.id 
              WHERE 1=1";
$params = [];

// ເພີ່ມເງື່ອນໄຂສຳລັບຜູ້ໃຊ້ທີ່ເປັນ admin (ເຫັນສະເພາະທີ່ກ່ຽວກັບວັດຂອງຕົນເທົ່ານັ້ນ)
if ($is_admin && !$is_superadmin) {
    $sql .= " AND s.temple_id = ?";
    $count_sql .= " AND s.temple_id = ?";
    $params[] = $temple_id;
}

// ເພີ່ມເງື່ອນໄຂຕາມການຄົ້ນຫາ
if ($temple_filter && $is_superadmin) { // Admin ບໍ່ສາມາດກຳນົດວັດໄດ້
    $sql .= " AND s.temple_id = ?";
    $count_sql .= " AND s.temple_id = ?";
    $params[] = $temple_filter;
}

if ($status_filter !== 'all') {
    $sql .= " AND p.status = ?";
    $count_sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $search_term = "%$search%";
    $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR t.name LIKE ?)";
    $count_sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR t.name LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

// ດຶງຂໍ້ມູນຈຳນວນທັງໝົດ
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// ກຳນົດການຈັດລຽງ
$sql .= " ORDER BY p.payment_date DESC LIMIT $offset, $per_page";

// ດຶງຂໍ້ມູນການຊຳລະເງິນ
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// ດຶງລາຍຊື່ວັດສຳລັບຕົວກອງ (ສຳລັບ superadmin ເທົ່ານັ້ນ)
$temples = [];
if ($is_superadmin) {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ຈັດການການຊຳລະເງິນ</h1>
            <p class="mt-1 text-sm text-gray-600">ຈັດການແລະຕິດຕາມການຊຳລະເງິນສຳລັບການສະໝັກສະມາຊິກ</p>
        </div>
        
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>subscriptions/" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-users-cog mr-2"></i> ການສະໝັກສະມາຊິກ
            </a>
            <a href="<?= $base_url ?>subscription_payments/create.php" class="inline-flex items-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-plus mr-2"></i> ເພີ່ມການຊຳລະເງິນ
            </a>
        </div>
    </div>

    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນ -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ຟອມຄົ້ນຫາແລະກອງ -->
    <div class="bg-white shadow-sm rounded-md p-4 mb-6">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">ຄົ້ນຫາ</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="ພິມຊື່ຜູ້ໃຊ້, ວັດ">
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <select name="status" id="status" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>ທັງໝົດ</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>ລໍຖ້າການຢືນຢັນ</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>ອະນຸມັດແລ້ວ</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>ຖືກປະຕິເສດ</option>
                </select>
            </div>
            
            <?php if ($is_superadmin && !empty($temples)): ?>
            <div>
                <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-1">ວັດ</label>
                <select name="temple_id" id="temple_id" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">ທັງໝົດ</option>
                    <?php foreach ($temples as $temple): ?>
                    <option value="<?= $temple['id'] ?>" <?= $temple_filter == $temple['id'] ? 'selected' : '' ?>><?= htmlspecialchars($temple['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
                <a href="<?= $base_url ?>subscription_payments/" class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-times mr-2"></i> ລ້າງ
                </a>
            </div>
        </form>
    </div>

    <!-- ຕາຕະລາງສະແດງຂໍ້ມູນ -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຂໍ້ມູນ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈຳນວນເງິນ</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">ບໍ່ພົບຂໍ້ມູນການຊຳລະເງິນ</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($payment['user_name']) ?> (<?= htmlspecialchars($payment['username']) ?>)
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($payment['temple_name']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></div>
                                <div class="text-xs text-gray-500">ເວລາ: <?= date('H:i', strtotime($payment['payment_date'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= number_format($payment['amount']) ?> ກີບ</div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($payment['payment_method'] ?? 'ບໍ່ລະບຸ') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_badge = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800'
                                ];
                                $status_text = [
                                    'pending' => 'ລໍຖ້າການຢືນຢັນ',
                                    'approved' => 'ອະນຸມັດແລ້ວ',
                                    'rejected' => 'ຖືກປະຕິເສດ'
                                ];
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_badge[$payment['status']] ?>">
                                    <?= $status_text[$payment['status']] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="<?= $base_url ?>subscription_payments/view.php?id=<?= $payment['id'] ?>" class="text-indigo-600 hover:text-indigo-900" title="ເບິ່ງລາຍລະອຽດ">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                    <a href="<?= $base_url ?>subscription_payments/approve.php?id=<?= $payment['id'] ?>" class="text-green-600 hover:text-green-900" title="ອະນຸມັດ">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                    <a href="<?= $base_url ?>subscription_payments/reject.php?id=<?= $payment['id'] ?>" class="text-red-600 hover:text-red-900" title="ປະຕິເສດ">
                                        <i class="fas fa-times-circle"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?= $base_url ?>subscription_payments/edit.php?id=<?= $payment['id'] ?>" class="text-blue-600 hover:text-blue-900" title="ແກ້ໄຂ">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?= $base_url ?>subscription_payments/delete.php?id=<?= $payment['id'] ?>" class="text-red-600 hover:text-red-900" title="ລຶບ">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ສ່ວນຂອງການແບ່ງໜ້າ -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-700">
                    ສະແດງ <span class="font-medium"><?= count($payments) ?></span> ຈາກທັງໝົດ <span class="font-medium"><?= $total_records ?></span> ລາຍການ
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?><?= $temple_filter ? "&temple_id=$temple_filter" : "" ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?><?= $temple_filter ? "&temple_id=$temple_filter" : "" ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?><?= $temple_filter ? "&temple_id=$temple_filter" : "" ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>