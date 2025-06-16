<?php
session_start();

require_once '../config/db.php';
require_once '../config/base_url.php';

require_once('../vendor/autoload.php');

// ตั้งค่าให้ TCPDF หาฟอนต์ในโฟลเดอร์เพิ่มเติม
$tcpdf_fonts_path = dirname(__FILE__) . '/../vendor/tecnickcom/tcpdf/fonts/';
TCPDF_FONTS::addTTFfont(
    dirname(__FILE__) . '/../assets/fonts/saysettha_ot.ttf',
    'TrueTypeUnicode',
    '', 
    96
);

// ກວດສອບວ່າຜູ້ໃຊ້ເຂົ້າສູ່ລະບົບແລ້ວຫຼືບໍ່
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

// ກວດສອບສິດໃນການເຂົ້າເຖິບຂໍ້ມູນ
$is_superadmin = $_SESSION['user']['role'] === 'superadmin';
$is_admin = $_SESSION['user']['role'] === 'admin';
$user_temple_id = $_SESSION['user']['temple_id'] ?? null;

// ຕັ້ງຄ່າຕົວກອງ
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$temple_filter = isset($_GET['temple_id']) && is_numeric($_GET['temple_id']) ? (int)$_GET['temple_id'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';

// ຖ້າບໍ່ແມ່ນ superadmin, ຈຳກັດການເຂົ້າເຖິບຂໍ້ມູນ
if (!$is_superadmin && $is_admin && $user_temple_id) {
    $temple_filter = $user_temple_id;
}

// ຈັດຕຽມ SQL ແລະ ຕົວກັ່ນຕອງ
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

// SQL ພື້ນຖານ
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

// Try to get temple name for report title
$temple_name = '';
if ($temple_filter) {
    try {
        $temple_stmt = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $temple_stmt->execute([$temple_filter]);
        $temple_name = $temple_stmt->fetch(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        // Silently handle error
    }
}

// Require TCPDF library (needs to be installed)
require_once('../vendor/autoload.php');

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Temple Management System');
$pdf->SetTitle('ລາຍງານຂໍ້ມູນພຣະສົງ');
$pdf->SetSubject('ຂໍ້ມູນພຣະສົງ');

// Remove header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(5, 5, 5);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 10);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// ใช้ฟอนต์ที่กำหนด
$pdf->SetFont('dejavusans', '', 12);

// Add a page
$pdf->AddPage();
// Set document headers
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'ສາທາລະນະລັດ ປະຊາທິປະໄຕ ປະຊາຊົນລາວ', 0, 1, 'C');
$pdf->Cell(0, 10, 'ສັນຕິພາບ ເອກະລາດ ປະຊາທິປະໄຕ ເອກະພາບ ວັດທະນະຖາວອນ', 0, 1, 'C');

// Set report title
$title = 'ລາຍງານຂໍ້ມູນພຣະສົງ';
$pdf->SetFont('dejavusans', '', 10); // Reduced from 16 to 14
         
if ($temple_name) {
    $title .= ' - ວັດ' . $temple_name;
}
if ($status_filter) {
    $title .= ' - ' . ($status_filter == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ');
}

$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, $title, 0, 1, 'C');
$pdf->Ln(5);

// Filter info
$pdf->SetFont('dejavusans', '', 10);
$filter_text = 'ຕົວກອງ: ';
if ($search_term) $filter_text .= 'ຄົ້ນຫາ "' . $search_term . '"';
if ($position_filter) $filter_text .= ($search_term ? ', ' : '') . 'ຕໍາແໜ່ງ "' . $position_filter . '"';
if ($filter_text != 'ຕົວກອງ: ') {
    $pdf->Cell(0, 6, $filter_text, 0, 1, 'L');
    $pdf->Ln(2);
}
$pdf->Cell(0, 6, 'ວັນທີ່ພິມ: ' . date('d/m/Y H:i'), 0, 1, 'L');
$pdf->Ln(5);

// Table header
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(10, 10, 'ລຳດັບ', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'ຄຳນຳໜ້າ', 1, 0, 'C', true); // เพิ่มคอลัมน์ prefix
$pdf->Cell(45, 10, 'ຊື່', 1, 0, 'C', true); // ลดความกว้างลงเล็กน้อย
$pdf->Cell(35, 10, 'ນາມສະກຸນ', 1, 0, 'C', true); // ปรับความกว้างลง
$pdf->Cell(20, 10, 'ພັນສາ', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'ວັນບວດ', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'ຕໍາແໜ່ງ', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'ວັດ', 1, 0, 'C', true); // ปรับความกว้างลง
$pdf->Cell(20, 10, 'ສະຖານະ', 1, 1, 'C', true);

// Table content
$pdf->SetFont('dejavusans', '', 8);

if (count($monks) > 0) {
    foreach($monks as $i => $monk) {
        $pdf->Cell(10, 8, $i + 1, 1, 0, 'C');
        $pdf->Cell(20, 8, $monk['prefix'] ?? '-', 1, 0, 'C'); // เพิ่มคอลัมน์ prefix
        $pdf->Cell(45, 8, $monk['name'], 1, 0, 'L'); // ลดความกว้างลงเล็กน้อย
        $pdf->Cell(35, 8, $monk['lay_name'] ?? '-', 1, 0, 'L'); // ปรับความกว้างลง
        $pdf->Cell(20, 8, $monk['pansa'] . ' ພັນສາ', 1, 0, 'C');
        
        $ordination_date = $monk['ordination_date'] ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-';
        $pdf->Cell(30, 8, $ordination_date, 1, 0, 'C');
        
        $pdf->Cell(40, 8, $monk['position'] ?? '-', 1, 0, 'L');
        $pdf->Cell(50, 8, $monk['temple_name'], 1, 0, 'L'); // ปรับความกว้างลง
        
        $status_text = $monk['status'] == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ';
        $pdf->Cell(20, 8, $status_text, 1, 1, 'C');
    }
} else {
    // คำนวณความกว้างทั้งหมดของคอลัมน์
    $total_width = 10 + 20 + 45 + 35 + 20 + 30 + 40 + 50 + 20; // 270
    $pdf->Cell($total_width, 10, 'ບໍ່ພົບຂໍ້ມູນພຣະສົງທີ່ຕົງຕາມເງື່ອນໄຂ', 1, 1, 'C');
}

// Summary
$pdf->Ln(5);
$pdf->SetFont('saysettha_ot', 'B', 12);
$pdf->Cell(0, 8, 'ສະຫຼຸບ:', 0, 1);
$pdf->SetFont('saysettha_ot', '', 10);
$pdf->Cell(0, 6, 'ຈໍານວນພຣະສົງທັງໝົດ: ' . count($monks) . ' ລາຍການ', 0, 1);

// นับจำนวนแยกตาม prefix
$prefix_count = [];
foreach ($monks as $monk) {
    $prefix = $monk['prefix'] ?: 'ບໍ່ໄດ້ລະບຸ'; // 'ไม่ได้ระบุ' ในภาษาลาว
    if (!isset($prefix_count[$prefix])) {
        $prefix_count[$prefix] = 0;
    }
    $prefix_count[$prefix]++;
}

// เพิ่มตารางสรุปจำแนกตาม prefix
$pdf->Ln(5);
$pdf->SetFont('saysettha_ot', 'B', 12);
$pdf->Cell(0, 8, 'ສະຫຼຸບຕາມຄຳນຳໜ້າ:', 0, 1);

// สร้างตารางสรุป
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetFillColor(200, 220, 255);

// หัวตารางสรุป
$pdf->Cell(20, 8, 'ລຳດັບ', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'ຄຳນຳໜ້າ', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'ຈຳນວນ', 1, 1, 'C', true);

// เนื้อหาตารางสรุป
$pdf->SetFont('dejavusans', '', 10);
$i = 1;
$total = 0;
foreach ($prefix_count as $prefix => $count) {
    $pdf->Cell(20, 8, $i++, 1, 0, 'C');
    $pdf->Cell(40, 8, $prefix, 1, 0, 'C');
    $pdf->Cell(30, 8, $count . ' ລາຍການ', 1, 1, 'C');
    $total += $count;
}

// แสดงยอดรวม
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Cell(60, 8, 'ລວມທັງໝົດ', 1, 0, 'C', true);
$pdf->Cell(30, 8, $total . ' ລາຍການ', 1, 1, 'C', true);

// Output the PDF
$pdf->Output('ລາຍງານຂໍ້ມູນພຣະສົງ.pdf', 'I');