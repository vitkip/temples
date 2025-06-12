<?php
// ตรวจสอบว่าเซสชันเริ่มต้นแล้วหรือไม่ก่อนเรียก session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/base_url.php';

// ตรวจสอบว่ามีการเข้าสู่ระบบและเป็น superadmin หรือไม่
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດໃນການເຂົ້າເຖິງໜ້ານີ້";
    header("Location: {$base_url}dashboard.php");
    exit;
}