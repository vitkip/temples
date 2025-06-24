<?php
require_once '../config/db.php';
require_once '../config/base_url.php';
session_start();

// ตรวจสอบว่าเป็น superadmin เท่านั้นที่เข้าถึงได้
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header('Location: ' . $base_url);
    exit;
}

// ตรวจสอบโครงสร้างตาราง temples
try {
    $stmt = $pdo->query("DESCRIBE temples");
    $temple_structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ดึงข้อมูลตัวอย่าง
    $sample_stmt = $pdo->query("SELECT * FROM temples LIMIT 5");
    $sample_data = $sample_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ตรวจสอบจำนวนวัดตามแขวงและเมือง
    $count_stmt = $pdo->query("
        SELECT p.province_name, d.district_name, COUNT(t.id) as temple_count
        FROM temples t
        JOIN provinces p ON t.province_id = p.province_id
        JOIN districts d ON t.district_id = d.district_id
        WHERE t.status = 'active'
        GROUP BY p.province_name, d.district_name
        ORDER BY p.province_name, d.district_name
    ");
    $temple_counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // แสดงผลในรูปแบบที่อ่านได้ง่าย
    echo '<h1>Temple Database Structure</h1>';
    
    echo '<h2>Table Structure</h2>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    foreach ($temple_structure as $column) {
        echo '<tr>';
        foreach ($column as $key => $value) {
            echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<h2>Sample Data (5 records)</h2>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    // Headers
    if (!empty($sample_data)) {
        echo '<tr>';
        foreach (array_keys($sample_data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        // Data rows
        foreach ($sample_data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
            }
            echo '</tr>';
        }
    } else {
        echo '<tr><td>No data found</td></tr>';
    }
    echo '</table>';
    
    echo '<h2>Temple Counts by Province and District</h2>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>Province</th><th>District</th><th>Temple Count</th></tr>';
    foreach ($temple_counts as $count) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($count['province_name']) . '</td>';
        echo '<td>' . htmlspecialchars($count['district_name']) . '</td>';
        echo '<td>' . htmlspecialchars($count['temple_count']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} catch (PDOException $e) {
    echo '<h1>Error</h1>';
    echo '<p>Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>