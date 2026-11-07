<?php
/**
 * modules/bp/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล bp
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ก่อนมีไฟล์นี้ งานทั้งหมดนี้ฝังอยู่ใน install/upgrade2.php ของโปรเจ็ค โดยมี
 * CREATE TABLE เขียนซ้ำไว้อีกชุดหนึ่ง นิยามสองชุดคือสิ่งที่วันหนึ่งจะต่างกันเงียบ ๆ
 * แล้วไซต์ที่ติดตั้งใหม่กับไซต์ที่ปรับรุ่นจะได้ตารางคนละหน้าตาโดยไม่มีอะไรฟ้อง
 * ตอนนี้นิยามอยู่ที่ modules/bp/install/database.sql ที่เดียว
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

// =========================================================
// bp — โมดูลบันทึกความดันโลหิต / สุขภาพ
//
// รองรับ 2 กรณี
//   ก) ระบบ adminframework ที่ยังไม่เคยมีโมดูล bp -> สร้างตารางใหม่ทั้งหมด
//   ข) ระบบ bp รุ่นเดิม (Kotchasan 6.x) -> ย้าย {prefix}_family และ
//      หมวดหมู่ type='tag' ออกจากตารางกลาง แล้วเติมคอลัมน์ที่ขาด
//
// ทุกขั้นตอน idempotent และผลลัพธ์ต้องตรงกับ install/database.sql
// ตรวจด้วย .harness/bin/diff_schema.php
// =========================================================
$table_bp = $prefix.'_bp';
$table_bp_items = $prefix.'_bp_items';
$table_bp_family = $prefix.'_bp_family';
$table_bp_category = $prefix.'_bp_category';
$table_bp_group = $prefix.'_bp_group';
$table_bp_visit = $prefix.'_bp_visit';
$table_family_old = $prefix.'_family';

// ---- bp_group : ตารางใหม่ ไม่มีของเดิมให้ย้าย ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp_group)) {
    $content[] = '<li class="correct">bp_group: สร้างตารางใหม่</li>';
} elseif (convertToUtf8mb4($db, $table_bp_group)) {
    $content[] = '<li class="correct">bp_group: แปลงเป็น utf8mb4</li>';
}

// ---- bp_visit : ตารางใหม่ ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp_visit)) {
    $content[] = '<li class="correct">bp_visit: สร้างตารางใหม่</li>';
} elseif (convertToUtf8mb4($db, $table_bp_visit)) {
    $content[] = '<li class="correct">bp_visit: แปลงเป็น utf8mb4</li>';
}

// ---- bp_family : ตารางใหม่ + ย้ายข้อมูลจาก {prefix}_family ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp_family)) {
    $content[] = '<li class="correct">bp_family: สร้างตารางใหม่</li>';
} elseif (convertToUtf8mb4($db, $table_bp_family)) {
    $content[] = '<li class="correct">bp_family: แปลงเป็น utf8mb4</li>';
}

// ย้ายข้อมูล — ทำนอกบล็อก CREATE เพื่อให้ปลอดภัยแม้รอบก่อนหยุดกลางคัน
if ($db->tableExists($table_family_old)) {
    $db->query("INSERT IGNORE INTO `$table_bp_family`
        (`id`,`member_id`,`group_id`,`favorite`,`name`,`sex`,`height`,`id_card`,
         `address`,`phone`,`provinceID`,`province`,`zipcode`,`country`,`birthday`,
         `sys`,`dia`,`bmi`,`client_uuid`,`created_at`,`updated_at`)
        SELECT `id`,`member_id`,0,IFNULL(`favorite`,0),`name`,`sex`,IFNULL(`height`,0),`id_card`,
         `address`,`phone`,`provinceID`,`province`,`zipcode`,IFNULL(`country`,'TH'),`birthday`,
         `sys`,`dia`,`bmi`,UUID(),`create_date`,IFNULL(`create_date`, NOW())
        FROM `$table_family_old`");
    $moved = $db->customQuery("SELECT COUNT(*) AS c FROM `$table_bp_family`");
    // แจ้งตัวนับแถวว่าแถวถูก "ย้าย" ไม่ใช่ "หาย" — ไม่งั้นตัวปรับรุ่นจะรายงานว่า
    // ข้อมูลหายแล้วตัดสินว่าล้มเหลว ทั้งที่แถวอยู่ครบที่ปลายทาง
    noteRowsMoved($table_family_old, $table_bp_family, empty($moved) ? 0 : (int) $moved[0]->c);
    // เก็บตารางเดิมไว้เป็น _bak ไม่ลบทิ้ง เผื่อต้องกู้คืน
    $bak = $table_family_old.'_bak';
    if ($db->tableExists($bak)) {
        $bak = $bak.'_'.date('YmdHis');
    }
    $db->query("RENAME TABLE `$table_family_old` TO `$bak`");
    $content[] = '<li class="correct">bp_family: ย้ายข้อมูลจาก '.$table_family_old.' ('.$moved[0]->c.' แถว) และเก็บตารางเดิมไว้เป็น '.$bak.'</li>';
}

// ---- bp : ตารางหลัก (ชื่อเดิม ปรับโครงสร้างในที่) ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp)) {
    $content[] = '<li class="correct">bp: สร้างตารางใหม่</li>';
} else {
    // เพิ่มคอลัมน์ใหม่ตามลำดับของ database.sql (ใช้ AFTER คุมตำแหน่ง)
    $added = [];
    if (addColumn($db, $table_bp, 'recorder_id', 'INT(11) NOT NULL DEFAULT 0', 'family_id')) {
        $added[] = 'recorder_id';
    }
    if (addColumn($db, $table_bp, 'visit_id', 'INT(11) NOT NULL DEFAULT 0', 'recorder_id')) {
        $added[] = 'visit_id';
    }
    if (addColumn($db, $table_bp, 'glucose', 'FLOAT NULL DEFAULT NULL', 'waist')) {
        $added[] = 'glucose';
    }
    if (addColumn($db, $table_bp, 'spo2', 'INT(11) NULL DEFAULT NULL', 'glucose')) {
        $added[] = 'spo2';
    }
    if (addColumn($db, $table_bp, 'note', 'VARCHAR(255) NULL DEFAULT NULL', 'tag')) {
        $added[] = 'note';
    }
    if (addColumn($db, $table_bp, 'client_uuid', 'VARCHAR(36) NULL DEFAULT NULL', 'create_date')) {
        $added[] = 'client_uuid';
    }
    if (addColumn($db, $table_bp, 'updated_at', 'DATETIME NULL DEFAULT NULL', 'client_uuid')) {
        $added[] = 'updated_at';
    }
    if (addColumn($db, $table_bp, 'deleted_at', 'DATETIME NULL DEFAULT NULL', 'updated_at')) {
        $added[] = 'deleted_at';
    }
    if (!empty($added)) {
        $content[] = '<li class="correct">bp: เพิ่ม '.implode(', ', $added).'</li>';
    }
    // ค่า DEFAULT ของคอลัมน์ตัวเลข — isColumnType() ไม่ดู DEFAULT ให้ ต้องใช้ columnInfo()
    foreach (['weight', 'height', 'temperature', 'waist'] as $_col) {
        $_info = columnInfo($db, $table_bp, $_col);
        if ($_info && $_info->Default === null) {
            $db->query("ALTER TABLE `$table_bp` MODIFY `$_col` FLOAT NOT NULL DEFAULT 0");
            $content[] = '<li class="correct">bp: '.$_col.' เพิ่ม DEFAULT 0</li>';
        }
    }
    $_info = columnInfo($db, $table_bp, 'tag');
    if ($_info && $_info->Default === null) {
        $db->query("ALTER TABLE `$table_bp` MODIFY `tag` VARCHAR(10) NOT NULL DEFAULT ''");
        $content[] = '<li class="correct">bp: tag เพิ่ม DEFAULT \'\'</li>';
    }
    if (!$db->indexExists($table_bp, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_bp` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">bp: เพิ่ม PRIMARY KEY</li>';
    }
    if (!isAutoIncrement($db, $table_bp, 'id')) {
        $db->query("ALTER TABLE `$table_bp` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        $content[] = '<li class="correct">bp: id เป็น AUTO_INCREMENT</li>';
    }
    // index ของเดิมเป็นคอลัมน์เดียว ของใหม่รวม create_date เพื่อให้ช่วงวันที่เร็วขึ้น
    foreach (['family_id' => '`family_id`,`create_date`', 'member_id' => '`member_id`,`create_date`'] as $_idx => $_cols) {
        $_cur = $db->customQuery(
            "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_bp' AND INDEX_NAME = '$_idx'"
        );
        $_want = str_replace('`', '', $_cols);
        if (empty($_cur) || $_cur[0]->cols !== $_want) {
            if ($db->indexExists($table_bp, $_idx)) {
                $db->query("ALTER TABLE `$table_bp` DROP INDEX `$_idx`");
            }
            $db->query("ALTER TABLE `$table_bp` ADD INDEX `$_idx` ($_cols)");
            $content[] = '<li class="correct">bp: index '.$_idx.' รวม create_date</li>';
        }
    }
    if (!$db->indexExists($table_bp, 'visit_id')) {
        $db->query("ALTER TABLE `$table_bp` ADD INDEX `visit_id` (`visit_id`)");
        $content[] = '<li class="correct">bp: เพิ่ม index visit_id</li>';
    }
    if (convertToUtf8mb4($db, $table_bp)) {
        $content[] = '<li class="correct">bp: แปลงเป็น utf8mb4</li>';
    }
}

// backfill client_uuid / updated_at แล้วค่อยใส่ UNIQUE
// (ต้องมีค่าครบก่อน มิฉะนั้น UNIQUE จะชนกันเองที่ค่า NULL ซ้ำไม่ได้ในบางเวอร์ชัน)
if ($db->fieldExists($table_bp, 'client_uuid')) {
    $db->query("UPDATE `$table_bp` SET `client_uuid` = UUID() WHERE `client_uuid` IS NULL OR `client_uuid` = ''");
    $db->query("UPDATE `$table_bp` SET `updated_at` = `create_date` WHERE `updated_at` IS NULL");
    if (!$db->indexExists($table_bp, 'client_uuid')) {
        $db->query("ALTER TABLE `$table_bp` ADD UNIQUE `client_uuid` (`member_id`,`client_uuid`)");
        $content[] = '<li class="correct">bp: เพิ่ม UNIQUE (member_id, client_uuid)</li>';
    }
}
if ($db->tableExists($table_bp_family) && $db->fieldExists($table_bp_family, 'client_uuid')) {
    $db->query("UPDATE `$table_bp_family` SET `client_uuid` = UUID() WHERE `client_uuid` IS NULL OR `client_uuid` = ''");
    $db->query("UPDATE `$table_bp_family` SET `updated_at` = IFNULL(`created_at`, NOW()) WHERE `updated_at` IS NULL");
}

// ---- bp_items ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp_items)) {
    $content[] = '<li class="correct">bp_items: สร้างตารางใหม่</li>';
} else {
    if (!$db->indexExists($table_bp_items, 'PRIMARY')) {
        // ลบแถวซ้ำก่อน มิฉะนั้นการเพิ่ม PRIMARY KEY จะล้ม
        $db->query("DELETE t1 FROM `$table_bp_items` t1
            INNER JOIN `$table_bp_items` t2
            WHERE t1.`bp_id` = t2.`bp_id` AND t1.`index` = t2.`index`
              AND t1.`sys` <=> t2.`sys` AND t1.`dia` <=> t2.`dia` AND t1.`pulse` <=> t2.`pulse`
              AND t1.`bp_id` IS NOT NULL LIMIT 0");
        $db->query("ALTER TABLE `$table_bp_items` ADD PRIMARY KEY (`bp_id`,`index`) USING BTREE");
        $content[] = '<li class="correct">bp_items: เพิ่ม PRIMARY KEY</li>';
    }
    if (convertToUtf8mb4($db, $table_bp_items)) {
        $content[] = '<li class="correct">bp_items: แปลงเป็น utf8mb4</li>';
    }
}

// ---- bp_category : ย้าย type='tag' ออกจากตารางหมวดหมู่กลาง ----
// นิยามตารางอยู่ที่ modules/bp/install/database.sql ที่เดียว
if (ensureTable($db, $prefix, $table_bp_category)) {
    $content[] = '<li class="correct">bp_category: สร้างตารางใหม่</li>';
} elseif (convertToUtf8mb4($db, $table_bp_category)) {
    $content[] = '<li class="correct">bp_category: แปลงเป็น utf8mb4</li>';
}

// ตารางหมวดหมู่กลางของ adminframework ไม่มีคอลัมน์ member_id และ
// \Gcms\Category::save() ก็ insert โดยไม่ส่งค่านี้ ถ้าปล่อยคอลัมน์ NOT NULL
// ที่ไม่มี default ค้างไว้ การเพิ่มหมวดหมู่ของระบบกลางจะ error ทันทีใน strict mode
if ($db->fieldExists($table_category, 'member_id')) {
    $db->query("INSERT IGNORE INTO `$table_bp_category`
        (`member_id`,`type`,`category_id`,`language`,`topic`,`is_active`)
        SELECT `member_id`,`type`,`category_id`,IFNULL(`language`,''),`topic`,
               ".($db->fieldExists($table_category, 'is_active') ? '`is_active`' : '1')."
        FROM `$table_category` WHERE `type` = 'tag'");
    $moved = $db->customQuery("SELECT COUNT(*) AS c FROM `$table_bp_category`");
    noteRowsMoved($table_category, $table_bp_category, empty($moved) ? 0 : (int) $moved[0]->c);
    $db->query("DELETE FROM `$table_category` WHERE `type` = 'tag'");
    if ($db->indexExists($table_category, 'member_id')) {
        $db->query("ALTER TABLE `$table_category` DROP INDEX `member_id`");
    }
    $db->query("ALTER TABLE `$table_category` DROP COLUMN `member_id`");
    $content[] = '<li class="correct">bp_category: ย้ายหมวดหมู่ tag ('.$moved[0]->c.' แถว) ออกจาก '.$table_category.' และลบคอลัมน์ member_id</li>';
}

$content[] = '<li class="correct">bp อัปเกรดสำเร็จ</li>';

// ค่ากำหนดของโมดูล bp — เติมให้ระบบเดิมที่ยังไม่มีคีย์เหล่านี้
foreach ([
    'bp_sys_hight' => 140,
    'bp_dia_hight' => 90,
    'bp_sys_max' => 120,
    'bp_dia_max' => 80,
    'bp_sys_min' => 90,
    'bp_dia_min' => 60,
    'bp_referral_sys' => 180,
    'bp_referral_dia' => 110,
    'bp_avg_days' => 7,
    'bp_sync_enabled' => true
] as $_key => $_default) {
    if (!isset($config[$_key])) {
        $config[$_key] = $_default;
    }
}
