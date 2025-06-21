<?php
// filepath: c:\xampp\htdocs\temples\reports\generate_excel_monks.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึงตามบทบาท
$user_role = $_SESSION['user']['role'] ?? '';
$user_id = $_SESSION['user']['id'] ?? 0;
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ถ้าเป็น user ทั่วไป ไม่อนุญาตให้เข้าถึงหน้านี้
if ($user_role === 'user') {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຫນ້ານີ້";
    header('Location: ' . $base_url . 'monks/');
    exit;
}

// ตัวแปรตรวจสอบสิทธิ์
$is_superadmin = ($user_role === 'superadmin');
$is_province_admin = ($user_role === 'province_admin');
$is_temple_admin = ($user_role === 'admin');

// ตั้งค่าตัวกรอง
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$temple_filter = isset($_GET['temple_id']) && is_numeric($_GET['temple_id']) ? (int)$_GET['temple_id'] : 0;
$province_filter = isset($_GET['province_id']) && is_numeric($_GET['province_id']) ? (int)$_GET['province_id'] : 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';

// ตรวจสอบและจำกัดสิทธิ์ตามบทบาท
if ($is_temple_admin && $user_temple_id) {
    // Admin วัด ดูได้เฉพาะวัดตัวเอง ไม่สนใจว่า temple_filter จะเป็นอะไร
    $temple_filter = $user_temple_id;
} elseif ($is_province_admin) {
    // Province Admin ดูได้เฉพาะแขวงที่รับผิดชอบ
    try {
        // ตรวจสอบแขวงที่ Province Admin รับผิดชอบ
        $stmt = $pdo->prepare("SELECT province_id FROM user_province_access WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $allowed_provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // ถ้ามีการระบุ province_filter แต่ไม่อยู่ในรายการที่รับผิดชอบ
        if ($province_filter > 0 && !in_array($province_filter, $allowed_provinces)) {
            $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນແຂວງນີ້";
            header('Location: ' . $base_url . 'monks/');
            exit;
        }
        
        // ถ้าไม่ได้ระบุ province_filter แต่มีแขวงที่รับผิดชอบ ใช้แขวงแรก
        if ($province_filter == 0 && count($allowed_provinces) > 0) {
            $province_filter = $allowed_provinces[0];
        }
    } catch (PDOException $e) {
        error_log('Error checking province access: ' . $e->getMessage());
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການກວດສອບສິດການເຂົ້າເຖິງ";
        header('Location: ' . $base_url . 'monks/');
        exit;
    }
}

// จัดเตรียม SQL และพารามิเตอร์
$sql_where = " WHERE 1=1 ";
$params = [];

// ใส่เงื่อนไขกรองตามสิทธิ์การเข้าถึง
if ($is_temple_admin) {
    // Admin วัด เห็นเฉพาะวัดตัวเอง
    $sql_where .= " AND m.temple_id = ? ";
    $params[] = $temple_filter;
} elseif ($is_province_admin) {
    // Province Admin เห็นเฉพาะแขวงที่รับผิดชอบ
    $sql_where .= " AND t.province_id = ? ";
    $params[] = $province_filter;
    
    // ถ้ามีการกรองวัด
    if ($temple_filter > 0) {
        $sql_where .= " AND m.temple_id = ? ";
        $params[] = $temple_filter;
    }
} else {
    // Superadmin เห็นทั้งหมด - แต่อาจมีการกรองเพิ่มเติม
    if ($temple_filter > 0) {
        $sql_where .= " AND m.temple_id = ? ";
        $params[] = $temple_filter;
    }
    
    if ($province_filter > 0) {
        $sql_where .= " AND t.province_id = ? ";
        $params[] = $province_filter;
    }
}

// เพิ่มเงื่อนไขการกรองอื่นๆ
if ($status_filter !== '') {
    $sql_where .= " AND m.status = ? ";
    $params[] = $status_filter;
}

if ($position_filter !== '') {
    $sql_where .= " AND m.position = ? ";
    $params[] = $position_filter;
}

if ($search_term !== '') {
    $sql_where .= " AND (m.name LIKE ? OR m.lay_name LIKE ? OR m.contact_number LIKE ?) ";
    $search_param = "%{$search_term}%";
    array_push($params, $search_param, $search_param, $search_param);
}

// SQL พื้นฐาน - เพิ่ม join กับตาราง provinces
$sql = "
    SELECT 
        m.*, 
        t.name as temple_name,
        p.province_name
    FROM 
        monks m
    JOIN 
        temples t ON m.temple_id = t.id
    LEFT JOIN
        provinces p ON t.province_id = p.province_id
    $sql_where
    ORDER BY 
        t.name ASC, m.name ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $monks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage());
}

// ดึงข้อมูลประกอบสำหรับชื่อไฟล์และหัวรายงาน
$report_title = 'ຂໍ້ມູນພຣະສົງ';
$temple_name = '';
$province_name = '';

if ($temple_filter) {
    try {
        $temple_stmt = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $temple_stmt->execute([$temple_filter]);
        $temple_name = $temple_stmt->fetch(PDO::FETCH_COLUMN);
        $report_title .= '_ວັດ' . $temple_name;
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ไม่เปลี่ยนชื่อรายงาน
    }
}

if ($province_filter) {
    try {
        $province_stmt = $pdo->prepare("SELECT province_name FROM provinces WHERE province_id = ?");
        $province_stmt->execute([$province_filter]);
        $province_name = $province_stmt->fetch(PDO::FETCH_COLUMN);
        if (!$temple_name) { // เพิ่มชื่อแขวงเมื่อไม่ได้กรองตามวัด
            $report_title .= '_ແຂວງ' . $province_name;
        }
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ไม่เปลี่ยนชื่อรายงาน
    }
}

// ตรวจสอบว่า PhpSpreadsheet ถูกติดตั้งหรือไม่
if (!file_exists('../vendor/autoload.php')) {
    die("ກະລຸນາຕິດຕັ້ງ PhpSpreadsheet ໂດຍໃຊ້ Composer: composer require phpoffice/phpspreadsheet");
}

// โหลด library
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// สร้าง spreadsheet ใหม่
try {
    // Start output buffer
    ob_start();
    
    // Create spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ຂໍ້ມູນພຣະສົງ');
    
    // สร้างส่วนหัวรายงาน
    $sheet->setCellValue('A1', 'ລາຍງານຂໍ້ມູນພຣະສົງ');
    
    if ($temple_name) {
        $sheet->setCellValue('A2', 'ວັດ: ' . $temple_name);
    }
    
    if ($province_name) {
        $sheet->setCellValue('A3', 'ແຂວງ: ' . $province_name);
    }
    
    $sheet->setCellValue('A4', 'ວັນທີ່ພິມ: ' . date('d/m/Y H:i'));
    $sheet->setCellValue('A5', 'ຜູ້ພິມ: ' . $_SESSION['user']['name']);
    
    // จัดรูปแบบส่วนหัวรายงาน
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2:A5')->getFont()->setSize(12);
    $sheet->mergeCells('A1:O1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // เว้นช่องว่าง
    $header_row = 7;
    
    // ตั้งค่าส่วนหัวของตาราง
    $sheet->setCellValue('A'.$header_row, 'ລຳດັບ');
    $sheet->setCellValue('B'.$header_row, 'ຄຳນຳໜ້າ');
    $sheet->setCellValue('C'.$header_row, 'ຊື່');
    $sheet->setCellValue('D'.$header_row, 'ນາມສະກຸນ');
    $sheet->setCellValue('E'.$header_row, 'ຈໍານວນພັນສາ');
    $sheet->setCellValue('F'.$header_row, 'ວັນບວດ');
    $sheet->setCellValue('G'.$header_row, 'ວັນເກີດ');
    $sheet->setCellValue('H'.$header_row, 'ແຂວງເກີດ');
    $sheet->setCellValue('I'.$header_row, 'ການສຶກສາ');
    $sheet->setCellValue('J'.$header_row, 'ການສຶກສາທາງທຳມະ');
    $sheet->setCellValue('K'.$header_row, 'ເບີໂທຕິດຕໍ່');
    $sheet->setCellValue('L'.$header_row, 'ຕໍາແໜ່ງ');
    $sheet->setCellValue('M'.$header_row, 'ວັດ');
    $sheet->setCellValue('N'.$header_row, 'ແຂວງ');
    $sheet->setCellValue('O'.$header_row, 'ສະຖານະ');

    // จัดรูปแบบส่วนหัวตาราง
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'D9EAD3', // พื้นหลังสีเขียวอ่อน
            ],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $sheet->getStyle('A'.$header_row.':O'.$header_row)->applyFromArray($headerStyle);
    
    // ตั้งค่าความกว้างของคอลัมน์
    $sheet->getColumnDimension('A')->setWidth(10); // ลำดับ
    $sheet->getColumnDimension('B')->setWidth(15); // คำนำหน้า
    $sheet->getColumnDimension('C')->setWidth(25); // ชื่อพระสงฆ์
    $sheet->getColumnDimension('D')->setWidth(25); // ชื่อก่อนบวช
    $sheet->getColumnDimension('E')->setWidth(15); // จำนวนพรรษา
    $sheet->getColumnDimension('F')->setWidth(15); // วันบวช
    $sheet->getColumnDimension('G')->setWidth(15); // วันเกิด
    $sheet->getColumnDimension('H')->setWidth(20); // แขวงเกิด
    $sheet->getColumnDimension('I')->setWidth(20); // การศึกษา
    $sheet->getColumnDimension('J')->setWidth(20); // การศึกษาทางธรรม
    $sheet->getColumnDimension('K')->setWidth(15); // เบอร์โทรติดต่อ
    $sheet->getColumnDimension('L')->setWidth(20); // ตำแหน่ง
    $sheet->getColumnDimension('M')->setWidth(30); // วัด
    $sheet->getColumnDimension('N')->setWidth(20); // แขวง
    $sheet->getColumnDimension('O')->setWidth(15); // สถานะ
    
    // เพิ่มข้อมูลในแต่ละแถว
    $row = $header_row + 1;
    foreach ($monks as $index => $monk) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $monk['prefix'] ?? '-');
        $sheet->setCellValue('C' . $row, $monk['name'] ?? '');
        $sheet->setCellValue('D' . $row, $monk['lay_name'] ?? '-');
        $sheet->setCellValue('E' . $row, ($monk['pansa'] ?? '0') . ' ພັນສາ');
        $sheet->setCellValue('F' . $row, !empty($monk['ordination_date']) ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-');
        $sheet->setCellValue('G' . $row, !empty($monk['birth_date']) ? date('d/m/Y', strtotime($monk['birth_date'])) : '-');
        $sheet->setCellValue('H' . $row, $monk['birth_province'] ?? '-');
        $sheet->setCellValue('I' . $row, $monk['education'] ?? '-');
        $sheet->setCellValue('J' . $row, $monk['dharma_education'] ?? '-');
        $sheet->setCellValue('K' . $row, $monk['contact_number'] ?? '-');
        $sheet->setCellValue('L' . $row, $monk['position'] ?? '-');
        $sheet->setCellValue('M' . $row, $monk['temple_name'] ?? '-');
        $sheet->setCellValue('N' . $row, $monk['province_name'] ?? '-');
        $sheet->setCellValue('O' . $row, ($monk['status'] ?? '') == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ');
        $row++;
    }
    
    // ใส่กรอบให้กับทุกเซลล์ที่มีข้อมูล
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    if ($row > ($header_row + 1)) {
        $sheet->getStyle('A'.($header_row).':O' . ($row - 1))->applyFromArray($styleArray);
    }
    
    // จัดข้อความในบางคอลัมน์ให้อยู่กึ่งกลาง
    if ($row > ($header_row + 1)) {
        $sheet->getStyle('A'.($header_row + 1).':A' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.($header_row + 1).':B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E'.($header_row + 1).':G' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O'.($header_row + 1).':O' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    
    // เพิ่มข้อมูลสรุป
    $summary_row = $row + 2;
    $sheet->setCellValue('A' . $summary_row, 'ສະຫຼຸບ:');
    $sheet->getStyle('A' . $summary_row)->getFont()->setBold(true);
    $summary_row++;
    
    $sheet->setCellValue('A' . $summary_row, 'ຈຳນວນພຣະສົງທັງໝົດ: ' . count($monks) . ' ລາຍການ');
    $summary_row += 2;
    
    // นับจำนวนแยกตาม prefix
    $prefix_count = [];
    foreach ($monks as $monk) {
        $prefix = $monk['prefix'] ?: 'ບໍ່ໄດ້ລະບຸ';
        if (!isset($prefix_count[$prefix])) {
            $prefix_count[$prefix] = 0;
        }
        $prefix_count[$prefix]++;
    }

    // เพิ่มส่วนสรุปแยกตาม prefix
    $sheet->setCellValue('A' . $summary_row, 'ສະຫລຸບຈຳນວນຕາມຄຳນຳໜ້າ:');
    $sheet->getStyle('A' . $summary_row)->getFont()->setBold(true);
    $summary_row++;

    // สร้างหัวตารางสรุป
    $sheet->setCellValue('A' . $summary_row, 'ລຳດັບ');
    $sheet->setCellValue('B' . $summary_row, 'ຄຳນຳໜ້າ');
    $sheet->setCellValue('C' . $summary_row, 'ຈຳນວນ');

    // จัดรูปแบบหัวตารางสรุป
    $sheet->getStyle('A' . $summary_row . ':C' . $summary_row)->applyFromArray($headerStyle);
    $summary_row++;

    // เพิ่มข้อมูลสรุปแยกตาม prefix
    $i = 1;
    foreach ($prefix_count as $prefix => $count) {
        $sheet->setCellValue('A' . $summary_row, $i++);
        $sheet->setCellValue('B' . $summary_row, $prefix);
        $sheet->setCellValue('C' . $summary_row, $count);
        
        $sheet->getStyle('A' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $summary_row++;
    }

    // เพิ่มเส้นกรอบให้ตารางสรุป
    if (count($prefix_count) > 0) {
        $sheet->getStyle('A' . ($summary_row - count($prefix_count) - 1) . ':C' . ($summary_row - 1))->applyFromArray($styleArray);
    }

    // รวมทั้งหมด
    $sheet->setCellValue('A' . $summary_row, 'ລວມທັງໝົດ');
    $sheet->setCellValue('C' . $summary_row, count($monks));
    $sheet->mergeCells('A' . $summary_row . ':B' . $summary_row);
    $sheet->getStyle('A' . $summary_row . ':C' . $summary_row)->getFont()->setBold(true);
    $sheet->getStyle('A' . $summary_row . ':C' . $summary_row)->applyFromArray($styleArray);
    $sheet->getStyle('A' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $summary_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // ข้อมูลการกรอง
    $filter_text = 'ຕົວກອງທີ່ໃຊ້: ';
    $filter_parts = [];

    if ($province_filter) {
        $filter_parts[] = 'ແຂວງ "' . $province_name . '"';
    }
    if ($temple_filter) {
        $filter_parts[] = 'ວັດ "' . $temple_name . '"';
    }
    if ($status_filter) {
        $filter_parts[] = 'ສະຖານະ "' . ($status_filter == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ') . '"';
    }
    if ($search_term) {
        $filter_parts[] = 'ຄົ້ນຫາ "' . $search_term . '"';
    }
    if ($position_filter) {
        $filter_parts[] = 'ຕໍາແໜ່ງ "' . $position_filter . '"';
    }

    if (!empty($filter_parts)) {
        $filter_text .= implode(', ', $filter_parts);
        $sheet->setCellValue('A' . ($summary_row + 2), $filter_text);
    } else {
        $sheet->setCellValue('A' . ($summary_row + 2), 'ສະແດງທຸກຂໍ້ມູນທັງໝົດ (ບໍ່ມີການກອງ)');
    }

    // กำหนดชื่อไฟล์
    $filename = 'ລາຍງານ'.$report_title.'_' . date('Ymd_His') . '.xlsx';
    
    // ตั้งค่า header สำหรับการดาวน์โหลด
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // ตรวจสอบว่าไม่มีข้อมูลที่ส่งออกไปแล้วก่อนส่งไฟล์
    ob_clean();
    
    // สร้างไฟล์ Excel และส่งออก
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    die("ເກີດຂໍ້ຜິດພາດໃນການສ້າງໄຟລ໌ Excel: " . $e->getMessage());
}

// เพิ่มการตรวจสอบกรณีไม่พบข้อมูล
if (count($monks) === 0) {
    // แสดงข้อความว่าไม่พบข้อมูลตามเงื่อนไขที่เลือก
    $pdf->SetFont($fontname, '', 12);
    $pdf->Cell(0, 10, 'ບໍ່ພົບຂໍ້ມູນພຣະສົງຕາມເງື່ອນໄຂທີ່ເລືອກ', 0, 1, 'C');
    // ยังคงสร้างไฟล์ PDF แต่มีข้อความแจ้งว่าไม่พบข้อมูล
}

exit;