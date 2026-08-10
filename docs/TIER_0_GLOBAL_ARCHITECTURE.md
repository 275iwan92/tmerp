# TIER 0 — RANCANGAN GLOBAL ERP PT. TELASIH MAKMUR
### Versi Laravel | Arsitektur Database & Modul (Top-Down)
**Tujuan dokumen:** memberi gambaran menyeluruh dari level tertinggi, agar dapat dikoreksi dari yang paling global sebelum turun ke detail (Tier 1–4).

---

## 0. STRATEGI DUA VERSI (LOCKED)

Sistem berjalan dalam **transisi bertahap** dari ScriptCase ke Laravel:

| | Versi 1 (existing) | Versi 2 (baru) |
|---|---|---|
| Platform | ScriptCase | Laravel 13 |
| Database | `tm*` (tm2mst, tm2sdi, …) | **`tm2*` (tm2mst, tm2sdi, …)** |
| Skema | existing / Tier 3B (campur ID) | **Tier 3A all-new (English, snake_case)** |
| Server | Produksi `103.79.247.146:23306` | Lokal dev → produksi saat cutover |
| Status akhir | **dipensiunkan** setelah migrasi | **menjadi satu-satunya yang hidup** |

**Alur transisi:** dev Laravel di `tm2*` (lokal) → selesai per modul → migrasi data `tm*` → `tm2*` via script transformasi → cutover modul → `tm*` lama dipensiunkan.

> **Konsekuensi penting:** karena `tm2*` akhirnya menggantikan `tm*` (bukan paralel selamanya), nama `tm2*` **permanen** — tidak perlu di-rename balik. Aman dipakai di seluruh kode.
>
> **Peringatan migrasi:** skema 3A ≠ existing (field & struktur berubah, mis. `idproduct`→`product_id`, status ENUM→lookup table). Migrasi data **bukan copy-paste** — butuh script transformasi per modul saat cutover. Dirancang saat tiap modul siap.

---

## 1. PRINSIP ARSITEKTUR (LOCKED)

| # | Keputusan | Nilai |
|---|-----------|-------|
| 1 | **Pemisahan** | 1 database per modul fungsional (database-per-module) |
| 2 | **Alasan** | Skalabilitas — tiap DB dapat dipindah ke server sendiri saat scale |
| 3 | **Join antar-modul** | Lewat **VIEW** (read-only), **bukan** foreign key lintas-DB |
| 4 | **Master data** | **Terpusat** di `tm2mst` sebagai single source of truth |
| 5 | **Integritas lintas-modul** | Tanggung jawab **aplikasi (Laravel)**, bukan DB |
| 6 | **Deployment awal** | 10 DB dalam 1 server MySQL lokal; pisah server saat scale (cukup ubah `DB_HOST`) |
| 7 | **Engine** | MySQL 8.0.46 · InnoDB · utf8mb4 · utf8mb4_unicode_ci |
| 8 | **Penamaan DB** | Prefix **`tm2`** + kode modul 3 huruf (huruf+angka, ringkas) |

> ⚠️ **Konsekuensi sadar dari #3 & #5:** Foreign key MySQL tidak bisa lintas-database. Maka relasi antar-modul (mis. Sales butuh Item dari Inventory) dibaca via VIEW, dan validasi keberadaan data (mis. `item_id` valid) dilakukan di layer aplikasi.

---

## 2. PETA 10 DATABASE

| # | Connection | Database | Modul | Fungsi | Peran |
|---|-----------|----------|-------|--------|-------|
| 1 | `conn_tm2sys` | **tm2sys** | Security & Notification | User, role, hak akses, audit trail, notifikasi | Infrastruktur |
| 2 | `conn_tm2mst` | **tm2mst** | Master / Lookup Data | Data induk & referensi global | **Single Source** |
| 3 | `conn_tm2sdi` | **tm2sdi** | Sales & Distribution | Inquiry → Quotation → SO, Costing, Dispatch, DO | Transaksi inti |
| 4 | `conn_tm2pur` | **tm2pur** | Procurement | PR → PO → Receiving | Transaksi |
| 5 | `conn_tm2inv` | **tm2inv** | Inventory | Item stock, movement, adjustment | Transaksi |
| 6 | `conn_tm2prd` | **tm2prd** | Production | Planning, scheduling, capacity, output | Transaksi |
| 7 | `conn_tm2acc` | **tm2acc** | Finance & Accounting | Cashier, AP/AR, General Ledger | Transaksi |
| 8 | `conn_tm2hrm` | **tm2hrm** | HR (Master) | Kepegawaian, data karyawan | Master + Transaksi |
| 9 | `conn_tm2hrr` | **tm2hrr** | HR Recruitment | Rekrut & seleksi | Transaksi |
| 10 | `conn_tm2pyr` | **tm2pyr** | Payroll | Penggajian | Transaksi |

**Catatan penempatan khusus (LOCKED):**
- `i_inquiry_costing` (MTO costing) → **tm2sdi** (menyatu dengan alur Sales/Inquiry)
- `i_stock_costing` (MTS costing) → **tm2inv** (menyatu dengan stok, tanpa party/inquiry linkage)

---

## 3. POLA RELASI ANTAR-MODUL (VIEW-BASED)

```
                      ┌─────────────────────────┐
                      │        tm2mst            │
                      │   (MASTER — SUMBER)     │
                      │  customer, item, party, │
                      │  supplier, uom, prefix  │
                      └───────────┬─────────────┘
                                  │ dibaca via VIEW (read-only)
        ┌──────────────┬──────────┼──────────┬──────────────┐
        ▼              ▼          ▼          ▼              ▼
   ┌─────────┐   ┌─────────┐  ┌────────┐ ┌────────┐   ┌─────────┐
   │  tm2sdi  │   │  tm2pur  │  │ tm2inv  │ │ tm2prd  │   │  tm2acc  │
   │ Sales   │   │Purchase │  │Invent. │ │ Prod.  │   │Finance  │
   └────┬────┘   └─────────┘  └────────┘ └────────┘   └─────────┘
        │ transaksi Sales juga baca stok/produksi via VIEW
        └──────────────────────────────────────────────────────►
```

**Aturan VIEW:**
- Setiap modul yang butuh data master membuat **VIEW lokal** yang menunjuk ke `tm2mst` (mis. `tm2sdi.v_customer`, `tm2inv.v_item`).
- VIEW bersifat **read-only** — modul tidak boleh menulis ke master lewat VIEW.
- Penulisan master **hanya** melalui modul Master (`tm2mst`).
- Di Laravel: VIEW diperlakukan sebagai Model read-only (tanpa create/update).

---

## 4. KONVENSI PENAMAAN (dari SQL eksisting)

Berdasarkan struktur `tm2mst` aktual:

| Elemen | Konvensi | Contoh |
|--------|----------|--------|
| Prefix tabel master | `doc_` , `m_` | `doc_rmprefix1`, `m_product` |
| Prefix tabel transaksi | `i_` (inquiry/item transaksi) | `i_inquiry_costing`, `i_inquiry_prove_log` |
| Primary key | `id...` UNSIGNED AUTO_INCREMENT | `idprefix`, `idproduct` |
| Charset | utf8mb4 / utf8mb4_unicode_ci | (semua tabel) |
| Engine | InnoDB, ROW_FORMAT=Dynamic | (semua tabel) |
| Komentar tabel | Wajib, Bahasa Indonesia | `COMMENT='Master prefix...'` |

> Konvensi ini **dipertahankan** di versi Laravel agar konsisten dengan sistem eksisting.

---

## 5. INTI SETUP LARAVEL (multi-connection)

### 5.1 `.env` — daftar 10 koneksi
```
# Master
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=xxxx

DB_SYS=tm2sys
DB_MST=tm2mst
DB_SDI=tm2sdi
DB_PUR=tm2pur
DB_INV=tm2inv
DB_PRD=tm2prd
DB_ACC=tm2acc
DB_HRM=tm2hrm
DB_HRR=tm2hrr
DB_PYR=tm2pyr
```
> Saat scale: cukup tambah `DB_HOST_INV=ip-server-lain` per koneksi, tanpa ubah kode.

### 5.2 `config/database.php` — pola tiap koneksi
```php
'tm2mst' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_MST', 'tm2mst'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'strict' => true,
    'engine' => 'InnoDB',
],
// ...ulangi untuk tm2sys, tm2sdi, tm2pur, tm2inv, tm2prd, tm2acc, tm2hrm, tm2hrr, tm2pyr
```

### 5.3 Model menempel ke koneksi
```php
class Item extends Model {
    protected $connection = 'tm2mst';   // master
}
class SalesOrder extends Model {
    protected $connection = 'tm2sdi';   // transaksi sales
}
class ItemView extends Model {         // VIEW read-only di tm2sdi
    protected $connection = 'tm2sdi';
    protected $table = 'v_item';
    public $timestamps = false;
}
```

### 5.4 Migration per koneksi
```php
Schema::connection('tm2inv')->create('stock_movement', function($t){ ... });
```

---

## 6. URUTAN PEMBANGUNAN (ROADMAP)

| Fase | Aktivitas | DB terlibat |
|------|-----------|-------------|
| **0** ✅ | Environment (Laravel, Git, MySQL, Claude Code) | — |
| **1** | Setup 10 database + 10 koneksi Laravel | semua |
| **2** | Modul **Master** (`tm2mst`) — fondasi semua modul | tm2mst |
| **3** | Modul **Security** (`tm2sys`) — user/role/akses | tm2sys |
| **4** | Modul **Sales/Inquiry** (`tm2sdi`) — transaksi inti pertama | tm2sdi + view tm2mst |
| **5** | Modul lain menyusul sesuai prioritas bisnis | — |

> **Kenapa Master & Security dulu?** Semua modul transaksi bergantung pada keduanya (data induk + hak akses). Membangun Sales sebelum Master = menulis VIEW ke tabel yang belum ada.

---

## 7. CATATAN PENTING — DATABASE PRODUKSI SUDAH ADA

File SQL menunjukkan server produksi **aktif**:
`103.79.247.146:23306` · MySQL 8.0.46 · schema **`tmmst`** (nama lama, versi 1) sudah terisi (doc_rmprefix, dll).

**Implikasi keputusan (disesuaikan dengan strategi tm2 + skema 3A all-new):**
- DB produksi lama (`tm*`) = **sumber data** untuk migrasi, **bukan** struktur yang dipakai Laravel.
- DB baru (`tm2*`) dibuat **dari nol** mengikuti skema **Tier 3A** (English, ideal) — via **Migration** sejak awal (bukan impor `.sql`).
- File `.sql` produksi dipakai sebagai **referensi mapping** (field lama → field baru) saat menulis migration & script migrasi data, bukan diimpor mentah.
- Konsisten dengan aturan "semua via migration" — karena `tm2*` memang skema baru, bukan lanjutan `tm*`.

---

## 8. YANG PERLU DIKOREKSI/DIKONFIRMASI

1. **HR terpecah 3 DB** (`tm2hrm`/`tm2hrr`/`tm2pyr`) — apakah recruitment & payroll cukup mandiri untuk DB terpisah, atau lebih baik 1 DB `tmhr` dengan skema terpisah?
2. **tm2sys vs tm2mst** — notifikasi & audit trail di `tm2sys`; apakah lookup/parameter global (bukan master bisnis) juga di `tm2mst` atau di `tm2sys`?
3. **Costing MTS** (`i_stock_costing`) di `tm2inv` — konfirmasi apakah butuh VIEW balik ke `tm2sdi` untuk HPP engine yang sama.
4. **Baseline data lokal** — impor `.sql` produksi ke lokal, atau mulai struktur bersih ikut Tier 3?
