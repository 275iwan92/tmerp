# Kartu konsep: Customer (sisi Sales)

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtmst`/`ddtsdi`, kode dengan baris). Hanya sisi yang disentuh Sales (Inquiry, Quotation); Customer Order, Invoice, CRM milik kartu orang lain.

- **Modul/sub-modul**: Master Data (layar `md-customer`), dipakai Sales > Inquiry & Quotation.
- **Nama kanonik**: Customer = `tm2mst.cust_customer` (label layar "Master Customer").
- **Sinonim**: pelanggan, buyer, klien, prospect, QC-kode (customer cepat dari Inquiry).
- **BUKAN sama dengan**: *prospect* di Inquiry (`iqp_inquiry.is_prospect` + `prospect_name`, tanpa baris `cust_customer`); Customer 360 (`crm-customer`, hanya baca); supplier.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Master | `cust_customer` 882 baris: 881 `data_origin=LEGACY`, 1 `LIVE` ("PAK PURNA (BLITAR)", `customer_code` NULL). Semua `activate=1`, `approval_status=DRAFT`, `is_prospect=0`, `customer_type='local'`. 713 punya `payment_term`; 8 punya email; 0 punya `sales_division_id`. | FAKTA, query 2026-10-16 |
| Kolom | Identitas (`customer_code` unik, `customer_name`, `trade_name`, `parent_group`, `industry_category`, `nickname`, `alias_code`, `legacy_vendor_code`), kontak dan alamat (`default_address`, `default_area_id/_level`, `phone`, `email`, `contact_name`), keuangan (`payment_term`, `currency_code`, `credit_limit`, `current_balance`, `tax_profile`, `credit_rating`, `credit_check_enabled`, `tax_validated`, `kyc_status`, `approval_status`, `is_blacklisted`), preferensi cetak (`primary_product`, `default_substrate`, `color_standard`, `proofing_method`, `artwork_approval`, `finishing_preference`, `die_owner`, `packing_instruction`, `quality_notes`), `customer_tier` (bawaan 2). 61 kolom. | FAKTA, `show columns` |
| Pipeline | `approval_status`: DRAFT, CREDIT_CHECK, APPROVED, BLOCKED; `CUSTOMER_TIERS` 1 key, 2 regular, 3 occasional ("bobot prioritas penjadwalan produksi, dipegang Sales"). | FAKTA, `CustCustomer.php:12-25` (konstanta); pemakaian tier di penjadwalan BELUM dicek |
| Layar input | `md-customer` = `CustomerGrid` (grid master + form v1 `pages::master.customer`), kolom grid: Customer, Group, Type, Industry, Credit Limit, Term, Approval (BLOCKED bila `is_blacklisted`), jumlah CO. Item menu `Master Customer` ada TIGA kali: modul Master Data (id 511), grup Master Data di Sales > Inquiry & Quotation (id 1143), grup Master Data di Sales > CRM (id 1191). | FAKTA, `CustomerGrid.php:15-79`; `sys_app_menu` |
| Dipakai Inquiry | Lookup `customer` (aktif) di Inquiry New; wajib `exists:tm2mst.cust_customer`. Disimpan `iqp_inquiry.customer_id` (lintas-DB, tanpa FK). | FAKTA, `inquiry-new.blade.php:51-52`, `InquiryPayload.php:24`, `Sales.php:29-33` |
| Customer cepat | Dari Inquiry: `InquiryWriter::quickCustomer` membuat baris `QC`+yymm+4 digit, `approval_status=DRAFT`, `tax_profile=PPN_11`, `credit_rating=C`, `kyc_status=PENDING`, `customer_type` (local/import), `is_prospect=false`. Respons rute: "status DRAFT". Di data: 0 customer berkode `QC%`. | FAKTA, `InquiryWriter.php:157-179`, `routes/v2.php:308-317`; query |
| Dipakai Quotation | `quo_quotation.customer_id` dari inquiry pertama; `payment_term`, `currency_code`, `attn_contact` diisi dari customer (boleh diubah). Quotation hanya menerima inquiry dengan `customer_id` terisi. | FAKTA, `QuotationBuilder.php:59-74,127` |
| Pemakaian di data | `iqp_inquiry`: 19 baris punya `customer_id` (9 customer berbeda), 4 baris prospect tanpa customer. | FAKTA, query |
| Gerbang persetujuan | `approval_status`, `is_blacklisted`, `credit_check_enabled` tidak dibaca oleh Inquiry, Quotation, maupun CO: di `app/` hanya dibaca `CustomerGrid` (tampilan) dan ditulis `quickCustomer`. | FAKTA, `grep` `app/` (`approval_status|is_blacklisted|credit_check_enabled`) |

## Relasi
- **sumber**: layar Master Customer (Sales/Finance), migrasi legacy (881 baris), customer cepat dari Inquiry.
- **dipakai_oleh**: Inquiry, Quotation, Customer Order, Invoice/AR, Delivery, CRM, ProDev (kartu `vw_master_card` memuat nama pelanggan).
- **mengalir**: nilai master dibekukan ke transaksi (A.13): `payment_term` dan `currency_code` disalin ke Quotation, lalu ke CO; perubahan master tidak mengubah dokumen lama. Master hanya diubah user (B.14).
- **zona beku**: bukan; tetapi data Master Customer diubah hanya oleh user.

## Temuan (dicatat, tidak diperbaiki)
1. **Tidak ada customer berstatus APPROVED** (882 DRAFT) dan **tidak ada gerbang** yang menahan Inquiry/Quotation/CO untuk customer DRAFT/BLOCKED/blacklist. Rute customer cepat berkomentar "review Finance/Credit tetap wajib" (`routes/v2.php:308`); langkah pipeline ada di form Master Customer (`CustCustomer::APPROVAL_STATUSES`), tetapi tidak ada kode transaksi yang memeriksanya. Perlu keputusan Jo: gerbang di mana (Quotation Confirm? CO Confirm?), atau cukup tanda.
2. **Tiga item menu yang sama** (`v2:md-customer`) di tiga tempat; A.15b membolehkan pintasan, tetapi tanpa penanda "pintasan" di data menu (kolom `sys_app_menu` tidak punya penanda itu).
3. **Prospect tidak punya jalan ke Quotation**: `is_prospect` di customer selalu 0; prospect Inquiry hanya nama teks (`prospect_name`) dan tidak lolos `availableInquiries`. Jalur "prospect menjadi customer" tidak ditemukan di kode Sales (BELUM dicari di CRM).
4. **Satu customer LIVE tanpa `customer_code`** ("PAK PURNA (BLITAR)"); `customer_code` unik tetapi boleh NULL.
5. **Banyak kolom kaya (preferensi cetak, `die_owner`, dll.)** tidak dibaca layar Sales; pemakainya (ProDev, QA) BELUM dicek, jadi belum bisa ditandai tak terpakai.

## Sudah ada? Dicari di mana, hasil
Dicari: `cust_*` di `ddtmst` (`information_schema`: `cust_customer`, `_bank`, `_branch`, `_contact`, `cust_category`, `cust_receiving_hour`), menu `sys_app_menu`, `grep` `app/Support/V2`, `app/Services/Sales`. Hasil: konsep SUDAH ADA; tidak diperlukan tabel baru.


