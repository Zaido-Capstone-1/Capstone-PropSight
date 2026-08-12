-- MySQL dump 10.13  Distrib 8.0.18, for Win64 (x86_64)
--
-- Host: tokaido.proxy.rlwy.net    Database: railway
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` text COLLATE utf8mb4_general_ci,
  `module` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_notifications`
--

DROP TABLE IF EXISTS `admin_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'message, new_booking, cancellation, refund, maintenance, support_ticket, id_verification',
  `ref_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'e.g. task-11, booking-5, msg-3',
  `text` text COLLATE utf8mb4_general_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ts` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_ref` (`admin_id`,`ref_id`),
  KEY `idx_admin_read` (`admin_id`,`is_read`),
  KEY `idx_ts` (`ts`),
  CONSTRAINT `fk_an_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_notifications`
--

LOCK TABLES `admin_notifications` WRITE;
/*!40000 ALTER TABLE `admin_notifications` DISABLE KEYS */;
INSERT INTO `admin_notifications` VALUES (175,1,'new_booking','booking-788060','New booking: BK-788060: Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 30–Aug 25, 2026 (','reservations.php?status=pending','2026-07-23 15:40:26',0),(176,1,'refund','refund-788060','Refund Request — BK-788060: A guest has requested a refund of ₱31,700.00 for booking BK-788060.','refunds.php','2026-07-23 16:02:09',0),(177,1,'new_booking','booking-392379','New booking: BK-392379: Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 25–Jul 31, 2026 (','reservations.php?status=pending','2026-07-23 16:15:56',0),(178,1,'new_booking','booking-218566','New booking #218566','reservations.php?status=pending','2026-07-23 16:31:14',0),(180,1,'new_booking','booking-374041','New booking: BK-374041: Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 25–Jul 31, 2026 (','reservations.php?status=pending','2026-07-24 01:23:39',0),(181,1,'support_ticket','ticket-1','New support ticket: asdasdasd','messages.php','2026-07-24 02:20:15',0),(182,1,'id_verification','id-4','ID submitted for review by user #4','id_verification.php','2026-07-24 05:41:26',0);
/*!40000 ALTER TABLE `admin_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_settings`
--

DROP TABLE IF EXISTS `admin_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `value` text COLLATE utf8mb4_general_ci,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_settings`
--

LOCK TABLES `admin_settings` WRITE;
/*!40000 ALTER TABLE `admin_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `amenities`
--

DROP TABLE IF EXISTS `amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `amenities` (
  `amenity_id` int NOT NULL AUTO_INCREMENT,
  `property_id` int NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `icon` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'security',
  `status` enum('available','unavailable','maintenance') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`amenity_id`),
  KEY `idx_property` (`property_id`),
  CONSTRAINT `fk_amenities_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `amenities`
--

LOCK TABLES `amenities` WRITE;
/*!40000 ALTER TABLE `amenities` DISABLE KEYS */;
/*!40000 ALTER TABLE `amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_rate_limits`
--

DROP TABLE IF EXISTS `api_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_rate_limits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier_endpoint` (`identifier`,`endpoint`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_cleanup` (`identifier`,`endpoint`,`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_rate_limits`
--

LOCK TABLES `api_rate_limits` WRITE;
/*!40000 ALTER TABLE `api_rate_limits` DISABLE KEYS */;
INSERT INTO `api_rate_limits` VALUES (38,'143.44.196.183, 152.233.15.123','login',1786504670,'143.44.196.183, 152.233.15.123');
/*!40000 ALTER TABLE `api_rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocked_dates`
--

DROP TABLE IF EXISTS `blocked_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocked_dates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `blocked_date` date NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blocked_date` (`blocked_date`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_bd_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocked_dates`
--

LOCK TABLES `blocked_dates` WRITE;
/*!40000 ALTER TABLE `blocked_dates` DISABLE KEYS */;
/*!40000 ALTER TABLE `blocked_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_reviews`
--

DROP TABLE IF EXISTS `booking_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_reviews` (
  `review_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uq_booking_review` (`booking_id`),
  KEY `idx_unit_rating` (`unit_id`,`rating`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_br_user` (`user_id`),
  CONSTRAINT `fk_br_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_br_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_br_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_reviews`
--

LOCK TABLES `booking_reviews` WRITE;
/*!40000 ALTER TABLE `booking_reviews` DISABLE KEYS */;
INSERT INTO `booking_reviews` VALUES (1,392379,8,3,5,'I like this room so much!','2026-07-23 16:26:06','2026-07-23 16:26:06');
/*!40000 ALTER TABLE `booking_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `tenant_id` int NOT NULL DEFAULT '1',
  `user_id` int NOT NULL,
  `checkin_date` date NOT NULL,
  `checkout_date` date NOT NULL,
  `guests` int NOT NULL DEFAULT '1',
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','active','completed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `special_requests` text COLLATE utf8mb4_general_ci,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `booking_source` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Direct',
  `payment_ref` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_notes` text COLLATE utf8mb4_general_ci,
  `paid_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `checkin_status` enum('pending','done') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `checkout_status` enum('pending','done') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `checkin_actual` datetime DEFAULT NULL,
  `checkout_actual` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`booking_id`),
  KEY `fk_booking_unit` (`unit_id`),
  KEY `fk_booking_tenant` (`tenant_id`),
  KEY `fk_booking_user` (`user_id`),
  KEY `idx_bookings_updated_at` (`updated_at`),
  KEY `idx_bookings_created_at` (`created_at`),
  CONSTRAINT `fk_booking_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=788061 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (218566,8,1,3,'2026-07-25','2026-07-31',6,8200.00,'completed',NULL,'Cash','Direct',NULL,NULL,NULL,'2026-07-23 16:31:28','pending','pending',NULL,NULL,'2026-07-23 16:31:14','2026-07-23 16:33:37'),(374041,8,1,3,'2026-07-25','2026-07-31',4,7200.00,'completed',NULL,'GCash','Direct',NULL,NULL,'2026-07-24 01:24:02',NULL,'pending','pending',NULL,NULL,'2026-07-24 01:23:38','2026-07-24 01:56:26'),(392379,8,1,3,'2026-07-25','2026-07-31',4,7200.00,'completed',NULL,'GCash','Direct',NULL,NULL,'2026-07-23 16:16:18',NULL,'pending','pending',NULL,NULL,'2026-07-23 16:15:55','2026-07-23 16:20:01'),(788060,8,1,3,'2026-07-30','2026-08-25',5,31700.00,'cancelled',NULL,'GCash','Direct',NULL,NULL,'2026-07-23 15:40:39',NULL,'pending','pending',NULL,NULL,'2026-07-23 15:40:26','2026-07-23 16:01:04');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `expense_id` int NOT NULL AUTO_INCREMENT,
  `property_id` int DEFAULT NULL,
  `expense_category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `amount` decimal(10,2) DEFAULT NULL,
  `expense_date` date DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  PRIMARY KEY (`expense_id`),
  KEY `property_id` (`property_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `fk_expense_unit` (`unit_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_expense_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,9,'Maintenance','AC Repair',500.00,'2026-07-23',1,NULL);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_records`
--

DROP TABLE IF EXISTS `financial_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `property_id` int DEFAULT NULL,
  `year` smallint NOT NULL,
  `month` tinyint NOT NULL,
  `revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `maintenance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `utilities` decimal(14,2) NOT NULL DEFAULT '0.00',
  `salaries` decimal(14,2) NOT NULL DEFAULT '0.00',
  `admin` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prop_year_month` (`property_id`,`year`,`month`),
  KEY `year_month` (`year`,`month`),
  CONSTRAINT `fk_fr_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_records`
--

LOCK TABLES `financial_records` WRITE;
/*!40000 ALTER TABLE `financial_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_reports`
--

DROP TABLE IF EXISTS `financial_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_reports` (
  `report_id` int NOT NULL AUTO_INCREMENT,
  `report_month` int DEFAULT NULL,
  `report_year` int DEFAULT NULL,
  `total_income` decimal(12,2) DEFAULT NULL,
  `total_expenses` decimal(12,2) DEFAULT NULL,
  `net_profit` decimal(12,2) DEFAULT NULL,
  `generated_by` int DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  KEY `fk_frep_generated_by` (`generated_by`),
  CONSTRAINT `fk_frep_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_reports`
--

LOCK TABLES `financial_reports` WRITE;
/*!40000 ALTER TABLE `financial_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `financial_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `tenant_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `unit` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_date` date NOT NULL,
  `due_date` date NOT NULL,
  `items` text COLLATE utf8mb4_general_ci,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('Paid','Pending','Overdue','Sent') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_no` (`invoice_no`),
  KEY `tenant_id` (`tenant_id`),
  KEY `booking_id` (`booking_id`),
  KEY `fk_inv_created_by` (`created_by`),
  CONSTRAINT `fk_inv_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,'INV-202607-0001',1,NULL,'Unit H','2026-07-23','2026-07-25','Rent + Water',0.00,0.00,500.00,'Paid',NULL,NULL,'2026-07-23 15:57:30');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_points`
--

DROP TABLE IF EXISTS `loyalty_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_points` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `points` int NOT NULL DEFAULT '0',
  `type` enum('earn','redeem','bonus','expire') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'earn',
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `fk_lp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_points`
--

LOCK TABLES `loyalty_points` WRITE;
/*!40000 ALTER TABLE `loyalty_points` DISABLE KEYS */;
INSERT INTO `loyalty_points` VALUES (1,3,820,'earn','Booking #218566 stay completed',218566,'2026-07-23 16:33:39'),(2,3,720,'earn','Booking #374041 stay completed',374041,'2026-07-24 01:56:27');
/*!40000 ALTER TABLE `loyalty_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_redemptions`
--

DROP TABLE IF EXISTS `loyalty_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_redemptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `reward_id` int NOT NULL,
  `reward_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `points_used` smallint unsigned NOT NULL,
  `voucher_code` varchar(32) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('active','used','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_voucher` (`voucher_code`),
  KEY `fk_lr_reward` (`reward_id`),
  CONSTRAINT `fk_lr_reward` FOREIGN KEY (`reward_id`) REFERENCES `loyalty_rewards` (`reward_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_redemptions`
--

LOCK TABLES `loyalty_redemptions` WRITE;
/*!40000 ALTER TABLE `loyalty_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_rewards`
--

DROP TABLE IF EXISTS `loyalty_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_rewards` (
  `reward_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `points_cost` smallint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reward_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_rewards`
--

LOCK TABLES `loyalty_rewards` WRITE;
/*!40000 ALTER TABLE `loyalty_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_requests`
--

DROP TABLE IF EXISTS `maintenance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `issue_description` text COLLATE utf8mb4_general_ci,
  `request_status` enum('pending','open','in_progress','completed','closed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `request_date` date DEFAULT NULL,
  `resolved_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_requests`
--

LOCK TABLES `maintenance_requests` WRITE;
/*!40000 ALTER TABLE `maintenance_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `from_user` int NOT NULL,
  `to_user` int NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `attachment_url` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `parent_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `from_user` (`from_user`),
  KEY `to_user` (`to_user`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_msg_from` FOREIGN KEY (`from_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_msg_parent` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`message_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_msg_to` FOREIGN KEY (`to_user`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,2,'','hi',NULL,1,NULL,'2026-07-19 09:59:28'),(2,1,2,'','📎 Attachment','uploads/messages/msg_6a5ca0353dea50.07786627.png',1,NULL,'2026-07-19 10:00:21'),(3,1,2,'','hrey','uploads/messages/msg_6a5ca0449e9894.10067760.png',1,NULL,'2026-07-19 10:00:36'),(4,1,3,'','hey',NULL,1,NULL,'2026-07-19 10:41:41'),(5,3,1,'','what',NULL,1,NULL,'2026-07-19 10:41:55'),(6,3,1,'','nothing',NULL,1,NULL,'2026-07-19 10:42:35'),(7,1,2,'','hi',NULL,1,NULL,'2026-07-20 19:24:40'),(8,1,3,'','hi',NULL,1,NULL,'2026-07-20 19:25:07'),(9,3,1,'','salamat',NULL,1,NULL,'2026-07-20 19:27:27'),(10,3,1,'','what',NULL,1,NULL,'2026-07-20 19:27:42'),(11,3,1,'','baket',NULL,1,NULL,'2026-07-20 19:34:15'),(12,3,1,'','hi',NULL,1,NULL,'2026-07-20 19:35:01');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_user` (`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_newsletter_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'info',
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `body` text COLLATE utf8mb4_general_ci,
  `link` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (5,1,'message','New message from Bulldogz Google','what','pages/admin/messages.php',0,'2026-07-19 10:41:55'),(6,1,'message','New message from Bulldogz Google','nothing','pages/admin/messages.php',0,'2026-07-19 10:42:35'),(9,1,'message','New message from Bulldogz Google','salamat','pages/admin/messages.php',0,'2026-07-20 19:27:27'),(10,1,'message','New message from Bulldogz Google','what','pages/admin/messages.php',0,'2026-07-20 19:27:42'),(11,1,'message','New message from Bulldogz Google','baket','pages/admin/messages.php',0,'2026-07-20 19:34:16'),(12,1,'message','New message from Bulldogz Google','hi','pages/admin/messages.php',0,'2026-07-20 19:35:02'),(13,1,'booking','New booking: BK-788060','Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 30–Aug 25, 2026 (26 nights)','pages/admin/reservations.php',0,'2026-07-23 15:40:27'),(14,3,'booking','Booking submitted: BK-788060','Your booking for Ocean Garden Villas — Unit Unit H (Jul 30–Aug 25, 2026) is pending admin confirmation.','pages/user/bookings.php',1,'2026-07-23 15:40:27'),(15,3,'booking','Booking cancelled','Booking BK-788060 for Ocean Garden Villas — Unit Unit H has been cancelled.','pages/user/bookings.php',1,'2026-07-23 16:01:05'),(16,1,'booking','Refund Request — BK-788060','A guest has requested a refund of ₱31,700.00 for booking BK-788060.','pages/admin/refunds.php',0,'2026-07-23 16:02:10'),(17,3,'booking','Refund Approved — BK-788060','Your refund of ₱31,700.00 for booking BK-788060 has been approved and is now being processed. You will be notified once it is completed.','pages/user/payment.php',1,'2026-07-23 16:03:15'),(18,3,'booking','Refund Completed \\u2014 BK-788060','Your refund of ₱31,700.00 for booking BK-788060 has been completed successfully.','pages/user/payment.php',1,'2026-07-23 16:04:17'),(19,1,'booking','New booking: BK-392379','Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 25–Jul 31, 2026 (6 nights)','pages/admin/reservations.php',0,'2026-07-23 16:15:56'),(20,3,'booking','Booking submitted: BK-392379','Your booking for Ocean Garden Villas — Unit Unit H (Jul 25–Jul 31, 2026) is pending admin confirmation.','pages/user/bookings.php',0,'2026-07-23 16:15:56'),(21,3,'booking','Stay completed — thanks for visiting!','Booking BK-392379 at Ocean Garden Villas — Unit Unit H is now complete.','pages/user/bookings.php',1,'2026-07-23 16:20:02'),(22,1,'booking','New booking: BK-218566','Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 25–Jul 31, 2026 (6 nights)','pages/admin/reservations.php',0,'2026-07-23 16:31:14'),(23,3,'booking','Booking submitted: BK-218566','Your booking for Ocean Garden Villas — Unit Unit H (Jul 25–Jul 31, 2026) is pending admin confirmation.','pages/user/bookings.php',0,'2026-07-23 16:31:14'),(24,3,'booking','Your booking is confirmed! 🎉','Booking BK-218566 for Ocean Garden Villas — Unit Unit H has been confirmed.','pages/user/bookings.php',0,'2026-07-23 16:31:29'),(25,3,'loyalty','Points Earned!','You earned 820 loyalty points from your stay!',NULL,0,'2026-07-23 16:33:39'),(26,3,'booking','Stay completed — thanks for visiting!','Booking BK-218566 at Ocean Garden Villas — Unit Unit H is now complete.','pages/user/bookings.php',0,'2026-07-23 16:33:39'),(27,1,'booking','New booking: BK-374041','Bulldogz Google booked Ocean Garden Villas — Unit Unit H · Jul 25–Jul 31, 2026 (6 nights)','pages/admin/reservations.php',0,'2026-07-24 01:23:39'),(28,3,'booking','Booking submitted: BK-374041','Your booking for Ocean Garden Villas — Unit Unit H (Jul 25–Jul 31, 2026) is pending admin confirmation.','pages/user/bookings.php',0,'2026-07-24 01:23:39'),(29,3,'loyalty','Points Earned!','You earned 720 loyalty points from your stay!',NULL,0,'2026-07-24 01:56:27'),(30,3,'booking','Stay completed — thanks for visiting!','Booking BK-374041 at Ocean Garden Villas — Unit Unit H is now complete.','pages/user/bookings.php',0,'2026-07-24 01:56:28'),(31,1,'support','New Support Ticket','asdasdasd','pages/admin/messages.php',0,'2026-07-24 02:20:14'),(32,4,'support','ID Submitted for Review','Your government ID has been submitted. We\'ll notify you once it\'s reviewed (usually within 24 hours).','pages/user/profile.php',0,'2026-07-24 05:41:26');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_tokens`
--

DROP TABLE IF EXISTS `otp_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `otp_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_tokens`
--

LOCK TABLES `otp_tokens` WRITE;
/*!40000 ALTER TABLE `otp_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `otp_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_attempts`
--

DROP TABLE IF EXISTS `password_reset_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_found` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_email_ip` (`email`,`ip_address`),
  KEY `idx_attempted_at` (`attempted_at`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_attempts`
--

LOCK TABLES `password_reset_attempts` WRITE;
/*!40000 ALTER TABLE `password_reset_attempts` DISABLE KEYS */;
INSERT INTO `password_reset_attempts` VALUES (1,'marlonvillegas00@gmail.com','143.44.196.34, 152.233.68.97','2026-07-23 02:26:05',1),(2,'marlonvillegas00@gmail.com','143.44.196.34, 152.233.68.97','2026-07-23 02:29:27',1),(3,'marlonvillegas86@gmail.com','::1','2026-07-24 01:29:01',1),(4,'bulldogz0923@gmail.com','::1','2026-07-24 05:59:30',1);
/*!40000 ALTER TABLE `password_reset_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (1,1,'marlonvillegas00@gmail.com','eca014db81cbdb84616df3f0468898e30a7d18edcb8d0fb4c3b980c36281b220','2026-07-23 02:56:06',0,'2026-07-23 02:26:06'),(2,2,'marlonvillegas86@gmail.com','ebf04aae9d52ca1dca9bf9c0fcc7cbe3efeadb930ec609b38f9416cb87dd5359','2026-07-24 03:59:02',1,'2026-07-24 01:29:02'),(3,3,'bulldogz0923@gmail.com','ef586d9da2384105d97232a6eefc05f9a059495356c7ab6fdde30ee8cfd6ae82','2026-07-24 08:29:32',0,'2026-07-24 05:59:33');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('card','ewallet') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'card',
  `provider` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last4` varchar(4) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expiry_month` int DEFAULT NULL,
  `expiry_year` int DEFAULT NULL,
  `holder_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `account_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_status` enum('paid','pending','late') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `fk_payment_booking` (`booking_id`),
  CONSTRAINT `fk_payment_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,788060,'2026-07-23',31700.00,'GCash','paid','PayMongo payment via link check (auto-synced)','2026-07-23 15:40:38'),(2,NULL,'2026-07-23',500.00,'Maya','paid','INV-PMT-1','2026-07-23 15:59:44'),(3,392379,'2026-07-23',7200.00,'GCash','paid','PayMongo payment via link check (auto-synced)','2026-07-23 16:16:17'),(4,218566,'2026-07-23',8200.00,'Cash','paid','Auto-created on booking confirmation','2026-07-23 16:31:29'),(5,374041,'2026-07-24',7200.00,'GCash','paid','PayMongo payment via link check (auto-synced)','2026-07-24 01:24:01');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paymongo_payments`
--

DROP TABLE IF EXISTS `paymongo_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paymongo_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `paymongo_link_id` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `paymongo_payment_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `checkout_url` text COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `reference_id` int DEFAULT NULL,
  `reference_type` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unpaid',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `paymongo_link_id` (`paymongo_link_id`),
  KEY `fk_pp_user` (`user_id`),
  CONSTRAINT `fk_pp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paymongo_payments`
--

LOCK TABLES `paymongo_payments` WRITE;
/*!40000 ALTER TABLE `paymongo_payments` DISABLE KEYS */;
INSERT INTO `paymongo_payments` VALUES (1,788060,3,'link_a87fa650be8b1b4f2e0285e0',NULL,'https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/eurETol',31700.00,'GCash',NULL,NULL,'paid',NULL,'2026-07-23 15:40:28','2026-07-23 16:10:28'),(2,NULL,NULL,'link_d27263bfc739fdd2e7f9eaad',NULL,'https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/KIIlBCg',500.00,'gcash',1,'invoice','cancelled',NULL,'2026-07-23 15:58:06','2026-07-24 15:58:06'),(3,NULL,NULL,'link_8ba91ac164cac8eed0c66b5e','pay_pGYmiiJzkJYzz2G6MwAtjRx3','https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/Zu4RHpX',500.00,'paymaya',1,'invoice','paid','2026-07-23 15:59:42','2026-07-23 15:58:06','2026-07-24 15:58:06'),(4,NULL,NULL,'cs_c76d99dfeebad211335ca3e5',NULL,'https://checkout.paymongo.com/c76d99dfeebad211335ca3e5',500.00,'card',1,'invoice','cancelled',NULL,'2026-07-23 15:58:07','2026-07-24 15:58:07'),(5,NULL,NULL,'link_c1a94abdef31326deb63b941',NULL,'https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/2sPc6nv',500.00,'dob',1,'invoice','cancelled',NULL,'2026-07-23 15:58:07','2026-07-24 15:58:07'),(6,392379,3,'link_fd630c61536f49a7cc022e31',NULL,'https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/ocMkj2P',7200.00,'GCash',NULL,NULL,'paid',NULL,'2026-07-23 16:15:59','2026-07-23 16:45:59'),(7,374041,3,'link_35795d571a547c7efe8c16d4',NULL,'https://pm.link/org-pxKS4buaYAjiozGR9zUkbXaM/test/4rPsATI',7200.00,'GCash',NULL,NULL,'paid',NULL,'2026-07-24 01:23:43','2026-07-24 01:53:43');
/*!40000 ALTER TABLE `paymongo_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `properties` (
  `property_id` int NOT NULL AUTO_INCREMENT,
  `property_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zip` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `property_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `photo` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`property_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `properties`
--

LOCK TABLES `properties` WRITE;
/*!40000 ALTER TABLE `properties` DISABLE KEYS */;
INSERT INTO `properties` VALUES (6,'Casa Camilla Beachfront','Angol Road, Malay, Aklan 5608','Malay','Aklan','5608','Active','Residential',NULL,NULL,'2026-07-23 15:07:20',11.9491190,121.9314680),(7,'Roxon Residences','Boat Station 3 Road, Malay, Aklan 5608','Malay','Aklan','5608','Active','Residential',NULL,NULL,'2026-07-23 15:13:28',11.9522530,121.9306170),(8,'Ocean Garden Villas','Boracay Main Road, Malay, Aklan 5608','Malay','Aklan','5608','Active','Residential',NULL,NULL,'2026-07-23 15:14:42',11.9840750,121.9170000),(9,'Casa De Primera','Tulubhan Road, Malay, Aklan 5608','Malay','Aklan','5608','Active','Residential',NULL,NULL,'2026-07-23 15:16:40',11.9509750,121.9377410);
/*!40000 ALTER TABLE `properties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refunds`
--

DROP TABLE IF EXISTS `refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refunds` (
  `refund_id` int NOT NULL AUTO_INCREMENT,
  `payment_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `refund_reason` text COLLATE utf8mb4_unicode_ci,
  `refund_status` enum('pending','processing','completed','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `refund_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `refund_date` date DEFAULT NULL,
  `processed_date` date DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`refund_id`),
  KEY `fk_refund_payment` (`payment_id`),
  KEY `fk_refund_booking` (`booking_id`),
  KEY `fk_refund_user` (`user_id`),
  KEY `fk_refund_processor` (`processed_by`),
  KEY `idx_user_status` (`user_id`,`refund_status`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_refund_invoice` (`invoice_id`),
  CONSTRAINT `fk_refund_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_refund_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refunds`
--

LOCK TABLES `refunds` WRITE;
/*!40000 ALTER TABLE `refunds` DISABLE KEYS */;
INSERT INTO `refunds` VALUES (1,1,788060,NULL,3,31700.00,'Why did you cancel my bookings? I want to refund it','completed','GCash','2026-07-23','2026-07-23',1,'Manually completed by admin','2026-07-23 16:02:09','2026-07-23 16:04:17');
/*!40000 ALTER TABLE `refunds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `location` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_units`
--

DROP TABLE IF EXISTS `saved_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved` (`user_id`,`unit_id`),
  KEY `user_id` (`user_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `fk_saved_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_units`
--

LOCK TABLES `saved_units` WRITE;
/*!40000 ALTER TABLE `saved_units` DISABLE KEYS */;
INSERT INTO `saved_units` VALUES (31,5,5,'2026-08-05 04:50:18');
/*!40000 ALTER TABLE `saved_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_permissions`
--

DROP TABLE IF EXISTS `staff_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_permission` (`user_id`,`permission`),
  CONSTRAINT `fk_sp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_permissions`
--

LOCK TABLES `staff_permissions` WRITE;
/*!40000 ALTER TABLE `staff_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_messages`
--

DROP TABLE IF EXISTS `support_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `user_id` int NOT NULL,
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_smsg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_smsg_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_messages`
--

LOCK TABLES `support_messages` WRITE;
/*!40000 ALTER TABLE `support_messages` DISABLE KEYS */;
INSERT INTO `support_messages` VALUES (1,1,2,'asdasdasdasdasdasd',0,'2026-07-24 02:20:14');
/*!40000 ALTER TABLE `support_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `ticket_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') COLLATE utf8mb4_general_ci DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
INSERT INTO `support_tickets` VALUES (1,2,'Booking Inquiry','asdasdasd','medium','in_progress','2026-07-24 02:20:14','2026-07-24 02:21:51');
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `tenant_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `move_in_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Bulldogz Google',NULL,'bulldogz0923@gmail.com','2026-07-30','2026-07-23 15:40:26');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` enum('Income','Expense') COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `property_id` int DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `recorded_by` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `booking_id` (`booking_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `fk_txn_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_txn_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_txn_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'PMT-1','PayMongo payment (GCash) for Booking #788060','Room Revenue','Income',31700.00,'2026-07-23',8,788060,NULL,NULL,'2026-07-23 15:40:38'),(2,'INV-PMT-1','PayMongo payment (Maya) for Invoice #1','Invoice Revenue','Income',500.00,'2026-07-23',NULL,NULL,NULL,'','2026-07-23 15:59:44'),(3,'EXP-1-1784822445','AC Repair','Maintenance','Expense',500.00,'2026-07-23',9,NULL,NULL,'Logged via Expense Module','2026-07-23 16:00:46'),(5,'TXN-BK-392379','Booking #392379 payment','Room Revenue','Income',7200.00,'2026-07-23',8,392379,NULL,NULL,'2026-07-23 16:20:01'),(6,'TXN-BK-218566','Booking #218566 payment','Room Revenue','Income',8200.00,'2026-07-23',8,218566,NULL,NULL,'2026-07-23 16:33:38'),(7,'PMT-5','PayMongo payment (GCash) for Booking #374041','Room Revenue','Income',7200.00,'2026-07-24',8,374041,NULL,NULL,'2026-07-24 01:24:01');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unit_amenities`
--

DROP TABLE IF EXISTS `unit_amenities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_amenities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `amenity_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_amenity` (`unit_id`,`amenity_id`),
  KEY `fk_ua_unit` (`unit_id`),
  KEY `fk_ua_amenity` (`amenity_id`),
  CONSTRAINT `fk_ua_amenity` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`amenity_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unit_amenities`
--

LOCK TABLES `unit_amenities` WRITE;
/*!40000 ALTER TABLE `unit_amenities` DISABLE KEYS */;
/*!40000 ALTER TABLE `unit_amenities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unit_images`
--

DROP TABLE IF EXISTS `unit_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unit_images` (
  `image_id` int unsigned NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` tinyint unsigned DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_id`),
  KEY `idx_unit` (`unit_id`),
  CONSTRAINT `fk_unit_images` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unit_images`
--

LOCK TABLES `unit_images` WRITE;
/*!40000 ALTER TABLE `unit_images` DISABLE KEYS */;
INSERT INTO `unit_images` VALUES (4,5,'uploads/units/5/unit_6a6230bf0fd913.69874650.jpg',0,'2026-07-23 15:18:23'),(5,6,'uploads/units/6/unit_6a6230eef16193.34111918.jpg',0,'2026-07-23 15:19:11'),(6,7,'uploads/units/7/unit_6a62316edbb636.05867806.jpg',0,'2026-07-23 15:21:19'),(7,8,'uploads/units/8/unit_6a6232016033c8.78252349.jpg',0,'2026-07-23 15:23:45'),(8,9,'uploads/units/9/unit_6a62328ade2775.10408822.jpg',0,'2026-07-23 15:26:03'),(9,10,'uploads/units/10/unit_6a6232b6381a09.45377745.jpg',0,'2026-07-23 15:26:46');
/*!40000 ALTER TABLE `unit_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `units` (
  `unit_id` int NOT NULL AUTO_INCREMENT,
  `property_id` int DEFAULT NULL,
  `tenant_id` int DEFAULT NULL,
  `unit_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `floor` int DEFAULT NULL,
  `rent_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('occupied','vacant','maintenance','booked') COLLATE utf8mb4_general_ci DEFAULT 'vacant',
  `season` enum('Peak','High','Low') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Low',
  `tenant_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `max_guests` int NOT NULL DEFAULT '2',
  `bedrooms` int DEFAULT '1',
  `bathrooms` int DEFAULT '1',
  `area_sqm` decimal(8,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`),
  KEY `property_id` (`property_id`),
  KEY `fk_units_tenant` (`tenant_id`),
  CONSTRAINT `fk_units_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (5,6,NULL,'Unit 10','','Penthouse',2,500.00,'vacant','Low','','0',2,1,1,NULL,'2026-07-23 15:18:23'),(6,7,NULL,'Unit 5','','Studio',2,100.00,'vacant','Low','','0',2,1,1,NULL,'2026-07-23 15:19:11'),(7,9,NULL,'Unit A18','','1 Bedroom',1,500.00,'vacant','Low',NULL,'0',3,1,1,NULL,'2026-07-23 15:21:19'),(8,8,NULL,'Unit H','','Penthouse',4,1200.00,'vacant','High','','0',4,1,1,NULL,'2026-07-23 15:23:45'),(9,8,NULL,'Unit A','','Penthouse',5,1200.00,'vacant','Peak',NULL,'0',4,1,1,NULL,'2026-07-23 15:26:03'),(10,8,NULL,'Unit F','','Penthouse',6,1200.00,'vacant','Peak','','WNASDASdasdSAd',4,1,1,NULL,'2026-07-23 15:26:46'),(12,6,NULL,'Unit B2','','1 Bedroom',4,200.00,'vacant','High',NULL,'KASdasdbasdbadbas',4,1,1,NULL,'2026-07-24 00:21:29');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `notif_booking_confirm` tinyint(1) DEFAULT '1',
  `notif_checkin_remind` tinyint(1) DEFAULT '1',
  `notif_promotions` tinyint(1) DEFAULT '0',
  `notif_loyalty` tinyint(1) DEFAULT '1',
  `notif_newsletter` tinyint(1) DEFAULT '0',
  `notif_sms` tinyint(1) DEFAULT '0',
  `privacy_profile` enum('public','private') COLLATE utf8mb4_general_ci DEFAULT 'private',
  `privacy_activity` tinyint(1) DEFAULT '0',
  `two_factor_enabled` tinyint(1) DEFAULT '1',
  `language` varchar(10) COLLATE utf8mb4_general_ci DEFAULT 'en',
  `timezone` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Asia/Manila',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `push_inapp_alerts` tinyint(1) DEFAULT '1',
  `push_checkout_reminder` tinyint(1) DEFAULT '1',
  `push_room_availability` tinyint(1) DEFAULT '0',
  `privacy_share_history` tinyint(1) DEFAULT '0',
  `privacy_recommendations` tinyint(1) DEFAULT '1',
  `privacy_analytics` tinyint(1) DEFAULT '1',
  `data_export_requested_at` datetime DEFAULT NULL,
  `last_session_action_at` datetime DEFAULT NULL,
  `active_sessions_count` int DEFAULT '2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_settings` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_settings`
--

LOCK TABLES `user_settings` WRITE;
/*!40000 ALTER TABLE `user_settings` DISABLE KEYS */;
INSERT INTO `user_settings` VALUES (1,2,1,1,0,1,0,0,'private',0,0,'en','Asia/Manila','2026-07-24 01:50:13',1,1,0,0,1,1,NULL,NULL,2),(2,3,1,1,0,1,0,0,'private',0,1,'en','Asia/Manila','2026-07-19 10:41:17',1,1,0,0,1,1,NULL,NULL,2),(7,4,1,1,0,1,0,0,'private',0,1,'en','Asia/Manila','2026-07-23 02:26:48',1,1,0,0,1,1,NULL,NULL,2),(10,5,1,1,0,1,0,0,'private',0,1,'en','Asia/Manila','2026-08-05 02:59:33',1,1,0,0,1,1,NULL,NULL,2);
/*!40000 ALTER TABLE `user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nationality` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birthday` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `oauth_provider` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oauth_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `role` enum('user','admin','manager','frontdesk','accounting','maintenance') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `verification_status` enum('Not Verified','Verified') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Not Verified',
  `id_verified` enum('none','pending','approved','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `id_document_path` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_verified_at` datetime DEFAULT NULL,
  `id_reject_reason` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login_attempts` int DEFAULT '0',
  `last_attempt` datetime DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `profile_photo` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `uniq_oauth_provider_id` (`oauth_provider`,`oauth_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Marlon','Villegas','marlonvillegas00@gmail.com','9497680949','Austria',NULL,NULL,'$2y$10$oo/3q3uKb1655EZ4pQ6mue/bZWZHrocA6DDkSwAeNHR0iuyoW3x1K','google','115360974854624748495','2026-05-30 13:29:44','admin','Verified','approved',NULL,NULL,NULL,0,'2026-07-24 05:21:00',0,NULL,0,1,NULL,NULL,'Iloilo City'),(2,'Marlon','Villegas','marlonvillegas86@gmail.com',NULL,NULL,NULL,NULL,'$2y$10$1tepCClL02/vfc.R8WvWBekN8JiOewdEk.lT.tEzaRdrU0Wmx72XW','google','102906208950229381678','2026-07-13 08:02:31','user','Verified','none',NULL,NULL,NULL,0,'2026-07-24 03:27:09',0,NULL,0,1,NULL,'https://lh3.googleusercontent.com/a/ACg8ocKBBgyKknlNMbIK5yOHwj7Rm4iDI925WOebrKc86zz73MDP9w=s96-c',NULL),(3,'Bulldogz','Google','bulldogz0923@gmail.com','9334012641','Switzerland','','Female','$2y$10$FZIjW3lsJudsrGb4zmICdullG8eOkctnRl159wle14nmVvSJHtjdS','google','108219113092803232575','2026-07-19 10:41:17','user','Verified','approved',NULL,NULL,NULL,3,'2026-07-24 05:59:11',0,NULL,0,1,NULL,'https://lh3.googleusercontent.com/a/ACg8ocJpAph_MbkSpwmHko-zVMfXTLXVouR5xdaLq3cd09U_xo_ZeQ=s96-c',NULL),(4,'Jay','ar','magdaongjayar4@gmail.com','09958219498','Philippines','2004-09-08','Male','$2y$10$PBR5Dikt5IlTG3f2iowCJOQ7pqT7I5r1ZMzNhYcAI5j91KYeUsUH.','google','117664627393286721240','2026-07-23 02:26:47','user','Verified','pending','uploads/id_documents/user_4_20260724_074124_a961073c.jpg',NULL,NULL,0,NULL,0,NULL,0,1,NULL,'https://lh3.googleusercontent.com/a/ACg8ocLa2QPL9f3REX_mtLl3kigXHftKfL-AfP4xmJBwtFbsJ9jFIzhG=s96-c',NULL),(5,'Kitche Tacaisan','CAMRAL','kita.camral.ui@phinmaed.com',NULL,NULL,NULL,NULL,'$2y$10$oc85DfkSGJpT9VCdEFEyiO31Gb6fDWFPmVf7Zy6hbp3hIK/lba9de','google','109564698739278093957','2026-08-05 02:59:32','user','Verified','none',NULL,NULL,NULL,0,NULL,0,NULL,0,1,NULL,'https://lh3.googleusercontent.com/a/ACg8ocLu3zk9oGz-MZxjxJavJIepiEzg2AADf8MFBWCCYDo7b0LuM6o=s96-c',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'railway'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-12 12:20:06
