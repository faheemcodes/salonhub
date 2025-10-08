-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2025 at 01:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saloon`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `shop_id`, `customer_name`, `customer_phone`, `appointment_date`, `appointment_time`, `service_type`, `payment_method`, `status`, `created_at`) VALUES
(12, 46, 'rathore', '+923403224959', '2025-08-02', '20:00:00', 'haircut', 'online', 'pending', '2025-08-02 10:39:38'),
(13, 48, 'rathore', '+923403224959', '2025-08-02', '12:00:00', 'coloring', 'online', 'confirmed', '2025-08-02 11:50:36'),
(14, 49, 'rathore', '+923403224959', '2025-08-02', '21:00:00', 'coloring', 'online', 'confirmed', '2025-08-02 12:24:15'),
(15, 49, 'akash', '+923403224959', '2025-08-02', '21:00:00', 'coloring', 'online', 'confirmed', '2025-08-02 12:50:09');

-- --------------------------------------------------------

--
-- Table structure for table `manual_payments`
--

CREATE TABLE `manual_payments` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `shop_cnic` varchar(20) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `service_type` varchar(50) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(20) NOT NULL DEFAULT 'cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `manual_payments`
--

INSERT INTO `manual_payments` (`id`, `shop_id`, `shop_cnic`, `customer_name`, `customer_phone`, `service_type`, `payment_amount`, `payment_date`, `payment_method`, `created_at`) VALUES
(7, 48, '44302-9876667-7', 'scjdwcwen', '+923403224959', 'haircut', 430.00, '2025-08-02', 'cash', '2025-08-02 11:51:51'),
(8, 49, '44302-9876667-4', 'sir raffique', '03403224959', 'haircut', 320.00, '2025-08-02', 'card', '2025-08-02 12:25:48');

-- --------------------------------------------------------

--
-- Table structure for table `register_shop`
--

CREATE TABLE `register_shop` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `shop_logo` varchar(255) DEFAULT NULL,
  `shop_address` text DEFAULT NULL,
  `shop_description` text DEFAULT NULL,
  `shop_images` text DEFAULT NULL,
  `owner_name` varchar(255) DEFAULT NULL,
  `owner_photo` varchar(255) DEFAULT NULL,
  `staff_info` text DEFAULT NULL,
  `staff_photos` text DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `register_shop`
--

INSERT INTO `register_shop` (`id`, `shop_name`, `shop_logo`, `shop_address`, `shop_description`, `shop_images`, `owner_name`, `owner_photo`, `staff_info`, `staff_photos`, `contact_number`, `whatsapp_number`, `cnic`, `email`, `latitude`, `longitude`, `payment_proof`, `transaction_id`, `created_at`, `status`, `admin_notes`, `processed_at`) VALUES
(46, 'kamlesh', '../uploads/registerShop/998.jpg', '4t34uuu34fyu4', 'eruf3uf ', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', 'aku', '../uploads/registerShop/998.jpg', 'th4uithu', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', '03403224959', '+923403224959', '44302-9876667-1', 'khokher.akumar@gmail.com', '', '', '../uploads/registerShop/998.jpg', 'whu3gfyrgydfsufu', '2025-08-01 08:00:02', '', NULL, '2025-08-01 13:00:40'),
(48, 'ghalib', '../uploads/registerShop/998.jpg', 'aliplace qasmabad,hyd', 'wfjewkf iwk', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', 'Mir Ghalib', '../uploads/registerShop/998.jpg', 'dsjebwjfwejkf', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', '03043953713', '+923043953713', '44302-9876667-7', 'khokher.akumar@gmail.com', '', '', '../uploads/registerShop/998.jpg', 'whu3gfyrgydfsufu', '2025-08-02 11:34:24', '', NULL, '2025-08-02 16:35:15'),
(49, 'Sir Raffique', '../uploads/registerShop/998.jpg', 'aliplace qasmabad,hyd', 'snd sjcbsc', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', 'sir raffique', '../uploads/registerShop/998.jpg', 'cbjsbjsjcbj', 'a:1:{i:0;s:31:\"../uploads/registerShop/998.jpg\";}', '03043953713', '+923043953713', '44302-9876667-4', 'khokher.akumar@gmail.com', '', '', '../uploads/registerShop/998.jpg', 'whu3gfyrgydfsufu', '2025-08-02 12:06:56', '', NULL, '2025-08-02 17:08:16');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_accounts`
--

CREATE TABLE `vendor_accounts` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_date` date NOT NULL,
  `cnic` varchar(15) NOT NULL,
  `shop_status` enum('open','closed') NOT NULL DEFAULT 'closed',
  `status` enum('working','blocked') NOT NULL DEFAULT 'working'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_accounts`
--

INSERT INTO `vendor_accounts` (`id`, `shop_id`, `username`, `password`, `created_at`, `account_date`, `cnic`, `shop_status`, `status`) VALUES
(0, 46, 'akash', '$2y$10$2KGhlYSo7/1SGkk8Z/7QRONVpLMzqylnET0NfDCNZV3rBVkVjKjGO', '2025-08-01 08:00:40', '2026-02-01', '44302-9876667-1', 'open', 'working'),
(0, 48, 'akash', '$2y$10$MmDFhReOXWd3op6KeBrxO.JtCIOOu1O/0y/nH.S6G578GhfGSrGoW', '2025-08-02 11:35:15', '2026-01-01', '44302-9876667-7', 'open', 'blocked'),
(0, 49, 'akash', '$2y$10$IZS38STNE.VWHnPSyN2nAOM9kgSOitP3URYqnmNa7yA1z2d9fTcM6', '2025-08-02 12:08:16', '2026-01-01', '44302-9876667-4', 'open', 'working');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`);

--
-- Indexes for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `shop_cnic` (`shop_cnic`),
  ADD KEY `payment_date` (`payment_date`);

--
-- Indexes for table `register_shop`
--
ALTER TABLE `register_shop`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `register_shop`
--
ALTER TABLE `register_shop`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `register_shop` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
