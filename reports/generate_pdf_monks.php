<?php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// เพิ่มการโหลดไลบรารี TCPDF
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

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
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິບຂໍ້ມູນແລະບໍ່ສາມາດເຂົ້າເຖິບຂໍ້ມູນຂອງວັດແລະແຂວງ";
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
            $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິບຂໍ້ມູນແຂວງນີ້";
            header('Location: ' . $base_url . 'monks/');
            exit;
        }
        
        // ถ้าไม่ได้ระบุ province_filter แต่มีแขวงที่รับผิดชอบ ใช้แขวงแรก
        if ($province_filter == 0 && count($allowed_provinces) > 0) {
            $province_filter = $allowed_provinces[0];
        }
    } catch (PDOException $e) {
        error_log('Error checking province access: ' . $e->getMessage());
        $_SESSION['error'] = "ເກີດຂໍ້ຜິດພາດໃນການກວດສອບສິດການເຂົ້າເຖິບຂໍ້ມູນ";
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

// ดึงข้อมูลประกอบสำหรับหัวรายงาน
$report_title = 'ລາຍງານຂໍ້ມູນພຣະສົງ';
$temple_name = '';
$province_name = '';

if ($temple_filter) {
    try {
        $temple_stmt = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $temple_stmt->execute([$temple_filter]);
        $temple_name = $temple_stmt->fetch(PDO::FETCH_COLUMN);
        $report_title .= ' - ວັດ' . $temple_name;
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
            $report_title .= ' - ແຂວງ' . $province_name;
        }
    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ไม่เปลี่ยนชื่อรายงาน
    }
}

if ($status_filter) {
    $report_title .= ' - ' . ($status_filter == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ');
}

// กำหนด path สำหรับฟอนต์
$font_path = dirname(__FILE__) . '/../assets/fonts/Phetsarathot.ttf';
$font_path_bold = dirname(__FILE__) . '/../assets/fonts/Phetsarathotb.ttf';  // ไฟล์แบบ Bold (ถ้ามี)

// ตรวจสอบว่ามีไฟล์ฟอนต์อยู่จริง
if (!file_exists($font_path)) {
    die("ບໍ່ພົບໄຟລ໌ແບບອັກສອນທີ່: $font_path");
}

// สร้างอ็อบเจ็กต์ PDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// เพิ่มฟอนต์ปกติ
$fontname = TCPDF_FONTS::addTTFfont(
    $font_path,
    'TrueTypeUnicode',
    '',
    96
);

// เพิ่มฟอนต์ตัวหนาถ้ามีไฟล์
if (file_exists($font_path_bold)) {
    $fontname_bold = TCPDF_FONTS::addTTFfont(
        $font_path_bold,
        'TrueTypeUnicode',
        'B',
        96
    );
}

// ตั้งค่าข้อมูลเอกสาร
$pdf->SetCreator('Temple Management System');
$pdf->SetAuthor($_SESSION['user']['name'] . ' (' . $_SESSION['user']['role'] . ')');
$pdf->SetTitle($report_title);
$pdf->SetSubject('ຂໍ້ມູນພຣະສົງ');

// ไม่แสดง header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// ตั้งค่าฟอนต์ monospaced เริ่มต้น
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// ตั้งค่าขอบกระดาษ
$pdf->SetMargins(5, 10, 5);

// ตั้งค่าการแบ่งหน้าอัตโนมัติ
$pdf->SetAutoPageBreak(TRUE, 10);

// ตั้งค่าอัตราส่วนการปรับขนาดภาพ
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// เพิ่มหน้า
$pdf->AddPage();

// ตั้งค่าส่วนหัวเอกสาร
$pdf->SetFont($fontname, 'B', 12);
$pdf->Cell(0, 10, 'ສາທາລະນະລັດ ປະຊາທິປະໄຕ ປະຊາຊົນລາວ', 0, 1, 'C');
$pdf->Cell(0, 10, 'ສັນຕິພາບ ເອກະລາດ ປະຊາທິປະໄຕ ເອກະພາບ ວັດທະນະຖາວອນ', 0, 1, 'C');

// ตั้งค่าชื่อรายงาน
$pdf->SetFont($fontname, 'B', 12);
$pdf->Cell(0, 10, $report_title, 0, 1, 'C');
$pdf->Ln(5);

// ข้อมูลการกรอง
$pdf->SetFont($fontname, '', 10);
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
    $pdf->Cell(0, 6, $filter_text, 0, 1, 'L');
    $pdf->Ln(2);
} else {
    $pdf->Cell(0, 6, 'ສະແດງທຸກຂໍ້ມູນທັງໝົດ (ບໍ່ມີການກອງ)', 0, 1, 'L');
    $pdf->Ln(2);
}

// ข้อมูลรายงาน
$pdf->Cell(0, 6, 'ວັນທີ່ພິມ: ' . date('d/m/Y H:i'), 0, 1, 'L');
$pdf->Cell(0, 6, 'ຜູ້ພິມ: ' . $_SESSION['user']['name'], 0, 1, 'L');
$pdf->Ln(5);

// ส่วนหัวตาราง
$pdf->SetFont($fontname, 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(10, 10, 'ລຳດັບ', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'ຄຳນຳໜ້າ', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'ຊື່', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'ນາມສະກຸນ', 1, 0, 'C', true);
$pdf->Cell(15, 10, 'ພັນສາ', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'ວັນບວດ', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'ຕໍາແໜ່ງ', 1, 0, 'C', true);
$pdf->Cell(55, 10, 'ວັດ', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'ແຂວງ', 1, 0, 'C', true);
$pdf->Cell(15, 10, 'ສະຖານະ', 1, 1, 'C', true);

// เนื้อหาตาราง
$pdf->SetFont($fontname, '', 8);

if (count($monks) > 0) {
    foreach($monks as $i => $monk) {
        $pdf->Cell(10, 8, $i + 1, 1, 0, 'C');
        $pdf->Cell(20, 8, $monk['prefix'] ?? '-', 1, 0, 'C');
        $pdf->Cell(40, 8, $monk['name'], 1, 0, 'L');
        $pdf->Cell(35, 8, $monk['lay_name'] ?? '-', 1, 0, 'L');
        $pdf->Cell(15, 8, ($monk['pansa'] ?? '0') . ' ພັນສາ', 1, 0, 'C');
        
        $ordination_date = $monk['ordination_date'] ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-';
        $pdf->Cell(25, 8, $ordination_date, 1, 0, 'C');
        
        $pdf->Cell(35, 8, $monk['position'] ?? '-', 1, 0, 'L');
        $pdf->Cell(55, 8, $monk['temple_name'], 1, 0, 'L');
        $pdf->Cell(30, 8, $monk['province_name'] ?? '-', 1, 0, 'L');
        
        $status_text = $monk['status'] == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ';
        $pdf->Cell(15, 8, $status_text, 1, 1, 'C');
    }
} else {
    // คำนวณความกว้างทั้งหมดของคอลัมน์
    $total_width = 10 + 20 + 40 + 35 + 15 + 25 + 35 + 55 + 30 + 15; // 280
    $pdf->Cell($total_width, 10, 'ບໍ່ພົບຂໍ້ມູນພຣະສົງທີ່ຕົງຕາມເງື່ອນໄຂ', 1, 1, 'C');
}

// สรุปข้อมูล
$pdf->Ln(5);
$pdf->SetFont($fontname, 'B', 12);
$pdf->Cell(0, 8, 'ສະຫຼຸບ:', 0, 1);
$pdf->SetFont($fontname, '', 10);
$pdf->Cell(0, 6, 'ຈໍານວນພຣະສົງທັງໝົດ: ' . count($monks) . ' ລາຍການ', 0, 1);

// นับจำนวนแยกตาม prefix
$prefix_count = [];
foreach ($monks as $monk) {
    $prefix = $monk['prefix'] ?: 'ບໍ່ໄດ້ລະບຸ';
    if (!isset($prefix_count[$prefix])) {
        $prefix_count[$prefix] = 0;
    }
    $prefix_count[$prefix]++;
}

// สร้างตารางสรุปตาม prefix
$pdf->Ln(5);
$pdf->SetFont($fontname, 'B', 12);
$pdf->Cell(0, 8, 'ສະຫຼຸບຕາມຄຳນຳໜ້າ:', 0, 1);

// หัวตารางสรุป
$pdf->SetFont($fontname, 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(20, 8, 'ລຳດັບ', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'ຄຳນຳໜ້າ', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'ຈຳນວນ', 1, 1, 'C', true);

// เนื้อหาตารางสรุป
$pdf->SetFont($fontname, '', 10);
$i = 1;
$total = 0;
foreach ($prefix_count as $prefix => $count) {
    $pdf->Cell(20, 8, $i++, 1, 0, 'C');
    $pdf->Cell(40, 8, $prefix, 1, 0, 'C');
    $pdf->Cell(30, 8, $count . ' ລາຍການ', 1, 1, 'C');
    $total += $count;
}

// แสดงยอดรวม
$pdf->SetFont($fontname, 'B', 10);
$pdf->Cell(60, 8, 'ລວມທັງໝົດ', 1, 0, 'C', true);
$pdf->Cell(30, 8, $total . ' ລາຍການ', 1, 1, 'C', true);

// แสดง PDF
$pdf->Output('ລາຍງານຂໍ້ມູນພຣະສົງ.pdf', 'I');
?>