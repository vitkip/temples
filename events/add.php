<?php
// filepath: c:\xampp\htdocs\temples\events\add.php
ob_start();

$page_title = 'ເພີ່ມກິດຈະກໍາໃໝ່';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// --- Existing PHP Logic (No changes needed here) ---
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
if (!isset($_SESSION['user'])) { $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ"; header('Location: ' . $base_url . 'auth/'); exit; }
if (!in_array($_SESSION['user']['role'], ['superadmin', 'admin', 'province_admin'])) { $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເພີ່ມກິດຈະກໍາ"; header('Location: ' . $base_url . 'events/'); exit; }

// ดึงข้อมูลวัดสำหรับ dropdown
if ($_SESSION['user']['role'] === 'superadmin') {
    $temple_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    $temples = $temple_stmt->fetchAll();
} elseif ($_SESSION['user']['role'] === 'province_admin') {
    $province_stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
    $province_stmt->execute([$_SESSION['user']['id']]);
    $province_ids = $province_stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($province_ids) {
        $in = str_repeat('?,', count($province_ids) - 1) . '?';
        $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE province_id IN ($in) AND status = 'active' ORDER BY name");
        $temple_stmt->execute($province_ids);
        $temples = $temple_stmt->fetchAll();
    } else { $temples = []; }
} else {
    $temple_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
    $temple_stmt->execute([$_SESSION['user']['temple_id']]);
    $temples = $temple_stmt->fetchAll();
}

$errors = [];
$form_data = ['title' => '', 'description' => '', 'event_date' => '', 'event_time' => '08:00', 'location' => '', 'temple_id' => $_SESSION['user']['role'] === 'admin' ? $_SESSION['user']['temple_id'] : '', 'monks' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { $_SESSION['error'] = "ການຮ້ອງຂໍບໍ່ຖືກຕ້ອງ"; header('Location: ' . $base_url . 'events/add.php'); exit; }
    $form_data = ['title' => trim($_POST['title'] ?? ''), 'description' => trim($_POST['description'] ?? ''), 'event_date' => trim($_POST['event_date'] ?? ''), 'event_time' => trim($_POST['event_time'] ?? ''), 'location' => trim($_POST['location'] ?? ''), 'temple_id' => isset($_POST['temple_id']) ? (int)$_POST['temple_id'] : 0, 'monks' => isset($_POST['monks']) ? $_POST['monks'] : []];
    if (empty($form_data['title'])) { $errors[] = "ກະລຸນາປ້ອນຊື່ກິດຈະກໍາ"; }
    if (empty($form_data['event_date'])) { $errors[] = "ກະລຸນາປ້ອນວັນທີກິດຈະກໍາ"; }
    if (empty($form_data['event_time'])) { $errors[] = "ກະລຸນາປ້ອນເວລາກິດຈະກໍາ"; }
    if (empty($form_data['temple_id'])) { $errors[] = "ກະລຸນາເລືອກວັດ"; }
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (temple_id, title, description, event_date, event_time, location) VALUES (:temple_id, :title, :description, :event_date, :event_time, :location)");
            $stmt->execute([':temple_id' => $form_data['temple_id'], ':title' => $form_data['title'], ':description' => $form_data['description'], ':event_date' => $form_data['event_date'], ':event_time' => $form_data['event_time'], ':location' => $form_data['location']]);
            $event_id = $pdo->lastInsertId();
            if (!empty($form_data['monks']) && is_array($form_data['monks'])) {
                $monk_stmt = $pdo->prepare("INSERT INTO event_monk (event_id, monk_id, role, note) VALUES (:event_id, :monk_id, :role, :note)");
                foreach ($form_data['monks'] as $monk) {
                    if (!isset($monk['id']) || empty($monk['id'])) { continue; }
                    $monk_stmt->execute([':event_id' => $event_id, ':monk_id' => $monk['id'], ':role' => $monk['role'] ?? null, ':note' => $monk['note'] ?? null]);
                }
            }
            $_SESSION['success'] = "ເພີ່ມຂໍ້ມູນກິດຈະກໍາສໍາເລັດແລ້ວ";
            header('Location: ' . $base_url . 'events/view.php?id=' . $event_id);
            exit;
        } catch (PDOException $e) { $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການເພີ່ມຂໍ້ມູນ: " . $e->getMessage(); }
    }
}

$monk_params = [];
$monk_query = "SELECT m.id, m.name, m.pansa, t.name as temple_name FROM monks m LEFT JOIN temples t ON m.temple_id = t.id WHERE m.status = 'active'";
if ($_SESSION['user']['role'] === 'admin') {
    $monk_query .= " AND m.temple_id = ?";
    $monk_params[] = $_SESSION['user']['temple_id'];
}
$monk_query .= " ORDER BY m.pansa DESC, m.name ASC";
$monk_stmt = $pdo->prepare($monk_query);
$monk_stmt->execute($monk_params);
$monks = $monk_stmt->fetchAll();
?>

<div class="page-container">
    <!-- Page Header -->
    <div class="header-section flex flex-col md:flex-row justify-between items-start md:items-center mb-6 p-6 rounded-lg">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">ເພີ່ມກິດນິມົນໃໝ່</h1>
            <p class="text-sm text-gray-600 mt-1">ຟອມເພີ່ມຂໍ້ມູນກິດນິມົມ ແລະ ພະສົງທີ່ເຂົ້າຮ່ວມ</p>
        </div>
        <a href="<?= $base_url ?>events/" class="btn btn-secondary mt-4 md:mt-0">
            <i class="fas fa-arrow-left"></i> ກັບຄືນ
        </a>
    </div>

    <!-- Display Errors -->
    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm">
        <h3 class="text-sm font-medium text-red-800">ພົບຂໍ້ຜິດພາດ <?= count($errors) ?> ລາຍການ</h3>
        <ul class="list-disc pl-5 space-y-1 mt-2 text-sm text-red-700">
            <?php foreach ($errors as $error): ?><li><?= $error ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Add Event Form -->
    <div class="flex justify-center">
        <form action="<?= $base_url ?>events/add.php" method="post" class="space-y-8 w-full max-w-4xl">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- Event Details Card -->
            <div class="card bg-white p-6 md:p-8 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-6 flex items-center">
                    <i class="fas fa-calendar-check text-amber-600 mr-3"></i>ຂໍ້ມູນກິດນິມົນ
                </h2>
                
                <!-- Main event information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Title - Full width -->
                    <div class="md:col-span-2">
                        <label for="title" class="form-label block mb-1">ຊື່ງານທີ່ຈະໄປ <span class="text-red-600">*</span></label>
                        <input type="text" name="title" id="title" class="form-input w-full" value="<?= htmlspecialchars($form_data['title']) ?>" required>
                    </div>
                    
                    <!-- Date and time - Side by side -->
                    <div>
                        <label for="event_date" class="form-label block mb-1">ວັນທີ <span class="text-red-600">*</span></label>
                        <input type="date" name="event_date" id="event_date" class="form-input w-full" value="<?= htmlspecialchars($form_data['event_date']) ?>" required>
                    </div>
                    
                    <div>
                        <label for="event_time" class="form-label block mb-1">ເວລາ <span class="text-red-600">*</span></label>
                        <input type="time" name="event_time" id="event_time" class="form-input w-full" value="<?= htmlspecialchars($form_data['event_time']) ?>" required>
                    </div>
                    
                    <!-- Temple selection - Full width -->
                    <div class="md:col-span-2">
                        <label for="temple_id" class="form-label block mb-1">ວັດ <span class="text-red-600">*</span></label>
                        <select name="temple_id" id="temple_id" class="form-select w-full" required <?= $_SESSION['user']['role'] === 'admin' ? 'disabled' : '' ?>>
                            <option value="">-- ເລືອກວັດ --</option>
                            <?php foreach ($temples as $temple): ?>
                            <option value="<?= $temple['id'] ?>" <?= $temple['id'] == $form_data['temple_id'] ? 'selected' : '' ?>><?= htmlspecialchars($temple['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                            <input type="hidden" name="temple_id" value="<?= $_SESSION['user']['temple_id'] ?>">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Location and description -->
                    <div class="md:col-span-2">
                        <label for="location" class="form-label block mb-1">ສະຖານທີ່ (ຖ້າມີ)</label>
                        <input type="text" name="location" id="location" class="form-input w-full" value="<?= htmlspecialchars($form_data['location']) ?>" placeholder="ເຊັ່ນ: ສາລາໂຮງທຳ">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="form-label block mb-1">ລາຍລະອຽດ</label>
                        <textarea name="description" id="description" rows="4" class="form-textarea w-full resize-y"><?= htmlspecialchars($form_data['description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Participating Monks Card -->
            <div class="card bg-white p-6 md:p-8 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-800 border-b pb-4 mb-6 flex items-center">
                    <i class="fas fa-users text-amber-600 mr-3"></i>ເອີນພຣະສົງທີ່ຈະເອີນໄປຮ່ວມກິດນິມົນ
                </h2>
                
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ພະສົງ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ບົດບາດ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ໝາຍເຫດ</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="monk-rows">
                            <!-- JS will populate this -->
                        </tbody>
                    </table>
                </div>
                
                <div id="no-monks-placeholder" class="text-center py-8 text-gray-500 border-2 border-dashed rounded-lg mt-4 bg-gray-50">
                    <i class="fas fa-user-plus fa-2x mb-3"></i>
                    <p>ຍັງບໍ່ມີພະສົງເຂົ້າຮ່ວມ</p>
                </div>
                
                <button type="button" id="add-monk" class="btn btn-secondary mt-6 flex items-center mx-auto">
                    <i class="fas fa-plus mr-2"></i> ເພີ່ມພະສົງ
                </button>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 border-t border-gray-200 pt-6 flex justify-end space-x-4">
                <a href="<?= $base_url ?>events/" class="btn btn-secondary px-6 inline-block py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-times mr-2"></i>ຍົກເລີກ
                </a>
                <button type="submit" class="btn btn-primary px-6">
                    <i class="fas fa-save mr-2"></i> ບັນທຶກຂໍ້ມູນ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Monk Selection Modal -->
<div id="monkModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-60 flex items-center justify-center z-50 p-4">
    <div class="card bg-white max-w-2xl w-full shadow-xl" style="animation: fadeInUp 0.3s ease-out forwards;">
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-900">ເລືອກພະສົງ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6">
            <input type="text" id="monk-search" class="form-input w-full mb-4" placeholder="ຄົ້ນຫາຕາມຊື່ ຫຼື ວັດ...">
            <div class="flex justify-between items-center mb-2 text-sm">
                <div class="space-x-2">
                    <button type="button" id="select-all-monks" class="text-indigo-600 hover:text-indigo-800 font-medium">ເລືອກທັງໝົດ</button>
                    <span class="text-gray-300">|</span>
                    <button type="button" id="deselect-all-monks" class="text-gray-600 hover:text-gray-800 font-medium">ຍົກເລີກທັງໝົດ</button>
                </div>
                <span id="monk-count" class="text-gray-500"></span>
            </div>
            <div class="max-h-64 overflow-y-auto border rounded-lg">
                <table class="w-full">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="p-3 text-left"><input type="checkbox" id="select-all-header-checkbox"></th>
                            <th class="p-3 text-left text-xs font-medium text-gray-500">ພະສົງ</th>
                            <th class="p-3 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">ພັນສາ</th>
                            <th class="p-3 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">ວັດ</th>
                        </tr>
                    </thead>
                    <tbody id="monks-list" class="divide-y divide-gray-200">
                        <?php foreach ($monks as $monk): ?>
                        <tr class="monk-item hover:bg-gray-50">
                            <td class="p-3"><input type="checkbox" class="monk-select" value="<?= $monk['id'] ?>" data-name="<?= htmlspecialchars($monk['name']) ?>" data-pansa="<?= $monk['pansa'] ?>"></td>
                            <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($monk['name']) ?></td>
                            <td class="p-3 text-gray-600 hidden sm:table-cell"><?= $monk['pansa'] ?></td>
                            <td class="p-3 text-gray-600 hidden sm:table-cell"><?= htmlspecialchars($monk['temple_name']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="monk-role" class="form-label text-sm">ບົດບາດ (ສຳລັບທຸກຄົນທີ່ເລືອກ)</label>
                    <input type="text" id="monk-role" class="form-input w-full text-sm" placeholder="ເຊັ່ນ: ປະທານສົງ">
                </div>
                <div>
                    <label for="monk-note" class="form-label text-sm">ໝາຍເຫດ</label>
                    <input type="text" id="monk-note" class="form-input w-full text-sm" placeholder="ຂໍ້ມູນເພີ່ມເຕີມ">
                </div>
            </div>
        </div>
        <div class="flex justify-end space-x-3 p-4 bg-gray-50 border-t">
            <button id="cancelMonkSelect" type="button" class="btn btn-secondary">ຍົກເລີກ</button>
            <button id="confirmMonkSelect" type="button" class="btn btn-primary">ເພີ່ມພະສົງທີ່ເລືອກ</button>
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
    const noMonksPlaceholder = document.getElementById('no-monks-placeholder');
    
    let selectedMonks = [];

    function toggleModal(show) {
        monkModal.classList.toggle('hidden', !show);
    }

    addMonkBtn.addEventListener('click', () => {
        document.getElementById('monk-role').value = '';
        document.getElementById('monk-note').value = '';
        document.querySelectorAll('.monk-select').forEach(cb => cb.checked = false);
        document.getElementById('select-all-header-checkbox').checked = false;
        filterMonks();
        toggleModal(true);
    });

    monkModal.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-modal') || e.target.id === 'cancelMonkSelect' || e.target === monkModal) {
            toggleModal(false);
        }
    });

    function filterMonks() {
        const searchTerm = monkSearch.value.toLowerCase();
        const monkItems = monksList.querySelectorAll('.monk-item');
        let visibleCount = 0;
        monkItems.forEach(item => {
            const monkName = item.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const templeNameCell = item.querySelector('td:nth-child(4)');
            const templeName = templeNameCell ? templeNameCell.textContent.toLowerCase() : '';
            const isVisible = monkName.includes(searchTerm) || templeName.includes(searchTerm);
            item.style.display = isVisible ? '' : 'none';
            if(isVisible) visibleCount++;
        });
        document.getElementById('monk-count').textContent = `${visibleCount} ລາຍການ`;
    }
    monkSearch.addEventListener('input', filterMonks);

    confirmMonkSelect.addEventListener('click', function() {
        const selectedCheckboxes = document.querySelectorAll('.monk-select:checked');
        if (selectedCheckboxes.length === 0) { alert('ກະລຸນາເລືອກພະສົງຢ່າງໜ້ອຍ 1 ອົງ'); return; }
        
        const role = document.getElementById('monk-role').value;
        const note = document.getElementById('monk-note').value;
        
        selectedCheckboxes.forEach(checkbox => {
            const monkId = checkbox.value;
            if (!selectedMonks.some(m => m.id === monkId)) {
                selectedMonks.push({ id: monkId, name: checkbox.dataset.name, pansa: checkbox.dataset.pansa, role: role, note: note });
            }
        });
        updateMonkRows();
        toggleModal(false);
    });

    function updateMonkRows() {
        monkRows.innerHTML = '';
        const hasMonks = selectedMonks.length > 0;
        noMonksPlaceholder.style.display = hasMonks ? 'none' : 'block';
        monkRows.closest('table').style.display = hasMonks ? 'table' : 'none';

        selectedMonks.forEach((monk, index) => {
            const row = document.createElement('tr');
            row.className = 'border-b';
            row.innerHTML = `
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800">${monk.name}</div>
                    <div class="text-xs text-gray-500">${monk.pansa} ພັນສາ</div>
                    <input type="hidden" name="monks[${index}][id]" value="${monk.id}">
                </td>
                <td class="px-4 py-3"><input type="text" name="monks[${index}][role]" class="form-input text-sm w-full" value="${monk.role}"></td>
                <td class="px-4 py-3"><input type="text" name="monks[${index}][note]" class="form-input text-sm w-full" value="${monk.note}"></td>
                <td class="px-4 py-3 text-right">
                    <button type="button" class="remove-monk text-red-500 hover:text-red-700" data-index="${index}"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;
            monkRows.appendChild(row);
        });

        document.querySelectorAll('.remove-monk').forEach(button => {
            button.addEventListener('click', function() {
                selectedMonks.splice(parseInt(this.dataset.index), 1);
                updateMonkRows();
            });
        });
    }

    const selectAllHeader = document.getElementById('select-all-header-checkbox');
    selectAllHeader.addEventListener('change', function() {
        document.querySelectorAll('#monks-list .monk-item:not([style*="display: none"]) .monk-select').forEach(cb => cb.checked = this.checked);
    });
    document.getElementById('select-all-monks').addEventListener('click', () => selectAllHeader.click());
    document.getElementById('deselect-all-monks').addEventListener('click', () => {
        selectAllHeader.checked = false;
        selectAllHeader.dispatchEvent(new Event('change'));
    });

    updateMonkRows(); // Initial call
});
</script>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>