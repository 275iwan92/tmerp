# TIER 3A — DAFTAR PREFIX (Naming Convention)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**
Pendekatan ALL-NEW · nama English · prefix per **domain data** (bukan per pemakai).

---

## Aturan dasar
- Prefix = **entitas/domain inti** tabel, bukan "siapa pemakai terbesar".
- Format: `<prefix>_<entitas>_<detail>` (mis. `cust_customer_address`).
- Pengelompokan otomatis via sort alfabet (semua `cust_*` berkumpul).
- **1 tabel = 1 domain = 1 prefix.** Kalau ambigu, prefix ikut **entitas inti**.

---

## A. MASTER — database `tmmst` (netral, shared lintas modul)

| Prefix | Domain | Contoh tabel |
|--------|--------|--------------|
| `cust_` | Customer | cust_customer, cust_group, cust_address, cust_contact, cust_term |
| `prod_` | Product & Proses produksi | prod_product, prod_type, prod_category, prod_bom, prod_process, prod_routing, prod_coating_type, prod_parts_process, prod_cfg_ink |
| `mat_`  | Material & HARGA bahan/proses | mat_raw, mat_paper, mat_paper_grammage, mat_coating_rate, mat_film_rate, mat_sheet_rate, mat_sheet_discount_tier |
| `inv_`  | Inventory | inv_item, inv_warehouse, inv_location, inv_allocation |
| `uom_`  | Unit measure | uom_unit, uom_conversion, uom_group |
| `sup_`  | Supplier | sup_supplier, sup_sheet_supplier, sup_contact |
| `emp_`  | Employee (data orang) | emp_employee, emp_contract, emp_family, emp_document, emp_bank_account, emp_shift |
| `org_`  | Organisasi/perusahaan (struktur) | org_company, org_division, org_department, org_position, org_level |
| `geo_`  | Geografis/wilayah | geo_country, geo_region, geo_city, geo_district, geo_village* |
| `fin_`  | Finance (uang transaksional) | fin_currency, fin_currency_rate, fin_payment_term, fin_tax_scheme |
| `sys_`  | Sistem/parameter | sys_constant, (nanti: sys_config, sys_sequence, sys_setting) |
| `ref_`  | Reference/lookup generik lintas-domain | ref_status, ref_yes_no, ref_calc_type (yang tak punya domain induk) |

> Lookup **ber-domain** nempel ke induknya (mis. `iqp_type`, `prod_coating_type`), BUKAN ke `ref_`. `ref_` hanya untuk lookup generik tanpa domain induk. Prefix `lkp_` **dihapus**.

\* `geo_village` opsional (5-level, aktif bila diputuskan).

---

## B. TRANSAKSI — database `tmsdi` (Sales-domain)

| Prefix | Domain | Contoh tabel | Status |
|--------|--------|--------------|--------|
| `iqp_`  | Inquiry Product (+ costing turunannya) | iqp_inquiry, iqp_part, iqp_layout, iqp_costing, iqp_costing_part, iqp_type, iqp_prove_log | **TERKUNCI** |
| `quo_`  | Quotation | quo_quotation | diusulkan |
| `co_`   | Customer Order | co_order, co_line | diusulkan |
| `do_`   | Delivery Order | do_delivery, do_line | diusulkan |

> Costing transaksi = **iqp_costing** (ikut inquiry induk), BUKAN prefix `cost_`.
> `iqp_` = **inquiry product** (semua inquiry = produk cetak yang di-costing).

---

## C. INFRASTRUKTUR LINTAS-MODUL

| Nama | Fungsi |
|------|--------|
| `doc_state_log` | State log terpadu semua dokumen (polymorphic: doc_type + doc_id) |
| `doc_history`   | Snapshot JSON saat freeze (Skenario B) |
| `master_change_log` | Field-change log master ber-harga (opsional) |

---

## D. KOLOM BAKU (baseline)

**Master:**
```
is_active TINYINT(1) NOT NULL DEFAULT 1
created_at / updated_at / created_by / updated_by
```
Master transaksional (cust_customer, prod_product) WAJIB 4 kolom audit.
Master statis kecil (lkp_) cukup is_active + create/update.

**Transaksi (semua):**
```
created_by / created_at / updated_by / updated_at
```
+ ber-approval: `approved_by` / `approved_at`
+ confirm/cancel: cukup flag `is_confirmed` / `is_cancelled` + `cancel_reason` (pelaku via updated_by).

---

## E. KEPUTUSAN TERKUNCI (rangkuman)
- Flag master = **`is_active`** (bukan is_enable).
- Rate fluktuatif (coating/film/sheet) = **pola versioned + is_active**, hanya **1 baris is_active=1 per jenis**. `effective_date` untuk riwayat; **tanpa end_date**. Proteksi 1-aktif via generated column + unique index (pending konfirmasi).
- Costing lama aman via **snapshot** ke transaksi (bukan baca master).
- Currency↔Country: **geo_country.currency_id → fin_currency** (arah dibalik). tax pindah ke country.
- Prefix **`cost_` DIHAPUS** — tak ada penghuni (semua ke mat_/prod_/ref_).
- `m_constant` → **`sys_constant`** (domain sistem baru).
- **`hr_` dipecah** → `emp_` (data orang) + `org_` (struktur organisasi).
- **`lkp_` → `ref_`**; lookup ber-domain nempel ke induk, `ref_` hanya generik.
- Inquiry → **`iqp_`** (inquiry product), semua turunan ikut.

---

## F. PENDING
1. Prefix transaksi lain (`quo_/co_/do_`) — kunci atau revisi? (`iqp_` sudah terkunci)
2. Proteksi "1 aktif per jenis" rate: generated column+unique index, atau disiplin aplikasi?
3. geo: 4 vs 5 level; cakupan luar negeri; sekali-download vs live-sync.
4. Data legal perusahaan (NPWP/SIUP/akta) — masuk `org_company` atau prefix `legal_` terpisah?
