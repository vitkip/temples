<?php
// filepath: c:\xampp\htdocs\temples\monks\excel_import_process.php
require_once '../config/db.php';
require_once '../config/base_url.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// ตรวจสอบการล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'ບໍ່ໄດ້ຮັບອະນຸຍາດ']);
    exit;
}

$user = $_SESSION['user'];
$allowed_roles = ['superadmin', 'admin'];
if (!in_array($user['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'ບໍ່ມີສິດເຂົ້າຖີງ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'import_excel') {
    echo json_encode(['success' => false, 'message' => 'ຄຳຂໍບໍ່ຖືກຕ້ອງ']);
    exit;
}

try {
    // ตรวจสอบไฟล์
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('ບໍ່ພົບໄຟລ໌ທີ່ອັບໂຫຼດ');
    }

    $file = $_FILES['excel_file'];
    $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('ປະເພດໄຟລ໌ບໍ່ຖືກຕ້ອງ ກະລຸນາໃຊ້ໄຟລ໌ Excel (.xlsx, .xls)');
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('ໄຟລ໌ມີຂະໜາດໃຫຍ່ເກີນໄປ (ສູງສຸດ 10MB)');
    }

    // โหลดไฟล์ Excel
    $spreadsheet = IOFactory::load($file['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // ตรวจสอบว่ามีข้อมูลหรือไม่
    if (empty($rows) || count($rows) < 5) { // อย่างน้อย 5 แถว (header + ตัวอย่าง)
        throw new Exception('ໄຟລ໌ Excel ວ່າງເປົ່າຫຼືບໍ່ມີຂໍ້ມູນ');
    }

    // ดึงข้อมูลวัดสำหรับ validation
    if ($user['role'] === 'superadmin') {
        $temples_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active'");
    } else {
        $temples_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
        $temples_stmt->execute([$user['temple_id']]);
    }
    $temples = $temples_stmt->fetchAll(PDO::FETCH_ASSOC);
    $temple_names = array_column($temples, 'name', 'id');
    $temple_ids = array_flip($temple_names);

    // รายการแขวงที่ถูกต้อง
    $valid_provinces = [
        'ອັດຕະປື', 'ບໍ່ແກ້ວ', 'ບໍລິຄໍາໄຊ', 'ຈໍາປາສັກ', 'ຫົວພັນ', 'ຄໍາມ່ວນ', 
        'ຫຼວງນ້ຳທາ', 'ຫຼວງພະບາງ', 'ອຸດົມໄຊ', 'ຜົ້ງສາລີ', 'ໄຊຍະບູລີ', 'ສາລະວັນ', 
        'ສະຫວັນນະເຂດ', 'ເຊກອງ', 'ແຂວງວຽງຈັນ', 'ນະຄອນຫຼວງວຽງຈັນ', 'ໄຊສົມບູນ', 'ຊຽງຂວາງ',
        'ວຽງຈັນ' // เพิ่มแบบย่อ
    ];

    // รายการคำนำหน้าที่ถูกต้อง
    $valid_prefixes = ['ພຣະ', 'ຄຸນແມ່ຂາວ', 'ສ.ນ', 'ສັງກະລີ'];

    // ประมวลผลข้อมูล - เริ่มจากแถวที่ 5 (ข้าม header และตัวอย่าง)
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $imported_data = [];

    for ($i = 4; $i < count($rows); $i++) { // เริ่มจากแถวที่ 5 (index 4)
        $row = $rows[$i];
        $row_num = $i + 1;
        
        // ข้ามแถวว่าง
        $row_data = array_filter($row, function($value) {
            return !empty(trim($value));
        });
        
        if (empty($row_data)) {
            continue;
        }
        
        try {
            // รับข้อมูลตาม schema ใหม่
            $prefix = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');  // ชื่อพระสงฆ์
            $lay_name = trim($row[2] ?? '');  // ชื่อคนธรรมดา
            $pansa = trim($row[3] ?? '0');  // จำนวนพรรษา
            $birth_date = trim($row[4] ?? '');
            $birth_province = trim($row[5] ?? '');
            $ordination_date = trim($row[6] ?? '');
            $temple_name = trim($row[7] ?? '');
            $contact_number = trim($row[8] ?? '');
            $id_card = trim($row[9] ?? '');
            $education = trim($row[10] ?? '');
            $dharma_education = trim($row[11] ?? '');
            $position = trim($row[12] ?? '');
            $status = trim($row[13] ?? 'active');

            // Validation ข้อมูลที่จำเป็น
            if (empty($name)) {
                throw new Exception("ແຖວ {$row_num}: ກະລຸນາປ້ອນຊື່ພຣະສົງ");
            }

            if (empty($temple_name)) {
                throw new Exception("ແຖວ {$row_num}: ກະລຸນາລະບຸວັດ");
            }

            if (!isset($temple_ids[$temple_name])) {
                throw new Exception("ແຖວ {$row_num}: ບໍ່ພົບວັດ '{$temple_name}' ໃນລະບົບ");
            }

            $temple_id = $temple_ids[$temple_name];

            // ตรวจสอบสิทธิ์การเข้าถึงวัด
            if ($user['role'] === 'admin' && $temple_id != $user['temple_id']) {
                throw new Exception("ແຖວ {$row_num}: ທ່ານບໍ່ມີສິດເພີ່ມຂໍ້ມູນໃນວັດ '{$temple_name}'");
            }

            // ตรวจสอบจำนวนพรรษา
            if (!is_numeric($pansa) || $pansa < 0) {
                throw new Exception("ແຖວ {$row_num}: ຈຳນວນພັນສາຕ້ອງເປັນຕົວເລກ 0 ຂຶ້ນໄປ");
            }
            $pansa = (int)$pansa;

            // ตรวจสอบคำนำหน้า
            if (!empty($prefix) && !in_array($prefix, $valid_prefixes)) {
                throw new Exception("ແຖວ {$row_num}: ຄຳນຳໜ້າບໍ່ຖືກຕ້ອງ (ພຣະ, ຄຸນແມ່ຂາວ, ສ.ນ, ສັງກະລີ)");
            }

            // ตรวจสอบแขวงเกิด
            if (!empty($birth_province) && !in_array($birth_province, $valid_provinces)) {
                throw new Exception("ແຖວ {$row_num}: ແຂວງເກີດບໍ່ຖືກຕ້ອງ");
            }

            // ตรวจสอบสถานะ
            if (!in_array($status, ['active', 'inactive'])) {
                throw new Exception("ແຖວ {$row_num}: ສະຖານະຕ້ອງເປັນ active ຫຼື inactive");
            }

            // แปลงวันที่
            $birth_date_formatted = null;
            $ordination_date_formatted = null;

            if (!empty($birth_date)) {
                $birth_date_obj = DateTime::createFromFormat('d/m/Y', $birth_date);
                if (!$birth_date_obj) {
                    $birth_date_obj = DateTime::createFromFormat('Y-m-d', $birth_date);
                }
                if ($birth_date_obj) {
                    $birth_date_formatted = $birth_date_obj->format('Y-m-d');
                } else {
                    throw new Exception("ແຖວ {$row_num}: ຮູບແບບວັນເກີດບໍ່ຖືກຕ້ອງ (dd/mm/yyyy ຫຼື yyyy-mm-dd)");
                }
            }

            if (!empty($ordination_date)) {
                $ordination_date_obj = DateTime::createFromFormat('d/m/Y', $ordination_date);
                if (!$ordination_date_obj) {
                    $ordination_date_obj = DateTime::createFromFormat('Y-m-d', $ordination_date);
                }
                if ($ordination_date_obj) {
                    $ordination_date_formatted = $ordination_date_obj->format('Y-m-d');
                } else {
                    throw new Exception("ແຖວ {$row_num}: ຮູບແບບວັນບວດບໍ່ຖືກຕ້ອງ (dd/mm/yyyy ຫຼື yyyy-mm-dd)");
                }
            }

            // ตรวจสอบข้อมูลซ้ำ
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE name = ? AND temple_id = ?");
            $check_stmt->execute([$name, $temple_id]);
            if ($check_stmt->fetchColumn() > 0) {
                throw new Exception("ແຖວ {$row_num}: ມີພຣະສົງຊື່ '{$name}' ໃນວັດນີ້ແລ້ວ");
            }

            // ตรวจสอบเลขบัตรประชาชนซ้ำ
            if (!empty($id_card)) {
                $check_id_stmt = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE id_card = ? AND id_card != ''");
                $check_id_stmt->execute([$id_card]);
                if ($check_id_stmt->fetchColumn() > 0) {
                    throw new Exception("ແຖວ {$row_num}: ເລກບັດປະຊາຊົນນີ້ມີຢູ່ໃນລະບົບແລ້ວ");
                }
            }

            // เพิ่มข้อมูลลงฐานข้อมูล - ใช้ schema ใหม่
            $sql = "INSERT INTO monks (id_card, prefix, name, lay_name, pansa, birth_date, birth_province, 
                    ordination_date, education, dharma_education, contact_number, temple_id, position, 
                    photo, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_card ?: null,
                $prefix ?: null,
                $name,
                $lay_name ?: null,
                $pansa,
                $birth_date_formatted,
                $birth_province ?: null,
                $ordination_date_formatted,
                $education ?: null,
                $dharma_education ?: null,
                $contact_number ?: null,
                $temple_id,
                $position ?: null,
                'uploads/monks/default.png', // default photo
                $status
            ]);

            $success_count++;
            $imported_data[] = [
                'row' => $row_num,
                'name' => $prefix . $name,
                'temple' => $temple_name,
                'pansa' => $pansa . ' ພັນສາ'
            ];

        } catch (Exception $e) {
            $error_count++;
            $errors[] = $e->getMessage();
        }
    }

    // สร้าง HTML ผลลัพธ์
    $html = '<div class="import-summary">';
    $html .= '<div class="summary-stats">';
    $html .= '<div class="stat-item success">';
    $html .= '<i class="fas fa-check-circle"></i>';
    $html .= '<div><h3>' . $success_count . '</h3><p>ສຳເລັດ</p></div>';
    $html .= '</div>';
    $html .= '<div class="stat-item error">';
    $html .= '<i class="fas fa-times-circle"></i>';
    $html .= '<div><h3>' . $error_count . '</h3><p>ຜິດພາດ</p></div>';
    $html .= '</div>';
    $html .= '<div class="stat-item total">';
    $html .= '<i class="fas fa-list"></i>';
    $html .= '<div><h3>' . ($success_count + $error_count) . '</h3><p>ທັງໝົດ</p></div>';
    $html .= '</div>';
    $html .= '</div>';

    if ($success_count > 0) {
        $html .= '<div class="success-list">';
        $html .= '<h4><i class="fas fa-check text-green-600"></i> ຂໍ້ມູນທີ່ນຳເຂົ້າສຳເລັດ</h4>';
        $html .= '<div class="imported-items">';
        foreach (array_slice($imported_data, 0, 10) as $item) {
            $html .= '<div class="imported-item">';
            $html .= '<span class="item-name">' . htmlspecialchars($item['name']) . '</span>';
            $html .= '<span class="item-temple">' . htmlspecialchars($item['temple']) . '</span>';
            $html .= '<span class="item-pansa">' . htmlspecialchars($item['pansa']) . '</span>';
            $html .= '</div>';
        }
        if (count($imported_data) > 10) {
            $html .= '<p class="text-gray-500">ແລະອີກ ' . (count($imported_data) - 10) . ' ລາຍການ...</p>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    if ($error_count > 0) {
        $html .= '<div class="error-list">';
        $html .= '<h4><i class="fas fa-exclamation-triangle text-red-600"></i> ຂໍ້ຜິດພາດ</h4>';
        $html .= '<div class="error-items">';
        foreach (array_slice($errors, 0, 15) as $error) {
            $html .= '<div class="error-item">' . htmlspecialchars($error) . '</div>';
        }
        if (count($errors) > 15) {
            $html .= '<p class="text-gray-500">ແລະອີກ ' . (count($errors) - 15) . ' ຂໍ້ຜິດພາດ...</p>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '<div class="import-actions">';
    if ($success_count > 0) {
        $html .= '<a href="' . $base_url . 'monks/" class="btn btn-primary">';
        $html .= '<i class="fas fa-list"></i> ໄປຫາລາຍການພະສົງ';
        $html .= '</a>';
    }
    $html .= '<button type="button" class="btn btn-secondary" onclick="location.reload()">';
    $html .= '<i class="fas fa-redo"></i> ນຳເຂົ້າໃໝ່';
    $html .= '</button>';
    $html .= '</div>';
    $html .= '</div>';

    // เพิ่ม CSS สำหรับ import results
    $html .= '<style>
    .import-summary { margin-top: 2rem; }
    .summary-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 0.5rem; }
    .stat-item.success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
    .stat-item.error { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
    .stat-item.total { background: #F0F9FF; border: 1px solid #BAE6FD; color: #0C4A6E; }
    .stat-item i { font-size: 2rem; }
    .stat-item h3 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .stat-item p { margin: 0; font-size: 0.875rem; }
    .success-list, .error-list { margin-bottom: 2rem; padding: 1rem; border-radius: 0.5rem; }
    .success-list { background: #ECFDF5; border: 1px solid #A7F3D0; }
    .error-list { background: #FEF2F2; border: 1px solid #FECACA; }
    .success-list h4, .error-list h4 { margin: 0 0 1rem 0; font-weight: 600; }
    .imported-items, .error-items { display: flex; flex-direction: column; gap: 0.5rem; }
    .imported-item { display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem; padding: 0.5rem; background: white; border-radius: 0.25rem; }
    .error-item { padding: 0.5rem; background: white; border-radius: 0.25rem; font-size: 0.875rem; }
    .import-actions { display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; }
    @media (max-width: 768px) {
        .summary-stats { grid-template-columns: 1fr; }
        .imported-item { grid-template-columns: 1fr; }
        .import-actions { flex-direction: column; }
    }
    </style>';

    echo json_encode([
        'success' => true,
        'message' => "ນຳເຂົ້າຂໍ້ມູນເຮັດເສັດແລ້ວ: ສຳເລັດ {$success_count} ລາຍການ, ຜິດພາດ {$error_count} ລາຍການ",
        'html' => $html,
        'stats' => [
            'success' => $success_count,
            'error' => $error_count,
            'total' => $success_count + $error_count
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>