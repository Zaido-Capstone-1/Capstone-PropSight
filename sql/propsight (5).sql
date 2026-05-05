-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 27, 2026 at 01:47 PM
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
-- Database: `propsight`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `module`, `ip_address`, `action_date`) VALUES
(1, 15, 'Blacklisted user #6: ', 'guests', NULL, '2026-04-03 10:17:59'),
(2, 15, 'Blacklisted user #2: ', 'guests', NULL, '2026-04-24 15:54:39');

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `setting_key`, `value`, `updated_by`, `updated_at`) VALUES
(1, 'site_name', 'Filipino Homes', NULL, '2026-03-26 04:10:48'),
(2, 'site_email', 'admin@filipinohomes.com', NULL, '2026-03-26 04:10:48'),
(3, 'currency', 'PHP', NULL, '2026-03-26 04:10:48'),
(4, 'currency_symbol', '₱', NULL, '2026-03-26 04:10:48'),
(5, 'checkout_time', '12:00', NULL, '2026-03-26 04:10:48'),
(6, 'checkin_time', '14:00', NULL, '2026-03-26 04:10:48'),
(7, 'min_nights', '1', NULL, '2026-03-26 04:10:48'),
(8, 'max_nights', '90', NULL, '2026-03-26 04:10:48'),
(9, 'loyalty_points_per_peso', '0.1', NULL, '2026-03-26 04:10:48'),
(10, 'booking_cancellation_hours', '48', NULL, '2026-03-26 04:10:48'),
(11, 'smtp_host', 'smtp.gmail.com', NULL, '2026-03-26 04:10:48'),
(12, 'smtp_port', '587', NULL, '2026-03-26 04:10:48'),
(13, 'tax_rate', '0.12', NULL, '2026-03-26 04:10:48');

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `amenity_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `icon` varchar(30) NOT NULL DEFAULT 'security',
  `status` enum('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`amenity_id`, `property_id`, `name`, `icon`, `status`, `created_at`) VALUES
(13, 24, 'Free Wifi', 'wifi', 'available', '2026-04-25 14:48:24');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `id` int(10) UNSIGNED NOT NULL,
  `blocked_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `checkin_date` date NOT NULL,
  `checkout_date` date NOT NULL,
  `guests` int(3) NOT NULL DEFAULT 1,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  `special_requests` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `booking_source` varchar(50) DEFAULT 'Direct',
  `payment_ref` varchar(100) DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `checkin_status` enum('pending','done') NOT NULL DEFAULT 'pending',
  `checkout_status` enum('pending','done') NOT NULL DEFAULT 'pending',
  `checkin_actual` datetime DEFAULT NULL,
  `checkout_actual` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `unit_id`, `tenant_id`, `user_id`, `checkin_date`, `checkout_date`, `guests`, `total_amount`, `status`, `special_requests`, `payment_method`, `booking_source`, `payment_ref`, `payment_notes`, `paid_at`, `confirmed_at`, `checkin_status`, `checkout_status`, `checkin_actual`, `checkout_actual`, `created_at`, `updated_at`) VALUES
(46, 20, 3, 21, '2026-04-26', '2026-04-27', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-25 14:15:44', '2026-04-25 14:34:24'),
(47, 20, 3, 21, '2026-04-26', '2026-04-27', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-25 14:34:43', '2026-04-25 19:14:27'),
(48, 20, 3, 21, '2026-04-26', '2026-04-27', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-25 19:14:40', '2026-04-25 19:15:07'),
(49, 20, 3, 21, '2026-04-26', '2026-04-26', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-25 19:15:13', '2026-04-25 19:18:46'),
(50, 20, 3, 21, '2026-04-26', '2026-04-27', 2, 1000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'done', 'done', '2026-04-26 14:25:19', '2026-04-26 14:26:03', '2026-04-25 19:18:58', '2026-04-26 06:26:03'),
(51, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 06:33:25', '2026-04-26 06:37:34'),
(52, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 06:37:50', '2026-04-26 06:38:01'),
(53, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 06:38:10', '2026-04-26 06:40:00'),
(54, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 06:40:08', '2026-04-26 06:42:39'),
(55, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 06:42:46', '2026-04-26 06:42:54'),
(56, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'completed', NULL, 'cash', 'Direct', NULL, NULL, NULL, NULL, 'done', 'done', '2026-04-26 14:59:44', '2026-04-26 15:02:20', '2026-04-26 06:56:07', '2026-04-26 07:02:20'),
(57, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'done', 'done', '2026-04-26 15:16:15', '2026-04-26 15:16:51', '2026-04-26 07:13:31', '2026-04-26 07:16:51'),
(58, 20, 3, 21, '2026-04-27', '2026-04-30', 2, 3000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'done', 'done', '2026-04-26 15:40:56', '2026-04-26 15:54:43', '2026-04-26 07:40:28', '2026-04-26 07:54:43'),
(59, 20, 3, 21, '2026-04-27', '2026-04-29', 2, 2000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'done', 'done', '2026-04-26 16:05:05', '2026-04-26 16:09:52', '2026-04-26 08:04:47', '2026-04-26 08:09:52'),
(60, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 08:10:09', '2026-04-26 08:12:13'),
(61, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 08:12:19', '2026-04-26 15:33:43'),
(62, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 16:08:27', '2026-04-26 16:21:42'),
(63, 20, 3, 21, '2026-04-27', '2026-04-29', 2, 2000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, '2026-04-27 00:30:58', 'done', 'done', '2026-04-27 00:43:01', '2026-04-27 00:44:02', '2026-04-26 16:22:35', '2026-04-26 16:44:02'),
(64, 20, 3, 21, '2026-04-27', '2026-04-30', 2, 3000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, '2026-04-27 00:52:50', 'done', 'done', '2026-04-27 00:53:18', '2026-04-27 00:54:10', '2026-04-26 16:52:37', '2026-04-26 16:54:10'),
(65, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'cancelled', NULL, 'gcash', 'Direct', NULL, NULL, NULL, NULL, 'pending', 'pending', NULL, NULL, '2026-04-26 16:55:13', '2026-04-26 16:55:27'),
(66, 20, 3, 21, '2026-04-27', '2026-04-28', 2, 1000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, '2026-04-27 00:55:55', 'done', 'done', '2026-04-27 00:56:20', '2026-04-27 00:56:32', '2026-04-26 16:55:36', '2026-04-26 16:56:32'),
(67, 20, 3, 21, '2026-04-27', '2026-04-27', 2, 1000.00, 'completed', NULL, 'gcash', 'Direct', NULL, NULL, NULL, '2026-04-27 00:58:51', 'pending', 'pending', NULL, NULL, '2026-04-26 16:58:42', '2026-04-26 16:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `booking_reviews`
--

CREATE TABLE `booking_reviews` (
  `review_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_reviews`
--

INSERT INTO `booking_reviews` (`review_id`, `booking_id`, `unit_id`, `user_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(1, 24, 17, 17, 5, 'I love it', '2026-04-13 15:35:19', '2026-04-13 15:35:19'),
(2, 25, 14, 17, 5, 'I love this unit because it has all everything. Also the staff here is friendly and hospitable!', '2026-04-13 16:35:39', '2026-04-13 16:35:39'),
(3, 58, 20, 21, 5, 'I like this room very much!', '2026-04-26 15:31:26', '2026-04-26 15:31:26'),
(4, 57, 20, 21, 4, 'Nice experience!', '2026-04-26 15:45:42', '2026-04-26 15:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `expense_category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `property_id`, `expense_category`, `description`, `amount`, `expense_date`, `recorded_by`, `unit_id`) VALUES
(18, NULL, 'Maintenance', 'Aircon', 13123.00, '2026-03-26', NULL, NULL),
(19, NULL, 'Maintenance', 'Water Bill', 1000.00, '2026-03-26', NULL, NULL),
(20, NULL, 'Maintenance', 'Water Bill', 5000.00, '2026-03-28', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `financial_records`
--

CREATE TABLE `financial_records` (
  `id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `year` smallint(4) NOT NULL,
  `month` tinyint(2) NOT NULL,
  `revenue` decimal(14,2) NOT NULL DEFAULT 0.00,
  `maintenance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `utilities` decimal(14,2) NOT NULL DEFAULT 0.00,
  `salaries` decimal(14,2) NOT NULL DEFAULT 0.00,
  `admin` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_reports`
--

CREATE TABLE `financial_reports` (
  `report_id` int(11) NOT NULL,
  `report_month` int(11) DEFAULT NULL,
  `report_year` int(11) DEFAULT NULL,
  `total_income` decimal(12,2) DEFAULT NULL,
  `total_expenses` decimal(12,2) DEFAULT NULL,
  `net_profit` decimal(12,2) DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(30) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `issued_date` date NOT NULL,
  `due_date` date NOT NULL,
  `items` text DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Paid','Pending','Overdue','Sent') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_no`, `tenant_id`, `booking_id`, `unit`, `issued_date`, `due_date`, `items`, `subtotal`, `tax`, `total`, `status`, `notes`, `created_by`, `created_at`) VALUES
(5, 'INV-202604-0001', 3, NULL, 'Unit 10', '2026-04-27', '2026-04-30', 'Extended Stay', 0.00, 0.00, 2000.00, 'Sent', NULL, NULL, '2026-04-26 07:43:14');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_points`
--

CREATE TABLE `loyalty_points` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `type` enum('earn','redeem','bonus','expire') NOT NULL DEFAULT 'earn',
  `description` varchar(255) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty_points`
--

INSERT INTO `loyalty_points` (`id`, `user_id`, `points`, `type`, `description`, `booking_id`, `created_at`) VALUES
(1, 14, 420, 'earn', 'Booking #2 — Unit A18 stay', NULL, '2026-03-26 04:10:48'),
(2, 14, 150, 'earn', 'Booking #5 — Casa Camilla stay', NULL, '2026-03-26 04:10:48'),
(3, 14, -100, 'redeem', 'Redeemed for room discount', NULL, '2026-03-26 04:10:48'),
(4, 14, 100, 'bonus', 'New member welcome bonus', NULL, '2026-03-26 04:10:48'),
(8, 21, 7500, 'earn', 'Booking #29 stay completed', NULL, '2026-04-16 06:58:16'),
(9, 21, 15000, 'earn', 'Booking #30 stay completed', NULL, '2026-04-16 06:58:16'),
(10, 21, 7500, 'earn', 'Booking #31 stay completed', NULL, '2026-04-16 06:58:16'),
(11, 21, -150, 'redeem', 'Redeemed: Free Breakfast', NULL, '2026-04-16 06:59:05'),
(12, 21, 15000, 'earn', 'Booking #30 stay completed', NULL, '2026-04-16 07:03:00'),
(13, 21, 7500, 'earn', 'Booking #29 stay completed', NULL, '2026-04-16 07:03:03'),
(14, 21, 45000, 'earn', 'Booking #32 stay completed', NULL, '2026-04-24 17:53:10'),
(15, 21, 100, 'earn', 'Booking #50 stay completed', 50, '2026-04-26 06:26:03'),
(16, 21, 100, 'earn', 'Booking #56 stay completed', 56, '2026-04-26 07:02:20'),
(17, 21, 100, 'earn', 'Booking #57 stay completed', 57, '2026-04-26 07:16:51'),
(18, 21, 300, 'earn', 'Booking #58 stay completed', 58, '2026-04-26 07:54:43'),
(19, 21, 200, 'earn', 'Booking #59 stay completed', 59, '2026-04-26 08:09:52'),
(20, 21, 200, 'earn', 'Booking #63 stay completed', 63, '2026-04-26 16:44:02'),
(21, 21, 300, 'earn', 'Booking #64 stay completed', 64, '2026-04-26 16:54:10'),
(22, 21, 100, 'earn', 'Booking #66 stay completed', 66, '2026-04-26 16:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `request_status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `request_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `from_user` int(11) NOT NULL,
  `to_user` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `from_user`, `to_user`, `subject`, `body`, `attachment_url`, `is_read`, `parent_id`, `created_at`) VALUES
(1, 11, 14, 'asdas', 'asd', NULL, 0, NULL, '2026-03-26 11:37:38'),
(2, 11, 14, '', 'hi', NULL, 0, NULL, '2026-03-26 11:38:04'),
(3, 11, 14, '', 'hello', NULL, 0, NULL, '2026-03-26 11:38:13'),
(4, 11, 12, 'asdasd', 'asdsad', NULL, 0, NULL, '2026-03-26 11:44:01'),
(5, 11, 14, '21312', '123', NULL, 0, NULL, '2026-03-26 12:14:37'),
(6, 11, 14, '', 'asdsa', NULL, 0, NULL, '2026-03-26 12:14:44'),
(7, 12, 15, '', 'Hello!', NULL, 1, NULL, '2026-04-10 13:08:39'),
(8, 15, 12, '', 'Hi', NULL, 1, NULL, '2026-04-10 13:08:56'),
(9, 12, 15, '', 'How are  you?', NULL, 1, NULL, '2026-04-10 13:09:09'),
(10, 15, 12, '', 'Asdasdasd', NULL, 1, NULL, '2026-04-10 13:11:39'),
(11, 12, 15, 'Question', 'Asdasd', NULL, 1, NULL, '2026-04-10 13:12:21'),
(12, 12, 15, '', 'hi', NULL, 1, NULL, '2026-04-10 13:24:03'),
(13, 15, 12, '', 'hello', NULL, 1, NULL, '2026-04-10 13:24:11'),
(14, 12, 15, '', 'asdasdasda', NULL, 1, NULL, '2026-04-10 13:24:23'),
(15, 12, 4, '', 'Hello', NULL, 0, NULL, '2026-04-12 18:03:58'),
(19, 21, 15, '', 'Hi', NULL, 1, NULL, '2026-04-14 05:43:24'),
(20, 21, 15, '', 'asdasd', 'assets/uploads/messages/msg_69edc45d4573e0.82715415.pdf', 1, NULL, '2026-04-26 07:53:01'),
(21, 21, 15, '', 'asdasd', 'assets/uploads/messages/msg_69edc4666b3538.52159505.png', 1, NULL, '2026-04-26 07:53:10');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(1, 14, 'booking', 'Booking Confirmed', 'Your booking for Unit A18 has been confirmed.', NULL, 1, '2026-03-26 04:10:48'),
(2, 14, 'loyalty', 'Points Earned', 'You earned 420 loyalty points from your recent stay!', NULL, 1, '2026-03-26 04:10:48'),
(3, 14, 'system', 'Welcome to Filipino Homes', 'Thanks for joining! Explore available units.', NULL, 1, '2026-03-26 04:10:48'),
(4, 2, 'booking', 'New Booking Request', 'A new booking request has been submitted for Unit 10.', NULL, 0, '2026-03-26 04:10:48'),
(5, 14, 'message', 'New message from admin', 'asd', 'support.php', 0, '2026-03-26 11:37:38'),
(6, 14, 'message', 'New message from admin', 'hi', 'support.php', 0, '2026-03-26 11:38:04'),
(7, 14, 'message', 'New message from admin', 'hello', 'support.php', 0, '2026-03-26 11:38:13'),
(8, 12, 'message', 'New message from admin', 'asdsad', 'support.php', 1, '2026-03-26 11:44:01'),
(9, 14, 'message', 'New message from admin', '123', 'support.php', 0, '2026-03-26 12:14:37'),
(10, 14, 'message', 'New message from admin', 'asdsa', 'support.php', 0, '2026-03-26 12:14:44'),
(11, 4, 'support', 'New Support Ticket', 'assdas', 'pages/admin/messages.php', 0, '2026-03-26 13:46:44'),
(13, 4, 'support', 'New Support Ticket', 'assdas', 'pages/admin/messages.php', 0, '2026-03-26 13:46:46'),
(15, 15, 'message', 'New message from Marlon Garcia', 'Hello!', 'pages/admin/messages.php', 0, '2026-04-10 13:08:39'),
(16, 12, 'message', 'New message from admin', 'Hi', 'pages/user/messages.php', 1, '2026-04-10 13:08:56'),
(17, 15, 'message', 'New message from Marlon Garcia', 'How are  you?', 'pages/admin/messages.php', 0, '2026-04-10 13:09:09'),
(18, 12, 'message', 'New message from admin', 'Asdasdasd', 'pages/user/messages.php', 1, '2026-04-10 13:11:39'),
(19, 15, 'message', 'New message from Marlon Garcia', 'Asdasd', 'pages/admin/messages.php', 0, '2026-04-10 13:12:21'),
(20, 15, 'message', 'New message from Marlon Garcia', 'hi', 'pages/admin/messages.php', 0, '2026-04-10 13:24:03'),
(21, 12, 'message', 'New message from admin', 'hello', 'pages/user/messages.php', 1, '2026-04-10 13:24:11'),
(22, 15, 'message', 'New message from Marlon Garcia', 'asdasdasda', 'pages/admin/messages.php', 0, '2026-04-10 13:24:23'),
(23, 4, 'message', 'New message from Marlon Garcia', 'Hello', 'pages/admin/messages.php', 0, '2026-04-12 18:03:58'),
(24, 4, 'booking', 'New booking: BK-000021', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 07:57:04'),
(25, 15, 'booking', 'New booking: BK-000021', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 07:57:04'),
(26, 12, 'booking', 'Booking submitted: BK-000021', 'Your booking for  (Apr 14–Apr 17, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-13 07:57:04'),
(27, 4, 'booking', 'Booking cancelled: BK-000021', 'Marlon Garcia cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 08:04:27'),
(28, 15, 'booking', 'Booking cancelled: BK-000021', 'Marlon Garcia cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 08:04:27'),
(29, 12, 'booking', 'Booking cancelled: BK-000021', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-13 08:04:27'),
(30, 4, 'booking', 'New booking: BK-000022', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 08:04:56'),
(31, 15, 'booking', 'New booking: BK-000022', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 08:04:56'),
(32, 12, 'booking', 'Booking submitted: BK-000022', 'Your booking for  (Apr 14–Apr 17, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-13 08:04:56'),
(33, 4, 'booking', 'Booking cancelled: BK-000022', 'Marlon Garcia cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 08:29:58'),
(34, 15, 'booking', 'Booking cancelled: BK-000022', 'Marlon Garcia cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 08:29:58'),
(35, 12, 'booking', 'Booking cancelled: BK-000022', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-13 08:29:58'),
(36, 4, 'booking', 'New booking: BK-000023', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 09:06:22'),
(37, 15, 'booking', 'New booking: BK-000023', 'Marlon Garcia booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 09:06:22'),
(38, 12, 'booking', 'Booking submitted: BK-000023', 'Your booking for  (Apr 14–Apr 17, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-13 09:06:22'),
(39, 4, 'booking', 'Booking cancelled: BK-000023', 'Marlon Garcia cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-13 09:15:58'),
(40, 15, 'booking', 'Booking cancelled: BK-000023', 'Marlon Garcia cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-13 09:15:58'),
(41, 12, 'booking', 'Booking cancelled: BK-000023', 'Your booking for Casa De Primera — Unit Unit A18 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-13 09:15:58'),
(42, 15, 'message', 'New message from Bull Dogz', 'Hi', 'pages/admin/messages.php', 0, '2026-04-13 14:33:17'),
(43, 4, 'booking', 'New booking: BK-000024', 'Bull Dogz booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 15:16:42'),
(44, 15, 'booking', 'New booking: BK-000024', 'Bull Dogz booked  · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 15:16:42'),
(48, 4, 'support', 'New Support Ticket', 'asdASd', 'pages/admin/messages.php', 0, '2026-04-13 15:47:51'),
(49, 15, 'support', 'New Support Ticket', 'asdASd', 'pages/admin/messages.php', 0, '2026-04-13 15:47:51'),
(50, 4, 'support', 'New Support Ticket', 'asdASdasd', 'pages/admin/messages.php', 0, '2026-04-13 15:49:56'),
(51, 15, 'support', 'New Support Ticket', 'asdASdasd', 'pages/admin/messages.php', 0, '2026-04-13 15:49:56'),
(52, 4, 'support', 'New Support Ticket', 'ASDASDASDasd', 'pages/admin/messages.php', 0, '2026-04-13 15:50:41'),
(53, 15, 'support', 'New Support Ticket', 'ASDASDASDasd', 'pages/admin/messages.php', 0, '2026-04-13 15:50:41'),
(54, 4, 'support', 'New Support Ticket', 'asdASdasd', 'pages/admin/messages.php', 0, '2026-04-13 15:57:53'),
(55, 15, 'support', 'New Support Ticket', 'asdASdasd', 'pages/admin/messages.php', 0, '2026-04-13 15:57:53'),
(56, 4, 'booking', 'New booking: BK-000025', 'Bull Dogz booked Ocean Garden Villas — Unit Unit A · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 16:05:46'),
(57, 15, 'booking', 'New booking: BK-000025', 'Bull Dogz booked Ocean Garden Villas — Unit Unit A · Apr 14–Apr 17, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 16:05:46'),
(61, 4, 'booking', 'New booking: BK-000026', 'Bull Dogz booked Casa Camilla Beachfront — Unit Unit 10 · Apr 14–Apr 16, 2026 (2 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 16:34:24'),
(62, 15, 'booking', 'New booking: BK-000026', 'Bull Dogz booked Casa Camilla Beachfront — Unit Unit 10 · Apr 14–Apr 16, 2026 (2 nights)', 'pages/admin/reservations.php', 0, '2026-04-13 16:34:24'),
(64, 4, 'booking', 'Booking cancelled: BK-000026', 'Bull Dogz cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 16:34:51'),
(65, 15, 'booking', 'Booking cancelled: BK-000026', 'Bull Dogz cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-13 16:34:51'),
(67, 4, 'support', 'New Support Ticket', 'ASdasdasdadas', 'pages/admin/messages.php', 0, '2026-04-13 16:36:32'),
(68, 15, 'support', 'New Support Ticket', 'ASdasdasdadas', 'pages/admin/messages.php', 0, '2026-04-13 16:36:32'),
(69, 15, 'message', 'New message from Bull Dogz', 'Hello', 'pages/admin/messages.php', 0, '2026-04-13 16:36:49'),
(71, 4, 'booking', 'New booking: BK-000027', 'Jr Marticio booked Roxon Residences — Unit Unit 5 · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:38:38'),
(72, 15, 'booking', 'New booking: BK-000027', 'Jr Marticio booked Roxon Residences — Unit Unit 5 · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:38:38'),
(73, 21, 'booking', 'Booking submitted: BK-000027', 'Your booking for Roxon Residences — Unit Unit 5 (Apr 15–Apr 18, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-14 05:38:38'),
(74, 4, 'booking', 'Booking cancelled: BK-000027', 'Jr Marticio cancelled their booking for Roxon Residences — Unit Unit 5.', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:17'),
(75, 15, 'booking', 'Booking cancelled: BK-000027', 'Jr Marticio cancelled their booking for Roxon Residences — Unit Unit 5.', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:17'),
(76, 21, 'booking', 'Booking cancelled: BK-000027', 'Your booking for Roxon Residences — Unit Unit 5 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-14 05:42:17'),
(77, 4, 'booking', 'New booking: BK-000028', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 15–Apr 17, 2026 (2 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:41'),
(78, 15, 'booking', 'New booking: BK-000028', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 15–Apr 17, 2026 (2 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:41'),
(79, 21, 'booking', 'Booking submitted: BK-000028', 'Your booking for Casa De Primera — Unit Unit A18 (Apr 15–Apr 17, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-14 05:42:41'),
(80, 4, 'booking', 'Booking cancelled: BK-000028', 'Jr Marticio cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:52'),
(81, 15, 'booking', 'Booking cancelled: BK-000028', 'Jr Marticio cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-14 05:42:52'),
(82, 21, 'booking', 'Booking cancelled: BK-000028', 'Your booking for Casa De Primera — Unit Unit A18 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-14 05:42:52'),
(83, 15, 'message', 'New message from Jr Marticio', 'Hi', 'pages/admin/messages.php', 0, '2026-04-14 05:43:24'),
(84, 4, 'booking', 'New booking: BK-000029', 'Jr Marticio booked Ocean Garden Villas — Unit Unit A · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:43:51'),
(85, 15, 'booking', 'New booking: BK-000029', 'Jr Marticio booked Ocean Garden Villas — Unit Unit A · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:43:51'),
(86, 21, 'booking', 'Booking submitted: BK-000029', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 15–Apr 18, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-14 05:43:51'),
(87, 4, 'support', 'New Support Ticket', '[BK-000029] Booking concern', 'pages/admin/messages.php', 0, '2026-04-14 05:44:17'),
(88, 15, 'support', 'New Support Ticket', '[BK-000029] Booking concern', 'pages/admin/messages.php', 0, '2026-04-14 05:44:17'),
(89, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000029 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-14 05:46:33'),
(90, 21, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000029 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 1, '2026-04-14 05:47:26'),
(91, 4, 'booking', 'New booking: BK-000030', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:49:16'),
(92, 15, 'booking', 'New booking: BK-000030', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 15–Apr 18, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-14 05:49:16'),
(93, 21, 'booking', 'Booking submitted: BK-000030', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 15–Apr 18, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-14 05:49:16'),
(94, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000030 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-14 05:49:29'),
(95, 21, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000030 at Casa Camilla Beachfront — Unit Unit 10 is now complete.', 'pages/user/bookings.php', 1, '2026-04-14 05:49:49'),
(96, 4, 'booking', 'New booking: BK-000031', 'Jr Marticio booked Roxon Residences — Unit Unit 5 · Apr 17–Apr 20, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-16 06:56:55'),
(97, 15, 'booking', 'New booking: BK-000031', 'Jr Marticio booked Roxon Residences — Unit Unit 5 · Apr 17–Apr 20, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-16 06:56:55'),
(98, 21, 'booking', 'Booking submitted: BK-000031', 'Your booking for Roxon Residences — Unit Unit 5 (Apr 17–Apr 20, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-16 06:56:55'),
(99, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000031 for Roxon Residences — Unit Unit 5 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-16 06:57:21'),
(100, 21, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000031 at Roxon Residences — Unit Unit 5 is now complete.', 'pages/user/bookings.php', 1, '2026-04-16 06:57:44'),
(101, 4, 'booking', 'New booking: BK-000032', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 17–Apr 20, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-16 07:01:41'),
(102, 15, 'booking', 'New booking: BK-000032', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 17–Apr 20, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-16 07:01:41'),
(103, 21, 'booking', 'Booking submitted: BK-000032', 'Your booking for Casa De Primera — Unit Unit A18 (Apr 17–Apr 20, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-16 07:01:41'),
(104, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000032 for Casa De Primera — Unit Unit A18 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-16 07:02:12'),
(105, 21, 'loyalty', 'Points Earned!', 'You earned 15000 loyalty points from your stay!', NULL, 1, '2026-04-16 07:03:00'),
(106, 21, 'loyalty', 'Points Earned!', 'You earned 7500 loyalty points from your stay!', NULL, 1, '2026-04-16 07:03:03'),
(107, 4, 'booking', 'New booking: BK-000034', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 17:38:15'),
(108, 15, 'booking', 'New booking: BK-000034', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 17:38:15'),
(109, 12, 'booking', 'Booking submitted: BK-000034', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-19 17:38:15'),
(110, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000034 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-19 17:38:42'),
(111, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000034 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 1, '2026-04-19 17:39:00'),
(112, 4, 'booking', 'Booking cancelled: BK-000033', 'Marlon Garcia cancelled their booking for Ocean Garden Villas — Unit Unit A.', 'pages/admin/reservations.php', 0, '2026-04-19 17:55:55'),
(113, 15, 'booking', 'Booking cancelled: BK-000033', 'Marlon Garcia cancelled their booking for Ocean Garden Villas — Unit Unit A.', 'pages/admin/reservations.php', 0, '2026-04-19 17:55:55'),
(114, 12, 'booking', 'Booking cancelled: BK-000033', 'Your booking for Ocean Garden Villas — Unit Unit A has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-19 17:55:55'),
(115, 4, 'booking', 'New booking: BK-000035', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit H · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 17:56:18'),
(116, 15, 'booking', 'New booking: BK-000035', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit H · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 17:56:18'),
(117, 12, 'booking', 'Booking submitted: BK-000035', 'Your booking for Ocean Garden Villas — Unit Unit H (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-19 17:56:18'),
(118, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000035 for Ocean Garden Villas — Unit Unit H has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-19 17:56:54'),
(119, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000035 at Ocean Garden Villas — Unit Unit H is now complete.', 'pages/user/bookings.php', 1, '2026-04-19 17:58:30'),
(120, 4, 'booking', 'New booking: BK-000036', 'Marlon Garcia booked Roxon Residences — Unit Unit 5 · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:04:39'),
(121, 15, 'booking', 'New booking: BK-000036', 'Marlon Garcia booked Roxon Residences — Unit Unit 5 · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:04:39'),
(122, 12, 'booking', 'Booking submitted: BK-000036', 'Your booking for Roxon Residences — Unit Unit 5 (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-19 18:04:39'),
(123, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000036 for Roxon Residences — Unit Unit 5 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-19 18:05:09'),
(124, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000036 at Roxon Residences — Unit Unit 5 is now complete.', 'pages/user/bookings.php', 1, '2026-04-19 18:07:41'),
(125, 4, 'booking', 'New booking: BK-000037', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:10:04'),
(126, 15, 'booking', 'New booking: BK-000037', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:10:04'),
(127, 12, 'booking', 'Booking submitted: BK-000037', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-19 18:10:04'),
(128, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000037 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-19 18:10:31'),
(129, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000037 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 1, '2026-04-19 18:13:10'),
(130, 4, 'booking', 'New booking: BK-000038', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:20:10'),
(131, 15, 'booking', 'New booking: BK-000038', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:20:10'),
(132, 12, 'booking', 'Booking submitted: BK-000038', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-19 18:20:10'),
(133, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000038 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-19 18:20:30'),
(134, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000038 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 1, '2026-04-19 18:21:17'),
(135, 4, 'booking', 'New booking: BK-000039', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:31:06'),
(136, 15, 'booking', 'New booking: BK-000039', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:31:06'),
(137, 12, 'booking', 'Booking submitted: BK-000039', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 0, '2026-04-19 18:31:06'),
(138, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000039 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 0, '2026-04-19 18:31:21'),
(139, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000039 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 0, '2026-04-19 18:34:33'),
(140, 4, 'booking', 'New booking: BK-000040', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:34:54'),
(141, 15, 'booking', 'New booking: BK-000040', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit A · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:34:54'),
(142, 12, 'booking', 'Booking submitted: BK-000040', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 0, '2026-04-19 18:34:54'),
(143, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000040 for Ocean Garden Villas — Unit Unit A has been confirmed.', 'pages/user/bookings.php', 0, '2026-04-19 18:35:03'),
(144, 12, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000040 at Ocean Garden Villas — Unit Unit A is now complete.', 'pages/user/bookings.php', 0, '2026-04-19 18:36:01'),
(145, 4, 'booking', 'New booking: BK-000041', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit H · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:36:26'),
(146, 15, 'booking', 'New booking: BK-000041', 'Marlon Garcia booked Ocean Garden Villas — Unit Unit H · Apr 20–Apr 23, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-19 18:36:26'),
(147, 12, 'booking', 'Booking submitted: BK-000041', 'Your booking for Ocean Garden Villas — Unit Unit H (Apr 20–Apr 23, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 0, '2026-04-19 18:36:26'),
(148, 12, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000041 for Ocean Garden Villas — Unit Unit H has been confirmed.', 'pages/user/bookings.php', 0, '2026-04-19 18:36:38'),
(149, 4, 'booking', 'New booking: BK-000042', 'Jr Marticio booked Ocean Garden Villas — Unit Unit A · Apr 25–Apr 28, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-24 17:53:52'),
(150, 15, 'booking', 'New booking: BK-000042', 'Jr Marticio booked Ocean Garden Villas — Unit Unit A · Apr 25–Apr 28, 2026 (3 nights)', 'pages/admin/reservations.php', 0, '2026-04-24 17:53:52'),
(151, 21, 'booking', 'Booking submitted: BK-000042', 'Your booking for Ocean Garden Villas — Unit Unit A (Apr 25–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-24 17:53:52'),
(152, 4, 'booking', 'Booking cancelled: BK-000042', 'Jr Marticio cancelled their booking for Ocean Garden Villas — Unit Unit A.', 'pages/admin/reservations.php', 0, '2026-04-25 03:55:13'),
(153, 15, 'booking', 'Booking cancelled: BK-000042', 'Jr Marticio cancelled their booking for Ocean Garden Villas — Unit Unit A.', 'pages/admin/reservations.php', 0, '2026-04-25 03:55:13'),
(154, 21, 'booking', 'Booking cancelled: BK-000042', 'Your booking for Ocean Garden Villas — Unit Unit A has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 03:55:13'),
(155, 4, 'booking', 'New booking: BK-000043', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Oct 26, 2026 (183 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 04:43:45'),
(156, 15, 'booking', 'New booking: BK-000043', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Oct 26, 2026 (183 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 04:43:45'),
(157, 21, 'booking', 'Booking submitted: BK-000043', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Oct 26, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 04:43:45'),
(158, 4, 'booking', 'Booking cancelled: BK-000043', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 12:01:36'),
(159, 15, 'booking', 'Booking cancelled: BK-000043', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 12:01:36'),
(160, 21, 'booking', 'Booking cancelled: BK-000043', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 12:01:36'),
(161, 4, 'booking', 'New booking: BK-000044', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 26, 2027 (365 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:16'),
(162, 15, 'booking', 'New booking: BK-000044', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 26, 2027 (365 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:16'),
(163, 21, 'booking', 'Booking submitted: BK-000044', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 26, 2027) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 12:14:17'),
(164, 4, 'booking', 'Booking cancelled: BK-000044', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:39'),
(165, 15, 'booking', 'Booking cancelled: BK-000044', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:39'),
(166, 21, 'booking', 'Booking cancelled: BK-000044', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 12:14:39'),
(167, 4, 'booking', 'New booking: BK-000045', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 26–Apr 26, 2027 (365 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:54'),
(168, 15, 'booking', 'New booking: BK-000045', 'Jr Marticio booked Casa De Primera — Unit Unit A18 · Apr 26–Apr 26, 2027 (365 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 12:14:54'),
(169, 21, 'booking', 'Booking submitted: BK-000045', 'Your booking for Casa De Primera — Unit Unit A18 (Apr 26–Apr 26, 2027) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 12:14:54'),
(170, 4, 'booking', 'Booking cancelled: BK-000045', 'Jr Marticio cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-25 12:15:02'),
(171, 15, 'booking', 'Booking cancelled: BK-000045', 'Jr Marticio cancelled their booking for Casa De Primera — Unit Unit A18.', 'pages/admin/reservations.php', 0, '2026-04-25 12:15:02'),
(172, 21, 'booking', 'Booking cancelled: BK-000045', 'Your booking for Casa De Primera — Unit Unit A18 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 12:15:02'),
(173, 4, 'booking', 'New booking: BK-000046', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 14:15:44'),
(174, 15, 'booking', 'New booking: BK-000046', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 14:15:44'),
(175, 21, 'booking', 'Booking submitted: BK-000046', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 27, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 14:15:44'),
(176, 4, 'booking', 'Booking cancelled: BK-000046', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 14:34:24'),
(177, 15, 'booking', 'Booking cancelled: BK-000046', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 14:34:24'),
(178, 21, 'booking', 'Booking cancelled: BK-000046', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 14:34:24'),
(179, 4, 'booking', 'New booking: BK-000047', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 14:34:43'),
(180, 15, 'booking', 'New booking: BK-000047', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 14:34:43'),
(181, 21, 'booking', 'Booking submitted: BK-000047', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 27, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 14:34:43'),
(182, 4, 'booking', 'Booking cancelled: BK-000047', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 19:14:27'),
(183, 15, 'booking', 'Booking cancelled: BK-000047', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 19:14:27'),
(184, 21, 'booking', 'Booking cancelled: BK-000047', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 19:14:27'),
(185, 4, 'booking', 'New booking: BK-000048', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:14:40'),
(186, 15, 'booking', 'New booking: BK-000048', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:14:40'),
(187, 21, 'booking', 'Booking submitted: BK-000048', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 27, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 19:14:41'),
(188, 4, 'booking', 'Booking cancelled: BK-000048', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 19:15:07'),
(189, 15, 'booking', 'Booking cancelled: BK-000048', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-25 19:15:07'),
(190, 21, 'booking', 'Booking cancelled: BK-000048', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 19:15:07'),
(191, 4, 'booking', 'New booking: BK-000049', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:15:13'),
(192, 15, 'booking', 'New booking: BK-000049', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:15:13'),
(193, 21, 'booking', 'Booking submitted: BK-000049', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 27, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 19:15:13'),
(194, 21, 'booking', 'Booking cancelled', 'Booking BK-000049 for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-25 19:18:46'),
(195, 4, 'booking', 'New booking: BK-000050', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:18:58'),
(196, 15, 'booking', 'New booking: BK-000050', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 26–Apr 27, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-25 19:18:58'),
(197, 21, 'booking', 'Booking submitted: BK-000050', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 26–Apr 27, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-25 19:18:58'),
(198, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000050 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-25 19:20:55'),
(199, 21, 'loyalty', 'Points Earned!', 'You earned 100 loyalty points from your stay!', NULL, 1, '2026-04-26 06:26:03'),
(200, 4, 'booking', 'New booking: BK-000051', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:33:25'),
(201, 15, 'booking', 'New booking: BK-000051', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:33:25'),
(202, 21, 'booking', 'Booking submitted: BK-000051', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:33:25'),
(203, 4, 'booking', 'Booking cancelled: BK-000051', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:37:34'),
(204, 15, 'booking', 'Booking cancelled: BK-000051', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:37:34'),
(205, 21, 'booking', 'Booking cancelled: BK-000051', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 06:37:34'),
(206, 4, 'booking', 'New booking: BK-000052', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:37:50'),
(207, 15, 'booking', 'New booking: BK-000052', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:37:50'),
(208, 21, 'booking', 'Booking submitted: BK-000052', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:37:50'),
(209, 4, 'booking', 'Booking cancelled: BK-000052', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:38:01'),
(210, 15, 'booking', 'Booking cancelled: BK-000052', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:38:01'),
(211, 21, 'booking', 'Booking cancelled: BK-000052', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 06:38:01'),
(212, 4, 'booking', 'New booking: BK-000053', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:38:10'),
(213, 15, 'booking', 'New booking: BK-000053', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:38:10'),
(214, 21, 'booking', 'Booking submitted: BK-000053', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:38:10'),
(215, 4, 'booking', 'Booking cancelled: BK-000053', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:40:00'),
(216, 15, 'booking', 'Booking cancelled: BK-000053', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:40:00'),
(217, 21, 'booking', 'Booking cancelled: BK-000053', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 06:40:00'),
(218, 4, 'booking', 'New booking: BK-000054', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:40:08'),
(219, 15, 'booking', 'New booking: BK-000054', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:40:08'),
(220, 21, 'booking', 'Booking submitted: BK-000054', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:40:08'),
(221, 4, 'booking', 'Booking cancelled: BK-000054', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:39'),
(222, 15, 'booking', 'Booking cancelled: BK-000054', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:39'),
(223, 21, 'booking', 'Booking cancelled: BK-000054', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 06:42:39'),
(224, 4, 'booking', 'New booking: BK-000055', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:47'),
(225, 15, 'booking', 'New booking: BK-000055', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:47'),
(226, 21, 'booking', 'Booking submitted: BK-000055', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:42:47'),
(227, 4, 'booking', 'Booking cancelled: BK-000055', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:54'),
(228, 15, 'booking', 'Booking cancelled: BK-000055', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 06:42:54'),
(229, 21, 'booking', 'Booking cancelled: BK-000055', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 06:42:54'),
(230, 4, 'booking', 'New booking: BK-000056', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:56:07'),
(231, 15, 'booking', 'New booking: BK-000056', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 06:56:07'),
(232, 21, 'booking', 'Booking submitted: BK-000056', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 06:56:07'),
(233, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000056 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 06:56:59'),
(234, 21, 'loyalty', 'Points Earned!', 'You earned 100 loyalty points from your stay!', NULL, 1, '2026-04-26 07:02:20'),
(235, 4, 'booking', 'New booking: BK-000057', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 07:13:31'),
(236, 15, 'booking', 'New booking: BK-000057', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 07:13:31'),
(237, 21, 'booking', 'Booking submitted: BK-000057', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 07:13:31'),
(238, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000057 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 07:14:25'),
(239, 21, 'loyalty', 'Points Earned!', 'You earned 100 loyalty points from your stay!', NULL, 1, '2026-04-26 07:16:51'),
(240, 4, 'booking', 'New booking: BK-000058', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 07:40:28'),
(241, 15, 'booking', 'New booking: BK-000058', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 07:40:28'),
(242, 21, 'booking', 'Booking submitted: BK-000058', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 07:40:28'),
(243, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000058 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 07:40:41'),
(244, 15, 'message', 'New message from Jr Marticio', 'asdasd', 'pages/admin/messages.php', 0, '2026-04-26 07:53:01'),
(245, 15, 'message', 'New message from Jr Marticio', 'asdasd', 'pages/admin/messages.php', 0, '2026-04-26 07:53:10'),
(246, 21, 'loyalty', 'Points Earned!', 'You earned 300 loyalty points from your stay!', NULL, 1, '2026-04-26 07:54:43'),
(247, 4, 'booking', 'New booking: BK-000059', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:04:47'),
(248, 15, 'booking', 'New booking: BK-000059', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:04:47'),
(249, 21, 'booking', 'Booking submitted: BK-000059', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 08:04:47'),
(250, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000059 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 08:04:55'),
(251, 21, 'loyalty', 'Points Earned!', 'You earned 200 loyalty points from your stay!', NULL, 1, '2026-04-26 08:09:52'),
(252, 4, 'booking', 'New booking: BK-000060', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:10:09'),
(253, 15, 'booking', 'New booking: BK-000060', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:10:09'),
(254, 21, 'booking', 'Booking submitted: BK-000060', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 08:10:09'),
(255, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000060 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 08:10:14'),
(256, 4, 'booking', 'Booking cancelled: BK-000060', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 08:12:13'),
(257, 15, 'booking', 'Booking cancelled: BK-000060', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 08:12:13'),
(258, 21, 'booking', 'Booking cancelled: BK-000060', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 08:12:13'),
(259, 4, 'booking', 'New booking: BK-000061', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:12:19'),
(260, 15, 'booking', 'New booking: BK-000061', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 08:12:19'),
(261, 21, 'booking', 'Booking submitted: BK-000061', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 08:12:19'),
(262, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000061 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 08:12:37'),
(263, 4, 'booking', 'Booking cancelled: BK-000061', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 15:33:43'),
(264, 15, 'booking', 'Booking cancelled: BK-000061', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 15:33:43'),
(265, 21, 'booking', 'Booking cancelled: BK-000061', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 15:33:43'),
(266, 4, 'booking', 'New booking: BK-000062', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:08:27'),
(267, 15, 'booking', 'New booking: BK-000062', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:08:27'),
(268, 21, 'booking', 'Booking submitted: BK-000062', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:08:27'),
(269, 4, 'booking', 'Booking cancelled: BK-000062', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 16:21:42'),
(270, 15, 'booking', 'Booking cancelled: BK-000062', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 16:21:42'),
(271, 21, 'booking', 'Booking cancelled: BK-000062', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 16:21:42'),
(272, 4, 'booking', 'New booking: BK-000063', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:22:35'),
(273, 15, 'booking', 'New booking: BK-000063', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:22:35'),
(274, 21, 'booking', 'Booking submitted: BK-000063', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:22:35'),
(275, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000063 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 16:30:58'),
(276, 21, 'loyalty', 'Points Earned!', 'You earned 200 loyalty points from your stay!', NULL, 1, '2026-04-26 16:44:02'),
(277, 4, 'booking', 'New booking: BK-000064', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:52:37'),
(278, 15, 'booking', 'New booking: BK-000064', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:52:37'),
(279, 21, 'booking', 'Booking submitted: BK-000064', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:52:37'),
(280, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000064 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 16:52:50'),
(281, 21, 'loyalty', 'Points Earned!', 'You earned 300 loyalty points from your stay!', NULL, 1, '2026-04-26 16:54:10'),
(282, 4, 'booking', 'New booking: BK-000065', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:13'),
(283, 15, 'booking', 'New booking: BK-000065', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:13'),
(284, 21, 'booking', 'Booking submitted: BK-000065', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:55:13'),
(285, 4, 'booking', 'Booking cancelled: BK-000065', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:27'),
(286, 15, 'booking', 'Booking cancelled: BK-000065', 'Jr Marticio cancelled their booking for Casa Camilla Beachfront — Unit Unit 10.', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:27'),
(287, 21, 'booking', 'Booking cancelled: BK-000065', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 has been cancelled.', 'pages/user/bookings.php', 1, '2026-04-26 16:55:27'),
(288, 4, 'booking', 'New booking: BK-000066', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:36');
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `link`, `is_read`, `created_at`) VALUES
(289, 15, 'booking', 'New booking: BK-000066', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:55:36'),
(290, 21, 'booking', 'Booking submitted: BK-000066', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:55:36'),
(291, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000066 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 16:55:55'),
(292, 21, 'loyalty', 'Points Earned!', 'You earned 100 loyalty points from your stay!', NULL, 1, '2026-04-26 16:56:32'),
(293, 4, 'booking', 'New booking: BK-000067', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:58:42'),
(294, 15, 'booking', 'New booking: BK-000067', 'Jr Marticio booked Casa Camilla Beachfront — Unit Unit 10 · Apr 27–Apr 28, 2026 (1 nights)', 'pages/admin/reservations.php', 0, '2026-04-26 16:58:42'),
(295, 21, 'booking', 'Booking submitted: BK-000067', 'Your booking for Casa Camilla Beachfront — Unit Unit 10 (Apr 27–Apr 28, 2026) is pending admin confirmation.', 'pages/user/bookings.php', 1, '2026-04-26 16:58:42'),
(296, 21, 'booking', 'Your booking is confirmed! 🎉', 'Booking BK-000067 for Casa Camilla Beachfront — Unit Unit 10 has been confirmed.', 'pages/user/bookings.php', 1, '2026-04-26 16:58:51'),
(297, 21, 'booking', 'Stay completed — thanks for visiting!', 'Booking BK-000067 at Casa Camilla Beachfront — Unit Unit 10 is now complete.', 'pages/user/bookings.php', 1, '2026-04-26 16:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('paid','pending','late') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `payment_date`, `amount_paid`, `payment_method`, `payment_status`, `notes`, `created_at`) VALUES
(8, 50, '2026-04-26', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-25 19:20:55'),
(9, 56, '2026-04-26', 1000.00, 'Cash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 06:56:59'),
(10, 57, '2026-04-26', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 07:14:25'),
(11, 58, '2026-04-26', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 07:40:41'),
(13, 59, '2026-04-26', 3000.00, 'GCash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 08:04:55'),
(14, 60, '2026-04-26', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 08:10:14'),
(15, 61, '2026-04-26', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 08:12:37'),
(16, 63, '2026-04-27', 1000.00, 'GCash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 16:30:58'),
(17, 64, '2026-04-27', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 16:52:50'),
(18, 66, '2026-04-27', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 16:55:55'),
(19, 67, '2026-04-27', 1000.00, 'gcash', 'paid', 'Auto-created on booking confirmation', '2026-04-26 16:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('card','ewallet') NOT NULL DEFAULT 'card',
  `provider` varchar(50) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `last4` varchar(4) DEFAULT NULL,
  `expiry_month` int(2) DEFAULT NULL,
  `expiry_year` int(4) DEFAULT NULL,
  `holder_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `user_id`, `type`, `provider`, `label`, `last4`, `expiry_month`, `expiry_year`, `holder_name`, `account_number`, `is_default`, `is_active`, `created_at`) VALUES
(1, 12, 'card', 'Card', 'Card ···· 8283', '8283', 9, 2028, 'Marlon Garcia', NULL, 1, 0, '2026-03-26 19:13:15'),
(2, 12, 'ewallet', 'GCash', 'GCash', NULL, NULL, NULL, NULL, '09497680949', 0, 1, '2026-03-26 19:13:31'),
(3, 12, 'card', 'Card', 'Card ···· 1232', '1232', 9, 2028, 'Marlon Garcia', NULL, 1, 0, '2026-04-03 10:03:37');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL,
  `property_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `property_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`property_id`, `property_name`, `address`, `city`, `state`, `zip`, `status`, `property_type`, `description`, `photo`, `created_at`, `latitude`, `longitude`) VALUES
(24, 'Casa Camilla Beachfront', 'Boracay Beachfront Path, Malay, Aklan 5608', 'Malay', 'Aklan', '5608', 'Active', 'Residential', NULL, NULL, '2026-04-25 13:53:52', 11.9491290, 121.9314650);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_units`
--

CREATE TABLE `saved_units` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_units`
--

INSERT INTO `saved_units` (`id`, `user_id`, `unit_id`, `created_at`) VALUES
(25, 21, 20, '2026-04-26 16:06:51');

-- --------------------------------------------------------

--
-- Table structure for table `staff_permissions`
--

CREATE TABLE `staff_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_messages`
--

INSERT INTO `support_messages` (`id`, `ticket_id`, `user_id`, `body`, `is_admin`, `created_at`) VALUES
(1, 1, 14, 'I am trying to change my check-in date but the system is not allowing it.', 0, '2026-03-20 10:00:00'),
(2, 1, 4, 'Hello! We have updated your booking dates as requested. Please check your bookings page.', 1, '2026-03-20 11:30:00'),
(3, 2, 2, 'Hi, is it possible to check out at 2pm instead of 12pm?', 0, '2026-03-22 08:30:00'),
(4, 3, 12, 'asdasd', 0, '2026-03-26 13:46:44'),
(5, 4, 12, 'asdasd', 0, '2026-03-26 13:46:46'),
(11, 10, 21, 'Hi Support Team,\r\n\r\nI need help regarding booking BK-000029.\r\n\r\nConcern: Nothing', 0, '2026-04-14 05:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`ticket_id`, `user_id`, `category`, `subject`, `priority`, `status`, `created_at`, `updated_at`) VALUES
(1, 14, 'Booking & Reservations', 'Unable to modify booking dates', 'medium', 'resolved', '2026-03-20 10:00:00', '2026-03-26 04:10:48'),
(2, 2, 'Check-in & Check-out', 'Request for late check-out', 'low', 'open', '2026-03-22 08:30:00', '2026-03-26 04:10:48'),
(3, 12, 'Payment Issue', 'assdas', '', 'open', '2026-03-26 13:46:44', '2026-03-26 13:46:44'),
(4, 12, 'Payment Issue', 'assdas', '', 'open', '2026-03-26 13:46:46', '2026-03-26 13:46:46'),
(10, 21, 'Booking Inquiry', '[BK-000029] Booking concern', 'medium', 'open', '2026-04-14 05:44:17', '2026-04-14 05:44:17');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `move_in_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `full_name`, `phone`, `email`, `move_in_date`, `created_at`) VALUES
(1, 'John Michael Arcido', NULL, 'joda.arcido.ui@phinmaed.com', '2026-03-23', '2026-03-22 13:04:43'),
(2, 'Marlon Pogi', NULL, 'marlonvillegas86@gmail.com', '2026-03-27', '2026-03-26 04:23:31'),
(3, 'Bull Dogz', NULL, 'bulldogz0923@gmail.com', '2026-04-14', '2026-04-13 15:16:42');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `type` enum('Income','Expense') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `reference_no`, `description`, `category`, `type`, `amount`, `transaction_date`, `property_id`, `booking_id`, `recorded_by`, `notes`, `created_at`) VALUES
(1, 'TXN-001', 'Booking #2 payment - Unit A18', 'Room Revenue', 'Income', 450000.00, '2026-03-23', 14, 2, NULL, NULL, '2026-03-26 04:10:48'),
(2, 'TXN-002', 'Booking #5 payment - Unit 10', 'Room Revenue', 'Income', 150000.00, '2026-03-23', 15, 5, NULL, NULL, '2026-03-26 04:10:48'),
(3, 'TXN-003', 'Utilities - Casa De Primera', 'Utilities', 'Expense', 8500.00, '2026-03-20', 14, NULL, NULL, NULL, '2026-03-26 04:10:48'),
(4, 'TXN-004', 'Cleaning supplies', 'Maintenance', 'Expense', 2200.00, '2026-03-21', 15, NULL, NULL, NULL, '2026-03-26 04:10:48'),
(5, 'TXN-005', 'Internet subscription', 'Utilities', 'Expense', 3500.00, '2026-03-15', 18, NULL, NULL, NULL, '2026-03-26 04:10:48'),
(6, 'TXN-1774520596', 'Maintenance salaries', 'Salaries', 'Income', 5000.00, '2026-03-26', 15, NULL, 11, '', '2026-03-26 10:23:16'),
(7, 'EXP-20260326-6', 'Broken Door', 'Maintenance', 'Expense', 1000.00, '0000-00-00', 17, NULL, 11, NULL, '2026-03-26 17:14:19'),
(8, 'EXP-20260326-7', 'Water Bill', 'Utilities', 'Expense', 5000.00, '0000-00-00', 15, NULL, 11, NULL, '2026-03-26 17:17:37'),
(17, 'TXN-1774546583', 'Water Bill', 'Utilities', 'Expense', 1123123.00, '2026-03-26', 14, NULL, NULL, 'Logged via Expense Module', '2026-03-26 17:36:23'),
(18, 'TXN-1774546732', 'Aircon', 'Maintenance', 'Expense', 13123.00, '2026-03-26', 14, NULL, NULL, 'Logged via Expense Module', '2026-03-26 17:38:52'),
(19, 'EXP-19-1774548850', 'Water Bill', 'Maintenance', 'Expense', 1000.00, '2026-03-26', 18, NULL, NULL, 'Logged via Expense Module', '2026-03-26 18:14:10'),
(20, 'TXN-1774550276', 'Maintenance salaries', 'Room Revenue', 'Income', 1000.00, '2026-03-26', 15, NULL, 11, '', '2026-03-26 18:37:56'),
(21, 'TXN-1774550539', 'Maintenance salaries', 'Room Revenue', 'Income', 5000.00, '2026-03-26', NULL, NULL, 11, '', '2026-03-26 18:42:19'),
(22, 'EXP-20-1774550699', 'Water Bill', 'Maintenance', 'Expense', 5000.00, '2026-03-28', 15, NULL, NULL, 'Logged via Expense Module', '2026-03-26 18:44:59'),
(23, 'TXN-BK-29', 'Booking #29 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-14', NULL, 29, NULL, NULL, '2026-04-14 05:47:26'),
(24, 'TXN-BK-30', 'Booking #30 payment', 'Room Revenue', 'Income', 150000.00, '2026-04-14', NULL, 30, NULL, NULL, '2026-04-14 05:49:49'),
(25, 'TXN-BK-31', 'Booking #31 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-16', NULL, 31, NULL, NULL, '2026-04-16 06:57:44'),
(26, 'TXN-BK-34', 'Booking #34 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 34, NULL, NULL, '2026-04-19 17:39:00'),
(27, 'TXN-BK-35', 'Booking #35 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 35, NULL, NULL, '2026-04-19 17:58:30'),
(28, 'TXN-BK-36', 'Booking #36 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 36, NULL, NULL, '2026-04-19 18:07:41'),
(29, 'TXN-BK-37', 'Booking #37 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 37, NULL, NULL, '2026-04-19 18:13:10'),
(30, 'TXN-BK-38', 'Booking #38 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 38, NULL, NULL, '2026-04-19 18:21:17'),
(31, 'TXN-BK-39', 'Booking #39 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 39, NULL, NULL, '2026-04-19 18:34:33'),
(32, 'TXN-BK-40', 'Booking #40 payment', 'Room Revenue', 'Income', 75000.00, '2026-04-20', NULL, 40, NULL, NULL, '2026-04-19 18:36:01'),
(33, 'EXP-21-1777049346', 'Aircon Repair', 'Maintenance', 'Expense', 1000.00, '2026-04-24', 15, NULL, NULL, 'Logged via Expense Module', '2026-04-24 16:49:06'),
(34, 'EXP-22-1777049485', 'Front Desk Salary', 'Salaries', 'Expense', 5000.00, '2026-04-24', 17, NULL, NULL, 'Logged via Expense Module', '2026-04-24 16:51:25'),
(35, 'TXN-BK-57', 'Booking #57 payment', 'Room Revenue', 'Income', 1000.00, '2026-04-26', NULL, 57, NULL, NULL, '2026-04-26 07:16:51'),
(36, 'PMT-12', 'Payment #12 for Booking #58', 'Room Revenue', 'Income', 2000.00, '2026-04-26', NULL, 58, NULL, NULL, '2026-04-26 07:54:20'),
(37, 'TXN-BK-58', 'Booking #58 payment', 'Room Revenue', 'Income', 3000.00, '2026-04-26', 24, 58, NULL, NULL, '2026-04-26 07:54:43'),
(38, 'TXN-BK-59', 'Booking #59 payment', 'Room Revenue', 'Income', 2000.00, '2026-04-26', 24, 59, NULL, NULL, '2026-04-26 08:09:52'),
(39, 'TXN-BK-63', 'Booking #63 payment', 'Room Revenue', 'Income', 2000.00, '2026-04-27', 24, 63, NULL, NULL, '2026-04-26 16:44:02'),
(40, 'TXN-BK-64', 'Booking #64 payment', 'Room Revenue', 'Income', 3000.00, '2026-04-27', 24, 64, NULL, NULL, '2026-04-26 16:54:10'),
(41, 'TXN-BK-66', 'Booking #66 payment', 'Room Revenue', 'Income', 1000.00, '2026-04-27', 24, 66, NULL, NULL, '2026-04-26 16:56:32'),
(42, 'TXN-BK-67', 'Booking #67 payment', 'Room Revenue', 'Income', 1000.00, '2026-04-27', 24, 67, NULL, NULL, '2026-04-26 16:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `unit_number` varchar(50) DEFAULT NULL,
  `unit_name` varchar(100) DEFAULT NULL,
  `unit_type` varchar(50) DEFAULT NULL,
  `floor` int(11) DEFAULT NULL,
  `rent_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('occupied','vacant','maintenance') DEFAULT 'vacant',
  `tenant_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_guests` int(3) NOT NULL DEFAULT 2,
  `bedrooms` int(3) DEFAULT 1,
  `bathrooms` int(3) DEFAULT 1,
  `area_sqm` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `property_id`, `tenant_id`, `unit_number`, `unit_name`, `unit_type`, `floor`, `rent_amount`, `status`, `tenant_name`, `description`, `max_guests`, `bedrooms`, `bathrooms`, `area_sqm`, `created_at`) VALUES
(20, 24, 3, 'Unit 10', '', '', 2, 1000.00, 'vacant', 'Jr Marticio', '', 2, 1, 1, NULL, '2026-04-25 13:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `unit_amenities`
--

CREATE TABLE `unit_amenities` (
  `id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `amenity_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_amenities`
--

INSERT INTO `unit_amenities` (`id`, `unit_id`, `amenity_id`) VALUES
(7, 11, 6),
(8, 11, 8),
(9, 13, 2),
(13, 17, 9),
(21, 19, 12),
(22, 20, 13);

-- --------------------------------------------------------

--
-- Table structure for table `unit_images`
--

CREATE TABLE `unit_images` (
  `image_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_images`
--

INSERT INTO `unit_images` (`image_id`, `unit_id`, `image_path`, `sort_order`, `created_at`) VALUES
(16, 20, 'uploads/units/20/unit_69ecc79346f566.97171576.jpg', 0, '2026-04-25 13:54:27'),
(17, 20, 'uploads/units/20/unit_69ecc793494ad3.71458667.jpg', 1, '2026-04-25 13:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birthday` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','admin','manager','frontdesk','accounting','maintenance') NOT NULL DEFAULT 'user',
  `verification_status` enum('Not Verified','Verified') NOT NULL DEFAULT 'Not Verified',
  `login_attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `phone`, `nationality`, `birthday`, `gender`, `password`, `created_at`, `role`, `verification_status`, `login_attempts`, `last_attempt`, `is_locked`, `locked_until`, `is_blacklisted`, `is_active`, `last_login`, `profile_photo`, `address`) VALUES
(2, 'Jr', 'Marticio', 'jr@gmail.com', '09876543211', 'Filipino', '2004-09-08', 'Male', '$2y$10$SLpufN.25x34BMQG0l.Wou8NQGxCPbxv6T0L0G2kqhrfqByh/rQXa', '2026-03-18 14:34:21', 'user', 'Not Verified', 0, '2026-03-21 21:05:33', 0, NULL, 0, 1, NULL, NULL, NULL),
(4, 'Myra', 'Jonson', 'myrajonson@gmail.com', '09876543210', NULL, NULL, NULL, '$2y$10$wXIDzETTGkxbTwkIbBaKDOzxIUvFq2DSwEu2AqILaC8rWofz1pZXa', '2026-03-20 11:30:54', 'admin', 'Not Verified', 0, '2026-03-21 21:10:44', 0, NULL, 0, 1, NULL, NULL, NULL),
(6, 'Sonny', 'Wagas', 'sonny@phinmaed.com', '09324123512', NULL, NULL, NULL, '$2y$10$/Ry3y5remClD8wUdj8/zIuf3CPp8vJqh.3KTFCHrJsu5ZVpvGPazi', '2026-03-21 15:28:14', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(12, 'Marlon', 'Garcia', 'marlonvillegas86@gmail.com', '09497695123', 'Filipino', '2004-09-23', 'Female', '$2y$10$hlRbO5WSPVIa00gygt.uyemTYt6Tx12yb3qejqdWmPu/42hJYEZPO', '2026-03-21 15:48:32', 'user', 'Verified', 3, '2026-04-25 20:59:55', 0, NULL, 0, 1, NULL, NULL, NULL),
(13, 'Sean', 'Peniero', 'sean@gmail.com', '09235612571', NULL, NULL, NULL, '$2y$10$RKfNhMcgrvbBLghTdvbgSuyf4qUQq.8xdjaZY3cnbpNUPSqcbmTyS', '2026-03-22 05:34:17', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(14, 'John Michael', 'Arcido', 'joda.arcido.ui@phinmaed.com', '09497695099', 'Filipino', '', 'Female', '$2y$10$.oxDJrUYsLqb2y9yOipeWeAB7rKpVgX9U7.YbYIi9p4XoWWb4BYOC', '2026-03-22 11:14:33', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(15, 'Marlon', 'Garcia', 'marlonvillegas00@gmail.com', '09497680949', NULL, NULL, NULL, '$2y$10$asnHuGdUe0YHEn11asURKOuabXPBK5NCcawISnielGgsPvrZM//GS', '2026-04-03 10:13:54', 'admin', 'Not Verified', 1, '2026-04-14 13:44:54', 0, NULL, 0, 1, NULL, 'assets/images/profile_photos/admin_15_1777288006.jpg', 'Boracay'),
(16, 'Marlon', 'Bulldog', 'bulldogz@gmail.com', '0987654512', NULL, NULL, NULL, '$2y$10$VYBJ2pSLgSoeMHJNi5QoOOMVGyo9.Zz7bjrUMOrPq/TZ3pQJ0WpL2', '2026-04-13 10:23:37', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(18, 'Tony', 'Chopper', 'tonychopper@gmail.com', '09572362513', NULL, NULL, NULL, '$2y$10$.c520Yb4rA7WRFG7yZ/Kk.yq.DfFuuzhABN.kE58Aeu.djFwQi7kO', '2026-04-13 14:57:19', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(19, 'Sonny', 'Wagas', 'soes@gmail.com', '09497609836', NULL, NULL, NULL, '$2y$10$vWDoRLZ4Jdc6BuQvU6fOwOuUI5.E0qmzEkRzgeI082yIYqw.DD8NW', '2026-04-13 14:58:18', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(20, 'Sean', 'Peniero', 'sean123@gmail.com', '09876554289', NULL, NULL, NULL, '$2y$10$jxAYWcaWpgI.SO1HLFDI/e7pxYAy0ggoPCP6txIAFMiqzeuVqCvQG', '2026-04-13 14:58:45', 'user', 'Not Verified', 0, NULL, 0, NULL, 0, 1, NULL, NULL, NULL),
(21, 'Jr', 'Marticio', 'bulldogz0923@gmail.com', '9334012641', 'Afghanistan', '', 'Male', '$2y$10$Ng4qpD/PPI2vS9u83NC34eAd8/bJ2PsJ/tKaAYU9JWX7d450c9Qvi', '2026-04-14 05:30:30', 'user', 'Verified', 0, NULL, 0, NULL, 0, 1, NULL, 'uploads/profile_photos/user_21_1776144858.jpg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notif_booking_confirm` tinyint(1) DEFAULT 1,
  `notif_checkin_remind` tinyint(1) DEFAULT 1,
  `notif_promotions` tinyint(1) DEFAULT 0,
  `notif_loyalty` tinyint(1) DEFAULT 1,
  `notif_newsletter` tinyint(1) DEFAULT 0,
  `notif_sms` tinyint(1) DEFAULT 0,
  `privacy_profile` enum('public','private') DEFAULT 'private',
  `privacy_activity` tinyint(1) DEFAULT 0,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'Asia/Manila',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `push_inapp_alerts` tinyint(1) DEFAULT 1,
  `push_checkout_reminder` tinyint(1) DEFAULT 1,
  `push_room_availability` tinyint(1) DEFAULT 0,
  `privacy_share_history` tinyint(1) DEFAULT 0,
  `privacy_recommendations` tinyint(1) DEFAULT 1,
  `privacy_analytics` tinyint(1) DEFAULT 1,
  `data_export_requested_at` datetime DEFAULT NULL,
  `last_session_action_at` datetime DEFAULT NULL,
  `active_sessions_count` int(11) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `notif_booking_confirm`, `notif_checkin_remind`, `notif_promotions`, `notif_loyalty`, `notif_newsletter`, `notif_sms`, `privacy_profile`, `privacy_activity`, `two_factor_enabled`, `language`, `timezone`, `updated_at`, `push_inapp_alerts`, `push_checkout_reminder`, `push_room_availability`, `privacy_share_history`, `privacy_recommendations`, `privacy_analytics`, `data_export_requested_at`, `last_session_action_at`, `active_sessions_count`) VALUES
(1, 14, 1, 1, 0, 1, 0, 0, 'private', 0, 0, 'en', 'Asia/Manila', '2026-03-26 04:10:48', 1, 1, 0, 0, 1, 1, NULL, NULL, 2),
(2, 2, 1, 1, 1, 1, 1, 0, 'private', 0, 0, 'en', 'Asia/Manila', '2026-03-26 04:10:48', 1, 1, 0, 0, 1, 1, NULL, NULL, 2),
(3, 12, 1, 1, 0, 1, 0, 0, 'private', 0, 0, 'en', 'Asia/Manila', '2026-03-26 04:21:46', 1, 1, 0, 0, 1, 1, NULL, NULL, 2),
(22, 21, 1, 1, 0, 1, 0, 0, 'private', 0, 0, 'en', 'Asia/Manila', '2026-04-14 05:43:12', 1, 1, 0, 0, 1, 1, NULL, NULL, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_setting_key` (`setting_key`);

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`amenity_id`),
  ADD KEY `idx_property` (`property_id`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blocked_date` (`blocked_date`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_booking_unit` (`unit_id`),
  ADD KEY `fk_booking_tenant` (`tenant_id`),
  ADD KEY `fk_booking_user` (`user_id`),
  ADD KEY `idx_bookings_updated_at` (`updated_at`),
  ADD KEY `idx_bookings_created_at` (`created_at`);

--
-- Indexes for table `booking_reviews`
--
ALTER TABLE `booking_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uq_booking_review` (`booking_id`),
  ADD KEY `idx_unit_rating` (`unit_id`,`rating`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `fk_expense_unit` (`unit_id`);

--
-- Indexes for table `financial_records`
--
ALTER TABLE `financial_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prop_year_month` (`property_id`,`year`,`month`),
  ADD KEY `year_month` (`year`,`month`);

--
-- Indexes for table `financial_reports`
--
ALTER TABLE `financial_reports`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invoice_no` (`invoice_no`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_booking` (`booking_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`property_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `saved_units`
--
ALTER TABLE `saved_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_saved` (`user_id`,`unit_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_permission` (`user_id`,`permission`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`tenant_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `fk_units_tenant` (`tenant_id`);

--
-- Indexes for table `unit_amenities`
--
ALTER TABLE `unit_amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_unit_amenity` (`unit_id`,`amenity_id`),
  ADD KEY `fk_ua_unit` (`unit_id`),
  ADD KEY `fk_ua_amenity` (`amenity_id`);

--
-- Indexes for table `unit_images`
--
ALTER TABLE `unit_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `idx_unit` (`unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_settings` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `amenity_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `booking_reviews`
--
ALTER TABLE `booking_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `financial_records`
--
ALTER TABLE `financial_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_reports`
--
ALTER TABLE `financial_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

--
-- AUTO_INCREMENT for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_units`
--
ALTER TABLE `saved_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `unit_amenities`
--
ALTER TABLE `unit_amenities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `unit_images`
--
ALTER TABLE `unit_images`
  MODIFY `image_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `amenities`
--
ALTER TABLE `amenities`
  ADD CONSTRAINT `fk_amenities_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_expense_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `loyalty_points`
--
ALTER TABLE `loyalty_points`
  ADD CONSTRAINT `fk_lp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `maintenance_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_units`
--
ALTER TABLE `saved_units`
  ADD CONSTRAINT `fk_saved_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_permissions`
--
ALTER TABLE `staff_permissions`
  ADD CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_smsg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_smsg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `fk_units_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE;

--
-- Constraints for table `unit_images`
--
ALTER TABLE `unit_images`
  ADD CONSTRAINT `fk_unit_images` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
