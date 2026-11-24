-- ============================================================================
-- TAKASHI FRIED CHICKEN (TFC) - DATABASE SCHEMA FOR MYSQL 8.0+
-- Database: dasbord_takashi
-- Compatible with Laravel 11/12/13 Eloquent ORM & Raw MySQL
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `dasbord_takashi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dasbord_takashi`;

-- 1. OUTLETS TABLE
CREATE TABLE IF NOT EXISTS `outlets` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('CENTRAL_PLANT', 'STORE', 'CLOUD_KITCHEN') NOT NULL DEFAULT 'STORE',
    `address` TEXT NULL,
    `phone` VARCHAR(30) NULL,
    `manager_name` VARCHAR(100) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE (LARAVEL COMPATIBLE AUTH)
CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('OWNER', 'HQ_SUPPLY_CHAIN', 'STORE_MANAGER', 'KITCHEN_STAFF') NOT NULL DEFAULT 'STORE_MANAGER',
    `phone` VARCHAR(30) NULL,
    `remember_token` VARCHAR(100) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. SUPPLIERS TABLE
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `category` ENUM('POULTRY', 'DRY_GOODS', 'OIL', 'PACKAGING', 'PRODUCE') NOT NULL,
    `lead_time_days` INT DEFAULT 2,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `rating` DECIMAL(3, 2) DEFAULT 4.80,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. INVENTORY CATEGORIES
CREATE TABLE IF NOT EXISTS `categories` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `storage_type` ENUM('FROZEN', 'CHILLED', 'DRY', 'ROOM_TEMP') NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. INVENTORY ITEMS (MASTER SKU WITH ABC CLASSIFICATION)
CREATE TABLE IF NOT EXISTS `items` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `category_id` VARCHAR(36) NOT NULL,
    `sku` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `unit` VARCHAR(20) NOT NULL, -- 'kg', 'ekor', 'liter', 'zak', 'jerigen', 'pack'
    `abc_class` CHAR(1) NOT NULL DEFAULT 'B', -- 'A' (78% capital), 'B', 'C'
    `min_safety_stock` DECIMAL(10, 2) NOT NULL DEFAULT 10.00,
    `optimal_stock` DECIMAL(10, 2) NOT NULL DEFAULT 50.00,
    `standard_cost` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `target_shelf_life_days` INT NOT NULL DEFAULT 7,
    `max_dwell_hours` INT NULL, -- Max 48 hours for marinated chicken
    `natural_yield_tolerance` DECIMAL(4, 2) DEFAULT 0.03, -- 3% - 5% tolerance
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. REAL-TIME OUTLET STOCKS
CREATE TABLE IF NOT EXISTS `outlet_stocks` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `current_stock` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `allocated_stock` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `in_transit_stock` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `last_opname_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_outlet_item` (`outlet_id`, `item_id`),
    CONSTRAINT `fk_stocks_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stocks_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. INVENTORY BATCHES (FEFO ENGINE)
CREATE TABLE IF NOT EXISTS `inventory_batches` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `lot_number` VARCHAR(50) NOT NULL,
    `production_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `initial_qty` DECIMAL(10, 2) NOT NULL,
    `remaining_qty` DECIMAL(10, 2) NOT NULL,
    `unit_cost` DECIMAL(12, 2) NOT NULL,
    `arrival_temp_celsius` DECIMAL(4, 1) NULL,
    `dwell_hours` INT DEFAULT 12,
    `status` ENUM('ACTIVE', 'DEPLETED', 'QUARANTINED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_fefo` (`item_id`, `expiry_date`, `remaining_qty`),
    CONSTRAINT `fk_batches_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_batches_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. GOODS RECEIVING (STOCK IN)
CREATE TABLE IF NOT EXISTS `receiving_logs` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `receiving_number` VARCHAR(50) NOT NULL UNIQUE,
    `outlet_id` VARCHAR(36) NOT NULL,
    `supplier_id` VARCHAR(36) NOT NULL,
    `received_by` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `lot_number` VARCHAR(50) NOT NULL,
    `qty_received` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL,
    `unit_cost` DECIMAL(12, 2) NOT NULL,
    `production_date` DATE NOT NULL,
    `expiry_date` DATE NOT NULL,
    `arrival_temp_celsius` DECIMAL(4, 1) NOT NULL,
    `cold_chain_status` ENUM('PASS', 'WARNING', 'REJECTED') NOT NULL DEFAULT 'PASS',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rec_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_rec_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
    CONSTRAINT `fk_rec_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_rec_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. KITCHEN USAGE & SPOILAGE LOGS
CREATE TABLE IF NOT EXISTS `kitchen_logs` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NOT NULL,
    `user_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `log_type` ENUM('SALES', 'SPOILAGE') NOT NULL,
    `qty` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL,
    `spoilage_reason` ENUM('BURNT_OVERCOOK', 'HOLDING_EXPIRED_90M', 'OVER_PORTION', 'THAWING_LOSS', 'REGULAR_USE') NOT NULL,
    `note` TEXT NULL,
    `logged_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_kitchen_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_kitchen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_kitchen_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. HOLDING CABINET TIMERS (MAX 90 MINUTES)
CREATE TABLE IF NOT EXISTS `holding_cabinets` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NOT NULL,
    `batch_code` VARCHAR(50) NOT NULL,
    `item_name` VARCHAR(100) NOT NULL,
    `qty_pieces` INT NOT NULL,
    `station` VARCHAR(50) NOT NULL,
    `fried_at` TIMESTAMP NOT NULL,
    `max_minutes` INT DEFAULT 90,
    `status` ENUM('FRESH', 'WARNING_75M', 'EXPIRED_90M', 'SERVED', 'DISCARDED') NOT NULL DEFAULT 'FRESH',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cabinet_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TPM SENSOR LOGS (OIL SENSOR - LIMIT 24%)
CREATE TABLE IF NOT EXISTS `tpm_logs` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `outlet_id` VARCHAR(36) NOT NULL,
    `fryer_id` VARCHAR(50) NOT NULL,
    `tpm_percentage` DECIMAL(4, 1) NOT NULL,
    `oil_temp_celsius` DECIMAL(4, 1) NOT NULL,
    `status` ENUM('SAFE', 'WARNING', 'CRITICAL') NOT NULL,
    `measured_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tpm_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. INTER-BRANCH TRANSFERS (RP 2.000.000 APPROVAL RULE)
CREATE TABLE IF NOT EXISTS `transfers` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `transfer_number` VARCHAR(50) NOT NULL UNIQUE,
    `from_outlet_id` VARCHAR(36) NOT NULL,
    `to_outlet_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `qty` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL,
    `total_valuation` DECIMAL(14, 2) NOT NULL,
    `requires_hq_approval` TINYINT(1) DEFAULT 0, -- 1 if >= Rp 2.000.000
    `status` ENUM('WAITING_HQ_APPROVAL', 'APPROVED', 'IN_TRANSIT', 'COMPLETED', 'REJECTED') NOT NULL DEFAULT 'IN_TRANSIT',
    `requested_by` VARCHAR(100) NOT NULL,
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL,
    `completed_at` TIMESTAMP NULL,
    CONSTRAINT `fk_tr_from` FOREIGN KEY (`from_outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_tr_to` FOREIGN KEY (`to_outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_tr_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. SYSTEM CONFIGS
CREATE TABLE IF NOT EXISTS `system_configs` (
    `config_key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `config_value` TEXT NOT NULL,
    `description` TEXT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. PURCHASE ORDERS (MODUL PO FORMAL PRD)
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `po_number` VARCHAR(50) NOT NULL UNIQUE,
    `outlet_id` VARCHAR(36) NOT NULL,
    `supplier_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `qty` DECIMAL(10, 2) NOT NULL,
    `unit` VARCHAR(20) NOT NULL,
    `unit_cost` DECIMAL(12, 2) NOT NULL,
    `total_cost` DECIMAL(14, 2) NOT NULL,
    `expected_date` DATE NOT NULL,
    `status` ENUM('DRAFT', 'SUBMITTED', 'APPROVED_HQ', 'ORDERED', 'RECEIVED', 'CANCELLED') NOT NULL DEFAULT 'SUBMITTED',
    `requested_by` VARCHAR(100) NOT NULL,
    `approved_by` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_po_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
    CONSTRAINT `fk_po_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. STOCK OPNAMES (MODUL STOCK OPNAME FORMAL PRD — TOLERANSI 3% - 5%)
CREATE TABLE IF NOT EXISTS `stock_opnames` (
    `id` VARCHAR(36) NOT NULL PRIMARY KEY,
    `opname_number` VARCHAR(50) NOT NULL UNIQUE,
    `outlet_id` VARCHAR(36) NOT NULL,
    `item_id` VARCHAR(36) NOT NULL,
    `system_stock` DECIMAL(10, 2) NOT NULL,
    `physical_stock` DECIMAL(10, 2) NOT NULL,
    `discrepancy_qty` DECIMAL(10, 2) NOT NULL,
    `discrepancy_pct` DECIMAL(6, 2) NOT NULL,
    `discrepancy_value` DECIMAL(14, 2) NOT NULL,
    `tolerance_status` ENUM('NORMAL', 'WARNING_3_5_PCT', 'AUDIT_ALERT_GT_5_PCT') NOT NULL DEFAULT 'NORMAL',
    `stock_adjusted` TINYINT(1) DEFAULT 0,
    `audited_by` VARCHAR(100) NOT NULL,
    `notes` TEXT NULL,
    `audited_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_opname_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
    CONSTRAINT `fk_opname_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

INSERT INTO `outlets` (`id`, `code`, `name`, `type`, `address`, `phone`, `manager_name`) VALUES
('out-01', 'TFC-HQ', 'Central Processing Plant & Hub', 'CENTRAL_PLANT', 'Kawasan Pusat Suplai & Cold Storage', '0451-482110', 'Sarah Wijaya, S.E.'),
('out-02', 'TFC-DYD', 'Takashi fried chicken DayoDara', 'STORE', 'Jalan Dayo Dara, Talise Valangguni', '0451-481230', 'Chef Danu Prasetyo'),
('out-03', 'TFC-ZBR', 'Takashi fried chicken (Zebra)', 'STORE', 'Jalan Zebra, Lolu Utara', '0451-483440', 'Rian Hidayat'),
('out-04', 'TFC-LPT', 'Takashi Fried Chicken (Lapatta)', 'STORE', 'Jalan Lapata, Dusun 4', '0451-485670', 'Siti Nurhaliza'),
('out-05', 'TFC-NGR', 'Takashi Fried Chicken (Ngurah Rai)', 'STORE', 'Jalan I Gusti Ngurah Rai, Boyaoge', '0451-487890', 'Ahmad Farhan')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `address`=VALUES(`address`);

-- Password bcrypt hash valid untuk plain text: "password" (diverifikasi via password_verify)
INSERT INTO `users` (`id`, `outlet_id`, `name`, `email`, `password`, `role`, `phone`) VALUES
('usr-01', 'out-01', 'Sarah Wijaya, S.E.', 'owner@takashi.id', '$2y$10$67JrtPeqUfsk/iJ7.M9WVOb1DzxIaKWmHGSx7//PXxonYOjTRiGLa', 'OWNER', '08119876543'),
('usr-02', 'out-01', 'Bima Sakti', 'hq@takashi.id', '$2y$10$67JrtPeqUfsk/iJ7.M9WVOb1DzxIaKWmHGSx7//PXxonYOjTRiGLa', 'HQ_SUPPLY_CHAIN', '08123456789'),
('usr-03', 'out-02', 'Chef Danu Prasetyo', 'dayodara@takashi.id', '$2y$10$67JrtPeqUfsk/iJ7.M9WVOb1DzxIaKWmHGSx7//PXxonYOjTRiGLa', 'STORE_MANAGER', '08139988776'),
('usr-04', 'out-02', 'Agus Koki', 'kitchen.dayodara@takashi.id', '$2y$10$67JrtPeqUfsk/iJ7.M9WVOb1DzxIaKWmHGSx7//PXxonYOjTRiGLa', 'KITCHEN_STAFF', '08151122334')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `password`=VALUES(`password`);

INSERT INTO `categories` (`id`, `code`, `name`, `storage_type`) VALUES
('cat-01', 'AYAM', 'Ayam & Daging Segar', 'CHILLED'),
('cat-02', 'BUMBU', 'Bumbu & Tepung', 'DRY'),
('cat-03', 'MINYAK', 'Minyak Goreng', 'ROOM_TEMP'),
('cat-04', 'SAUS', 'Saus & Topping', 'CHILLED'),
('cat-05', 'PACK', 'Packaging & Kemasan', 'DRY')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `items` (`id`, `category_id`, `sku`, `name`, `description`, `unit`, `abc_class`, `min_safety_stock`, `optimal_stock`, `standard_cost`, `target_shelf_life_days`, `max_dwell_hours`) VALUES
('item-01', 'cat-01', 'TFC-RAW-001', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'Daging karkas ayam broiler standar TFC potong 9 bagian', 'ekor', 'A', 60.00, 250.00, 38500.00, 3, 48),
('item-02', 'cat-01', 'TFC-RAW-002', 'Paha Fillet Boneless (Karaage Cut)', 'Paha ayam tanpa tulang dipotong dadu 30-35gr marinate ready', 'kg', 'A', 40.00, 180.00, 52000.00, 4, 48),
('item-03', 'cat-01', 'TFC-RAW-003', 'Sayap Ayam (Wings)', 'Sayap ayam tengah dan drumette potongan seimbang', 'kg', 'B', 25.00, 100.00, 42000.00, 4, 48),
('item-04', 'cat-02', 'TFC-DRY-001', 'Tepung Bumbu Crispy Signature 25kg', 'Racikan tepung tapioka dan rempah khas Jepang TFC', 'zak', 'A', 10.00, 45.00, 485000.00, 90, NULL),
('item-05', 'cat-02', 'TFC-DRY-002', 'Japanese Garlic Shoyu Marinade 5L', 'Marinade konsentrat kecap shoyu dan bawang putih khas Nagoya', 'jerigen', 'B', 6.00, 24.00, 215000.00, 30, NULL),
('item-06', 'cat-03', 'TFC-OIL-001', 'Minyak Padat Deep Frying Shortening 15kg', 'Lemak nabati padat titik asap tinggi 230C', 'karton', 'A', 15.00, 60.00, 340000.00, 180, NULL),
('item-07', 'cat-04', 'TFC-SAU-001', 'Saus Mentai Bakar TFC 5L', 'Saus mentai mayones tobiko racikan khusus siap torch', 'jerigen', 'B', 8.00, 30.00, 275000.00, 21, NULL),
('item-08', 'cat-05', 'TFC-PCK-001', 'Combo Box Packaging Eco-Friendly TFC', 'Kotak kemasan tahan minyak dengan sirkulasi uap renyah', 'pack', 'C', 50.00, 300.00, 45000.00, 365, NULL)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `suppliers` (`id`, `code`, `name`, `category`, `lead_time_days`, `contact_person`, `phone`, `email`, `rating`) VALUES
('sup-01', 'SUP-JAPFA', 'PT Japfa Comfeed Indonesia Tbk', 'POULTRY', 1, 'Hendra Gunawan', '021-8839001', 'order@japfa.tfc.id', 4.95),
('sup-02', 'SUP-CP', 'PT Charoen Pokphand Indonesia', 'POULTRY', 1, 'Bambang Irawan', '021-6919999', 'supply@cp.co.id', 4.90),
('sup-03', 'SUP-WILMAR', 'PT Wilmar Nabati Indonesia', 'OIL', 2, 'Dewi Sartika', '021-2527777', 'b2b@wilmar.co.id', 4.85),
('sup-04', 'SUP-BOGASARI', 'PT Indofood Sukses Makmur (Bogasari)', 'DRY_GOODS', 3, 'Agus Prasetyo', '021-4301048', 'flour@bogasari.com', 4.88),
('sup-05', 'SUP-PACKINDO', 'PT Packaging Nusantara Perkasa', 'PACKAGING', 4, 'Kevin Wijaya', '021-5595900', 'sales@packindo.com', 4.75)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `system_configs` (`config_key`, `config_value`, `description`) VALUES
('TRANSFER_HQ_THRESHOLD', '2000000', 'Threshold transfer wajib approval HQ (Rp 2 Juta)'),
('TPM_MAX_THRESHOLD', '24.0', 'Maksimal TPM sensor minyak goreng (24.0%)'),
('HOLDING_CABINET_MAX_MINUTES', '90', 'Maksimal waktu holding cabinet ayam (90 menit)'),
('TARGET_DSI_CHICKEN', '2.5', 'Target DSI ayam segar (< 2.5 hari)')
ON DUPLICATE KEY UPDATE `config_value`=VALUES(`config_value`);
