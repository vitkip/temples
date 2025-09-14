-- เพิ่มข้อมูลตัวอย่างสำหรับทดสอบ Temple Distribution Chart
-- ใช้ไฟล์นี้เมื่อต้องการทดสอบกราฟกับข้อมูลจริง

-- ตรวจสอบและเพิ่มข้อมูลจังหวัด (ถ้ายังไม่มี)
INSERT IGNORE INTO provinces (province_id, province_name) VALUES
(1, 'ນະຄອນຫຼວງວຽງຈັນ'),
(2, 'ຫຼວງພຣະບາງ'), 
(3, 'ຈຳປາສັກ'),
(4, 'ສະຫວັນນະເຂດ'),
(5, 'ຄຳມ່ວນ'),
(6, 'ບໍ່ແກ້ວ'),
(7, 'ອັດຕະປື'),
(8, 'ໄຊຍະບູລີ');

-- ตรวจสอบและเพิ่มข้อมูลอำเภอ (ถ้ายังไม่มี)
INSERT IGNORE INTO districts (district_id, district_name, province_id) VALUES
(1, 'ເມືອງຈັນທະບູລີ', 1),
(2, 'ເມືອງສີສັດຕະນາກ', 1),
(3, 'ເມືອງຫຼວງພຣະບາງ', 2),
(4, 'ເມືອງປາກສີ', 3),
(5, 'ເມືອງໄກສອນ', 4),
(6, 'ເມືອງຄຳມ່ວນ', 5),
(7, 'ເມືອງບໍ່ແກ້ວ', 6),
(8, 'ເມືອງສາມະຄີໄຊ', 7),
(9, 'ເມືອງໄຊຍະບູລີ', 8);

-- เพิ่มข้อมูลวัดตัวอย่างสำหรับทดสอบกราฟ
INSERT IGNORE INTO temples (id, name, province_id, district_id, status, created_at) VALUES
-- ນະຄອນຫຼວງວຽງຈັນ (8 ວັດ)
(1, 'ວັດສີສະເກດ', 1, 1, 'active', NOW()),
(2, 'ວັດສີມືງ', 1, 1, 'active', NOW()),
(3, 'ວັດວິສຸນາ', 1, 1, 'active', NOW()),
(4, 'ວັດພຣະເກດ', 1, 2, 'active', NOW()),
(5, 'ວັດປາ​ຫ່າຍ', 1, 2, 'active', NOW()),
(6, 'ວັດທາດຫຼວງ', 1, 1, 'active', NOW()),
(7, 'ວັດມິສາຍ', 1, 2, 'active', NOW()),
(8, 'ວັດອົງຕື', 1, 1, 'active', NOW()),

-- ຫຼວງພຣະບາງ (5 ວັດ)
(9, 'ວັດຊຽງທອງ', 2, 3, 'active', NOW()),
(10, 'ວັດໄໝ', 2, 3, 'active', NOW()),
(11, 'ວັດວິໄສ', 2, 3, 'active', NOW()),
(12, 'ວັດມໍພຸດ', 2, 3, 'active', NOW()),
(13, 'ວັດມະນົນລອມ', 2, 3, 'active', NOW()),

-- ຈຳປາສັກ (4 ວັດ)
(14, 'ວັດວໍລະເມສ', 3, 4, 'active', NOW()),
(15, 'ວັດຫຼວງ', 3, 4, 'active', NOW()),
(16, 'ວັດເຊິງພະ', 3, 4, 'active', NOW()),
(17, 'ວັດພະບາດ', 3, 4, 'active', NOW()),

-- ສະຫວັນນະເຂດ (3 ວັດ)
(18, 'ວັດໄຊພົງ', 4, 5, 'active', NOW()),
(19, 'ວັດໄກສອນ', 4, 5, 'active', NOW()),
(20, 'ວັດສີນື່ນ', 4, 5, 'active', NOW()),

-- ຄຳມ່ວນ (2 ວັດ)
(21, 'ວັດຄຳມ່ວນ', 5, 6, 'active', NOW()),
(22, 'ວັດນາບອງ', 5, 6, 'active', NOW()),

-- ບໍ່ແກ້ວ (2 ວັດ)
(23, 'ວັດບໍ່ແກ້ວ', 6, 7, 'active', NOW()),
(24, 'ວັດແຫຼມ', 6, 7, 'active', NOW()),

-- ອັດຕະປື (1 ວັດ)
(25, 'ວັດສາມະຄີໄຊ', 7, 8, 'active', NOW()),

-- ໄຊຍະບູລີ (2 ວັດ)
(26, 'ວັດໄຊຍະບູລີ', 8, 9, 'active', NOW()),
(27, 'ວັດແກ້ວ', 8, 9, 'active', NOW());

-- อัพเดต timestamp
UPDATE temples SET updated_at = created_at WHERE updated_at IS NULL;

-- ตรวจสอบผลลัพธ์
SELECT 
    p.province_name,
    COUNT(t.id) as temple_count
FROM temples t
LEFT JOIN provinces p ON t.province_id = p.province_id
WHERE t.status = 'active'
GROUP BY t.province_id, p.province_name
HAVING temple_count > 0
ORDER BY temple_count DESC, p.province_name ASC;