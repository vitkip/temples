<?php
session_start();
require_once __DIR__ . '/../config/base_url.php';

if (!isset($_SESSION['user'])) {
    header("Location: {$base_url}auth/login.php");
    exit;
}
