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
function buildWhereConditions($user_role, $user_id, $user_temple_id, $search, $province, $status) {
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
function fetchTempleData($pdo, $where_clause, $params) {
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
                CASE 
                    WHEN t.status = 'active' THEN 'ເປີດໃຊ້ງານ'
                    WHEN t.status = 'inactive' THEN 'ປິດໃຊ້ງານ'
                    ELSE 'ບໍ່ລະບຸ'
                END as status_text
            FROM temples t 
            LEFT JOIN provinces p ON t.province_id = p.province_id 
            LEFT JOIN districts d ON t.district_id = d.district_id
            {$where_clause}
            ORDER BY p.province_name, t.name ASC
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
$status = sanitizeInput($_GET['status'] ?? '');

try {
    // สร้างเงื่อนไขการค้นหา
    $conditions = buildWhereConditions($user_role, $user_id, $user_temple_id, $search, $province, $status);
    
    // ดึงข้อมูลวัด
    $temples = fetchTempleData($pdo, $conditions['where_clause'], $conditions['params']);
    
    if (empty($temples)) {
        throw new Exception("ไม่พบข้อมูลที่ตรงตามเงื่อนไข");
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
$summaryStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'name' => 'Phetsarath OT'
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
            'rgb' => 'E8EAF6' // สีฟ้าอ่อน
        ]
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// กำหนดความกว้างคอลัมน์
$sheet->getColumnDimension('A')->setWidth(8);  // No.
$sheet->getColumnDimension('B')->setWidth(35); // ชื่อวัด
$sheet->getColumnDimension('C')->setWidth(20); // แขวง
$sheet->getColumnDimension('D')->setWidth(20); // เมือง
$sheet->getColumnDimension('E')->setWidth(25); // เจ้าอาวาส
$sheet->getColumnDimension('F')->setWidth(18); // โทรศัพท์
$sheet->getColumnDimension('G')->setWidth(15); // สถานะ

// เพิ่มหัวรายงาน
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'ລາຍງານຂໍ້ມູນວັດ');
$sheet->getStyle('A1')->applyFromArray($titleStyle);
$sheet->getRowDimension(1)->setRowHeight(30);

// วันที่และเวลา
$sheet->mergeCells('A2:G2');
$sheet->setCellValue('A2', 'ວັນທີ່: ' . date('d/m/Y H:i:s'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFont()->setSize(11)->setName('Phetsarath OT');

// ตัวกรอง
$filter_parts = [];
if (!empty($search)) {
    $filter_parts[] = 'ຄໍາຄົ້ນຫາ: "' . $search . '"';
}
if (!empty($province)) {
    $filter_parts[] = 'ແຂວງ: ' . $province;
}
if (!empty($status)) {
    $status_text = ($status === 'active') ? 'ເປີດໃຊ້ງານ' : 'ປິດໃຊ້ງານ';
    $filter_parts[] = 'ສະຖານະ: ' . $status_text;
}

$filter_text = !empty($filter_parts) ? 'ຕົວກອງ: ' . implode(' | ', $filter_parts) : 'ຕົວກອງ: ທັງໝົດ';

$sheet->mergeCells('A3:G3');
$sheet->setCellValue('A3', $filter_text);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A3')->getFont()->setSize(11)->setName('Phetsarath OT');

$sheet->mergeCells('A4:G4');
$sheet->setCellValue('A4', 'ຈໍານວນລາຍການທັງໝົດ: ' . count($temples) . ' ວັດ');
$sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('A4')->getFont()->setSize(11)->setName('Phetsarath OT')->setBold(true);
$sheet->getStyle('A4')->getFont()->getColor()->setRGB('CC0000'); // สีแดง

// เว้นบรรทัด
$currentRow = 6;

// เพิ่มหัวตาราง
$headers = ['ລ/ດ', 'ຊື່ວັດ', 'ແຂວງ', 'ເມືອງ', 'ເຈົ້າອາວາດ', 'ໂທລະສັບ', 'ສະຖານະ'];
$sheet->fromArray($headers, NULL, 'A' . $currentRow);
$sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(20);

// เพิ่มข้อมูลวัด
$currentRow++;
foreach ($temples as $index => $temple) {
    $row = [
        $index + 1,
        $temple['name'],
        $temple['province_name'] ?? '-', 
        $temple['district'] ?? '-',
        $temple['abbot_name'] ?? '-',
        $temple['phone'] ?? '-',
        $temple['status_text'] ?? '-'
    ];
    
    $sheet->fromArray($row, NULL, 'A' . $currentRow);
    
    // จัดสไตล์แถว
    $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray($dataStyle);
    
    // สลับสีพื้นหลังแถวคู่
    if (($index + 1) % 2 == 0) {
        $sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->applyFromArray($evenRowStyle);
    }
    
    // จัดข้อความให้ตรงกลางสำหรับลำดับและโทรศัพท์
    $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // จัดข้อความชิดซ้ายสำหรับข้อมูลอื่น
    $sheet->getStyle('B' . $currentRow . ':E' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    
    // สีสถานะ
    if ($temple['status'] == 'active') {
        $sheet->getStyle('G' . $currentRow)->getFont()->getColor()->setRGB('009900'); // สีเขียว
    } else {
        $sheet->getStyle('G' . $currentRow)->getFont()->getColor()->setRGB('CC0000'); // สีแดง
    }
    
    $sheet->getStyle('G' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $currentRow++;
}

// สรุปข้อมูล
$currentRow += 2;
$summaryStartRow = $currentRow;

// หัวข้อสรุป
$sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('A' . $currentRow, 'ສະຫຼຸບລາຍງານ');
$sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14)->setName('Phetsarath OT');
$sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getRowDimension($currentRow)->setRowHeight(25);
$sheet->getStyle('A' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2D3E50');
$sheet->getStyle('A' . $currentRow)->getFont()->getColor()->setRGB('FFFFFF');
$currentRow++;

// จำนวนวัดแยกตามสถานะ
$active_temples = count(array_filter($temples, function($temple) {
    return $temple['status'] === 'active';
}));
$inactive_temples = count($temples) - $active_temples;
$active_percent = count($temples) > 0 ? ($active_temples / count($temples)) * 100 : 0;
$inactive_percent = count($temples) > 0 ? ($inactive_temples / count($temples)) * 100 : 0;

// แสดงจำนวนวัดที่เปิดใช้งาน
$sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('A' . $currentRow, 'ວັດທີ່ເປີດໃຊ້ງານ: ' . $active_temples . ' ວັດ (' . number_format($active_percent, 1) . '%)');
$sheet->getStyle('A' . $currentRow)->getFont()->setSize(12)->setName('Phetsarath OT');
$sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
$sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$currentRow++;

// แสดงจำนวนวัดที่ปิดใช้งาน
$sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('A' . $currentRow, 'ວັດທີ່ປິດໃຊ້ງານ: ' . $inactive_temples . ' ວັດ (' . number_format($inactive_percent, 1) . '%)');
$sheet->getStyle('A' . $currentRow)->getFont()->setSize(12)->setName('Phetsarath OT');
$sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCFCFC');
$sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$currentRow++;

// รวมทั้งหมด
$sheet->mergeCells('A' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('A' . $currentRow, 'ລວມທັງໝົດ: ' . count($temples) . ' ວັດ');
$sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(12)->setName('Phetsarath OT');
$sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A' . $currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0FF');
$sheet->getStyle('A' . $currentRow)->getFont()->getColor()->setRGB('000096');
$sheet->getStyle('A' . $currentRow . ':G' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ลายเซ็น
$currentRow += 3;
$sheet->mergeCells('F' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('F' . $currentRow, 'ຫ້ອງການບໍລິຫານ');
$sheet->getStyle('F' . $currentRow)->getFont()->setBold(true)->setSize(12)->setName('Phetsarath OT');
$sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$currentRow += 2;
$sheet->mergeCells('F' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('F' . $currentRow, '___________________________');
$sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$currentRow += 1;
$sheet->mergeCells('F' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('F' . $currentRow, '(.....................................................)');
$sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . $currentRow)->getFont()->setSize(10)->setName('Phetsarath OT');

$currentRow += 1;
$sheet->mergeCells('F' . $currentRow . ':G' . $currentRow);
$sheet->setCellValue('F' . $currentRow, 'ວັນທີ່ ......./......./...........');
$sheet->getStyle('F' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F' . $currentRow)->getFont()->setSize(10)->setName('Phetsarath OT');

// เพิ่มข้อมูลในส่วนท้าย (footer) ของเอกสาร
$lastRow = $currentRow + 2;
$sheet->mergeCells('A' . $lastRow . ':D' . $lastRow);
$sheet->setCellValue('A' . $lastRow, 'ລະບົບຄຸ້ມຄອງວັດ | ຜູ້ໃຊ້: ' . ($_SESSION['user']['username'] ?? 'ລະບົບ'));
$sheet->getStyle('A' . $lastRow)->getFont()->setSize(8)->setName('Phetsarath OT');
$sheet->getStyle('A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$sheet->mergeCells('E' . $lastRow . ':G' . $lastRow);
$sheet->setCellValue('E' . $lastRow, 'ສ້າງເມື່ອ: ' . date('d/m/Y H:i:s'));
$sheet->getStyle('E' . $lastRow)->getFont()->setSize(8)->setName('Phetsarath OT');
$sheet->getStyle('E' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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