<?php
ob_start();
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

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
$user_id = $_SESSION['user']['id'] ?? null;

if (!$is_superadmin && !$is_admin) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ດຶງ payment ID ຈາກ GET parameter
$payment_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$payment_id) {
    $_SESSION['error'] = "ບໍ່ພົບ ID ຂອງການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ດຶງຂໍ້ມູນການຊຳລະເງິນ
$stmt = $pdo->prepare("
    SELECT sp.*, s.id as subscription_id, s.temple_id, s.status as subscription_status
    FROM subscription_payments sp
    JOIN subscriptions s ON sp.subscription_id = s.id
    WHERE sp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນການຊຳລະເງິນ";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ກວດສອບວ່າ admin ມີສິດເຂົ້າເຖິງຂໍ້ມູນຂອງວັດນີ້ຫຼືບໍ່
if ($is_admin && !$is_superadmin && $payment['temple_id'] != $temple_id) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດປະຕິເສດການຊຳລະເງິນນີ້";
    header('Location: ' . $base_url . 'subscription_payments/');
    exit;
}

// ກວດສອບວ່າການຊຳລະເງິນຍັງລໍຖ້າການຢືນຢັນຢູ່ຫຼືບໍ່
if ($payment['status'] !== 'pending') {
    $_SESSION['error'] = "ການຊຳລະເງິນນີ້ໄດ້ຖືກດຳເນີນການແລ້ວ (" . ($payment['status'] === 'approved' ? 'ອະນຸມັດແລ້ວ' : 'ປະຕິເສດແລ້ວ') . ")";
    header('Location: ' . $base_url . 'subscription_payments/view.php?id=' . $payment_id);
    exit;
}

// ຂໍ້ຄວາມເຫດຜົນໃນການປະຕິເສດ
$rejection_reason = '';
if (isset($_POST['submit'])) {
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    // ຖ້າມີການສົ່ງຟອມມາ, ດຳເນີນການປະຕິເສດການຊຳລະເງິນ
    try {
        // ເລີ່ມຕົ້ນການ transaction
        $pdo->beginTransaction();
        
        // ອັບເດດສະຖານະການຊຳລະເງິນເປັນ "rejected"
        $update_payment = $pdo->prepare("
            UPDATE subscription_payments 
            SET status = 'rejected', 
                notes = CASE
                    WHEN notes IS NULL OR notes = '' THEN ?
                    ELSE CONCAT(notes, '\n', ?)
                END
            WHERE id = ?
        ");
        $note = "ປະຕິເສດ: " . ($rejection_reason ?: "ບໍ່ຜ່ານເງື່ອນໄຂ");
        $update_payment->execute([$note, $note, $payment_id]);
        
        // ຖ້າຫາກການສະໝັກສະມາຊິກມີສະຖານະ "pending", ບໍ່ມີການປ່ຽນແປງໃດໆເພາະລໍຖ້າການຊຳລະເງິນໃໝ່
        
        $pdo->commit();
        
        $_SESSION['success'] = "ປະຕິເສດການຊຳລະເງິນສຳເລັດແລ້ວ";
        header('Location: ' . $base_url . 'subscription_payments/view.php?id=' . $payment_id);
        exit;
        
    } catch (PDOException $e) {
        // ຖ້າມີຂໍ້ຜິດພາດໃດໆ, ຍົກເລີກການປ່ຽນແປງທັງໝົດ
        $pdo->rollBack();
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການປະຕິເສດການຊຳລະເງິນ: " . $e->getMessage();
    }
}

// ຖ້າບໍ່ມີການສົ່ງຟອມມາ, ສະແດງຟອມເພື່ອລະບຸເຫດຜົນໃນການປະຕິເສດ
$page_title = 'ປະຕິເສດການຊຳລະເງິນ';
require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <a href="<?= $base_url ?>subscription_payments/view.php?id=<?= $payment_id ?>" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> ກັບໄປໜ້າຂໍ້ມູນການຊຳລະເງິນ
            </a>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-6 text-center text-red-600">ປະຕິເສດການຊຳລະເງິນ</h1>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            ທ່ານກຳລັງຈະປະຕິເສດການຊຳລະເງິນນີ້. ກະລຸນາລະບຸເຫດຜົນໃນການປະຕິເສດເພື່ອແຈ້ງໃຫ້ຜູ້ໃຊ້ຮູ້.
                        </p>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <div class="mb-6">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">ເຫດຜົນໃນການປະຕິເສດ</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="ກະລຸນາລະບຸເຫດຜົນໃນການປະຕິເສດການຊຳລະເງິນນີ້..."><?= htmlspecialchars($rejection_reason) ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">ເຫດຜົນນີ້ຈະຖືກບັນທຶກໄວ້ເປັນໝາຍເຫດໃນການຊຳລະເງິນ.</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="<?= $base_url ?>subscription_payments/view.php?id=<?= $payment_id ?>" class="py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ຍົກເລີກ
                    </a>
                    <button type="submit" name="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-times-circle mr-1"></i> ຢືນຢັນການປະຕິເສດ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>