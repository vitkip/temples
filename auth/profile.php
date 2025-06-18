<?php
// filepath: c:\xampp\htdocs\temples\auth\profile.php
session_start();
require_once '../config/db.php';
require_once '../config/base_url.php';

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบหรือไม่
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = "ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ";
    header("Location: {$base_url}auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້";
        header("Location: {$base_url}dashboard.php");
        exit;
    }

    // ถ้าผู้ใช้เป็น admin หรือ superadmin ดึงข้อมูลวัดด้วย
    if (in_array($user['role'], ['admin', 'superadmin']) && !empty($user['temple_id'])) {
        $temple_stmt = $pdo->prepare("SELECT name FROM temples WHERE id = ?");
        $temple_stmt->execute([$user['temple_id']]);
        $temple = $temple_stmt->fetch();
    }

} catch (PDOException $e) {
    $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
}

// ประมวลผลการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจสอบความถูกต้องของข้อมูล
    $errors = [];

    if (empty($name)) {
        $errors[] = "ກະລຸນາປ້ອນຊື່";
    }

    if (empty($email)) {
        $errors[] = "ກະລຸນາປ້ອນອີເມວ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ";
    }

    // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ยกเว้นอีเมลของผู้ใช้เอง)
    if (!empty($email) && $email !== $user['email']) {
        $email_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $user_id]);
        if ($email_check->fetchColumn() > 0) {
            $errors[] = "ອີເມວນີ້ຖືກໃຊ້ແລ້ວ";
        }
    }

    // ตรวจสอบการเปลี่ยนรหัสผ่าน
    $update_password = false;
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານປັດຈຸບັນ";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = "ລະຫັດຜ່ານປັດຈຸບັນບໍ່ຖືກຕ້ອງ";
        }

        if (empty($new_password)) {
            $errors[] = "ກະລຸນາປ້ອນລະຫັດຜ່ານໃໝ່";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "ລະຫັດຜ່ານໃໝ່ຕ້ອງມີຢ່າງນ້ອຍ 6 ຕົວອັກສອນ";
        }

        if (empty($confirm_password)) {
            $errors[] = "ກະລຸນາຢືນຢັນລະຫັດຜ່ານໃໝ່";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "ລະຫັດຜ່ານຢືນຢັນບໍ່ຕົງກັນ";
        }

        if (empty($errors)) {
            $update_password = true;
        }
    }

    // ถ้าไม่มีข้อผิดพลาด ดำเนินการอัปเดตข้อมูล
    if (empty($errors)) {
        try {
            if ($update_password) {
                $sql = "UPDATE users SET name = ?, email = ?, phone = ?, password = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    password_hash($new_password, PASSWORD_DEFAULT),
                    $user_id
                ]);
            } else {
                $sql = "UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $user_id
                ]);
            }

            // อัปเดต session name ด้วย
            $_SESSION['user']['name'] = $name;

            $success_message = "ອັບເດດຂໍ້ມູນສຳເລັດແລ້ວ";

            // รีโหลดข้อมูลผู้ใช้
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

        } catch (PDOException $e) {
            $error_message = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$page_title = 'ຂໍ້ມູນສ່ວນຕົວ';
require_once '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
            <div class="category-icon">
                <i class="fas fa-user"></i>
            </div>
            ຂໍ້ມູນສ່ວນຕົວ
        </h1>

        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <?= $success_message ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <?= $error_message ?>
        </div>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-4">
            <div class="p-6">
                <form method="post" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                                ຊື່ຜູ້ໃຊ້
                            </label>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5" 
                                   disabled>
                            <p class="text-xs text-gray-500 mt-1">ບໍ່ສາມາດແກ້ໄຂຊື່ຜູ້ໃຊ້ໄດ້</p>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                ສິດການໃຊ້ງານ
                            </label>
                            <input type="text" id="role" name="role" 
                                   value="<?= htmlspecialchars($user['role'] === 'superadmin' ? 'ຜູ້ດູແລລະບົບ' : ($user['role'] === 'admin' ? 'ຜູ້ດູແລວັດ' : 'ຜູ້ໃຊ້ທົ່ວໄປ')) ?>" 
                                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5" 
                                   disabled>
                        </div>

                        <?php if (isset($temple) && !empty($temple)): ?>
                        <div class="md:col-span-2">
                            <label for="temple" class="block text-sm font-medium text-gray-700 mb-1">
                                ວັດທີ່ຮັບຜິດຊອບ
                            </label>
                            <input type="text" id="temple" name="temple" value="<?= htmlspecialchars($temple['name']) ?>" 
                                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5" 
                                   disabled>
                        </div>
                        <?php endif; ?>

                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                ຊື່-ນາມສະກຸນ <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5" 
                                   required>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                ອີເມວ <span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5" 
                                   required>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                ເບີໂທ
                            </label>
                            <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5">
                        </div>
                    </div>

                    <h3 class="text-lg font-medium text-gray-900 mt-8 mb-4">ປ່ຽນລະຫັດຜ່ານ <span class="text-sm font-normal text-gray-500">(ຖ້າຕ້ອງການ)</span></h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                                ລະຫັດຜ່ານປັດຈຸບັນ
                            </label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5">
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                ລະຫັດຜ່ານໃໝ່
                            </label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5">
                            <p class="text-xs text-gray-500 mt-1">ຢ່າງນ້ອຍ 6 ຕົວອັກສອນ</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                ຢືນຢັນລະຫັດຜ່ານໃໝ່
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5">
                        </div>
                    </div>

                    <div class="flex justify-end mt-6 gap-3">
                        <a href="<?= $base_url ?>dashboard.php" class="py-2 px-4 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            ຍົກເລີກ
                        </a>
                        <button type="submit" class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            ບັນທຶກ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>