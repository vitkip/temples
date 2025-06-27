<?php
/**
 * Temple Report Excel Generator
 * ระบบสร้างรายงานข้อมูลวัดในรูปแบบ Excel
 * 
 * @author Temple Management System
 * @version 2.0
 */

require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// เริ่มต้น session และตรวจสอบการเข้าสู่ระบบ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึง
$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// อนุญาตเฉพาะผู้ใช้ที่มีสิทธิ์
$allowed_roles = ['superadmin', 'admin', 'province_admin'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

/**
 * ฟังก์ชันสำหรับทำความสะอาดข้อมูล input
 */
function sanitizeInput($input) {
    return isset($input) ? trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')) : '';
}

/**
 * ฟังก์ชันสำหรับตรวจสอบและสร้างเงื่อนไขการค้นหา
 */
function buildWhereConditions($user_role, $user_id, $user_temple_id, $search, $province, $district, $status) {
    $where_conditions = [];
    $params = [];

    // จำกัดข้อมูลตามบทบาทผู้ใช้
    switch ($user_role) {
        case 'admin':
            if ($user_temple_id) {
                $where_conditions[] = "t.id = ?";
                $params[] = $user_temple_id;
            }
            break;
        case 'province_admin':
            $where_conditions[] = "t.province_id IN (SELECT province_id FROM user_province_access WHERE user_id = ?)";
            $params[] = $user_id;
            break;
        // superadmin ไม่มีข้อจำกัด
    }

    // เงื่อนไขการค้นหา
    if (!empty($search)) {
        $where_conditions[] = "(t.name LIKE ? OR t.address LIKE ? OR t.abbot_name LIKE ? OR t.phone LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }

    // เงื่อนไขจังหวัด
    if (!empty($province)) {
        $where_conditions[] = "p.province_name = ?";
        $params[] = $province;
    }
    
    // เงื่อนไขเมือง (เพิ่มเข้ามาใหม่)
    if (!empty($district)) {
        $where_conditions[] = "t.district_id = ?";
        $params[] = $district;
    }

    // เงื่อนไขสถานะ
    if (!empty($status)) {
        $where_conditions[] = "t.status = ?";
        $params[] = $status;
    }

    return [
        'where_clause' => !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '',
        'params' => $params
    ];
}

/**
 * ฟังก์ชันสำหรับดึงข้อมูลวัด
 */
function fetchTempleData($pdo, $where_clause, $params, $province_filter = false) {
    try {
        $query = "
            SELECT 
                t.id,
                t.name,
                t.address,
                t.abbot_name,
                t.phone,
                t.email,
                t.status,
                t.created_at,
                p.province_name,
                d.district_name AS district,
                d.district_id,
                CASE 
                    WHEN t.status = 'active' THEN 'ເປີດໃຊ້ງານ'
                    WHEN t.status = 'inactive' THEN 'ປິດໃຊ້ງານ'
                    ELSE 'ບໍ່ລະບຸ'
                END as status_text
            FROM temples t 
            LEFT JOIN provinces p ON t.province_id = p.province_id 
            LEFT JOIN districts d ON t.district_id = d.district_id
            {$where_clause}
            ORDER BY " . ($province_filter ? "d.district_name, t.name" : "p.province_name, t.name") . " ASC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in fetchTempleData: " . $e->getMessage());
        throw new Exception("เกิดข้อผิดพลาดในการดึงข้อมูล");
    }
}

// รับพารามิเตอร์การกรอง
$search = sanitizeInput($_GET['search'] ?? '');
$province = sanitizeInput($_GET['province'] ?? '');
$district = sanitizeInput($_GET['district'] ?? ''); // เพิ่มบรรทัดนี้
$status = sanitizeInput($_GET['status'] ?? '');

try {
    // สร้างเงื่อนไขการค้นหา
    $conditions = buildWhereConditions($user_role, $user_id, $user_temple_id, $search, $province, $district, $status);
    
    // ดึงข้อมูลวัด
    $temples = fetchTempleData($pdo, $conditions['where_clause'], $conditions['params'], !empty($province));
    
    if (empty($temples)) {
        throw new Exception("ບໍ່ມີວັດໃນແຂວງທີ່ເລືອກ");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// กำหนดชื่อไฟล์
$filename = 'temples_report_' . date('Ymd_His') . '.xlsx';

// สร้างไฟล์ Excel ใหม่
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('รายงานข้อมูลวัด');

// กำหนดสไตล์หัวรายงาน
$titleStyle = [
    'font' => [
        'bold' => true,
        'size' => 16,
        'name' => 'Phetsarath OT'
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'FFFFFF'
        ]
    ]
];

// กำหนดสไตล์ส่วนหัวตาราง
$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Phetsarath OT',
        'color' => ['rgb' => 'FFFFFF']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '2980B9' // สีน้ำเงิน
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// กำหนดสไตล์ส่วนหัวกลุ่มเมือง
$districtHeaderStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Phetsarath OT',
        'color' => ['rgb' => 'FFFFFF']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'E6891E' // สีเหลืองส้ม
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// กำหนดสไตล์ข้อมูล
$dataStyle = [
    'font' => [
        'size' => 11,
        'name' => 'Phetsarath OT'
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

// กำหนดสไตล์ข้อมูลแถวคู่
$evenRowStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'F8F9FA' // สีพื้นหลังอ่อน
        ]
    ]
];

// กำหนดสไตล์ส่วนสรุป
$summaryHeaderStyle = [
    'font' => [
        'bold' => true,
        'size' => 14,
        'name' => 'Phetsarath OT',
        'color' => ['rgb' => 'FFFFFF']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => '2D3E50' // สีน้ำเงินเข้ม
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// สไตล์สรุปตาราง
$summaryTableHeaderStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Phetsarath OT'
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'F0F0F0' // สีเทาอ่อน
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// สไตล์สรุปรวม
$summaryTotalStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Phetsarath OT',
        'color' => ['rgb' => '000096']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'E0E0FF'
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// กำหนดความกว้างคอลัมน์ (ลบคอลัมน์สถานะออกเหมือนใน PDF)
$sheet->getColumnDimension('A')->setWidth(8);  // No.
$sheet->getColumnDimension('B')->setWidth(35); // ชื่อวัด
$sheet->getColumnDimension('C')->setWidth(20); // แขวง
$sheet->getColumnDimension('D')->setWidth(20); // เมือง
$sheet->getColumnDimension('E')->setWidth(25); // เจ้าอาวาส
$sheet->getColumnDimension('F')->setWidth(18); // โทรศัพท์

// เพิ่มหัวรายงาน
$sheet->mergeCells('A1:F1');
$sheet->setCellValue('A1', 'ລາຍງານຂໍ້ມູນວັດ');
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// วันที่และเวลา
$sheet->mergeCells('A2:F2');
$sheet->setCellValue('A2', 'ວັນທີ່ອອກລາຍງານ: ' . date('d/m/Y H:i:s'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A2')->getFont()->setSize(11)->setName('Phetsarath OT');

// ตัวกรอง
$filter_parts = [];
if (!empty($search)) {
    $filter_parts[] = 'ຄໍາຄົ້ນຫາ: "' . $search . '"';
}
if (!empty($province)) {
    $filter_parts[] = 'ແຂວງ: ' . $province;
}
if (!empty($district)) {
    try {
        $district_stmt = $pdo->prepare("SELECT district_name FROM districts WHERE district_id = ?");
        $district_stmt->execute([$district]);
        $district_data = $district_stmt->fetch(PDO::FETCH_ASSOC);
        if ($district_data) {
            $filter_parts[] = 'ເມືອງ: ' . $district_data['district_name'];
        }
    } catch (PDOException $e) {
        $filter_parts[] = 'ເມືອງ: ' . $district;
    }
}
if (!empty($status)) {
    $status_text = ($status === 'active') ? 'ເປີດໃຊ້ງານ' : 'ປິດໃຊ້ງານ';
    $filter_parts[] = 'ສະຖານະ: ' . $status_text;
}

$filter_text = !empty($filter_parts) ? 'ຕົວກອງ: ' . implode(' | ', $filter_parts) : 'ຕົວກອງ: ທັງໝົດ';

$sheet->mergeCells('A3:F3');
$sheet->setCellValue('A3', $filter_text);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A3')->getFont()->setSize(11)->setName('Phetsarath OT');

// เว้นบรรทัด
$currentRow = 5;

// จัดกลุ่มข้อมูลตามเมือง (ถ้ามีการเลือกแขวง)
if (!empty($province)) {
    // จัดกลุ่มข้อมูลตามเมือง
    $districts = [];
    foreach ($temples as $temple) {
        $district_name = $temple['district'] ?: 'ບໍ່ລະບຸ';
        $district_id = $temple['district_id'] ?: 0;
        
        if (!isset($districts[$district_id])) {
            $districts[$district_id] = [
                'name' => $district_name,
                'temples' => []
            ];
        }
        $districts[$district_id]['temples'][] = $temple;
    }
    
    // วาดข้อมูลแยกตามเมือง
    foreach ($districts as $district_id => $district_data) {
        // หัวข้อเมือง
        $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'ເມືອງ: ' . $district_data['name']);
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($districtHeaderStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;
        
        // หัวตาราง
        $headers = ['ລ/ດ', 'ຊື່ວັດ', 'ແຂວງ', 'ເມືອງ', 'ເຈົ້າອາວາດ', 'ໂທລະສັບ'];
        $sheet->fromArray($headers, NULL, 'A' . $currentRow);
        $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;
        
        // ข้อมูลวัดในเมืองนี้
        foreach ($district_data['temples'] as $index => $temple) {
            $row = [
                $index + 1,
                $temple['name'],
                $temple['province_name'] ?? '-',
                $temple['district'] ?? '-',
                $temple['abbot_name'] ?? '-',
                $temple['phone'] ?? '-'
            ];
            
            $sheet->fromArray($row, NULL, 'A' . $currentRow);
            
            // จัดสไตล์แถว
            $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($dataStyle);
            
            // สลับสีพื้นหลังแถวคู่
            if (($index + 1) % 2 == 0) {
                $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($evenRowStyle);
            }
            
            // จัดข้อความให้ตรงกลางสำหรับลำดับและโทรศัพท์
            $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // จัดข้อความชิดซ้ายสำหรับข้อมูลอื่น
            $sheet->getStyle('B' . $currentRow . ':E' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            
            $currentRow++;
        }
        
        // เว้นบรรทัด
        $currentRow += 1;
    }
    
    // สรุปรายงานตามเมือง
    $currentRow += 1;
    
    // หัวข้อสรุปรายงาน
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ສະຫຼຸບລາຍງານຕາມເມືອງ');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($summaryHeaderStyle);
    $sheet->getRowDimension($currentRow)->setRowHeight(25);
    $currentRow++;
    
    // หัวตารางสรุป
    $sheet->setCellValue("A{$currentRow}", 'ເມືອງ');
    $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
    $sheet->setCellValue("F{$currentRow}", 'ຈຳນວນວັດ');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($summaryTableHeaderStyle);
    $currentRow++;
    
    // ข้อมูลสรุปตามเมือง
    foreach ($districts as $district_id => $district_data) {
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", $district_data['name']);
        $sheet->setCellValue("F{$currentRow}", count($district_data['temples']) . ' ວັດ');
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $currentRow++;
    }
    
    // รวมทั้งหมด
    $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ລວມທັງໝົດ');
    $sheet->setCellValue("F{$currentRow}", count($temples) . ' ວັດ');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($summaryTotalStyle);
    $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    // กรณีไม่ได้กรองตามแขวง - แสดงแบบรวม
    $headers = ['ລ/ດ', 'ຊື່ວັດ', 'ແຂວງ', 'ເມືອງ', 'ເຈົ້າອາວາດ', 'ໂທລະສັບ'];
    $sheet->fromArray($headers, NULL, 'A' . $currentRow);
    $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($headerStyle);
    $sheet->getRowDimension($currentRow)->setRowHeight(20);
    $currentRow++;
    
    // เพิ่มข้อมูลวัด
    foreach ($temples as $index => $temple) {
        $row = [
            $index + 1,
            $temple['name'],
            $temple['province_name'] ?? '-',
            $temple['district'] ?? '-',
            $temple['abbot_name'] ?? '-',
            $temple['phone'] ?? '-'
        ];
        
        $sheet->fromArray($row, NULL, 'A' . $currentRow);
        
        // จัดสไตล์แถว
        $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($dataStyle);
        
        // สลับสีพื้นหลังแถวคู่
        if (($index + 1) % 2 == 0) {
            $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->applyFromArray($evenRowStyle);
        }
        
        // จัดข้อความให้ตรงกลางสำหรับลำดับและโทรศัพท์
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // จัดข้อความชิดซ้ายสำหรับข้อมูลอื่น
        $sheet->getStyle('B' . $currentRow . ':E' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        $currentRow++;
    }
    
    // สรุปข้อมูล
    $currentRow += 2;
    
    // หัวข้อสรุป
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ສະຫຼຸບລາຍງານ');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($summaryHeaderStyle);
    $sheet->getRowDimension($currentRow)->setRowHeight(25);
    $currentRow++;
    
    // จำนวนวัดแยกตามสถานะ
    $active_temples = count(array_filter($temples, function($temple) {
        return $temple['status'] === 'active';
    }));
    $inactive_temples = count($temples) - $active_temples;
    $active_percent = count($temples) > 0 ? ($active_temples / count($temples)) * 100 : 0;
    $inactive_percent = count($temples) > 0 ? ($inactive_temples / count($temples)) * 100 : 0;
    
    // แสดงจำนวนวัดที่เปิดใช้งาน
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ວັດທີ່ເປີດໃຊ້ງານ: ' . $active_temples . ' ວັດ (' . number_format($active_percent, 1) . '%)');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$currentRow}")->getFont()->setSize(12)->setName('Phetsarath OT');
    $sheet->getStyle("A{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
    $currentRow++;
    
    // แสดงจำนวนวัดที่ปิดใช้งาน
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ວັດທີ່ປິດໃຊ້ງານ: ' . $inactive_temples . ' ວັດ (' . number_format($inactive_percent, 1) . '%)');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A{$currentRow}")->getFont()->setSize(12)->setName('Phetsarath OT');
    $sheet->getStyle("A{$currentRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCFCFC');
    $currentRow++;
    
    // รวมทั้งหมด
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", 'ລວມທັງໝົດ: ' . count($temples) . ' ວັດ');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($summaryTotalStyle);
    $currentRow++;
}

// ลายเซ็น
$currentRow += 3;
$sheet->mergeCells("E{$currentRow}:F{$currentRow}");
$sheet->setCellValue("E{$currentRow}", 'ຫ້ອງການບໍລິຫານ');
$sheet->getStyle("E{$currentRow}")->getFont()->setBold(true)->setSize(12)->setName('Phetsarath OT');
$sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$currentRow += 2;
$sheet->mergeCells("E{$currentRow}:F{$currentRow}");
$sheet->setCellValue("E{$currentRow}", '___________________________');
$sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$currentRow += 1;
$sheet->mergeCells("E{$currentRow}:F{$currentRow}");
$sheet->setCellValue("E{$currentRow}", '(.....................................................)');
$sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("E{$currentRow}")->getFont()->setSize(10)->setName('Phetsarath OT');

$currentRow += 1;
$sheet->mergeCells("E{$currentRow}:F{$currentRow}");
$sheet->setCellValue("E{$currentRow}", 'ວັນທີ່ ......./......./...........');
$sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("E{$currentRow}")->getFont()->setSize(10)->setName('Phetsarath OT');

// เพิ่มข้อมูลในส่วนท้าย (footer) ของเอกสาร
$lastRow = $currentRow + 2;
$sheet->mergeCells("A{$lastRow}:C{$lastRow}");
$sheet->setCellValue("A{$lastRow}", 'ລະບົບຄຸ້ມຄອງວັດ | ຜູ້ໃຊ້: ' . ($_SESSION['user']['username'] ?? 'ລະບົບ'));
$sheet->getStyle("A{$lastRow}")->getFont()->setSize(8)->setName('Phetsarath OT');
$sheet->getStyle("A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$sheet->mergeCells("D{$lastRow}:F{$lastRow}");
$sheet->setCellValue("D{$lastRow}", 'ສ້າງເມື່ອ: ' . date('d/m/Y H:i:s'));
$sheet->getStyle("D{$lastRow}")->getFont()->setSize(8)->setName('Phetsarath OT');
$sheet->getStyle("D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

// ส่งออกไฟล์ Excel
try {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    error_log("Excel Output Error: " . $e->getMessage());
    die("เกิดข้อผิดพลาดในการสร้างไฟล์ Excel");
}

exit;
?>