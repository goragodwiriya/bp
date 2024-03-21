-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 06, 2023 at 12:36 PM
-- Server version: 10.2.44-MariaDB-log
-- PHP Version: 7.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Database: `bp_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_bp`
--

CREATE TABLE `{prefix}_bp` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `weight` float NOT NULL,
  `height` float NOT NULL,
  `temperature` float NOT NULL,
  `waist` float NOT NULL,
  `tag` varchar(10) NOT NULL,
  `create_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_bp_items`
--

CREATE TABLE `{prefix}_bp_items` (
  `bp_id` int(11) NOT NULL,
  `index` tinyint(4) NOT NULL DEFAULT 1,
  `sys` int(11) DEFAULT NULL,
  `dia` int(11) DEFAULT NULL,
  `pulse` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_category`
--

CREATE TABLE `{prefix}_category` (
  `member_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `category_id` varchar(10) NOT NULL DEFAULT '0',
  `topic` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_family`
--

CREATE TABLE `{prefix}_family` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `favorite` tinyint(1) DEFAULT 0,
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
  `create_date` datetime DEFAULT NULL,
  `sys` double DEFAULT 0,
  `dia` double DEFAULT 0,
  `bmi` double DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_language`
--

CREATE TABLE `{prefix}_language` (
  `id` int(11) NOT NULL,
  `key` text NOT NULL,
  `type` varchar(5) NOT NULL,
  `owner` varchar(20) NOT NULL,
  `js` tinyint(1) NOT NULL,
  `th` text DEFAULT NULL,
  `en` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_logs`
--

CREATE TABLE `{prefix}_logs` (
  `id` int(11) NOT NULL,
  `src_id` int(11) NOT NULL,
  `module` varchar(20) NOT NULL,
  `action` varchar(20) NOT NULL,
  `create_date` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `topic` text NOT NULL,
  `datas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_user`
--

CREATE TABLE `{prefix}_user` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `salt` varchar(32) NOT NULL,
  `password` varchar(50) NOT NULL,
  `token` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 0,
  `permission` text NOT NULL,
  `name` varchar(150) NOT NULL,
  `sex` varchar(1) DEFAULT NULL,
  `id_card` varchar(13) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `provinceID` varchar(3) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `country` varchar(2) DEFAULT 'TH',
  `create_date` datetime DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `social` tinyint(1) DEFAULT 0,
  `line_uid` varchar(33) DEFAULT NULL,
  `activatecode` varchar(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `{prefix}_user_meta`
--

CREATE TABLE `{prefix}_user_meta` (
  `value` varchar(10) NOT NULL,
  `name` varchar(10) NOT NULL,
  `member_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Indexes for table `{prefix}_bp`
--
ALTER TABLE `{prefix}_bp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `family_id` (`family_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `{prefix}_bp_items`
--
ALTER TABLE `{prefix}_bp_items`
  ADD PRIMARY KEY (`bp_id`,`index`) USING BTREE;

--
-- Indexes for table `{prefix}_category`
--
ALTER TABLE `{prefix}_category`
  ADD KEY `type` (`type`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `{prefix}_family`
--
ALTER TABLE `{prefix}_family`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `{prefix}_logs`
--
ALTER TABLE `{prefix}_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `src_id` (`src_id`),
  ADD KEY `module` (`module`),
  ADD KEY `action` (`action`);

--
-- Indexes for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `line_uid` (`line_uid`),
  ADD KEY `username` (`username`),
  ADD KEY `token` (`token`),
  ADD KEY `phone` (`phone`),
  ADD KEY `id_card` (`id_card`),
  ADD KEY `activatecode` (`activatecode`);

--
-- Indexes for table `{prefix}_user_meta`
--
ALTER TABLE `{prefix}_user_meta`
  ADD KEY `member_id` (`member_id`,`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `{prefix}_bp`
--
ALTER TABLE `{prefix}_bp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_family`
--
ALTER TABLE `{prefix}_family`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_language`
--
ALTER TABLE `{prefix}_language`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_logs`
--
ALTER TABLE `{prefix}_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `{prefix}_user`
--
ALTER TABLE `{prefix}_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
