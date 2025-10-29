-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 01:39 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `franklin_baker`
--

-- --------------------------------------------------------

--
-- Table structure for table `label_templates`
--

CREATE TABLE `label_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `layout` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`layout`)),
  `barcode_type` varchar(20) DEFAULT 'EAN13',
  `label_size` varchar(20) DEFAULT '50x30',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `printed_labels`
--

CREATE TABLE `printed_labels` (
  `id` int(11) NOT NULL,
  `ean_code` varchar(13) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `code_date` date NOT NULL,
  `net_weight_lbs` decimal(10,2) NOT NULL,
  `net_weight_kg` decimal(10,2) NOT NULL,
  `bag_no` varchar(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `printed_labels`
--

INSERT INTO `printed_labels` (`id`, `ean_code`, `product_name`, `code_date`, `net_weight_lbs`, `net_weight_kg`, `bag_no`, `created_at`) VALUES
(19, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 04:48:35'),
(20, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0002', '2025-10-29 04:48:35'),
(21, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0003', '2025-10-29 04:48:35'),
(22, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 04:48:35'),
(23, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0002', '2025-10-29 04:48:35'),
(24, 'EAN: 01234567', '', '0000-00-00', 0.00, 0.00, '0003', '2025-10-29 04:48:35'),
(25, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:18:33'),
(26, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:23:07'),
(27, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:26:50'),
(28, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:27:35'),
(29, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:27:56'),
(30, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:27:58'),
(31, '0123456789012', '', '0000-00-00', 0.00, 0.00, '0001', '2025-10-29 06:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `ean_code` varchar(13) NOT NULL,
  `name` varchar(255) NOT NULL,
  `net_weight_lbs` decimal(10,2) NOT NULL,
  `net_weight_kg` decimal(10,2) NOT NULL,
  `code_date` date NOT NULL,
  `manufacturer` varchar(255) DEFAULT 'Franklin Baker',
  `ingredients` varchar(255) DEFAULT 'Coconut, Sugar',
  `country_of_origin` varchar(100) DEFAULT 'Philippines',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `ean_code`, `name`, `net_weight_lbs`, `net_weight_kg`, `code_date`, `manufacturer`, `ingredients`, `country_of_origin`, `created_at`) VALUES
(1, '0123456789018', 'Sweetened Coconut', 1.50, 0.68, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(2, '1234567890120', 'Desiccated Coconut', 2.00, 0.91, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(3, '2345678901234', 'Coconut Milk Powder', 1.75, 0.79, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(4, '3456789012346', 'Toasted Coconut', 3.00, 1.36, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(5, '4567890123458', 'Coconut Cream', 2.25, 1.02, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(6, '5678901234560', 'Organic Coconut Flakes', 1.80, 0.82, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(7, '6789012345672', 'Coconut Sugar', 2.50, 1.13, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(8, '7890123456784', 'Coconut Oil', 1.90, 0.86, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(9, '8901234567896', 'Coconut Flour', 2.10, 0.95, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51'),
(10, '9012345678908', 'Shredded Coconut', 2.75, 1.25, '2025-10-12', 'Franklin Baker', 'Coconut, Sugar', 'Philippines', '2025-10-12 15:36:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Employee','Admin','Super Admin') NOT NULL DEFAULT 'Employee',
  `status` enum('pending','active','inactive') NOT NULL DEFAULT 'pending',
  `custom_menus` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `employee_id`, `email`, `password`, `role`, `status`, `custom_menus`, `created_at`) VALUES
(1, 'marcus', 'joshua', 'alvarado', '1234', '3mjhay0416@gmail.com', '$2y$10$fXVpieEj8bBwtlWD5WW0L.DmdL2PVnVLLmVMcPtjmVqdmqT3qWhve', 'Super Admin', 'active', NULL, '2025-10-12 15:37:38'),
(2, 'carl', 'monkey', 'sumajestad', '12345', 'd3lm4rk10@gmail.com', '$2y$10$W1/HeSSsUS6FDWrDr2k0He/NQzgNdyrNVTwSDGFuSqGwjG5TVXdK.', 'Admin', 'active', 'manage_users,manage_products,reports', '2025-10-12 15:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `user_actions`
--

CREATE TABLE `user_actions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('promotion','deletion','menu_update') NOT NULL,
  `details` text DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_actions`
--

INSERT INTO `user_actions` (`id`, `user_id`, `action_type`, `details`, `reason`, `created_at`) VALUES
(1, 2, 'promotion', '{\"new_role\":\"Employee\",\"new_status\":\"active\",\"email\":\"d3lm4rk10@gmail.com\"}', NULL, '2025-10-29 04:48:13'),
(3, 2, 'promotion', '{\"new_role\":\"Admin\",\"new_status\":\"active\",\"email\":\"d3lm4rk10@gmail.com\"}', NULL, '2025-10-29 11:41:24'),
(11, 1, 'menu_update', '{\"role\":\"Admin\",\"menus\":\"manage_users,manage_products,reports,create_label\"}', NULL, '2025-10-29 12:33:20'),
(12, 1, 'menu_update', '{\"role\":\"Admin\",\"menus\":\"manage_users,manage_products,reports\"}', NULL, '2025-10-29 12:33:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `label_templates`
--
ALTER TABLE `label_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `printed_labels`
--
ALTER TABLE `printed_labels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ean_code` (`ean_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `label_templates`
--
ALTER TABLE `label_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `printed_labels`
--
ALTER TABLE `printed_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_actions`
--
ALTER TABLE `user_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `label_templates`
--
ALTER TABLE `label_templates`
  ADD CONSTRAINT `label_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
