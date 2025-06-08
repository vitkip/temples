<?php
// ตรวจสอบว่ามีการเริ่มต้น session หรือไม่
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';            // ຊື່ເຄື່ອງລ້ຽງ
$db   = 'tp-system';            // ຊື່ຖານຂໍ້ມູນ
$user = 'root';                 // ຊື່ຜູ້ໃຊ້ MySQL
$pass = '';                     // ລະຫັດຜ່ານ (ຄ່າປົກກະຕິໃນ XAMPP ຄືວ່າງ)
$charset = 'utf8mb4';           // ຊຸດຕົວອັກສອນ

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ໃຫ້ໂຊ້ຄວາມຜິດພາດ
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ໃຫ້ດຶງຄ່າໄດ້ງ່າຍ
    PDO::ATTR_EMULATE_PREPARES   => false,                  // ປ້ອງກັນ SQL Injection
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo "ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນຜິດພາດ: " . $e->getMessage();
    exit;
}
?>
