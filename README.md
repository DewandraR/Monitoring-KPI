# Monitoring-KPI

Sistem **Monitoring KPI** berbasis **Laravel (Blade)** + **pipeline sinkronisasi data SAP → MySQL** menggunakan Python. Repository ini berfokus pada **pengambilan master data Work Center ↔ Person (NIK/PERNR)** dan **pengambilan data KPI (YPPR058)** dari SAP melalui RFC, lalu menyimpannya ke MySQL untuk dipakai aplikasi web/reporting.

> Catatan: RFC yang dipanggil di project ini mencakup RFC standar (mis. `RFC_READ_TABLE`) dan RFC custom Z\_*. Pastikan RFC tersebut tersedia di landscape SAP Anda.

---

## Daftar Isi

- [Gambaran Umum](#gambaran-umum)
- [Arsitektur Singkat](#arsitektur-singkat)
- [Fitur Utama](#fitur-utama)
- [Prasyarat](#prasyarat)
- [Instalasi](#instalasi)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Struktur Database](#struktur-database)
- [Alur Data](#alur-data)
- [Panduan Script 1: `wc_person_to_mysql.py`](#panduan-script-1-wc_person_to_mysqlpy)
- [Panduan Script 2: `yppr058_loader.py`](#panduan-script-2-yppr058_loaderpy)
- [Penjadwalan (Cron)](#penjadwalan-cron)
- [Logging](#logging)
- [Troubleshooting](#troubleshooting)
- [Kontribusi](#kontribusi)
- [Lisensi](#lisensi)

---

## Gambaran Umum

Project ini biasanya dijalankan dalam 2 tahap utama:

1. **Sinkron master data Work Center → Person (PERNR)** ke tabel MySQL (`wc_person_data`), termasuk enrichment (role, deskripsi WC, devisi, kode_laravel), blacklist, dan backup bulanan.
2. **Tarik data KPI YPPR058** dari SAP per tanggal/per pasangan Work Center–Plant, berdasarkan daftar PERNR aktif di `wc_person_data`, lalu simpan ke tabel MySQL (`yppr058_data`).

Aplikasi web Laravel memanfaatkan tabel-tabel tersebut untuk kebutuhan monitoring/reporting KPI.

---

## Arsitektur Singkat

**SAP** (RFC)  
↓  
**Python ETL/Loader**
- `wc_person_to_mysql.py` → isi `wc_person_data` (+ backup bulanan)
- `yppr058_loader.py` → isi `yppr058_data` (berbasis `wc_person_data`)  
↓  
**MySQL** (utf8mb4)  
↓  
**Laravel Web App** (Blade) / report UI

---

## Fitur Utama

### 1) Master Work Center ↔ Person (`wc_person_to_mysql.py`)
- Auto-scan **CRHD** untuk semua Work Center aktif (LVORM != 'X') via `RFC_READ_TABLE`, atau bisa dipersempit dengan filter plant / LIKE ARBPL.
- Tarik person list dari RFC `CR_PERSONS_OF_WORKCENTER` per pasangan (ARBPL, WERKS).
- **Blacklist PERNR**:
  - Hardcoded
  - Tambahan dari ENV
  - Tambahan dari file TXT/CSV
  - Opsi **purge** global untuk menghapus semua baris PERNR blacklist dari tabel.
- **Selective refresh**: delete data lama per pasangan (ARBPL, WERKS), lalu insert/upsert data baru.
- **Enrichment**:
  - `role` via RFC `Z_RFC_DISPLAY_NIK_CONF` (set `INDUK` bila aktif).
  - `desc` (deskripsi Work Center) via RFC `Z_FM_GET_WC_DESC`.
  - `devisi` & `kode_laravel` dari Excel `DEVISI.xlsx` (support merged cell kolom F untuk `kode_laravel`).
- **Backup bulanan** otomatis: **hanya jalan setiap tanggal 6**, menyalin isi `wc_person_data` ke tabel backup per bulan.

### 2) Loader KPI YPPR058 (`yppr058_loader.py`)
- Ambil daftar pasangan (ARBPL, WERKS) dari `wc_person_data` (atau override manual / filter).
- Tarik KPI dari RFC `Z_FM_YPPR058DX` dengan model **T_ARBPL & T_PERNR**.
- Mode tanggal fleksibel:
  - `--yesterday` (1 hari kemarin)
  - `--dates` (daftar tanggal spesifik)
  - `--begda/--endda` (range, otomatis split per hari)
  - default: loop harian **descending** dari kemarin sampai tanggal 1 (bulan berjalan / bulan lalu, tergantung tanggal hari ini).
- **Sinkronisasi PERNR otomatis** (sebelum tarik data):
  - Hapus PERNR di `yppr058_data` yang tidak ada di `wc_person_data`.
  - Hapus kombinasi (PERNR, ARBPL, WERKS) di `yppr058_data` yang sudah tidak ada di `wc_person_data`.
- **Anti tabrakan proses**: pair-lock per (ARBPL, WERKS) pakai MySQL `GET_LOCK()` saat fase delete + upsert.
- **Log mutasi special-case**:
  - Panggil RFC `Z_FM_YPP_LOG_MUTA` per PERNR.
  - Jika `EV_SUBRC == 0` → PERNR dianggap “spesial” dan **ditarik 1-per-1**.
  - Selain itu → ditarik normal pakai chunk (`--pernr-chunk`).
- Enrichment output dari DB WC:
  - `desc` + `devisi` diambil dari `wc_person_data`
  - `role` per PERNR diambil dari `wc_person_data` pada tanggal terkait.

---

## Prasyarat

### Backend Web (Laravel)
- PHP (sesuaikan dengan requirement project Laravel Anda)
- Composer
- MySQL/MariaDB

### Python Loader
- Python 3.9+ (disarankan 3.10+)
- Library:
  - `python-dotenv`
  - `mysql-connector-python`
  - `pyrfc` *(butuh SAP NetWeaver RFC SDK / NWRFC SDK terinstall di host)*
  - `openpyxl` *(opsional, hanya untuk baca `DEVISI.xlsx`)*

---

## Instalasi

### 1) Clone repository
```bash
git clone https://github.com/DewandraR/Monitoring-KPI.git
cd Monitoring-KPI
```

### 2) Setup Laravel (umum)
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

> Pastikan konfigurasi DB di `.env` Laravel mengarah ke database yang sama yang dipakai script Python (atau setidaknya satu server MySQL yang sama).

### 3) Setup Python (direkomendasikan pakai venv)
```bash
python -m venv .venv
# Windows: .venv\Scripts\activate
# Linux/Mac: source .venv/bin/activate

pip install -r requirements.txt
```
Jika repository belum punya `requirements.txt`, minimal:
```bash
pip install python-dotenv mysql-connector-python openpyxl pyrfc
```

> **Catatan penting:** `pyrfc` tidak selalu bisa di-install tanpa dependency SAP NWRFC SDK. Ikuti panduan instalasi `pyrfc` sesuai OS Anda.

---

## Konfigurasi Environment

Kedua script Python memanggil `load_dotenv()` → Anda bisa menaruh konfigurasi di file `.env` pada root project.

### Variabel SAP
```env
SAP_ASHOST=192.168.254.154
SAP_SYSNR=01
SAP_CLIENT=300
SAP_LANG=EN
SAP_USER=auto_email
SAP_PASS=11223344
```

### Variabel MySQL
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=root
DB_NAME=wc_person
```

### Variabel tambahan `wc_person_to_mysql.py`
```env
DB_TABLE=wc_person_data
DB_BACKUP_TABLE=wc_person_backup

# lokasi log (opsional)
WC_LOG_DIR=storage/logs/python_wc_person_mysql

# tambahan blacklist (comma-separated)
WC_BLACKLIST_PERNR=10001234,10005678

# lokasi Excel mapping devisi
WC_DEVISI_FILE=DEVISI.xlsx
```

### Variabel tambahan `yppr058_loader.py`
```env
WC_TABLE=wc_person_data
DB_TABLE_OUT=yppr058_data

# lokasi log (opsional)
YPPR058_LOG_DIR=storage/logs/python wc_person_mysql
```

> Tips: Bila Anda deploy di server, simpan `.env` sebagai secret (jangan commit kredensial SAP/DB ke repo).

---

## Struktur Database

Project ini akan membuat database & tabel otomatis (jika belum ada) menggunakan DDL di script.

### Database
- **Default database**: `wc_person`
- Charset/Collation: `utf8mb4` / `utf8mb4_unicode_ci`

### Tabel `wc_person_data`
Dibuat & dimigrasi otomatis oleh `wc_person_to_mysql.py`.

Field penting:
- Identitas: `objid`, `pernr`, `begda`, `endda`
- Konteks: `arbpl` (Work Center), `werks` (Plant)
- Enrichment:
  - `role` (mis. `INDUK`)
  - `desc` (deskripsi WC)
  - `devisi`
  - `kode_laravel`
- Unique key: `(objid, pernr, begda, arbpl, werks)`

### Tabel backup `wc_person_backup`
Dibuat otomatis oleh `wc_person_to_mysql.py`.
- Menyimpan snapshot per bulan (`backup_month` = `YYYY-MM`, bulan yang dibackup = bulan sebelumnya).
- Diproses **hanya setiap tanggal 6** (refresh: delete bulan itu lalu insert ulang).

### Tabel `yppr058_data`
Dibuat & dimigrasi otomatis oleh `yppr058_loader.py`.

Field penting (ringkas):
- `pernr`, `begda`
- KPI numeric: `total_jam`, `mint2`, `mintu`, `mintu2`, `mintu3`, `gji`, `gji2`, `varnt`, `varnt1`
- WC info: `arbpl`, `arbpl2`, `werks`, `shift`
- Enrichment: `desc`, `role`, `devisi`
- Unique key: `(pernr, begda, arbpl, werks)`

---

## Alur Data

### Urutan proses yang disarankan
1) Refresh master WC-person:
```bash
python wc_person_to_mysql.py
```

2) Tarik KPI YPPR058:
```bash
python yppr058_loader.py --yesterday
```
atau mode default (loop harian descending):
```bash
python yppr058_loader.py
```

---

## Panduan Script 1: `wc_person_to_mysql.py`

### Fungsi utama
Memuat hasil RFC `CR_PERSONS_OF_WORKCENTER` ke MySQL, dengan opsi auto-scan pasangan WC aktif dari tabel SAP `CRHD` (via `RFC_READ_TABLE`).

### Cara kerja ringkas
1. Tentukan pasangan (ARBPL, WERKS) yang akan diproses:
   - dari argumen `--pairs`, `--arbpl/--werks`, atau `--pairs-file`
   - jika kosong → auto-read CRHD dan filter (opsional) `--werks-filter` / `--like`
2. Untuk tiap pasangan:
   - ambil data dari RFC
   - normalize field, pad PERNR jadi 8 digit bila numerik
   - filter blacklist
   - delete data lama (kecuali `--no-delete` / `--dry-run`)
   - upsert batch ke MySQL
3. Setelah semua pasangan:
   - update `role` via `Z_RFC_DISPLAY_NIK_CONF` (skip jika `--dry-run`)
   - update `desc` via `Z_FM_GET_WC_DESC`
   - update `devisi` + `kode_laravel` dari Excel
   - backup bulanan (hanya tanggal 6)

### Command examples
**1) Mode default (auto READ CRHD semua WC aktif)**
```bash
python wc_person_to_mysql.py
```

**2) Filter plant dan LIKE ARBPL saat auto READ**
```bash
python wc_person_to_mysql.py --werks-filter 1000 --werks-filter 2000 --like "WC%"
```

**3) Pasangan spesifik**
```bash
python wc_person_to_mysql.py --pairs "WC034:1000,WC035:2000"
```

**4) Kombinasi ARBPL/WERKS via argumen terpisah**
```bash
python wc_person_to_mysql.py --arbpl WC034 --arbpl WC035 --werks 1000 --werks 2000
```

**5) Lihat rencana saja (tanpa ubah DB)**
```bash
python wc_person_to_mysql.py --dry-run --show-pairs --verbose-steps
```

**6) Tambah blacklist dari file & purge**
```bash
python wc_person_to_mysql.py --blacklist-file my_blacklist.csv --purge-blacklist
```

### Daftar argumen CLI (ringkas)
- Input pasangan:
  - `--arbpl` (repeatable)
  - `--werks` (repeatable)
  - `--pairs "ARBPL:WERKS,..."`
  - `--pairs-file <csv>` (header minimal: `ARBPL,WERKS`)
- Auto READ filter:
  - `--werks-filter <WERKS>` (repeatable)
  - `--like "WC%"` (repeatable, `%/_` seperti SQL LIKE)
- Tanggal:
  - `--date-all` (default `31.12.9999` → dikonversi jadi `99991231`)
- Blacklist:
  - `--blacklist-file <txt/csv>` (CSV butuh kolom `PERNR`)
  - `--purge-blacklist`
- Mode/log/performa:
  - `--show-pairs`
  - `--verbose-steps`
  - `--no-delete`
  - `--dry-run`
  - `--batch <n>` (default 500)

---

## Panduan Script 2: `yppr058_loader.py`

### Fungsi utama
Memuat hasil RFC `Z_FM_YPPR058DX` ke MySQL (`yppr058_data`) dengan input pasangan WC & daftar PERNR yang diambil dari `wc_person_data`.

### Cara kerja ringkas
**A. Persiapan (sekali per run)**
1. Connect SAP dan MySQL.
2. Buat/alter tabel output jika perlu.
3. Jalankan **sinkronisasi awal** `yppr058_data` vs `wc_person_data`:
   - Level 1: hapus PERNR di YPPR yang tidak ada di WC person
   - Level 2: hapus kombinasi (PERNR,ARBPL,WERKS) di YPPR yang tidak ada di WC person
   - Jika `--dry-run` atau `--no-delete` → hanya log, tidak hapus.

**B. Proses per hari (begda=endda per hari)**
Untuk tiap pasangan (ARBPL,WERKS):
1. Ambil `desc` dan `devisi` untuk pair dari `wc_person_data`.
2. Ambil semua PERNR aktif pada tanggal itu (begda/endda dari WC person).
3. Ambil role map PERNR→role (jika tersedia).
4. Jalankan RFC `Z_FM_YPP_LOG_MUTA` per PERNR:
   - EV_SUBRC=0 → masuk list “special” → ditarik satu-per-satu
   - lainnya → masuk list “normal” → ditarik chunk (`--pernr-chunk`)
5. **Lock pair** (MySQL `GET_LOCK`) sebelum delete+upsert agar aman untuk parallel run.
6. Delete selektif untuk rentang hari itu (kecuali `--no-delete` / `--dry-run`).
7. Upsert batch hasil RFC.

### Mode tanggal (prioritas)
1. `--yesterday` → hanya 1 hari kemarin (abaikan `--dates`/range)
2. `--dates` → daftar tanggal spesifik (urutan sesuai input)
3. `--begda/--endda` → range (di-split per hari urutan naik)
4. Tanpa semua → default loop harian **descending** dari kemarin sampai tanggal 1:
   - Jika hari ini **<= 6** → turun sampai tanggal 1 **bulan lalu**
   - Jika hari ini **> 6** → turun sampai tanggal 1 **bulan berjalan**

### Command examples
**0) Tarik hanya data HARI KEMARIN**
```bash
python yppr058_loader.py --yesterday
```

**1) Mode default (kemarin turun ke tgl 1)**
```bash
python yppr058_loader.py
```

**2) Filter pasangan saat baca dari DB**
```bash
python yppr058_loader.py --werks-filter 1000 --werks-filter 3000 --like "WC%"
```

**3) Pasangan spesifik**
```bash
python yppr058_loader.py --pairs "WC034:1000,WC035:3000"
```

**4) Range tanggal (akan split per hari)**
```bash
python yppr058_loader.py --begda 2025-11-09 --endda 2025-11-11
```

**5) Tanggal spesifik (urutan dipertahankan)**
```bash
python yppr058_loader.py --dates 2025-11-11,2025-11-10,2025-11-09
```

**6) Dry-run**
```bash
python yppr058_loader.py --dry-run --show-pairs
```

**7) Skip delete lama (hanya upsert)**
```bash
python yppr058_loader.py --no-delete
```

### Daftar argumen CLI (ringkas)
- Pair selection:
  - `--pairs "ARBPL:WERKS,..."`
  - `--arbpl` (repeatable)
  - `--werks` (repeatable)
  - `--werks-filter` (repeatable)
  - `--like "WC%"` (repeatable)
- Tanggal:
  - `--yesterday`
  - `--dates "<d1>,<d2>,..."`
  - `--begda`, `--endda`
- Mode/performa:
  - `--show-pairs`
  - `--verbose-steps`
  - `--no-delete`
  - `--dry-run`
  - `--batch` (default 500)
  - `--pernr-chunk` (default 100)
  - `--sample-log` (default 8)
  - `--lock-timeout` (default 120 detik)

---

## Penjadwalan (Cron)

Berikut contoh **konsep** penjadwalan harian (sesuaikan jam dan server Anda):

### 1) Refresh master wc_person (harian)
Misal jam 00:10 setiap hari:
```cron
10 0 * * * cd /path/to/Monitoring-KPI && /usr/bin/python3 wc_person_to_mysql.py >> storage/logs/cron_wc_person.log 2>&1
```

### 2) Tarik YPPR058 (harian)
Misal jam 00:30 setiap hari:
```cron
30 0 * * * cd /path/to/Monitoring-KPI && /usr/bin/python3 yppr058_loader.py --yesterday >> storage/logs/cron_yppr058.log 2>&1
```

### 3) Backup bulanan WC person
**Tidak perlu cron khusus**: `wc_person_to_mysql.py` otomatis melakukan backup **hanya tanggal 6** saat dijalankan.

---

## Logging

### `wc_person_to_mysql.py`
- Console log
- Rotating daily log:
  - `storage/logs/python_wc_person_mysql/wc_person_to_mysql.log`
- Log unik per-run:
  - `storage/logs/python_wc_person_mysql/wc_person_to_mysql_<pid>_<timestamp>.log`
- Override lokasi log:
  - ENV `WC_LOG_DIR`

### `yppr058_loader.py`
- Log unik per-run:
  - default: `storage/logs/python wc_person_mysql/yppr058_loader_<pid>_<timestamp>.log`
- Override lokasi log:
  - ENV `YPPR058_LOG_DIR`

---

## Troubleshooting

### 1) `pyrfc` gagal install / error library
- Pastikan SAP NWRFC SDK sudah terpasang dan environment (PATH/LD_LIBRARY_PATH) sudah benar.
- Cek dokumentasi `pyrfc` sesuai OS Anda.

### 2) Gagal konek SAP (`CommunicationError` / `LogonError`)
- Pastikan `SAP_ASHOST`, `SAP_SYSNR`, `SAP_CLIENT`, `SAP_USER`, `SAP_PASS` benar.
- Pastikan jaringan ke SAP (VPN/route/firewall) terbuka.

### 3) Excel devisi tidak terbaca
- Install `openpyxl`:
  ```bash
  pip install openpyxl
  ```
- Pastikan file `DEVISI.xlsx` ada di path yang benar, atau set `WC_DEVISI_FILE`.

### 4) Data tidak ter-update
- Jalankan dengan `--verbose-steps` untuk detail step RFC dan DB.
- Cek log unik per-run (paling mudah untuk investigasi).

### 5) Proses “nyangkut” karena lock
- `yppr058_loader.py` memakai `GET_LOCK()` per pair `(ARBPL, WERKS)`.
- Jika ada proses lain yang masih berjalan, proses berikutnya menunggu hingga `--lock-timeout` (default 120 detik), lalu skip pair tersebut.

---

## Kontribusi
Kontribusi sangat dipersilakan:
1. Fork repo
2. Buat branch fitur (`feature/...`)
3. Pull Request dengan deskripsi jelas dan langkah test

---

## Lisensi
Repo ini mengikuti lisensi yang digunakan oleh dependency utamanya (Laravel), dan/atau lisensi yang Anda tetapkan di repository. Jika belum ada file `LICENSE`, Anda bisa menambahkan sesuai kebutuhan.
