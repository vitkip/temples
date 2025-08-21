<?php
// config/db.php - Production Version
$environment = $_SERVER['SERVER_NAME'] ?? 'localhost';

if ($environment === 'localhost' || $environment === '127.0.0.1') {
    // Development Environment
    $host = 'localhost';
    $dbname = 'db_temples';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';
    
    // Enable error reporting for development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
} else {
    // Production Environment - ແກ້ໄຂສ່ວນນີ້
    $host = 'localhost'; // ໃສ່ Host ຂອງ Server ແທ້ (ສ່ວນຫຼາຍແມ່ນ 'localhost')
    $dbname = 'your_production_db_name'; // ໃສ່ຊື່ຖານຂໍ້ມູນໃນ Server ແທ້
    $username = 'your_production_db_user'; // ໃສ່ຊື່ຜູ້ໃຊ້ໃນ Server ແທ້
    $password = 'your_production_db_password'; // ໃສ່ລະຫັດຜ່ານໃນ Server ແທ້
    $charset = 'utf8mb4';
    
    // Disable error display for production
    error_reporting(0); // ປິດການລາຍງານ Error ທັງໝົດ
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+07:00'");
    
} catch (PDOException $e) {
    // Log error instead of displaying it
    error_log("Database connection failed: " . $e->getMessage());
    
    if ($environment === 'localhost') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("ລະບົບກໍາລັງປະສົບບັນຫາ ກະລຸນາລອງໃໝ່ໃນພາຍຫຼັງ");
    }
}

// Security headers
if ($environment !== 'localhost') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
?>
