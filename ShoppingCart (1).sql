-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ShoppingCart
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_activity_logs`
--

DROP TABLE IF EXISTS `admin_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_email` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity_logs`
--

LOCK TABLES `admin_activity_logs` WRITE;
/*!40000 ALTER TABLE `admin_activity_logs` DISABLE KEYS */;
INSERT INTO `admin_activity_logs` VALUES (1,'admin@bloomandbasket.com','Login','Admin logged in successfully.','::1','2026-09-28 01:45:41'),(2,'admin@bloomandbasket.com','Update Product','Updated product ID: 8 (Velvet Matte Lipstick), Stock: 22','::1','2026-09-28 01:46:14'),(3,'admin@bloomandbasket.com','Update Settings','Updated security policies (Lockout, MFA, Password)','::1','2026-09-28 01:53:31'),(4,'admin@bloomandbasket.com','Update Settings','Updated security policies (Lockout, MFA, Password)','::1','2026-09-28 02:08:06'),(5,'admin@bloomandbasket.com','Update Settings','Updated security policies (Lockout, MFA, Password)','::1','2026-09-28 02:08:14'),(6,'admin@bloomandbasket.com','Update Settings','Updated security policies (Lockout, MFA, Password)','::1','2026-09-28 02:14:24');
/*!40000 ALTER TABLE `admin_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_security_settings`
--

DROP TABLE IF EXISTS `admin_security_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_security_settings`
--

LOCK TABLES `admin_security_settings` WRITE;
/*!40000 ALTER TABLE `admin_security_settings` DISABLE KEYS */;
INSERT INTO `admin_security_settings` VALUES (1,'lockout_threshold','3'),(2,'lockout_duration','8'),(3,'mfa_enabled','0'),(4,'password_min_length','8'),(5,'password_require_special','1'),(6,'password_require_number','1'),(7,'password_require_uppercase','1');
/*!40000 ALTER TABLE `admin_security_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Administrator',
  `last_login` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `lockout_threshold` int(11) DEFAULT 3,
  `lockout_duration` int(11) DEFAULT 15,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin@bloomandbasket.com','Decie Mae Iglesia','assets/uploads/admins/admin_1_1790436606.jpg','Administrator','2026-09-28 01:45:41','$2y$10$iD1iaoMslHRsk55drJR1Q.WOcd3LxEtdMQaTgwvvjrePI03sow2De','2026-09-21 01:20:48',0,0,NULL,3,15);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `color` varchar(100) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  KEY `fk_cart_items_cart` (`cart_id`),
  KEY `fk_cart_items_product` (`product_id`),
  CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart_items`
--

LOCK TABLES `cart_items` WRITE;
/*!40000 ALTER TABLE `cart_items` DISABLE KEYS */;
INSERT INTO `cart_items` VALUES (10,3,6,1,NULL,'2026-09-08 02:06:45'),(18,8,25,1,NULL,'2026-09-21 03:49:47');
/*!40000 ALTER TABLE `cart_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `carts`
--

DROP TABLE IF EXISTS `carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_id`),
  KEY `fk_carts_user` (`user_id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `carts`
--

LOCK TABLES `carts` WRITE;
/*!40000 ALTER TABLE `carts` DISABLE KEYS */;
INSERT INTO `carts` VALUES (2,2,'03a58r63luo745134q9hmlu590','2026-08-13 13:13:50'),(3,2,'h8qoqbrcfielkh0g8hniaf6pj1','2026-09-08 00:38:19'),(4,2,'rd90ne8uq9nd6e219a4on5elbc','2026-09-14 01:10:07'),(5,2,'gno5htqaiq68ethaa88t3brpr3','2026-09-15 03:44:43'),(6,2,'3km8vq4n9o7mr621jj74naue3r','2026-09-20 02:46:18'),(7,NULL,'rakcihrn2gli8cjjhnnojfu3i4','2026-09-20 15:18:42'),(8,3,'3km8vq4n9o7mr621jj74naue3r','2026-09-21 02:22:00'),(9,2,'rakcihrn2gli8cjjhnnojfu3i4','2026-09-26 01:56:08'),(11,NULL,'jrl1ok3pvusvv2frb9udog6d05','2026-09-28 03:37:42');
/*!40000 ALTER TABLE `carts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (2,'Electronics','Gadgets and devices like phones, laptops, cameras, and accessories.','2026-07-01 04:08:25'),(3,'Fashion','Clothing, shoes, and accessories for men, women, and kids.','2026-07-01 04:08:43'),(4,'Beauty & Personal Care','Skincare, makeup, haircare, and grooming products.','2026-07-01 04:09:02'),(5,'Books & Stationery','Literature, school supplies, and office essentials.','2026-07-01 04:09:27'),(6,'Health & Wellness','Vitamins, supplements, medical supplies, and fitness products.','2026-07-01 04:09:50');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_addresses`
--

DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `recipient_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address_line1` varchar(150) NOT NULL,
  `address_line2` varchar(150) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'Philippines',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`address_id`),
  KEY `fk_customer_addresses_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_addresses`
--

LOCK TABLES `customer_addresses` WRITE;
/*!40000 ALTER TABLE `customer_addresses` DISABLE KEYS */;
INSERT INTO `customer_addresses` VALUES (1,2,'Home','Decie Mae Iglesia','09917025975','Zone 9E Hilltop Macanhan','Carmen, CDOC','Cagayan de Oro City','','9000','Philippines',0,'2026-08-13 13:49:10','2026-09-26 01:58:57'),(2,3,'Home','Czarina Moore','09917025972','Zone 5 Hilltop, Macanhan','Carmen, CDOC','Cagayan de Oro City','Misamis Oriental','9000','Philippines',1,'2026-09-21 02:24:24','2026-09-21 02:24:24'),(3,2,'School','Decie Mae Iglesia','09917025975','Corrales Extension','','Cagayan de Oro City','Misamis Oriental','9000','Philippines',1,'2026-09-26 01:58:49','2026-09-26 01:58:57');
/*!40000 ALTER TABLE `customer_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_details`
--

DROP TABLE IF EXISTS `order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_details` (
  `order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_detail_id`),
  KEY `fk_order_details_order` (`order_id`),
  KEY `fk_order_details_product` (`product_id`),
  CONSTRAINT `fk_order_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_details_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_details`
--

LOCK TABLES `order_details` WRITE;
/*!40000 ALTER TABLE `order_details` DISABLE KEYS */;
INSERT INTO `order_details` VALUES (1,1,7,1,699.00,699.00),(2,2,6,1,2275.00,2275.00),(3,3,7,1,699.00,699.00),(5,5,7,1,699.00,699.00),(6,6,7,1,699.00,699.00),(8,8,7,1,699.00,699.00),(9,9,7,1,699.00,699.00),(10,10,7,1,699.00,699.00),(11,11,25,1,999.00,999.00);
/*!40000 ALTER TABLE `order_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `shipping_address` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`order_id`),
  KEY `fk_orders_user` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,2,'2026-08-13 13:49:21',699.00,'Shipped','Decie Mae Iglesia | 09917025975 | Zone 9E Hilltop Macanhan, Carmen, CDOC, Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines',NULL,NULL,0.00),(2,2,'2026-08-14 23:20:41',2275.00,'Confirmed','Decie Mae Iglesia | 09917025975 | Zone 9E Hilltop Macanhan, Carmen, CDOC, Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines',NULL,NULL,0.00),(3,2,'2026-08-15 00:34:13',699.00,'Pending','Zone 9E Hilltop Macanhan, Carmen, CDOC, Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines',NULL,NULL,0.00),(4,2,'2026-08-15 00:41:20',599.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines',NULL,NULL,0.00),(5,2,'2026-09-08 01:30:44',749.00,'Cancelled','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','Cash on Delivery (COD)','Pending',50.00),(6,2,'2026-09-08 01:38:55',749.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','Cash on Delivery (COD)','Pending',50.00),(7,2,'2026-09-08 01:43:57',649.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','Cash on Delivery (COD)','Pending',50.00),(8,2,'2026-09-08 01:51:05',749.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','E-Wallet','Paid',50.00),(9,2,'2026-09-08 02:06:37',749.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','E-Wallet','Paid',50.00),(10,2,'2026-09-14 01:45:32',749.00,'Delivered','Zone 9E Hilltop Macanhan, Carmen, CDOC | Cagayan de Oro City, 9000 | Philippines','Cash on Delivery (COD)','Pending',50.00),(11,2,'2026-09-28 02:23:16',1049.00,'Pending','Cor*************on | Cagayan de Oro City, Misamis Oriental, 9000 | Philippines','Cash on Delivery (COD)','Pending',50.00);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_colors`
--

DROP TABLE IF EXISTS `product_colors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_colors` (
  `product_color_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `color` varchar(100) NOT NULL,
  PRIMARY KEY (`product_color_id`),
  UNIQUE KEY `uniq_product_color` (`product_id`,`color`),
  KEY `fk_product_colors_product` (`product_id`),
  CONSTRAINT `fk_product_colors_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_colors`
--

LOCK TABLES `product_colors` WRITE;
/*!40000 ALTER TABLE `product_colors` DISABLE KEYS */;
INSERT INTO `product_colors` VALUES (1,6,'Black'),(2,6,'White'),(3,7,'Black'),(6,7,'Blue'),(5,7,'Red'),(4,7,'White'),(10,8,'Mauve'),(7,8,'Nude'),(9,8,'Red'),(8,8,'Rose'),(11,9,'Clear'),(12,9,'Pink'),(15,10,'Beige'),(13,10,'Black'),(14,10,'Rose Gold'),(17,11,'Black'),(18,11,'Brown'),(19,11,'Cream'),(16,11,'Maroon'),(20,11,'Pink'),(21,12,'Black'),(22,12,'Blue'),(26,12,'Brown'),(25,12,'Khaki'),(23,12,'Pink'),(24,12,'Purple'),(27,13,'Black'),(29,13,'Khaki'),(30,13,'Pink'),(28,13,'White'),(31,14,'Black'),(33,14,'Blue'),(32,14,'White'),(36,15,'Beige'),(34,15,'Black'),(37,15,'Gray'),(35,15,'White'),(38,16,'Black'),(40,16,'Brown'),(39,16,'Cream'),(41,16,'Pink'),(44,17,'Beige'),(43,17,'Black'),(42,17,'White'),(45,18,'Black'),(46,18,'Blue'),(47,18,'Pink'),(48,18,'White'),(49,19,'Black'),(52,19,'Blue'),(51,19,'Green'),(50,19,'Purple'),(53,20,'Black'),(56,20,'Blue'),(55,20,'Pink'),(54,20,'White'),(59,21,'Lavender'),(57,21,'Pink'),(58,21,'White'),(62,22,'Blue'),(63,22,'Green'),(61,22,'Pink'),(60,22,'Yellow'),(64,23,'Black'),(65,23,'Blue'),(66,23,'Pink'),(67,23,'White'),(68,24,'Beige'),(69,24,'Black'),(70,24,'Brown'),(71,24,'Cream'),(73,25,'Black'),(72,25,'White'),(74,25,'Wood');
/*!40000 ALTER TABLE `product_colors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  KEY `fk_product_reviews_product` (`product_id`),
  CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `CONSTRAINT_1` CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `fk_products_category` (`category_id`),
  KEY `fk_products_created_by` (`created_by`),
  KEY `fk_products_updated_by` (`updated_by`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_products_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (6,2,NULL,NULL,'Keyboard','A keyboard is an essential input device designed for typing, navigating, and executing commands on a computer. Built with a layout of keys for letters, numbers, and functions, it enables efficient communication and control. Available in wired and wireless models, keyboards come in various styles — from compact designs for portability to mechanical types for precision and durability. Perfect for everyday work, gaming, and professional tasks.',1500.00,44,'uploads/71e4d1fa7c4deae7b8a7.jpg','2026-07-01 04:56:54'),(7,5,NULL,NULL,'File Organizer','A file organizer is a practical storage tool designed to keep documents, papers, and records neatly arranged. Compact yet spacious, it helps maintain order by categorizing files for easy access and retrieval.',699.00,63,'uploads/caa5d0d4da2cc4fd012e.jpg','2026-07-01 05:05:12'),(8,4,NULL,NULL,'Velvet Matte Lipstick','Long-lasting matte lipstick with a smooth, lightweight finish for everyday or evening looks.',499.00,22,'uploads/b4fc573475e2607551bb4955.jpg','2026-09-20 03:28:03'),(9,4,NULL,NULL,'Glow & Hydrate Facial Serum','Lightweight facial serum designed to help keep skin hydrated and give it a fresh, healthy-looking glow.',999.00,35,'uploads/923a8a4e2a575970324830ac.jpg','2026-09-20 03:34:12'),(10,4,NULL,NULL,'Everyday Makeup Brush Set','A complete set of soft makeup brushes suitable for foundation, blush, eyeshadow, and blending.',499.00,30,'uploads/de11d8ebf0958b9498f4bdff.jpg','2026-09-20 03:38:15'),(11,5,NULL,NULL,'Classic Hardcover Journal','Premium hardcover notebook with lined pages for notes, journaling, planning, and personal ideas.',1999.00,10,'uploads/662aba983719f51f8e55189c.jpg','2026-09-20 03:43:19'),(12,5,NULL,NULL,'Minimalist Gel Pen Set','Smooth-writing gel pens suitable for studying, note-taking, journaling, and everyday writing.',299.00,50,'uploads/4b7eb54b0e3d74576d0bfd6a.jpg','2026-09-20 03:47:26'),(13,2,NULL,NULL,'Wireless Bluetooth Earbuds','Compact wireless earbuds featuring Bluetooth connectivity and a portable charging case.',899.00,22,'uploads/a9029d5d4fe2f221fb6f13a0.jpg','2026-09-20 03:49:40'),(14,2,NULL,NULL,'Portable Power Bank 10,000mAh','Compact rechargeable power bank designed to provide convenient backup power for smartphones and other USB devices.',1799.00,35,'uploads/51ce058aaf0481e2bfc9fafd.jpg','2026-09-20 03:51:26'),(15,3,NULL,NULL,'Classic Oversized T-Shirt','Comfortable oversized shirt made for casual everyday wear with a relaxed and modern silhouette.',499.00,30,'uploads/15d60a2e82c4aa028eeb6b09.jpg','2026-09-20 04:00:05'),(16,3,NULL,NULL,'Everyday Canvas Tote Bag','Reusable canvas tote with a spacious interior for books, personal belongings, and everyday essentials.',299.00,34,'uploads/657286556555a0955af21723.jpg','2026-09-20 04:05:04'),(17,3,NULL,NULL,'Minimalist Casual Sneakers','Versatile casual sneakers designed to complement everyday outfits while providing comfortable all-day wear.',1599.00,20,'uploads/d66fd1a65089dab18f516f62.jpg','2026-09-20 04:06:00'),(18,6,NULL,NULL,'Insulated Water Bottle 750mL','Reusable insulated bottle designed to keep beverages cool and convenient to carry throughout the day.',599.00,15,'uploads/95aa415b9181ad11ca33513c.jpg','2026-09-20 04:08:56'),(19,6,NULL,NULL,'Yoga & Exercise Mat','Non-slip exercise mat suitable for yoga, stretching, home workouts, and basic fitness routines.',3500.00,10,'uploads/4053eca2d540cae03edf01d1.jpg','2026-09-20 04:10:36'),(20,6,NULL,NULL,'Digital Fitness Tracker','Wearable fitness tracker that monitors everyday activity and provides convenient wellness tracking features.',5000.00,8,'uploads/93300c3fee6ba0e69f995086.jpg','2026-09-20 04:11:52'),(21,4,NULL,NULL,'Hydrating Hand Cream','Lightweight hand cream that helps keep hands soft, smooth, and moisturized throughout the day.',399.00,18,'uploads/08b2c1ec23a9072135e27e3b.jpg','2026-09-20 04:21:00'),(22,5,NULL,NULL,'Sticky Notes & Memo Set','Colorful sticky notes and memo sheets for reminders, study notes, planning, and organization.',150.00,40,'uploads/e546859807aee08227735d29.jpg','2026-09-20 04:22:49'),(23,2,NULL,NULL,'Mini Portable Bluetooth Speaker','Compact wireless speaker designed for music, podcasts, and entertainment at home or on the go.',999.00,19,'uploads/4f4b2c15b990466bde135ac1.jpg','2026-09-20 04:24:06'),(24,3,NULL,NULL,'Ribbed Casual Cardigan','Soft and versatile ribbed cardigan that can be layered over casual outfits for a comfortable and stylish look.',699.00,27,'uploads/bd25cebb5e26386789d5c8f0.jpg','2026-09-20 04:24:59'),(25,6,NULL,NULL,'Aromatherapy Essential Oil Diffuser','Compact aroma diffuser designed to create a relaxing atmosphere at home, in a dorm room, or in the workplace.',999.00,11,'uploads/ee4deb16d6b32ac98ec7293b.jpg','2026-09-20 04:27:06');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'Decie Mae','Iglesia','iglesia.decie04@gmail.com','$2y$10$4SZwLYO8FXeLpTZG83p5Oekw7E6A2q2awZyCJ/suFI9ZvRZEr3MBS','09917025975','2026-08-13 13:13:49'),(3,'Czarina','Moore','czarina.moore@gmail.com','$2y$10$X3iMzxgr4enkoM0OIL6RRuyeU8beq.p5MF5tGyF.WTEibUR9qlUZC','09917025972','2026-09-21 02:22:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-28 17:31:55
