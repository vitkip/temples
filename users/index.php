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

// ກຽມຄິວລີຕາມສິດຂອງຜູ່ໃຊ້
$params = [];
$query = "SELECT u.*, t.name as temple_name FROM users u 
          LEFT JOIN temples t ON u.temple_id = t.id WHERE 1=1";

// ຖ້າເປັນ admin, ເບິ່ງສະເພາະຜູໃຊ້ໃນວັດຂອງຕົນ (ແລະ ບໍ່ສາມາດເບິ່ງ superadmin ໄດ້)
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

// ຕົວກອງສະຖານະຜູ່ໃຊ້
if (isset($_GET['status']) && !empty($_GET['status'])) {
    if ($_GET['status'] === 'active') {
        $query .= " AND u.status = 'active'";
    } elseif ($_GET['status'] === 'inactive') {
        $query .= " AND u.status = 'inactive'";
    }
    // ສະເພາະສະຖານະລໍຖ້າ, ບໍ່ໃສ່ສະຖານະອື່ນ
    elseif ($_GET['status'] === 'pending') {
        $query .= " AND u.status = 'pending'";
    }
}

// ຈັດລຽງຕາມຊື່ຜູ່ໃຊ້
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
// ຕົວກອງຕາມສະຖານະຜູ້ໃຊ້
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $query .= " AND u.status = ?";
    $params[] = $_GET['status'];
}
// ຕັ້ງຄ່າສິດໃນການເພີ່ມຜູ້ໃຊ້
$can_add = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">ຈັດການຜູ້ໃຊ້ງານ</h1>
        <p class="text-sm text-gray-600">ລາຍການຜູ່ໃຊ້ງານທັງໝົດ</p>
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
                <input type="text" name="search" id="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="ຊື່ຜູ່ໃຊ້ ຫຼື ຊື່-ນາມສະກຸນ">
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
            
            <!-- ຕົວກອງສະຖານະຜູ່ໃຊ້ -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ສະຖານະຜູ້ໃຊ້</label>
                <select name="status" id="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">-- ທັງໝົດ --</option>
                    <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>ໃຊ້ງານໄດ້</option>
                    <option value="pending" <?= isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : '' ?>>ລໍຖ້າອະນຸມັດ</option>
                    <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                </select>
            </div>
            
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

<!-- ຕາຕະລາງຜູ່ໃຊ້ -->
<div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <?php if (count($users) > 0): ?>
    <!-- เด้สก์ทอป - แสดงเป็นตาราง (ซ่อนบนมือถือ) -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ຜູ່ໃຊ້</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່-ນາມສະກຸນ</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ວັດ</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະຜູ່ໃຊ້</th>
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
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $status_labels = [
                            'active' => ['ໃຊ້ງານໄດ້', 'bg-green-100 text-green-800'],
                            'pending' => ['ລໍຖ້າອະນຸມັດ', 'bg-yellow-100 text-yellow-800'],
                            'inactive' => ['ປິດໃຊ້ງານ', 'bg-red-100 text-red-800']
                        ];
                        $user_status = $user['status'] ?? 'active'; // กำหนดค่าเริ่มต้นถ้าไม่มีข้อมูล
                        $status_data = $status_labels[$user_status] ?? ['ບໍ່ກໍານົດ', 'bg-gray-100 text-gray-800'];
                        ?>
                        <span id="status-badge-<?= $user['id'] ?>" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_data[1] ?>">
                            <?= $status_data[0] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-gray-500">
                            <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div class="flex space-x-2">
                            <a href="<?= $base_url ?>users/view.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-900" title="ເບິ່ງລາຍລະອຽດ">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if (
                                // สิทธิ์ในการจัดการสถานะผู้ใช้
                                (($_SESSION['user']['role'] === 'superadmin') || 
                                ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')) &&
                                $_SESSION['user']['id'] != $user['id']
                            ): ?>
                                
                                <!-- ปุ่มจัดการสถานะแบบ dropdown -->
                                <div class="relative inline-block text-left">
                                    <button type="button" class="status-action text-gray-600 hover:text-gray-900" data-userid="<?= $user['id'] ?>">
                                        <i class="fas fa-user-cog"></i>
                                    </button>
                                    
                                    <div id="status-dropdown-<?= $user['id'] ?>" class="status-dropdown hidden absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                        <div class="py-1" role="menu">
                                            <?php if (isset($user['status']) && $user['status'] !== 'active'): ?>
                                            <button class="change-status block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-100" 
                                                    data-userid="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    data-status="active" 
                                                    title="ອະນຸມັດຜູ່ໃຊ້">
                                                <i class="fas fa-check-circle mr-2"></i> ອະນຸມັດຜູ່ໃຊ້
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($user['status']) && $user['status'] !== 'pending'): ?>
                                            <button class="change-status block w-full text-left px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-100" 
                                                    data-userid="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    data-status="pending" 
                                                    title="ຕັ້ງເປັນລໍຖ້າອະນຸມັດ">
                                                <i class="fas fa-clock mr-2"></i> ຕັ້ງເປັນລໍຖ້າອະນຸມັດ
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($user['status']) && $user['status'] !== 'inactive'): ?>
                                            <button class="change-status block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-100" 
                                                    data-userid="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>"
                                                    data-status="inactive" 
                                                    title="ປິດໃຊ້ງານ">
                                                <i class="fas fa-ban mr-2"></i> ປິດໃຊ້ງານ
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- ปุ่มแก้ไขและลบตามเดิม -->
                            <?php if (
                                ($_SESSION['user']['role'] === 'superadmin') || 
                                ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')
                            ): ?>
                            <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (
                                (($_SESSION['user']['role'] === 'superadmin') || 
                                ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')) &&
                                $_SESSION['user']['id'] != $user['id']
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
    </div>
    
    <!-- มือถือ - แสดงเป็นการ์ด -->
    <div class="md:hidden">
        <div class="divide-y divide-gray-200">
            <?php foreach($users as $user): ?>
            <div class="p-4 hover:bg-gray-50 <?= ($user['status'] == 'pending') ? 'bg-yellow-50' : '' ?>">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></div>
                        <div class="text-sm text-gray-600"><?= htmlspecialchars($user['name']) ?></div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="<?= $base_url ?>users/view.php?id=<?= $user['id'] ?>" class="text-indigo-600 hover:text-indigo-900 p-1" title="ເບິ່ງລາຍລະອຽດ">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if (
                            (($_SESSION['user']['role'] === 'superadmin') || 
                            ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')) &&
                            $_SESSION['user']['id'] != $user['id']
                        ): ?>
                            
                            <!-- ปุ่มจัดการสถานะแบบ dropdown สำหรับมือถือ -->
                            <div class="relative inline-block text-left">
                                <button type="button" class="status-action text-gray-600 hover:text-gray-900 p-1" data-userid="<?= $user['id'] ?>">
                                    <i class="fas fa-user-cog"></i>
                                </button>
                                
                                <div id="status-dropdown-<?= $user['id'] ?>" class="status-dropdown hidden absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                    <!-- สถานะดรอปดาวน์เหมือนบนเดสก์ทอป -->
                                    <div class="py-1" role="menu">
                                        <?php if (isset($user['status']) && $user['status'] !== 'active'): ?>
                                        <button class="change-status block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-100" 
                                                data-userid="<?= $user['id'] ?>" 
                                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                                data-status="active" 
                                                title="ອະນຸມັດຜູ່ໃຊ້">
                                            <i class="fas fa-check-circle mr-2"></i> ອະນຸມັດຜູ່ໃຊ້
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($user['status']) && $user['status'] !== 'pending'): ?>
                                        <button class="change-status block w-full text-left px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-100" 
                                                data-userid="<?= $user['id'] ?>" 
                                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                                data-status="pending" 
                                                title="ຕັ້ງເປັນລໍຖ້າອະນຸມັດ">
                                            <i class="fas fa-clock mr-2"></i> ຕັ້ງເປັນລໍຖ້າອະນຸມັດ
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($user['status']) && $user['status'] !== 'inactive'): ?>
                                        <button class="change-status block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-100" 
                                                data-userid="<?= $user['id'] ?>" 
                                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                                data-status="inactive" 
                                                title="ປິດໃຊ້ງານ">
                                            <i class="fas fa-ban mr-2"></i> ປິດໃຊ້ງານ
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ปุ่มแก้ไขและลบสำหรับมือถือ -->
                        <?php if (
                            ($_SESSION['user']['role'] === 'superadmin') || 
                            ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')
                        ): ?>
                        <a href="<?= $base_url ?>users/edit.php?id=<?= $user['id'] ?>" class="text-blue-600 hover:text-blue-900 p-1">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (
                            (($_SESSION['user']['role'] === 'superadmin') || 
                            ($_SESSION['user']['role'] === 'admin' && $_SESSION['user']['temple_id'] == $user['temple_id'] && $user['role'] !== 'superadmin')) &&
                            $_SESSION['user']['id'] != $user['id']
                        ): ?>
                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-user p-1" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['username']) ?>">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <?php if (!empty($user['temple_name'])): ?>
                    <div>
                        <span class="text-gray-500">ວັດ:</span> 
                        <span class="text-gray-900"><?= htmlspecialchars($user['temple_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <span class="text-gray-500">ສະຖານະ:</span>
                        <?php if($user['role'] === 'superadmin'): ?>
                            <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-0.5 rounded">Super Admin</span>
                        <?php elseif($user['role'] === 'admin'): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">ຜູ້ດູແລວັດ</span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2 py-0.5 rounded">ຜູ້ໃຊ້ທົ່ວໄປ</span>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">ສະຖານະຜູ່ໃຊ້:</span>
                        <?php
                        $status_labels = [
                            'active' => ['ໃຊ້ງານໄດ້', 'bg-green-100 text-green-800'],
                            'pending' => ['ລໍຖ້າອະນຸມັດ', 'bg-yellow-100 text-yellow-800'],
                            'inactive' => ['ປິດໃຊ້ງານ', 'bg-red-100 text-red-800']
                        ];
                        $user_status = $user['status'] ?? 'active';
                        $status_data = $status_labels[$user_status] ?? ['ບໍ່ກໍານົດ', 'bg-gray-100 text-gray-800'];
                        ?>
                        <span id="status-badge-mobile-<?= $user['id'] ?>" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $status_data[1] ?>">
                            <?= $status_data[0] ?>
                        </span>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">ວັນທີສ້າງ:</span>
                        <span class="text-gray-900"><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="p-6 text-center text-gray-500">
        <i class="fas fa-users fa-3x mb-4"></i>
        <p class="text-lg font-semibold">ບໍ່ມີຂໍໍ່ານັບຜູ່ໃຊ້ທີ່ສາມາດເພີ່ມໄດ້</p>
        <p class="text-sm">ເພີ່ມຜູ່ໃຊ້ໃໝ່ໂດຍກົດປຸ່ມ <strong>ເພີ່ມຜູ້ໃຊ້ໃໝ່</strong> ຂໍໍ່ານັບສູງສຸດ</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal สำหรับเปลี่ยนสถานะ -->
<div id="statusModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl transform transition-all">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການປ່ຽນສະຖານະຜູ່ໃຊ້</h3>
            <button type="button" class="close-status-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການປ່ຽນສະຖານະຜູ່ໃຊ້ <span id="changeUserNameDisplay" class="font-medium"></span> ເປັນ <span id="changeStatusDisplay" class="font-medium"></span> ແທ້ບໍ່?</p>
        </div>
        <div id="statusProcessing" class="hidden py-2">
            <div class="flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                <span class="ml-2">ກຳລັງດຳເນີນການ...</span>
            </div>
        </div>
        <div id="statusSuccess" class="hidden py-2 bg-green-50 text-green-700 rounded px-3">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span id="successMessage"></span>
            </div>
        </div>
        <div id="statusError" class="hidden py-2 bg-red-50 text-red-700 rounded px-3">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span id="errorMessage"></span>
            </div>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button id="cancelChangeStatus" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <button id="confirmChangeStatus" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                ຢືນຢັນ
            </button>
        </div>
    </div>
</div>
<!-- JavaScript สำหรับการเปลี่ยนสถานะผู้ใช้แบบ AJAX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ຕົວປ່ຽນ
    const statusModal = document.getElementById('statusModal');
    const changeUserNameDisplay = document.getElementById('changeUserNameDisplay');
    const changeStatusDisplay = document.getElementById('changeStatusDisplay');
    const statusProcessing = document.getElementById('statusProcessing');
    const statusSuccess = document.getElementById('statusSuccess');
    const statusError = document.getElementById('statusError');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const confirmChangeStatus = document.getElementById('confirmChangeStatus');
    const cancelChangeStatus = document.getElementById('cancelChangeStatus');
    
    let currentUserId = null;
    let currentStatus = null;
    
    // ເປີດ dropdown ສະຖານະ
    document.querySelectorAll('.status-action').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const userId = this.getAttribute('data-userid');
            const dropdown = document.getElementById(`status-dropdown-${userId}`);
            
            // ປິດ dropdown ອື່ນໆທັງໝົດ
            document.querySelectorAll('.status-dropdown').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.add('hidden');
                }
            });
            
            // ສະຫຼັບການສະແດງ dropdown ປັດຈຸບັນ
            dropdown.classList.toggle('hidden');
        });
    });
    
    // ປິດ dropdown ເມື່ອກົດທີ່ພື້ນທີ່ອື່ນ
    document.addEventListener('click', function() {
        document.querySelectorAll('.status-dropdown').forEach(menu => {
            menu.classList.add('hidden');
        });
    });
    
    // ຈັດການປຸ່ມປ່ຽນສະຖານະ
    document.querySelectorAll('.change-status').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-userid');
            const userName = this.getAttribute('data-username');
            const newStatus = this.getAttribute('data-status');
            
            currentUserId = userId;
            currentStatus = newStatus;
            
            // ແປງສະຖານະເປັນພາສາລາວ
            const statusNames = {
                'active': 'ໃຊ້ງານໄດ້',
                'pending': 'ລໍຖ້າອະນຸມັດ',
                'inactive': 'ປິດໃຊ້ງານ'
            };
            
            // ຕັ້ງຄ່າ modal
            changeUserNameDisplay.textContent = userName;
            changeStatusDisplay.textContent = statusNames[newStatus];
            
            // ລີເຊັດ modal
            statusProcessing.classList.add('hidden');
            statusSuccess.classList.add('hidden');
            statusError.classList.add('hidden');
            confirmChangeStatus.classList.remove('hidden');
            cancelChangeStatus.textContent = 'ຍົກເລີກ';
            
            // ສະແດງ modal
            statusModal.classList.remove('hidden');
        });
    });
    
    // ປິດ modal
    document.querySelectorAll('.close-status-modal, #cancelChangeStatus').forEach(element => {
        element.addEventListener('click', function() {
            statusModal.classList.add('hidden');
        });
    });
    
    // ຢືນຢັນການປ່ຽນສະຖານະ
    confirmChangeStatus.addEventListener('click', async function() {
        if (!currentUserId || !currentStatus) return;
        
        // ສະແດງກຳລັງປະມວນຜົນ
        statusProcessing.classList.remove('hidden');
        confirmChangeStatus.classList.add('hidden');
        cancelChangeStatus.classList.add('hidden');
        
        try {
            const response = await fetch('<?= $base_url ?>users/change_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: currentUserId,
                    status: currentStatus
                })
            });
            
            // ตรวจสอบว่าการตอบกลับเป็น JSON จริงหรือไม่
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server did not return JSON');
            }
            
            const data = await response.json();
            
            // ຊ່ອນການປະມວນຜົນ
            statusProcessing.classList.add('hidden');
            cancelChangeStatus.classList.remove('hidden');
            cancelChangeStatus.textContent = 'ປິດ';
            
            if (data.success) {
                // ສະແດງຂໍ້ຄວາມສຳເລັດ
                successMessage.textContent = data.message;
                statusSuccess.classList.remove('hidden');
                
                // ອັບເດດແບດຈ໌ສະຖານະໃນຕາຕະລາງ
                const statusBadge = document.getElementById(`status-badge-${currentUserId}`);
                if (statusBadge) {
                    statusBadge.className = `inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${data.status_class}`;
                    statusBadge.textContent = data.status_label;
                }
                
                // เพิ่มอีเฟคเน้นแถวที่มีการเปลี่ยนแปลง
                const userRow = statusBadge.closest('tr');
                userRow.classList.add('bg-green-50', 'transition-all', 'duration-1000');
                setTimeout(() => {
                    userRow.classList.remove('bg-green-50');
                    // อัปเดตการเน้นสำหรับแถวที่สถานะเป็น pending
                    if (currentStatus === 'pending') {
                        userRow.classList.add('bg-yellow-50');
                    } else {
                        userRow.classList.remove('bg-yellow-50');
                    }
                }, 3000);
                
            } else {
                // ສະແດງຂໍ້ຄວາມຜິດພາດ
                errorMessage.textContent = data.message;
                statusError.classList.remove('hidden');
            }
            
        } catch (error) {
            console.error('Error:', error);
            
            // ຊ່ອນການປະມວນຜົນ ແລະ ສະແດງຂໍ້ຜິດພາດ
            statusProcessing.classList.add('hidden');
            cancelChangeStatus.classList.remove('hidden');
            cancelChangeStatus.textContent = 'ປິດ';
            
            // เพิ่มคำแนะนำในการแก้ไข
            errorMessage.textContent = 'ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່ກັບເຊີບເວີ. ກົດປິດແລະລອງໃໝ່ອີກຄັ້ງ.';
            statusError.classList.remove('hidden');
            
            // เพิ่มปุ่มรีโหลดหน้า
            const reloadButton = document.createElement('button');
            reloadButton.className = 'ml-3 px-3 py-1 bg-gray-200 hover:bg-gray-300 rounded-md text-sm';
            reloadButton.textContent = 'ຣີເຟຣຊໜ້າ';
            reloadButton.addEventListener('click', () => location.reload());
            statusError.querySelector('div').appendChild(reloadButton);
        }
    });
    
    // ເພີ່ມ highlight ສຳລັບແຖວຜູ້ໃຊ້ທີ່ລໍຖ້າອະນຸມັດ
    document.querySelectorAll('tr').forEach(row => {
        const statusCell = row.querySelector('td:nth-child(5)');
        if (statusCell && statusCell.textContent.trim() === 'ລໍຖ້າອະນຸມັດ') {
            row.classList.add('bg-yellow-50');
        }
    });
});
</script>
<?php
require_once '../includes/footer.php';
ob_end_flush();
?>