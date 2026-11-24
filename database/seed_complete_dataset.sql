-- ============================================================================
-- TAKASHI FRIED CHICKEN (TFC) - COMPLETE OPERATIONAL DATASET
-- Database: dasbord_takashi
-- Generated: 2026-11-17
-- Includes: Outlets, Users, Items, FEFO Batches, Receiving, Transfers,
--           Kitchen Spoilage, Holding Cabinets, and IoT Sensor Telemetry.
-- ============================================================================

USE `dasbord_takashi`;

-- 1. REAL-TIME OUTLET STOCKS
INSERT INTO `outlet_stocks` (`id`, `outlet_id`, `item_id`, `current_stock`, `allocated_stock`, `in_transit_stock`, `last_opname_at`) VALUES
-- Central Hub (out-01)
('stk-001', 'out-01', 'item-01', 850.00, 150.00, 0.00, '2026-11-16 22:00:00'),
('stk-002', 'out-01', 'item-02', 420.00, 60.00, 0.00, '2026-11-16 22:00:00'),
('stk-003', 'out-01', 'item-03', 310.00, 40.00, 0.00, '2026-11-16 22:00:00'),
('stk-004', 'out-01', 'item-04', 120.00, 20.00, 0.00, '2026-11-16 22:00:00'),
('stk-005', 'out-01', 'item-05', 45.00, 8.00, 0.00, '2026-11-16 22:00:00'),
('stk-006', 'out-01', 'item-06', 80.00, 15.00, 0.00, '2026-11-16 22:00:00'),
('stk-007', 'out-01', 'item-07', 55.00, 10.00, 0.00, '2026-11-16 22:00:00'),
('stk-008', 'out-01', 'item-08', 850.00, 100.00, 0.00, '2026-11-16 22:00:00'),

-- Takashi DayoDara (out-02) - Flagship QSR Kitchen
('stk-011', 'out-02', 'item-01', 78.00, 18.00, 0.00, '2026-11-16 23:00:00'),
('stk-012', 'out-02', 'item-02', 32.00, 10.00, 25.00, '2026-11-16 23:00:00'),
('stk-013', 'out-02', 'item-03', 54.00, 8.00, 0.00, '2026-11-16 23:00:00'),
('stk-014', 'out-02', 'item-04', 18.00, 4.00, 0.00, '2026-11-16 23:00:00'),
('stk-015', 'out-02', 'item-05', 9.00, 2.00, 0.00, '2026-11-16 23:00:00'),
('stk-016', 'out-02', 'item-06', 12.00, 3.00, 10.00, '2026-11-16 23:00:00'),
('stk-017', 'out-02', 'item-07', 14.00, 2.00, 0.00, '2026-11-16 23:00:00'),
('stk-018', 'out-02', 'item-08', 160.00, 30.00, 0.00, '2026-11-16 23:00:00'),

-- Takashi Zebra (out-03)
('stk-021', 'out-03', 'item-01', 52.00, 12.00, 0.00, '2026-11-16 21:30:00'),
('stk-022', 'out-03', 'item-02', 28.00, 5.00, 25.00, '2026-11-16 21:30:00'),
('stk-023', 'out-03', 'item-03', 38.00, 6.00, 0.00, '2026-11-16 21:30:00'),
('stk-024', 'out-03', 'item-04', 14.00, 2.00, 0.00, '2026-11-16 21:30:00'),
('stk-025', 'out-03', 'item-06', 10.00, 2.00, 0.00, '2026-11-16 21:30:00'),

-- Takashi Lapatta (out-04)
('stk-031', 'out-04', 'item-01', 45.00, 10.00, 0.00, '2026-11-16 21:00:00'),
('stk-032', 'out-04', 'item-02', 22.00, 4.00, 0.00, '2026-11-16 21:00:00'),
('stk-033', 'out-04', 'item-04', 11.00, 2.00, 0.00, '2026-11-16 21:00:00'),
('stk-034', 'out-04', 'item-06', 9.00, 2.00, 0.00, '2026-11-16 21:00:00'),

-- Takashi Ngurah Rai (out-05)
('stk-041', 'out-05', 'item-01', 60.00, 15.00, 0.00, '2026-11-16 21:15:00'),
('stk-042', 'out-05', 'item-02', 30.00, 6.00, 0.00, '2026-11-16 21:15:00'),
('stk-043', 'out-05', 'item-04', 15.00, 3.00, 0.00, '2026-11-16 21:15:00'),
('stk-044', 'out-05', 'item-06', 11.00, 2.00, 0.00, '2026-11-16 21:15:00')
ON DUPLICATE KEY UPDATE `current_stock`=VALUES(`current_stock`), `in_transit_stock`=VALUES(`in_transit_stock`);

-- 2. INVENTORY BATCHES (FEFO ENGINE & COLD-CHAIN TELEMETRY)
INSERT INTO `inventory_batches` (`id`, `outlet_id`, `item_id`, `lot_number`, `production_date`, `expiry_date`, `initial_qty`, `remaining_qty`, `unit_cost`, `arrival_temp_celsius`, `dwell_hours`, `status`) VALUES
('btc-001', 'out-02', 'item-01', 'LOT-20261114-042', '2026-11-14', '2026-11-17', 50.00, 12.00, 38500.00, 2.4, 38, 'ACTIVE'),
('btc-002', 'out-02', 'item-01', 'LOT-20261115-055', '2026-11-15', '2026-11-18', 40.00, 26.00, 38500.00, 2.1, 22, 'ACTIVE'),
('btc-003', 'out-02', 'item-01', 'LOT-20261116-089', '2026-11-16', '2026-11-19', 40.00, 40.00, 38500.00, 2.2, 8, 'ACTIVE'),
('btc-004', 'out-02', 'item-02', 'LOT-20261113-030', '2026-11-13', '2026-11-17', 30.00, 8.00, 52000.00, 2.8, 44, 'ACTIVE'),
('btc-005', 'out-02', 'item-02', 'LOT-20261115-061', '2026-11-15', '2026-11-19', 30.00, 24.00, 52000.00, 2.3, 20, 'ACTIVE'),
('btc-006', 'out-02', 'item-04', 'LOT-20261020-008', '2026-10-20', '2027-01-20', 25.00, 18.00, 485000.00, 26.5, NULL, 'ACTIVE'),
('btc-007', 'out-02', 'item-06', 'LOT-20260910-001', '2026-09-10', '2027-03-10', 20.00, 12.00, 340000.00, 28.0, NULL, 'ACTIVE')
ON DUPLICATE KEY UPDATE `remaining_qty`=VALUES(`remaining_qty`), `status`=VALUES(`status`);

-- 3. GOODS RECEIVING LOGS (COLD-CHAIN SAFETY GATE)
INSERT INTO `receiving_logs` (`id`, `receiving_number`, `outlet_id`, `supplier_id`, `received_by`, `item_id`, `lot_number`, `qty_received`, `unit`, `unit_cost`, `production_date`, `expiry_date`, `arrival_temp_celsius`, `cold_chain_status`, `notes`) VALUES
('rcv-001', 'RCV-20261116-001', 'out-02', 'sup-01', 'usr-03', 'item-01', 'LOT-20261116-089', 50.00, 'ekor', 38500.00, '2026-11-16', '2026-11-19', 2.2, 'PASS', 'Suhu prima, armada reefer tersegel rapi.'),
('rcv-002', 'RCV-20261115-004', 'out-02', 'sup-02', 'usr-03', 'item-02', 'LOT-20261115-061', 30.00, 'kg', 52000.00, '2026-11-15', '2026-11-19', 2.3, 'PASS', 'Kualitas karkas segar & higienis.'),
('rcv-003', 'RCV-20261114-002', 'out-02', 'sup-03', 'usr-03', 'item-06', 'LOT-20261114-012', 15.00, 'karton', 340000.00, '2026-11-10', '2027-05-10', 28.5, 'PASS', 'Shortening minyak goreng dalam kondisi tersegel pabrik.'),
('rcv-004', 'RCV-20261112-009', 'out-02', 'sup-01', 'usr-03', 'item-01', 'LOT-20261112-REJ', 40.00, 'ekor', 38500.00, '2026-11-12', '2026-11-15', 5.6, 'REJECTED', 'DITOLAK OTOMATIS: Suhu ketibaan 5.6C melebihi batas food safety 4.0C!')
ON DUPLICATE KEY UPDATE `cold_chain_status`=VALUES(`cold_chain_status`);

-- 4. KITCHEN LOGS (SALES VS SPOILAGE)
INSERT INTO `kitchen_logs` (`id`, `outlet_id`, `user_id`, `item_id`, `log_type`, `qty`, `unit`, `spoilage_reason`, `note`, `logged_at`) VALUES
('klog-01', 'out-02', 'usr-04', 'item-02', 'SALES', 5.00, 'kg', 'REGULAR_USE', 'Order Bento Karaage Siang (14 porsi)', '2026-11-16 14:15:00'),
('klog-02', 'out-02', 'usr-04', 'item-01', 'SPOILAGE', 1.00, 'ekor', 'BURNT_OVERCOOK', 'Minyak fryer overheating saat pergantian shift', '2026-11-16 13:50:00'),
('klog-03', 'out-02', 'usr-03', 'item-01', 'SALES', 15.00, 'ekor', 'REGULAR_USE', 'Fryer Batch Siang #3 Crispy Original', '2026-11-16 13:00:00'),
('klog-04', 'out-02', 'usr-04', 'item-01', 'SPOILAGE', 2.00, 'ekor', 'HOLDING_EXPIRED_90M', 'Ayam di holding cabinet melewati batas kesegaran 90 menit', '2026-11-16 11:45:00'),
('klog-05', 'out-02', 'usr-04', 'item-03', 'SALES', 8.00, 'kg', 'REGULAR_USE', 'Order Spicy Wings Party Box', '2026-11-16 10:30:00'),
('klog-06', 'out-02', 'usr-04', 'item-01', 'SPOILAGE', 0.50, 'ekor', 'THAWING_LOSS', 'Susut karkas alami saat proses de-frosting', '2026-11-16 08:30:00')
ON DUPLICATE KEY UPDATE `qty`=VALUES(`qty`);

-- 5. HOLDING CABINET TIMERS (90-MINUTE HORIZON)
INSERT INTO `holding_cabinets` (`id`, `outlet_id`, `batch_code`, `item_name`, `qty_pieces`, `station`, `fried_at`, `max_minutes`, `status`) VALUES
('hc-01', 'out-02', 'BATCH-HC-301', 'Ayam Crispy Original Potong 9', 18, 'Fryer-01 (Nagoya)', '2026-11-16 13:30:00', 90, 'FRESH'),
('hc-02', 'out-02', 'BATCH-HC-302', 'Spicy Fire Karaage Cut', 24, 'Fryer-02 (Tokyo)', '2026-11-16 12:50:00', 90, 'WARNING_75M'),
('hc-03', 'out-02', 'BATCH-HC-303', 'Takashi Wings Shoyu Garlic', 12, 'Fryer-03 (Osaka)', '2026-11-16 13:55:00', 90, 'FRESH')
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

-- 6. TPM OIL QUALITY SENSOR LOGS (CRITICAL LIMIT 24%)
INSERT INTO `tpm_logs` (`id`, `outlet_id`, `fryer_id`, `tpm_percentage`, `oil_temp_celsius`, `status`, `measured_at`) VALUES
('tpm-01', 'out-02', 'Fryer-01 (Nagoya)', 14.5, 172.5, 'SAFE', '2026-11-16 14:00:00'),
('tpm-02', 'out-02', 'Fryer-02 (Tokyo)', 21.8, 175.0, 'WARNING', '2026-11-16 14:00:00'),
('tpm-03', 'out-02', 'Fryer-03 (Osaka)', 11.2, 171.0, 'SAFE', '2026-11-16 14:00:00'),
('tpm-04', 'out-03', 'Fryer-01 (Zebra)', 24.8, 176.5, 'CRITICAL', '2026-11-16 13:45:00'),
('tpm-05', 'out-04', 'Fryer-01 (Lapatta)', 16.0, 173.0, 'SAFE', '2026-11-16 13:30:00'),
('tpm-06', 'out-05', 'Fryer-01 (Ngurah Rai)', 18.5, 174.0, 'SAFE', '2026-11-16 13:15:00')
ON DUPLICATE KEY UPDATE `tpm_percentage`=VALUES(`tpm_percentage`);

-- 7. INTER-BRANCH TRANSFERS (RP 2.000.000 HQ APPROVAL RULE)
INSERT INTO `transfers` (`id`, `transfer_number`, `from_outlet_id`, `to_outlet_id`, `item_id`, `qty`, `unit`, `total_valuation`, `requires_hq_approval`, `status`, `requested_by`, `created_at`, `approved_at`, `completed_at`) VALUES
('tr-001', 'TR-2026-089', 'out-01', 'out-02', 'item-06', 10.00, 'karton', 3400000.00, 1, 'WAITING_HQ_APPROVAL', 'Chef Danu Prasetyo', '2026-11-16 13:40:00', NULL, NULL),
('tr-002', 'TR-2026-088', 'out-01', 'out-03', 'item-02', 25.00, 'kg', 1300000.00, 0, 'IN_TRANSIT', 'Chef Danu Prasetyo', '2026-11-16 11:15:00', '2026-11-16 11:15:00', NULL),
('tr-003', 'TR-2026-085', 'out-02', 'out-04', 'item-04', 2.00, 'zak', 970000.00, 0, 'COMPLETED', 'Rian Hidayat', '2026-11-15 16:20:00', '2026-11-15 16:20:00', '2026-11-15 18:30:00'),
('tr-004', 'TR-2026-082', 'out-01', 'out-05', 'item-01', 60.00, 'ekor', 2310000.00, 1, 'APPROVED', 'Ahmad Farhan', '2026-11-15 09:30:00', '2026-11-15 10:05:00', '2026-11-15 12:00:00')
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

-- 8. PURCHASE ORDERS (MODUL PO FORMAL PRD)
INSERT INTO `purchase_orders` (`id`, `po_number`, `outlet_id`, `supplier_id`, `item_id`, `qty`, `unit`, `unit_cost`, `total_cost`, `expected_date`, `status`, `requested_by`, `approved_by`, `notes`, `created_at`) VALUES
('po-001', 'PO-20261118-001', 'out-02', 'sup-03', 'item-06', 25.00, 'karton', 340000.00, 8500000.00, '2026-11-20', 'SUBMITTED', 'Chef Danu Prasetyo', NULL, 'Restock mendesak Minyak Padat (stok 12/15 karton < safety stock)', '2026-11-18 09:15:00'),
('po-002', 'PO-20261117-004', 'out-01', 'sup-01', 'item-01', 150.00, 'ekor', 38500.00, 5775000.00, '2026-11-19', 'APPROVED_HQ', 'Bima Sakti', 'Sarah Wijaya, S.E.', 'Pesanan EOQ optimal persiapan weekend surge Jumat-Minggu', '2026-11-17 14:30:00'),
('po-003', 'PO-20261116-002', 'out-02', 'sup-02', 'item-02', 45.00, 'kg', 52000.00, 2340000.00, '2026-11-18', 'ORDERED', 'Chef Danu Prasetyo', 'Sarah Wijaya, S.E.', 'Restock Paha Fillet Karaage Cut (di bawah batas 40 kg)', '2026-11-16 11:00:00'),
('po-004', 'PO-20261114-009', 'out-01', 'sup-04', 'item-04', 20.00, 'zak', 485000.00, 9700000.00, '2026-11-16', 'RECEIVED', 'Bima Sakti', 'Sarah Wijaya, S.E.', 'Tepung Bumbu Crispy Signature diterima lengkap di Hub', '2026-11-14 08:45:00')
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);

-- 9. STOCK OPNAMES (MODUL STOCK OPNAME FORMAL PRD — TOLERANSI 3% - 5%)
INSERT INTO `stock_opnames` (`id`, `opname_number`, `outlet_id`, `item_id`, `system_stock`, `physical_stock`, `discrepancy_qty`, `discrepancy_pct`, `discrepancy_value`, `tolerance_status`, `stock_adjusted`, `audited_by`, `notes`, `audited_at`) VALUES
('opn-001', 'OPN-20261118-001', 'out-02', 'item-01', 80.00, 78.00, -2.00, -2.50, -77000.00, 'NORMAL', 1, 'Chef Danu Prasetyo', 'Susut natural thawing masih dalam batas toleransi <= 3%.', '2026-11-18 07:30:00'),
('opn-002', 'OPN-20261117-002', 'out-02', 'item-02', 33.50, 32.00, -1.50, -4.48, -78000.00, 'WARNING_3_5_PCT', 1, 'Chef Danu Prasetyo', 'Selisih 4.48% masuk zona warning 3%-5% (over-portioning karaage).', '2026-11-17 22:15:00'),
('opn-003', 'OPN-20261116-003', 'out-03', 'item-06', 11.00, 10.00, -1.00, -9.09, -340000.00, 'AUDIT_ALERT_GT_5_PCT', 1, 'Rian Hidayat', 'AUDIT ALERT > 5%: 1 karton minyak tidak tercatat saat penggantian fryer kritis.', '2026-11-16 21:45:00')
ON DUPLICATE KEY UPDATE `tolerance_status`=VALUES(`tolerance_status`);
