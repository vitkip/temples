<?php
// filepath: c:\xampp\htdocs\temples\config\sms_functions.php
require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

/**
 * ส่ง OTP ไปยังเบอร์โทรศัพท์
 * 
 * @param string $phone เบอร์โทรศัพท์ (รวม country code)
 * @param string $otp รหัส OTP
 * @return boolean สถานะการส่ง
 */
function send_otp_sms($phone, $otp) {
    // ดึงการตั้งค่า Twilio จากฐานข้อมูล
    global $pdo;
    $settings = [];
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching settings: " . $e->getMessage());
        return false;
    }
    
    $account_sid = $settings['twilio_account_sid'] ?? '';
    $auth_token = $settings['twilio_auth_token'] ?? '';
    $twilio_phone = $settings['twilio_phone_number'] ?? '';
    $site_name = $settings['site_name'] ?? 'ລະບົບຈັດການວັດ';
    
    if (empty($account_sid) || empty($auth_token) || empty($twilio_phone)) {
        error_log("Missing Twilio settings");
        return false;
    }

    try {
        $client = new Client($account_sid, $auth_token);
        $message = $client->messages->create(
            $phone, // เบอร์ผู้รับ
            [
                'from' => $twilio_phone, // เบอร์ที่ได้จาก Twilio
                'body' => "[$site_name] ລະຫັດຢືນຢັນຂອງທ່ານແມ່ນ: $otp  ລະຫັດນີ້ຈະໝົດອາຍຸໃນ 15 ນາທີ."
            ]
        );
        
        // บันทึกประวัติการส่ง SMS
        log_sms_sent($phone, $message->sid);
        return true;
    } catch (Exception $e) {
        error_log("Error sending SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * บันทึกประวัติการส่ง SMS
 */
function log_sms_sent($phone, $message_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone, message_id, sent_at) VALUES (?, ?, NOW())");
        $stmt->execute([$phone, $message_id]);
    } catch (PDOException $e) {
        error_log("Error logging SMS: " . $e->getMessage());
    }
}

/**
 * สร้าง OTP
 * 
 * @param int $length ความยาวของ OTP
 * @return string รหัส OTP
 */
function generate_otp($length = 6) {
    // สร้าง OTP แบบตัวเลขอย่างเดียวให้จำง่าย
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * บันทึก OTP ลงในฐานข้อมูล
 * 
 * @param int $user_id ID ของผู้ใช้
 * @param string $otp รหัส OTP
 * @param int $expires_in เวลาหมดอายุ (วินาที)
 * @return boolean สถานะการบันทึก
 */
function save_otp($user_id, $otp, $expires_in = 900) { // 900 วินาที = 15 นาที
    global $pdo;
    $expires = date('Y-m-d H:i:s', time() + $expires_in);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET otp = ?, otp_expires = ? WHERE id = ?");
        return $stmt->execute([$otp, $expires, $user_id]);
    } catch (PDOException $e) {
        error_log("Error saving OTP: " . $e->getMessage());
        return false;
    }
}

/**
 * ตรวจสอบ OTP
 * 
 * @param int $user_id ID ของผู้ใช้
 * @param string $otp รหัส OTP ที่ผู้ใช้กรอก
 * @return boolean ผลการตรวจสอบ
 */
function verify_otp($user_id, $otp) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT otp, otp_expires FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // ตรวจสอบว่า OTP ถูกต้องและยังไม่หมดอายุ
        if ($user['otp'] === $otp && strtotime($user['otp_expires']) > time()) {
            // เคลียร์ OTP เมื่อใช้งานสำเร็จ
            $stmt = $pdo->prepare("UPDATE users SET otp = NULL, otp_expires = NULL, phone_verified = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error verifying OTP: " . $e->getMessage());
        return false;
    }
}