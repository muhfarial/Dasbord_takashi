<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Takashi Fried Chicken (TFC) - Persistent CRUD Repository
 * Uses MySQL 8.0 PDO when active, or auto-bootstrapped SQLite PDO (database/takashi_runtime.sqlite)
 * so all writes (Receiving, Kitchen Logs, Transfers, Purchase Orders, Stock Opnames, Configs)
 * persist permanently across browser refreshes (F5).
 */
class StorageRepository
{
    private PDO $pdo;
    private bool $isMysql;

    public function __construct()
    {
        $mysql = Database::mysql();
        if ($mysql !== null) {
            $this->pdo = $mysql;
            $this->isMysql = true;
            $this->ensureMysqlExtendedTables();
        } else {
            $this->pdo = Database::sqlite();
            $this->isMysql = false;
            $this->ensureSqliteSchemaAndSeed();
        }
    }

    private function ensureMysqlExtendedTables(): void
    {
        try {
            $this->pdo->exec("
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
                    `status` VARCHAR(30) NOT NULL DEFAULT 'SUBMITTED',
                    `requested_by` VARCHAR(100) NOT NULL,
                    `approved_by` VARCHAR(100) NULL,
                    `notes` TEXT NULL,
                    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->pdo->exec("
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
                    `tolerance_status` VARCHAR(30) NOT NULL DEFAULT 'NORMAL',
                    `stock_adjusted` TINYINT(1) DEFAULT 0,
                    `audited_by` VARCHAR(100) NOT NULL,
                    `notes` TEXT NULL,
                    `audited_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Non-fatal if tables already exist with stricter constraints
        }
    }

    private function ensureSqliteSchemaAndSeed(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS outlets (
                id TEXT PRIMARY KEY, code TEXT, name TEXT, type TEXT, address TEXT, hours TEXT, city TEXT, manager_name TEXT
            );
            CREATE TABLE IF NOT EXISTS suppliers (
                id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, lead_time_days INTEGER, rating REAL
            );
            CREATE TABLE IF NOT EXISTS items (
                id TEXT PRIMARY KEY, sku TEXT, name TEXT, category TEXT, unit TEXT, abc_class TEXT,
                current_stock REAL, safety_stock REAL, optimal_stock REAL, standard_cost REAL, max_dwell INTEGER
            );
            CREATE TABLE IF NOT EXISTS inventory_batches (
                id TEXT PRIMARY KEY, lot_number TEXT, item_id TEXT, item_name TEXT, sku TEXT, outlet_name TEXT,
                prod_date TEXT, exp_date TEXT, initial_qty REAL, remaining_qty REAL, unit TEXT,
                arrival_temp REAL, dwell_hours INTEGER, status TEXT
            );
            CREATE TABLE IF NOT EXISTS receiving_logs (
                id TEXT PRIMARY KEY, receiving_number TEXT, supplier_name TEXT, item_id TEXT, item_name TEXT,
                lot_number TEXT, qty REAL, unit TEXT, prod_date TEXT, exp_date TEXT,
                arrival_temp REAL, cold_chain_status TEXT, received_by TEXT, created_at TEXT
            );
            CREATE TABLE IF NOT EXISTS kitchen_logs (
                id TEXT PRIMARY KEY, time_str TEXT, item_id TEXT, item_name TEXT, log_type TEXT,
                qty REAL, unit TEXT, note TEXT, user_name TEXT, created_at TEXT
            );
            CREATE TABLE IF NOT EXISTS holding_cabinets (
                id TEXT PRIMARY KEY, code TEXT, item_name TEXT, qty INTEGER, station TEXT,
                elapsed_minutes INTEGER, max_minutes INTEGER, status TEXT
            );
            CREATE TABLE IF NOT EXISTS transfers (
                id TEXT PRIMARY KEY, from_outlet TEXT, to_outlet TEXT, item_id TEXT, item_name TEXT,
                qty REAL, unit TEXT, total_valuation REAL, requires_hq_approval INTEGER,
                status TEXT, requested_by TEXT, rejection_reason TEXT, created_at TEXT
            );
            CREATE TABLE IF NOT EXISTS purchase_orders (
                id TEXT PRIMARY KEY, po_number TEXT, outlet_id TEXT, outlet_name TEXT,
                supplier_id TEXT, supplier_name TEXT, item_id TEXT, item_name TEXT,
                qty REAL, unit TEXT, unit_cost REAL, total_cost REAL, expected_date TEXT,
                status TEXT, requested_by TEXT, approved_by TEXT, notes TEXT, created_at TEXT
            );
            CREATE TABLE IF NOT EXISTS stock_opnames (
                id TEXT PRIMARY KEY, opname_number TEXT, outlet_id TEXT, outlet_name TEXT,
                item_id TEXT, item_name TEXT, unit TEXT, system_stock REAL, physical_stock REAL,
                discrepancy_qty REAL, discrepancy_pct REAL, discrepancy_value REAL,
                tolerance_status TEXT, stock_adjusted INTEGER, audited_by TEXT, notes TEXT, audited_at TEXT
            );
            CREATE TABLE IF NOT EXISTS system_configs (
                config_key TEXT PRIMARY KEY, config_value TEXT, description TEXT
            );
        ");

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        if ($count > 0) {
            return;
        }

        // Seed Outlets
        $outlets = [
            ['out-01', 'TFC-HQ', 'Central Plant & Cold Storage Hub', 'CENTRAL_PLANT', 'Kawasan Pusat Suplai & Cold Storage', '24 Jam', 'Palu', 'Sarah Wijaya, S.E.'],
            ['out-02', 'TFC-DYD', 'Takashi fried chicken DayoDara', 'FAST_FOOD', 'Jalan Dayo Dara, Talise Valangguni', 'Open · Closes 00:00', 'Palu', 'Chef Danu Prasetyo'],
            ['out-03', 'TFC-ZBR', 'Takashi fried chicken (Zebra)', 'CHICKEN', 'Jalan Zebra, Lolu Utara', 'Open', 'Palu', 'Rian Hidayat'],
            ['out-04', 'TFC-LPT', 'Takashi Fried Chicken (Lapatta)', 'RESTAURANT', 'Jalan Lapata, Dusun 4', 'Open', 'Palu', 'Siti Nurhaliza'],
            ['out-05', 'TFC-NGR', 'Takashi Fried Chicken (Ngurah Rai)', 'RESTAURANT', 'Jalan I Gusti Ngurah Rai, Boyaoge', 'Open', 'Palu', 'Ahmad Farhan'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO outlets VALUES (?,?,?,?,?,?,?,?)");
        foreach ($outlets as $o) {
            $stmt->execute($o);
        }

        // Seed Suppliers
        $suppliers = [
            ['sup-01', 'SUP-JAPFA', 'PT Japfa Comfeed Indonesia Tbk', 'POULTRY', 1, 4.95],
            ['sup-02', 'SUP-CP', 'PT Charoen Pokphand Indonesia', 'POULTRY', 1, 4.90],
            ['sup-03', 'SUP-WILMAR', 'PT Wilmar Nabati Indonesia', 'OIL', 2, 4.85],
            ['sup-04', 'SUP-BOGASARI', 'PT Indofood Sukses Makmur (Bogasari)', 'DRY_GOODS', 3, 4.88],
            ['sup-05', 'SUP-PACKINDO', 'PT Packaging Nusantara Perkasa', 'PACKAGING', 4, 4.75],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO suppliers VALUES (?,?,?,?,?,?)");
        foreach ($suppliers as $s) {
            $stmt->execute($s);
        }

        // Seed Items
        $items = [
            ['item-01', 'TFC-RAW-001', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'Ayam & Daging Segar', 'ekor', 'A', 78, 60, 250, 38500, 48],
            ['item-02', 'TFC-RAW-002', 'Paha Fillet Boneless (Karaage Cut)', 'Ayam & Daging Segar', 'kg', 'A', 32, 40, 180, 52000, 48],
            ['item-03', 'TFC-RAW-003', 'Sayap Ayam (Wings)', 'Ayam & Daging Segar', 'kg', 'B', 54, 25, 100, 42000, 48],
            ['item-04', 'TFC-DRY-001', 'Tepung Bumbu Crispy Signature 25kg', 'Bumbu & Tepung', 'zak', 'A', 18, 10, 45, 485000, null],
            ['item-05', 'TFC-DRY-002', 'Japanese Garlic Shoyu Marinade 5L', 'Bumbu & Tepung', 'jerigen', 'B', 9, 6, 24, 215000, null],
            ['item-06', 'TFC-OIL-001', 'Minyak Padat Deep Frying Shortening 15kg', 'Minyak Goreng', 'karton', 'A', 12, 15, 60, 340000, null],
            ['item-07', 'TFC-SAU-001', 'Saus Mentai Bakar TFC 5L', 'Saus & Topping', 'jerigen', 'B', 14, 8, 30, 275000, null],
            ['item-08', 'TFC-PCK-001', 'Combo Box Packaging Eco-Friendly TFC', 'Packaging & Kemasan', 'pack', 'C', 160, 50, 300, 45000, null],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO items VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($items as $it) {
            $stmt->execute($it);
        }

        // Seed FEFO Batches
        $batches = [
            ['btc-001', 'LOT-20261114-042', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'TFC-RAW-001', 'Takashi DayoDara', '2026-11-14', '2026-11-19', 50, 12, 'ekor', 2.4, 38, 'EXPIRING_SOON_24H'],
            ['btc-002', 'LOT-20261115-055', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'TFC-RAW-001', 'Takashi DayoDara', '2026-11-15', '2026-11-20', 40, 26, 'ekor', 2.1, 22, 'ACTIVE_FRESH'],
            ['btc-003', 'LOT-20261116-089', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'TFC-RAW-001', 'Takashi DayoDara', '2026-11-16', '2026-11-21', 40, 40, 'ekor', 2.2, 8, 'ACTIVE_FRESH'],
            ['btc-004', 'LOT-20261113-030', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 'TFC-RAW-002', 'Takashi DayoDara', '2026-11-13', '2026-11-19', 30, 8, 'kg', 2.8, 44, 'EXPIRING_SOON_24H'],
            ['btc-005', 'LOT-20261115-061', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 'TFC-RAW-002', 'Takashi DayoDara', '2026-11-15', '2026-11-21', 30, 24, 'kg', 2.3, 20, 'ACTIVE_FRESH'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO inventory_batches VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($batches as $b) {
            $stmt->execute($b);
        }

        // Seed Holding Cabinets
        $cabinets = [
            ['hc-01', 'BATCH-HC-301', 'Ayam Crispy Original Potong 9', 18, 'Fryer-01 (Nagoya)', 38, 90, 'ACTIVE'],
            ['hc-02', 'BATCH-HC-302', 'Spicy Fire Karaage', 24, 'Fryer-02 (Tokyo)', 78, 90, 'ACTIVE'],
            ['hc-03', 'BATCH-HC-303', 'Takashi Wings Shoyu', 12, 'Fryer-03 (Osaka)', 14, 90, 'ACTIVE'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO holding_cabinets VALUES (?,?,?,?,?,?,?,?)");
        foreach ($cabinets as $c) {
            $stmt->execute($c);
        }

        // Seed Kitchen Logs
        $klogs = [
            ['k-1', '14:15', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 'SALES', 5.0, 'kg', 'Order Bento Karaage Siang', 'Agus Koki', '2026-11-16 14:15:00'],
            ['k-2', '13:50', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'SPOILAGE', 1.0, 'ekor', 'BURNT_OVERCOOK - Minyak terlalu panas', 'Agus Koki', '2026-11-16 13:50:00'],
            ['k-3', '13:00', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'SALES', 15.0, 'ekor', 'Fryer Batch Siang #3', 'Chef Danu Prasetyo', '2026-11-16 13:00:00'],
            ['k-4', '11:45', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'SPOILAGE', 2.0, 'ekor', 'HOLDING_EXPIRED_90M - Melebihi batas cabinet', 'Agus Koki', '2026-11-16 11:45:00'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO kitchen_logs VALUES (?,?,?,?,?,?,?,?,?,?)");
        foreach ($klogs as $k) {
            $stmt->execute($k);
        }

        // Seed Transfers
        $transfers = [
            ['TR-2026-089', 'Central Plant', 'Takashi DayoDara', 'item-06', 'Minyak Padat Deep Frying Shortening 15kg', 10, 'karton', 3400000, 1, 'WAITING_HQ_APPROVAL', 'Chef Danu Prasetyo', null, '16 Nov 2026, 13:40'],
            ['TR-2026-088', 'Central Plant', 'Takashi Zebra', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 25, 'kg', 1300000, 0, 'IN_TRANSIT', 'Chef Danu Prasetyo', null, '16 Nov 2026, 11:15'],
            ['TR-2026-085', 'Takashi DayoDara', 'Takashi Lapatta', 'item-04', 'Tepung Bumbu Crispy Signature 25kg', 2, 'zak', 970000, 0, 'COMPLETED', 'Rian Hidayat', null, '15 Nov 2026, 16:20'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO transfers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($transfers as $t) {
            $stmt->execute($t);
        }

        // Seed Purchase Orders
        $pos = [
            ['po-001', 'PO-20261118-001', 'out-02', 'Takashi fried chicken DayoDara', 'sup-03', 'PT Wilmar Nabati Indonesia', 'item-06', 'Minyak Padat Deep Frying Shortening 15kg', 25, 'karton', 340000, 8500000, '2026-11-20', 'SUBMITTED', 'Chef Danu Prasetyo', null, 'Restock mendesak Minyak Padat (stok 12/15 karton < safety stock)', '18 Nov 2026, 09:15'],
            ['po-002', 'PO-20261117-004', 'out-01', 'Central Plant & Cold Storage Hub', 'sup-01', 'PT Japfa Comfeed Indonesia Tbk', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 150, 'ekor', 38500, 5775000, '2026-11-19', 'APPROVED_HQ', 'Bima Sakti', 'Sarah Wijaya, S.E.', 'Pesanan EOQ optimal persiapan weekend surge Jumat-Minggu', '17 Nov 2026, 14:30'],
            ['po-003', 'PO-20261116-002', 'out-02', 'Takashi fried chicken DayoDara', 'sup-02', 'PT Charoen Pokphand Indonesia', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 45, 'kg', 52000, 2340000, '2026-11-18', 'ORDERED', 'Chef Danu Prasetyo', 'Sarah Wijaya, S.E.', 'Restock Paha Fillet Karaage Cut (di bawah batas 40 kg)', '16 Nov 2026, 11:00'],
            ['po-004', 'PO-20261114-009', 'out-01', 'Central Plant & Cold Storage Hub', 'sup-04', 'PT Indofood Sukses Makmur (Bogasari)', 'item-04', 'Tepung Bumbu Crispy Signature 25kg', 20, 'zak', 485000, 9700000, '2026-11-16', 'RECEIVED', 'Bima Sakti', 'Sarah Wijaya, S.E.', 'Tepung Bumbu Crispy Signature diterima lengkap di Hub', '14 Nov 2026, 08:45'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO purchase_orders VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($pos as $p) {
            $stmt->execute($p);
        }

        // Seed Stock Opnames
        $opnames = [
            ['opn-001', 'OPN-20261118-001', 'out-02', 'Takashi fried chicken DayoDara', 'item-01', 'Ayam Karkas Segar 0.9-1.0kg (Potong 9)', 'ekor', 80, 78, -2, -2.50, -77000, 'NORMAL', 1, 'Chef Danu Prasetyo', 'Susut natural thawing masih dalam batas toleransi <= 3%.', '18 Nov 2026, 07:30'],
            ['opn-002', 'OPN-20261117-002', 'out-02', 'Takashi fried chicken DayoDara', 'item-02', 'Paha Fillet Boneless (Karaage Cut)', 'kg', 33.5, 32, -1.5, -4.48, -78000, 'WARNING_3_5_PCT', 1, 'Chef Danu Prasetyo', 'Selisih 4.48% masuk zona warning 3%-5% (over-portioning karaage).', '17 Nov 2026, 22:15'],
            ['opn-003', 'OPN-20261116-003', 'out-03', 'Takashi fried chicken (Zebra)', 'item-06', 'Minyak Padat Deep Frying Shortening 15kg', 'karton', 11, 10, -1, -9.09, -340000, 'AUDIT_ALERT_GT_5_PCT', 1, 'Rian Hidayat', 'AUDIT ALERT > 5%: 1 karton minyak tidak tercatat saat penggantian fryer kritis.', '16 Nov 2026, 21:45'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO stock_opnames VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($opnames as $op) {
            $stmt->execute($op);
        }

        // Seed Configs
        $configs = [
            ['TRANSFER_HQ_THRESHOLD', '2000000', 'Threshold transfer wajib approval HQ (Rp 2 Juta)'],
            ['TPM_MAX_THRESHOLD', '24.0', 'Maksimal TPM sensor minyak goreng (24.0%)'],
            ['HOLDING_CABINET_MAX_MINUTES', '90', 'Maksimal waktu holding cabinet ayam (90 menit)'],
            ['TARGET_DSI_CHICKEN', '2.5', 'Target DSI ayam segar (< 2.5 hari)'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO system_configs VALUES (?,?,?)");
        foreach ($configs as $cfg) {
            $stmt->execute($cfg);
        }
    }

    public function getConfigs(): array
    {
        $rows = $this->pdo->query("SELECT config_key, config_value FROM system_configs")->fetchAll();
        $map = [
            'TRANSFER_HQ_THRESHOLD' => 2000000,
            'TPM_MAX_THRESHOLD' => 24.0,
            'HOLDING_CABINET_MAX_MINUTES' => 90,
            'TARGET_DSI_CHICKEN' => 2.5,
        ];
        foreach ($rows as $r) {
            $k = $r['config_key'];
            $v = $r['config_value'];
            $map[$k] = is_numeric($v) ? (str_contains((string)$v, '.') ? (float)$v : (int)$v) : $v;
        }
        return $map;
    }

    public function updateConfigs(array $payload): array
    {
        $allowed = ['TRANSFER_HQ_THRESHOLD', 'TPM_MAX_THRESHOLD', 'HOLDING_CABINET_MAX_MINUTES', 'TARGET_DSI_CHICKEN'];
        foreach ($allowed as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                if ($this->isMysql) {
                    $stmt = $this->pdo->prepare("INSERT INTO system_configs (config_key, config_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                } else {
                    $stmt = $this->pdo->prepare("INSERT INTO system_configs (config_key, config_value) VALUES (:k, :v) ON CONFLICT(config_key) DO UPDATE SET config_value = excluded.config_value");
                }
                $stmt->execute([':k' => $key, ':v' => (string)$payload[$key]]);
            }
        }
        return ['status' => 'success', 'configs' => $this->getConfigs(), 'message' => 'Pengaturan ambang batas berhasil disimpan ke database.'];
    }

    public function getItems(): array
    {
        if ($this->isMysql) {
            $sql = "
                SELECT i.id, i.sku, i.name, c.name AS category, i.unit, i.abc_class,
                       COALESCE(os.current_stock, 0) AS current_stock,
                       i.min_safety_stock AS safety_stock, i.optimal_stock, i.standard_cost, i.max_dwell_hours AS max_dwell
                FROM items i
                LEFT JOIN categories c ON c.id = i.category_id
                LEFT JOIN outlet_stocks os ON os.item_id = i.id AND os.outlet_id = 'out-02'
                ORDER BY i.id ASC
            ";
            $rows = $this->pdo->query($sql)->fetchAll();
        } else {
            $rows = $this->pdo->query("SELECT * FROM items ORDER BY id ASC")->fetchAll();
        }

        return array_map(fn($r) => [
            'id' => $r['id'],
            'sku' => $r['sku'],
            'name' => $r['name'],
            'category' => $r['category'] ?? 'Umum',
            'unit' => $r['unit'],
            'abcClass' => $r['abc_class'],
            'currentStock' => (float)$r['current_stock'],
            'safetyStock' => (float)$r['safety_stock'],
            'optimalStock' => (float)$r['optimal_stock'],
            'standardCost' => (float)$r['standard_cost'],
            'maxDwell' => $r['max_dwell'] !== null ? (int)$r['max_dwell'] : null,
        ], $rows);
    }

    private function adjustItemStock(string $itemId, float $delta, ?float $absoluteSet = null): float
    {
        if ($this->isMysql) {
            $cur = $this->pdo->prepare("SELECT current_stock FROM outlet_stocks WHERE outlet_id = 'out-02' AND item_id = :item LIMIT 1");
            $cur->execute([':item' => $itemId]);
            $row = $cur->fetch();
            $oldStock = $row ? (float)$row['current_stock'] : 0.0;
            $newStock = $absoluteSet !== null ? max(0.0, $absoluteSet) : max(0.0, $oldStock + $delta);

            if ($row) {
                $upd = $this->pdo->prepare("UPDATE outlet_stocks SET current_stock = :stk, last_opname_at = NOW() WHERE outlet_id = 'out-02' AND item_id = :item");
                $upd->execute([':stk' => $newStock, ':item' => $itemId]);
            } else {
                $ins = $this->pdo->prepare("INSERT INTO outlet_stocks (id, outlet_id, item_id, current_stock) VALUES (:id, 'out-02', :item, :stk)");
                $ins->execute([':id' => 'stk-' . uniqid(), ':item' => $itemId, ':stk' => $newStock]);
            }
            return $newStock;
        }

        $cur = $this->pdo->prepare("SELECT current_stock FROM items WHERE id = :id");
        $cur->execute([':id' => $itemId]);
        $oldStock = (float)$cur->fetchColumn();
        $newStock = $absoluteSet !== null ? max(0.0, $absoluteSet) : max(0.0, $oldStock + $delta);

        $upd = $this->pdo->prepare("UPDATE items SET current_stock = :stk WHERE id = :id");
        $upd->execute([':stk' => $newStock, ':id' => $itemId]);
        return $newStock;
    }

    public function getBootstrapState(): array
    {
        $items = $this->getItems();
        $configs = $this->getConfigs();

        // Suppliers
        $suppliers = $this->pdo->query("SELECT id, code, name, category, lead_time_days, rating FROM suppliers ORDER BY id ASC")->fetchAll();

        // FEFO Batches
        if ($this->isMysql) {
            $batches = $this->pdo->query("
                SELECT b.id, b.lot_number, b.item_id, i.name AS item_name, i.sku, o.name AS outlet_name,
                       b.production_date AS prod_date, b.expiry_date AS exp_date, b.initial_qty, b.remaining_qty,
                       i.unit, b.arrival_temp_celsius AS arrival_temp, b.dwell_hours, b.status
                FROM inventory_batches b
                JOIN items i ON i.id = b.item_id
                JOIN outlets o ON o.id = b.outlet_id
                ORDER BY b.expiry_date ASC
            ")->fetchAll();
        } else {
            $batches = $this->pdo->query("SELECT * FROM inventory_batches ORDER BY exp_date ASC")->fetchAll();
        }

        // Holding Cabinets
        if ($this->isMysql) {
            $cabinets = $this->pdo->query("SELECT id, batch_code AS code, item_name, qty_pieces AS qty, station, max_minutes, status FROM holding_cabinets WHERE status IN ('FRESH','WARNING_75M','EXPIRED_90M') ORDER BY id ASC")->fetchAll();
            $cabinets = array_map(fn($c) => [
                'id' => $c['id'], 'code' => $c['code'], 'item' => $c['item_name'],
                'qty' => (int)$c['qty'], 'station' => $c['station'],
                'elapsedMinutes' => $c['status'] === 'WARNING_75M' ? 78 : ($c['status'] === 'EXPIRED_90M' ? 92 : 28),
                'maxMinutes' => (int)$c['max_minutes'],
            ], $cabinets);
        } else {
            $cabinets = $this->pdo->query("SELECT * FROM holding_cabinets WHERE status = 'ACTIVE' ORDER BY rowid DESC")->fetchAll();
            $cabinets = array_map(fn($c) => [
                'id' => $c['id'], 'code' => $c['code'], 'item' => $c['item_name'],
                'qty' => (int)$c['qty'], 'station' => $c['station'],
                'elapsedMinutes' => (int)$c['elapsed_minutes'], 'maxMinutes' => (int)$c['max_minutes'],
            ], $cabinets);
        }

        // Kitchen Logs
        if ($this->isMysql) {
            $klogs = $this->pdo->query("
                SELECT k.id, DATE_FORMAT(k.logged_at, '%H:%i') AS time_str, i.name AS item_name,
                       k.log_type, k.qty, k.unit, CONCAT(k.spoilage_reason, ' - ', COALESCE(k.note, '')) AS note, u.name AS user_name
                FROM kitchen_logs k
                JOIN items i ON i.id = k.item_id
                JOIN users u ON u.id = k.user_id
                ORDER BY k.logged_at DESC LIMIT 50
            ")->fetchAll();
        } else {
            $klogs = $this->pdo->query("SELECT * FROM kitchen_logs ORDER BY rowid DESC LIMIT 50")->fetchAll();
        }
        $kitchenLogs = array_map(fn($k) => [
            'id' => $k['id'], 'time' => $k['time_str'], 'item' => $k['item_name'],
            'type' => $k['log_type'], 'qty' => (float)$k['qty'], 'unit' => $k['unit'],
            'note' => $k['note'], 'user' => $k['user_name'],
        ], $klogs);

        // Transfers
        if ($this->isMysql) {
            $trs = $this->pdo->query("
                SELECT t.transfer_number AS id, o1.name AS from_outlet, o2.name AS to_outlet,
                       i.name AS item_name, t.qty, t.unit, t.total_valuation, t.requires_hq_approval,
                       t.status, t.requested_by, t.rejection_reason, DATE_FORMAT(t.created_at, '%d %b %Y, %H:%i') AS created_at
                FROM transfers t
                JOIN outlets o1 ON o1.id = t.from_outlet_id
                JOIN outlets o2 ON o2.id = t.to_outlet_id
                JOIN items i ON i.id = t.item_id
                ORDER BY t.created_at DESC
            ")->fetchAll();
        } else {
            $trs = $this->pdo->query("SELECT * FROM transfers ORDER BY rowid DESC")->fetchAll();
        }
        $transfers = array_map(fn($t) => [
            'id' => $t['id'], 'fromOutlet' => $t['from_outlet'], 'toOutlet' => $t['to_outlet'],
            'item' => $t['item_name'], 'qty' => (float)$t['qty'], 'unit' => $t['unit'],
            'totalValuation' => (float)$t['total_valuation'],
            'requiresHqApproval' => (bool)$t['requires_hq_approval'],
            'status' => $t['status'], 'requestedBy' => $t['requested_by'],
            'rejectionReason' => $t['rejection_reason'] ?? null,
            'createdAt' => $t['created_at'],
        ], $trs);

        // Purchase Orders
        if ($this->isMysql) {
            $pos = $this->pdo->query("
                SELECT p.id, p.po_number, p.outlet_id, o.name AS outlet_name, p.supplier_id, s.name AS supplier_name,
                       p.item_id, i.name AS item_name, p.qty, p.unit, p.unit_cost, p.total_cost, p.expected_date,
                       p.status, p.requested_by, p.approved_by, p.notes, DATE_FORMAT(p.created_at, '%d %b %Y, %H:%i') AS created_at
                FROM purchase_orders p
                LEFT JOIN outlets o ON o.id = p.outlet_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN items i ON i.id = p.item_id
                ORDER BY p.created_at DESC
            ")->fetchAll();
        } else {
            $pos = $this->pdo->query("SELECT * FROM purchase_orders ORDER BY rowid DESC")->fetchAll();
        }
        $purchaseOrders = array_map(fn($p) => [
            'id' => $p['id'], 'poNumber' => $p['po_number'], 'outletId' => $p['outlet_id'], 'outletName' => $p['outlet_name'] ?? $p['outlet_id'],
            'supplierId' => $p['supplier_id'], 'supplierName' => $p['supplier_name'] ?? $p['supplier_id'],
            'itemId' => $p['item_id'], 'itemName' => $p['item_name'] ?? $p['item_id'],
            'qty' => (float)$p['qty'], 'unit' => $p['unit'], 'unitCost' => (float)$p['unit_cost'], 'totalCost' => (float)$p['total_cost'],
            'expectedDate' => $p['expected_date'], 'status' => $p['status'],
            'requestedBy' => $p['requested_by'], 'approvedBy' => $p['approved_by'], 'notes' => $p['notes'],
            'createdAt' => $p['created_at'],
        ], $pos);

        // Stock Opnames
        if ($this->isMysql) {
            $opns = $this->pdo->query("
                SELECT so.id, so.opname_number, so.outlet_id, o.name AS outlet_name, so.item_id, i.name AS item_name,
                       i.unit, so.system_stock, so.physical_stock, so.discrepancy_qty, so.discrepancy_pct,
                       so.discrepancy_value, so.tolerance_status, so.stock_adjusted, so.audited_by, so.notes,
                       DATE_FORMAT(so.audited_at, '%d %b %Y, %H:%i') AS audited_at
                FROM stock_opnames so
                LEFT JOIN outlets o ON o.id = so.outlet_id
                LEFT JOIN items i ON i.id = so.item_id
                ORDER BY so.audited_at DESC
            ")->fetchAll();
        } else {
            $opns = $this->pdo->query("SELECT * FROM stock_opnames ORDER BY rowid DESC")->fetchAll();
        }
        $stockOpnames = array_map(fn($op) => [
            'id' => $op['id'], 'opnameNumber' => $op['opname_number'], 'outletId' => $op['outlet_id'], 'outletName' => $op['outlet_name'] ?? $op['outlet_id'],
            'itemId' => $op['item_id'], 'itemName' => $op['item_name'] ?? $op['item_id'], 'unit' => $op['unit'] ?? 'unit',
            'systemStock' => (float)$op['system_stock'], 'physicalStock' => (float)$op['physical_stock'],
            'discrepancyQty' => (float)$op['discrepancy_qty'], 'discrepancyPct' => (float)$op['discrepancy_pct'],
            'discrepancyValue' => (float)$op['discrepancy_value'], 'toleranceStatus' => $op['tolerance_status'],
            'stockAdjusted' => (bool)$op['stock_adjusted'], 'auditedBy' => $op['audited_by'], 'notes' => $op['notes'],
            'auditedAt' => $op['audited_at'],
        ], $opns);

        return [
            'status' => 'success',
            'persistenceMode' => Database::modeLabel(),
            'items' => $items,
            'suppliers' => $suppliers,
            'fefoBatches' => $batches,
            'holdingCabinets' => $cabinets,
            'kitchenLogs' => $kitchenLogs,
            'transfers' => $transfers,
            'purchaseOrders' => $purchaseOrders,
            'stockOpnames' => $stockOpnames,
            'configs' => $configs,
        ];
    }

    public function recordReceiving(array $payload): array
    {
        $itemId = (string)($payload['itemId'] ?? '');
        $qty = (float)($payload['qty'] ?? 0);
        $temp = (float)($payload['tempArrival'] ?? 2.0);
        $lot = trim((string)($payload['lot'] ?? 'LOT-' . date('Ymd-His')));
        $supplier = trim((string)($payload['supplier'] ?? 'PT Japfa Comfeed Indonesia Tbk'));
        $prodDate = (string)($payload['prodDate'] ?? date('Y-m-d'));
        $expDate = (string)($payload['expDate'] ?? date('Y-m-d', strtotime('+3 days')));
        $receivedBy = trim((string)($payload['receivedBy'] ?? 'Chef Danu Prasetyo'));

        if ($itemId === '' || $qty <= 0) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Item dan kuantitas penerimaan harus valid (> 0).'];
        }

        $items = $this->getItems();
        $target = null;
        foreach ($items as $it) {
            if ($it['id'] === $itemId) {
                $target = $it;
                break;
            }
        }
        if (!$target) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Item SKU tidak ditemukan.'];
        }

        $isPoultry = in_array($itemId, ['item-01', 'item-02', 'item-03'], true);
        if ($isPoultry && $temp > 4.0) {
            // Persist rejected cold-chain log for audit veracity
            if (!$this->isMysql) {
                $stmt = $this->pdo->prepare("INSERT INTO receiving_logs VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    'rcv-' . uniqid(), 'RCV-' . date('Ymd-His'), $supplier, $itemId, $target['name'],
                    $lot, $qty, $target['unit'], $prodDate, $expDate, $temp, 'REJECTED', $receivedBy, date('Y-m-d H:i:s'),
                ]);
            }
            http_response_code(422);
            return [
                'status' => 'rejected',
                'coldChainStatus' => 'REJECTED',
                'message' => "REJECT COLD-CHAIN GATE: Suhu ayam {$temp}°C > 4.0°C! Batch {$lot} ditolak & dicatat ke log audit demi food safety.",
            ];
        }

        $newStock = $this->adjustItemStock($itemId, $qty);
        $batchId = 'btc-' . uniqid();

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_batches (id, outlet_id, item_id, lot_number, production_date, expiry_date, initial_qty, remaining_qty, unit_cost, arrival_temp_celsius, dwell_hours, status)
                VALUES (:id, 'out-02', :item, :lot, :pd, :ed, :qty, :qty2, :cost, :temp, 1, 'ACTIVE')
            ");
            $stmt->execute([
                ':id' => $batchId, ':item' => $itemId, ':lot' => $lot, ':pd' => $prodDate, ':ed' => $expDate,
                ':qty' => $qty, ':qty2' => $qty, ':cost' => $target['standardCost'], ':temp' => $temp,
            ]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO receiving_logs VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                'rcv-' . uniqid(), 'RCV-' . date('Ymd-His'), $supplier, $itemId, $target['name'],
                $lot, $qty, $target['unit'], $prodDate, $expDate, $temp, 'PASS', $receivedBy, date('Y-m-d H:i:s'),
            ]);
            $bStmt = $this->pdo->prepare("INSERT INTO inventory_batches VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $bStmt->execute([
                $batchId, $lot, $itemId, $target['name'], $target['sku'], 'Takashi DayoDara',
                $prodDate, $expDate, $qty, $qty, $target['unit'], $temp, 1, 'ACTIVE_FRESH',
            ]);
        }

        return [
            'status' => 'success',
            'coldChainStatus' => 'PASS',
            'newStock' => $newStock,
            'message' => "Batch {$lot} (+{$qty} {$target['unit']}) lolos Cold-Chain Gate ({$temp}°C) & tersimpan permanen di database.",
            'state' => $this->getBootstrapState(),
        ];
    }

    public function recordKitchenLog(array $payload): array
    {
        $itemId = (string)($payload['itemId'] ?? '');
        $type = strtoupper((string)($payload['type'] ?? 'SALES'));
        $qty = (float)($payload['qty'] ?? 0);
        $reason = trim((string)($payload['reason'] ?? 'Penjualan Reguler'));
        $user = trim((string)($payload['user'] ?? 'Agus Koki'));

        if ($itemId === '' || $qty <= 0) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Item dan kuantitas dapur wajib diisi (> 0).'];
        }

        $items = $this->getItems();
        $target = null;
        foreach ($items as $it) {
            if ($it['id'] === $itemId) {
                $target = $it;
                break;
            }
        }
        if (!$target) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Item tidak ditemukan.'];
        }

        $newStock = $this->adjustItemStock($itemId, -$qty);
        $logId = 'klog-' . uniqid();
        $timeStr = date('H:i');

        if ($this->isMysql) {
            $reasonEnum = match (true) {
                str_contains($reason, 'Gosong') || str_contains($reason, 'BURNT') => 'BURNT_OVERCOOK',
                str_contains($reason, '90') || str_contains($reason, 'HOLDING') => 'HOLDING_EXPIRED_90M',
                str_contains($reason, 'Over') => 'OVER_PORTION',
                str_contains($reason, 'Thawing') => 'THAWING_LOSS',
                default => 'REGULAR_USE',
            };
            $stmt = $this->pdo->prepare("
                INSERT INTO kitchen_logs (id, outlet_id, user_id, item_id, log_type, qty, unit, spoilage_reason, note)
                VALUES (:id, 'out-02', 'usr-04', :item, :type, :qty, :unit, :reason, :note)
            ");
            $stmt->execute([
                ':id' => $logId, ':item' => $itemId, ':type' => $type === 'SPOILAGE' ? 'SPOILAGE' : 'SALES',
                ':qty' => $qty, ':unit' => $target['unit'], ':reason' => $reasonEnum, ':note' => $reason,
            ]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO kitchen_logs VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $logId, $timeStr, $itemId, $target['name'], $type === 'SPOILAGE' ? 'SPOILAGE' : 'SALES',
                $qty, $target['unit'], $reason, $user, date('Y-m-d H:i:s'),
            ]);
        }

        return [
            'status' => 'success',
            'newStock' => $newStock,
            'message' => "Log dapur tersimpan permanen: -{$qty} {$target['unit']} ({$type}).",
            'state' => $this->getBootstrapState(),
        ];
    }

    public function addHoldingCabinet(array $payload): array
    {
        $item = trim((string)($payload['item'] ?? 'Ayam Crispy Original Potong 9'));
        $qty = max(1, (int)($payload['qty'] ?? 18));
        $station = trim((string)($payload['station'] ?? 'Fryer-01 (Nagoya)'));
        $id = 'hc-' . uniqid();
        $code = 'BATCH-HC-' . random_int(400, 999);
        $configs = $this->getConfigs();
        $maxMin = (int)($configs['HOLDING_CABINET_MAX_MINUTES'] ?? 90);

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("INSERT INTO holding_cabinets (id, outlet_id, batch_code, item_name, qty_pieces, station, fried_at, max_minutes, status) VALUES (:id, 'out-02', :code, :item, :qty, :st, NOW(), :maxm, 'FRESH')");
            $stmt->execute([':id' => $id, ':code' => $code, ':item' => $item, ':qty' => $qty, ':st' => $station, ':maxm' => $maxMin]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO holding_cabinets VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$id, $code, $item, $qty, $station, 0, $maxMin, 'ACTIVE']);
        }

        return ['status' => 'success', 'message' => "Batch baru {$code} masuk Holding Cabinet ({$maxMin}m timer aktif).", 'state' => $this->getBootstrapState()];
    }

    public function discardHoldingCabinet(string $id, string $user = 'Agus Koki'): array
    {
        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("UPDATE holding_cabinets SET status = 'DISCARDED' WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } else {
            $rowStmt = $this->pdo->prepare("SELECT * FROM holding_cabinets WHERE id = :id");
            $rowStmt->execute([':id' => $id]);
            $cab = $rowStmt->fetch();

            $stmt = $this->pdo->prepare("UPDATE holding_cabinets SET status = 'DISCARDED' WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($cab) {
                $kStmt = $this->pdo->prepare("INSERT INTO kitchen_logs VALUES (?,?,?,?,?,?,?,?,?,?)");
                $kStmt->execute([
                    'klog-' . uniqid(), date('H:i'), 'item-01', $cab['item_name'], 'SPOILAGE',
                    1.0, 'batch', 'HOLDING_EXPIRED_90M - ' . $cab['code'] . ' dibuang dari ' . $cab['station'],
                    $user, date('Y-m-d H:i:s'),
                ]);
            }
        }

        return ['status' => 'success', 'message' => 'Batch dibuang & otomatis tercatat di log spoilage waste.', 'state' => $this->getBootstrapState()];
    }

    public function createTransfer(array $payload): array
    {
        $from = trim((string)($payload['from'] ?? 'Central Plant'));
        $to = trim((string)($payload['to'] ?? 'Takashi DayoDara'));
        $itemId = (string)($payload['itemId'] ?? '');
        $qty = (float)($payload['qty'] ?? 0);
        $requestedBy = trim((string)($payload['requestedBy'] ?? 'Staff'));

        if ($itemId === '' || $qty <= 0) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Item dan kuantitas transfer wajib valid (> 0).'];
        }

        $items = $this->getItems();
        $target = null;
        foreach ($items as $it) {
            if ($it['id'] === $itemId) {
                $target = $it;
                break;
            }
        }
        if (!$target) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Item tidak ditemukan.'];
        }

        $configs = $this->getConfigs();
        $threshold = (float)($configs['TRANSFER_HQ_THRESHOLD'] ?? 2000000);
        $valuation = $target['standardCost'] * $qty;
        $requiresHq = $valuation >= $threshold;
        $status = $requiresHq ? 'WAITING_HQ_APPROVAL' : 'IN_TRANSIT';
        $trNumber = 'TR-' . date('Y') . '-' . random_int(100, 999);

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("
                INSERT INTO transfers (id, transfer_number, from_outlet_id, to_outlet_id, item_id, qty, unit, total_valuation, requires_hq_approval, status, requested_by)
                VALUES (:id, :num, 'out-01', 'out-02', :item, :qty, :unit, :val, :req, :st, :by)
            ");
            $stmt->execute([
                ':id' => 'tr-' . uniqid(), ':num' => $trNumber, ':item' => $itemId,
                ':qty' => $qty, ':unit' => $target['unit'], ':val' => $valuation,
                ':req' => $requiresHq ? 1 : 0, ':st' => $status, ':by' => $requestedBy,
            ]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO transfers VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $trNumber, $from, $to, $itemId, $target['name'], $qty, $target['unit'],
                $valuation, $requiresHq ? 1 : 0, $status, $requestedBy, null, date('d M Y, H:i'),
            ]);
        }

        return [
            'status' => 'success',
            'transferNumber' => $trNumber,
            'requiresHqApproval' => $requiresHq,
            'message' => $requiresHq
                ? "Transfer {$trNumber} (Rp " . number_format($valuation, 0, ',', '.') . " ≥ threshold HQ) ditahan otomatis & tersimpan menunggu Approval Owner."
                : "Transfer {$trNumber} (Rp " . number_format($valuation, 0, ',', '.') . ") disetujui otomatis (IN_TRANSIT) & tersimpan permanen.",
            'state' => $this->getBootstrapState(),
        ];
    }

    public function updateTransferStatus(string $trId, string $action, string $role, ?string $reason = null): array
    {
        if (in_array($action, ['approve', 'reject'], true) && $role !== 'OWNER') {
            http_response_code(403);
            return ['status' => 'error', 'message' => 'Akses ditolak: Hanya peran OWNER yang berhak menyetujui/menolak transfer bernilai ≥ Rp 2 Juta.'];
        }

        $newStatus = match ($action) {
            'approve'  => 'IN_TRANSIT',
            'reject'   => 'REJECTED',
            'complete' => 'COMPLETED',
            default    => 'IN_TRANSIT',
        };

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("UPDATE transfers SET status = :st, rejection_reason = :r WHERE transfer_number = :id OR id = :id2");
            $stmt->execute([':st' => $newStatus, ':r' => $reason, ':id' => $trId, ':id2' => $trId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE transfers SET status = :st, rejection_reason = :r WHERE id = :id");
            $stmt->execute([':st' => $newStatus, ':r' => $reason, ':id' => $trId]);
        }

        return [
            'status' => 'success',
            'message' => "Status transfer {$trId} diperbarui menjadi {$newStatus} di database.",
            'state' => $this->getBootstrapState(),
        ];
    }

    public function createPurchaseOrder(array $payload): array
    {
        $outletId = (string)($payload['outletId'] ?? 'out-02');
        $outletName = (string)($payload['outletName'] ?? 'Takashi fried chicken DayoDara');
        $supplierId = (string)($payload['supplierId'] ?? 'sup-01');
        $supplierName = (string)($payload['supplierName'] ?? 'PT Japfa Comfeed Indonesia Tbk');
        $itemId = (string)($payload['itemId'] ?? '');
        $qty = (float)($payload['qty'] ?? 0);
        $expectedDate = (string)($payload['expectedDate'] ?? date('Y-m-d', strtotime('+2 days')));
        $requestedBy = trim((string)($payload['requestedBy'] ?? 'Staff'));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($itemId === '' || $qty <= 0) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Item SKU dan kuantitas PO wajib valid (> 0).'];
        }

        $items = $this->getItems();
        $target = null;
        foreach ($items as $it) {
            if ($it['id'] === $itemId) {
                $target = $it;
                break;
            }
        }
        if (!$target) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Item SKU tidak ditemukan.'];
        }

        $id = 'po-' . uniqid();
        $poNumber = 'PO-' . date('Ymd') . '-' . random_int(100, 999);
        $unitCost = $target['standardCost'];
        $totalCost = $unitCost * $qty;

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_orders (id, po_number, outlet_id, supplier_id, item_id, qty, unit, unit_cost, total_cost, expected_date, status, requested_by, notes)
                VALUES (:id, :num, :out, :sup, :item, :qty, :unit, :uc, :tc, :ed, 'SUBMITTED', :by, :notes)
            ");
            $stmt->execute([
                ':id' => $id, ':num' => $poNumber, ':out' => $outletId, ':sup' => $supplierId,
                ':item' => $itemId, ':qty' => $qty, ':unit' => $target['unit'],
                ':uc' => $unitCost, ':tc' => $totalCost, ':ed' => $expectedDate,
                ':by' => $requestedBy, ':notes' => $notes,
            ]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO purchase_orders VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $id, $poNumber, $outletId, $outletName, $supplierId, $supplierName,
                $itemId, $target['name'], $qty, $target['unit'], $unitCost, $totalCost,
                $expectedDate, 'SUBMITTED', $requestedBy, null, $notes, date('d M Y, H:i'),
            ]);
        }

        return [
            'status' => 'success',
            'poNumber' => $poNumber,
            'message' => "Purchase Order {$poNumber} (Rp " . number_format($totalCost, 0, ',', '.') . ") berhasil dibuat & disimpan.",
            'state' => $this->getBootstrapState(),
        ];
    }

    public function updatePurchaseOrderStatus(string $id, string $newStatus, string $actor, string $role): array
    {
        $validStatuses = ['DRAFT', 'SUBMITTED', 'APPROVED_HQ', 'ORDERED', 'RECEIVED', 'CANCELLED'];
        if (!in_array($newStatus, $validStatuses, true)) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Status PO tidak valid.'];
        }

        if ($newStatus === 'APPROVED_HQ' && !in_array($role, ['OWNER', 'HQ_SUPPLY_CHAIN'], true)) {
            http_response_code(403);
            return ['status' => 'error', 'message' => 'Hanya OWNER atau HQ_SUPPLY_CHAIN yang dapat menyetujui PO.'];
        }

        // Fetch PO to see if receiving should update stock
        $poStmt = $this->pdo->prepare("SELECT * FROM purchase_orders WHERE id = :id OR po_number = :num LIMIT 1");
        $poStmt->execute([':id' => $id, ':num' => $id]);
        $po = $poStmt->fetch();
        if (!$po) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Purchase Order tidak ditemukan.'];
        }

        $approvedBy = $po['approved_by'];
        if ($newStatus === 'APPROVED_HQ') {
            $approvedBy = $actor;
        }

        $upd = $this->pdo->prepare("UPDATE purchase_orders SET status = :st, approved_by = :ab WHERE id = :id");
        $upd->execute([':st' => $newStatus, ':ab' => $approvedBy, ':id' => $po['id']]);

        // If marked RECEIVED and wasn't already RECEIVED, increment live stock automatically
        if ($newStatus === 'RECEIVED' && $po['status'] !== 'RECEIVED') {
            $this->adjustItemStock((string)$po['item_id'], (float)$po['qty']);
        }

        return [
            'status' => 'success',
            'message' => "PO {$po['po_number']} diperbarui ke status {$newStatus}" . ($newStatus === 'RECEIVED' ? ' & stok otomatis bertambah!' : '.'),
            'state' => $this->getBootstrapState(),
        ];
    }

    public function createStockOpname(array $payload): array
    {
        $outletId = (string)($payload['outletId'] ?? 'out-02');
        $outletName = (string)($payload['outletName'] ?? 'Takashi fried chicken DayoDara');
        $itemId = (string)($payload['itemId'] ?? '');
        $physicalStock = (float)($payload['physicalStock'] ?? 0);
        $adjustStock = (bool)($payload['adjustStock'] ?? true);
        $auditedBy = trim((string)($payload['auditedBy'] ?? 'Chef Danu Prasetyo'));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($itemId === '' || $physicalStock < 0) {
            http_response_code(422);
            return ['status' => 'error', 'message' => 'Item SKU dan stok fisik aktual (>= 0) wajib valid.'];
        }

        $items = $this->getItems();
        $target = null;
        foreach ($items as $it) {
            if ($it['id'] === $itemId) {
                $target = $it;
                break;
            }
        }
        if (!$target) {
            http_response_code(404);
            return ['status' => 'error', 'message' => 'Item SKU tidak ditemukan.'];
        }

        $systemStock = $target['currentStock'];
        $discQty = round($physicalStock - $systemStock, 2);
        $discPct = $systemStock > 0 ? round(($discQty / $systemStock) * 100, 2) : ($physicalStock > 0 ? 100.0 : 0.0);
        $absPct = abs($discPct);
        $discVal = round($discQty * $target['standardCost'], 2);

        $toleranceStatus = 'NORMAL';
        if ($absPct > 5.0) {
            $toleranceStatus = 'AUDIT_ALERT_GT_5_PCT';
        } elseif ($absPct > 3.0) {
            $toleranceStatus = 'WARNING_3_5_PCT';
        }

        $id = 'opn-' . uniqid();
        $opnNumber = 'OPN-' . date('Ymd') . '-' . random_int(100, 999);

        if ($this->isMysql) {
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_opnames (id, opname_number, outlet_id, item_id, system_stock, physical_stock, discrepancy_qty, discrepancy_pct, discrepancy_value, tolerance_status, stock_adjusted, audited_by, notes)
                VALUES (:id, :num, :out, :item, :sys, :phy, :dq, :dp, :dv, :ts, :adj, :by, :notes)
            ");
            $stmt->execute([
                ':id' => $id, ':num' => $opnNumber, ':out' => $outletId, ':item' => $itemId,
                ':sys' => $systemStock, ':phy' => $physicalStock, ':dq' => $discQty, ':dp' => $discPct,
                ':dv' => $discVal, ':ts' => $toleranceStatus, ':adj' => $adjustStock ? 1 : 0,
                ':by' => $auditedBy, ':notes' => $notes,
            ]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO stock_opnames VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $id, $opnNumber, $outletId, $outletName, $itemId, $target['name'], $target['unit'],
                $systemStock, $physicalStock, $discQty, $discPct, $discVal, $toleranceStatus,
                $adjustStock ? 1 : 0, $auditedBy, $notes, date('d M Y, H:i'),
            ]);
        }

        if ($adjustStock) {
            $this->adjustItemStock($itemId, 0.0, $physicalStock);
        }

        return [
            'status' => 'success',
            'opnameNumber' => $opnNumber,
            'toleranceStatus' => $toleranceStatus,
            'message' => "Stock Opname {$opnNumber} tersimpan (Selisih {$discPct}% · {$toleranceStatus})" . ($adjustStock ? ' & stok sistem disesuaikan.' : '.'),
            'state' => $this->getBootstrapState(),
        ];
    }
}
