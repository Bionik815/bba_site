-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 08, 2026 at 08:06 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u632291655_barebones_ops`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `active`, `created_at`) VALUES
(1, 'Acton Krav Maga', 1, '2025-11-05 07:10:21'),
(2, 'AV Football Boosters', 1, '2025-11-05 07:10:21'),
(3, 'AV Pickleball', 1, '2025-11-05 07:10:21'),
(4, 'AVHS FB PlayerPack', 1, '2025-11-05 07:10:21'),
(5, 'AVHS FB PlayerPack;AVHS Football Students', 1, '2025-11-05 07:10:21'),
(6, 'AVHS Football Coaches', 1, '2025-11-05 07:10:21'),
(7, 'AVHS Football Students', 1, '2025-11-05 07:10:21'),
(8, 'Axe N Dagger', 1, '2025-11-05 07:10:21'),
(9, 'Barrel Springs Elementary', 1, '2025-11-05 07:10:21'),
(10, 'Beat the Streets', 1, '2025-11-05 07:10:21'),
(11, 'Breast Cancer Awareness', 1, '2025-11-05 07:10:21'),
(12, 'Del Sur Middle School', 1, '2025-11-05 07:10:21'),
(13, 'Desert View', 1, '2025-11-05 07:10:21'),
(14, 'Dirt Made', 1, '2025-11-05 07:10:21'),
(15, 'DJ Craig', 1, '2025-11-05 07:10:21'),
(16, 'DJ JK', 1, '2025-11-05 07:10:21'),
(17, 'Fulton & Alsbury Staff;Fulton & Alsbury Students', 1, '2025-11-05 07:10:21'),
(18, 'Fulton & Alsbury Students;Fulton & Alsbury Staff', 1, '2025-11-05 07:10:21'),
(19, 'GO DJ', 1, '2025-11-05 07:10:21'),
(20, 'Grand Arts Dance Company', 1, '2025-11-05 07:10:21'),
(21, 'Happy Hours', 1, '2025-11-05 07:10:21'),
(22, 'Hillview Middle School', 1, '2025-11-05 07:10:21'),
(24, 'Jack Northrop', 1, '2025-11-05 07:10:21'),
(25, 'Joei', 1, '2025-11-05 07:10:21'),
(26, 'Joshua Elementary', 1, '2025-11-05 07:10:21'),
(27, 'JS_Champ_Fight', 1, '2025-11-05 07:10:21'),
(28, 'Knight Flag Football', 1, '2025-11-05 07:10:21'),
(29, 'Knight High School Soccer', 1, '2025-11-05 07:10:21'),
(30, 'Lancaster High School Baseball', 1, '2025-11-05 07:10:21'),
(31, 'Lancaster High School Football Boosters', 1, '2025-11-05 07:10:21'),
(32, 'Lancaster High School Football Coaches', 1, '2025-11-05 07:10:21'),
(33, 'Lancaster High School Varsity Softball', 1, '2025-11-05 07:10:21'),
(34, 'Lancaster High School;Lancaster High School Baseball;Lancaster High School Varsity Softball', 1, '2025-11-05 07:10:21'),
(35, 'LAVA', 1, '2025-11-05 07:10:21'),
(36, 'Lincoln Elementary', 1, '2025-11-05 07:10:21'),
(37, 'Mariposa', 1, '2025-11-05 07:10:21'),
(38, 'Monte Vista Mustangs', 1, '2025-11-05 07:10:21'),
(39, 'New Vista Middle School', 1, '2025-11-05 07:10:21'),
(40, 'Newhall School District', 1, '2025-11-05 07:10:21'),
(41, 'Next Level Volleyball Academy', 1, '2025-11-05 07:10:21'),
(42, 'Not Done Yet', 1, '2025-11-05 07:10:21'),
(43, 'OP-B Elite', 1, '2025-11-05 07:10:21'),
(44, 'Rachel Garcia', 1, '2025-11-05 07:10:21'),
(45, 'Saad Ul-Hasan', 1, '2025-11-05 07:10:21'),
(46, 'Simple Mind', 1, '2025-11-05 07:10:21'),
(47, 'Skulls & Vines', 1, '2025-11-05 07:10:21'),
(48, 'SO CAL FREEDOM', 1, '2025-11-05 07:10:21'),
(49, 'Stealth Fastpitch', 1, '2025-11-05 07:10:21'),
(50, 'Sui Generis', 1, '2025-11-05 07:10:21');

-- --------------------------------------------------------

--
-- Table structure for table `client_products`
--

CREATE TABLE `client_products` (
  `client_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_products`
--

INSERT INTO `client_products` (`client_id`, `product_id`, `active`) VALUES
(1, 9, 1),
(1, 10, 1),
(1, 14, 1),
(1, 17, 1),
(1, 33, 1),
(1, 34, 1),
(1, 46, 1),
(1, 47, 1),
(1, 48, 1),
(1, 51, 1),
(1, 52, 1),
(1, 58, 1),
(1, 65, 1),
(2, 4, 1),
(2, 12, 1),
(2, 15, 1),
(2, 16, 1),
(2, 31, 1),
(2, 32, 1),
(2, 33, 1),
(2, 43, 1),
(2, 51, 1),
(2, 52, 1),
(2, 54, 1),
(2, 57, 1),
(2, 58, 1),
(2, 65, 1),
(3, 19, 1),
(3, 28, 1),
(3, 30, 1),
(3, 33, 1),
(3, 51, 1),
(3, 58, 1),
(3, 59, 1),
(3, 65, 1);

-- --------------------------------------------------------

--
-- Table structure for table `client_product_colors`
--

CREATE TABLE `client_product_colors` (
  `client_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `color_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_product_colors`
--

INSERT INTO `client_product_colors` (`client_id`, `product_id`, `color_id`) VALUES
(1, 4, 2),
(1, 9, 2),
(1, 10, 2),
(1, 14, 2),
(1, 17, 2),
(1, 33, 2),
(1, 34, 2),
(1, 46, 2),
(1, 47, 2),
(1, 48, 2),
(1, 51, 2),
(1, 52, 2),
(1, 58, 2),
(1, 65, 2);

-- --------------------------------------------------------

--
-- Table structure for table `client_product_designs`
--

CREATE TABLE `client_product_designs` (
  `client_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `design_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_product_sizes`
--

CREATE TABLE `client_product_sizes` (
  `client_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `size_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_product_sizes`
--

INSERT INTO `client_product_sizes` (`client_id`, `product_id`, `size_id`) VALUES
(1, 14, 8),
(1, 14, 9),
(1, 14, 10),
(1, 14, 11),
(1, 14, 12),
(1, 14, 13),
(1, 14, 14),
(1, 33, 8),
(1, 33, 9),
(1, 33, 10),
(1, 33, 11),
(1, 33, 12),
(1, 33, 13),
(1, 33, 14),
(1, 34, 8),
(1, 34, 9),
(1, 34, 10),
(1, 34, 11),
(1, 34, 12),
(1, 34, 13),
(1, 34, 14),
(1, 46, 8),
(1, 46, 9),
(1, 46, 10),
(1, 46, 11),
(1, 46, 12),
(1, 46, 13),
(1, 46, 14),
(1, 47, 8),
(1, 47, 9),
(1, 47, 10),
(1, 47, 11),
(1, 47, 12),
(1, 47, 13),
(1, 47, 14),
(1, 48, 8),
(1, 48, 9),
(1, 48, 10),
(1, 48, 11),
(1, 48, 12),
(1, 48, 13),
(1, 48, 14),
(1, 51, 8),
(1, 51, 9),
(1, 51, 10),
(1, 51, 11),
(1, 51, 12),
(1, 51, 13),
(1, 51, 14),
(1, 58, 8),
(1, 58, 9),
(1, 58, 10),
(1, 58, 11),
(1, 58, 12),
(1, 58, 13),
(1, 58, 14),
(1, 65, 8),
(1, 65, 9),
(1, 65, 10),
(1, 65, 11),
(1, 65, 12),
(1, 65, 13),
(1, 65, 14);

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

CREATE TABLE `colors` (
  `id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `hex` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`id`, `name`, `hex`) VALUES
(2, 'Black', '#000000'),
(3, 'White', '#FFFFFF'),
(4, 'Aquatic Blue', '\r'),
(5, 'Athletic Maroon', '\r'),
(6, 'Cardinal', '\r'),
(7, 'Coal Grey', '\r'),
(8, 'Dark Chocolate Brown', '\r'),
(9, 'Gold', '\r'),
(10, 'Heather Navy', '\r'),
(11, 'Heather Sangria', '\r'),
(12, 'Kelly', '\r'),
(13, 'Light Blue', '\r'),
(14, 'Navy', '\r'),
(15, 'Neon Pink', '\r'),
(16, 'Olive', '\r'),
(17, 'Pale Blush', '\r'),
(18, 'S. Green', '\r'),
(19, 'Sapphire', '\r'),
(20, 'Teal', '\r'),
(21, 'True Navy', '\r'),
(22, 'Woodland Brown', '\r'),
(23, 'Ash', '\r'),
(24, 'Athletic Heather', '\r'),
(25, 'Athletic Kelly', '\r'),
(26, 'Black Heather', '\r'),
(27, 'Bright Aqua', '\r'),
(28, 'Candy Pink', '\r'),
(29, 'Carolina Blue', '\r'),
(30, 'Charcoal', '\r'),
(31, 'Clover Green', '\r'),
(32, 'Coral', '\r'),
(33, 'Coyote Brown', '\r'),
(34, 'Crème', '\r'),
(35, 'Dark Green', '\r'),
(36, 'Dark Heather Grey', '\r'),
(37, 'Flush Pink', '\r'),
(38, 'Grapphite Heather', '\r'),
(39, 'Heather Athletic Maroon', '\r'),
(40, 'Heather Dark Chocolate Brown', '\r'),
(41, 'Heather Purple', '\r'),
(42, 'Hether Red', '\r'),
(43, 'Heather Royal', '\r'),
(44, 'Heathered Dusty Peach', '\r'),
(45, 'Jadeite', '\r'),
(46, 'Jet Black', '\r'),
(47, 'Laurel Green', '\r'),
(48, 'Lavender', '\r'),
(49, 'Lemon Yellow', '\r'),
(50, 'Lime', '\r'),
(51, 'Medium Grey', '\r'),
(52, 'Natural', '\r'),
(53, 'Neon Blue', '\r'),
(54, 'Neon Green', '\r'),
(55, 'Neon Orange', '\r'),
(56, 'Neon Yellow', '\r'),
(57, 'Neptune Blue', '\r'),
(58, 'Oatmel Heather', '\r'),
(59, 'Olive Drab Green', '\r'),
(60, 'Olive Drab Green Heather', '\r'),
(61, 'Orange', '\r'),
(62, 'Purple', '\r'),
(63, 'Red', '\r'),
(64, 'Royal', '\r'),
(65, 'S. Orange', '\r'),
(66, 'Sand', '\r'),
(67, 'Sangria', '\r'),
(68, 'Silver', '\r'),
(69, 'Steel Blue', '\r'),
(70, 'Stonewashed Blue', '\r'),
(71, 'Team Purple', '\r'),
(72, 'Tenessee Orange', '\r'),
(73, 'True Celadon', '\r'),
(74, 'True Royal', '\r'),
(75, 'Tundra Blue', '\r'),
(76, 'Yellow', '\r'),
(77, 'Zinnia', '');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_name` varchar(200) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `contact_name`, `email`, `phone`, `notes`, `created_at`) VALUES
(1, 'SOAr High School', 'Athletics', 'athletics@example.com', '555-0100', NULL, '2025-11-04 21:08:00'),
(2, 'NICK HAYES', 'NICK HAYES', 'NHAYES815@GMAIL.COM', '6618396783', NULL, '2025-11-05 05:13:17'),
(3, 'Nicholas S Hayes', 'Nicholas S Hayes', 'desertstar33@gmail.com', '6614882801', NULL, '2025-11-05 05:14:00');

-- --------------------------------------------------------

--
-- Table structure for table `designs`
--

CREATE TABLE `designs` (
  `id` bigint(20) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `name` varchar(200) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL,
  `external_ref` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `client_id` bigint(20) DEFAULT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `customer_email` varchar(190) DEFAULT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `customer_id` bigint(20) DEFAULT NULL,
  `intake_channel` enum('paper','phone','web','walk-in') NOT NULL,
  `status` enum('received','awaiting_supplies','supplies_ordered','supplies_received','in_production','ready_for_pickup','shipped','delivered','cancelled') NOT NULL DEFAULT 'received',
  `priority` tinyint(4) DEFAULT 3,
  `due_date` date DEFAULT NULL,
  `last_edited_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `edited_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `external_ref`, `tracking_number`, `client_id`, `product_id`, `customer_name`, `customer_email`, `customer_phone`, `customer_id`, `intake_channel`, `status`, `priority`, `due_date`, `last_edited_at`, `completed_at`, `edited_count`, `created_at`, `updated_at`) VALUES
(1, 'TEST-001', NULL, NULL, NULL, NULL, NULL, NULL, 1, 'phone', 'in_production', 2, '2025-11-11', '2025-11-08 06:01:42', NULL, 1, '2025-11-04 21:08:00', '2025-11-08 06:01:42'),
(2, '10101', NULL, NULL, NULL, NULL, NULL, NULL, 2, 'paper', 'supplies_ordered', 3, '2025-11-17', NULL, NULL, 0, '2025-11-05 05:13:17', '2025-11-05 06:18:41'),
(3, '10102', NULL, NULL, NULL, NULL, NULL, NULL, 3, 'web', 'supplies_received', 3, '2025-11-21', NULL, NULL, 0, '2025-11-05 05:14:00', '2025-11-05 06:18:49'),
(4, '1234', NULL, 1, NULL, 'NICK HAYES', 'NHAYES815@GMAIL.COM', '6618396783', NULL, 'web', 'received', 3, '2025-11-25', '2025-11-07 04:44:08', NULL, 1, '2025-11-07 02:10:23', '2025-11-07 04:44:08'),
(5, '', NULL, 9, NULL, 'NICK HAYES', 'NHAYES815@GMAIL.COM', '6618396783', NULL, 'web', 'received', 3, NULL, NULL, NULL, 0, '2025-11-07 04:54:29', '2025-11-07 04:54:29');

-- --------------------------------------------------------

--
-- Table structure for table `order_audits`
--

CREATE TABLE `order_audits` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `edited_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stage` varchar(40) DEFAULT NULL,
  `editor` varchar(150) DEFAULT NULL,
  `changes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`changes_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_audits`
--

INSERT INTO `order_audits` (`id`, `order_id`, `edited_at`, `stage`, `editor`, `changes_json`) VALUES
(1, 4, '2025-11-07 04:44:08', 'received', NULL, '{\"customer_name\":{\"before\":null,\"after\":\"NICK HAYES\"},\"customer_email\":{\"before\":null,\"after\":\"NHAYES815@GMAIL.COM\"},\"customer_phone\":{\"before\":null,\"after\":\"6618396783\"},\"color_id\":{\"before\":null,\"after\":2},\"size_id\":{\"before\":null,\"after\":11}}');

-- --------------------------------------------------------

--
-- Table structure for table `order_edits`
--

CREATE TABLE `order_edits` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `field` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `order_edits`
--

INSERT INTO `order_edits` (`id`, `order_id`, `user_id`, `field`, `old_value`, `new_value`, `created_at`) VALUES
(1, 1, NULL, 'status', 'delivered', 'in_production', '2025-11-08 06:01:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `color_id` bigint(20) DEFAULT NULL,
  `size_id` bigint(20) DEFAULT NULL,
  `design_id` bigint(20) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `customization` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`customization`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `color_id`, `size_id`, `design_id`, `sku`, `name`, `quantity`, `unit_price`, `customization`) VALUES
(1, 1, NULL, NULL, NULL, NULL, 'HOODIE-XL', 'Team Hoodie (XL)', 6, 45.00, NULL),
(2, 1, NULL, NULL, NULL, NULL, 'TOWEL', 'Rally Towels', 50, 6.00, NULL),
(3, 2, NULL, NULL, NULL, NULL, '', 'Axe N Dagger Unisex Tee', 1, 23.99, NULL),
(4, 3, NULL, NULL, NULL, NULL, '', 'Axe N Dagger Unisex Tee', 1, 14.99, NULL),
(5, 4, 14, 2, 11, NULL, 'Dri-Fit Tee', 'Dri-Fit Tee', 1, 0.00, '{\"color\": \"Black\", \"size\": \"Adult XL\", \"design\": null}'),
(6, 5, NULL, NULL, NULL, NULL, NULL, 'Custom Item', 1, 0.00, '{\"color\": null, \"size\": null, \"design\": null}');

-- --------------------------------------------------------

--
-- Table structure for table `order_reopens`
--

CREATE TABLE `order_reopens` (
  `id` int(11) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `from_status` varchar(50) NOT NULL,
  `to_status` varchar(50) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `active`) VALUES
(4, '1/4 Zip Shirt', '1/4 Zip Shirt', 1),
(5, '2 Button Jersey', '2 Button Jersey', 1),
(6, '3 Button Evelution Henley', '3 Button Evelution Henley', 1),
(7, 'Backpack', 'Backpack', 1),
(8, 'Baseball Jersey', 'Baseball Jersey', 1),
(9, 'Beanie', 'Beanie', 1),
(10, 'Bottle', 'Bottle', 1),
(11, 'Competitor Shorts', 'Competitor Shorts', 1),
(12, 'Crew Neck Sweater', 'Crew Neck Sweater', 1),
(13, 'Decals', 'Decals', 1),
(14, 'Dri-Fit Tee', 'Dri-Fit Tee', 1),
(15, 'Duffel Bag', 'Duffel Bag', 1),
(16, 'Featherweight T-Shirt Hoodie', 'Featherweight T-Shirt Hoodie', 1),
(17, 'Flat Bill Snapback', 'Flat Bill Snapback', 1),
(18, 'Fleece Lettermans Jacket', 'Fleece Lettermans Jacket', 1),
(19, 'FlexFit Curved Bill Cap', 'FlexFit Curved Bill Cap', 1),
(20, 'Hooded Tee', 'Hooded Tee', 1),
(21, 'Hyperform Sleeveless Compression', 'Hyperform Sleeveless Compression', 1),
(22, 'Insulated Leathermans Jacket', 'Insulated Leathermans Jacket', 1),
(23, 'Jersey Knit Polo', 'Jersey Knit Polo', 1),
(24, 'Ladies 3/4 Sleeve Tee', 'Ladies 3/4 Sleeve Tee', 1),
(25, 'Ladies Full Button Dress Shirt', 'Ladies Full Button Dress Shirt', 1),
(26, 'Ladies Full Zip Jacket', 'Ladies Full Zip Jacket', 1),
(27, 'Ladies Polo', 'Ladies Polo', 1),
(28, 'Ladies Racer Back Tank', 'Ladies Racer Back Tank', 1),
(29, 'Ladies Rocket Tank', 'Ladies Rocket Tank', 1),
(30, 'Ladies V-Neck Tee', 'Ladies V-Neck Tee', 1),
(31, 'Ladies Zephyr Zip-Up', 'Ladies Zephyr Zip-Up', 1),
(32, 'Lanyard', 'Lanyard', 1),
(33, 'Long Sleeve Tee', 'Long Sleeve Tee', 1),
(34, 'Loose Pullover Hoodie', 'Loose Pullover Hoodie', 1),
(35, 'Mens 3/4 Sleeve Tee', 'Mens 3/4 Sleeve Tee', 1),
(36, 'Mens Football Jersey', 'Mens Football Jersey', 1),
(37, 'Mens Full Button Dress Shirt', 'Mens Full Button Dress Shirt', 1),
(38, 'Mens Full Zip Jacket', 'Mens Full Zip Jacket', 1),
(39, 'Mens Microfleece Jacket', 'Mens Microfleece Jacket', 1),
(40, 'Mens Polo', 'Mens Polo', 1),
(41, 'Mens Quarter-Zip Sweatshirt', 'Mens Quarter-Zip Sweatshirt', 1),
(42, 'Mens Tri-Blend Hoodie', 'Mens Tri-Blend Hoodie', 1),
(43, 'Mens Zephyr Zip-Up', 'Mens Zephyr Zip-Up', 1),
(44, 'Mug', 'Mug', 1),
(45, 'New Era Tee', 'New Era Tee', 1),
(46, 'Nike Pullover Hoodie', 'Nike Pullover Hoodie', 1),
(47, 'Nike Sports Tee', 'Nike Sports Tee', 1),
(48, 'Nike Zip-Up Hoodie', 'Nike Zip-Up Hoodie', 1),
(49, 'Perfect Weight Fleece Cropped Crew', 'Perfect Weight Fleece Cropped Crew', 1),
(50, 'Posicharge Mesh 7\" Shorts', 'Posicharge Mesh 7\" Shorts', 1),
(51, 'Pullover Hoodie', 'Pullover Hoodie', 1),
(52, 'Rally Towel', 'Rally Towel', 1),
(53, 'Shorts w/ Pockets', 'Shorts w/ Pockets', 1),
(54, 'Socks', 'Socks', 1),
(55, 'Sport-Tek Wind Pants', 'Sport-Tek Wind Pants', 1),
(56, 'Sweat Pants', 'Sweat Pants', 1),
(57, 'Tote Bag', 'Tote Bag', 1),
(58, 'Unisex Tee', 'Unisex Tee', 1),
(59, 'Visor', 'Visor', 1),
(60, 'Womens Flex Waist Bike Shorts', 'Womens Flex Waist Bike Shorts', 1),
(61, 'Womens Football Jersey', 'Womens Football Jersey', 1),
(62, 'Womens Microfleece Jacket', 'Womens Microfleece Jacket', 1),
(63, 'Womens Quarter-Zip Sweatshirt', 'Womens Quarter-Zip Sweatshirt', 1),
(64, 'Womens Tri-Blend Hoodie', 'Womens Tri-Blend Hoodie', 1),
(65, 'Zip-Up Hoodie', 'Zip-Up Hoodie', 1);

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `id` bigint(20) NOT NULL,
  `code` varchar(40) NOT NULL,
  `label` varchar(80) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`id`, `code`, `label`, `sort_order`) VALUES
(2, 'YXS', 'Youth X-Small', 10),
(3, 'YS', 'Youth Small', 20),
(4, 'YM', 'Youth Medium', 30),
(5, 'YL', 'Youth Large', 40),
(6, 'YXL', 'Youth X-Large', 50),
(7, 'XS', 'X-Small', 60),
(8, 'S', 'Adult Small', 70),
(9, 'M', 'Adult Medium', 80),
(10, 'L', 'Adult Large', 90),
(11, 'XL', 'Adult XL', 100),
(12, '2XL', 'Adult 2XL', 110),
(13, '3XL', 'Adult 3XL', 120),
(14, '4XL', 'Adult 4XL', 130),
(15, '5XL', 'Adult 5XL', 140),
(16, '6XL', 'Adult 6XL', 150);

-- --------------------------------------------------------

--
-- Table structure for table `status_history`
--

CREATE TABLE `status_history` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `changed_at` timestamp NULL DEFAULT current_timestamp(),
  `changed_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `status_history`
--

INSERT INTO `status_history` (`id`, `order_id`, `from_status`, `to_status`, `changed_at`, `changed_by`) VALUES
(1, 1, NULL, 'received', '2025-11-04 21:08:00', NULL),
(2, 1, 'received', 'in_production', '2025-11-05 04:24:55', NULL),
(3, 1, 'in_production', 'received', '2025-11-05 04:24:58', NULL),
(4, 1, 'received', 'delivered', '2025-11-05 05:12:24', NULL),
(5, 2, NULL, 'received', '2025-11-05 05:13:17', NULL),
(6, 3, NULL, 'received', '2025-11-05 05:14:00', NULL),
(7, 2, 'received', 'supplies_ordered', '2025-11-05 06:18:41', NULL),
(8, 3, 'received', 'supplies_received', '2025-11-05 06:18:49', NULL),
(9, 4, NULL, 'received', '2025-11-07 02:10:23', NULL),
(10, 5, NULL, 'received', '2025-11-07 04:54:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `role` enum('admin','csr','purchaser','production','shipper','viewer') NOT NULL DEFAULT 'viewer',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `full_name`, `role`, `active`, `created_at`) VALUES
(1, 'nhayes', 'nick@barebones-apparel.com', '$2y$10$r/fA36qhajCUIFGW2hogMu2Xj73xH5tCuXMh5/Ds38fnEN9dw5kn2', NULL, 'admin', 1, '2025-11-08 21:47:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `client_products`
--
ALTER TABLE `client_products`
  ADD PRIMARY KEY (`client_id`,`product_id`),
  ADD KEY `fk_cp_product` (`product_id`);

--
-- Indexes for table `client_product_colors`
--
ALTER TABLE `client_product_colors`
  ADD PRIMARY KEY (`client_id`,`product_id`,`color_id`),
  ADD KEY `fk_cpc_product` (`product_id`),
  ADD KEY `fk_cpc_color` (`color_id`);

--
-- Indexes for table `client_product_designs`
--
ALTER TABLE `client_product_designs`
  ADD PRIMARY KEY (`client_id`,`product_id`,`design_id`),
  ADD KEY `fk_cpd_product` (`product_id`),
  ADD KEY `fk_cpd_design` (`design_id`);

--
-- Indexes for table `client_product_sizes`
--
ALTER TABLE `client_product_sizes`
  ADD PRIMARY KEY (`client_id`,`product_id`,`size_id`),
  ADD KEY `fk_cps_product` (`product_id`),
  ADD KEY `fk_cps_size` (`size_id`);

--
-- Indexes for table `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `designs`
--
ALTER TABLE `designs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_design_client` (`client_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_due` (`due_date`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_customer_email` (`customer_email`),
  ADD KEY `idx_orders_customer_phone` (`customer_phone`),
  ADD KEY `idx_orders_external_ref` (`external_ref`),
  ADD KEY `idx_orders_client_id_created` (`client_id`,`created_at`),
  ADD KEY `idx_orders_completed_at` (`completed_at`),
  ADD KEY `idx_orders_tracking` (`tracking_number`);

--
-- Indexes for table `order_audits`
--
ALTER TABLE `order_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_order` (`order_id`);

--
-- Indexes for table `order_edits`
--
ALTER TABLE `order_edits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `fk_edits_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_order` (`order_id`),
  ADD KEY `fk_oi_product` (`product_id`),
  ADD KEY `fk_oi_color` (`color_id`),
  ADD KEY `fk_oi_size` (`size_id`),
  ADD KEY `fk_oi_design` (`design_id`);

--
-- Indexes for table `order_reopens`
--
ALTER TABLE `order_reopens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `status_history`
--
ALTER TABLE `status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hist_order` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `colors`
--
ALTER TABLE `colors`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `designs`
--
ALTER TABLE `designs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_audits`
--
ALTER TABLE `order_audits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_edits`
--
ALTER TABLE `order_edits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_reopens`
--
ALTER TABLE `order_reopens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `status_history`
--
ALTER TABLE `status_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_products`
--
ALTER TABLE `client_products`
  ADD CONSTRAINT `fk_cp_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_product_colors`
--
ALTER TABLE `client_product_colors`
  ADD CONSTRAINT `fk_cpc_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpc_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_product_designs`
--
ALTER TABLE `client_product_designs`
  ADD CONSTRAINT `fk_cpd_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpd_design` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpd_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_product_sizes`
--
ALTER TABLE `client_product_sizes`
  ADD CONSTRAINT `fk_cps_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cps_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cps_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `designs`
--
ALTER TABLE `designs`
  ADD CONSTRAINT `fk_design_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `order_audits`
--
ALTER TABLE `order_audits`
  ADD CONSTRAINT `fk_audit_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_edits`
--
ALTER TABLE `order_edits`
  ADD CONSTRAINT `fk_edits_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_edits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`),
  ADD CONSTRAINT `fk_oi_design` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`),
  ADD CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_oi_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`);

--
-- Constraints for table `order_reopens`
--
ALTER TABLE `order_reopens`
  ADD CONSTRAINT `order_reopens_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `status_history`
--
ALTER TABLE `status_history`
  ADD CONSTRAINT `fk_hist_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
