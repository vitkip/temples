# ລະບົບການແຈ້ງເຕືອນ (Notification System)

## ລາຍການໄຟລ์ທີ່ຖືກເພີ່ມ/ແກ້ໄຂ

### 1. ຖານຂໍ້ມູນ
- `sql/notifications.sql` - ສ້າງຕາຕະລາງການແຈ້ງເຕືອນ

### 2. ໄຟລ์ PHP
- `config/notification_functions.php` - ຟັງຊັນຈັດການການແຈ້ງເຕືອນ
- `api/get-notifications.php` - API ດຶງການແຈ້ງເຕືອນ  
- `api/mark-notification-read.php` - API ໝາຍວ່າອ່ານແລ້ວ
- `api/mark-all-notifications-read.php` - API ໝາຍວ່າອ່ານທັງໝົດ
- `users/approve.php` - ຖືກແກ້ໄຂໃຫ້ສົ່ງການແຈ້ງເຕືອນ

### 3. ໄຟລ์ JavaScript
- `assets/js/notifications.js` - ລະບົບການແຈ້ງເຕືອນຝັ່ງຫນ້າເວັບ

### 4. ໄຟລ์ UI
- `includes/header.php` - ເພີ່ມພື້ນທີ່ສຳລັບການແຈ້ງເຕືອນ
- `includes/footer.php` - ເພີ່ມ script ການແຈ້ງເຕືອນ

## ຄຸນສົມບັດຂອງລະບົບ

### 1. ການແຈ້ງເຕືອນໃນແອັບ
- ສະແດງຢູ່ແຖບເໜາບານ (header)
- ນັບຈຳນວນທີ່ຍັງບໍ່ອ່ານ
- ສະແດງລາຍການການແຈ້ງເຕືອນແບບ dropdown
- ໝາຍວ່າອ່ານແລ້ວເມື່ອຄິກ

### 2. ການແຈ້ງເຕືອນ SMS  
- ສົ່ງຜ່ານ Twilio API
- ຮອງຮັບການຕັ້ງຄ່າແຕ່ລະຜູ້ໃຊ້
- ຟໍແມັດຂໍ້ຄວາມເປັນພາສາລາວ

### 3. ປະເພດການແຈ້ງເຕືອນ
- `user_approved` - ອະນຸມັດຜູ້ໃຊ້
- `user_rejected` - ປະຕິເສດຜູ້ໃຊ້  
- `user_suspended` - ໂຈະຜູ້ໃຊ້
- `monk_added` - ເພີ່ມພະສົງ
- `monk_updated` - ແກ້ໄຂພະສົງ
- `temple_updated` - ແກ້ໄຂວັດ
- `system_notification` - ການແຈ້ງເຕືອນລະບົບ

## ວິທີການໃຊ້ງານ

### 1. ສົ່ງການແຈ້ງເຕືອນ
```php
require_once 'config/notification_functions.php';

// ສົ່ງການແຈ້ງເຕືອນອະນຸມັດຜູ້ໃຊ້
$result = send_user_approval_notification($user_id, true); // true = ອະນຸມັດ, false = ປະຕິເສດ

// ສົ່ງການແຈ້ງເຕືອນທົ່ວໄປ
$result = send_notification(
    $user_id,
    'system',
    'ຫົວຂໍ້',
    'ຂໍ້ຄວາມ',
    $additional_data,
    $from_user_id
);
```

### 2. ດຶງການແຈ້ງເຕືອນ
```javascript
// ລະບົບຈະເລີ່ມຕົ້ນອັດຕະໂນມັດ
// ສາມາດເຂົ້າເຖິງຜ່ານ window.notificationManager

// ໂຫຼດການແຈ້ງເຕືອນເພີ່ມ
notificationManager.loadNotifications(10, 5); // offset=10, limit=5

// ໝາຍວ່າອ່ານແລ້ວ
notificationManager.markAsRead(notification_id);

// ໝາຍວ່າອ່ານທັງໝົດ
notificationManager.markAllAsRead();
```

### 3. ຕັ້ງຄ່າການແຈ້ງເຕືອນ
```php
// ດຶງການຕັ້ງຄ່າຂອງຜູ້ໃຊ້
$settings = get_user_notification_settings($user_id);

// ອັບເດດການຕັ້ງຄ່າ
update_user_notification_settings($user_id, [
    'sms_enabled' => true,
    'email_enabled' => false,
    'push_enabled' => true,
    'user_approval_sms' => true,
    'user_approval_push' => true
]);
```

## ການທົດສອບ

1. ເຂົ້າໄປທີ່ `http://localhost/temples/test_notification.php` ເພື່ອສ້າງການແຈ້ງເຕືອນທົດສອບ
2. ກວດສອບວ່າ badge ການແຈ້ງເຕືອນປະກົດຢູ່ແຖບເຫນືອ
3. ຄິກທີ່ໄອຄອນການແຈ້ງເຕືອນເພື່ອເບິ່ງລາຍການ
4. ທົດສອບການໝາຍວ່າອ່ານແລ້ວ

## API Endpoints

### GET `/api/get-notifications.php`
ດຶງລາຍການການແຈ້ງເຕືອນ

Parameters:
- `limit` (int) - ຈຳນວນສູງສຸດ (default: 10)
- `offset` (int) - ຈຸດເລີ່ມຕົ້ນ (default: 0)  
- `unread_only` (boolean) - ສະເພາະທີ່ຍັງບໍ່ອ່ານ (default: false)

### POST `/api/mark-notification-read.php`
ໝາຍການແຈ້ງເຕືອນວ່າອ່ານແລ້ວ

Body:
```json
{
  "notification_id": 123
}
```

### POST `/api/mark-all-notifications-read.php`
ໝາຍການແຈ້ງເຕືອນທັງໝົດວ່າອ່ານແລ້ວ

## ໂຄງສ້າງຖານຂໍ້ມູນ

### ຕາຕະລາງ `notifications`
- `id` - Primary key
- `user_id` - ຜູ້ຮັບການແຈ້ງເຕືອນ
- `from_user_id` - ຜູ້ສົ່ງ (ຖ້າມີ)
- `type` - ປະເພດການແຈ້ງເຕືອນ
- `title` - ຫົວຂໍ້
- `message` - ຂໍ້ຄວາມ
- `data` - ຂໍ້ມູນເພີ່ມເຕີມ (JSON)
- `is_read` - ສະຖານະການອ່ານ
- `read_at` - ເວລາອ່ານ
- `created_at` - ເວລາສ້າງ
- `updated_at` - ເວລາແກ້ໄຂ

### ຕາຕະລາງ `notification_settings`
- `id` - Primary key
- `user_id` - ຜູ້ໃຊ້
- `sms_enabled` - ເປີດ SMS
- `email_enabled` - ເປີດ Email
- `push_enabled` - ເປີດ Push
- `user_approval_sms` - SMS ເມື່ອອະນຸມັດ
- `user_approval_push` - Push ເມື່ອອະນຸມັດ

## ປະຫວັດການພັດທະນາ

- ✅ ສ້າງໂຄງສ້າງຖານຂໍ້ມູນ
- ✅ ສ້າງຟັງຊັນ PHP ສຳລັບການແຈ້ງເຕືອນ
- ✅ ສ້າງ API endpoints
- ✅ ສ້າງລະບົບ frontend JavaScript
- ✅ ເຊື່ອມຕໍ່ກັບລະບົບອະນຸມັດຜູ້ໃຊ້
- ✅ ເພີ່ມການສະແດງຜົນໃນ UI
- ✅ ທົດສອບລະບົບ
