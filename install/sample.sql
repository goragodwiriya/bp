-- ---------------------------------------------------------------------------
-- install/sample.sql — ข้อมูลตัวอย่างของระบบบันทึกความดันโลหิต (ชื่อบุคคลและสถานที่สมมติทั้งหมด)
--
-- ตัวติดตั้งไม่โหลดไฟล์นี้ (schemaFiles() อ่านแค่ core, database, timeline และ
-- database.sql ของโมดูล) ข้อมูลชุดนี้จึงไม่เข้าฐานข้อมูลของไซต์ถ้าไม่ได้สั่งเอง
--
-- วิธีใช้ (สำรองฐานข้อมูลก่อน แทน app ด้วย prefix และ bp_test ด้วยชื่อฐานของไซต์):
--   sed 's/{prefix}/app/g' install/sample.sql | mysql -u <user> -p bp_test
--
-- ข้อมูลทุกแถวเป็นของสมาชิกคนเดียวตาม @member_id ด้านล่าง ต้องเข้าระบบด้วยบัญชีนั้นจึงจะเห็น
-- ผู้ดูแลระบบสูงสุด (status = 1) เห็นโหมด อสม. เลย ส่วนบัญชีอื่นต้องมีสิทธิ can_manage_bp_care
--
-- วันที่ทุกค่าคิดย้อนจากวันที่โหลด แดชบอร์ดจึงแสดงครบทุกสถานะเสมอ
-- ข้อมูลเก่าลงเมื่อไร ให้รันไฟล์นี้ซ้ำ ระบบจะลบชุดเดิมแล้วลงใหม่ให้เป็นปัจจุบัน
--
-- แดชบอร์ด อสม. ทั้งหมด 13 คน: ส่งต่อ 2, สูง 2, ก่อนสูง 3, ปกติ 4, ต่ำ 1, ไม่มีข้อมูล 1
-- ไม่ได้วัดเกิน 30 วัน 4 คน และยังไม่ให้ความยินยอม 2 คน
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- id ของสมาชิกเจ้าของข้อมูล (1 = ผู้ดูแลระบบที่ตัวติดตั้งสร้าง)
SET @member_id = 1;

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- ลบชุดตัวอย่างเดิม — แถวตัวอย่างมี client_uuid ขึ้นต้นด้วย 00000000-0000-4000-8000-
-- ซึ่ง uuid สุ่มของจริงไม่มีทางซ้ำ ข้อมูลอื่นของสมาชิกจึงไม่ถูกแตะ ยกเว้นค่าที่วัด
-- หรือรอบเยี่ยมที่ไปเพิ่มให้คนหรือกลุ่มตัวอย่างในหน้าเว็บ ซึ่งจะถูกลบไปพร้อมกัน
-- ถ้าต้องการลบข้อมูลตัวอย่างอย่างเดียว ให้รันถึงท้ายส่วนนี้แล้วตามด้วย COMMIT;
-- แท็กไม่ถูกลบ เพราะไม่มีเครื่องหมายแยกจากแท็กที่สมาชิกสร้างเอง
-- ---------------------------------------------------------------------------
DELETE I FROM `{prefix}_bp_items` I
INNER JOIN `{prefix}_bp` P ON P.`id` = I.`bp_id`
INNER JOIN `{prefix}_bp_family` F ON F.`id` = P.`family_id`
WHERE F.`member_id` = @member_id AND F.`client_uuid` LIKE '00000000-0000-4000-8000-%';

DELETE P FROM `{prefix}_bp` P
INNER JOIN `{prefix}_bp_family` F ON F.`id` = P.`family_id`
WHERE F.`member_id` = @member_id AND F.`client_uuid` LIKE '00000000-0000-4000-8000-%';

DELETE V FROM `{prefix}_bp_visit` V
INNER JOIN `{prefix}_bp_group` G ON G.`id` = V.`group_id`
WHERE G.`member_id` = @member_id AND G.`client_uuid` LIKE '00000000-0000-4000-8000-%';

DELETE FROM `{prefix}_bp_family` WHERE `member_id` = @member_id AND `client_uuid` LIKE '00000000-0000-4000-8000-%';

DELETE FROM `{prefix}_bp_group` WHERE `member_id` = @member_id AND `client_uuid` LIKE '00000000-0000-4000-8000-%';

-- ---------------------------------------------------------------------------
-- id เริ่มต่อจากแถวสูงสุดที่มีอยู่ จึงไม่ชนกับข้อมูลจริง และเป็น 1, 2, 3 ... เมื่อตารางว่าง
-- ---------------------------------------------------------------------------
SET @group_id = (SELECT COALESCE(MAX(`id`), 0) FROM `{prefix}_bp_group`);
SET @family_id = (SELECT COALESCE(MAX(`id`), 0) FROM `{prefix}_bp_family`);
SET @visit_id = (SELECT COALESCE(MAX(`id`), 0) FROM `{prefix}_bp_visit`);
SET @bp_id = (SELECT COALESCE(MAX(`id`), 0) FROM `{prefix}_bp`);

-- ---------------------------------------------------------------------------
-- แท็ก — bp.tag เก็บ category_id ของตารางนี้ และ language ว่าง = ใช้ได้ทุกภาษา
-- INSERT IGNORE: ถ้าสมาชิกมีแท็กเลขนี้อยู่แล้วจะคงของเดิมไว้
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `{prefix}_bp_category` (`member_id`, `type`, `category_id`, `language`, `topic`, `color`, `is_active`) VALUES
(@member_id, 'tag', '1', '', 'ตอนเช้า', NULL, 1),
(@member_id, 'tag', '2', '', 'ตอนเย็น', NULL, 1),
(@member_id, 'tag', '3', '', 'ก่อนนอน', NULL, 1),
(@member_id, 'tag', '4', '', 'หลังออกกำลังกาย', NULL, 1),
(@member_id, 'tag', '5', '', 'เยี่ยมบ้าน', NULL, 1);

-- ---------------------------------------------------------------------------
-- กลุ่ม / หมู่บ้าน
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_bp_group` (`id`, `member_id`, `name`, `village`, `moo`, `address`, `latitude`, `longitude`, `note`, `client_uuid`, `created_at`, `updated_at`) VALUES
(@group_id + 1, @member_id, 'กลุ่ม อสม. หมู่ 3', 'บ้านหนองตัวอย่าง', '3', 'ต.ตัวอย่าง อ.เมืองขอนแก่น', 16.4419, 102.836, 'เยี่ยมบ้านทุกวันเสาร์ต้นเดือน', '00000000-0000-4000-8000-010000000001', NOW() - INTERVAL 180 DAY, NOW()),
(@group_id + 2, @member_id, 'กลุ่ม อสม. หมู่ 5', 'บ้านโคกตัวอย่าง', '5', 'ต.ตัวอย่าง อ.เมืองขอนแก่น', 16.4522, 102.8214, NULL, '00000000-0000-4000-8000-010000000002', NOW() - INTERVAL 180 DAY, NOW()),
(@group_id + 3, @member_id, 'ชมรมผู้สูงอายุตัวอย่าง', NULL, NULL, NULL, NULL, NULL, 'กลุ่มเปล่า ยังไม่มีสมาชิก', '00000000-0000-4000-8000-010000000003', NOW() - INTERVAL 30 DAY, NOW());

-- ---------------------------------------------------------------------------
-- บุคคล — group_id 0 คือครอบครัวตัวเอง ไม่สังกัดกลุ่ม
-- เบอร์โทรเป็นเลขสมมติที่โทรไม่ติด และไม่ใส่ id_card เพื่อไม่ให้ไปตรงกับเลขบัตรของคนจริง
-- sys, dia, bmi ปล่อยเป็นค่าเริ่มต้น แล้วคำนวณจากค่าที่วัดในส่วนท้ายไฟล์
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_bp_family` (`id`, `member_id`, `group_id`, `favorite`, `name`, `sex`, `height`, `id_card`, `address`, `phone`, `provinceID`, `province`, `zipcode`, `country`, `birthday`, `relation`, `chronic`, `smoking`, `alcohol`, `diabetes`, `notify_optin`, `consent_at`, `client_uuid`, `created_at`, `updated_at`) VALUES
-- ความดันก่อนสูง วัดทุกวัน 2 รอบ BMI แดง
(@family_id + 1, @member_id, 0, 1, 'สมชาย ใจดี', 'm', 170, NULL, '12 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000001', '40', 'ขอนแก่น', '40000', 'TH', '1968-03-15', 'self', NULL, 1, 0, 0, 1, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000001', NOW() - INTERVAL 150 DAY, NOW()),
-- ปกติ ยังไม่ได้วัดวันนี้ BMI เขียว
(@family_id + 2, @member_id, 0, 1, 'สมศรี ใจดี', 'f', 158, NULL, '12 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000002', '40', 'ขอนแก่น', '40000', 'TH', '1970-07-22', 'family', NULL, 0, 0, 0, 1, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000002', NOW() - INTERVAL 150 DAY, NOW()),
-- ความดันต่ำ BMI น้ำเงิน
(@family_id + 3, @member_id, 0, 0, 'มานี ใจดี', 'f', 160, NULL, '12 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000003', '40', 'ขอนแก่น', '40000', 'TH', '2002-11-02', 'family', NULL, 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000003', NOW() - INTERVAL 150 DAY, NOW()),
-- ส่งต่อ: ล่าสุด 186/112 แต่ค่าเฉลี่ย 7 วันแค่ก่อนสูง
(@family_id + 4, @member_id, @group_id + 1, 1, 'บุญมี ศรีสุข', 'm', 165, NULL, '8 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000004', '40', 'ขอนแก่น', '40000', 'TH', '1954-01-10', 'patient', 'ความดันโลหิตสูง', 0, 0, 0, 1, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000004', NOW() - INTERVAL 150 DAY, NOW()),
-- สูง มีเบาหวาน แนวโน้มลดลง
(@family_id + 5, @member_id, @group_id + 1, 0, 'คำแก้ว ศรีสุข', 'f', 155, NULL, '8 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000005', '40', 'ขอนแก่น', '40000', 'TH', '1958-05-30', 'patient', 'เบาหวาน, ความดันโลหิตสูง', 0, 0, 1, 1, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000005', NOW() - INTERVAL 150 DAY, NOW()),
-- ก่อนสูง ยังไม่ให้ความยินยอม PDPA
(@family_id + 6, @member_id, @group_id + 1, 0, 'ประยูร มั่นคง', 'm', 168, NULL, '21 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000006', '40', 'ขอนแก่น', '40000', 'TH', '1961-09-05', 'neighbor', NULL, 1, 1, 0, 0, NULL, '00000000-0000-4000-8000-020000000006', NOW() - INTERVAL 150 DAY, NOW()),
-- ปกติ วัดแล้วในรอบเยี่ยมวันนี้
(@family_id + 7, @member_id, @group_id + 1, 0, 'ทองใบ มั่นคง', 'f', 152, NULL, '21 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000007', '40', 'ขอนแก่น', '40000', 'TH', '1965-12-18', 'neighbor', NULL, 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000007', NOW() - INTERVAL 150 DAY, NOW()),
-- ปกติ แต่ไม่ได้วัดเกิน 30 วัน
(@family_id + 8, @member_id, @group_id + 1, 0, 'สำราญ พูนผล', 'm', 172, NULL, '35 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000008', '40', 'ขอนแก่น', '40000', 'TH', '1947-04-12', 'neighbor', 'ไขมันในเลือดสูง', 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000008', NOW() - INTERVAL 150 DAY, NOW()),
-- ยังไม่เคยวัด และยังไม่ให้ความยินยอม
(@family_id + 9, @member_id, @group_id + 1, 0, 'ลำดวน พูนผล', 'f', 0, NULL, '35 ม.3 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000009', '40', 'ขอนแก่น', '40000', 'TH', '1952-08-25', 'neighbor', NULL, 0, 0, 0, 0, NULL, '00000000-0000-4000-8000-020000000009', NOW() - INTERVAL 150 DAY, NOW()),
-- ส่งต่อเพราะตัวล่างอย่างเดียว: 148/112
(@family_id + 10, @member_id, @group_id + 2, 0, 'วิชัย แก้วมณี', 'm', 175, NULL, '5 ม.5 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000010', '40', 'ขอนแก่น', '40000', 'TH', '1971-02-14', 'patient', 'ความดันโลหิตสูง', 1, 1, 0, 1, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000010', NOW() - INTERVAL 150 DAY, NOW()),
-- ค่าขอบ 120/80 ยังเป็นปกติ BMI ส้ม
(@family_id + 11, @member_id, @group_id + 2, 0, 'นงลักษณ์ แก้วมณี', 'f', 165, NULL, '5 ม.5 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000011', '40', 'ขอนแก่น', '40000', 'TH', '1976-06-09', 'neighbor', NULL, 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000011', NOW() - INTERVAL 150 DAY, NOW()),
-- สูง และไม่ได้วัดเกิน 30 วัน
(@family_id + 12, @member_id, @group_id + 2, 0, 'สมบัติ ทองดี', 'm', 163, NULL, '17 ม.5 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000012', '40', 'ขอนแก่น', '40000', 'TH', '1943-10-01', 'patient', 'ความดันโลหิตสูง', 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000012', NOW() - INTERVAL 150 DAY, NOW()),
-- วัดล่าสุดเกิน 90 วัน แดชบอร์ด อสม. จึงใช้ค่าเฉลี่ยที่เก็บไว้
(@family_id + 13, @member_id, @group_id + 2, 0, 'บัวผัน ทองดี', 'f', 150, NULL, '17 ม.5 ต.ตัวอย่าง อ.เมืองขอนแก่น', '0000000013', '40', 'ขอนแก่น', '40000', 'TH', '1946-03-27', 'neighbor', NULL, 0, 0, 0, 0, NOW() - INTERVAL 150 DAY, '00000000-0000-4000-8000-020000000013', NOW() - INTERVAL 150 DAY, NOW());

-- ---------------------------------------------------------------------------
-- รอบเยี่ยมบ้าน — status 1 = ปิดรอบแล้ว, 0 = กำลังเยี่ยม (รอบวันนี้ของหมู่ 3 วัดไปแล้ว 2 จาก 6 คน)
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_bp_visit` (`id`, `member_id`, `group_id`, `visit_date`, `status`, `note`, `client_uuid`, `created_at`, `updated_at`) VALUES
(@visit_id + 1, @member_id, @group_id + 2, CURDATE() - INTERVAL 120 DAY, 1, 'เยี่ยมครบตามแผน', '00000000-0000-4000-8000-030000000001', TIMESTAMP(CURDATE() - INTERVAL 120 DAY, '08:30:00'), NOW()),
(@visit_id + 2, @member_id, @group_id + 2, CURDATE() - INTERVAL 60 DAY, 1, 'คุณบัวผันไปต่างจังหวัด ไม่ได้วัด', '00000000-0000-4000-8000-030000000002', TIMESTAMP(CURDATE() - INTERVAL 60 DAY, '08:30:00'), NOW()),
(@visit_id + 3, @member_id, @group_id + 1, CURDATE() - INTERVAL 45 DAY, 1, 'คุณลำดวนไม่สะดวก ขอเลื่อน', '00000000-0000-4000-8000-030000000003', TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '08:30:00'), NOW()),
(@visit_id + 4, @member_id, @group_id + 2, CURDATE() - INTERVAL 21 DAY, 1, NULL, '00000000-0000-4000-8000-030000000004', TIMESTAMP(CURDATE() - INTERVAL 21 DAY, '08:30:00'), NOW()),
(@visit_id + 5, @member_id, @group_id + 1, CURDATE() - INTERVAL 14 DAY, 1, 'คุณสำราญไม่อยู่บ้าน', '00000000-0000-4000-8000-030000000005', TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '08:30:00'), NOW()),
(@visit_id + 6, @member_id, @group_id + 1, CURDATE(), 0, NULL, '00000000-0000-4000-8000-030000000006', NOW(), NOW());

-- ---------------------------------------------------------------------------
-- ค่าที่วัด — หนึ่งแถวต่อการวัดหนึ่งครั้ง ตัวเลขความดันอยู่ใน bp_items
-- weight, height, temperature, waist เป็น 0 และ glucose, spo2 เป็น NULL = ไม่ได้วัด
-- updated_at ใช้เวลาตอนโหลด เพื่อให้เครื่องที่เคยซิงค์ไปแล้วดึงแถวเหล่านี้ไปด้วย
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_bp` (`id`, `member_id`, `family_id`, `recorder_id`, `visit_id`, `weight`, `height`, `temperature`, `waist`, `glucose`, `spo2`, `tag`, `note`, `create_date`, `client_uuid`, `updated_at`) VALUES
-- สมชาย ใจดี
(@bp_id + 1, @member_id, @family_id + 1, @member_id, 0, 78.5, 170, 0, 94, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 13 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000001', NOW()),
(@bp_id + 2, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000002', NOW()),
(@bp_id + 3, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 11 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000003', NOW()),
(@bp_id + 4, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000004', NOW()),
(@bp_id + 5, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000005', NOW()),
(@bp_id + 6, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000006', NOW()),
(@bp_id + 7, @member_id, @family_id + 1, @member_id, 0, 78, 170, 0, 94, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 7 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000007', NOW()),
(@bp_id + 8, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000008', NOW()),
(@bp_id + 9, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000009', NOW()),
(@bp_id + 10, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000010', NOW()),
(@bp_id + 11, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000011', NOW()),
(@bp_id + 12, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000012', NOW()),
(@bp_id + 13, @member_id, @family_id + 1, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '07:00:00'), '00000000-0000-4000-8000-040000000013', NOW()),
(@bp_id + 14, @member_id, @family_id + 1, @member_id, 0, 77.5, 170, 0, 94, NULL, NULL, '1', NULL, NOW(), '00000000-0000-4000-8000-040000000014', NOW()),
-- สมศรี ใจดี
(@bp_id + 15, @member_id, @family_id + 2, @member_id, 0, 55, 158, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 20 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000015', NOW()),
(@bp_id + 16, @member_id, @family_id + 2, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 12 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000016', NOW()),
(@bp_id + 17, @member_id, @family_id + 2, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 9 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000017', NOW()),
(@bp_id + 18, @member_id, @family_id + 2, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000018', NOW()),
(@bp_id + 19, @member_id, @family_id + 2, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000019', NOW()),
(@bp_id + 20, @member_id, @family_id + 2, @member_id, 0, 55, 158, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '19:30:00'), '00000000-0000-4000-8000-040000000020', NOW()),
-- มานี ใจดี
(@bp_id + 21, @member_id, @family_id + 3, @member_id, 0, 45.5, 160, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 25 DAY, '07:15:00'), '00000000-0000-4000-8000-040000000021', NOW()),
(@bp_id + 22, @member_id, @family_id + 3, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 10 DAY, '07:10:00'), '00000000-0000-4000-8000-040000000022', NOW()),
(@bp_id + 23, @member_id, @family_id + 3, @member_id, 0, 45, 160, 0, 0, NULL, NULL, '1', 'รู้สึกหน้ามืดเล็กน้อยตอนลุกจากเตียง', TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '07:20:00'), '00000000-0000-4000-8000-040000000023', NOW()),
-- บุญมี ศรีสุข
(@bp_id + 24, @member_id, @family_id + 4, @member_id, @visit_id + 3, 70, 165, 36.6, 92, NULL, 97, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '09:10:00'), '00000000-0000-4000-8000-040000000024', NOW()),
(@bp_id + 25, @member_id, @family_id + 4, @member_id, @visit_id + 5, 69.5, 165, 36.5, 92, NULL, 98, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '09:05:00'), '00000000-0000-4000-8000-040000000025', NOW()),
(@bp_id + 26, @member_id, @family_id + 4, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 6 DAY, '08:00:00'), '00000000-0000-4000-8000-040000000026', NOW()),
(@bp_id + 27, @member_id, @family_id + 4, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '08:00:00'), '00000000-0000-4000-8000-040000000027', NOW()),
(@bp_id + 28, @member_id, @family_id + 4, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 3 DAY, '08:00:00'), '00000000-0000-4000-8000-040000000028', NOW()),
(@bp_id + 29, @member_id, @family_id + 4, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 2 DAY, '08:00:00'), '00000000-0000-4000-8000-040000000029', NOW()),
(@bp_id + 30, @member_id, @family_id + 4, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '08:00:00'), '00000000-0000-4000-8000-040000000030', NOW()),
(@bp_id + 31, @member_id, @family_id + 4, @member_id, @visit_id + 6, 69, 165, 36.8, 91, NULL, 96, '5', 'เวียนศีรษะ ปวดท้ายทอย แนะนำให้ไป รพ.สต. วันนี้', NOW(), '00000000-0000-4000-8000-040000000031', NOW()),
-- คำแก้ว ศรีสุข
(@bp_id + 32, @member_id, @family_id + 5, @member_id, @visit_id + 3, 62, 155, 36.7, 88, 182, 97, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '09:30:00'), '00000000-0000-4000-8000-040000000032', NOW()),
(@bp_id + 33, @member_id, @family_id + 5, @member_id, @visit_id + 5, 61.5, 155, 36.5, 87, 175, 98, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '09:25:00'), '00000000-0000-4000-8000-040000000033', NOW()),
(@bp_id + 34, @member_id, @family_id + 5, @member_id, 0, 0, 0, 0, 0, 168, NULL, '1', NULL, TIMESTAMP(CURDATE() - INTERVAL 8 DAY, '07:40:00'), '00000000-0000-4000-8000-040000000034', NOW()),
(@bp_id + 35, @member_id, @family_id + 5, @member_id, 0, 0, 0, 0, 0, 154, NULL, '1', 'งดเค็มและกินยาสม่ำเสมอ', TIMESTAMP(CURDATE() - INTERVAL 4 DAY, '07:35:00'), '00000000-0000-4000-8000-040000000035', NOW()),
-- ประยูร มั่นคง
(@bp_id + 36, @member_id, @family_id + 6, @member_id, @visit_id + 3, 74, 168, 36.6, 95, NULL, NULL, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '10:00:00'), '00000000-0000-4000-8000-040000000036', NOW()),
(@bp_id + 37, @member_id, @family_id + 6, @member_id, @visit_id + 5, 74.5, 168, 36.7, 95, NULL, NULL, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '09:50:00'), '00000000-0000-4000-8000-040000000037', NOW()),
(@bp_id + 38, @member_id, @family_id + 6, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '2', NULL, TIMESTAMP(CURDATE() - INTERVAL 5 DAY, '18:10:00'), '00000000-0000-4000-8000-040000000038', NOW()),
-- ทองใบ มั่นคง
(@bp_id + 39, @member_id, @family_id + 7, @member_id, @visit_id + 3, 50, 152, 36.4, 0, NULL, 99, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '10:20:00'), '00000000-0000-4000-8000-040000000039', NOW()),
(@bp_id + 40, @member_id, @family_id + 7, @member_id, @visit_id + 5, 50, 152, 36.5, 0, NULL, 99, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 14 DAY, '10:15:00'), '00000000-0000-4000-8000-040000000040', NOW()),
(@bp_id + 41, @member_id, @family_id + 7, @member_id, @visit_id + 6, 49.5, 152, 36.4, 0, NULL, 99, '5', NULL, NOW(), '00000000-0000-4000-8000-040000000041', NOW()),
-- สำราญ พูนผล
(@bp_id + 42, @member_id, @family_id + 8, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '3', NULL, TIMESTAMP(CURDATE() - INTERVAL 75 DAY, '08:30:00'), '00000000-0000-4000-8000-040000000042', NOW()),
(@bp_id + 43, @member_id, @family_id + 8, @member_id, @visit_id + 3, 68, 172, 36.5, 86, NULL, 97, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 45 DAY, '10:40:00'), '00000000-0000-4000-8000-040000000043', NOW()),
-- วิชัย แก้วมณี
(@bp_id + 44, @member_id, @family_id + 10, @member_id, @visit_id + 2, 88, 175, 36.6, 102, NULL, 97, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 60 DAY, '09:10:00'), '00000000-0000-4000-8000-040000000044', NOW()),
(@bp_id + 45, @member_id, @family_id + 10, @member_id, @visit_id + 4, 87.5, 175, 36.7, 101, NULL, 97, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 21 DAY, '09:00:00'), '00000000-0000-4000-8000-040000000045', NOW()),
(@bp_id + 46, @member_id, @family_id + 10, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '3', 'นอนไม่หลับ ดื่มกาแฟหลายแก้ว', TIMESTAMP(CURDATE() - INTERVAL 1 DAY, '20:15:00'), '00000000-0000-4000-8000-040000000046', NOW()),
-- นงลักษณ์ แก้วมณี
(@bp_id + 47, @member_id, @family_id + 11, @member_id, @visit_id + 2, 65, 165, 36.5, 80, NULL, NULL, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 60 DAY, '09:40:00'), '00000000-0000-4000-8000-040000000047', NOW()),
(@bp_id + 48, @member_id, @family_id + 11, @member_id, @visit_id + 4, 65.5, 165, 36.6, 80, NULL, NULL, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 21 DAY, '09:30:00'), '00000000-0000-4000-8000-040000000048', NOW()),
(@bp_id + 49, @member_id, @family_id + 11, @member_id, 0, 0, 0, 0, 0, NULL, NULL, '4', 'เพิ่งเดินเร็ว 30 นาที', TIMESTAMP(CURDATE() - INTERVAL 7 DAY, '17:45:00'), '00000000-0000-4000-8000-040000000049', NOW()),
-- สมบัติ ทองดี
(@bp_id + 50, @member_id, @family_id + 12, @member_id, @visit_id + 1, 58, 163, 36.4, 0, NULL, 95, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 120 DAY, '10:00:00'), '00000000-0000-4000-8000-040000000050', NOW()),
(@bp_id + 51, @member_id, @family_id + 12, @member_id, @visit_id + 2, 57, 163, 36.5, 0, NULL, 95, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 60 DAY, '10:10:00'), '00000000-0000-4000-8000-040000000051', NOW()),
-- บัวผัน ทองดี
(@bp_id + 52, @member_id, @family_id + 13, @member_id, @visit_id + 1, 48, 150, 36.5, 0, NULL, 96, '5', NULL, TIMESTAMP(CURDATE() - INTERVAL 120 DAY, '10:30:00'), '00000000-0000-4000-8000-040000000052', NOW());

-- ---------------------------------------------------------------------------
-- ตัวเลขความดันของแต่ละการวัด — (bp_id, ครั้งที่, ตัวบน, ตัวล่าง, ชีพจร) วัดได้ครั้งละไม่เกิน 2 ค่า
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_bp_items` (`bp_id`, `index`, `sys`, `dia`, `pulse`) VALUES
(@bp_id + 1, 1, 128, 84, 74),
(@bp_id + 1, 2, 126, 82, 72),
(@bp_id + 2, 1, 132, 86, 76),
(@bp_id + 2, 2, 130, 84, 74),
(@bp_id + 3, 1, 126, 82, 72),
(@bp_id + 3, 2, 124, 80, 70),
(@bp_id + 4, 1, 130, 84, 75),
(@bp_id + 4, 2, 128, 82, 73),
(@bp_id + 5, 1, 134, 86, 78),
(@bp_id + 5, 2, 131, 85, 76),
(@bp_id + 6, 1, 127, 82, 73),
(@bp_id + 6, 2, 125, 81, 72),
(@bp_id + 7, 1, 129, 83, 74),
(@bp_id + 7, 2, 127, 82, 72),
(@bp_id + 8, 1, 131, 85, 75),
(@bp_id + 8, 2, 128, 83, 74),
(@bp_id + 9, 1, 125, 81, 71),
(@bp_id + 9, 2, 124, 80, 70),
(@bp_id + 10, 1, 128, 83, 73),
(@bp_id + 10, 2, 126, 81, 72),
(@bp_id + 11, 1, 133, 86, 77),
(@bp_id + 11, 2, 130, 84, 75),
(@bp_id + 12, 1, 127, 82, 72),
(@bp_id + 12, 2, 125, 81, 71),
(@bp_id + 13, 1, 131, 84, 75),
(@bp_id + 13, 2, 129, 83, 74),
(@bp_id + 14, 1, 134, 86, 76),
(@bp_id + 14, 2, 130, 84, 74),
(@bp_id + 15, 1, 118, 76, 72),
(@bp_id + 16, 1, 114, 72, 70),
(@bp_id + 17, 1, 116, 74, 68),
(@bp_id + 18, 1, 112, 70, 70),
(@bp_id + 19, 1, 118, 75, 74),
(@bp_id + 20, 1, 116, 74, 70),
(@bp_id + 21, 1, 90, 62, 78),
(@bp_id + 22, 1, 92, 60, 80),
(@bp_id + 23, 1, 88, 58, 82),
(@bp_id + 24, 1, 138, 86, 80),
(@bp_id + 25, 1, 134, 84, 78),
(@bp_id + 26, 1, 126, 78, 76),
(@bp_id + 27, 1, 128, 80, 74),
(@bp_id + 28, 1, 124, 78, 75),
(@bp_id + 29, 1, 130, 82, 78),
(@bp_id + 30, 1, 125, 79, 74),
(@bp_id + 31, 1, 186, 112, 96),
(@bp_id + 32, 1, 164, 100, 84),
(@bp_id + 33, 1, 158, 96, 82),
(@bp_id + 34, 1, 160, 98, 80),
(@bp_id + 35, 1, 152, 94, 78),
(@bp_id + 36, 1, 128, 82, 76),
(@bp_id + 37, 1, 132, 84, 78),
(@bp_id + 38, 1, 130, 82, 77),
(@bp_id + 39, 1, 118, 76, 72),
(@bp_id + 40, 1, 116, 74, 70),
(@bp_id + 41, 1, 114, 72, 70),
(@bp_id + 42, 1, 120, 78, 68),
(@bp_id + 43, 1, 118, 76, 66),
(@bp_id + 44, 1, 138, 88, 82),
(@bp_id + 45, 1, 142, 96, 84),
(@bp_id + 46, 1, 148, 112, 90),
(@bp_id + 47, 1, 118, 78, 74),
(@bp_id + 48, 1, 116, 76, 72),
(@bp_id + 49, 1, 120, 80, 88),
(@bp_id + 50, 1, 150, 92, 76),
(@bp_id + 51, 1, 158, 96, 78),
(@bp_id + 52, 1, 132, 84, 74);

-- ---------------------------------------------------------------------------
-- ค่าสรุปใน bp_family — แอปเขียนให้ตอนบันทึกผ่าน Bp\Calculator\Model::avg()
-- แต่ SQL ไม่ได้ผ่านโค้ดนั้นจึงต้องคำนวณเอง โดยใช้ค่าเดียวกับตอนบันทึกครั้งล่าสุดของแต่ละคน:
-- ค่าเฉลี่ยย้อน 7 วัน (ค่าเริ่มต้นของ bp_avg_days) นับจากวันที่วัดครั้งล่าสุด
-- และ BMI จากครั้งล่าสุดที่มีทั้งน้ำหนักและส่วนสูง (ไม่เคยชั่ง = NULL)
-- คนที่ไม่เคยวัดเลยไม่ถูกแก้ ค่าจึงยังเป็น 0 เหมือนในแอป
-- ---------------------------------------------------------------------------
UPDATE `{prefix}_bp_family` F
INNER JOIN (
  SELECT B.`family_id`, AVG(I.`sys`) AS `avg_sys`, AVG(I.`dia`) AS `avg_dia`
  FROM `{prefix}_bp` B
  INNER JOIN `{prefix}_bp_items` I ON I.`bp_id` = B.`id`
  INNER JOIN (
    SELECT `family_id`, MAX(DATE(`create_date`)) AS `last_day`
    FROM `{prefix}_bp`
    WHERE `member_id` = @member_id AND `deleted_at` IS NULL
    GROUP BY `family_id`
  ) L ON L.`family_id` = B.`family_id`
  WHERE B.`member_id` = @member_id
    AND B.`deleted_at` IS NULL
    AND DATE(B.`create_date`) >= L.`last_day` - INTERVAL 7 DAY
  GROUP BY B.`family_id`
) A ON A.`family_id` = F.`id`
SET F.`sys` = A.`avg_sys`,
    F.`dia` = A.`avg_dia`,
    F.`bmi` = (
      SELECT P.`weight` / ((P.`height` / 100) * (P.`height` / 100))
      FROM `{prefix}_bp` P
      WHERE P.`family_id` = F.`id`
        AND P.`member_id` = F.`member_id`
        AND P.`deleted_at` IS NULL
        AND P.`weight` > 0
        AND P.`height` > 0
      ORDER BY P.`create_date` DESC
      LIMIT 1
    )
WHERE F.`member_id` = @member_id AND F.`client_uuid` LIKE '00000000-0000-4000-8000-%';

COMMIT;
