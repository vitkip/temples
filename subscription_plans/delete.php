<?php
ob_start();
session_start();

$page_title = 'ລຶບແຜນສະມາຊິກ';
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

// ກວດສອບສິດ - ສະເພາະ superadmin ແລະ admin ເທົ່ານັ້ນທີ່ສາມາດລຶບແຜນສະມາຊິກ
if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ກວດສອບວ່າມີ ID ຫຼືບໍ່
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງແຜນສະມາຊິກ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

$plan_id = (int)$_GET['id'];

// ດຶງຂໍ້ມູນແຜນສະມາຊິກ
$stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນແຜນສະມາຊິກ";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ກວດສອບວ່າ admin ສາມາດລຶບແຜນຂອງວັດຕົນເອງເທົ່ານັ້ນ
if ($is_admin && $plan['temple_id'] != $_SESSION['user']['temple_id']) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດລຶບຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'subscription_plans/');
    exit;
}

// ກວດສອບວ່າມີການສົ່ງຟອມຫຼືບໍ່
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ຕິດຕາມຂໍ້ຜິດພາດ
    $errors = [];

    // ກວດສອບວ່າມີການສະໝັກໃຊ້ງານແຜນນີ້ຢູ່ຫຼືບໍ່
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE plan_id = ? AND status IN ('active', 'pending')");
    $check_stmt->execute([$plan_id]);
    $active_subscriptions = $check_stmt->fetchColumn();

    if ($active_subscriptions > 0) {
        $errors[] = "ບໍ່ສາມາດລຶບແຜນນີ້ໄດ້ເນື່ອງຈາກມີ {$active_subscriptions} ຄົນກຳລັງໃຊ້ງານຢູ່.";
    }

    // ຖ້າບໍ່ມີຂໍ້ຜິດພາດ
    if (empty($errors)) {
        try {
            // ລຶບຂໍ້ມູນແຜນສະມາຊິກ
            $delete_stmt = $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $delete_stmt->execute([$plan_id]);
            
            $_SESSION['success'] = "ລຶບແຜນສະມາຊິກ {$plan['name']} ສຳເລັດແລ້ວ";
            header('Location: ' . $base_url . 'subscription_plans/');
            exit;
        } catch (PDOException $e) {
            $errors[] = "ເກີດຂໍ້ຜິດພາດໃນການລຶບຂໍ້ມູນ: " . $e->getMessage();
        }
    }
}
?>

<!-- ສ່ວນຫົວຂອງໜ້າ -->
<div class="max-w-4xl mx-auto pb-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">ລຶບແຜນສະມາຊິກ</h1>
            <p class="text-sm text-gray-600">ຢືນຢັນການລຶບແຜນສະມາຊິກນີ້</p>
        </div>
        <div>
            <a href="<?= $base_url ?>subscription_plans/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-lg flex items-center transition">
                <i class="fas fa-arrow-left mr-2"></i> ກັບຄືນ
            </a>
        </div>
    </div>

    <?php if (isset($errors) && !empty($errors)): ?>
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

    <!-- ຂໍ້ມູນແຜນສະມາຊິກ -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
        <div class="p-6">
            <div class="flex items-center mb-4">
                <div class="rounded-full bg-red-100 p-3 mr-4">
                    <i class="fas fa-exclamation-triangle text-red-600 fa-2x"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">ທ່ານກຳລັງຈະລຶບແຜນສະມາຊິກນີ້</h2>
                    <p class="text-gray-600 mt-1">ການລຶບຂໍ້ມູນນີ້ຈະບໍ່ສາມາດກູ້ຄືນໄດ້</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h3 class="font-medium text-gray-800"><?= htmlspecialchars($plan['name']) ?></h3>
                <p class="text-gray-600 mt-1"><?= htmlspecialchars($plan['description'] ?? 'ບໍ່ມີລາຍລະອຽດ') ?></p>
                <div class="mt-2">
                    <span class="font-bold text-indigo-600"><?= number_format($plan['price']) ?> ກີບ</span>
                    <span class="text-gray-500 ml-2">/ <?= $plan['duration_months'] ?> ເດືອນ</span>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4">
            <form action="<?= $base_url ?>subscription_plans/delete.php?id=<?= $plan_id ?>" method="post" class="flex justify-end space-x-3">
                <a href="<?= $base_url ?>subscription_plans/" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">
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