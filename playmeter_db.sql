-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 09, 2026 at 12:13 PM
-- Server version: 8.0.39
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `playmeter_db`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_sessions_view`
-- (See below for the actual view)
--
CREATE TABLE `active_sessions_view` (
`amount_paid` decimal(10,2)
,`arduino_id` varchar(50)
,`arduino_unit_id` int
,`created_at` timestamp
,`customer_code` varchar(50)
,`customer_id` int
,`customer_name` varchar(100)
,`duration_minutes` decimal(10,2)
,`end_time` timestamp
,`id` int
,`machine_id` int
,`machine_name` varchar(100)
,`payment_status` enum('pending','paid','cancelled')
,`qr_scanned` varchar(255)
,`rate_per_minute` decimal(10,2)
,`session_code` varchar(50)
,`start_time` timestamp
,`total_cost` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `arduino_commands`
--

CREATE TABLE `arduino_commands` (
  `id` int NOT NULL,
  `arduino_unit_id` int DEFAULT NULL,
  `command` varchar(50) DEFAULT NULL,
  `parameters` text,
  `status` enum('pending','sent','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `executed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `arduino_commands`
--

INSERT INTO `arduino_commands` (`id`, `arduino_unit_id`, `command`, `parameters`, `status`, `created_at`, `executed_at`) VALUES
(1, 3, 'UPDATE_RATE', '', 'pending', '2026-03-02 22:50:01', NULL),
(2, 3, 'POWER_OFF', '', 'pending', '2026-03-02 22:50:24', NULL),
(3, 3, 'POWER_OFF', '', 'pending', '2026-03-02 22:50:34', NULL),
(4, 3, 'POWER_ON', '', 'pending', '2026-05-06 11:29:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `arduino_units`
--

CREATE TABLE `arduino_units` (
  `id` int NOT NULL,
  `unit_id` varchar(50) NOT NULL,
  `machine_id` int DEFAULT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `firmware_version` varchar(20) DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `arduino_units`
--

INSERT INTO `arduino_units` (`id`, `unit_id`, `machine_id`, `status`, `firmware_version`, `last_seen`, `ip_address`, `created_at`) VALUES
(1, 'ARDUINO_001', NULL, 'active', '1.0.0', '2026-03-02 20:52:13', NULL, '2026-03-02 20:52:13'),
(2, 'ARDUINO_002', NULL, 'active', '1.0.0', '2026-03-02 20:52:13', NULL, '2026-03-02 20:52:13'),
(3, 'ARDUINO_003', 3, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(4, 'ARDUINO_004', 4, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(5, 'ARDUINO_005', 5, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(6, 'ARDUINO_006', 6, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(7, 'ARDUINO_007', 7, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(8, 'ARDUINO_008', 8, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(9, 'ARDUINO_009', 9, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(10, 'ARDUINO_010', 10, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(11, 'ARDUINO_011', 11, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(12, 'ARDUINO_012', 12, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(13, 'ARDUINO_013', 13, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(14, 'ARDUINO_014', 14, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29'),
(15, 'ARDUINO_015', 15, 'active', '1.0.0', '2026-03-02 22:37:29', NULL, '2026-03-02 22:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `qr_code` text,
  `balance` decimal(10,2) DEFAULT '0.00',
  `total_visits` int DEFAULT '0',
  `total_spent` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `full_name`, `phone_number`, `email`, `qr_code`, `balance`, `total_visits`, `total_spent`, `created_at`, `updated_at`) VALUES
(1, 'CUST001', 'John Doe', '0712345678', 'john@example.com', 'PLAYMETER:CUST001', 0.00, 3, -330.00, '2026-03-02 20:52:13', '2026-03-02 22:30:30'),
(2, 'CUST002', 'Jane Smith', '0723456789', 'jane@example.com', NULL, 0.00, 2, -211.33, '2026-03-02 20:52:13', '2026-03-02 22:30:30'),
(3, 'CUST003', 'Bob Johnson', '0734567890', NULL, 'PLAYMETER:CUST003', 0.00, 0, 0.00, '2026-03-02 21:51:42', '2026-03-03 13:02:07'),
(6, 'CUST_1772490261_461', 'Scripter Cryptoking', '0799718653', 'scriptercyptoking@gmail.com', 'PLAYMETER:CUST_1772490261_461', 0.00, 0, 0.00, '2026-03-02 22:24:21', '2026-03-02 22:24:21'),
(7, 'CUST004', 'Alice Brown', '0745678901', 'alice@email.com', 'PLAYMETER:CUST004', 0.00, 55, 15200.00, '2026-03-02 22:37:29', '2026-03-03 13:02:12'),
(8, 'CUST005', 'Charlie Wilson', '0756789012', 'charlie@email.com', 'PLAYMETER:CUST005', 0.00, 18, 4300.00, '2026-03-02 22:37:29', '2026-03-03 13:02:18'),
(9, 'CUST006', 'Diana Prince', '0767890123', 'diana@email.com', 'PLAYMETER:CUST006', 0.00, 62, 18400.00, '2026-03-02 22:37:29', '2026-03-03 13:02:20'),
(10, 'CUST007', 'Edward Nygma', '0778901234', 'edward@email.com', 'PLAYMETER:CUST007', 0.00, 12, 2100.00, '2026-03-02 22:37:29', '2026-03-03 13:02:26'),
(11, 'CUST008', 'Fiona Glen', '0789012345', 'fiona@email.com', 'PLAYMETER:CUST008', 0.00, 38, 9800.00, '2026-03-02 22:37:29', '2026-03-03 13:02:42'),
(12, 'CUST009', 'George Costanza', '0790123456', 'george@email.com', 'PLAYMETER:CUST009', 0.00, 8, 1500.00, '2026-03-02 22:37:29', '2026-03-03 13:02:31'),
(13, 'CUST010', 'Hannah Abbott', '0701234567', 'hannah@email.com', NULL, 0.00, 72, 21300.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29'),
(14, 'CUST011', 'Ian Malcolm', '0712345670', 'ian@email.com', NULL, 0.00, 25, 6700.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29'),
(15, 'CUST012', 'Julia Roberts', '0723456780', 'julia@email.com', NULL, 0.00, 41, 11200.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29'),
(16, 'CUST013', 'Kevin Hart', '0734567891', 'kevin@email.com', NULL, 0.00, 15, 3800.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29'),
(17, 'CUST014', 'Laura Croft', '0745678902', 'laura@email.com', NULL, 0.00, 85, 25600.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29'),
(18, 'CUST015', 'Mike Tyson', '0756789013', 'mike@email.com', NULL, 0.00, 5, 800.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29');

-- --------------------------------------------------------

--
-- Table structure for table `machines`
--

CREATE TABLE `machines` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `machine_type` varchar(50) DEFAULT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `price_per_play` decimal(10,2) DEFAULT '1.00',
  `total_plays` int DEFAULT '0',
  `total_revenue` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `arduino_unit_id` int DEFAULT NULL,
  `current_session_id` int DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `machines`
--

INSERT INTO `machines` (`id`, `name`, `machine_type`, `status`, `price_per_play`, `total_plays`, `total_revenue`, `created_at`, `updated_at`, `arduino_unit_id`, `current_session_id`, `is_online`) VALUES
(1, 'Street Fighter VI', 'Arcade', 'active', 2.50, 1, 2.50, '2026-03-02 18:02:28', '2026-03-02 22:24:56', 1, NULL, 1),
(2, 'Pinball Wizard', 'Pinball', 'active', 1.50, 0, 0.00, '2026-03-02 18:02:28', '2026-03-02 20:52:13', 2, NULL, 1),
(3, 'Air Hockey Pro', 'Sports', 'active', 2.00, 5, 10.00, '2026-03-02 18:02:28', '2026-03-02 22:35:38', NULL, NULL, 0),
(4, 'Basketball Fever', 'Sports', 'active', 1.75, 0, 0.00, '2026-03-02 18:02:28', '2026-03-02 18:02:28', NULL, NULL, 0),
(5, 'Racing Simulator', 'Racing', 'maintenance', 3.00, 0, 0.00, '2026-03-02 18:02:28', '2026-03-02 21:27:47', NULL, NULL, 0),
(6, 'Street Fighter VI', 'Arcade', 'active', 2.50, 1, 2.50, '2026-03-02 21:51:42', '2026-05-06 11:28:38', NULL, NULL, 0),
(7, 'Pinball Wizard', 'Pinball', 'active', 1.50, 0, 0.00, '2026-03-02 21:51:42', '2026-03-02 21:51:42', NULL, NULL, 0),
(8, 'Air Hockey Pro', 'Sports', 'active', 2.00, 0, 0.00, '2026-03-02 21:51:42', '2026-03-02 21:51:42', NULL, NULL, 0),
(9, 'Street Fighter VI', 'Arcade', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(10, 'Pinball Wizard', 'Pinball', 'active', 1.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(11, 'Air Hockey Pro', 'Sports', 'active', 2.00, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(12, 'Basketball Fever', 'Sports', 'active', 1.75, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(13, 'Racing Simulator', 'Racing', 'active', 3.00, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(14, 'Mortal Kombat 11', 'Arcade', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(15, 'Terminator Salvation', 'Pinball', 'active', 1.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(16, 'Foozball Champion', 'Sports', 'active', 2.00, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(17, 'Dance Dance Revolution', 'Rhythm', 'active', 2.25, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(18, 'Time Crisis 5', 'Shooter', 'active', 2.75, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(19, 'House of the Dead', 'Shooter', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(20, 'Mario Kart Arcade', 'Racing', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(21, 'Tekken 7', 'Arcade', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(22, 'Jurassic Park Pinball', 'Pinball', 'active', 1.75, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0),
(23, 'Sega Rally', 'Racing', 'active', 2.50, 0, 0.00, '2026-03-02 22:37:29', '2026-03-02 22:37:29', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int NOT NULL,
  `machine_id` int DEFAULT NULL,
  `issue_description` text,
  `resolved` tinyint(1) DEFAULT '0',
  `reported_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`id`, `machine_id`, `issue_description`, `resolved`, `reported_date`, `resolved_date`) VALUES
(1, 5, 'Overheating', 1, '2026-03-02 21:27:47', '2026-03-02 21:27:53');

-- --------------------------------------------------------

--
-- Table structure for table `plays`
--

CREATE TABLE `plays` (
  `id` int NOT NULL,
  `machine_id` int DEFAULT NULL,
  `customer_id` int DEFAULT NULL,
  `player_name` varchar(100) DEFAULT NULL,
  `plays_count` int DEFAULT '1',
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('cash','card','token') DEFAULT 'cash',
  `play_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `plays`
--

INSERT INTO `plays` (`id`, `machine_id`, `customer_id`, `player_name`, `plays_count`, `amount_paid`, `payment_method`, `play_date`) VALUES
(1, 1, NULL, 'John Doe', 3, 7.50, 'card', '2026-03-02 18:02:28'),
(2, 2, NULL, 'Jane Smith', 2, 3.00, 'cash', '2026-03-02 18:02:28'),
(3, 3, NULL, 'Mike Johnson', 1, 2.00, 'token', '2026-03-02 18:02:28'),
(4, 1, NULL, 'Alice Brown', 4, 10.00, 'card', '2026-03-02 18:02:28'),
(5, 4, NULL, 'Bob Wilson', 2, 3.50, 'cash', '2026-03-02 18:02:28'),
(6, 5, NULL, 'Carol Davis', 1, 3.00, 'card', '2026-03-02 18:02:28'),
(7, 1, 6, 'Scripter Cyptoking', 1, 2.50, 'cash', '2026-03-02 22:24:56'),
(8, 3, 6, 'Scripter Cyptoking', 5, 10.00, 'cash', '2026-03-02 22:35:38'),
(9, 6, 16, 'Kevin', 1, 2.50, 'cash', '2026-05-06 11:28:38');

-- --------------------------------------------------------

--
-- Table structure for table `rates`
--

CREATE TABLE `rates` (
  `id` int NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `rate_per_minute` decimal(10,2) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rates`
--

INSERT INTO `rates` (`id`, `name`, `rate_per_minute`, `effective_from`, `effective_to`, `is_active`, `created_at`) VALUES
(1, 'Standard Rate', 2.00, '2026-03-02', NULL, 1, '2026-03-02 20:40:26'),
(2, 'Standard Rate', 2.00, '2026-03-02', NULL, 1, '2026-03-02 20:44:03'),
(3, 'Standard Rate', 2.00, '2026-03-02', NULL, 1, '2026-03-02 20:45:28');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int NOT NULL,
  `session_code` varchar(50) NOT NULL,
  `machine_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `arduino_unit_id` int DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_minutes` decimal(10,2) DEFAULT NULL,
  `rate_per_minute` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `qr_scanned` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','operator') DEFAULT 'operator',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(1, 'admin', 'admin123', 'admin@playmeter.com', 'admin', '2026-03-02 18:01:26');

-- --------------------------------------------------------

--
-- Structure for view `active_sessions_view`
--
DROP TABLE IF EXISTS `active_sessions_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_sessions_view`  AS SELECT `s`.`id` AS `id`, `s`.`session_code` AS `session_code`, `s`.`machine_id` AS `machine_id`, `s`.`customer_id` AS `customer_id`, `s`.`arduino_unit_id` AS `arduino_unit_id`, `s`.`start_time` AS `start_time`, `s`.`end_time` AS `end_time`, `s`.`duration_minutes` AS `duration_minutes`, `s`.`rate_per_minute` AS `rate_per_minute`, `s`.`total_cost` AS `total_cost`, `s`.`amount_paid` AS `amount_paid`, `s`.`payment_status` AS `payment_status`, `s`.`qr_scanned` AS `qr_scanned`, `s`.`created_at` AS `created_at`, `m`.`name` AS `machine_name`, `c`.`full_name` AS `customer_name`, `c`.`customer_code` AS `customer_code`, `a`.`unit_id` AS `arduino_id` FROM (((`sessions` `s` left join `machines` `m` on((`s`.`machine_id` = `m`.`id`))) left join `customers` `c` on((`s`.`customer_id` = `c`.`id`))) left join `arduino_units` `a` on((`s`.`arduino_unit_id` = `a`.`id`))) WHERE (`s`.`end_time` is null) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `arduino_commands`
--
ALTER TABLE `arduino_commands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arduino_unit_id` (`arduino_unit_id`);

--
-- Indexes for table `arduino_units`
--
ALTER TABLE `arduino_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_id` (`unit_id`),
  ADD KEY `fk_arduino_machine` (`machine_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`);

--
-- Indexes for table `machines`
--
ALTER TABLE `machines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `plays`
--
ALTER TABLE `plays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `machine_id` (`machine_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `rates`
--
ALTER TABLE `rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_code` (`session_code`),
  ADD KEY `fk_sessions_machine` (`machine_id`),
  ADD KEY `fk_sessions_customer` (`customer_id`),
  ADD KEY `fk_sessions_arduino` (`arduino_unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `arduino_commands`
--
ALTER TABLE `arduino_commands`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `arduino_units`
--
ALTER TABLE `arduino_units`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `machines`
--
ALTER TABLE `machines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `plays`
--
ALTER TABLE `plays`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `rates`
--
ALTER TABLE `rates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `arduino_commands`
--
ALTER TABLE `arduino_commands`
  ADD CONSTRAINT `arduino_commands_ibfk_1` FOREIGN KEY (`arduino_unit_id`) REFERENCES `arduino_units` (`id`);

--
-- Constraints for table `arduino_units`
--
ALTER TABLE `arduino_units`
  ADD CONSTRAINT `arduino_units_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_arduino_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plays`
--
ALTER TABLE `plays`
  ADD CONSTRAINT `plays_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `plays_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_arduino` FOREIGN KEY (`arduino_unit_id`) REFERENCES `arduino_units` (`id`),
  ADD CONSTRAINT `fk_sessions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_sessions_machine` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  ADD CONSTRAINT `sessions_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sessions_ibfk_3` FOREIGN KEY (`arduino_unit_id`) REFERENCES `arduino_units` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
