# customer_spec.md
Modul: **Lookup/Master Data** (dipakai Sales). DB: **`tm2mst`**. Prefix: **`cust_`**.
Status: **LOCKED** — spec self-contained untuk Claude Code. Tulis tanpa nanya; kalau ada
konflik dgn file lain, file ini menang untuk domain customer.

> **CATATAN 2026-08-19 — SUPERSEDED oleh implementasi nyata.** Skema `cust_customer` yang
> benar-benar berjalan sekarang (lihat `app/Models/Master/CustCustomer.php` +
> `resources/views/pages/master/customer.blade.php`) SUDAH MENYIMPANG dari DDL §2 di bawah:
> PK `id_customer` (bukan `customer_id`), tanpa `entity_type`/`trade_type`/`credit_hold`/
> `risk_class`/`status` enum — dipakai `customer_type`(local/import), `category`,
> `approval_status`(DRAFT/CREDIT_CHECK/APPROVED/BLOCKED), `is_blacklisted`. Dokumen ini TIDAK
> pernah dieksekusi sesuai desainnya sendiri. Satu-satunya bagian yang masih dipakai sebagai
> keputusan valid: §1.5 "buang Sales Org/Distribution Channel — SAP-isme tak relevan
> percetakan". Untuk skema aktual, baca kode langsung — jangan percaya DDL §2 di file ini.

Referensi mockup UI: `master_customer_mockup.html` (struktur layar + dummy) — **replikasi**,
bukan desain baru. Selaraskan token ke standar TMERP (lihat §6).

---

## 1. KEPUTUSAN TERKUNCI (jangan re-question)

1. **Migrasi** dari `m_customer` (flat, existing) → tiga tabel:
   `cust_customer` (header) + `cust_address` (1:N) + `cust_contact` (1:N).
   `m_customer` lama = sumber data migrasi (baca §7), lalu di-*deprecate* (rename `m_customer_olddata`, jangan DROP).
2. **Alamat 1:N wajib terpisah & bisa branching.** Satu customer (mis. "Bank BCA")
   punya banyak alamat: BILLING pusat + banyak SHIPPING/BRANCH. Tipe alamat = enum.
3. **Accounting WAJIB ada** (permintaan owner): AR reconciliation account, payment term,
   tax group, payment method, credit limit/balance, credit hold, risk class — semua di header.
4. **Semantik `customer_type` lama (local/import) DIPINDAH** ke kolom `trade_type`.
   Kolom baru `entity_type` = COMPANY|INDIVIDUAL (dari mockup). **Jangan** campur dua makna.
5. **SAP-isme yang DIBUANG** (tak relevan percetakan): Sales Org, Distribution Channel.
   **DIPERTAHANKAN**: Incoterms (relevan `trade_type=IMPORT`), Recon Account, Tax Group,
   Payment Terms/Method, Credit block, Risk Class.
6. **Metrics** (Sales Orders berjalan, Total Invoiced, Sisa Limit Kredit) = **DERIVED**,
   bukan kolom master. Sajikan via VIEW `v1_customer_metrics` / query runtime lintas modul
   (co_*, do_*, fin_*). Header master hanya simpan `credit_limit` & `current_balance`.
7. **FK lintas-DB = LOGIKAL** (Eloquent), bukan constraint MySQL. `currency`, `geo_*`,
   `fin_coa`, `emp_*` di-refer via id + index, **tanp** `CONSTRAINT ... FOREIGN KEY`.
   FK **intra-`tm2mst`** (`cust_address.customer_id`, `cust_contact.customer_id`) = fisik.
8. **DEPENDENCY belum tersedia**: `fin_coa` (Chart of Account, modul Finance belum dibuat).
   `ar_account_code` disimpan sebagai VARCHAR + komentar "FK logikal → fin_coa (future)".
   Payment term & tax group: enum sementara (lookup `ref_*` menyusul bila diminta).
9. Audit 4 kolom `created_at/updated_at/created_by/updated_by` (ikut konvensi `fin_`/`geo_`,
   **bukan** `create_log/create_by` gaya lama).
10. Delete guard: hard delete hanya saat `status='DRAFT'`. Selain itu → set `is_active=0`.

---

## 2. cust_customer (header)

```sql
-- DB: tm2mst
DROP TABLE IF EXISTS `cust_customer`;
CREATE TABLE `cust_customer` (
  `customer_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code`     VARCHAR(50)  NOT NULL                COMMENT 'BP ID, unik, mis. CUST-2026-0892',
  `customer_name`     VARCHAR(200) NOT NULL                COMMENT 'Nama entitas hukum / individu',
  `entity_type`       ENUM('COMPANY','INDIVIDUAL') NOT NULL DEFAULT 'COMPANY' COMMENT 'Perusahaan / individu (dari mockup)',
  `trade_type`        ENUM('LOCAL','IMPORT')       NOT NULL DEFAULT 'LOCAL'   COMMENT 'eks customer_type lama; IMPORT mengaktifkan incoterms',
  `tax_code`          VARCHAR(50)  NULL DEFAULT NULL       COMMENT 'NPWP',
  `mr_ms`             VARCHAR(5)   NULL DEFAULT 'Mr'       COMMENT 'Bapak/Ibu',
  `industry_sector`   VARCHAR(50)  NULL DEFAULT NULL       COMMENT 'Sektor industri (label bebas / ref)',
  `email`             VARCHAR(150) NULL DEFAULT NULL       COMMENT 'Email tagihan',
  `phone`             VARCHAR(50)  NULL DEFAULT NULL,
  `website`           VARCHAR(150) NULL DEFAULT NULL,
  -- Penjualan / logistik (SAP-isme dibuang; sisa yg relevan)
  `salesperson_id`    BIGINT UNSIGNED NULL DEFAULT NULL    COMMENT 'FK logikal -> emp_employee (account manager)',
  `incoterms`         VARCHAR(10)  NULL DEFAULT NULL       COMMENT 'DDP|FOB|CIF; hanya relevan trade_type=IMPORT',
  `currency_id`       INT UNSIGNED NULL DEFAULT NULL       COMMENT 'FK logikal -> fin_currency.id_currency',
  -- Accounting (WAJIB)
  `ar_account_code`   VARCHAR(30)  NULL DEFAULT NULL       COMMENT 'Recon AR account. FK logikal -> fin_coa (future/Finance)',
  `payment_term`      VARCHAR(30)  NULL DEFAULT NULL       COMMENT 'NET30|NET60|2/10NET30 (enum sementara -> ref_payment_term)',
  `payment_method`    ENUM('BANK_TRANSFER','CHEQUE','GIRO','CASH') NULL DEFAULT 'BANK_TRANSFER',
  `tax_group`         ENUM('PPN_11','EXEMPT')     NULL DEFAULT 'PPN_11' COMMENT 'Kena PPN 11% / bebas (kawasan berikat)',
  -- Credit management
  `credit_limit`      DECIMAL(18,4) UNSIGNED NULL DEFAULT 0 COMMENT 'Batas maksimum piutang',
  `current_balance`   DECIMAL(18,4) UNSIGNED NULL DEFAULT 0 COMMENT 'Saldo piutang berjalan (di-maintain modul Finance)',
  `credit_hold`       ENUM('NONE','AUTO_HOLD','MANUAL_HOLD') NOT NULL DEFAULT 'NONE',
  `risk_class`        ENUM('A','B','C')           NOT NULL DEFAULT 'B' COMMENT 'A=low B=medium C=high',
  -- Status
  `status`            ENUM('DRAFT','ACTIVE','REVIEW','INACTIVE') NOT NULL DEFAULT 'DRAFT',
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `is_prospect`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=prospek dari inkuiri, belum resmi',
  -- Audit
  `created_at`        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`        VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`        VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`customer_id`) USING BTREE,
  UNIQUE INDEX `uq_customer_code` (`customer_code` ASC) USING BTREE,
  INDEX `idx_cust_status`   (`status` ASC, `is_active` ASC) USING BTREE,
  INDEX `idx_cust_prospect` (`is_prospect` ASC, `is_active` ASC) USING BTREE,
  INDEX `idx_cust_currency` (`currency_id` ASC) USING BTREE,
  INDEX `idx_cust_sales`    (`salesperson_id` ASC) USING BTREE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master customer (header). Alamat & kontak di cust_address / cust_contact.';
```

## 3. cust_address (1:N — billing/shipping/branch)

```sql
DROP TABLE IF EXISTS `cust_address`;
CREATE TABLE `cust_address` (
  `address_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`  BIGINT UNSIGNED NOT NULL              COMMENT 'FK fisik -> cust_customer',
  `address_type` ENUM('BILLING','SHIPPING','BRANCH') NOT NULL DEFAULT 'SHIPPING',
  `label`        VARCHAR(100) NULL DEFAULT NULL        COMMENT 'Nama cabang, mis. "BCA KCP Cikarang"',
  `street`       TEXT         NULL                     COMMENT 'Jalan/gedung',
  `city_id`      BIGINT UNSIGNED NULL DEFAULT NULL     COMMENT 'FK logikal -> geo_city.id_city',
  `post_code`    VARCHAR(20)  NULL DEFAULT NULL,
  `is_default`   TINYINT(1)   NOT NULL DEFAULT 0        COMMENT '1=alamat default utk tipenya',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`   VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`   VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`address_id`) USING BTREE,
  INDEX `idx_addr_customer` (`customer_id` ASC, `address_type` ASC) USING BTREE,
  INDEX `idx_addr_city`     (`city_id` ASC) USING BTREE,
  CONSTRAINT `fk_addr_customer` FOREIGN KEY (`customer_id`)
      REFERENCES `cust_customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alamat customer 1:N. BILLING pusat + banyak SHIPPING/BRANCH (kasus BCA).';
```
> Aturan aplikasi: maksimal **1** `is_default=1` per (customer_id, address_type) — enforce di
> Eloquent observer, bukan MySQL (MySQL 8 tak punya partial unique index).

## 4. cust_contact (1:N — PIC)

```sql
DROP TABLE IF EXISTS `cust_contact`;
CREATE TABLE `cust_contact` (
  `contact_id`  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL              COMMENT 'FK fisik -> cust_customer',
  `contact_name` VARCHAR(200) NOT NULL,
  `position`    VARCHAR(100) NULL DEFAULT NULL,
  `email`       VARCHAR(150) NULL DEFAULT NULL,
  `phone`       VARCHAR(50)  NULL DEFAULT NULL,
  `is_primary`  TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`  VARCHAR(100) NULL DEFAULT NULL,
  `updated_by`  VARCHAR(100) NULL DEFAULT NULL,
  PRIMARY KEY (`contact_id`) USING BTREE,
  INDEX `idx_contact_customer` (`customer_id` ASC) USING BTREE,
  CONSTRAINT `fk_contact_customer` FOREIGN KEY (`customer_id`)
      REFERENCES `cust_customer` (`customer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kontak/PIC customer 1:N. Maks 1 is_primary per customer (enforce app-level).';
```

## 5. VIEW metrics (derived, non-master)

```sql
-- Kerangka; JOIN aktual menyusul saat co_*/do_*/fin_* tersedia. Jangan blokir DDL master.
DROP VIEW IF EXISTS `v1_customer_metrics`;
CREATE VIEW `v1_customer_metrics` AS
SELECT c.customer_id,
       0 AS open_sales_orders,     -- COUNT co_order status berjalan
       0 AS total_invoiced,        -- SUM fin AR
       (c.credit_limit - c.current_balance) AS remaining_credit
FROM `cust_customer` c;
```

---

## 6. PETA UI mockup → field (Tier 4)

Layar = `master_customer_mockup.html`. **Ganti token ke standar TMERP**: navy `#2c3e69`
(`--navy`), accent `#2f6fed` (`--accent`); buang `#2563eb`. Layout form customer = **TERPUSAT**
(`max-width`+`mx-auto` OK, mode form), **bukan** full-page. Badge lock/selector per §konvensi global.

| Elemen UI (mockup) | Field / aksi | Catatan |
|---|---|---|
| Radio Company/Individual | `entity_type` | ubah label nama saat Individual |
| Nama Pelanggan | `customer_name` | |
| BP ID (readonly) | `customer_code` | auto-generate, locked 🔒 |
| NPWP | `tax_code` | |
| Sektor Industri | `industry_sector` | |
| Email/Telepon/Website | `email`/`phone`/`website` | |
| Tab **Alamat** Billing/Shipping | `cust_address` rows | repeater; tombol +Cabang → BRANCH |
| Tab **Penjualan**: Account Mgr, Currency, Incoterms | `salesperson_id`,`currency_id`,`incoterms` | **buang** Sales Org & Dist Channel |
| Tab **Akuntansi**: Recon Acct, Payment Term, Method, Tax Group | `ar_account_code`,`payment_term`,`payment_method`,`tax_group` | Recon Acct = input teks sampai `fin_coa` ada |
| Tab **Kredit**: Limit, Hold, Risk | `credit_limit`,`credit_hold`,`risk_class` | `current_balance` readonly (Finance) |
| Status pipeline Draft/Aktif/Review/Non-Aktif | `status` | Draft→Active transisi |
| Smart metrics (3 kartu) | `v1_customer_metrics` | **derived**, readonly, jangan simpan sbg kolom |

---

## 7. Migrasi data `m_customer` → `cust_customer`

| Lama (`m_customer`) | Baru | Transform |
|---|---|---|
| `customer_code/name/tax_code/mr_ms/email/phone/website` | idem | copy |
| `customer_type` (local/import) | `trade_type` | UPPER |
| `default_address` + `default_id_area` | `cust_address` (BILLING, is_default=1) | 1 baris per customer |
| `contact_name` | `cust_contact` (is_primary=1) | 1 baris |
| `payment_term/currency_code` | `payment_term` / `currency_id` | map currency_code→id via fin_currency |
| `credit_limit/current_balance/is_active/is_prospect` | idem | copy |
| — | `entity_type` | default COMPANY |
| — | `status` | is_active=1 → ACTIVE, else INACTIVE |

Setelah migrasi: `RENAME TABLE m_customer TO m_customer_olddata;` (arsip, jangan DROP).

---

## 8. Task Claude Code (urut)
1. DDL §2–§5 → file `TIER_3A_CUSTOMER.sql` (DB `tm2mst`).
2. Migration Laravel + Eloquent: `Customer`, `CustomerAddress`, `CustomerContact` (hasMany).
3. Observer: enforce single `is_default` per (customer,type) & single `is_primary`.
4. Livewire CRUD (layout terpusat) sesuai peta §6; token TMERP.
5. Skrip migrasi data §7 (idempotent), lalu rename tabel lama.
6. Update `sales_state.yaml` / STATE master: catat customer master LOCKED.

## 9. OPEN (butuh keputusan lanjut, TIDAK memblokir DDL)
- `fin_coa` (Chart of Account) — recon account jadi FK logikal saat Finance dibangun.
- `ref_payment_term` & `ref_industry_sector` — jadikan lookup bila daftar mengeras.
- JOIN aktual `v1_customer_metrics` — saat `co_*`/`do_*`/`fin_*` siap.
