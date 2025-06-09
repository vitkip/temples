<?php
ob_start();
session_start();

$page_title = 'ລຶບຂໍ້ມູນການສະໝັກ';
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../includes/header.php';

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການລຶບຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງການສະໝັກສະມາຊິກ";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

$subscription_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນການສະໝັກສະມາຊິກ
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.username, u.name as user_name,
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

// ກວດສອບສິດໃນການລຶບຂໍ້ມູນ
$can_delete = $is_superadmin || 
             ($is_admin && $_SESSION['user']['temple_id'] == $subscription['temple_id']);

if (!$can_delete) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'subscriptions/');
    exit;
}

// ກວດສອບວ່າມີການສົ່ງຟອມຫຼືບໍ່
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // เก็บข้อมูลการชำระเงิน (ถ้ามี) ก่อนลบ
        $payment_files = [];
        $payment_stmt = $pdo->prepare("SELECT payment_proof FROM subscription_payments WHERE subscription_id = ?");
        $payment_stmt->execute([$subscription_id]);
        while ($payment = $payment_stmt->fetch()) {
            if (!empty($payment['payment_proof'])) {
                $payment_files[] = $payment['payment_proof'];
            }
        }
        
        // ลบข้อมูลการชำระเงิน
        $delete_payments = $pdo->prepare("DELETE FROM subscription_payments WHERE subscription_id = ?");
        $delete_payments->execute([$subscription_id]);
        
        // ลบไฟล์หลักฐานการชำระเงิน
        foreach ($payment_files as $file) {
            if (file_exists('../' . $file)) {
                unlink('../' . $file);
            }
        }
        
        // ลบข้อมูลการสมัครสมาชิก
        $delete_stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id = ?");
        $delete_stmt->execute([$subscription_id]);
        
        $_SESSION['success'] = "ລຶບຂໍ້ມູນການສະໝັກສະມາຊິກສຳເລັດແລ້ວ";
        header('Location: ' . $base_url . 'subscriptions/');
        exit;
    } catch (PDOException $e) {
        $error = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
    }
}

// ກໍານົດສະຖານະຂອງການສະໝັກສະມາຊິກ
switch($subscription['status']) {
    case 'active':
        $status_badge = 'bg-green-100 text-green-800';
        $status_text = 'ໃຊ້ງານຢູ່';
        break;
    case 'expired':
        $status_badge = 'bg-gray-100 text-gray-800';
        $status_text = 'ໝົດອາຍຸ';
        break;
    case 'pending':
        $status_badge = 'bg-yellow-100 text-yellow-800';
        $status_text = 'ລໍຖ້າການຢືນຢັນ';
        break;
    case 'canceled':
        $status_badge = 'bg-red-100 text-red-800';
        $status_text = 'ຍົກເລີກ';
        break;
    default:
        $status_badge = 'bg-gray-100 text-gray-800';
        $status_text = $subscription['status'];
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto pb-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ລຶບຂໍ້ມູນການສະໝັກສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ຢືນຢັນການລຶບຂໍ້ມູນການສະໝັກສະມາຊິກນີ້</p>
        </div>
        <div>
            <a href="<?= $base_url ?>subscriptions/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
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
                <div class="rounded-full bg-red-100 p-3 mr-4">
                    <i class="fas fa-exclamation-triangle text-red-600 fa-2x"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">ທ່ານກຳລັງຈະລຶບຂໍ້ມູນການສະໝັກສະມາຊິກນີ້</h2>
                    <p class="text-gray-600 mt-1">ການກະທຳນີ້ຈະລຶບຂໍ້ມູນການສະໝັກແລະການຊຳລະເງິນທີ່ກ່ຽວຂ້ອງທັງໝົດ</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-medium text-gray-800">ຂໍ້ມູນການສະໝັກສະມາຊິກ</h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $status_badge ?>">
                        <?= $status_text ?>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-sm text-gray-500">ຜູ້ໃຊ້</p>
                        <p class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($subscription['user_name']) ?> (<?= htmlspecialchars($subscription['username']) ?>)</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ວັດ</p>
                        <p class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($subscription['temple_name']) ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                    <div>
                        <p class="text-sm text-gray-500">ແຜນສະມາຊິກ</p>
                        <p class="text-sm text-gray-800 font-medium"><?= htmlspecialchars($subscription['plan_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ລາຄາ / ໄລຍະເວລາ</p>
                        <p class="text-sm text-gray-800 font-medium"><?= number_format($subscription['plan_price']) ?> ກີບ / <?= $subscription['duration_months'] ?> ເດືອນ</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີເລີ່ມຕົ້ນ</p>
                        <p class="text-sm text-gray-800 font-medium"><?= date('d/m/Y', strtotime($subscription['start_date'])) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">ວັນທີສິ້ນສຸດ</p>
                        <p class="text-sm text-gray-800 font-medium"><?= date('d/m/Y', strtotime($subscription['end_date'])) ?></p>
                    </div>
                </div>

                <?php if (!empty($subscription['notes'])): ?>
                <div class="mt-3">
                    <p class="text-sm text-gray-500">ຫມາຍເຫດ</p>
                    <p class="text-sm text-gray-800 mt-1"><?= nl2br(htmlspecialchars($subscription['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-yellow-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">ຄຳເຕືອນ</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc space-y-1 pl-5">
                                <li>ການລຶບຂໍ້ມູນການສະໝັກສະມາຊິກນີ້ຈະບໍ່ສາມາດກູ້ຄືນໄດ້</li>
                                <li>ຫຼັກຖານການຊຳລະເງິນທັງໝົດຈະຖືກລຶບຖາວອນ</li>
                                <li>ການລຶບຂໍ້ມູນບໍ່ໄດ້ສົ່ງຜົນຕໍ່ການຕອບອີເມວຫຼືການແຈ້ງເຕືອນທີ່ໄດ້ສົ່ງໄປແລ້ວ</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4">
            <form action="<?= $base_url ?>subscriptions/delete.php?id=<?= $subscription_id ?>" method="post" class="flex justify-end space-x-3">
                <a href="<?= $base_url ?>subscriptions/" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
                    ຍົກເລີກ
                </a>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> ຢືນຢັນການລຶບ
                </button>
            </form>
        </div>
    </div>
</div>

<?php
ob_end_flush();
require_once '../includes/footer.php';
?>