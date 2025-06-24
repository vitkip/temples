<?php
// filepath: c:\xampp\htdocs\temples\monks\download_template.php
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ตรวจสอบการล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header('Location: ' . $base_url . 'auth/login.php');
    exit;
}

$user = $_SESSION['user'];
$allowed_roles = ['superadmin', 'admin'];
if (!in_array($user['role'], $allowed_roles)) {
    die('ທ່ານບໍ່ມີສິດໃນການເຂົ້າເຖິງໜ້ານີ້');
}

try {
    // สร้าง Spreadsheet ใหม่
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('ແມ່ແບບຂໍ້ມູນພະສົງ');

    // กำหนดสไตล์
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
            'startColor' => ['rgb' => '2980B9']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF']
            ]
        ]
    ];

    $instructionStyle = [
        'font' => [
            'size' => 11,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '2C3E50']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_TOP,
            'wrapText' => true
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFF3CD']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'F39C12']
            ]
        ]
    ];

    $exampleStyle = [
        'font' => [
            'size' => 11,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '495057']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DEE2E6']
            ]
        ]
    ];

    // กำหนดความกว้างคอลัมน์ - ปรับให้ตรงกับ schema ใหม่
    $columnWidths = [
        'A' => 12,  // ຄຳນຳໜ້າ (prefix)
        'B' => 25,  // ຊື່ພຣະສົງ (name) *
        'C' => 20,  // ຊື່ຄົນທົ່ວໄປ (lay_name)
        'D' => 8,   // ຈຳນວນພັນສາ (pansa) *
        'E' => 12,  // ວັນເກີດ (birth_date)
        'F' => 15,  // ແຂວງເກີດ (birth_province)
        'G' => 12,  // ວັນບວດ (ordination_date)
        'H' => 25,  // ວັດ (temple) *
        'I' => 15,  // ເບີໂທ (contact_number)
        'J' => 17,  // ບັດປະຊາຊົນ (id_card)
        'K' => 20,  // ການສຶກສາທົ່ວໄປ (education)
        'L' => 20,  // ການສຶກສາທາງທຳມະ (dharma_education)
        'M' => 20,  // ຕຳແໜ່ງໃນວັດ (position)
        'N' => 12,  // ສະຖານະ (status)
    ];

    foreach ($columnWidths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    // สร้างหัวข้อรายงาน
    $sheet->mergeCells('A1:N1');
    $sheet->setCellValue('A1', 'ແມ່ແບບນຳເຂົ້າຂໍ້ມູນພະສົງ');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '2C3E50']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8F4FD']
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // วันที่สร้าง
    $sheet->mergeCells('A2:N2');
    $sheet->setCellValue('A2', 'ວັນທີ່ສ້າງ: ' . date('d/m/Y H:i:s'));
    $sheet->getStyle('A2')->applyFromArray([
        'font' => [
            'size' => 10,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '6C757D']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ]);

    // คำแนะนำการใช้งาน - อัปเดตให้ตรงกับ schema ใหม่
    $instructions = [
        "📋 ຄຳແນະນຳການນຳເຂົ້າຂໍ້ມູນ:",
        "1. ກະລຸນາຕື່ມຂໍ້ມູນໃສ່ແຖວທີ່ 5 ລົງໄປ",
        "2. ຫ້າມລຶບແຖວຫົວຕາຕະລາງ (ແຖວທີ່ 4)",
        "3. ຂໍ້ມູນຈຳເປັນ: ຊື່ພຣະສົງ (*), ຈຳນວນພັນສາ (*), ວັດ (*)",
        "4. ຮູບແບບວັນທີ່: dd/mm/yyyy ຫຼື yyyy-mm-dd",
        "5. ສະຖານະ: active (ຍັງບວດຢູ່) ຫຼື inactive (ສິກແລ້ວ)",
        "6. ຊື່ວັດຕ້ອງກົງກັບຊື່ໃນລະບົບ",
        "7. ຈຳນວນພັນສາຕ້ອງເປັນຕົວເລກ (0 ຂຶ້ນໄປ)",
        "8. ແຂວງເກີດຕ້ອງເປັນແຂວງໃນລາວ"
    ];

    $sheet->mergeCells('A3:N3');
    $sheet->setCellValue('A3', implode("\n", $instructions));
    $sheet->getStyle('A3')->applyFromArray($instructionStyle);
    $sheet->getRowDimension(3)->setRowHeight(140);

    // หัวตาราง - ปรับให้ตรงกับ schema ใหม่
    $headers = [
        'A4' => 'ຄຳນຳໜ້າ',
        'B4' => 'ຊື່ພຣະສົງ *',
        'C4' => 'ຊື່ຄົນທົ່ວໄປ', 
        'D4' => 'ຈຳນວນພັນສາ *',
        'E4' => 'ວັນເກີດ',
        'F4' => 'ແຂວງເກີດ',
        'G4' => 'ວັນບວດ',
        'H4' => 'ວັດ *',
        'I4' => 'ເບີໂທຕິດຕໍ່',
        'J4' => 'ບັດປະຊາຊົນ',
        'K4' => 'ການສຶກສາທົ່ວໄປ',
        'L4' => 'ການສຶກສາທາງທຳມະ',
        'M4' => 'ຕຳແໜ່ງໃນວັດ',
        'N4' => 'ສະຖານະ'
    ];

    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    $sheet->getStyle('A4:N4')->applyFromArray($headerStyle);
    $sheet->getRowDimension(4)->setRowHeight(20);

    // ข้อมูลตัวอย่าง - ปรับให้ตรงกับ schema ใหม่
    $examples = [
        ['ພຣະ', 'ສົມພອນ ສິລາຈານໂຕ', 'ໄຊຍະວົງ ພົນສະຫວັນ', '15', '15/01/1990', 'ວຽງຈັນ', '10/07/2010', '', '020 5555 1234', '1234567890123', 'ປະລິນຍາຕີ', 'ນັກທຳ 6', 'ຮອງເຈົ້າອາວາດ', 'active'],
        ['ສ.ນ', 'ນົງລັກ', 'ຈັນທະວົງ ຈັນທະລາ', '20', '20/03/1985', 'ຫຼວງພະບາງ', '05/05/2005', '', '020 9999 5678', '9876543210987', 'ມັດທະຍົມ', 'ນັກທຳ 3', 'ພະສົງທົ່ວໄປ', 'active'],
        ['ພຣະ', 'ວິຊາຍ ຄຸນາວຸທໂທ', 'ສີລະວົງ ພົມມະລາດ', '8', '08/12/1992', 'ຈໍາປາສັກ', '15/04/2015', '', '020 7777 8888', '1357924680246', 'ອານຸປະລິນຍາ', 'ປະລິນຍາທຳ', 'ຄູສອນ', 'inactive']
    ];

    // ดึงข้อมูลวัดที่ผู้ใช้สามารถเข้าถึงได้
    try {
        if ($user['role'] === 'superadmin') {
            $temples_stmt = $pdo->query("SELECT name FROM temples WHERE status = 'active' ORDER BY name LIMIT 5");
        } else {
            $temples_stmt = $pdo->prepare("SELECT name FROM temples WHERE id = ? AND status = 'active'");
            $temples_stmt->execute([$user['temple_id']]);
        }
        $temples = $temples_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // ใส่ชื่อวัดในตัวอย่าง
        if (!empty($temples)) {
            $examples[0][7] = $temples[0] ?? '';
            $examples[1][7] = !empty($temples[1]) ? $temples[1] : $temples[0];
            $examples[2][7] = !empty($temples[2]) ? $temples[2] : $temples[0];
        }
    } catch (Exception $e) {
        error_log("Error fetching temples: " . $e->getMessage());
    }

    // เพิ่มข้อมูลตัวอย่าง
    $row = 5;
    foreach ($examples as $example) {
        $col = 'A';
        foreach ($example as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray($exampleStyle);
        $row++;
    }

    // เพิ่มแถวว่างสำหรับกรอกข้อมูล
    for ($i = $row; $i <= $row + 15; $i++) {
        $sheet->getStyle('A' . $i . ':N' . $i)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E9ECEF']
                ]
            ]
        ]);
    }

    // หมายเหตุท้ายตาราง - อัปเดตข้อมูล
    $notes_row = $row + 17;
    $sheet->mergeCells('A' . $notes_row . ':N' . ($notes_row + 8));
    
    $notes = [
        "📝 ຫມາຍເຫດເພີ່ມເຕີມ:",
        "",
        "• ຄຳນຳໜ້າ: ພຣະ, ຄຸນແມ່ຂາວ, ສ.ນ, ສັງກະລີ",
        "• ການສຶກສາທົ່ວໄປ: ປະຖົມ, ມັດທະຍົມ, ອານຸປະລິນຍາ, ປະລິນຍາຕີ, ປະລິນຍາໂທ, ປະລິນຍາເອກ",
        "• ການສຶກສາທາງທຳມະ: ນັກທຳ 1-9, ປະລິນຍາທຳ, ປະລິນຍາໂທທຳ, ປະລິນຍາເອກທຳ",
        "• ສະຖານະ: active (ຍັງບວດຢູ່), inactive (ສິກແລ້ວ)",
        "• ແຂວງເກີດ: ວຽງຈັນ, ຫຼວງພະບາງ, ສະຫວັນນະເຂດ, ຈໍາປາສັກ, ອຸດົມໄຊ, ບໍ່ແກ້ວ, ສາລະວັນ, ເຊກອງ, ອັດຕະປື, ຜົ້ງສາລີ, ຫົວພັນ, ຄໍາມ່ວນ, ບໍລິຄໍາໄຊ, ຫຼວງນ້ຳທາ, ໄຊຍະບູລີ, ໄຊສົມບູນ, ຊຽງຂວາງ",
        "• ສາມາດປ່ອຍໃຫ້ຊ່ອງວ່າງໄດ້ຍົກເວັ້ນຂໍ້ມູນທີ່ມີເຄື່ອງໝາຍ *",
        "",
        "ຫາກມີຂໍ້ສົງໄສ ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບ"
    ];

    $sheet->setCellValue('A' . $notes_row, implode("\n", $notes));
    $sheet->getStyle('A' . $notes_row)->applyFromArray([
        'font' => [
            'size' => 10,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '495057']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_TOP,
            'wrapText' => true
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'DEE2E6']
            ]
        ]
    ]);
    $sheet->getRowDimension($notes_row)->setRowHeight(150);

    // ส่วนท้าย
    $footer_row = $notes_row + 10;
    $sheet->mergeCells('A' . $footer_row . ':N' . $footer_row);
    $sheet->setCellValue('A' . $footer_row, 'ລະບົບຄຸ້ມຄອງວັດວາອາຣາມ | ສ້າງໂດຍ: ' . htmlspecialchars($user['name']) . ' | ' . date('d/m/Y H:i:s'));
    $sheet->getStyle('A' . $footer_row)->applyFromArray([
        'font' => [
            'size' => 9,
            'name' => 'Phetsarath OT',
            'color' => ['rgb' => '6C757D'],
            'italic' => true
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ]);

    // แช่แข็งแถวหัวตาราง
    $sheet->freezePane('A5');

    // ป้องกันการแก้ไขหัวตาราง
    $sheet->getProtection()->setSheet(true);
    $sheet->getStyle('A4:N4')->getProtection()->setLocked(true);
    $sheet->getStyle('A5:N1000')->getProtection()->setLocked(false);

    // กำหนดชื่อไฟล์
    $filename = 'monks_import_template_' . date('Ymd_His') . '.xlsx';

    // ส่งออกไฟล์
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

} catch (Exception $e) {
    error_log("Template download error: " . $e->getMessage());
    
    // กลับไปหน้าเพิ่มข้อมูลพร้อมข้อความผิดพลาด
    header('Location: ' . $base_url . 'monks/add.php?error=' . urlencode('ເກີດຂໍ້ຜິດພາດໃນການດາວໂຫຼດແມ່ແບບ'));
    exit;
}

exit;
?>