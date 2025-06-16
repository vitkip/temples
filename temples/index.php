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

// เพิ่มเงื่อนไขการกรองตามสิทธิ์ - เพิ่มโค้ดส่วนนี้
if ($_SESSION['user']['role'] === 'admin') {
    // ถ้าเป็น admin วัด ให้แสดงเฉพาะวัดของตัวเอง
    $where_conditions[] = "id = ?";
    $params[] = $_SESSION['user']['temple_id'];
}
// superadmin จะไม่มีเงื่อนไขเพิ่มเติม จึงเห็นทุกวัด

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

// Get provinces for filter - แก้ให้เห็นเฉพาะแขวงที่เกี่ยวข้อง
if ($_SESSION['user']['role'] === 'admin') {
    $province_stmt = $pdo->prepare("SELECT DISTINCT province FROM temples WHERE id = ? ORDER BY province");
    $province_stmt->execute([$_SESSION['user']['temple_id']]);
} else {
    $province_stmt = $pdo->query("SELECT DISTINCT province FROM temples ORDER BY province");
}
$provinces = $province_stmt->fetchAll(PDO::FETCH_COLUMN);

// Check if user has edit permissions
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || $_SESSION['user']['role'] === 'admin');
?>

<!-- เพิ่ม link เพื่อนำเข้า monk-style.css -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">
<link rel="stylesheet" href="<?= $base_url ?>assets/css/temples-style.css">

<div class="page-container">
    <div class="max-w-7xl mx-auto p-4">
        <!-- Page Header -->
         
    <div class="header-section flex justify-between items-center mb-6 p-6 rounded-lg">
        <div>
            <h1 class="text-2xl font-bold flex items-center">
                <div class="category-icon">
                    <i class="fas fa-gopuram"></i>
                </div>
                ຈັດການວັດ
            </h1>
            <p class="text-sm text-amber-700 mt-1">ເບິ່ງແລະຈັດການຂໍ້ມູນວັດທັງໝົດ</p>
        </div>
        <?php if ($_SESSION['user']['role'] === 'superadmin'): /* แก้เงื่อนไขให้เฉพาะ superadmin เห็นปุ่มเพิ่มวัดใหม่ */ ?>
        <a href="<?= $base_url ?>temples/add.php" class="btn-primary flex items-center gap-2">
            <i class="fas fa-plus"></i> ເພີ່ມວັດໃໝ່
        </a>
        <?php endif; ?>
    </div>

        <!-- Filters -->
        <div class="card filter-section p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ຄົ້ນຫາ</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($search) ?>" 
                        placeholder="ຊື່ວັດ, ທີ່ຢູ່..." 
                        class="form-input w-full"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ແຂວງ</label>
                    <select name="province" class="form-select w-full">
                        <option value="">-- ທຸກແຂວງ --</option>
                        <?php foreach($provinces as $prov): ?>
                        <option value="<?= $prov ?>" <?= $province === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">ສະຖານະ</label>
                    <select name="status" class="form-select w-full">
                        <option value="">-- ທຸກສະຖານະ --</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ເປີດໃຊ້ງານ</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ປິດໃຊ້ງານ</option>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg mr-2 transition">
                        <i class="fas fa-filter mr-1"></i> ຕັງຄ່າຟິວເຕີ
                    </button>
                    
                    <a href="<?= $base_url ?>temples/" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                        <i class="fas fa-sync-alt mr-1"></i> ລ້າງ
                    </a>
                </div>
            </form>
        </div>

        <!-- Temples List -->
        <div class="card overflow-hidden">
            <?php if (count($temples) > 0): ?>
            <!-- Responsive Table: Desktop view -->
            <table class="w-full data-table hidden md:table">
            <thead class="table-header">
                <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຊື່ວັດ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານທີ່</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ເຈົ້າອະທິການ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ສະຖານະ</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ຈັດການ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($temples as $temple): ?>
                <tr class="table-row hover:bg-gray-50">
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
                    <?php if($can_edit): ?>
                    <!-- ปุ่มสลับสถานะแบบ toggle switch -->
                    <label class="status-toggle relative inline-block">
                        <input type="checkbox" 
                        class="temple-status-toggle hidden" 
                        data-id="<?= $temple['id'] ?>" 
                        data-name="<?= htmlspecialchars($temple['name']) ?>"
                        <?= $temple['status'] === 'active' ? 'checked' : '' ?>>
                        <span class="toggle-slider <?= $temple['status'] === 'active' ? 'bg-amber-500' : 'bg-gray-300' ?>">
                        <span class="status-text">
                            <?php if($temple['status'] === 'active'): ?>
                            <i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ
                            <?php else: ?>
                            <i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ
                            <?php endif; ?>
                        </span>
                        </span>
                    </label>
                    <?php else: ?>
                    <?php if($temple['status'] === 'active'): ?>
                        <span class="status-badge status-active">
                        <i class="fas fa-circle text-xs mr-1"></i>
                        ເປີດໃຊ້ງານ
                        </span>
                    <?php else: ?>
                        <span class="status-badge bg-gray-100 text-gray-600 border border-gray-200">
                        <i class="fas fa-circle-notch text-xs mr-1"></i>
                        ປິດໃຊ້ງານ
                        </span>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex space-x-3">
                    <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" class="text-amber-600 hover:text-amber-800">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php if ($can_edit): ?>
                    <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-edit"></i>
                    </a>
                    
                    <?php if ($_SESSION['user']['role'] === 'superadmin'): /* เพิ่มเงื่อนไขให้เฉพาะ superadmin สามารถลบวัด */ ?>
                    <a href="javascript:void(0)" class="text-red-500 hover:text-red-700 delete-temple" data-id="<?= $temple['id'] ?>" data-name="<?= htmlspecialchars($temple['name']) ?>">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                    </div>
                </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
            
            <!-- Mobile view: Card-based layout -->
            <div class="md:hidden">
            <?php foreach($temples as $temple): ?>
            <div class="border-b p-4">
                <div class="flex justify-between items-start mb-2">
                <h3 class="font-bold text-gray-900"><?= htmlspecialchars($temple['name']) ?></h3>
                <div>
                    <?php if($temple['status'] === 'active'): ?>
                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                        <i class="fas fa-circle text-xs"></i> ເປີດໃຊ້ງານ
                    </span>
                    <?php else: ?>
                    <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                        <i class="fas fa-circle-notch text-xs"></i> ປິດໃຊ້ງານ
                    </span>
                    <?php endif; ?>
                </div>
                </div>
                
                <div class="text-sm text-gray-600 mb-1">
                <i class="fas fa-map-marker-alt mr-1"></i> 
                <?= htmlspecialchars($temple['district']) ?>, <?= htmlspecialchars($temple['province']) ?>
                </div>
                
                <div class="text-sm text-gray-600 mb-3">
                <i class="fas fa-user mr-1"></i> 
                <?= htmlspecialchars($temple['abbot_name'] ?? '-') ?>
                </div>
                
                <div class="flex justify-between items-center mt-2">
                <!-- Status toggle for mobile -->
                <?php if($can_edit): ?>
                <div class="flex-grow">
                    <label class="status-toggle relative inline-block">
                    <input type="checkbox" 
                        class="temple-status-toggle hidden" 
                        data-id="<?= $temple['id'] ?>" 
                        data-name="<?= htmlspecialchars($temple['name']) ?>"
                        <?= $temple['status'] === 'active' ? 'checked' : '' ?>>
                    <span class="toggle-slider <?= $temple['status'] === 'active' ? 'bg-amber-500' : 'bg-gray-300' ?>">
                        <span class="status-text">
                        <?php if($temple['status'] === 'active'): ?>
                            <i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ
                        <?php else: ?>
                            <i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ
                        <?php endif; ?>
                        </span>
                    </span>
                    </label>
                </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="flex space-x-4">
                    <a href="<?= $base_url ?>temples/view.php?id=<?= $temple['id'] ?>" 
                       class="flex items-center text-amber-600 hover:text-amber-800">
                    <i class="fas fa-eye mr-1"></i> <span class="text-xs">ເບິ່ງ</span>
                    </a>
                    
                    <?php if ($can_edit): ?>
                    <a href="<?= $base_url ?>temples/edit.php?id=<?= $temple['id'] ?>" 
                       class="flex items-center text-blue-600 hover:text-blue-800">
                    <i class="fas fa-edit mr-1"></i> <span class="text-xs">ແກ້ໄຂ</span>
                    </a>
                    
                    <?php if ($_SESSION['user']['role'] === 'superadmin'): ?>
                    <a href="javascript:void(0)" 
                       class="flex items-center text-red-500 hover:text-red-700 delete-temple" 
                       data-id="<?= $temple['id'] ?>" 
                       data-name="<?= htmlspecialchars($temple['name']) ?>">
                    <i class="fas fa-trash mr-1"></i> <span class="text-xs">ລຶບ</span>
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="px-4 md:px-6 py-4 bg-white border-t border-gray-200">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <div class="text-sm text-gray-500 mb-2 md:mb-0">
                ສະແດງ <?= count($temples) ?> ຈາກທັງໝົດ <?= $total_temples ?> ວັດ
                </div>
                <div class="flex flex-wrap gap-1 justify-center md:justify-end">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php 
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a 
                    href="?<?= $query_string ?>" 
                    class="<?= $i === $page ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> px-3 py-1 rounded text-center min-w-[2rem]"
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="card bg-white max-w-md w-full p-6">
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
            <button id="cancelDelete" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <a id="confirmDelete" href="#" class="btn-primary px-4 py-2">
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
document.addEventListener('DOMContentLoaded', function() {
    // เพิ่ม JavaScript สำหรับการทำงานของ toggle status
    const toggles = document.querySelectorAll('.temple-status-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const templeId = this.getAttribute('data-id');
            const templeName = this.getAttribute('data-name');
            const newStatus = this.checked ? 'active' : 'inactive';
            const toggleLabel = this.closest('.status-toggle');
            const slider = toggleLabel.querySelector('.toggle-slider');
            const statusText = toggleLabel.querySelector('.status-text');
            
            // แสดงสถานะกำลังโหลด
            toggleLabel.classList.add('loading');
            
            // ส่งคำขอ Ajax เพื่อเปลี่ยนสถานะ
            fetch('<?= $base_url ?>temples/toggle-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    temple_id: templeId,
                    status: newStatus
                }),
                credentials: 'include' // สำคัญสำหรับการส่ง session cookies
            })
            .then(response => {
                // จับ response ทั้ง success และ error
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    return response.json().then(data => {
                        // ถ้าเป็น JSON ให้เพิ่ม status เพื่อใช้ต่อไป
                        return { ...data, status: response.status };
                    });
                } else {
                    // ถ้าไม่ใช่ JSON ให้อ่านเป็นข้อความ
                    return response.text().then(text => {
                        return { 
                            success: false, 
                            message: text || 'ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່ກັບເຊີບເວີ', 
                            status: response.status 
                        };
                    });
                }
            })
            .then(data => {
                console.log('Response data:', data);
                toggleLabel.classList.remove('loading');
                
                if (data.success) {
                    // อัปเดต UI
                    if (newStatus === 'active') {
                        slider.classList.remove('bg-gray-300');
                        slider.classList.add('bg-amber-500');
                        statusText.innerHTML = '<i class="fas fa-circle text-xs mr-1"></i> ເປີດໃຊ້ງານ';
                    } else {
                        slider.classList.remove('bg-amber-500');
                        slider.classList.add('bg-gray-300');
                        statusText.innerHTML = '<i class="fas fa-circle-notch text-xs mr-1"></i> ປິດໃຊ້ງານ';
                    }
                    
                    showNotification('ອັບເດດສະຖານະວັດ ' + templeName + ' ສຳເລັດແລ້ວ', 'success');
                } else {
                    // กลับไปสถานะเดิมเฉพาะเมื่อเกิดข้อผิดพลาดจริงๆ (HTTP error codes)
                    if (data.status >= 400) {
                        this.checked = !this.checked;
                        showNotification('ເກີດຂໍ້ຜິດພາດ: ' + (data.message || 'ບໍ່ສາມາດອັບເດດສະຖານະໄດ້'), 'error');
                    } else {
                        // ถ้า status code เป็น 2xx แต่ success เป็น false
                        // แสดงว่าอัปเดตสำเร็จแล้ว แต่มีการรายงานผลผิดพลาด
                        showNotification('ອັບເດດສະຖານະສຳເລັດແລ້ວ ແຕ່ມີຂໍໜິດພາດບາງຢ່າງ', 'warning');
                    }
                }
            })
            .catch(error => {
                // จัดการกับข้อผิดพลาดในการเชื่อมต่อ
                console.error('Fetch error:', error);
                toggleLabel.classList.remove('loading');
                
                // เก็บสถานะปัจจุบันไว้
                const currentStatus = this.checked;
                
                // ทำการอัปเดตด้วย form ปกติเพื่อความมั่นใจ
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= $base_url ?>temples/direct-update-status.php';
                form.style.display = 'none';
                
                const templeIdInput = document.createElement('input');
                templeIdInput.name = 'temple_id';
                templeIdInput.value = templeId;
                
                const statusInput = document.createElement('input');
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                const redirectInput = document.createElement('input');
                redirectInput.name = 'redirect';
                redirectInput.value = window.location.href;
                
                form.appendChild(templeIdInput);
                form.appendChild(statusInput);
                form.appendChild(redirectInput);
                document.body.appendChild(form);
                
                showNotification('ກຳລັງໃຊ້ການອັບເດດສຳຮອງ...', 'info');
                setTimeout(() => form.submit(), 500);
            });
        });
    });
    
    // ฟังก์ชันแสดงข้อความแจ้งเตือน
    function showNotification(message, type = 'info') {
        // ตรวจสอบว่ามี notification container หรือไม่
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'fixed top-4 right-4 z-50 flex flex-col space-y-2';
            document.body.appendChild(container);
        }
        
        // สร้าง notification
        const notification = document.createElement('div');
        notification.className = `notification ${type} px-4 py-2 rounded shadow-lg flex items-center transition-all transform translate-x-full`;
        
        // กำหนดสีตาม type
        if (type === 'success') {
            notification.classList.add('bg-green-100', 'border-l-4', 'border-green-500', 'text-green-700');
            notification.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + message;
        } else if (type === 'error') {
            notification.classList.add('bg-red-100', 'border-l-4', 'border-red-500', 'text-red-700');
            notification.innerHTML = '<i class="fas fa-exclamation-circle mr-2"></i>' + message;
        } else {
            notification.classList.add('bg-blue-100', 'border-l-4', 'border-blue-500', 'text-blue-700');
            notification.innerHTML = '<i class="fas fa-info-circle mr-2"></i>' + message;
        }
        
        // เพิ่ม notification ไปยัง container
        container.appendChild(notification);
        
        // แสดง notification ด้วยการเลื่อนเข้ามา
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
            notification.classList.add('translate-x-0');
        }, 10);
        
        // ซ่อน notification หลังจาก 3 วินาที
        setTimeout(() => {
            notification.classList.remove('translate-x-0');
            notification.classList.add('translate-x-full');
            
            // ลบ notification หลังจากการเคลื่อนไหวเสร็จสิ้น
            setTimeout(() => {
                container.removeChild(notification);
            }, 300);
        }, 3000);
    }
});

</script>

<?php require_once '../includes/footer.php'; ?>