<?php
// filepath: c:\xampp\htdocs\temples\test_notification.php
session_start();
require_once 'config/db.php';
require_once 'config/notification_functions.php';

// ກວດສອບການເຂົ້າສູ່ລະບົບ
if (!isset($_SESSION['user'])) {
    die('Please login first');
}

$user_id = $_SESSION['user']['id'];

// ສ້າງການແຈ້ງເຕືອນທົດສອບ
try {
    $result = send_notification(
        $user_id,
        'system',
        'ທົດສອບລະບົບການແຈ້ງເຕືອນ',
        'ນີ້ແມ່ນການທົດສອບລະບົບການແຈ້ງເຕືອນຂອງທ່ານ ຖ້າທ່ານເຫັນຂໍ້ຄວາມນີ້ ແມ່ນລະບົບເຮັດວຽກໄດ້ປົກກະຕິ',
        null,
        null,
        true // ແມ່ນ in-app notification
    );
    
    if ($result['success']) {
        echo "<h2>✅ ສຳເລັດ!</h2>";
        echo "<p>ການແຈ້ງເຕືອນຖືກສ້າງຂຶ້ນແລ້ວ</p>";
        echo "<p>Notification ID: " . $result['notification_id'] . "</p>";
        
        if ($result['sms_sent']) {
            echo "<p>SMS ຖືກສົ່ງແລ້ວ</p>";
        }
        
        echo "<br><a href='dashboard.php'>ກັບໄປໜ້າຫຼັກ</a>";
    } else {
        echo "<h2>❌ ຜິດພາດ!</h2>";
        echo "<p>ບໍ່ສາມາດສ້າງການແຈ້ງເຕືອນໄດ້: " . $result['error'] . "</p>";
        echo "<br><a href='dashboard.php'>ກັບໄປໜ້າຫຼັກ</a>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ ເກີດຂໍ້ຜິດພາດ!</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<br><a href='dashboard.php'>ກັບໄປໜ້າຫຼັກ</a>";
}
?>
