<?php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// เพิ่มการโหลดไลบรารี TCPDF
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

// สร้างคลาสที่ extend จาก TCPDF เพื่อเพิ่มหมายเลขหน้า
class PDF_WITH_PAGE_NUMBER extends TCPDF {
    
    private $fontname;
    
    public function __construct($orientation = 'L', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache);
    }
    
    public function setCustomFont($fontname) {
        $this->fontname = $fontname;
    }
    
    // Footer
    public function Footer() {
        // ตำแหน่งที่ 15 มม. จากด้านล่าง
        $this->SetY(-10);
        
        // ใช้ฟอนต์ที่กำหนด หรือ Arial ถ้าไม่มี
        $font = $this->fontname ?? 'Arial';
        $this->SetFont($font, '', 8);
        
        // หมายเลขหน้า
        $page_text = 'ໜ້າ ' . $this->getAliasNumPage() . ' ຈາກ ' . $this->getAliasNbPages();
        $this->Cell(0, 10, $page_text, 0, 0, 'C');
    }
}

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
        p.province_name,
        CASE
            WHEN m.prefix LIKE 'ພຣະ%' THEN 1
            WHEN m.prefix LIKE 'ສ.ນ%' OR m.prefix LIKE 'ສ.ນ%' THEN 2
            WHEN m.prefix LIKE 'ຄຸນແມ່ຂ່າວ%' OR m.prefix LIKE 'ແມ່ຂາວ%' THEN 3
            ELSE 4
        END as prefix_order
    FROM 
        monks m
    JOIN 
        temples t ON m.temple_id = t.id
    LEFT JOIN
        provinces p ON t.province_id = p.province_id
    $sql_where
    ORDER BY 
        prefix_order ASC,
        CAST(COALESCE(m.pansa, 0) AS UNSIGNED) DESC,
        m.name ASC
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
$font_path_bold = dirname(__FILE__) . '/../assets/fonts/PhetsarathOTBold.ttf';  // เปลี่ยนชื่อไฟล์ฟอนต์ตัวหนา

// ตรวจสอบว่ามีไฟล์ฟอนต์อยู่จริง
if (!file_exists($font_path)) {
    die("ບໍ່ພົບໄຟລ໌ແບບອັກສອນທີ່: $font_path");
}

if (!file_exists($font_path_bold)) {
    die("ບໍ່ພົບໄຟລ໌ແບບອັກສອນໂຕໜາທີ່: $font_path_bold");
}

// สร้างอ็อบเจ็กต์ PDF แบบใหม่ที่มีหมายเลขหน้า
$pdf = new PDF_WITH_PAGE_NUMBER('L', 'mm', 'A4', true, 'UTF-8', false);

// เพิ่มฟอนต์ปกติ
$fontname = TCPDF_FONTS::addTTFfont(
    $font_path,
    'TrueTypeUnicode',
    '',
    96
);

// เพิ่มฟอนต์ตัวหนา - ใช้ PhetsarathOTBold โดยตรง
$fontname_bold = TCPDF_FONTS::addTTFfont(
    $font_path_bold,
    'TrueTypeUnicode',
    '',  // ไม่ใช้ 'B' เพราะเป็นฟอนต์ตัวหนาอยู่แล้ว
    96
);

// ตั้งค่าฟอนต์สำหรับหมายเลขหน้าและเปิดใช้งาน footer
$pdf->setCustomFont($fontname);
$pdf->setPrintFooter(true);

// ตั้งค่าข้อมูลเอกสาร
$pdf->SetCreator('Temple Management System');
$pdf->SetAuthor($_SESSION['user']['name'] . ' (' . $_SESSION['user']['role'] . ')');
$pdf->SetTitle($report_title);
$pdf->SetSubject('ຂໍ້ມູນພຣະສົງ');

// ไม่แสดง header แต่แสดง footer สำหรับหมายเลขหน้า
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

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

// ตั้งค่าส่วนหัวเอกสาร - ใช้ฟอนต์ตัวหนา
$pdf->SetFont($fontname_bold, '', 12);  // เปลี่ยนจาก $fontname, 'B' เป็น $fontname_bold, ''
$pdf->Cell(0, 10, 'ສາທາລະນະລັດ ປະຊາທິປະໄຕ ປະຊາຊົນລາວ', 0, 1, 'C');
$pdf->Cell(0, 10, 'ສັນຕິພາບ ເອກະລາດ ປະຊາທິປະໄຕ ເອກະພາບ ວັດທະນະຖາວອນ', 0, 1, 'C');

// ตั้งค่าชื่อรายงาน - ใช้ฟอนต์ตัวหนา
$pdf->SetFont($fontname_bold, '', 12);
$pdf->Cell(0, 10, $report_title, 0, 1, 'C');
$pdf->Ln(5);

// ข้อมูลการกรอง - ใช้ฟอนต์ปกติ
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

// จัดกลุ่มพระสงฆ์ตามคำนำหน้า
$monk_groups = [
    'ພຣະ' => [],
    'ສ.ນ' => [],
    'ຄຸນແມ່ຂາວ' => [], // เปลี่ยนจาก 'ຄຸນແມ່ຂ່າວ' เป็น 'ຄຸນແມ່ຂາວ'
    'ສັງກະລີ' => []
];

foreach ($monks as $monk) {
    $prefix = $monk['prefix'] ?? '';
    
    if (strpos($prefix, 'ພຣະ') !== false) {
        $monk_groups['ພຣະ'][] = $monk;
    } elseif (strpos($prefix, 'ສ.ນ') !== false || strpos($prefix, 'ສ.ນ') !== false) {
        $monk_groups['ສ.ນ'][] = $monk;
    } elseif (strpos($prefix, 'ຄຸນແມ່ຂາວ') !== false || strpos($prefix, 'ແມ່ຂ່າວ') !== false) {
        $monk_groups['ຄຸນແມ່ຂາວ'][] = $monk; // เปลี่ยนจาก 'ຄຸນແມ່ຂ່າວ' เป็น 'ຄຸນແມ່ຂາວ'
    } else {
        $monk_groups['ສັງກະລີ'][] = $monk;
    }
}

// ปรับขนาดคอลัมน์ให้แคบลง
$col_widths = [
    'no' => 8,       // ลำดับ
    'prefix' => 18,  // คำนำหน้า
    'name' => 35,    // ชื่อ
    'surname' => 30, // นามสกุล
    'pansa' => 15,   // พรรษา
    'ordination' => 22, // วันบวช
    'position' => 30, // ตำแหน่ง
    'temple' => 50,  // วัด
    'province' => 25, // แขวง
    'status' => 12   // สถานะ
];

// รวมความกว้างทั้งหมด
$total_width = array_sum($col_widths);

// คำนวณตำแหน่งเริ่มต้นเพื่อให้ตารางอยู่ตรงกลาง
$page_width = $pdf->getPageWidth();
$margins = $pdf->getMargins();
$available_width = $page_width - $margins['left'] - $margins['right'];
$table_start_x = ($available_width - $total_width) / 2 + $margins['left'];

// แสดงข้อมูลแยกตามกลุ่ม
$group_order = ['ພຣະ', 'ສ.ນ', 'ຄຸນແມ່ຂາວ', 'ສັງກະລີ'];
$monk_count = 0;
$total_monks = 0;

foreach ($group_order as $group_name) {
    $group_monks = $monk_groups[$group_name];
    
    if (empty($group_monks)) {
        continue;
    }
    
    $total_monks += count($group_monks);
    
    // เพิ่มหัวข้อกลุ่ม
    $pdf->SetFont($fontname_bold, '', 11);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetX($table_start_x); // จัดให้ตารางอยู่ตรงกลาง
    $pdf->Cell($total_width, 8, $group_name . ' (' . count($group_monks) . ' ລາຍການ)', 1, 1, 'C', true);
    
    // ส่วนหัวตาราง
    $pdf->SetFont($fontname_bold, '', 9);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetX($table_start_x); // จัดให้ตารางอยู่ตรงกลาง
    $pdf->Cell($col_widths['no'], 8, 'ລຳດັບ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['prefix'], 8, 'ຄຳນຳໜ້າ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['name'], 8, 'ຊື່', 1, 0, 'C', true);
    $pdf->Cell($col_widths['surname'], 8, 'ນາມສະກຸນ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['pansa'], 8, 'ພັນສາ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['ordination'], 8, 'ວັນບວດ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['position'], 8, 'ຕໍາແໜ່ງ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['temple'], 8, 'ວັດ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['province'], 8, 'ແຂວງ', 1, 0, 'C', true);
    $pdf->Cell($col_widths['status'], 8, 'ສະຖານະ', 1, 1, 'C', true);
    
    // เนื้อหาตาราง
    $pdf->SetFont($fontname, '', 8);
    
    foreach($group_monks as $monk) {
        $monk_count++;
        
        $pdf->SetX($table_start_x); // จัดให้ตารางอยู่ตรงกลาง
        $pdf->Cell($col_widths['no'], 7, $monk_count, 1, 0, 'C');
        $pdf->Cell($col_widths['prefix'], 7, $monk['prefix'] ?? '-', 1, 0, 'C');
        $pdf->Cell($col_widths['name'], 7, $monk['name'], 1, 0, 'L');
        $pdf->Cell($col_widths['surname'], 7, $monk['lay_name'] ?? '-', 1, 0, 'L');
        $pdf->Cell($col_widths['pansa'], 7, ($monk['pansa'] ?? '0') . ' ພັນສາ', 1, 0, 'C');
        
        $ordination_date = $monk['ordination_date'] ? date('d/m/Y', strtotime($monk['ordination_date'])) : '-';
        $pdf->Cell($col_widths['ordination'], 7, $ordination_date, 1, 0, 'C');
        
        $pdf->Cell($col_widths['position'], 7, $monk['position'] ?? '-', 1, 0, 'L');
        $pdf->Cell($col_widths['temple'], 7, $monk['temple_name'], 1, 0, 'L');
        $pdf->Cell($col_widths['province'], 7, $monk['province_name'] ?? '-', 1, 0, 'L');
        
        $status_text = $monk['status'] == 'active' ? 'ບວດຢູ່' : 'ສຶກແລ້ວ';
        $pdf->Cell($col_widths['status'], 7, $status_text, 1, 1, 'C');
    }
    
    $pdf->Ln(3); // เว้นระยะระหว่างกลุ่ม
}

if ($total_monks == 0) {
    // ไม่พบข้อมูล
    $pdf->SetFont($fontname, '', 10);
    $pdf->SetX($table_start_x); // จัดให้ตารางอยู่ตรงกลาง
    $pdf->Cell($total_width, 10, 'ບໍ່ພົບຂໍ້ມູນພຣະສົງທີ່ຕົງຕາມເງື່ອນໄຂ', 1, 1, 'C');
}
// สรุปข้อมูล
$pdf->Ln(5);
$pdf->SetFont($fontname_bold, '', 12);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
$pdf->Cell(0, 8, 'ສະຫຼຸບ:', 0, 1);
$pdf->SetFont($fontname, '', 10);
$pdf->Cell(0, 6, 'ຈໍານວນພຣະສົງທັງໝົດ: ' . $total_monks . ' ລາຍການ', 0, 1); // แก้จาก ; เป็น )

// สรุปตามประเภท
$pdf->Ln(5);
$pdf->SetFont($fontname_bold, '', 12);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
$pdf->Cell(0, 8, 'ສະຫຼຸບຕາມປະເພດ:', 0, 1);

// หัวตารางสรุป
$pdf->SetFont($fontname_bold, '', 10);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
$pdf->SetFillColor(200, 220, 255);
// กำหนดความกว้างตารางสรุป
$summary_table_width = 90;
$summary_start_x = $margins['left']; // อยู่ด้านซ้าย
$pdf->SetX($summary_start_x);
$pdf->Cell(20, 8, 'ລຳດັບ', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'ປະເພດ', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'ຈຳນວນ', 1, 1, 'C', true);

// เนื้อหาตารางสรุป
$pdf->SetFont($fontname, '', 10);
$i = 1;
$total = 0;
foreach ($group_order as $group_name) {
    $count = count($monk_groups[$group_name]);
    if ($count > 0) {
        $pdf->SetX($summary_start_x);
        $pdf->Cell(20, 8, $i++, 1, 0, 'C');
        $pdf->Cell(40, 8, $group_name, 1, 0, 'C');
        $pdf->Cell(30, 8, $count . ' ລາຍການ', 1, 1, 'C');
        $total += $count;
    }
}

// แสดงยอดรวม
$pdf->SetFont($fontname_bold, '', 10);  // แก้ไขจาก $fontname, 'B' เป็น $fontname_bold, ''
$pdf->SetX($summary_start_x);
$pdf->Cell(60, 8, 'ລວມທັງໝົດ', 1, 0, 'C', true);
$pdf->Cell(30, 8, $total . ' ລາຍການ', 1, 1, 'C', true);

// บันทึกตำแหน่งหลังจากตารางสรุป
$summary_end_y = $pdf->GetY();

// เพิ่มลายเซ็น - วางไว้ในกรอบสีแดง (ด้านขวาของตารางสรุป)
// กำหนดตำแหน่งเริ่มต้นของลายเซ็น
$signature_start_x = 180; // ตำแหน่ง X ที่ต้องการ (ประมาณตำแหน่งกรอบสีแดง)
$signature_width = 70;   // ความกว้างของกรอบลายเซ็น
$signature_start_y = $summary_end_y - 45; // ย้อนกลับขึ้นไปเพื่อให้อยู่ในระดับเดียวกับตารางสรุป



// กลับไปยังสีดำสำหรับข้อความ
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.2);

// วางลายเซ็นภายในกรอบ
$pdf->SetY($signature_start_y + 5); // เว้นระยะจากขอบบนของกรอบ
$pdf->SetX($signature_start_x + 1);  // เว้นระยะจากขอบซ้ายของกรอบ
$pdf->SetFont($fontname_bold, '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($signature_width - 10, 8, 'ຫ້ອງການບໍລິຫານ ອພສ', 0, 1, 'C');

// เส้นสำหรับลายเซ็น
$pdf->Ln(5);
$pdf->SetX($signature_start_x + 5);
$pdf->Cell($signature_width - 10, 0, '', 'B', 1, 'C');

$pdf->Ln(5);

// ช่องใส่ชื่อ
$pdf->SetX($signature_start_x + 5);
$pdf->SetFont($fontname, '', 10);
$pdf->Cell($signature_width - 10, 8, '(.................................................)', 0, 1, 'C');

$pdf->Ln(5);

// วันที่
$pdf->SetX($signature_start_x + 5);
$pdf->Cell($signature_width - 10, 8, 'ວັນທີ່ ......./......./..........', 0, 1, 'C');

// แสดง PDF
$pdf->Output('ລາຍງານຂໍ້ມູນພຣະສົງ.pdf', 'I');

?>