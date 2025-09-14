<?php
/**
 * Script to clean up unused uploaded files
 * ສຄຣິບສຳລັບລົບໄຟລ່ທີ່ບໍໍ່ໄດ້ໃຊ້ງານ 
 */

require_once 'config/db.php';

function cleanupUnusedFiles() {
    global $pdo;
    
    echo "<h3>🧹 ກຳລັງກວດສອບໄຟລ່ທີ່ບໍ່ໄດ້ໃຊ້ງານ...</h3>";
    
    // Get all used image files from database
    $stmt = $pdo->query("
        SELECT DISTINCT photo FROM temples WHERE photo IS NOT NULL AND photo != ''
        UNION 
        SELECT DISTINCT logo FROM temples WHERE logo IS NOT NULL AND logo != ''
    ");
    $usedFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Convert to just filename
    $usedFilenames = [];
    foreach ($usedFiles as $file) {
        if ($file) {
            $usedFilenames[] = basename($file);
        }
    }
    
    echo "<p>📊 ໄຟລ່ທີ່ຖືກໃຊ້ງານໃນຖານຂໍ້ມູນ: " . count($usedFilenames) . " ໄຟລ່</p>";
    
    // Scan upload directories
    $uploadDirs = [
        'uploads/temples/',
        'uploads/monks/',
        'uploads/payments/'
    ];
    
    $totalDeleted = 0;
    $totalSize = 0;
    
    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) continue;
        
        echo "<h4>📁 ກວດສອບໂຟວເດີ: {$dir}</h4>";
        
        $files = glob($dir . '*');
        $dirDeleted = 0;
        $dirSize = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                
                // Skip if file is used in database
                if (in_array($filename, $usedFilenames)) {
                    continue;
                }
                
                // Check if it's a test/duplicate file
                if (
                    strpos($filename, 'ລົງທະບຽນຮຽນ.png') !== false ||
                    strpos($filename, '1000018295.png') !== false ||
                    strpos($filename, '???????????.png') !== false ||
                    preg_match('/^\d+_\.png$/', $filename) // Files like "1750514050_.png"
                ) {
                    $fileSize = filesize($file);
                    echo "<p>🗑️ ລົບໄຟລ່: {$filename} (" . formatBytes($fileSize) . ")</p>";
                    
                    if (unlink($file)) {
                        $dirDeleted++;
                        $dirSize += $fileSize;
                    }
                }
            }
        }
        
        echo "<p>✅ ລົບແລ້ວ: {$dirDeleted} ໄຟລ່ (" . formatBytes($dirSize) . ")</p>";
        $totalDeleted += $dirDeleted;
        $totalSize += $dirSize;
    }
    
    echo "<hr>";
    echo "<h3>📈 ສະຫຼຸບຜົນ:</h3>";
    echo "<p>🗑️ ລົບໄຟລ່ທັງໝົດ: {$totalDeleted} ໄຟລ່</p>";
    echo "<p>💾 ປະຢັດພື້ນທີ່: " . formatBytes($totalSize) . "</p>";
    echo "<p>✅ ການທຳຄວາມສະອາດສຳເລັດ!</p>";
}

function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ທຳຄວາມສະອາດໄຟລ່</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .button:hover { background: #005a87; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🧹 ລະບົບທຳຄວາມສະອາດໄຟລ່</h1>
    
    <div class="warning">
        <strong>⚠️ ຄຳເຕືອນ:</strong> ການດຳເນີນງານນີ້ຈະລົບໄຟລ່ທີ່ບໍ່ໄດ້ໃຊ້ງານຖາວອນ ກະລຸນາສຳຮອງຂໍ້ມູນກ່ອນດຳເນີນການ
    </div>
    
    <?php
    if (isset($_POST['cleanup'])) {
        cleanupUnusedFiles();
    } else {
    ?>
        <form method="post">
            <p>ສະຄຣິບນີ້ຈະລົບໄຟລ່ທີ່ບໍ່ໄດ້ໃຊ້ງານອອກຈາກລະບົບ:</p>
            <ul>
                <li>ໄຟລ່ທດສອບ (test files)</li>
                <li>ໄຟລ່ຊ້ຳກັນ (duplicate files)</li>
                <li>ໄຟລ່ທີ່ບໍ່ມີການອ້າງອີງໃນຖານຂໍ້ມູນ</li>
            </ul>
            <button type="submit" name="cleanup" class="button">🚀 ເລີ່ມທຳຄວາມສະອາດ</button>
        </form>
    <?php } ?>
</body>
</html>