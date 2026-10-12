-- =========================================================
-- QARTIV - SMART COMMERCE PLATFORM DATABASE EXPORT
-- Exported on: 2026-10-09 15:47:51
-- Host: localhost | Database: qartiv
-- =========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
SET time_zone = '+00:00';

-- ---------------------------------------------------------
-- Table structure for `addresses`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pincode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `addresses`

INSERT INTO `addresses` (`id`, `user_id`, `full_name`, `phone`, `address`, `city`, `state`, `pincode`, `created_at`) VALUES ('1', '2', 'Maddula manasa', '8247262119', 'brodipet 2/3, vaibhav apartment ,Guntur\r\nGuntur,Andhra pradesh', 'Guntur', 'Andhra Pradesh', '522002', '2026-09-12 10:11:24');

-- ---------------------------------------------------------
-- Table structure for `cart`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `cart`

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `created_at`) VALUES ('7', '2', '8', '6', '2026-09-29 18:52:47');
INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `created_at`) VALUES ('6', '5', '6', '2', '2026-09-29 17:43:41');

-- ---------------------------------------------------------
-- Table structure for `categories`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `categories`

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `status`, `created_at`) VALUES ('1', 'Electronics', 'Smart electronics and modern gadgets.', 'cat_electronics.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `categories` (`id`, `name`, `description`, `image`, `status`, `created_at`) VALUES ('2', 'Fashion', 'Trendy fashion products for everyday style.', 'cat_fashion.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `categories` (`id`, `name`, `description`, `image`, `status`, `created_at`) VALUES ('3', 'Home & Living', 'Useful products for a modern home.', 'cat_home.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `categories` (`id`, `name`, `description`, `image`, `status`, `created_at`) VALUES ('4', 'Beauty', 'Beauty and personal care essentials.', 'cat_beauty.jpg', 'active', '2026-09-07 10:23:48');

-- ---------------------------------------------------------
-- Table structure for `coupons`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_amount` decimal(10,2) DEFAULT '0.00',
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `coupons`

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `minimum_amount`, `expiry_date`, `status`, `created_at`) VALUES ('1', 'WELCOME10', 'percentage', '10.00', '500.00', '2028-12-31', 'active', '2026-09-29 18:12:26');
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `minimum_amount`, `expiry_date`, `status`, `created_at`) VALUES ('2', 'QARTIV500', 'fixed', '500.00', '2000.00', '2028-12-31', 'active', '2026-09-29 18:12:26');
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `minimum_amount`, `expiry_date`, `status`, `created_at`) VALUES ('3', 'FESTIVE20', 'percentage', '20.00', '1500.00', '2028-12-31', 'active', '2026-09-29 18:12:26');

-- ---------------------------------------------------------
-- Table structure for `order_items`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `order_items`

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES ('1', '1', '7', '2', '764.15');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES ('2', '1', '5', '5', '1195.08');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES ('3', '1', '1', '6', '2124.15');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES ('4', '1', '6', '1', '899.10');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES ('5', '1', '2', '3', '3149.10');

-- ---------------------------------------------------------
-- Table structure for `orders`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `address_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'COD',
  `payment_status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `order_status` enum('placed','confirmed','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'placed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `address_id` (`address_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `orders`

INSERT INTO `orders` (`id`, `user_id`, `address_id`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `created_at`) VALUES ('1', '2', '1', '30595.00', 'COD', 'pending', 'placed', '2026-09-12 10:11:24');

-- ---------------------------------------------------------
-- Table structure for `products`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `discount` decimal(5,2) DEFAULT '0.00',
  `stock` int DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `products`

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('1', '1', 'Wireless Headphones', 'Premium wireless headphones with clear sound and comfortable design.', '2499.00', '15.00', '14', 'prod_headphones.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('2', '1', 'Smart Watch', 'Modern smartwatch with fitness tracking and smart notifications.', '3499.00', '10.00', '12', 'prod_smartwatch.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('3', '2', 'Classic Sneakers', 'Comfortable everyday sneakers with a clean modern style.', '1999.00', '20.00', '25', 'prod_sneakers.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('4', '2', 'Premium Backpack', 'Stylish and durable backpack suitable for college and travel.', '1599.00', '12.00', '18', 'prod_backpack.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('5', '3', 'Minimal Table Lamp', 'Elegant table lamp designed for a modern workspace or bedroom.', '1299.00', '8.00', '7', 'prod_table_lamp.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('6', '3', 'Aroma Diffuser', 'Compact aroma diffuser for a relaxing home environment.', '999.00', '10.00', '9', 'prod_diffuser.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('7', '4', 'Daily Face Care Kit', 'Complete daily skincare essentials for a fresh and simple routine.', '899.00', '15.00', '18', 'prod_skincare.jpg', 'active', '2026-09-07 10:23:48');
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `discount`, `stock`, `image`, `status`, `created_at`) VALUES ('8', '4', 'Perfume Collection', 'Elegant fragrance collection with a long-lasting premium aroma.', '1799.00', '18.00', '14', 'prod_perfume.jpg', 'active', '2026-09-07 10:23:48');

-- ---------------------------------------------------------
-- Table structure for `reviews`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `reviews`

INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES ('1', '2', '1', '5', 'Outstanding sound clarity and the noise cancellation is top notch! Very comfortable for long work calls and gym sessions.', '2026-09-29 18:29:24');
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES ('2', '3', '1', '5', 'Build quality feels super premium with the gold accents. Battery lasts literally 4 days without needing a charge.', '2026-09-29 18:29:24');
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES ('3', '2', '2', '5', 'The OLED screen is vivid and outdoor visibility is great. Step and heart rate tracking are extremely accurate.', '2026-09-29 18:29:24');
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES ('4', '3', '3', '5', 'Hands down the most comfortable sneakers I own. Perfect stitching and the calfskin feels luxurious.', '2026-09-29 18:29:24');
INSERT INTO `reviews` (`id`, `user_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES ('5', '2', '8', '5', 'A captivating scent that lasts the entire day! The amber notes combined with bergamot smell magnificent.', '2026-09-29 18:29:24');

-- ---------------------------------------------------------
-- Table structure for `users`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `status` enum('active','blocked') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `users`

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES ('1', 'Qartiv Admin', 'admin@qartiv.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC3KJ7Kj2K8q0K8K8K8K', 'admin', 'active', '2026-09-06 18:53:21');
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES ('2', 'Maddula manasa', 'maddulamanasa485@gmail.com', '$2y$10$yA2rzV4I0ZuwoUVrgj6oC.hPDTIAkZ..nUFmdei9Pgrt2AoslFcPC', 'customer', 'active', '2026-09-06 19:04:13');
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES ('3', 'Maddula manasa', 'maddulamanasa5@gmail.com', '$2y$10$guDBbOn5ONvokjGTHZDccOIGQboPLjoUFiyTikZrmIfLyqKYYsFf6', 'customer', 'active', '2026-09-06 19:46:51');
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES ('4', 'Maddula Manasa', 'maddulamanasa4@gmail.com', '$2y$10$DL6Hc4eCl90RmzIwz0KYreDFQjJYFxmvMK8xI76VWqEUXpSBGgXdO', 'customer', 'active', '2026-09-12 10:35:01');
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES ('5', 'maddula jathin', 'maddulajathin4@gmail.com', '$2y$10$oPt6hKAxFI9Vsjn/nnm7benFyqY3at7lj5VTL6A3ahcNL29ZsvcyK', 'customer', 'active', '2026-09-29 17:43:26');

-- ---------------------------------------------------------
-- Table structure for `wishlist`
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `wishlist`

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES ('1', '2', '6', '2026-09-07 10:29:01');
INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `created_at`) VALUES ('2', '2', '5', '2026-09-12 10:00:38');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
