<?php
// filepath: c:\xampp\htdocs\temples\events\view.php
ob_start(); // เพิ่ม output buffering

$page_title = 'ລາຍລະອຽດກິດຈະກໍາ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

$event_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນກິດຈະກໍາພ້ອມກັບຂໍ້ມູນວັດ
$stmt = $pdo->prepare("
    SELECT e.*, t.name as temple_name, t.district, t.province 
    FROM events e
    LEFT JOIN temples t ON e.temple_id = t.id
    WHERE e.id = ?
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນກິດຈະກໍາ";
    header('Location: ' . $base_url . 'events/');
    exit;
}

// ດຶງລາຍຊື່ພະສົງທີ່ເຂົ້າຮ່ວມໃນກິດຈະກໍານີ້
$monk_stmt = $pdo->prepare("
    SELECT em.*, m.name as monk_name, m.photo, m.position, m.pansa
    FROM event_monk em
    LEFT JOIN monks m ON em.monk_id = m.id
    WHERE em.event_id = ?
    ORDER BY em.role, m.pansa DESC, m.name
");
$monk_stmt->execute([$event_id]);
$monks = $monk_stmt->fetchAll();

// ກວດສອບສິດໃນການແກ້ໄຂ
$can_edit = ($_SESSION['user']['role'] === 'superadmin' || 
            ($_SESSION['user']['role'] === 'admin' && 
             $_SESSION['user']['temple_id'] == $event['temple_id']));
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($event['title']) ?></h1>
            <p class="text-sm text-gray-600">
                <?= date('d/m/Y', strtotime($event['event_date'])) ?> 
                <?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ
            </p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= $base_url ?>events/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
            
            <?php if ($can_edit): ?>
            <a href="<?= $base_url ?>events/edit.php?id=<?= $event['id'] ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-edit mr-2"></i> ແກ້ໄຂ
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <!-- ສະແດງຂໍ້ຄວາມແຈ້ງເຕືອນສຳເລັດ -->
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- ຂໍ້ມູນກິດຈະກໍາ -->
        <div class="lg:col-span-2 space-y-6">
            <!-- ລາຍລະອຽດກິດຈະກໍາ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ລາຍລະອຽດກິດຈະກໍາ</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">ວັນເວລາຈັດງານ</h3>
                            <p class="text-gray-800">
                                <?= date('d/m/Y', strtotime($event['event_date'])) ?> 
                                ເວລາ <?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ
                            </p>
                        </div>
                        
                        <?php if (!empty($event['location'])): ?>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">ສະຖານທີ່</h3>
                            <p class="text-gray-800"><?= htmlspecialchars($event['location']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">ລາຍລະອຽດ</h3>
                            <div class="prose max-w-none">
                                <?php if (!empty($event['description'])): ?>
                                    <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($event['description']) ?></p>
                                <?php else: ?>
                                    <p class="text-gray-500 italic">ບໍ່ມີລາຍລະອຽດເພີ່ມເຕີມ</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ພະສົງທີ່ເຂົ້າຮ່ວມ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">ພະສົງທີ່ເຂົ້າຮ່ວມ</h2>
                        <?php if ($can_edit): ?>
                        <a href="<?= $base_url ?>events/add_monk.php?event_id=<?= $event_id ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm py-1 px-3 rounded flex items-center transition">
                            <i class="fas fa-plus mr-1"></i> ເພີ່ມພະສົງ
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($monks) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($monks as $monk): ?>
                        <div class="flex items-start border-b border-gray-200 pb-4">
                            <div class="flex-shrink-0 mr-4">
                                <?php if (!empty($monk['photo']) && $monk['photo'] !== 'uploads/monks/default.png'): ?>
                                    <img src="<?= $base_url . $monk['photo'] ?>" alt="<?= htmlspecialchars($monk['monk_name']) ?>" class="w-12 h-12 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-user text-indigo-600"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900"><?= htmlspecialchars($monk['monk_name']) ?></h3>
                                        <p class="text-sm text-gray-500">
                                            <?php if (!empty($monk['position'])): ?>
                                                <?= htmlspecialchars($monk['position']) ?> · 
                                            <?php endif; ?>
                                            <?= htmlspecialchars($monk['pansa'] ?? '') ?> ພັນສາ
                                        </p>
                                        <?php if (!empty($monk['role'])): ?>
                                        <div class="mt-1 inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2 py-0.5 rounded">
                                            <?= htmlspecialchars($monk['role']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($can_edit): ?>
                                    <div class="flex space-x-2">
                                        <a href="<?= $base_url ?>events/edit_monk.php?id=<?= $monk['id'] ?>&event_id=<?= $event_id ?>" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0)" class="text-red-600 hover:text-red-900 delete-monk-link" 
                                           data-id="<?= $monk['id'] ?>" 
                                           data-name="<?= htmlspecialchars($monk['monk_name']) ?>"
                                           data-event-id="<?= $event_id ?>">
                                            <i class="fas fa-minus-circle"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($monk['note'])): ?>
                                <div class="mt-2 text-sm text-gray-700">
                                    <p class="whitespace-pre-line"><?= htmlspecialchars($monk['note']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-pray text-3xl mb-2 text-gray-300"></i>
                        <p>ຍັງບໍ່ມີພະສົງເຂົ້າຮ່ວມໃນກິດຈະກໍານີ້</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ສ່ວນແຖບຂ້າງ -->
        <div class="space-y-6">
            <!-- ຂໍ້ມູນວັດ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນວັດ</h2>
                    
                    <div class="flex items-center space-x-4 mb-4">
                        <i class="fas fa-gopuram text-3xl text-indigo-600"></i>
                        <div>
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($event['temple_name'] ?? 'ບໍ່ມີຂໍ້ມູນ') ?></h3>
                            <p class="text-sm text-gray-500">
                                <?= htmlspecialchars($event['district'] ?? '') ?>
                                <?= !empty($event['district']) && !empty($event['province']) ? ', ' : '' ?>
                                <?= htmlspecialchars($event['province'] ?? '') ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($event['temple_id'])): ?>
                    <div class="mt-4">
                        <a href="<?= $base_url ?>temples/view.php?id=<?= $event['temple_id'] ?>" class="text-indigo-600 hover:text-indigo-800 flex items-center">
                            <span>ເບິ່ງລາຍລະອຽດວັດ</span>
                            <i class="fas fa-arrow-right ml-2 text-sm"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ຂໍ້ມູນໂດຍຫຍໍ້ -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">ຂໍ້ມູນສະຫຼຸບ</h2>
                    
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                                <i class="fas fa-calendar-alt text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ວັນທີຈັດງານ</p>
                                <p class="font-medium"><?= date('d/m/Y', strtotime($event['event_date'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center mr-3">
                                <i class="fas fa-clock text-amber-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ເວລາ</p>
                                <p class="font-medium"><?= date('H:i', strtotime($event['event_time'])) ?> ໂມງ</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($event['location'])): ?>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ສະຖານທີ່</p>
                                <p class="font-medium"><?= htmlspecialchars($event['location']) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <i class="fas fa-pray text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">ພະສົງເຂົ້າຮ່ວມ</p>
                                <p class="font-medium"><?= count($monks) ?> ອົງ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal ຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ -->
<div id="removeMonkModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">ຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ</h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="py-3">
            <p class="text-gray-700">ທ່ານຕ້ອງການລຶບພະສົງ <span id="removeMonkNameDisplay" class="font-medium"></span> ອອກຈາກການເຂົ້າຮ່ວມກິດຈະກໍານີ້ແທ້ບໍ່?</p>
        </div>
        <div class="flex justify-end space-x-3 mt-3">
            <button id="cancelRemove" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                ຍົກເລີກ
            </button>
            <a id="confirmRemove" href="#" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                ຢືນຢັນການລຶບ
            </a>
        </div>
    </div>
</div>

<!-- JavaScript ສຳລັບການຢືນຢັນການລຶບພະສົງຈາກກິດຈະກໍາ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const removeMonkModal = document.getElementById('removeMonkModal');
    const removeMonkNameDisplay = document.getElementById('removeMonkNameDisplay');
    const confirmRemove = document.getElementById('confirmRemove');
    
    // ເປີດ modal ເມື່ອກົດປຸ່ມລຶບພະສົງ
    document.querySelectorAll('.delete-monk-link').forEach(button => {
        button.addEventListener('click', function() {
            const monkId = this.getAttribute('data-id');
            const monkName = this.getAttribute('data-name');
            const eventId = this.getAttribute('data-event-id');
            
            // ຕັ້ງຊື່ພະສົງໃນ modal
            removeMonkNameDisplay.textContent = monkName;
            
            // ຕັ້ງລິ້ງຢືນຢັນ
            confirmRemove.href = '<?= $base_url ?>events/remove_monk.php?id=' + monkId + '&event_id=' + eventId;
            
            // ສະແດງ modal
            removeMonkModal.classList.remove('hidden');
        });
    });
    
    // ປິດ modal
    document.querySelectorAll('.close-modal, #cancelRemove').forEach(element => {
        element.addEventListener('click', function() {
            removeMonkModal.classList.add('hidden');
        });
    });
});
</script>

<?php
// Flush the buffer at the end of the file
ob_end_flush();
require_once '../includes/footer.php';
?>