<?php
// filepath: c:\xampp\htdocs\temples\users\index.php
ob_start();

$page_title = 'ຈັດການຜູ້ໃຊ້ງານ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບສິດໃນການເຂົ້າເຖິງໜ້ານີ້ (ສະເພາະ superadmin ແລະ admin ເທົ່ານັ້ນ)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['superadmin', 'admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ກຽມຄິວລີຕາມສິດຂອງຜູ້ໃຊ້
$params = [];
$query = "SELECT u.*, t.name as temple_name FROM users u 
          LEFT JOIN temples t ON u.temple_id = t.id WHERE 1=1";

// ຖ້າເປັນ admin, ເບິ່ງສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນ (ແລະ ບໍ່ສາມາດເບິ່ງ superadmin ໄດ້)
if ($_SESSION['user']['role'] === 'admin') {
    $query .= " AND u.temple_id = ? AND u.role != 'superadmin'";
    $params[] = $_SESSION['user']['temple_id'];
}

// ຄົ້ນຫາ
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query .= " AND (u.username LIKE ? OR u.name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// ຕົວກອງສະຖານະ
if (isset($_GET['role']) && !empty($_GET['role'])) {
    $query .= " AND u.role = ?";
    $params[] = $_GET['role'];
}

// ຕົວກອງວັດ (ສະເພາະ superadmin)
if ($_SESSION['user']['role'] === 'superadmin' && isset($_GET['temple_id']) && !empty($_GET['temple_id'])) {
    $query .= " AND u.temple_id = ?";
    $params[] = (int)$_GET['temple_id'];
}

// ຈັດລຽງຕາມຊື່ຜູ້ໃຊ້
$query .= " ORDER BY u.role = 'superadmin' DESC, u.username ASC";

// ດຳເນີນການຄິວລີ
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ດຶງຂໍ້ມູນວັດສຳລັບ dropdown (ສະເພາະ superadmin)
$temples = [];
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples ORDER BY name");
    $temples = $temple_stmt->fetchAll();
}

// ຕັ້ງຄ່າສິດໃນການເພີ່ມຜູ້ໃຊ້
$can_add = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ຈັດການຜູ້ໃຊ້ງານ</h1>
        <p class="text-sm text-gray-600">ລາຍການຜູ້ໃຊ້ງານທັງໝົດ</p>
    </div>
    <?php if ($can_add): ?>
    <div>
        <a href="<?= $base_url ?>users/add.php" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
            <i class="fas fa-user-plus mr-2"></i> ເພີ່ມຜູ້ໃຊ້ໃໝ່
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
                <input type="text" name="search" id="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="ຊື່ຜູ້ໃຊ້ ຫຼື ຊື່-ນາມສະກຸນ">
            </div>
            
            <!-- ຕົວກອງສະຖານະ -->
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະ</label>
                <select name="role" id="role" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- ທັງໝົດ --</option>
                    <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
                    <option value="superadmin" <?= isset($_GET['role']) && $_GET['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                    <?php endif; ?>
                    <option value="admin" <?= isset($_GET['role']) && $_GET['role'] === 'admin' ? 'selected' : '' ?>>ຜູ້ດູແລວັດ</option>
                    <option value="user" <?= isset($_GET['role']) && $_GET['role'] === 'user' ? 'selected' : '' ?>>ພຣະສົງ</option>
                </select>
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
            <div class="self-end <?= $_SESSION['user']['role'] === 'superadmin' ? 'md:col-span-3' : 'md:col-span-2' ?>">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-search mr-2"></i> ຄົ້ນຫາ
                </button>
                <a href="<?= $base_url ?>users/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg ml-2 transition">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ສະແດງຂໍ້ຄວາມສໍາເລັດ/ຜິດພາດ -->
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

<!-- ຕາຕະລາງຜູ້ໃຊ້ -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <?php if (count($users) > 0): ?>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ຜູ້ໃຊ້</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່-ນາມສະກຸນ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັນທີສ້າງ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach($users as $user): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500">
                        <?php if (!empty($user['temple_name'])): ?>
                            <?= htmlspecialchars($user['temple_name']) ?>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <?php if($user['role'] === 'superadmin'): ?>
                        <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2.5 py-0.5 rounded">Super Admin</span>
                    <?php elseif($user['role'] === 'admin'): ?>
                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">ຜູ້ດູແລວັດ</span>
                    <?php else: ?>
                        <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded">ຜູ້ໃຊ້ທົ່ວໄປ</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <div class="text-gray-500">
                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-2">
                        <a href="<?= $base_url ?>users/view.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if (
                            // superadmin ສາມາດແກ້ໄຂໄດ້ທຸກຄົນ
                            ($_SESSION['user']['role'] === 'superadmin') || 
                            // admin ແກ້ໄຂໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນ ແລະ ບໍ່ແມ່ນ superadmin
                            ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin') ||
                            // ຜູ້ໃຊ້ສາມາດແກ້ໄຂຂໍ້ມູນຕົນເອງໄດ້
                            ($_SESSION['user']['id'] == $user['id'])
                        ): ?>
                        <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (
                            // superadmin ສາມາດລຶບໄດ້ທຸກຄົນ ຍົກເວັ້ນຕົນເອງ
                            ($_SESSION['user']['role'] === 'superadmin' && $_SESSION['user']['id'] != $user['id']) || 
                            // admin ລຶບໄດ້ສະເພາະຜູ້ໃຊ້ໃນວັດຂອງຕົນ ແລະ ບໍ່ແມ່ນ superadmin ຫຼື admin
                            ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] === 'user')
                        ): ?>
                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-user" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['username']) ?>">
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
    <div class="p-8 text-center text-gray-500">
        <i class="fas fa-users text-4xl mb-4 text-gray-300"></i>
        <p>ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້ງານ</p>
        <?php if (!empty($_GET['search']) || !empty($_GET['role']) || !empty($_GET['temple_id'])): ?>
        <a href="<?= $base_url ?>users/" class="inline-block mt-2 text-indigo-600 hover:text-indigo-800">ລຶບຕົວກອງທັງໝົດ</a>
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
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບຜູ້ໃຊ້ <span id="deleteUserNameDisplay" class="font-medium"></span> ແທ້ບໍ່?</p>
            <p class="text-sm text-red-600 mt-2">ການລຶບຜູ້ໃຊ້ຈະເຮັດໃຫ້ສູນເສຍຂໍ້ມູນທັງໝົດຂອງຜູ້ໃຊ້ນີ້.</p>
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
    const deleteUserNameDisplay = document.getElementById('deleteUserNameDisplay');
    const confirmDelete = document.getElementById('confirmDelete');
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບ
    document.querySelectorAll('.delete-user').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            // ຕັ້ງຊື່ຜູ້ໃຊ້ໃນ modal
            deleteUserNameDisplay.textContent = userName;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmDelete.href = '<?= $base_url ?>users/delete.php?id=' + userId;
            
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