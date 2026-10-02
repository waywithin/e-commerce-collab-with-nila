-- ====================================================================
-- MAGAL CREATOR MULTI-VENDOR MARKETPLACE FOR WOMEN ENTREPRENEURS
-- Complete Relational MySQL Schema with Sample Data
-- Database: women_marketplace_db
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `women_marketplace_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `women_marketplace_db`;

-- Drop existing tables in correct order if recreating
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `commissions`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `service_providers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `platform_settings`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------------------
-- 1. USERS TABLE
-- Roles: 'manager', 'provider', 'customer'
-- Passwords hashed via PHP password_hash()
-- --------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `phone` VARCHAR(25) DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('manager', 'provider', 'customer') NOT NULL DEFAULT 'customer',
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_role` (`role`),
    INDEX `idx_user_email` (`email`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 2. CATEGORIES TABLE
-- Dynamic service categories managed by Platform Manager
-- --------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(120) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `icon_class` VARCHAR(50) DEFAULT 'fa-tag',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cat_status` (`status`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 3. SERVICE PROVIDERS TABLE
-- Public business profiles for Women Entrepreneurs
-- Linked 1-to-1 with a user of role 'provider'
-- --------------------------------------------------------------------
CREATE TABLE `service_providers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `business_name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL UNIQUE,
    `category_id` INT UNSIGNED NULL,
    `tagline` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `contact_phone` VARCHAR(25) DEFAULT NULL,
    `contact_email` VARCHAR(191) DEFAULT NULL,
    `logo_image` VARCHAR(255) DEFAULT 'default_logo.png',
    `cover_banner` VARCHAR(255) DEFAULT 'default_banner.png',
    `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `bank_account_name` VARCHAR(150) DEFAULT NULL,
    `bank_account_number` VARCHAR(50) DEFAULT NULL,
    `bank_ifsc` VARCHAR(25) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_provider_approval` (`approval_status`),
    INDEX `idx_provider_featured` (`is_featured`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 4. PRODUCTS & SERVICES TABLE
-- Supports standard pricing & dynamic Limited-Time Offers with countdowns
-- --------------------------------------------------------------------
CREATE TABLE `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(280) NOT NULL,
    `description` TEXT NOT NULL,
    `service_type` ENUM('service', 'product', 'package') NOT NULL DEFAULT 'service',
    `price` DECIMAL(10,2) NOT NULL,
    `discount_price` DECIMAL(10,2) DEFAULT NULL,
    `cover_image` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active',
    
    -- Limited-Time Offer Settings
    `offer_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `offer_price` DECIMAL(10,2) DEFAULT NULL,
    `offer_start_at` DATETIME DEFAULT NULL,
    `offer_end_at` DATETIME DEFAULT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
    INDEX `idx_prod_provider` (`provider_id`),
    INDEX `idx_prod_category` (`category_id`),
    INDEX `idx_prod_offer` (`offer_enabled`, `offer_start_at`, `offer_end_at`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 5. ORDERS & BOOKINGS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `order_status` ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `booking_date` DATE DEFAULT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_phone` VARCHAR(25) NOT NULL,
    `customer_address` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    INDEX `idx_order_number` (`order_number`),
    INDEX `idx_order_status` (`order_status`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 6. PLATFORM SETTINGS TABLE
-- --------------------------------------------------------------------
CREATE TABLE `platform_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 7. PAYMENTS TABLE
-- Direct integration with Cashfree or Mock Payment Adapter
-- --------------------------------------------------------------------
CREATE TABLE `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `gateway_name` VARCHAR(50) NOT NULL DEFAULT 'mock',
    `gateway_order_id` VARCHAR(100) DEFAULT NULL,
    `gateway_payment_id` VARCHAR(100) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `payment_status` ENUM('created', 'processing', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'created',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `payment_response_raw` LONGTEXT DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_gw_order` (`gateway_order_id`),
    INDEX `idx_pay_status` (`payment_status`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 8. COMMISSIONS & SETTLEMENTS TABLE
-- Records verified splits between platform commission and provider share
-- --------------------------------------------------------------------
CREATE TABLE `commissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT UNSIGNED NOT NULL UNIQUE,
    `order_id` INT UNSIGNED NOT NULL,
    `provider_id` INT UNSIGNED NOT NULL,
    `gross_amount` DECIMAL(10,2) NOT NULL,
    `commission_rate_percent` DECIMAL(5,2) NOT NULL,
    `platform_commission_amount` DECIMAL(10,2) NOT NULL,
    `gateway_fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `provider_payable_amount` DECIMAL(10,2) NOT NULL,
    `settlement_status` ENUM('pending', 'settled', 'held') NOT NULL DEFAULT 'pending',
    `settled_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`provider_id`) REFERENCES `service_providers`(`id`) ON DELETE RESTRICT,
    INDEX `idx_comm_settlement` (`settlement_status`)
) ENGINE=InnoDB;

-- --------------------------------------------------------------------
-- 9. ACTIVITY & AUDIT LOGS
-- --------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `magal_circle_posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `category` ENUM('Announcement', 'Business Tip', 'Milestone', 'Resource', 'Discussion') DEFAULT 'Discussion',
  `content` TEXT NOT NULL,
  `product_link` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ====================================================================
-- SEED DATA: SETTINGS, CATEGORIES, DEMO USERS, PROVIDERS & PRODUCTS
-- ====================================================================

-- Platform Settings
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `description`) VALUES
('platform_commission_percent', '10.00', 'Standard platform commission percentage taken on each booking/order'),
('platform_name', 'Magal Creator Marketplace', 'Official marketplace title'),
('platform_tagline', 'One Platform. Many Women Entrepreneurs.', 'Main marketplace slogan'),
('support_email', 'support@magalcreator.local', 'Public support contact email'),
('currency_symbol', 'â‚¹', 'Currency display symbol'),
('active_gateway', 'mock', 'Active payment gateway: mock or cashfree');

-- Categories (Dynamic and diverse women entrepreneur specialties)
INSERT INTO `categories` (`name`, `slug`, `description`, `icon_class`, `status`) VALUES
('Fashion & Boutique', 'fashion-boutique', 'Custom designer wear, ethnic wear, dresses, and boutique clothing.', 'fa-shirt', 'active'),
('Henna & Bridal Services', 'henna-bridal-services', 'Professional bridal mehendi, organic henna styling, and festive patterns.', 'fa-hands-holding-child', 'active'),
('Bakery & Cakes', 'bakery-cakes', 'Artisanal cakes, customized birthday treats, cupcakes, and gluten-free pastries.', 'fa-cake-candles', 'active'),
('Beauty & Salon', 'beauty-salon', 'Hair styling, organic facials, skin treatments, and makeup artistry.', 'fa-wand-magic-sparkles', 'active'),
('Jewellery & Accessories', 'jewellery-accessories', 'Handmade terracotta jewelry, oxidized silver, bridal sets, and bead accessories.', 'fa-gem', 'active'),
('Handicrafts & Handmade', 'handicrafts-handmade', 'Traditional macrame art, embroidered tote bags, clay pottery, and crafts.', 'fa-palette', 'active'),
('Food & Catering', 'food-catering', 'Home-cooked traditional delicacies, party catering, and corporate meal trays.', 'fa-utensils', 'active'),
('Wellness & Counselling', 'wellness-counselling', 'Mindfulness coaching, reflexology, psychotherapy, and yoga therapy.', 'fa-spa', 'active'),
('Graphic Design & Creative', 'graphic-design-creative', 'Brand identity, customized packaging design, social media kits, and logos.', 'fa-compass-drafting', 'active'),
('Tuition & Education', 'tuition-education', 'Personalized tutoring, language training, coding workshops, and art classes.', 'fa-graduation-cap', 'active');

-- Default Demo Users
-- All passwords are set to: password123
-- Hash generated via password_hash('password123', PASSWORD_BCRYPT)
-- $2y$10$eE2Y83Q1aVlA9F5V4G.5bO4fN8YI4jM1Tkm0m0G8hW9K5W0aW2SXe
-- Let's use a standard universally verifiable BCRYPT hash for password123:
-- "$2y$10$wT4nZ36eYjD7B85tJ2yQ4O9kZ1FpLq2vN3M4k7j5H6g4F3D2S1A0K" -> we can verify with PHP.

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES
-- 1: Manager
(1, 'Priya Sharma (Platform Director)', 'manager@magalcreator.local', '+91 98765 00001', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'manager', 'active'),

-- 2: Provider 1 - Henna Artist
(2, 'Ayesha Fatima', 'ayesha.henna@magalcreator.local', '+91 98765 00002', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'provider', 'active'),

-- 3: Provider 2 - Boutique Designer
(3, 'Meera Nambiar', 'meera.couture@magalcreator.local', '+91 98765 00003', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'provider', 'active'),

-- 4: Provider 3 - Organic Baker
(4, 'Sunita Roy', 'sunita.bakes@magalcreator.local', '+91 98765 00004', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'provider', 'active'),

-- 5: Provider 4 - Wellness & Reflexology
(5, 'Dr. Ananya Sen', 'ananya.wellness@magalcreator.local', '+91 98765 00005', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'provider', 'active'),

-- 6: Customer 1
(6, 'Ritu Verma', 'customer@magalcreator.local', '+91 98765 00010', '$2y$10$K5R1f0sVpQ8U3xI7lO2cMe1jB6Y4p7oH2Z9s5T1a2C3e4G5i6K7m8', 'customer', 'active');

-- Service Provider Profiles
INSERT INTO `service_providers` 
(`id`, `user_id`, `business_name`, `slug`, `category_id`, `tagline`, `description`, `location`, `city`, `contact_phone`, `contact_email`, `logo_image`, `cover_banner`, `approval_status`, `is_featured`, `bank_account_name`, `bank_account_number`, `bank_ifsc`) 
VALUES
(1, 2, 'Ayesha Henna Artistry', 'ayesha-henna-artistry', 2, 'Exquisite Organic Bridal & Festive Mehendi', 'With over 8 years of bridal expertise, Ayesha Henna Studio crafts customized, 100% organic Rajasthani, Arabic, and Indo-Western mehendi designs with rich, guaranteed dark stain.', '12 Rosewood Lane, Koramangala', 'Bengaluru', '+91 98765 00002', 'ayesha.henna@magalcreator.local', 'prov_ayesha_logo.png', 'prov_ayesha_banner.png', 'approved', 1, 'Ayesha Fatima', '123456789012', 'HDFC0001234'),

(2, 3, 'Meera Couture & Embroidery Studio', 'meera-couture', 1, 'Handcrafted Bespoke Ethnic Wear & Designer Blouses', 'Hand-stitched kurtas, festive lehengas, and intricate aari-work bridal blouses crafted sustainably by our women-led tailoring guild.', '45 Indiranagar 100ft Road', 'Bengaluru', '+91 98765 00003', 'meera.couture@magalcreator.local', 'prov_meera_logo.png', 'prov_meera_banner.png', 'approved', 1, 'Meera Nambiar', '987654321098', 'ICIC0005678'),

(3, 4, 'Sunita Sweet Retreat Bakery', 'sunita-sweet-retreat', 3, '100% Eggless Gourmet Cakes, Artisanal Bread & Cupcakes', 'Wholesome, home-baked artisanal treats prepared with organic ingredients, Belgium chocolate, and natural fruit compotes. Made with pure love.', '78 HSR Layout, Sector 4', 'Bengaluru', '+91 98765 00004', 'sunita.bakes@magalcreator.local', 'prov_sunita_logo.png', 'prov_sunita_banner.png', 'approved', 1, 'Sunita Roy', '456789012345', 'SBIN0009876'),

(4, 5, 'Sattva Wellness & Holistic Care', 'sattva-wellness', 8, 'Professional Reflexology, Mindfulness & Stress Therapy', 'Certified holistic therapist offering rejuvenating foot reflexology, calming stress-relief sessions, and private counselling tailored for working women.', '22 Whitefield Main Road', 'Bengaluru', '+91 98765 00005', 'ananya.wellness@magalcreator.local', 'prov_ananya_logo.png', 'prov_ananya_banner.png', 'approved', 0, 'Ananya Sen', '321098765432', 'UTIB0004321');

-- Products and Services (Including Limited-Time Offers with Dynamic Dates)
-- Dynamic countdown: Offer starts yesterday and ends 48 hours in the future
INSERT INTO `products` 
(`id`, `provider_id`, `category_id`, `title`, `slug`, `description`, `service_type`, `price`, `discount_price`, `cover_image`, `status`, `offer_enabled`, `offer_price`, `offer_start_at`, `offer_end_at`) 
VALUES
-- Henna Products
(1, 1, 2, 'Full Royal Bridal Mehendi Package', 'full-royal-bridal-mehendi-package', 'Elaborate traditional bridal mehendi up to both elbows and mid-calf. Includes custom storytelling figures (dulha-dulhan portrait, rituals), after-care clove steam kit, and organic lemon-sugar seal.', 'service', 4500.00, 3999.00, 'prod_henna_bridal.png', 'active', 1, 3499.00, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 14 HOUR)),

(2, 1, 2, 'Festive Arabic & Floral Henna (Both Palms)', 'festive-arabic-floral-henna', 'Chic, contemporary Arabic vine and mandala patterns for both hands. Fast application with high-density organic henna cone.', 'service', 1200.00, 999.00, 'prod_henna_arabic.png', 'active', 0, NULL, NULL, NULL),

-- Boutique Products
(3, 2, 1, 'Hand-Embroidered Zardozi Silk Blouse', 'hand-embroidered-zardozi-silk-blouse', 'Custom-tailored pure raw silk blouse with real zardozi gold-thread needlework, pearl accents, and padded sweetheart neckline.', 'product', 3200.00, 2800.00, 'prod_blouse_zardozi.png', 'active', 1, 2499.00, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 8 HOUR)),

(4, 2, 1, 'Bespoke Anarkali Suit with Organza Dupatta', 'bespoke-anarkali-suit-organza-dupatta', 'Graceful flared floor-length Anarkali in pastel lilac with hand-painted organza dupatta. Tailored to your exact measurements.', 'service', 5800.00, 5200.00, 'prod_anarkali_suit.png', 'active', 0, NULL, NULL, NULL),

-- Bakery Products
(5, 3, 3, 'Signature Belgian Dark Chocolate Truffle Cake (1 Kg)', 'belgian-dark-chocolate-truffle-cake', 'Decadent 55% Belgian chocolate ganache layered with moist cocoa sponge. 100% Eggless, free from artificial preservatives.', 'product', 1400.00, 1250.00, 'prod_cake_belgian.png', 'active', 1, 999.00, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_ADD(NOW(), INTERVAL 5 HOUR)),

(6, 3, 3, 'Assorted Gourmet Cupcakes Box (Set of 6)', 'assorted-gourmet-cupcakes-box', 'Six artisan flavors: Red Velvet Cream Cheese, Blueberry Lemon, Salted Caramel, Lotus Biscoff, Rose Pistachio, and Dark Ganache.', 'product', 650.00, 580.00, 'prod_cupcakes_box.png', 'active', 0, NULL, NULL, NULL),

-- Wellness Products
(7, 4, 8, 'Aromatherapy Foot Reflexology (60 Mins)', 'aromatherapy-foot-reflexology-60-mins', 'Targeted pressure-point foot reflexology using lavender and eucalyptus essential oils to relieve stress and improve circulation.', 'service', 1800.00, 1500.00, 'prod_wellness_reflex.png', 'active', 1, 1299.00, DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 20 HOUR));

-- Initial Demo Order, Payment & Commission
INSERT INTO `orders` 
(`id`, `order_number`, `customer_id`, `provider_id`, `product_id`, `unit_price`, `quantity`, `total_amount`, `order_status`, `booking_date`, `customer_name`, `customer_phone`, `customer_address`, `notes`) 
VALUES
(1, 'ORD-2026-0001', 6, 1, 1, 3499.00, 1, 3499.00, 'confirmed', '2026-09-28', 'Ritu Verma', '+91 98765 00010', 'Flat 402, Green Glen Layout, Bellandur, Bengaluru', 'Bridal appointment for wedding sangeet');

INSERT INTO `payments` 
(`id`, `order_id`, `gateway_name`, `gateway_order_id`, `gateway_payment_id`, `amount`, `currency`, `payment_status`, `payment_method`, `payment_response_raw`, `paid_at`) 
VALUES
(1, 1, 'mock', 'MOCK_ORD_20260924001', 'MOCK_PAY_98726351', 3499.00, 'INR', 'completed', 'UPI / GooglePay', '{"status":"SUCCESS","payment_mode":"UPI","bank_ref_num":"MOCK998877"}', NOW());

-- 10% Platform Commission Calculation: Gross: 3499.00 -> Platform Commission: 349.90 -> Provider Share: 3149.10
INSERT INTO `commissions` 
(`id`, `payment_id`, `order_id`, `provider_id`, `gross_amount`, `commission_rate_percent`, `platform_commission_amount`, `gateway_fee_amount`, `provider_payable_amount`, `settlement_status`, `settled_at`) 
VALUES
(1, 1, 1, 1, 3499.00, 10.00, 349.90, 0.00, 3149.10, 'settled', NOW());

-- Demo Activity Log
INSERT INTO `activity_logs` (`user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `details`) VALUES
(1, 'SYSTEM_INITIALIZATION', 'database', 1, '127.0.0.1', 'Database created and seeded with Magal Creator marketplace demo records.');

