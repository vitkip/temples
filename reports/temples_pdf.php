<?php
/**
 * Temple Report PDF Generator
 * ระบบสร้างรายงานข้อมูลวัดในรูปแบบ PDF
 * 
 * @author Temple Management System
 * @version 2.0
 */

require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../vendor/autoload.php';

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
        throw new Exception("ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ: " . $e->getMessage());
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
$filename = 'temples_report_' . date('Ymd_His') . '.pdf';

// โหลด TCPDF
require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

/**
 * คลาส MYPDF สำหรับจัดการ PDF
 */
class MYPDF extends TCPDF {
    protected $col_widths;
    protected $left_margin;
    protected $filter_text;
    protected $total_records;
    protected $report_title;

    /**
     * ตั้งค่าข้อมูลตาราง
     */
    public function setTableData($col_widths, $left_margin, $filter_text = '', $total_records = 0) {
        $this->col_widths = $col_widths;
        $this->left_margin = $left_margin;
        $this->filter_text = $filter_text;
        $this->total_records = $total_records;
        $this->report_title = 'ລາຍງານຂໍ້ມູນວັດ';
    }
    
    /**
     * ส่วนหัวของเอกสาร
     */
    public function Header() {
        // ชื่อหลักของรายงาน - แสดงเฉพาะหน้าแรก
        if ($this->getPage() == 1) {
            $this->SetFont('phetsarathb', 'B', 16);
            $this->SetTextColor(0, 51, 102); // สีน้ำเงินเข้ม
            $this->Cell(0, 10, $this->report_title, 0, 1, 'C');
        }
        
     
        
        // แสดงตัวกรองและจำนวนรายการ
        if ($this->getPage() == 1 && !empty($this->filter_text)) {
            $this->SetFont('phetsarathot', '', 11);
            $this->SetTextColor(51, 51, 51);
            $this->Cell(0, 6, $this->filter_text, 0, 1, 'L');
        }
        // วันที่และเวลา - แสดงเฉพาะหน้าแรก
        if ($this->getPage() == 1) {
            $this->SetFont('phetsarathot', '', 11);
            $this->SetTextColor(102, 102, 102); // สีเทา
            $this->Cell(0, 8, 'ວັນທີ່ອອກລາຍງານ: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
        }
        
       
    }

    /**
     * วาดหัวตาราง
     */
    public function drawTableHeader() {
        $this->SetX($this->left_margin);
        $this->SetFont('phetsarathb', 'B', 10);
        $this->SetFillColor(41, 128, 185); // สีน้ำเงิน
        $this->SetTextColor(255, 255, 255); // ข้อความสีขาว
        $this->SetDrawColor(255, 255, 255); // เส้นขอบสีขาว
        
        // ลบ 'ສະຖານະ' ออกจากอาร์เรย์
        $headers = ['ລ/ດ', 'ຊື່ວັດ', 'ແຂວງ', 'ເມືອງ', 'ເຈົ້າອາວາດ', 'ໂທລະສັບ'];
        
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($this->col_widths[$i], 10, $headers[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // รีเซ็ตสี
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
    }

    /**
     * ส่วนท้ายของเอกสาร
     */
    public function Footer() {
        $this->SetY(-20);
        
        // เส้นแบ่ง
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        
        $this->Ln(2);
        
        // ข้อมูลท้ายหน้า
        $this->SetFont('phetsarathot', '', 8);
        $this->SetTextColor(102, 102, 102);
        
        // ซ้าย: ระบบและผู้ใช้
        $this->Cell(0, 5, 'ລະບົບຄຸ້ມຄອງວັດ | ຜູ້ໃຊ້: ' . ($_SESSION['user']['username'] ?? 'ລະບົບ'), 0, 0, 'L');
        
        // ขวา: หมายเลขหน้า
        $this->Cell(0, 5, 'ໜ້າ ' . $this->getAliasNumPage() . ' ຈາກ ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    /**
     * วาดแถวข้อมูล
     */
    public function drawDataRow($data, $row_num) {
        $this->SetX($this->left_margin);
        $this->SetFont('phetsarathot', '', 9);
        
        // สลับสีพื้นหลัง
        if ($row_num % 2 == 0) {
            $this->SetFillColor(248, 249, 250); // สีเทาอ่อน
            $fill = true;
        } else {
            $fill = false;
        }
        
        // กำหนดความสูงแถว
        $height = 8;
        
        // วาดเซลล์ข้อมูล (ลบส่วนสถานะออก)
        $this->Cell($this->col_widths[0], $height, $row_num, 1, 0, 'C', $fill);
        $this->Cell($this->col_widths[1], $height, $this->truncateText($data['name'], 35), 1, 0, 'L', $fill);
        $this->Cell($this->col_widths[2], $height, $data['province_name'] ?? '-', 1, 0, 'C', $fill);
        $this->Cell($this->col_widths[3], $height, $data['district'] ?? '-', 1, 0, 'C', $fill);
        $this->Cell($this->col_widths[4], $height, $this->truncateText($data['abbot_name'] ?? '-', 25), 1, 0, 'L', $fill);
        $this->Cell($this->col_widths[5], $height, $data['phone'] ?? '-', 1, 1, 'C', $fill);
        
        // ลบส่วนที่แสดง status และกำหนดสีข้อความ
    }

    /**
     * ตัดข้อความให้สั้นลง
     */
    private function truncateText($text, $maxLength) {
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            return mb_substr($text, 0, $maxLength - 3, 'UTF-8') . '...';
        }
        return $text;
    }

    /**
     * เพิ่มสรุปท้ายรายงาน
     */
    public function addSummary($temples) {
        // Add more space before summary
        $this->Ln(15);
        
        // Add title with nice formatting
        $this->SetY($this->GetY() - 10);
        $this->SetFont('phetsarathb', 'B', 16);
        $this->SetTextColor(41, 80, 132);
        $this->Cell(0, 8, 'ສະຫຼຸບລາຍງານ', 0, 1, 'C');
        
        // Add summary content with nice formatting
        $this->SetFont('phetsarathot', '', 14);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 8, 'ລວມທັງໝົດ: ' . number_format(count($temples)) . ' ວັດ', 0, 1, 'C');
        
        // Add a decorative line
        $this->SetDrawColor(200, 200, 200);
        $this->Line(90, $this->GetY()+3, $this->getPageWidth()-90, $this->GetY()+3);
    }

    /**
     * เพิ่มลายเซ็น
     */
    public function addSignature() {
        // สร้างหน้าใหม่สำหรับลายเซ็น
        $this->AddPage();
        
        // เพิ่มระยะห่างด้านบน
        $this->Ln(20);
        
        // ตำแหน่งลายเซ็น (กำหนดให้อยู่ตรงกลางหน้า)
        $signature_x = ($this->getPageWidth() - 120) / 2;
        
        $this->SetX($signature_x);
        $this->SetFont('phetsarathb', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(120, 8, 'ຫ້ອງການບໍລິຫານ', 0, 1, 'C');
        
        $this->Ln(30); // เพิ่มพื้นที่สำหรับลายเซ็น
        
        // เส้นสำหรับลายเซ็น
        $this->SetX($signature_x);
        $this->Cell(120, 0, '', 'B', 1, 'C');
        
        $this->Ln(5);
        
        // ช่องใส่ชื่อ
        $this->SetX($signature_x);
        $this->SetFont('phetsarathot', '', 12);
        $this->Cell(120, 8, '(.................................................)', 0, 1, 'C');
        
        $this->Ln(5);
        
        $this->SetX($signature_x);
        $this->Cell(120, 8, 'ວັນທີ່ ......./......./..........', 0, 1, 'C');
    }

    /**
     * วาดส่วนหัวของเมือง
     */
    public function drawDistrictHeader($district_name) {
        $this->Ln(5); // เพิ่มระยะห่าง
        
        // วาดแถบสีสำหรับหัวเมือง
        $this->SetFillColor(230, 140, 30); // สีเหลืองส้มเข้ม
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('phetsarathb', 'B', 11);
        
        // กำหนดตำแหน่ง X ให้เริ่มตรงกับตาราง
        $this->SetX($this->left_margin);
        
        // ใช้ความกว้างเท่ากับความกว้างตารางรวม
        $this->Cell(array_sum($this->col_widths), 8, 'ເມືອງ: '.$district_name, 0, 1, 'L', true);
        
        // รีเซ็ตสีกลับเป็นค่าเริ่มต้น
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    /**
     * เพิ่มสรุปท้ายรายงานแบบแยกตามเมือง
     */
    public function addDetailedSummary($temples, $province_filter = false) {
        // สร้างหน้าใหม่สำหรับสรุปรายงาน
        $this->AddPage(); // ยังคงสร้างหน้าใหม่สำหรับสรุปรายงาน
        
        // กรณีมีการกรองตามแขวง
        if ($province_filter) {
            // เพิ่มพื้นที่ว่างด้านบนเล็กน้อย
            $this->Ln(1);
            
            $this->SetFont('phetsarathb', 'B', 16);
            $this->SetTextColor(41, 80, 132);
            $this->Cell(0, 8, 'ສະຫຼຸບລາຍງານຕາມເມືອງ', 0, 1, 'C');
            
            // จัดกลุ่มข้อมูลตามเมือง
            $districts = [];
            foreach ($temples as $temple) {
                $district = $temple['district'] ?: 'ບໍ່ລະບຸ';
                if (!isset($districts[$district])) {
                    $districts[$district] = 0;
                }
                $districts[$district]++;
            }
            
            // แสดงจำนวนวัดแยกตามเมือง
            $this->Ln(1);
            $this->SetFont('phetsarathot', '', 12);
            $this->SetTextColor(50, 50, 50);
            
            // วาดตารางสรุป
            $this->SetFillColor(240, 240, 240);
            $this->Cell(100, 8, 'ເມືອງ', 1, 0, 'C', true);
            $this->Cell(60, 8, 'ຈຳນວນວັດ', 1, 1, 'C', true);
            
            foreach ($districts as $district => $count) {
                $this->Cell(100, 7, $district, 1, 0, 'L');
                $this->Cell(60, 7, number_format($count) . ' ວັດ', 1, 1, 'C');
            }
            
            // รวมทั้งหมด
            $this->SetFont('phetsarathb', '', 12);
            $this->Cell(100, 8, 'ລວມທັງໝົດ', 1, 0, 'R', true);
            $this->Cell(60, 8, number_format(count($temples)) . ' ວັດ', 1, 1, 'C', true);
            
            // เพิ่มลายเซ็นในหน้าเดียวกัน (ใส่ที่นี่แทนการเรียก addSignature แยก)
            $this->addSignatureSection();
        } else {
            // กรณีไม่ได้กรองตามแขวง ใช้สรุปแบบเดิม
            $this->addSummary($temples);
            
            // เพิ่มลายเซ็นในหน้าเดียวกัน
            $this->addSignatureSection();
        }
    }

    /**
     * เพิ่มส่วนลายเซ็น (ไม่สร้างหน้าใหม่)
     */
    public function addSignatureSection() {
        // เพิ่มระยะห่าง
        $this->Ln(25);
        
        // ตำแหน่งลายเซ็น (กำหนดให้อยู่ตรงกลางหน้า)
        $signature_x = ($this->getPageWidth() - 120) / 2;
        
        $this->SetX($signature_x);
        $this->SetFont('phetsarathb', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(120, 8, 'ຫ້ອງການບໍລິຫານ', 0, 1, 'C');
        
        $this->Ln(25); // เพิ่มพื้นที่สำหรับลายเซ็น
        
        // เส้นสำหรับลายเซ็น
        $this->SetX($signature_x);
        $this->Cell(120, 0, '', 'B', 1, 'C');
        
        $this->Ln(5);
        
        // ช่องใส่ชื่อ
        $this->SetX($signature_x);
        $this->SetFont('phetsarathot', '', 12);
        $this->Cell(120, 8, '(.................................................)', 0, 1, 'C');
        
        $this->Ln(5);
        
        $this->SetX($signature_x);
        $this->Cell(120, 8, 'ວັນທີ່ ......./......./..........', 0, 1, 'C');
    }
}

// แก้ไขส่วนกำหนดความกว้างคอลัมน์โดยลบคอลัมน์สุดท้าย (สถานะ)
$col_widths = [15, 60, 40, 40, 55, 30]; // ลบ 25 (สถานะ) และปรับความกว้างคอลัมน์อื่น
$table_width = array_sum($col_widths);

// สร้าง PDF object
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8');

// ตั้งค่าเอกสาร
$pdf->SetCreator('Temple Management System v2.0');
$pdf->SetAuthor($_SESSION['user']['username'] ?? 'System Admin');
$pdf->SetTitle('ລາຍງານຂໍ້ມູນວັດ - ' . date('d/m/Y'));
$pdf->SetSubject('Temple Data Export Report');
$pdf->SetKeywords('Temple, Report, PDF, Management');

// ตั้งค่าขอบกระดาษ
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(1);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);

// คำนวณตำแหน่งตารางให้อยู่ตรงกลาง
$page_width = $pdf->getPageWidth();
$left_margin = ($page_width - $table_width) / 2;

// สร้างข้อความตัวกรอง
$filter_parts = [];
if (!empty($search)) {
    $filter_parts[] = 'ຄໍາຄົ້ນຫາ: "' . $search . '"';
}
if (!empty($province)) {
    $filter_parts[] = 'ແຂວງ: ' . $province;
}
if (!empty($district)) {
    // ดึงชื่อเมืองจาก database (ทำก่อนการสร้าง conditions)
    try {
        $district_stmt = $pdo->prepare("SELECT district_name FROM districts WHERE district_id = ?");
        $district_stmt->execute([$district]);
        $district_data = $district_stmt->fetch(PDO::FETCH_ASSOC);
        if ($district_data) {
            $filter_parts[] = 'ເມືອງ: ' . $district_data['district_name'];
        }
    } catch (PDOException $e) {
        // ถ้าไม่สามารถดึงข้อมูลได้ ใช้แค่ ID
        $filter_parts[] = 'ເມືອງ: ' . $district;
    }
}
if (!empty($status)) {
    $status_text = ($status === 'active') ? 'ເປີດໃຊ້ງານ' : 'ປິດໃຊ້ງານ';
    $filter_parts[] = 'ສະຖານະ: ' . $status_text;
}

$filter_text = !empty($filter_parts) ? 'ຕົວກອງ: ' . implode(' | ', $filter_parts) : 'ຕົວກອງ: ທັງໝົດ';

// ส่งข้อมูลไปยัง PDF class
$pdf->setTableData($col_widths, $left_margin, $filter_text, count($temples));

// เริ่มสร้างเอกสาร
$pdf->AddPage();

// เพิ่มระยะห่างก่อนเริ่มตารางทั้งหมด (ปรับค่าตามต้องการ)
$pdf->Ln(8); // ลดจาก 25 เป็น 10 มิลลิเมตร เพื่อขยับหัวตารางขึ้น

// วาดหัวตาราง (เฉพาะหน้าแรก)
// if ($pdf->getPage() == 1) {
//     $pdf->drawTableHeader();
// }

// วาดข้อมูลตาราง
$current_district = '';
$row_counter = 1;

foreach ($temples as $index => $temple) {
    // ตรวจสอบว่ามีการเปลี่ยนเมืองหรือไม่ และมีการเลือกแขวงหรือไม่
    if (!empty($province) && $current_district != $temple['district']) {
        $current_district = $temple['district'];
        
        // วาดหัวตารางใหม่ทุกครั้งที่เปลี่ยนเมือง
        if ($index > 0) {
            $pdf->Ln(3);
        }
        
        // วาดหัวเมือง
        $pdf->drawDistrictHeader($current_district);
        
        // วาดหัวตาราง
        $pdf->drawTableHeader();
        
        // รีเซ็ตเลขลำดับแถวให้เริ่มที่ 1 สำหรับแต่ละเมือง
        $row_counter = 1;
    } elseif ($index === 0) {
        // กรณีแถวแรก ให้วาดหัวตารางเลย (ไม่ว่าจะมีการเลือกแขวงหรือไม่)
        $pdf->drawTableHeader();
        $current_district = $temple['district'];
    }
    
    // วาดแถวข้อมูล
    $pdf->drawDataRow($temple, $row_counter);
    $row_counter++;
}

// ไม่ต้องใส่สรุปรายงานธรรมดาถ้าเราแยกหน้าละเอียดอยู่แล้ว
// $pdf->addSummary($temples);

// เพิ่มสรุปรายงานแบบละเอียด (จะแสดงในหน้าใหม่พร้อมลายเซ็น)
$pdf->addDetailedSummary($temples, !empty($province));

// ไม่ต้องเรียก addSignature อีก เพราะรวมอยู่ใน addDetailedSummary แล้ว
// $pdf->addSignature();

// ส่งออกไฟล์ PDF
try {
    $pdf->Output($filename, 'D');
} catch (Exception $e) {
    error_log("PDF Output Error: " . $e->getMessage());
    die("ເກີດຂໍ້ຜິດພາດໃນການສ້າງຟາຍ PDF");
}

exit;
?>