<?php
// filepath: c:\xampp\htdocs\temples\events\add.php
ob_start();

$page_title = 'ເພີ່ມກິດຈະກໍາໃໝ່';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ສ້າງ CSRF token ຖ້າບໍ່ມີ
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດທິຂອງຜູ້ໃຊ້ໃນການເພີ່ມກິດຈະກໍາ
if (!in_array($_SESSION['user']['role'], ['superadmin', 'admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເພີ່ມກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ດຶງຂໍ້ມູນວັດສໍາລັບ dropdown
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} else {
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

// ກໍານົດຕົວແປເລີ່ມຕົ້ນ
$errors = [];
$form_data = [
    'title' => '',
    'description' => '',
    'event_date' => '',
    'event_time' => '08:00',
    'location' => '',
    'temple_id' => $_SESSION['user']['role'] === 'admin' ? $_SESSION['user']['temple_id'] : '',
    'monks' => []
];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ກວດສອບຄວາມຖືກຕ້ອງຂອງ CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ";
        header('Location: ' . $base_url . 'events/add.php');
        exit;
    }
    
    // ກວດສອບຂໍ້ມູນທີ່ປ້ອນເຂົ້າມາ
    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'event_date' => trim($_POST['event_date'] ?? ''),
        'event_time' => trim($_POST['event_time'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0,
        'monks' => isset($_POST['monks']) ? $_POST['monks'] : []
    ];
    
    // ກົດລະບຽບການກວດສອບຂໍ້ມູນ
    if (empty($form_data['title'])) {
        $errors[] = "ກະລຸນາປ້ອນຊື່ກິດຈະກໍາ";
    }
    
    if (empty($form_data['event_date'])) {
        $errors[] = "ກະລຸນາປ້ອນວັນທີກິດຈະກໍາ";
    }
    
    if (empty($form_data['event_time'])) {
        $errors[] = "ກະລຸນາປ້ອນເວລາກິດຈະກໍາ";
    }
    
    if (empty($form_data['temple_id'])) {
        $errors[] = "ກະລຸນາເລືອກວັດ";
    }
    
    // ຖ້າການກວດສອບຜ່ານ
    if (empty($errors)) {
        try {
            // ເພີ່ມຂໍ້ມູນກິດຈະກໍາໃນຖານຂໍ້ມູນ
            $stmt = $pdo->prepare("
                INSERT INTO events (temple_id, title, description, event_date, event_time, location)
                VALUES (:temple_id, :title, :description, :event_date, :event_time, :location)
            ");
            
            $stmt->execute([
                ':temple_id' => $form_data['temple_id'],
                ':title' => $form_data['title'],
                ':description' => $form_data['description'],
                ':event_date' => $form_data['event_date'],
                ':event_time' => $form_data['event_time'],
                ':location' => $form_data['location']
            ]);
            
            $event_id = $pdo->lastInsertId();
            
            // ເພີ່ມຂໍ້ມູນພະສົງທີ່ເຂົ້າຮ່ວມກິດຈະກໍາ
            if (!empty($form_data['monks']) && is_array($form_data['monks'])) {
                $monk_stmt = $pdo->prepare("
                    INSERT INTO event_monk (event_id, monk_id, role, note)
                    VALUES (:event_id, :monk_id, :role, :note)
                ");
                
                foreach ($form_data['monks'] as $monk) {
                    if (!isset($monk['id']) || empty($monk['id'])) {
                        continue;
                    }
                    
                    $monk_stmt->execute([
                        ':event_id' => $event_id,
                        ':monk_id' => $monk['id'],
                        ':role' => $monk['role'] ?? null,
                        ':note' => $monk['note'] ?? null
                    ]);
                }
            }
            
            $_SESSION['success'] = "ເພີ່ມຂໍ້ມູນກິດຈະກໍາສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}

// ດຶງຂໍ້ມູນພະສົງສຳລັບເລືອກເຂົ້າຮ່ວມກິດຈະກໍາ
$monk_params = [];
$monk_query = "SELECT m.id, m.name, m.pansa, t.name as temple_name FROM monks m 
              LEFT JOIN temples t ON m.temple_id = t.id 
              WHERE m.status = 'active'";

if ($_SESSION['user']['role'] === 'admin') {
    $monk_query .= " AND m.temple_id = ?";
    $monk_params[] = $_SESSION['user']['temple_id'];
} elseif (!empty($form_data['temple_id'])) {
    $monk_query .= " AND m.temple_id = ?";
    $monk_params[] = $form_data['temple_id'];
}

$monk_query .= " ORDER BY m.pansa DESC, m.name ASC";
$monk_stmt = $pdo->prepare($monk_query);
$monk_stmt->execute($monk_params);
$monks = $monk_stmt->fetchAll();
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ເພີ່ມກິດຈະກໍາໃໝ່</h1>
            <p class="text-sm text-gray-600">ຟອມເພີ່ມຂໍ້ມູນກິດຈະກໍາ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>events/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
    <!-- ສະແດງຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">ພົບຂໍ້ຜິດພາດ <?= count($errors) ?> ລາຍການ</h3>
                <div class="mt-2 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- ຟອມເພີ່ມຂໍ້ມູນ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form action="<?= $base_url ?>events/add.php" method="post" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="grid grid-cols-1 gap-6">
                <!-- ຂໍ້ມູນພື້ນຖານ -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ຂໍ້ມູນກິດຈະກໍາ</h2>
                    
                    <div class="mb-4">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">ຊື່ກິດຈະກໍາ <span class="text-red-600">*</span></label>
                        <input type="text" name="title" id="title" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['title']) ?>" required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">ວັນທີຈັດກິດຈະກໍາ <span class="text-red-600">*</span></label>
                            <input type="date" name="event_date" id="event_date" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['event_date']) ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="event_time" class="block text-sm font-medium text-gray-700 mb-2">ເວລາຈັດກິດຈະກໍາ <span class="text-red-600">*</span></label>
                            <input type="time" name="event_time" id="event_time" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['event_time']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-2">ສະຖານທີ່ຈັດກິດຈະກໍາ</label>
                        <input type="text" name="location" id="location" class="form-input rounded-md w-full" value="<?= htmlspecialchars($form_data['location']) ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label for="temple_id" class="block text-sm font-medium text-gray-700 mb-2">ວັດ <span class="text-red-600">*</span></label>
                        <select name="temple_id" id="temple_id" class="form-select rounded-md w-full" required <?= $_SESSION['user']['role'] === 'admin' ? 'disabled' : '' ?>>
                            <option value="">ເລືອກວັດ</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $temple['id'] == $form_data['temple_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($temple['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <input type="hidden" name="temple_id" value="<?= $_SESSION['user']['temple_id'] ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">ລາຍລະອຽດກິດຈະກໍາ</label>
                        <textarea name="description" id="description" rows="4" class="form-textarea rounded-md w-full"><?= htmlspecialchars($form_data['description']) ?></textarea>
                    </div>
                </div>
                
                <!-- ຂໍ້ມູນພະສົງທີ່ເຂົ້າຮ່ວມ -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-3">ພະສົງທີ່ເຂົ້າຮ່ວມ</h2>
                    
                    <div class="mb-4" id="monks-container">
                        <table class="w-full mb-4">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ພະສົງ</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ບົດບາດ</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ໝາຍເຫດ</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ຈັດການ</th>
                                </tr>
                            </thead>
                            <tbody id="monk-rows">
                                <!-- JavaScript will populate rows here -->
                            </tbody>
                        </table>
                        
                        <button type="button" id="add-monk" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 py-2 px-4 rounded-lg text-sm flex items-center transition">
                            <i class="fas fa-plus mr-2"></i> ເພີ່ມພະສົງ
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- ປຸ່ມດຳເນີນການ -->
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-3">
                <a href="<?= $base_url ?>events/" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                </button>
            </div>
        </form>
    </div>
    
    <!-- Modal ເພີ່ມພະສົງ -->
    <div id="monkModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg max-w-xl w-full p-6 shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">ເລືອກພະສົງ</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <input type="text" id="monk-search" class="form-input rounded-md w-full mb-4" placeholder="ຄົ້ນຫາພະສົງ...">
                
                <div class="max-h-80 overflow-y-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ເລືອກ</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ພະສົງ</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ພັນສາ</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">ວັດ</th>
                            </tr>
                        </thead>
                        <tbody id="monks-list">
                            <?php foreach ($monks as $monk): ?>
                            <tr class="monk-item hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <input type="radio" name="monk_select" class="monk-select" value="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>" data-pansa="<?= $monk['pansa'] ?>">
                                </td>
                                <td class="px-3 py-2"><?= htmlspecialchars($monk['name']) ?></td>
                                <td class="px-3 py-2"><?= $monk['pansa'] ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($monk['temple_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <h4 class="text-sm font-medium text-gray-700 mb-2">ບົດບາດໃນກິດຈະກໍາ</h4>
                <input type="text" id="monk-role" class="form-input rounded-md w-full mb-3" placeholder="ປະທານສົງ, ພະສົງຮ່ວມພິທີ, ຯລຯ">
                
                <h4 class="text-sm font-medium text-gray-700 mb-2">ໝາຍເຫດ</h4>
                <textarea id="monk-note" class="form-textarea rounded-md w-full mb-4" rows="2" placeholder="ຂໍ້ມູນເພີ່ມເຕີມ"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3 mt-3">
                <button id="cancelMonkSelect" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                    ຍົກເລີກ
                </button>
                <button id="confirmMonkSelect" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                    ເພີ່ມພະສົງ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const monkModal = document.getElementById('monkModal');
    const addMonkBtn = document.getElementById('add-monk');
    const monkRows = document.getElementById('monk-rows');
    const monkSearch = document.getElementById('monk-search');
    const monksList = document.getElementById('monks-list');
    const confirmMonkSelect = document.getElementById('confirmMonkSelect');
    const cancelMonkSelect = document.getElementById('cancelMonkSelect');
    const closeModal = document.querySelector('.close-modal');
    
    // อาเรย์เก็บข้อมูลพระที่เข้าร่วม
    let selectedMonks = [];
    
    // เปิด modal เมื่อกดปุ่มเพิ่มพระ
    addMonkBtn.addEventListener('click', function() {
        monkModal.classList.remove('hidden');
        document.getElementById('monk-role').value = '';
        document.getElementById('monk-note').value = '';
        
        const monkSelects = document.querySelectorAll('.monk-select');
        monkSelects.forEach(radio => radio.checked = false);
    });
    
    // ปิด modal
    [closeModal, cancelMonkSelect].forEach(element => {
        element.addEventListener('click', function() {
            monkModal.classList.add('hidden');
        });
    });
    
    // ค้นหาพระ
    monkSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const monkItems = document.querySelectorAll('.monk-item');
        
        monkItems.forEach(item => {
            const monkName = item.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const templeName = item.querySelector('td:nth-child(4)').textContent.toLowerCase();
            
            if (monkName.includes(searchTerm) || templeName.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // เมื่อกดปุ่มยืนยันเลือกพระ
    confirmMonkSelect.addEventListener('click', function() {
        const selectedRadio = document.querySelector('.monk-select:checked');
        
        if (!selectedRadio) {
            alert('ກະລຸນາເລືອກພະສົງ');
            return;
        }
        
        const monkId = selectedRadio.value;
        const monkName = selectedRadio.getAttribute('data-name');
        const monkPansa = selectedRadio.getAttribute('data-pansa');
        const role = document.getElementById('monk-role').value;
        const note = document.getElementById('monk-note').value;
        
        // ตรวจสอบว่าพระรูปนี้ถูกเลือกแล้วหรือไม่
        if (selectedMonks.some(monk => monk.id === monkId)) {
            alert('ພະສົງຮູບນີ້ຖືກເລືອກແລ້ວ');
            return;
        }
        
        // เพิ่มพระที่เลือกลงในอาเรย์
        selectedMonks.push({
            id: monkId,
            name: monkName,
            pansa: monkPansa,
            role: role,
            note: note
        });
        
        // อัปเดตการแสดงผล
        updateMonkRows();
        
        // ปิด modal
        monkModal.classList.add('hidden');
    });
    
    // อัปเดตการแสดงผลพระที่เลือก
    function updateMonkRows() {
        monkRows.innerHTML = '';
        
        selectedMonks.forEach((monk, index) => {
            const row = document.createElement('tr');
            row.classList.add('border-b');
            
            row.innerHTML = `
                <td class="px-3 py-2">
                    <div class="font-medium">${monk.name}</div>
                    <div class="text-xs text-gray-500">${monk.pansa} พันสา</div>
                    <input type="hidden" name="monks[${index}][id]" value="${monk.id}">
                </td>
                <td class="px-3 py-2">
                    <input type="text" name="monks[${index}][role]" class="form-input text-sm w-full" value="${monk.role}">
                </td>
                <td class="px-3 py-2">
                    <input type="text" name="monks[${index}][note]" class="form-input text-sm w-full" value="${monk.note}">
                </td>
                <td class="px-3 py-2">
                    <button type="button" class="remove-monk text-red-500 hover:text-red-700" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
            
            monkRows.appendChild(row);
        });
        
        // เพิ่ม event listener สำหรับปุ่มลบ
        document.querySelectorAll('.remove-monk').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                selectedMonks.splice(index, 1);
                updateMonkRows();
            });
        });
    }
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>