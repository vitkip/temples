<?php
// filepath: c:\xampp\htdocs\temples\admin\flush_cache.php

session_start();
require_once '../config/base_url.php';

// ตรวจสอบสิทธิ์เฉพาะ superadmin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

$page_title = 'Flush Cache';
require_once '../includes/header.php';

// ตัวอย่าง: ลบไฟล์แคชในโฟลเดอร์ cache (ถ้ามี)
$cache_dir = realpath(__DIR__ . '/../cache');
$flushed = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($cache_dir && is_dir($cache_dir)) {
        $files = glob($cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $flushed = true;
    } else {
        $error = 'ไม่พบโฟลเดอร์ cache';
    }
}
?>

<div class="max-w-xl mx-auto mt-10 bg-white p-8 rounded shadow">
    <h1 class="text-2xl font-bold mb-4"><i class="fas fa-broom text-amber-600"></i> Flush Cache</h1>
    <div id="progress-section" style="display:none;">
        <div class="w-full bg-gray-200 rounded-full h-4 mb-4">
            <div id="progress-bar" class="bg-amber-500 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <div id="progress-text" class="text-center text-sm text-gray-600">ກຳລັງດຳເນີນການ...</div>
    </div>
    <?php if ($flushed): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4 rounded">✅ ລືບ cache ສຳເລັດແລ້ວ</div>
    <?php elseif ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form id="flush-form" method="post">
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-broom"></i> ລືບ cache ທັງໝົດ
        </button>
    </form>
</div>

<script>
document.getElementById('flush-form').addEventListener('submit', function(e) {
    // แสดง progress bar
    document.getElementById('progress-section').style.display = 'block';
    let bar = document.getElementById('progress-bar');
    let text = document.getElementById('progress-text');
    let percent = 0;
    bar.style.width = '0%';
    text.textContent = 'ກຳລັງດຳເນີນການ...';

    // จำลอง progress (UX เท่านั้น)
    let interval = setInterval(function() {
        percent += Math.floor(Math.random() * 20) + 10;
        if (percent >= 100) {
            percent = 100;
            clearInterval(interval);
        }
        bar.style.width = percent + '%';
        text.textContent = percent < 100 ? 'ກຳລັງດຳເນີນການ... ' + percent + '%' : 'ສຳເລັດ!';
    }, 200);

    // รอ 1 วินาทีค่อย submit จริง
    setTimeout(() => {
        this.submit();
    }, 1200);

    // ป้องกัน submit ทันที
    e.preventDefault();
});
</script>

<?php require_once '../includes/footer.php'; ?>