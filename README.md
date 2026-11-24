# 🍗 Dasbord Takashi Fried Chicken (TFC)
### Arsitektur Fullstack: React + Laravel + MySQL 8.0

Sistem manajemen inventori cerdas, pemantauan cold-chain, pelacakan kedaluwarsa FEFO, dan cockpit analitik eksekutif 25 chart untuk franchise restoran **Takashi Fried Chicken (takashi_friedchicken)**.

---

## 🏗️ Arsitektur & Tech Stack

Sesuai standar engineering QSR berkecepatan tinggi:
- **Frontend:** **React 18 SPA** + **Tailwind CSS** + **Chart.js** + **Lucide Icons**
  - Tipografi: *Plus Jakarta Sans* di seluruh antarmuka (UI, Heading, Form, Card, Tabel, serta Angka Tabular/Finansial).
  - Skema Desain: Mengacu penuh pada [TFC Design Brief](file:///C:/Users/hp-PC/.gemini/antigravity/brain/0e63a455-986d-4392-a158-b6deb12a622c/TFC-Design-Brief.md) (*Sumi Black #0F172A*, *TFC Crimson #DC2626*, *Yuzu Amber #D97706*, *Warm Canvas #FFF7ED*).
- **Backend:** **Laravel Framework** (PHP 8.3+)
  - Controller & Service Architecture: `AuthController`, `DashboardController`, `InventoryController`, dll.
  - Front Controller: `public/index.php` dan `artisan` CLI.
- **Database:** **MySQL 8.0**
  - DDL Schema: `database/schema_mysql.sql` (13 tabel relasional, foreign keys, constraints, dan index FEFO).

---

## 🔐 1. Alur Autentikasi (SCR-01 Strict Security Gateway — Wajib Login Dulu)

Sistem menerapkan **Strict Authentication Guard**. Seluruh data inventori, telemetry chiller, dan transaksi cabang terkunci 100% dan **TIDAK DAPAT diakses tanpa login terlebih dahulu**:
1. Saat pertama kali web dibuka, sistem selalu menampilkan **Portal Login Terproteksi (SCR-01)** dengan status `🔴 Belum Terautentikasi`.
2. Pengguna **wajib mengisi email dan kata sandi**, lalu menekan tombol **"Masuk ke Dasbor"**.
3. Tombol demo di sisi kanan berfungsi sebagai **Autofill Helper** (mengisi formulir secara otomatis), dan pengguna tetap harus menekan tombol **"Masuk ke Dasbor"** untuk diverifikasi.
4. Terdapat tombol **"Keluar / Logout"** di header dan sidebar yang akan menghapus sesi `sessionStorage` dan langsung mengunci kembali dasbor ke halaman login.

### Akun Demo Tersedia (Password default: `password`):
| Role | Nama Pengguna | Email | Layar Utama Setelah Login |
| :--- | :--- | :--- | :--- |
| **OWNER** | Sarah Wijaya, S.E. | `owner@takashi.id` | **SCR-02 Executive Cockpit (25 Chart)** |
| **HQ_SUPPLY_CHAIN** | Bima Sakti | `hq@takashi.id` | **SCR-02 Executive Cockpit (25 Chart)** |
| **STORE_MANAGER** | Chef Danu Prasetyo | `dayodara@takashi.id` | **SCR-03 Stok Real-Time Cabang DayoDara** |
| **KITCHEN_STAFF** | Agus Koki | `kitchen.dayodara@takashi.id` | **SCR-05 Kitchen Console & 90m Timer** |

---

## 📍 2. Daftar Cabang Resmi Takashi (Sesuai Data Lokasi Nyata)

Sistem telah disesuaikan dengan data cabang nyata Takashi Fried Chicken:
1. **Takashi fried chicken DayoDara** (Fast food · Jalan Dayo Dara, Talise Valangguni · *Open · Closes 00:00*)
2. **Takashi fried chicken** (Chicken · Jalan Zebra, Lolu Utara)
3. **Takashi Fried Chicken** (Restaurant · Jalan Lapata, Dusun 4)
4. **Takashi Fried Chicken** (Restaurant · Jalan I Gusti Ngurah Rai, Boyaoge)
5. **Central Plant & Kitchen Hub** (Pusat Distribusi Bahan Baku & Cold Storage)

---

## 🚀 3. Cara Menjalankan Aplikasi

### Pilihan A: Menggunakan Laragon (Sangat Direkomendasikan)
Karena folder project berada di `C:\laragon\www\dasbord takasih\`:
1. Buka aplikasi Laragon, pastikan tombol **Start All** (Apache & MySQL) aktif.
2. Buka browser dan akses:
   ```
   http://localhost/dasbord%20takasih/
   ```
   Aplikasi akan langsung memuat **SCR-01 Login Gateway**.

### Pilihan B: Menggunakan PHP Artisan Serve
1. Buka terminal di folder ini:
   ```bash
   php artisan serve --port=8000
   ```
2. Buka browser pada `http://127.0.0.1:8000`.

---

## 🗄️ 3. Setup Database MySQL

Database schema dan data master awal telah disiapkan di `database/schema_mysql.sql`:
1. Buat database di phpMyAdmin / MySQL CLI:
   ```sql
   CREATE DATABASE dasbord_takashi;
   ```
2. Import schema:
   ```bash
   mysql -u root -p dasbord_takashi < database/schema_mysql.sql
   ```
   atau jalankan simulasi via artisan:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

---

## 📊 4. Pemetaan 8 Layar & 25 Chart (PRD)

1. **SCR-01: Login Gateway:** Login email + password, 1-Click quick login demo, dan error handling kredensial.
2. **SCR-02: Executive Analytics Cockpit:** 4 Top KPI Cards + Tab 5 Pilar memuat **25 Chart**:
   - **Pilar 1 (Perencanaan):** Konsumsi vs Forecast, Safety Stock Alert, Weekend Surge (+350%), Stockout Runway, EOQ (150 ekor).
   - **Pilar 2 (Pengadaan):** Komposisi Belanja (Ayam 48%), Lead Time RPA (Japfa 98.4%), PPV Hemat Rp 4.5 Juta, PO Funnel, Reject Gate QC.
   - **Pilar 3 (Penyimpanan):** Utilisasi Chiller (PIM 3 92%), Distribusi Stok (Chilled 45%), Marinade Dwell 12-48h, Log Suhu 24 Jam, Kecepatan Fryer.
   - **Pilar 4 (Kendali Dapur):** Holding Cabinet Horizon (<90m), Pareto Biaya Waste, Discrepancy Opname Harian, TPM Sensor Minyak (24%), Volume Transfer.
   - **Pilar 5 (Valuasi):** Valuasi Stok per Outlet (FIFO), ITR Radar (>15x), DSI Benchmark (<2.5 hari), Analisis Pareto Kelas ABC (Kelas A = 78%), Potensi Lost Sales.
3. **SCR-03: Branch Real-Time Inventory Table:** Filter kategori, kelas ABC, status semaphore (Aman/Perhatian/Kritis), dan tombol **Export CSV**.
4. **SCR-04: Goods Receiving (Stock In):** Validasi nomor LOT, tanggal kedaluwarsa (FEFO), dan **Cold-Chain Safety Gate** (otomatis menolak penerimaan ayam jika suhu ketibaan > 4.0°C).
5. **SCR-05: Kitchen Console (Tablet Mode):** Countdown timer hitung mundur **Holding Cabinet 90 Menit** (peringatan kuning pada 75 menit, merah saat 90 menit), dan log pemakaian vs pembuangan (*Gosong, Holding Expired, Over-portion*).
6. **SCR-06: Inter-Branch Transfer Hub:** Aturan otomatis: transfer < Rp 2 Juta langsung `IN_TRANSIT`; transfer &ge; Rp 2 Juta berstatus `WAITING_HQ_APPROVAL` dan memerlukan persetujuan Owner.
7. **SCR-07: Batch Inspector & Sensor TPM:** Monitoring Total Polar Material minyak fryer (batas kritis 24.0% wajib ganti minyak baru) dan FEFO Expiry Matrix.
8. **SCR-08: Master Data & Thresholds:** Pengaturan batas operasional franchise dan daftar 6 cabang TFC.

---

## 📂 Struktur Direktori Proyek

```
c:\laragon\www\dasbord takasih\
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php      # Controller login, session, dan RBAC
│   │   ├── DashboardController.php # Controller 25 chart analitik
│   │   └── InventoryController.php # Controller CRUD inventori
│   └── Models/
│       ├── User.php                # Model Pengguna & Role
│       └── InventoryItem.php       # Model Master Item & Status
├── database/
│   └── schema_mysql.sql            # Skema lengkap MySQL 8.0 & Data Seed
├── public/
│   └── index.php                   # Front controller Laravel / PHP
├── resources/
│   └── views/
│       └── app.blade.php           # Template view Laravel
├── routes/
│   ├── api.php                     # Route endpoint API
│   └── web.php                     # Route web frontend
├── artisan                         # CLI executable Laravel
├── composer.json                   # Konfigurasi dependensi backend
├── index.html                      # Standalone React 18 SPA (Lengkap dengan SCR-01 Login)
├── .env                            # Konfigurasi environment & database MySQL
└── README.md                       # Dokumentasi lengkap
```

---
*Takashi Fried Chicken Engineering Team — 2026*

# Dasbord_takashi
