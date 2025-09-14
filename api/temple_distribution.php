<?php
/**
 * Temple Distribution API
 * ดึงข้อมูลการกระจายวัดตามแขวง/จังหวัด
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // Cache 5 minutes

require_once '../config/db.php';

try {
    // ดึงข้อมูลการกระจายวัดตามจังหวัด
    $stmt = $pdo->query("
        SELECT 
            p.province_name,
            COUNT(t.id) as temple_count
        FROM temples t
        LEFT JOIN provinces p ON t.province_id = p.province_id
        WHERE t.status = 'active'
        GROUP BY t.province_id, p.province_name
        HAVING temple_count > 0
        ORDER BY temple_count DESC, p.province_name ASC
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // แปลงข้อมูลให้เหมาะสำหรับ Chart.js
    $chartData = [];
    $totalTemples = 0;
    
    foreach ($data as $row) {
        $provinceName = $row['province_name'] ?? 'ບໍ່ລະບຸແຂວງ';
        $count = (int)$row['temple_count'];
        
        $chartData[] = [
            'province' => $provinceName,
            'count' => $count,
            'label' => $provinceName
        ];
        
        $totalTemples += $count;
    }
    
    // เพิ่มข้อมูลสรุป
    $response = [
        'success' => true,
        'data' => $chartData,
        'summary' => [
            'total_temples' => $totalTemples,
            'total_provinces' => count($chartData),
            'generated_at' => date('c')
        ]
    ];
    
    // ถ้าไม่มีข้อมูล ใช้ข้อมูลตัวอย่าง
    if (empty($chartData)) {
        $response = [
            'success' => true,
            'data' => [
                ['province' => 'ນະຄອນຫຼວງວຽງຈັນ', 'count' => 5, 'label' => 'ນະຄອນຫຼວງວຽງຈັນ'],
                ['province' => 'ຫຼວງພຣະບາງ', 'count' => 3, 'label' => 'ຫຼວງພຣະບາງ'],
                ['province' => 'ຈຳປາສັກ', 'count' => 2, 'label' => 'ຈຳປາສັກ']
            ],
            'summary' => [
                'total_temples' => 10,
                'total_provinces' => 3,
                'note' => 'Sample data - no real data available',
                'generated_at' => date('c')
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Temple distribution API error: ' . $e->getMessage());
    
    // Return sample data on database error
    echo json_encode([
        'success' => true,
        'data' => [
            ['province' => 'ນະຄອນຫຼວງວຽງຈັນ', 'count' => 5, 'label' => 'ນະຄອນຫຼວງວຽງຈັນ'],
            ['province' => 'ຫຼວງພຣະບາງ', 'count' => 3, 'label' => 'ຫຼວງພຣະບາງ'],
            ['province' => 'ຈຳປາສັກ', 'count' => 2, 'label' => 'ຈຳປາສັກ'],
            ['province' => 'ສະຫວັນນະເຂດ', 'count' => 1, 'label' => 'ສະຫວັນນະເຂດ']
        ],
        'summary' => [
            'total_temples' => 11,
            'total_provinces' => 4,
            'note' => 'Sample data - database error occurred',
            'error' => $e->getMessage(),
            'generated_at' => date('c')
        ]
    ]);
}
?>