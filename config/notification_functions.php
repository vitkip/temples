<?php
// filepath: c:\xampp\htdocs\temples\config\notification_functions.php
require_once __DIR__ . '/sms_functions.php';

/**
 * ສົ່ງການແຈ້ງເຕືອນແບບຄົບຖ້ວນ (SMS + In-app)
 * 
 * @param int $user_id ຜູ້ຮັບການແຈ້ງເຕືອນ
 * @param string $type ປະເພດການແຈ້ງເຕືອນ
 * @param string $title ຫົວຂໍ້
 * @param string $message ຂໍ້ຄວາມ
 * @param array $data ຂໍ້ມູນເພີ່ມເຕີມ
 * @param int $from_user_id ຜູ້ສົ່ງ (ຖ້າມີ)
 * @return array ຜົນການສົ່ງ
 */
function send_notification($user_id, $type, $title, $message, $data = null, $from_user_id = null) {
    global $pdo;
    
    $results = [
        'in_app' => false,
        'sms' => false,
        'email' => false,
        'errors' => []
    ];
    
    try {
        // ດຶງຂໍ້ມູນຜູ້ໃຊ້ແລະການຕັ້ງຄ່າ
        $stmt = $pdo->prepare("
            SELECT u.*, ns.sms_enabled, ns.email_enabled, ns.push_enabled,
                   ns.user_approval_sms, ns.user_approval_push
            FROM users u 
            LEFT JOIN notification_settings ns ON u.id = ns.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $results['errors'][] = "ບໍ່ພົບຜູ້ໃຊ້";
            return $results;
        }
        
        // 1. ສົ່ງການແຈ້ງເຕືອນໃນແອັບ (ບັນທຶກໃນຖານຂໍ້ມູນ)
        $results['in_app'] = create_in_app_notification($user_id, $type, $title, $message, $data, $from_user_id);
        
        // 2. ສົ່ງ SMS (ຖ້າເປີດໄວ້)
        if (should_send_sms($user, $type)) {
            $results['sms'] = send_notification_sms($user['phone'], $title, $message);
        }
        
        // 3. ສົ່ງ Email (ສຳລັບອະນາຄົດ)
        if (should_send_email($user, $type)) {
            // TODO: ເພີ່ມການສົ່ງ Email ໃນອະນາຄົດ
            $results['email'] = false;
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "ເກີດຂໍ້ຜິດພາດ: " . $e->getMessage();
        error_log("Notification error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * ສ້າງການແຈ້ງເຕືອນໃນແອັບ
 */
function create_in_app_notification($user_id, $type, $title, $message, $data = null, $from_user_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, title, message, data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $json_data = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
        
        return $stmt->execute([$user_id, $from_user_id, $type, $title, $message, $json_data]);
    } catch (PDOException $e) {
        error_log("Error creating in-app notification: " . $e->getMessage());
        return false;
    }
}

/**
 * ສົ່ງ SMS ການແຈ້ງເຕືອນ
 */
function send_notification_sms($phone, $title, $message) {
    if (empty($phone)) {
        return false;
    }
    
    // ຈັດຮູບແບບເບີໂທລະສັບ
    $formatted_phone = format_phone_number($phone);
    if (!$formatted_phone) {
        return false;
    }
    
    // ສ້າງຂໍ້ຄວາມ SMS
    $sms_message = "[$title] $message";
    
    // ສົ່ງ SMS ຜ່ານ Twilio
    return send_sms($formatted_phone, $sms_message);
}

/**
 * ສົ່ງ SMS ຜ່ານ Twilio
 */
function send_sms($phone, $message) {
    global $pdo;
    
    // ດຶງການຕັ້ງຄ່າ Twilio
    $settings = get_twilio_settings();
    if (!$settings) {
        return false;
    }
    
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        $client = new \Twilio\Rest\Client($settings['account_sid'], $settings['auth_token']);
        
        $twilio_message = $client->messages->create(
            $phone,
            [
                'from' => $settings['phone_number'],
                'body' => $message
            ]
        );
        
        // ບັນທຶກປະຫວັດການສົ່ງ
        log_sms_sent($phone, $twilio_message->sid, $message);
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * ດຶງການຕັ້ງຄ່າ Twilio
 */
function get_twilio_settings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'twilio_%'");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = str_replace('twilio_', '', $row['setting_key']);
            $settings[$key] = $row['setting_value'];
        }
        
        // ກວດສອບວ່າມີຄ່າທີ່ຈຳເປັນຫຼືບໍ່
        if (empty($settings['account_sid']) || empty($settings['auth_token']) || empty($settings['phone_number'])) {
            return false;
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Error getting Twilio settings: " . $e->getMessage());
        return false;
    }
}

/**
 * ຈັດຮູບແບບເບີໂທລະສັບ
 */
function format_phone_number($phone) {
    // ລຶບຊ່ອງວ່າງແລະອັກຂະລະພິເສດ
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // ເພີ່ມລະຫັດປະເທດລາວ ຖ້າບໍ່ມີ
    if (preg_match('/^(20|30)\d{8}$/', $phone)) {
        $phone = '+856' . $phone;
    } elseif (preg_match('/^856\d{10}$/', $phone)) {
        $phone = '+' . $phone;
    } elseif (!preg_match('/^\+856\d{10}$/', $phone)) {
        return false; // ຮູບແບບເບີໂທບໍ່ຖືກຕ້ອງ
    }
    
    return $phone;
}

/**
 * ກວດສອບວ່າຄວນສົ່ງ SMS ຫຼືບໍ່
 */
function should_send_sms($user, $type) {
    // ກວດສອບການຕັ້ງຄ່າທົ່ວໄປ
    if (!$user['sms_enabled']) {
        return false;
    }
    
    // ກວດສອບການຕັ້ງຄ່າສະເພາະປະເພດ
    switch ($type) {
        case 'user_approved':
        case 'user_rejected':
            return $user['user_approval_sms'] ?? true;
        default:
            return true;
    }
}

/**
 * ກວດສອບວ່າຄວນສົ່ງ Email ຫຼືບໍ່
 */
function should_send_email($user, $type) {
    return $user['email_enabled'] ?? false;
}

/**
 * ສົ່ງການແຈ້ງເຕືອນການອະນຸມັດຜູ້ໃຊ້
 */
function send_user_approval_notification($approved_user_id, $approver_id) {
    global $pdo;
    
    try {
        // ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ຖືກອະນຸມັດ
        $stmt = $pdo->prepare("
            SELECT u.*, t.name as temple_name 
            FROM users u 
            LEFT JOIN temples t ON u.temple_id = t.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$approved_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // ດຶງຂໍ້ມູນຜູ້ອະນຸມັດ
        $stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
        $stmt->execute([$approver_id]);
        $approver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $approver_name = $approver ? $approver['name'] : 'ລະບົບ';
        
        // ສ້າງຂໍ້ຄວາມ
        $title = "ບັນຊີຂອງທ່ານຖືກອະນຸມັດແລ້ວ";
        $message = "ບັນຊີຂອງທ່ານໃນລະບົບຈັດການວັດໄດ້ຮັບການອະນຸມັດໂດຍ {$approver_name} ແລ້ວ. ທ່ານສາມາດເຂົ້າໃຊ້ລະບົບໄດ້ຕະຫຼອດເວລາ.";
        
        // ຂໍ້ມູນເພີ່ມເຕີມ
        $data = [
            'approver_id' => $approver_id,
            'approver_name' => $approver_name,
            'temple_name' => $user['temple_name'],
            'approved_at' => date('Y-m-d H:i:s')
        ];
        
        // ສົ່ງການແຈ້ງເຕືອນ
        return send_notification(
            $approved_user_id,
            'user_approved',
            $title,
            $message,
            $data,
            $approver_id
        );
        
    } catch (Exception $e) {
        error_log("Error sending user approval notification: " . $e->getMessage());
        return false;
    }
}

/**
 * ສົ່ງການແຈ້ງເຕືອນການປະຕິເສດຜູ້ໃຊ້
 */
function send_user_rejection_notification($rejected_user_id, $rejector_id, $reason = '') {
    global $pdo;
    
    try {
        // ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ຖືກປະຕິເສດ
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$rejected_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        // ດຶງຂໍ້ມູນຜູ້ປະຕິເສດ
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$rejector_id]);
        $rejector = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $rejector_name = $rejector ? $rejector['name'] : 'ລະບົບ';
        
        // ສ້າງຂໍ້ຄວາມ
        $title = "ຄຳຂໍລົງທະບຽນຖືກປະຕິເສດ";
        $message = "ຄຳຂໍລົງທະບຽນຂອງທ່ານໃນລະບົບຈັດການວັດຖືກປະຕິເສດໂດຍ {$rejector_name}";
        
        if (!empty($reason)) {
            $message .= " ເຫດຜົນ: $reason";
        }
        
        $message .= " ກະລຸນາຕິດຕໍ່ຜູ້ດູແລລະບົບເພື່ອຂໍຂໍ້ມູນເພີ່ມເຕີມ.";
        
        // ຂໍ້ມູນເພີ່ມເຕີມ
        $data = [
            'rejector_id' => $rejector_id,
            'rejector_name' => $rejector_name,
            'reason' => $reason,
            'rejected_at' => date('Y-m-d H:i:s')
        ];
        
        // ສົ່ງການແຈ້ງເຕືອນ
        return send_notification(
            $rejected_user_id,
            'user_rejected',
            $title,
            $message,
            $data,
            $rejector_id
        );
        
    } catch (Exception $e) {
        error_log("Error sending user rejection notification: " . $e->getMessage());
        return false;
    }
}

/**
 * ດຶງການແຈ້ງເຕືອນຂອງຜູ້ໃຊ້
 */
function get_user_notifications($user_id, $limit = 10, $offset = 0, $unread_only = false) {
    global $pdo;
    
    try {
        $where_clause = "WHERE n.user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $where_clause .= " AND n.is_read = 0";
        }
        
        $stmt = $pdo->prepare("
            SELECT n.*, u.name as from_user_name
            FROM notifications n
            LEFT JOIN users u ON n.from_user_id = u.id
            $where_clause
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting user notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * ຫມາຍການແຈ້ງເຕືອນວ່າອ່ານແລ້ວ
 */
function mark_notification_as_read($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notification_id, $user_id]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * ຫມາຍການແຈ້ງເຕືອນທັງໝົດວ່າອ່ານແລ້ວ
 */
function mark_all_notifications_as_read($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? AND is_read = 0
        ");
        
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * ນັບຈຳນວນການແຈ້ງເຕືອນທີ່ຍັງບໍ່ອ່ານ
 */
function count_unread_notifications($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}
?>
