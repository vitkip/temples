<?php
// filepath: c:\xampp\htdocs\temples\reports\generate_excel_monks.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ตรวจสอบสิทธิ์การเข้าถึงข้อมูล
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ตั้งค่าตัวกรอง
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$temple_filter = isset($_GET['temple_id']) && is_numeric($_GET['temple_id']) ? (int)$_GET['temple_id'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';

// ถ้าไม่ใช่ superadmin จำกัดการเข้าถึงข้อมูล
if (!$is_superadmin && $is_admin && $user_temple_id) {
    $temple_filter = $user_temple_id;
}

// เตรียม SQL และตัวกรอง
$sql_where = " WHERE 1=1 ";
$params = [];

if ($status_filter !== '') {
    $sql_where .= " AND m.status = ? ";
    $params[] = $status_filter;
}

if ($temple_filter !== '') {
    $sql_where .= " AND m.temple_id = ? ";
    $params[] = $temple_filter;
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

// SQL พื้นฐาน
$sql = "
    SELECT 
        m.*, 
        t.name as temple_name
    FROM 
        monks m
    JOIN 
        temples t ON m.temple_id = t.id
    $sql_where
    ORDER BY 
        m.name ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $monks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage());
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
    
    // ตั้งค่าส่วนหัว
    $sheet->setCellValue('A1', 'ລຳດັບ');
    $sheet->setCellValue('B1', 'ຊື່ພຣະສົງ');
    $sheet->setCellValue('C1', 'ຊື່ກ່ອນບວດ');
    $sheet->setCellValue('D1', 'ຈໍານວນພັນສາ');
    $sheet->setCellValue('E1', 'ວັນບວດ');
    $sheet->setCellValue('F1', 'ວັນເກີດ');
    $sheet->setCellValue('G1', 'ການສຶກສາ');
    $sheet->setCellValue('H1', 'ການສຶກສາທາງທຳມະ');
    $sheet->setCellValue('I1', 'ເບີໂທຕິດຕໍ່');
    $sheet->setCellValue('J1', 'ຕໍາແໜ່ງ');
    $sheet->setCellValue('K1', 'ວັດ');
    $sheet->setCellValue('L1', 'ສະຖານະ');
    
    // จัดรูปแบบแถวส่วนหัว
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
    
    $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
    
    // ตั้งค่าความกว้างของคอลัมน์
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(20);
    $sheet->getColumnDimension('H')->setWidth(20);
    $sheet->getColumnDimension('I')->setWidth(15);
    $sheet->getColumnDimension('J')->setWidth(20);
    $sheet->getColumnDimension('K')->setWidth(30);
    $sheet->getColumnDimension('L')->setWidth(15);
    
    // เพิ่มข้อมูลในแต่ละแถว
    $row = 2;
    foreach ($monks as $index => $monk) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $monk['name'] ?? '');
        $sheet->setCellValue('C' . $row, $monk['lay_name'] ?? '-');
        $sheet->setCellValue('D' . $row, ($monk['pansa'] ?? '0') . ' ພັນສາ');
        $sheet->setCellValue('E' . $row, !empty($monk['ordination_date']) ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-');
        $sheet->setCellValue('F' . $row, !empty($monk['birth_date']) ? date('d/m/Y', strtotime($monk['birth_date'])) : '-');
        $sheet->setCellValue('G' . $row, $monk['education'] ?? '-');
        $sheet->setCellValue('H' . $row, $monk['dharma_education'] ?? '-');
        $sheet->setCellValue('I' . $row, $monk['contact_number'] ?? '-');
        $sheet->setCellValue('J' . $row, $monk['position'] ?? '-');
        $sheet->setCellValue('K' . $row, $monk['temple_name'] ?? '-');
        $sheet->setCellValue('L' . $row, ($monk['status'] ?? '') == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ');
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
    $sheet->getStyle('A1:L' . ($row - 1))->applyFromArray($styleArray);
    
    // จัดข้อความในบางคอลัมน์ให้อยู่กึ่งกลาง
    $sheet->getStyle('A2:A' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D2:F' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('L2:L' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // เพิ่มข้อมูลสรุป
    $sheet->setCellValue('A' . ($row + 1), 'ລາຍງານຂໍ້ມູນພຣະສົງ - ສ້າງວັນທີ ' . date('d/m/Y H:i'));
    $sheet->setCellValue('A' . ($row + 2), 'ຈຳນວນທັງໝົດ: ' . count($monks) . ' ລາຍການ');
    $sheet->mergeCells('A' . ($row + 1) . ':L' . ($row + 1));
    $sheet->mergeCells('A' . ($row + 2) . ':L' . ($row + 2));
    
    // ตั้งค่า header สำหรับการดาวน์โหลด
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ລາຍງານຂໍ້ມູນພຣະສົງ_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    // ตรวจสอบว่าไม่มีข้อมูลที่ส่งออกไปแล้วก่อนส่งไฟล์
    ob_clean();
    
    // สร้างไฟล์ Excel และส่งออก
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    die("ເກີດຂໍ້ຜິດພາດໃນການສ້າງໄຟລ໌ Excel: " . $e->getMessage());
}

exit;