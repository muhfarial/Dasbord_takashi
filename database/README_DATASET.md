# 📦 Dataset Operasional Dasbor Takashi Fried Chicken (TFC)
### Arsitektur Data: Relasional MySQL 8.0, JSON REST API & CSV Ekspor

Dataset ini disusun secara komprehensif berdasarkan **PRD Dasbor Manajemen Inventori Takashi Fried Chicken** dan mencakup data lokasi cabang nyata di Kota Palu, Sulawesi Tengah.

---

## 📂 Struktur Berkas Dataset

```
database/
├── schema_mysql.sql               # Skema DDL 13 tabel MySQL + relasi foreign key & index FEFO
├── seed_complete_dataset.sql      # Kumpulan query INSERT data operasional siap impor ke MySQL/phpMyAdmin
├── datasets_json/                 # Format JSON untuk API Endpoint & Frontend State
│   ├── outlets.json               # Master cabang Palu & Hub Cold Storage
│   ├── inventory_items.json       # Master SKU bahan baku & klasifikasi ABC
│   ├── fefo_batches.json          # Nomor LOT, tanggal kedaluwarsa & suhu kedatangan
│   ├── kitchen_waste_logs.json    # Log penjualan dapur vs susut/spoilage & akar penyebab
│   ├── transfers.json             # Transfer antar cabang dengan aturan approval Rp 2 Juta
│   └── iot_telemetry.json         # Telemetri sensor TPM minyak & suhu chiller walk-in
└── datasets_csv/                  # Format CSV kompatibel Microsoft Excel & Google Sheets
    ├── tfc_inventory_master.csv   # Data master inventori & safety stock
    ├── tfc_fefo_batches.csv       # Matriks FEFO & expiry horizon
    ├── tfc_kitchen_waste_logs.csv # Log pembuangan & estimasi kerugian Rupiah
    └── tfc_transfers.csv          # Riwayat mutasi stok antar outlet
```

---

## 🍗 1. Master Cabang & Fasilitas (Sesuai Foto Riil)

| Kode Outlet | Nama Cabang | Tipe Fasilitas | Alamat | Jam Buka |
| :--- | :--- | :--- | :--- | :--- |
| `TFC-HQ` | Central Processing Plant & Hub | CENTRAL_PLANT | Kawasan Pusat Suplai & Cold Storage Hub | 24 Jam (Logistik) |
| `TFC-DYD` | Takashi fried chicken DayoDara | FAST_FOOD (Kitchen) | Jalan Dayo Dara, Talise Valangguni | Open · Closes 00:00 |
| `TFC-ZBR` | Takashi fried chicken (Zebra) | CHICKEN STORE | Jalan Zebra, Lolu Utara | Open |
| `TFC-LPT` | Takashi Fried Chicken (Lapatta) | RESTAURANT | Jalan Lapata, Dusun 4 | Open |
| `TFC-NGR` | Takashi Fried Chicken (Ngurah Rai)| RESTAURANT | Jalan I Gusti Ngurah Rai, Boyaoge | Open |

---

## 🥩 2. Master Item Bahan Baku & Klasifikasi ABC

| SKU | Nama Bahan Baku | Kategori | Satuan | Kelas ABC | Safety Stock | Optimal | Harga Standar (Rp) |
| :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: |
| `TFC-RAW-001` | Ayam Karkas Segar 0.9-1.0kg (Potong 9) | Ayam Segar | ekor | **A** | 60 | 250 | Rp 38.500 |
| `TFC-RAW-002` | Paha Fillet Boneless (Karaage Cut) | Ayam Segar | kg | **A** | 40 | 180 | Rp 52.000 |
| `TFC-RAW-003` | Sayap Ayam (Wings) | Ayam Segar | kg | **B** | 25 | 100 | Rp 42.000 |
| `TFC-DRY-001` | Tepung Bumbu Crispy Signature 25kg | Bumbu | zak | **A** | 10 | 45 | Rp 485.000 |
| `TFC-DRY-002` | Japanese Garlic Shoyu Marinade 5L | Bumbu | jerigen | **B** | 6 | 24 | Rp 215.000 |
| `TFC-OIL-001` | Minyak Padat Deep Frying Shortening 15kg | Minyak Goreng| karton | **A** | 15 | 60 | Rp 340.000 |
| `TFC-SAU-001` | Saus Mentai Bakar TFC 5L | Saus | jerigen | **B** | 8 | 30 | Rp 275.000 |
| `TFC-PCK-001` | Combo Box Packaging Eco-Friendly | Kemasan | pack | **C** | 50 | 300 | Rp 45.000 |

---

## ⚙️ 3. Aturan Bisnis & Ambang Batas Operasional (Business Rules)

1. **Cold-Chain Safety Gate:**
   - Suhu kedatangan karkas ayam diwajibkan $\le 4.0^\circ\text{C}$.
   - Jika armada reefer tiba dengan suhu $> 4.0^\circ\text{C}$, sistem menandai `REJECTED` secara otomatis.
2. **Holding Cabinet Countdown (90 Menit):**
   - Masa simpan ayam goreng matang di holding cabinet: maksimal **90 menit**.
   - Menit 0–74: `FRESH` (Hijau).
   - Menit 75–89: `WARNING_75M` (Kuning / Prioritas Sajian).
   - Menit $\ge 90$: `EXPIRED_90M` (Merah / Wajib dibuang & dicatat sebagai Waste).
3. **Aturan Persetujuan Transfer Cabang (Rp 2.000.000):**
   - Mutasi stok dengan total valuasi $< \text{Rp } 2.000.000$ disetujui otomatis (`IN_TRANSIT`).
   - Mutasi stok $\ge \text{Rp } 2.000.000$ ditahan otomatis (`WAITING_HQ_APPROVAL`) dan memerlukan *digital sign-off* akun Owner (`owner@takashi.id`).
4. **Sensor TPM Minyak Goreng:**
   - Batas maksimal polar material: **24.0% TPM**. Di atas 24.0% wajib dikuras dan ganti minyak shortening baru demi standar kerenyahan & kesehatan konsumen.

---

## 💻 4. Cara Impor ke Database MySQL (phpMyAdmin / Laragon)

### Melalui phpMyAdmin:
1. Buka `http://localhost/phpmyadmin/`.
2. Buat database baru dengan nama `dasbord_takashi`.
3. Klik tab **Import**, pilih berkas:
   - Pertama: `database/schema_mysql.sql`
   - Kedua: `database/seed_complete_dataset.sql`
4. Klik **Go / Kirim**. Seluruh tabel dan baris data akan langsung terisi.

### Melalui Terminal / CLI:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS dasbord_takashi;"
mysql -u root -p dasbord_takashi < "c:\laragon\www\dasbord takasih\database\schema_mysql.sql"
mysql -u root -p dasbord_takashi < "c:\laragon\www\dasbord takasih\database\seed_complete_dataset.sql"
```
