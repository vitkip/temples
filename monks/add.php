<?php
require_once '../config/db.php';
require_once '../config/base_url.php';

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
    header('Location: ' . $base_url . 'dashboard.php');
    exit;
}

// ข้อความแจ้งเตือน
$success_message = '';
$error_message = '';

// ดึงข้อมูลวัดสำหรับ dropdown
try {
    if ($user['role'] === 'superadmin') {
        $temples_stmt = $pdo->query("SELECT id, name FROM temples WHERE status = 'active' ORDER BY name");
    } else {
        $temples_stmt = $pdo->prepare("SELECT id, name FROM temples WHERE id = ? AND status = 'active'");
        $temples_stmt->execute([$user['temple_id']]);
    }
    $temples = $temples_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'เกิดข้อผิดพลาดในการดึงข้อมูลวัด';
}

// ประมวลผลฟอร์มเพิ่มข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_monk') {
    try {
        // รับและตรวจสอบข้อมูล - แก้ไขให้ตรงกับ schema
        $data = [
            'id_card' => trim($_POST['id_card'] ?? ''),
            'prefix' => trim($_POST['prefix'] ?? ''),
            'name' => trim($_POST['name'] ?? ''), // ชื่อพระสงฆ์
            'lay_name' => trim($_POST['lay_name'] ?? ''), // ชื่อคนธรรมดาก่อนบวด
            'pansa' => (int)($_POST['pansa'] ?? 0), // จำนวนพรรษา
            'birth_date' => $_POST['birth_date'] ?? '',
            'birth_province' => trim($_POST['birth_province'] ?? ''),
            'ordination_date' => $_POST['ordination_date'] ?? '',
            'education' => trim($_POST['education'] ?? ''),
            'dharma_education' => trim($_POST['dharma_education'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'temple_id' => (int)($_POST['temple_id'] ?? 0),
            'position' => trim($_POST['position'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];

        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($data['name'])) {
            throw new Exception('ກະລຸນາປ້ອນຊື່ພຣະສົງ');
        }

        if ($data['pansa'] < 0) {
            throw new Exception('ກະລຸນາປ້ອນຈຳນວນພັນສາທີ່ຖືກຕ້ອງ');
        }

        if ($data['temple_id'] <= 0) {
            throw new Exception('ກະລຸນາເລືອກວັດ');
        }

        // ตรวจสอบสิทธิ์การเข้าถึงวัด
        if ($user['role'] === 'admin' && $data['temple_id'] != $user['temple_id']) {
            throw new Exception('ທ່ານບໍ່ມີສິດໃນການເພີ່ມຂໍ້ມູນໃນວັດນີ້');
        }

        // ตรวจสอบเลขบัตรประชาชนซ้ำ (ถ้ามีการกรอก)
        if (!empty($data['id_card'])) {
            $check_id_stmt = $pdo->prepare("SELECT COUNT(*) FROM monks WHERE id_card = ? AND id_card != ''");
            $check_id_stmt->execute([$data['id_card']]);
            if ($check_id_stmt->fetchColumn() > 0) {
                throw new Exception('ເລກບັດປະຊາຊົນນີ້ມີຢູໃນລະບົບແລ້ວ');
            }
        }

        // จัดการอัปโหลดรูปภาพ
        $photo_path = 'uploads/monks/default.png'; // ค่าเริ่มต้น
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/monks/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('ໄຟລ໌ຮູບພາບຕ້ອງເປັນ JPG, PNG, GIF ຫຼື WebP ເທົ່ານັ້ນ');
            }

            $upload_max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['photo']['size'] > $upload_max_size) {
                throw new Exception('ຂະໜາດໄຟລ໌ຮູບພາບຕ້ອງບໍ່ເກີນ 5MB');
            }

            $photo_name = 'monk_' . time() . '_' . uniqid() . '.' . $file_extension;
            $photo_path_full = $upload_dir . $photo_name;

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path_full)) {
                throw new Exception('ບໍ່ສາມາດອັບໂຫຼດຮູບພາບໄດ້');
            }
            
            $photo_path = 'uploads/monks/' . $photo_name; // เก็บ relative path
        }

        // เพิ่มข้อมูลลงฐานข้อมูล - ปรับ SQL ให้ตรงกับ schema ใหม่
        $sql = "INSERT INTO monks (id_card, prefix, name, lay_name, pansa, birth_date, birth_province, 
                ordination_date, education, dharma_education, contact_number, temple_id, position, 
                photo, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['id_card'] ?: null,
            $data['prefix'] ?: null,
            $data['name'],
            $data['lay_name'] ?: null,
            $data['pansa'],
            $data['birth_date'] ?: null,
            $data['birth_province'] ?: null,
            $data['ordination_date'] ?: null,
            $data['education'] ?: null,
            $data['dharma_education'] ?: null,
            $data['contact_number'] ?: null,
            $data['temple_id'],
            $data['position'] ?: null,
            $photo_path,
            $data['status']
        ]);

        $success_message = 'ເພີ່ມຂໍ້ມູນພຣະສົງສຳເລັດແລ້ວ';
        
        // รีเซ็ตฟอร์ม
        $_POST = [];

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // ลบไฟล์ที่อัปโหลดแล้วถ้าเกิดข้อผิดพลาด
        if (isset($photo_path_full) && file_exists($photo_path_full)) {
            unlink($photo_path_full);
        }
    }
}

// Excel Import Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ບໍ່ພົບໄຟລ໌ Excel ທີ່ອັບໂຫຼດ');
        }
        
        require_once '../vendor/autoload.php';
        
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['excel_file']['tmp_name']);
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
        $temple_ids = array_flip($temple_names); // สร้าง map จากชื่อวัดเป็น ID

        $success_count = 0;
        $error_count = 0;
        $errors = [];
        $imported_data = [];
        
        // ประมวลผลข้อมูล - เริ่มจากแถวที่ 5 (ข้าม header และตัวอย่าง)
        for ($i = 4; $i < count($rows); $i++) { // เริ่มจากแถวที่ 5 (index 4)
            $row = $rows[$i];
            $row_num = $i + 1;
            
            // ข้ามแถวว่าง
            if (empty(array_filter($row, function($value) {
                return !empty(trim($value . ''));
            }))) {
                continue;
            }
            
            try {
                // รับข้อมูลจาก column ต่างๆ
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

                // ตรวจสอบข้อมูลสำคัญ
                if (empty($name)) {
                    throw new Exception("ແຖວ {$row_num}: ກະລຸນາປ້ອນຊື່ພຣະສົງ");
                }

                if (empty($temple_name)) {
                    throw new Exception("ແຖວ {$row_num}: ກະລຸນາລະບຸວັດ");
                }

                // ตรวจสอบว่ามีวัดในระบบหรือไม่
                if (!isset($temple_ids[$temple_name])) {
                    throw new Exception("ແຖວ {$row_num}: ບໍ່ພົບວັດ '{$temple_name}' ໃນລະບົບ");
                }

                $temple_id = $temple_ids[$temple_name]; // แปลงชื่อวัดเป็น ID

                // เพิ่มการประมวลผลอื่นๆ...
                // ตรวจสอบจำนวนพรรษา
                if (!is_numeric($pansa)) {
                    throw new Exception("ແຖວ {$row_num}: ຈຳນວນພັນສາຕ້ອງເປັນຕົວເລກ");
                }
                
                $pansa = (int)$pansa;
                if ($pansa < 0) {
                    throw new Exception("ແຖວ {$row_num}: ຈຳນວນພັນສາຕ້ອງບໍ່ຕ່ຳກວ່າ 0");
                }

                // แปลงวันที่
                $birth_date_formatted = null;
                $ordination_date_formatted = null;

                if (!empty($birth_date)) {
                    $date_formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
                    foreach ($date_formats as $format) {
                        $date_obj = \DateTime::createFromFormat($format, $birth_date);
                        if ($date_obj !== false) {
                            $birth_date_formatted = $date_obj->format('Y-m-d');
                            break;
                        }
                    }
                    if ($birth_date_formatted === null) {
                        throw new Exception("ແຖວ {$row_num}: ຮູບແບບວັນເກີດບໍ່ຖືກຕ້ອງ (dd/mm/yyyy ຫຼື yyyy-mm-dd)");
                    }
                }

                if (!empty($ordination_date)) {
                    $date_formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];
                    foreach ($date_formats as $format) {
                        $date_obj = \DateTime::createFromFormat($format, $ordination_date);
                        if ($date_obj !== false) {
                            $ordination_date_formatted = $date_obj->format('Y-m-d');
                            break;
                        }
                    }
                    if ($ordination_date_formatted === null) {
                        throw new Exception("ແຖວ {$row_num}: ຮູບແບບວັນບວດບໍ່ຖືກຕ້ອງ (dd/mm/yyyy ຫຼື yyyy-mm-dd)");
                    }
                }

                // เพิ่มข้อมูลลงฐานข้อมูล
                $sql = "INSERT INTO monks (prefix, name, lay_name, pansa, birth_date, birth_province, 
                        ordination_date, temple_id, contact_number, id_card, education, dharma_education,
                        position, photo, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $prefix ?: null,
                    $name,
                    $lay_name ?: null,
                    $pansa,
                    $birth_date_formatted,
                    $birth_province ?: null,
                    $ordination_date_formatted,
                    $temple_id,
                    $contact_number ?: null,
                    $id_card ?: null,
                    $education ?: null,
                    $dharma_education ?: null,
                    $position ?: null,
                    'uploads/monks/default.png',
                    $status
                ]);

                $success_count++;
                $imported_data[] = [
                    'name' => ($prefix ? $prefix . ' ' : '') . $name,
                    'temple' => $temple_name,
                    'pansa' => $pansa . ' ພັນສາ'
                ];
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = $e->getMessage();
            }
        }
        
        // สร้าง HTML สำหรับแสดงผล
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
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($error_count > 0) {
            $html .= '<div class="error-list">';
            $html .= '<h4><i class="fas fa-exclamation-triangle text-red-600"></i> ຂໍ້ຜິດພາດ</h4>';
            $html .= '<div class="error-items">';
            foreach ($errors as $error) {
                $html .= '<div class="error-item">' . htmlspecialchars($error) . '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        echo json_encode([
            'success' => true,
            'message' => "ນຳເຂົ້າຂໍ້ມູນສຳເລັດ {$success_count} ລາຍການ, ຜິດພາດ {$error_count} ລາຍການ",
            'html' => $html
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'html' => '<div class="error">ເກີດຂໍ້ຜິດພາດ: ' . htmlspecialchars($e->getMessage()) . '</div>'
        ]);
    }
    exit;
}

$page_title = 'ເພີ່ມຂໍ້ມູນພຣະສົງ';
require_once '../includes/header.php';
?>

<div class="page-container">
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-6xl mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between mb-6">
                <div>
                    <h1 class="monk-title">ເພີ່ມຂໍ້ມູນພຣະສົງ</h1>
                    <p class="text-gray-600">ເພີ່ມຂໍ້ມູນພຣະສົງໃໝ່ລົງໃນລະບົບ</p>
                </div>
                <div class="flex gap-3 mt-4 lg:mt-0">
                    <a href="<?= $base_url ?>monks/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> ກັບໄປລາຍການ
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tab-container">
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="manual">
                        <i class="fas fa-user-plus"></i> ເພີ່ມແບບແມນນວນ
                    </button>
                    <button class="tab-btn" data-tab="excel">
                        <i class="fas fa-file-excel"></i> ນຳເຂົ້າຈາກ Excel
                    </button>
                </div>

                <!-- Manual Add Tab -->
                <div class="tab-content active" id="manual-tab">
                    <div class="info-card">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="card-header-content">
                                <h2 class="card-title">ເພີ່ມຂໍ້ມູນພຣະສົງ</h2>
                                <p class="card-subtitle">ກະລຸນາປ້ອນຂໍ້ມູນພຣະສົງໃໝ່</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" enctype="multipart/form-data" id="monkForm">
                                <input type="hidden" name="action" value="add_monk">
                                
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                    <!-- ข้อมูลพื้นฐาน -->
                                    <div class="lg:col-span-2">
                                        <h3 class="section-title">
                                            <i class="fas fa-user"></i>
                                            ຂໍ້ມູນພື້ນຖານ
                                        </h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="form-group">
                                                <label for="prefix" class="form-label">
                                                    <i class="fas fa-tag"></i>
                                                    ຄຳນຳໜ້າ
                                                </label>
                                                <select name="prefix" id="prefix" class="form-control">
                                                    <option value="">-- ເລືອກຄຳນຳໜ້າ --</option>
                                                    <option value="ພຣະ" <?= isset($_POST['prefix']) && $_POST['prefix'] === 'ພຣະ' ? 'selected' : '' ?>>ພຣະ</option>
                                                    <option value="ຄຸນແມ່ຂາວ" <?= isset($_POST['prefix']) && $_POST['prefix'] === 'ຄຸນແມ່ຂາວ' ? 'selected' : '' ?>>ຄຸນແມ່ຂາວ</option>
                                                    <option value="ສ.ນ" <?= isset($_POST['prefix']) && $_POST['prefix'] === 'ສ.ນ' ? 'selected' : '' ?>>ສ.ນ</option>
                                                    <option value="ສັງກະລี" <?= isset($_POST['prefix']) && $_POST['prefix'] === 'ສັງກະລี' ? 'selected' : '' ?>>ສັງກະລี</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="name" class="form-label required">
                                                    <i class="fas fa-signature"></i>
                                                    ຊື່ພຣະສົງ
                                                </label>
                                                <input type="text" name="name" id="name" 
                                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                                                       class="form-control" required 
                                                       placeholder="ຊື່ພຣະສົງໃນລະບົບ"
                                                       autocomplete="name">
                                            </div>

                                            <div class="form-group">
                                                <label for="lay_name" class="form-label">
                                                    <i class="fas fa-user-circle"></i>
                                                    ຊື່ຄົນທົ່ວໄປ
                                                </label>
                                                <input type="text" name="lay_name" id="lay_name" 
                                                       value="<?= htmlspecialchars($_POST['lay_name'] ?? '') ?>" 
                                                       class="form-control" 
                                                       placeholder="ຊື່ກ່ອນບວດ"
                                                       autocomplete="given-name">
                                            </div>

                                            <div class="form-group">
                                                <label for="pansa" class="form-label required">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    ຈຳນວນພັນສາ
                                                </label>
                                                <input type="number" name="pansa" id="pansa" 
                                                       value="<?= htmlspecialchars($_POST['pansa'] ?? '0') ?>" 
                                                       class="form-control" required min="0" max="100"
                                                       placeholder="ຈຳນວນພັນສາ">
                                                <small class="form-text text-muted">ລະບົບຈະຄິດໄລ່ອັດຕະໂນມັດຈາກວັນບວດ</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="birth_date" class="form-label">
                                                    <i class="fas fa-birthday-cake"></i>
                                                    ວັນເກີດ
                                                </label>
                                                <input type="date" name="birth_date" id="birth_date" 
                                                       value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" 
                                                       class="form-control"
                                                       max="<?= date('Y-m-d') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="birth_province" class="form-label">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    ແຂວງເກີດ
                                                </label>
                                                <select name="birth_province" id="birth_province" class="form-control">
                                                    <option value="">-- ເລືອກແຂວງ --</option>
                                                    <option value="ວຽງຈັນ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ວຽງຈັນ' ? 'selected' : '' ?>>ວຽງຈັນ</option>
                                                    <option value="ຫຼວງພະບາງ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຫຼວງພະບາງ' ? 'selected' : '' ?>>ຫຼວງພະບາງ</option>
                                                    <option value="ສະຫວັນນະເຂດ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ສະຫວັນນະເຂດ' ? 'selected' : '' ?>>ສະຫວັນນະເຂດ</option>
                                                    <option value="ຈໍາປາສັກ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຈໍາປາສັກ' ? 'selected' : '' ?>>ຈໍາປາສັກ</option>
                                                    <option value="ອຸດົມໄຊ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ອຸດົມໄຊ' ? 'selected' : '' ?>>ອຸດົມໄຊ</option>
                                                    <option value="ບໍ່ແກ້ວ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ບໍ່ແກ້ວ' ? 'selected' : '' ?>>ບໍ່ແກ້ວ</option>
                                                    <option value="ສາລະວັນ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ສາລະວັນ' ? 'selected' : '' ?>>ສາລະວັນ</option>
                                                    <option value="ເຊກອງ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ເຊກອງ' ? 'selected' : '' ?>>ເຊກອງ</option>
                                                    <option value="ອັດຕະປື" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ອັດຕະປື' ? 'selected' : '' ?>>ອັດຕະປື</option>
                                                    <option value="ຜົ້ງສາລີ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຜົ້ງສາລີ' ? 'selected' : '' ?>>ຜົ້ງສາລີ</option>
                                                    <option value="ຫົວພັນ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຫົວພັນ' ? 'selected' : '' ?>>ຫົວພັນ</option>
                                                    <option value="ຄໍາມ່ວນ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຄໍາມ່ວນ' ? 'selected' : '' ?>>ຄໍາມ່ວນ</option>
                                                    <option value="ບໍລິຄໍາໄຊ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ບໍລິຄໍາໄຊ' ? 'selected' : '' ?>>ບໍລິຄໍາໄຊ</option>
                                                    <option value="ຫຼວງນ້ຳທາ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຫຼວງນ້ຳທາ' ? 'selected' : '' ?>>ຫຼວງນ້ຳທາ</option>
                                                    <option value="ໄຊຍະບູລີ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ໄຊຍະບູລີ' ? 'selected' : '' ?>>ໄຊຍະບູລີ</option>
                                                    <option value="ໄຊສົມບູນ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ໄຊສົມບູນ' ? 'selected' : '' ?>>ໄຊສົມບູນ</option>
                                                    <option value="ຊຽງຂວາງ" <?= isset($_POST['birth_province']) && $_POST['birth_province'] === 'ຊຽງຂວາງ' ? 'selected' : '' ?>>ຊຽງຂວາງ</option>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="ordination_date" class="form-label">
                                                    <i class="fas fa-pray"></i>
                                                    ວັນບວດ
                                                </label>
                                                <input type="date" name="ordination_date" id="ordination_date" 
                                                       value="<?= htmlspecialchars($_POST['ordination_date'] ?? '') ?>" 
                                                       class="form-control"
                                                       max="<?= date('Y-m-d') ?>">
                                            </div>

                                            <div class="form-group">
                                                <label for="temple_id" class="form-label required">
                                                    <i class="fas fa-place-of-worship"></i>
                                                    ວັດ
                                                </label>
                                                <select name="temple_id" id="temple_id" class="form-control" required>
                                                    <option value="">-- ເລືອກວັດ --</option>
                                                    <?php foreach ($temples as $temple): ?>
                                                    <option value="<?= $temple['id'] ?>" 
                                                            <?= (isset($_POST['temple_id']) && $_POST['temple_id'] == $temple['id']) || 
                                                                ($user['role'] === 'admin' && $temple['id'] == $user['temple_id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($temple['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-group">
                                                <label for="position" class="form-label">
                                                    <i class="fas fa-user-tie"></i>
                                                    ຕຳແໜ່ງໃນວັດ
                                                </label>
                                                <input type="text" name="position" id="position" 
                                                       value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" 
                                                       class="form-control" 
                                                       placeholder="ຕຳແໜ່ງ ຫຼື ໜ້າທີ່ຮັບຜິດຊອບ"
                                                       list="position-suggestions">
                                                <datalist id="position-suggestions">
                                                    <option value="ເຈົ້າອາວາດ">
                                                    <option value="ຮອງເຈົ້າອາວາດ">
                                                    <option value="ພຣະລູກວັດ">
                                                    <option value="ຄູສອນ">
                                                    <option value="ພຣະສົງທົ່ວໄປ">
                                                </datalist>
                                            </div>

                                            <div class="form-group">
                                                <label for="status" class="form-label">
                                                    <i class="fas fa-info-circle"></i>
                                                    ສະຖານະ
                                                </label>
                                                <select name="status" id="status" class="form-control">
                                                    <option value="active" <?= (!isset($_POST['status']) || $_POST['status'] === 'active') ? 'selected' : '' ?>>
                                                        <i class="fas fa-check-circle"></i> ຍັງບວດຢູ່
                                                    </option>
                                                    <option value="inactive" <?= isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'selected' : '' ?>>
                                                        <i class="fas fa-times-circle"></i> ສິກແລ້ວ
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <h3 class="section-title">
                                            <i class="fas fa-address-book"></i>
                                            ຂໍ້ມູນຕິດຕໍ່ແລະການສຶກສາ
                                        </h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div class="form-group">
                                                <label for="contact_number" class="form-label">
                                                    <i class="fas fa-phone"></i>
                                                    ເບີໂທຕິດຕໍ່
                                                </label>
                                                <input type="tel" name="contact_number" id="contact_number" 
                                                       value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" 
                                                       class="form-control" placeholder="020 12345678"
                                                       pattern="[0-9\s\-\+\(\)]+"
                                                       autocomplete="tel">
                                            </div>

                                            <div class="form-group">
                                                <label for="id_card" class="form-label">
                                                    <i class="fas fa-id-card"></i>
                                                    ເລກບັດປະຊາຊົນ
                                                </label>
                                                <input type="text" name="id_card" id="id_card" 
                                                    value="<?= htmlspecialchars($_POST['id_card'] ?? '') ?>" 
                                                    class="form-control" 
                                                    placeholder="1234567890"
                                                    pattern="[0-9]{10}"
                                                    maxlength="10">
                                                <small class="form-text text-muted">10 ຫຼັກ (ໃສ່ພຽງຕົວເລກ)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="education" class="form-label">
                                                    <i class="fas fa-graduation-cap"></i>
                                                    ການສຶກສາທົ່ວໄປ
                                                </label>
                                                <input type="text" name="education" id="education" 
                                                       value="<?= htmlspecialchars($_POST['education'] ?? '') ?>" 
                                                       class="form-control" 
                                                       placeholder="ປະຖົມ, ມັດທະຍົມ, ອານຸປະລິນຍາ, ປະລິນຍາຕີ..."
                                                       list="education-suggestions">
                                                <datalist id="education-suggestions">
                                                    <option value="ປະຖົມ">
                                                    <option value="ມັດທະຍົມຕອນຕົ້ນ">
                                                    <option value="ມັດທະຍົມຕອນປາຍ">
                                                    <option value="ອານຸປະລິນຍາ">
                                                    <option value="ປະລິນຍາຕີ">
                                                    <option value="ປະລິນຍາໂທ">
                                                    <option value="ປະລິນຍາເອກ">
                                                </datalist>
                                            </div>

                                            <div class="form-group">
                                                <label for="dharma_education" class="form-label">
                                                    <i class="fas fa-om"></i>
                                                    ການສຶກສາທາງທຳມະ
                                                </label>
                                                <input type="text" name="dharma_education" id="dharma_education" 
                                                       value="<?= htmlspecialchars($_POST['dharma_education'] ?? '') ?>" 
                                                       class="form-control" 
                                                       placeholder="ນັກທັມຕີ, ນັກທັມໂທ, ນັກທັມເອກ..."
                                                       list="dharma-suggestions">
                                                <datalist id="dharma-suggestions">
                                                    <option value="ນັກທັມຕີ">
                                                    <option value="ນັກທັມໂທ">
                                                    <option value="ນັກທັມເອກ">
                                                </datalist>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- อัปโหลดรูปภาพ -->
                                    <div class="lg:col-span-1">
                                        <h3 class="section-title">
                                            <i class="fas fa-camera"></i>
                                            ຮູບພາບ
                                        </h3>
                                        
                                        <div class="photo-upload-container">
                                            <div class="photo-preview" id="photoPreview" role="button" tabindex="0">
                                                <div class="photo-placeholder">
                                                    <i class="fas fa-camera"></i>
                                                    <p>ກົດເພື່ອເລືອກຮູບ</p>
                                                    <span class="text-sm text-gray-500">JPG, PNG, GIF ຫຼື WebP</span>
                                                    <span class="text-xs text-gray-400">ຂະໜາດສູງສຸດ 5MB</span>
                                                </div>
                                            </div>
                                            <input type="file" name="photo" id="photo" accept="image/*" class="photo-input" aria-label="ເລືອກຮູບພາບ">
                                            <button type="button" class="btn btn-secondary btn-sm mt-3" id="removePhoto" style="display: none;">
                                                <i class="fas fa-trash"></i> ລຶບຮູບ
                                            </button>
                                        </div>

                                        <!-- คำแนะนำ -->
                                        <div class="info-box">
                                            <h4>
                                                <i class="fas fa-info-circle"></i> 
                                                ຄຳແນະນຳ
                                            </h4>
                                            <ul class="space-y-2">
                                                <li><i class="fas fa-star text-red-500"></i> ຟິວທີ່ມີເຄື່ອງໝາຍ * ແມ່ນຈຳເປັນ</li>
                                                <li><i class="fas fa-calculator text-blue-500"></i> ຈຳນວນພັນສາຈະຄິດໄລ່ອັດຕະໂນມັດ</li>
                                                <li><i class="fas fa-image text-green-500"></i> ຮູບພາບຂະໜາດບໍ່ເກີນ 5MB</li>
                                                <li><i class="fas fa-shield-alt text-purple-500"></i> ເລກບັດປະຊາຊົນຕ້ອງບໍ່ຊ້ຳກັນ</li>
                                                <li><i class="fas fa-mobile-alt text-orange-500"></i> ໃຊ້ງານໄດ້ຢ່າງສະດວກບນມືຖື</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <div class="flex flex-col sm:flex-row gap-4">
                                        <a href="<?= $base_url ?>monks/" class="btn btn-secondary flex-1 sm:flex-none order-2 sm:order-1">
                                            <i class="fas fa-times"></i> ຍົກເລີກ
                                        </a>
                                        <button type="submit" class="btn btn-primary flex-1 order-1 sm:order-2" id="submitBtn">
                                            <i class="fas fa-save"></i> ບັນທຶກຂໍ້ມູນ
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Excel Import Tab -->
                <div class="tab-content" id="excel-tab">
                    <div class="info-card">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <i class="fas fa-file-excel"></i>
                            </div>
                            <div class="card-header-content">
                                <h2 class="card-title">ນຳເຂົ້າຈາກ Excel</h2>
                                <p class="card-subtitle">ນຳເຂົ້າຂໍ້ມູນພຣະສົງຫຼາຍຄົນພ້ອມກັນ</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- ขั้นตอนการใช้งาน -->
                            <div class="import-steps">
                                <h3 class="section-title">ຂັ້ນຕອນການນຳເຂົ້າ</h3>
                                <div class="steps-container">
                                    <div class="step">
                                        <div class="step-number">1</div>
                                        <div class="step-content">
                                            <h4>ດາວໂຫຼດແບບຟອມ</h4>
                                            <p>ດາວໂຫຼດແບບຟອມ Excel ເພື່ອນຳໃຊ້ເປັນແມ່ແບບ</p>
                                            <a href="download_template.php" class="btn btn-success btn-sm">
                                                <i class="fas fa-download"></i> ດາວໂຫຼດແມ່ແບບ
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="step">
                                        <div class="step-number">2</div>
                                        <div class="step-content">
                                            <h4>ປ້ອນຂໍ້ມູນ</h4>
                                            <p>ເປີດໄຟລ໌ແລະປ້ອນຂໍ້ມູນພຣະສົງຕາມຮູບແບບທີ່ກຳໜົດ</p>
                                        </div>
                                    </div>
                                    
                                    <div class="step">
                                        <div class="step-number">3</div>
                                        <div class="step-content">
                                            <h4>ອັບໂຫຼດ</h4>
                                            <p>ອັບໂຫຼດໄຟລ໌ Excel ເພື່ອນຳເຂົ້າສູ່ລະບົບ</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ฟอร์มอัปโหลด -->
                            <div class="import-form">
                                <form id="excelImportForm" enctype="multipart/form-data">
                                    <div class="upload-area" id="uploadArea">
                                        <div class="upload-content">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <h3>ລາກໄຟລ໌ມາວາງທີ່ນີ້ ຫຼື ກົດເພື່ອເລືອກ</h3>
                                            <p>ຮອງຮັບໄຟລ໌: .xlsx, .xls</p>
                                            <p class="text-sm text-gray-500">ຂະໜາດສູງສຸດ: 10MB</p>
                                        </div>
                                        <input type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" class="upload-input">
                                    </div>
                                    
                                    <div class="file-info" id="fileInfo" style="display: none;">
                                        <div class="file-details">
                                            <i class="fas fa-file-excel text-green-600"></i>
                                            <div>
                                                <p class="file-name"></p>
                                                <p class="file-size text-sm text-gray-500"></p>
                                            </div>
                                        </div>
                                        <button type="button" id="removeFile" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>

                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary" id="importBtn" disabled>
                                            <i class="fas fa-upload"></i> ເລີ່ມນຳເຂົ້າ
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Progress Bar -->
                            <div class="import-progress" id="importProgress" style="display: none;">
                                <div class="progress-info">
                                    <span id="progressText">ກຳລັງນຳເຂົ້າ...</span>
                                    <span id="progressPercent">0%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                            </div>

                            <!-- Results -->
                            <div class="import-results" id="importResults" style="display: none;">
                                <!-- จะแสดงผลลัพธ์การ import ที่นี่ -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<link rel="stylesheet" href="<?= $base_url ?>assets/css/addmonks.css">

<!-- JavaScript -->
<script src="<?= $base_url ?>assets/js/addmonks.js"></script>

<?php require_once '../includes/footer.php'; ?>