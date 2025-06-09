<?php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

$page_title = 'ປ່ຽນສະຖານະການສະໝັກ';
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

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ກວດສອບວ່າມີ ID ແລະ status ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "ຂໍ້ມູນບໍ່ຄົບຖ້ວນ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

$subscription_id = (int)$_GET['id'];
$new_status = $_GET['status'];

// ກວດສອບວ່າ status ຖືກຕ້ອງຫຼືບໍ່
$valid_statuses = ['active', 'expired', 'pending', 'canceled'];
if (!in_array($new_status, $valid_statuses)) {
    $_SESSION['error'] = "ສະຖານະບໍ່ຖືກຕ້ອງ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ດຶງຂໍ້ມູນການສະໝັກ
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.username, u.name as user_name, u.email as user_email,
           t.name as temple_name, 
           p.name as plan_name, p.price as plan_price, p.duration_months
    FROM subscriptions s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN temples t ON s.temple_id = t.id
    LEFT JOIN subscription_plans p ON s.plan_id = p.id
    WHERE s.id = ?
");
$stmt->execute([$subscription_id]);
$subscription = $stmt->fetch();

if (!$subscription) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ກວດສອບສິດໃນການປ່ຽນສະຖານະ
$can_change_status = $is_superadmin || 
                   ($is_admin && $_SESSION['user']['temple_id'] == $subscription['temple_id']);

if (!$can_change_status) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດປ່ຽນສະຖານະການສະໝັກນີ້";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ສະແດງຂໍ້ຄວາມອະທິບາຍສະຖານະໃໝ່
$status_descriptions = [
    'active' => [
        'title' => 'ເປີດໃຊ້ງານການສະໝັກ',
        'description' => 'ການສະໝັກສະມາຊິກນີ້ຈະຖືກເປີດໃຊ້ງານຢ່າງເຕັມທີ່.',
        'color' => 'green',
        'icon' => 'check-circle'
    ],
    'expired' => [
        'title' => 'ໝົດອາຍຸການສະໝັກ',
        'description' => 'ການສະໝັກສະມາຊິກນີ້ຈະຖືກບັນທຶກວ່າໝົດອາຍຸແລ້ວ.',
        'color' => 'gray',
        'icon' => 'clock'
    ],
    'pending' => [
        'title' => 'ລໍຖ້າການຢືນຢັນການສະໝັກ',
        'description' => 'ການສະໝັກສະມາຊິກນີ້ຈະຖືກກໍານົດເປັນສະຖານະລໍຖ້າການຢືນຢັນ.',
        'color' => 'yellow',
        'icon' => 'hourglass'
    ],
    'canceled' => [
        'title' => 'ຍົກເລີກການສະໝັກ',
        'description' => 'ການສະໝັກສະມາຊິກນີ້ຈະຖືກຍົກເລີກ ແລະບໍ່ສາມາດໃຊ້ງານໄດ້.',
        'color' => 'red',
        'icon' => 'ban'
    ]
];

$current_status_labels = [
    'active' => ['ໃຊ້ງານຢູ່', 'bg-green-100 text-green-800'],
    'expired' => ['ໝົດອາຍຸ', 'bg-gray-100 text-gray-800'],
    'pending' => ['ລໍຖ້າການຢືນຢັນ', 'bg-yellow-100 text-yellow-800'],
    'canceled' => ['ຍົກເລີກ', 'bg-red-100 text-red-800']
];

$current_status = $current_status_labels[$subscription['status']] ?? [$subscription['status'], 'bg-gray-100 text-gray-800'];
$status_info = $status_descriptions[$new_status];

// ປະມວນຜົນການສົ່ງຟອມ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ປັບປຸງສະຖານະ
        $update_stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$new_status, $subscription_id]);
        
        // ບັນທຶກໝາຍເຫດເພີ່ມເຕີມຖ້າມີ
        if (!empty($_POST['notes'])) {
            $notes = $subscription['notes'] . "\n\n" . date('d/m/Y H:i') . " - " . 
                     "ສະຖານະປ່ຽນຈາກ " . $current_status[0] . " ເປັນ " . $status_descriptions[$new_status]['title'] . "\n" .
                     "ໝາຍເຫດ: " . trim($_POST['notes']) . 
                     "\nໂດຍ: " . $_SESSION['user']['name'] . " (" . $_SESSION['user']['username'] . ")";
            
            $notes_stmt = $pdo->prepare("UPDATE subscriptions SET notes = ? WHERE id = ?");
            $notes_stmt->execute([$notes, $subscription_id]);
        }
        
        // ແຈ້ງເຕືອນຜູ້ໃຊ້ (ຖ້າມີອີເມວ ແລະຖ້າເປັນການເປີດໃຊ້ງານຫຼືຍົກເລີກ)
        if (!empty($subscription['user_email']) && ($new_status === 'active' || $new_status === 'canceled')) {
            // ໃນໂຄດຈິງ, ຈະມີການສົ່ງອີເມວແຈ້ງເຕືອນທີ່ນີ້
            // ໃນໂຄດຕົວຢ່າງນີ້, ພວກເຮົາຈະຂ້າມການສົ່ງອີເມວໄປ
        }
        
        $_SESSION['success'] = "ປ່ຽນສະຖານະການສະໝັກເປັນ " . $status_info['title'] . " ສຳເລັດແລ້ວ";
        header('Location: ' . $base_url . 'subscriptions/view.php?id=' . $subscription_id);
        exit;
    } catch (PDOException $e) {
        $error = "ເກີດຂໍ້ຜິດພາດໃນການປ່ຽນສະຖານະ: " . $e->getMessage();
    }
}

// ຫົວຂໍ້ໜ້າເວັບ
$page_title = 'ປ່ຽນສະຖານະການສະໝັກ';
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= $status_info['title'] ?></h1>
            <p class="text-sm text-gray-600">ຢືນຢັນການປ່ຽນສະຖານະການສະໝັກ</p>
        </div>
        <div>
            <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription_id ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <!-- ສະແດງຂໍ້ຜິດພາດ -->
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= $error ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ຂໍ້ມູນການສະໝັກສະມາຊິກ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="rounded-full bg-<?= $status_info['color'] ?>-100 p-3 mr-4">
                    <i class="fas fa-<?= $status_info['icon'] ?> text-<?= $status_info['color'] ?>-600 fa-2x"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        ທ່ານກຳລັງຈະປ່ຽນສະຖານະການສະໝັກເປັນ "<?= $status_info['title'] ?>"
                    </h2>
                    <p class="text-gray-600 mt-1"><?= $status_info['description'] ?></p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-medium text-gray-800">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h3>
                    <div class="flex items-center">
                        <span class="mr-2 text-sm text-gray-500">ສະຖານະປັດຈຸບັນ:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $current_status[1] ?>">
                            <?= $current_status[0] ?>
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-sm text-gray-500">ຜູ້ໃຊ້</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= htmlspecialchars($subscription['user_name']) ?> 
                            (<?= htmlspecialchars($subscription['username']) ?>)
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ອີເມວ</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= !empty($subscription['user_email']) ? 
                                htmlspecialchars($subscription['user_email']) : 
                                '<span class="italic text-gray-400">ບໍ່ມີຂໍ້ມູນ</span>' ?>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-sm text-gray-500">ວັດ</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= htmlspecialchars($subscription['temple_name']) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ແຜນສະມາຊິກ</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= htmlspecialchars($subscription['plan_name']) ?> 
                            (<?= number_format($subscription['plan_price']) ?> ກີບ / <?= $subscription['duration_months'] ?> ເດືອນ)
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີເລີ່ມຕົ້ນ</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= date('d/m/Y', strtotime($subscription['start_date'])) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີສິ້ນສຸດ</p>
                        <p class="text-sm text-gray-800 font-medium">
                            <?= date('d/m/Y', strtotime($subscription['end_date'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($new_status === 'canceled'): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">ຄຳເຕືອນ</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>ການຍົກເລີກການສະໝັກສະມາຊິກນີ້ຈະສົ່ງຜົນດັ່ງນີ້:</p>
                            <ul class="list-disc space-y-1 pl-5 mt-1">
                                <li>ຜູ້ໃຊ້ຈະບໍ່ສາມາດເຂົ້າເຖິງຄຸນສົມບັດພິເສດໄດ້ອີກຕໍ່ໄປ</li>
                                <li>ຈະບໍ່ມີການຄືນເງິນໂດຍອັດຕະໂນມັດ</li>
                                <li>ຖ້າຕ້ອງການເປີດໃຊ້ງານອີກຄັ້ງ, ຕ້ອງປ່ຽນສະຖານະເປັນ "ໃຊ້ງານຢູ່" ດ້ວຍຕົນເອງ</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form action="<?= $base_url ?>subscriptions/status.php?id=<?= $subscription_id ?>&status=<?= $new_status ?>" method="post" class="mt-6">
                <div class="mb-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">ໝາຍເຫດ (ທາງເລືອກ)</label>
                    <textarea id="notes" name="notes" rows="3" class="form-textarea w-full rounded-md"></textarea>
                    <p class="text-xs text-gray-500 mt-1">ລະບຸສາເຫດຂອງການປ່ຽນສະຖານະ ຫຼືຂໍ້ມູນເພີ່ມເຕີມອື່ນໆ</p>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <div class="flex justify-end space-x-3">
                        <a href="<?= $base_url ?>subscriptions/view.php?id=<?= $subscription_id ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                            ຍົກເລີກ
                        </a>
                        <button type="submit" class="px-4 py-2 bg-<?= $status_info['color'] ?>-600 hover:bg-<?= $status_info['color'] ?>-700 text-white rounded-lg transition flex items-center">
                            <i class="fas fa-check mr-2"></i> ຢືນຢັນການປ່ຽນສະຖານະ
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>