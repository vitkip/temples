<?php
session_start();
require_once 'config/base_url.php';

if (isset($_SESSION['user'])) {
    // ຖ້າ login ແລ້ວ ໄປ dashboard
    header("Location: {$base_url}dashboard.php");
} else {
    // ຖ້າຍັງບໍ່ login ໄປໜ້າ login
    header("Location: {$base_url}auth/login.php");
}
exit;
?>
