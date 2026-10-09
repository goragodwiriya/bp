-- ---------------------------------------------------------------------------
-- modules/bp/install/database.sql — ตารางที่โมดูล bp เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- และห้ามเขียน CREATE TABLE ซ้ำไว้ในตัวปรับรุ่นอีกชุด
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_bp_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `village` varchar(150) DEFAULT NULL,
  `moo` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `note` text DEFAULT NULL,
  `client_uuid` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_uuid` (`member_id`,`client_uuid`),
  KEY `member_id` (`member_id`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `{prefix}_bp_family` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `favorite` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(150) NOT NULL,
  `sex` varchar(1) DEFAULT NULL,
  `height` float DEFAULT 0,
  `id_card` varchar(13) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `provinceID` varchar(3) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `country` varchar(2) DEFAULT 'TH',
  `birthday` date DEFAULT NULL,
  `relation` varchar(50) DEFAULT NULL,
  `chronic` varchar(255) DEFAULT NULL,
  `smoking` tinyint(1) NOT NULL DEFAULT 0,
  `alcohol` tinyint(1) NOT NULL DEFAULT 0,
  `diabetes` tinyint(1) NOT NULL DEFAULT 0,
  `notify_optin` tinyint(1) NOT NULL DEFAULT 0,
  `consent_at` datetime DEFAULT NULL,
  `sys` double DEFAULT 0,
  `dia` double DEFAULT 0,
  `bmi` double DEFAULT 0,
  `client_uuid` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_uuid` (`member_id`,`client_uuid`),
  KEY `member_id` (`member_id`,`deleted_at`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `{prefix}_bp_visit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL DEFAULT 0,
  `visit_date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `note` text DEFAULT NULL,
  `client_uuid` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_uuid` (`member_id`,`client_uuid`),
  KEY `member_id` (`member_id`,`visit_date`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `{prefix}_bp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `recorder_id` int(11) NOT NULL DEFAULT 0,
  `visit_id` int(11) NOT NULL DEFAULT 0,
  `weight` float NOT NULL DEFAULT 0,
  `height` float NOT NULL DEFAULT 0,
  `temperature` float NOT NULL DEFAULT 0,
  `waist` float NOT NULL DEFAULT 0,
  `glucose` float DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `tag` varchar(10) NOT NULL DEFAULT '',
  `note` varchar(255) DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `client_uuid` varchar(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_uuid` (`member_id`,`client_uuid`),
  KEY `family_id` (`family_id`,`create_date`),
  KEY `member_id` (`member_id`,`create_date`),
  KEY `visit_id` (`visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `{prefix}_bp_items` (
  `bp_id` int(11) NOT NULL,
  `index` tinyint(4) NOT NULL DEFAULT 1,
  `sys` int(11) DEFAULT NULL,
  `dia` int(11) DEFAULT NULL,
  `pulse` int(11) DEFAULT NULL,
  PRIMARY KEY (`bp_id`,`index`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `{prefix}_bp_category` (
  `member_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `category_id` varchar(10) NOT NULL DEFAULT '0',
  `language` varchar(2) NOT NULL DEFAULT '',
  `topic` varchar(150) NOT NULL,
  `color` varchar(16) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`member_id`,`type`,`category_id`,`language`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
