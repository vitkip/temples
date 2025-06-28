<?php
/**
 * Temple Import from Excel
 * ระบบนำเข้าข้อมูลวัดจาก Excel
 */
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ตรวจสอบการร้องขอดาวน์โหลดเทมเพลต - ต้องทำก่อน include header.php
if (isset($_GET['download_template'])) {
    // สร้างไฟล์เทมเพลต Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // กำหนดหัวข้อ
    $headers = [
        'ຊື່ວັດ', 'ແຂວງ', 'ເມືອງ', 'ທີ່ຢູ່', 'ເຈົ້າອາວາດ', 'ເບີໂທ', 
        'ອີເມວ', 'ເວັບໄຊທ໌', 'ວັນທີສ້າງຕັ້ງ', 'ຄຳອະທິບາຍ', 
        'Latitude', 'Longitude', 'ສະຖານະ'
    ];
    
    // ใช้ setCellValue แทน setCellValueByColumnAndRow
    $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
    foreach ($headers as $index => $header) {
        $sheet->setCellValue($columns[$index] . '1', $header);
    }
    
    // ตัวอย่างข้อมูล
    $example_data = [
        [
            'ວັດຕົວຢ່າງ 1', 'ແຂວງຫລວງພະບາງ', 'ເມືອງຫຼວງພະບາງ', 'ບ້ານວັດໄຊ, ເມືອງຫຼວງພະບາງ, ແຂວງຫລວງພະບາງ', 
            'ພະອາຈານ ທອງສະຫວ່າງ', '020 12345678', 'wat@example.com', 'http://www.example.com', 
            '2000-01-01', 'ວັດເກົ່າແກ່ ສ້າງໃນປີ 2000', '19.8847', '102.1359', 'active'
        ],
        [
            'ວັດຕົວຢ່າງ 2', 'ແຂວງວຽງຈັນ', 'ເມືອງວຽງຄຳ', 'ບ້ານດົງຄຳ, ເມືອງວຽງຄຳ', 
            'ພະອາຈານ ສີບຸນເຮືອງ', '020 87654321', '', '', 
            '1998-05-10', 'ວັດກາງບ້ານ', '', '', 'inactive'
        ]
    ];
    
    // เพิ่มตัวอย่างข้อมูล
    foreach ($example_data as $rowIndex => $dataRow) {
        $row = $rowIndex + 2; // เริ่มจากแถวที่ 2
        foreach ($dataRow as $colIndex => $value) {
            $sheet->setCellValue($columns[$colIndex] . $row, $value);
        }
    }
    
    // จัดรูปแบบเซลล์
    $styleArray = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4F6228'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ];
    
    $sheet->getStyle('A1:M1')->applyFromArray($styleArray);
    
    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach (range('A', 'M') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // สร้างโฟลเดอร์ถ้ายังไม่มี
    if (!is_dir('../uploads/templates/')) {
        mkdir('../uploads/templates/', 0777, true);
    }
    
    // สร้างและส่งไฟล์
    $writer = new Xlsx($spreadsheet);
    $file_path = '../uploads/templates/temple_import_template.xlsx';
    $writer->save($file_path);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="temple_import_template.xlsx"');
    header('Cache-Control: max-age=0');
    
    readfile($file_path);
    exit; // ต้องมี exit เพื่อหยุดการทำงานหลังจากส่งไฟล์
}

// ตั้งค่า page title ก่อนรวมส่วนหัว
$page_title = 'ນຳເຂົ້າຂໍ້ມູນວັດຈາກ Excel';

// รวมไฟล์ส่วนหัวและเริ่มส่วนที่แสดงผล HTML
require_once '../includes/header.php';

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header('Location: ' . $base_url . 'login.php');
    exit;
}

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];

// อนุญาตเฉพาะ superadmin และ province_admin
if (!in_array($user_role, ['superadmin', 'province_admin'])) {
    $_SESSION['error'] = "ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້";
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ดึงแขวงที่ผู้ใช้สามารถเพิ่มวัดได้
$available_provinces = [];
if ($user_role === 'superadmin') {
    // superadmin เห็นทุกแขวง
    $province_stmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
    $available_provinces = $province_stmt->fetchAll();
} elseif ($user_role === 'province_admin') {
    // province_admin เห็นเฉพาะแขวงที่ตัวเองดูแล
    $province_stmt = $pdo->prepare("
        SELECT p.province_id, p.province_name 
        FROM provinces p 
        JOIN user_province_access upa ON p.province_id = upa.province_id 
        WHERE upa.user_id = ? 
        ORDER BY p.province_name
    ");
    $province_stmt->execute([$user_id]);
    $available_provinces = $province_stmt->fetchAll();
}

// กำหนด arrays เพื่อเก็บผลลัพธ์
$success_records = [];
$error_records = [];
$province_map = [];
$district_map = [];

// สร้าง map ของแขวงและเมืองเพื่อใช้ในการนำเข้าข้อมูล
foreach ($available_provinces as $province) {
    $province_map[strtolower($province['province_name'])] = $province['province_id'];
    
    // ดึงเมืองในแขวงนี้
    $district_stmt = $pdo->prepare("
        SELECT district_id, district_name 
        FROM districts 
        WHERE province_id = ? 
        ORDER BY district_name
    ");
    $district_stmt->execute([$province['province_id']]);
    $districts = $district_stmt->fetchAll();
    
    foreach ($districts as $district) {
        $district_map[$province['province_id']][strtolower($district['district_name'])] = $district['district_id'];
    }
}

// ดำเนินการเมื่อมีการอัพโหลดไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['excel_file']['name'])) {
    try {
        // ตรวจสอบไฟล์
        $allowed_extensions = ['xlsx', 'xls', 'csv'];
        $file_extension = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('ອັບໂຫລດໄດ້ສະເພາະໄຟລ໌ Excel ເທົ່ານັ້ນ (xlsx, xls, csv)');
        }
        
        if ($_FILES['excel_file']['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception('ຂະຫນາດໄຟລ໌ເກີນ 5MB');
        }
        
        // อ่านไฟล์ Excel
        $inputFileType = IOFactory::identify($_FILES['excel_file']['tmp_name']);
        $reader = IOFactory::createReader($inputFileType);
        $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // ตรวจสอบความถูกต้องของรูปแบบไฟล์
        $required_headers = ['ຊື່ວັດ', 'ແຂວງ'];
        $header_row = $worksheet->getRowIterator(1)->current();
        $cellIterator = $header_row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        $headers = [];
        foreach ($cellIterator as $cell) {
            $headers[] = $cell->getValue();
        }
        
        // ตรวจสอบว่ามีคอลัมน์ที่จำเป็นครบหรือไม่
        foreach ($required_headers as $required) {
            if (!in_array($required, $headers)) {
                throw new Exception('ຮູບແບບໄຟລ໌ບໍ່ຖືກຕ້ອງ. ຄໍລໍາ "' . $required . '" ຫາຍໄປ');
            }
        }
        
        // แปลงข้อมูลจาก Spreadsheet เป็น Array
        $data = [];
        $rows = $worksheet->getRowIterator(2); // เริ่มจากแถวที่ 2 (ข้ามส่วนหัว)
        
        $header_indexes = [];
        foreach ($headers as $index => $header) {
            $header_indexes[$header] = $index;
        }
        
        foreach ($rows as $row) {
            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }
            
            // ตรวจสอบว่าแถวว่างหรือไม่
            $is_empty = true;
            foreach ($cells as $cell_value) {
                if (!empty($cell_value)) {
                    $is_empty = false;
                    break;
                }
            }
            
            // ข้ามแถวว่าง
            if ($is_empty) {
                continue;
            }
            
            // แปลงข้อมูลเป็นรูปแบบที่ใช้ได้
            foreach ($headers as $index => $header) {
                $value = isset($cells[$index]) ? $cells[$index] : null;
                $rowData[$header] = $value;
            }
            
            $data[] = $rowData;
        }
        
        // เริ่มนำเข้าข้อมูล
        $pdo->beginTransaction();
        
        foreach ($data as $index => $temple) {
            try {
                // ตรวจสอบข้อมูลที่จำเป็น
                $name = trim($temple['ຊື່ວັດ'] ?? '');
                $province_name = trim($temple['ແຂວງ'] ?? '');
                $district_name = trim($temple['ເມືອງ'] ?? '');
                $address = trim($temple['ທີ່ຢູ່'] ?? '');
                $abbot_name = trim($temple['ເຈົ້າອາວາດ'] ?? '');
                $phone = trim($temple['ເບີໂທ'] ?? '');
                $email = trim($temple['ອີເມວ'] ?? '');
                $website = trim($temple['ເວັບໄຊທ໌'] ?? '');
                $founding_date = trim($temple['ວັນທີສ້າງຕັ້ງ'] ?? '');
                $description = trim($temple['ຄຳອະທິບາຍ'] ?? '');
                $latitude = isset($temple['Latitude']) ? floatval($temple['Latitude']) : null;
                $longitude = isset($temple['Longitude']) ? floatval($temple['Longitude']) : null;
                $status = strtolower(trim($temple['ສະຖານະ'] ?? '')) === 'inactive' ? 'inactive' : 'active';
                
                // ตรวจสอบข้อมูลที่จำเป็น
                if (empty($name)) {
                    throw new Exception('ຊື່ວັດຫວ່າງເປົ່າ');
                }
                
                if (empty($province_name)) {
                    throw new Exception('ແຂວງຫວ່າງເປົ່າ');
                }
                
                // ค้นหา province_id จากชื่อแขวง
                $province_id = null;
                $province_name_lower = strtolower($province_name);
                
                if (isset($province_map[$province_name_lower])) {
                    $province_id = $province_map[$province_name_lower];
                } else {
                    throw new Exception('ບໍ່ພົບແຂວງ "' . $province_name . '" ໃນລະບົບ ຫຼື ທ່ານບໍ່ມີສິດເຂົ້າເຖິງແຂວງນີ້');
                }
                
                // ตรวจสอบสิทธิ์การเพิ่มวัดในแขวง
                if ($user_role === 'province_admin') {
                    $check_access = $pdo->prepare("SELECT COUNT(*) FROM user_province_access WHERE user_id = ? AND province_id = ?");
                    $check_access->execute([$user_id, $province_id]);
                    
                    if ($check_access->fetchColumn() == 0) {
                        throw new Exception('ທ່ານບໍ່ມີສິດເພີ່ມວັດໃນແຂວງ "' . $province_name . '"');
                    }
                }
                
                // ค้นหา district_id จากชื่อเมือง (ถ้ามี)
                $district_id = null;
                if (!empty($district_name) && isset($district_map[$province_id])) {
                    $district_name_lower = strtolower($district_name);
                    
                    if (isset($district_map[$province_id][$district_name_lower])) {
                        $district_id = $district_map[$province_id][$district_name_lower];
                    } else {
                        // ถ้าไม่มีเมืองนี้ แต่ผู้ใช้มีสิทธิ์ในแขวง ให้สร้างเมืองใหม่
                        $insert_district = $pdo->prepare("
                            INSERT INTO districts (district_name, province_id, created_at, updated_at)
                            VALUES (?, ?, NOW(), NOW())
                        ");
                        $insert_district->execute([$district_name, $province_id]);
                        $district_id = $pdo->lastInsertId();
                        
                        // อัพเดต district_map
                        $district_map[$province_id][$district_name_lower] = $district_id;
                    }
                }
                
                // ตรวจสอบวันที่
                if (!empty($founding_date)) {
                    // ถ้าเป็น Excel นับวัน (มักเป็นตัวเลข)
                    if (is_numeric($founding_date)) {
                        $excel_date = (int)$founding_date;
                        $unix_date = ($excel_date - 25569) * 86400; // แปลงวันที่ Excel เป็น Unix timestamp
                        $founding_date = gmdate('Y-m-d', $unix_date);
                    } 
                    // ถ้าเป็นรูปแบบ d/m/Y หรือ d-m-Y
                    elseif (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $founding_date)) {
                        $date_parts = preg_split('/[\/\-]/', $founding_date);
                        $founding_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
                    }
                    // ถ้าไม่ใช่รูปแบบ Y-m-d
                    elseif (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $founding_date)) {
                        $founding_date = null;
                    }
                }
                
                // เพิ่มข้อมูลวัด
                $stmt = $pdo->prepare("
                    INSERT INTO temples (
                        name, address, district_id, province_id, phone, email, 
                        website, founding_date, abbot_name, description, 
                        latitude, longitude, status, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        ?, ?, ?, ?, 
                        ?, ?, ?, NOW(), NOW()
                    )
                ");
                
                $stmt->execute([
                    $name, $address, $district_id, $province_id, $phone, $email, 
                    $website, $founding_date, $abbot_name, $description, 
                    $latitude, $longitude, $status
                ]);
                
                // เพิ่มในรายการสำเร็จ
                $success_records[] = [
                    'row' => $index + 2, // แถวที่เท่าไรในไฟล์ Excel (บวก 2 เพราะเริ่มจาก 0 และมีส่วนหัว)
                    'name' => $name,
                    'province' => $province_name,
                    'district' => $district_name
                ];
                
            } catch (Exception $e) {
                // เก็บข้อมูลรายการที่มีปัญหา
                $error_records[] = [
                    'row' => $index + 2,
                    'name' => $temple['ຊື່ວັດ'] ?? 'N/A',
                    'province' => $temple['ແຂວງ'] ?? 'N/A',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // ถ้ามีข้อมูลที่สำเร็จ ยืนยันการนำเข้า
        if (count($success_records) > 0) {
            $pdo->commit();
            $import_success = true;
        } else {
            // ถ้าไม่มีข้อมูลที่สำเร็จเลย ยกเลิกการนำเข้าทั้งหมด
            $pdo->rollBack();
            $import_success = false;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $import_success = false;
        
        // กรณีมีการเริ่ม transaction แล้วเกิด error ให้ rollback
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
?>

<!-- เพิ่ม CSS ของหน้า -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/monk-style.css">

<!-- Page Container -->
<div class="page-container">
    <div class="max-w-4xl mx-auto p-4">
        <!-- Page Header -->
        <div class="header-section flex justify-between items-center mb-8 p-6 rounded-lg">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <div class="category-icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    ນຳເຂົ້າຂໍ້ມູນວັດຈາກ Excel
                </h1>
                <p class="text-sm text-amber-700 mt-1">ອັບໂຫລດໄຟລ໌ Excel ເພື່ອນຳເຂົ້າຂໍ້ມູນວັດຫຼາຍລາຍການໃນຄັ້ງດຽວ</p>
            </div>
            <div class="flex space-x-2">
                <a href="<?= $base_url ?>temples/import.php?download_template=1" class="btn flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition">
                    <i class="fas fa-download"></i> ດາວໂຫລດແບບຟອມ
                </a>
                <a href="<?= $base_url ?>temples/" class="btn flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                    <i class="fas fa-arrow-left"></i> ກັບຄືນ
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-red-700"><?= $error_message ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!isset($import_success)): ?>
        <!-- คำแนะนำ -->
        <div class="card bg-white p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">ຄຳແນະນຳໃນການນຳເຂົ້າຂໍ້ມູນ</h2>
            
            <ul class="list-disc pl-5 space-y-2 text-gray-700">
                <li>ດາວໂຫລດແບບຟອມ Excel ເພື່ອເບິ່ງຕົວຢ່າງໂຄງສ້າງຂໍ້ມູນທີ່ຖືກຕ້ອງ</li>
                <li>ຂໍ້ມູນທີ່ຈຳເປັນຕ້ອງມີ: <strong>ຊື່ວັດ</strong> ແລະ <strong>ແຂວງ</strong></li>
                <li>ຮັບຮອງວ່າຊື່ແຂວງຖືກຕ້ອງຕາມທີ່ມີໃນລະບົບ</li>
                <li>ຮູບແບບວັນທີຄວນເປັນ <code>YYYY-MM-DD</code> ເຊັ່ນ <code>2000-01-31</code></li>
                <li>ສະຖານະມີ 2 ຄ່າ: <code>active</code> ຫຼື <code>inactive</code> (ຄ່າເລີ່ມຕົ້ນແມ່ນ active)</li>
                <li>ສາມາດນຳເຂົ້າໄດ້ສູງສຸດ 100 ລາຍການໃນຄັ້ງດຽວ</li>
            </ul>
        </div>

        <!-- อัพโหลดฟอร์ม -->
        <div class="card bg-white p-6">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">ເລືອກໄຟລ໌ Excel (.xlsx, .xls, .csv)</label>
                    <input 
                        type="file" 
                        name="excel_file" 
                        required
                        accept=".xlsx,.xls,.csv"
                        class="w-full border border-gray-300 rounded-lg p-2"
                    >
                    <p class="mt-2 text-sm text-gray-500">ຂະໜາດໄຟລ໌ສູງສຸດ: 5MB</p>
                </div>

                <?php if (count($available_provinces) > 0): ?>
                <div class="mb-6">
                    <p class="text-sm font-medium text-gray-700 mb-2">ທ່ານສາມາດນຳເຂົ້າວັດໃນແຂວງເຫຼົ່ານີ້:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($available_provinces as $province): ?>
                        <span class="px-2 py-1 bg-gray-100 rounded-full text-sm text-gray-700">
                            <?= htmlspecialchars($province['province_name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-6 py-2 text-white rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-upload"></i> ນຳເຂົ້າຂໍ້ມູນ
                    </button>
                </div>
            </form>
        </div>
        <?php else: ?>
        <!-- ผลการนำเข้าข้อมูล -->
        <div class="card bg-white p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-clipboard-check text-green-600 mr-2"></i>
                ຜົນການນຳເຂົ້າຂໍ້ມູນ
            </h2>
            
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700">ນຳເຂົ້າຂໍ້ມູນສຳເລັດ <?= count($success_records) ?> ລາຍການ</p>
                </div>
                <?php if (count($error_records) > 0): ?>
                <p class="ml-8 mt-1 text-sm text-red-600">ພົບຂໍ້ຜິດພາດ <?= count($error_records) ?> ລາຍການ</p>
                <?php endif; ?>
            </div>
            
            <?php if (count($success_records) > 0): ?>
            <div class="mb-8">
                <h3 class="text-md font-semibold mb-3 text-green-800">ລາຍການທີ່ສຳເລັດ</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border-b text-left">ແຖວ</th>
                                <th class="py-2 px-4 border-b text-left">ຊື່ວັດ</th>
                                <th class="py-2 px-4 border-b text-left">ແຂວງ</th>
                                <th class="py-2 px-4 border-b text-left">ເມືອງ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($success_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border-b"><?= $record['row'] ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($record['name']) ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($record['province']) ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($record['district'] ?: '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (count($error_records) > 0): ?>
            <div>
                <h3 class="text-md font-semibold mb-3 text-red-800">ລາຍການທີ່ມີຂໍ້ຜິດພາດ</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 border-b text-left">ແຖວ</th>
                                <th class="py-2 px-4 border-b text-left">ຊື່ວັດ</th>
                                <th class="py-2 px-4 border-b text-left">ແຂວງ</th>
                                <th class="py-2 px-4 border-b text-left">ຂໍ້ຜິດພາດ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($error_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 border-b"><?= $record['row'] ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($record['name']) ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($record['province']) ?></td>
                                <td class="py-2 px-4 border-b text-red-600"><?= htmlspecialchars($record['error']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-6 flex justify-between">
                <a href="<?= $base_url ?>temples/import.php" class="btn px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition">
                    <i class="fas fa-redo mr-1"></i> ນຳເຂົ້າໄຟລ໌ອື່ນ
                </a>
                <a href="<?= $base_url ?>temples/" class="btn-primary px-4 py-2 text-white rounded-lg transition">
                    <i class="fas fa-list mr-1"></i> ເບິ່ງລາຍການວັດ
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>