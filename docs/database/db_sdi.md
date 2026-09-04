---
name: db_sdi
description: Database sales-domain (tm2sdi) — Inquiry Product (iqp_*), Costing HPP, Quotation, Customer Order (co_order), delivery plan.
---

# Database: tm2sdi (koneksi Eloquent: `sdi`)

**Sumber DDL**: `docs/ddl/tm2sdi.sql`
**Jumlah tabel**: 22

## Daftar Tabel

### `co_order`
Header Customer Order. 1 CO = 1 Quotation (locked 2026-08-20).
- PK: `id_order`
- Kolom: `id_order`, `co_no` (unique), `co_date`, `quotation_id` (unique — 1:1 ke quotation), `customer_po_no`, `ship_area_id` (FK logis lintas-DB), `ship_area_level` (VILLAGE/DISTRICT/CITY), `ship_address_label`, `ship_address_detail`, `ship_postal_code`, `discount_doc_pct` (snapshot dari iqp_inquiry, locked), `discount_qty_pct` (editable), `doc_status` (DRAFT/CONFIRMED), `notes`, `created_at/by`, `updated_at/by`
- FK internal: `quotation_id -> quo_quotation`
- Kemungkinan FK logis lintas-DB: `ship_area_id` -> tm2mst.geo_village/geo_district/geo_city (level ditentukan `ship_area_level`)
- Catatan: `quotation_id` saat ini NOT NULL — CO Mode #2 (manual tanpa Quotation) BELUM bisa dibuat tanpa migration (lihat CLAUDE.md §4).

### `co_order_delivery`
Baris rencana kirim (CPDEL/Customer Plan Delivery) per item PRODUCT. Auto-generate saat CO confirm; Split/Gabung via `split_from_id` self-FK.
- PK: `id_delivery`
- Kolom: `id_delivery`, `order_id`, `item_id` (FK -> co_order_item, harus PRODUCT), `shipment_type` (FULL/PARTIAL), `ship_date`, `ship_qty`, `status` (PLANNED/SHIPPED), `split_from_id` (self-FK, baris asal Split), `created_at/by`, `updated_at/by`
- FK internal: `order_id -> co_order`, `item_id -> co_order_item`, `split_from_id -> co_order_delivery` (self-referencing)

### `co_order_external_cost`
Biaya eksternal per CO (Trucking/Expedition/Lainnya).
- PK: `id_cost`
- Kolom: `id_cost`, `order_id`, `category`, `description`, `nominal`, `tax_treatment` (TAXABLE/REIMBURSEMENT), `created_at/by`, `updated_at/by`
- FK internal: `order_id -> co_order`

### `co_order_item`
Baris item CO — snapshot dari `quo_quotation_item` saat CO lahir (bukan live-linked), atau manual (`quotation_item_id` NULL).
- PK: `id_item`
- Kolom: `id_item`, `order_id`, `line_seq`, `quotation_item_id` (FK logis, NULL kalau manual), `line_kind` (PRODUCT/SERVICE), `inquiry_id` (FK -> iqp_inquiry, traceability, nullable), `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, `subtotal`, `due_date`, `created_at/by`, `updated_at/by`
- FK internal: `order_id -> co_order`, `inquiry_id -> iqp_inquiry`

### `iqp_costing`
Header costing HPP (MTO). RAHASIA (Sisi A). Publish via approve Direksi.
- PK: `id_costing`
- Kolom: `id_costing`, `doc_no` (unique, format HPP{YY}{NNNNN}), `doc_date`, `inquiry_id` (unique — 1:1), `calc_type` (NEW/RECALC/REVISE, immutable), `i_dim_panjang_mm`, `i_dim_lebar_mm`, `i_dim_tinggi_mm`, `i_lidah_lem_mm`, `total_material_cost_pc`, `total_process_cost_pc`, `total_finishing_cost_pc`, `c_tooling_cost_order`, `doc_status` (DRAFT/CALCULATED/PENDING_APPROVAL/PUBLISHED/FAILED), `rounding_mode`, `ppn_mode` (PPN/NON_PPN), `ppn_pct`, `currency_code`, `calc_notes`, `published_flag` (1=terbuka ke Sisi B/Sales), `is_approved` (approval Direksi = publish), `approved_by/at`, `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`
- Catatan: `calc_type` saat ini hardcode `'NEW'` di kode aplikasi (CLAUDE.md §4).

### `iqp_costing_part`
Detail costing per part (baru tipe DC yang didukung engine). Kolom prefix `i_`=input manual, `c_`=hasil kalkulasi engine (read-only).
- PK: `id_costing_part`
- Kolom: `id_costing_part`, `costing_id`, `part_id` (FK -> iqp_part, opsional), `no_urut`, `part_name`, `part_type_code` (FK logis -> prod_part_type, default DC), `qty_per_set`, `i_dim_panjang_mm`, `i_dim_lebar_mm`, `i_dim_tinggi_mm`, `grammage_id` (FK logis -> mat_grammage), `sheet_rate_id` (FK logis -> mat_sheet_rate), `supplier_sheet_code`, `flute_type`, `i_lidah_lem_mm`, `i_isi_per_lb_kerja`, `i_ongkos_print_pc`, `i_ongkos_diecut_pc`, `i_ongkos_slitter_pc`, `i_ongkos_slotter_pc`, `i_ongkos_join_pc`, `i_ongkos_partisi_pc`, `i_ongkos_layer_pc`, `i_ongkos_qc_pack_pc`, `i_ongkos_kirim_pc`, `c_panjang_sheet_cm`, `c_lebar_sheet_cm`, `c_isi_trim`, `c_lebar_trim_cm`, `c_running_meter`, `c_discount_rm`, `c_sheet_cost_pc`, `c_print_cost_pc`, `i_dc_persen_override`, `i_panjang_plano_cm`, `i_lebar_plano_cm`, `i_cut_per_plano`, `i_isi_per_lb`, `i_jumlah_warna`, `i_ongkos_cetak_rim`, `i_ongkos_plong_lb`, `i_ongkos_lem_pc`, `i_film_faktor`, `i_film_rate`, `i_pisau_faktor`, `i_pisau_rate_rp`, `i_ongkos_plate_warna`, `c_area_cetak_cm2`, `c_jumlah_cetak_lc`, `c_jumlah_bahan_lb`, `c_paper_cost_pc`, `c_cetak_cost_pc`, `c_plong_cost_pc`, `c_lem_cost_pc`, `c_total_coating_pc`, `c_film_cost_order`, `c_plate_cost_order`, `c_knife_cost_order`, `c_tooling_cost_order`, `c_process_cost_pc`, `c_total_part_cost_pc`, `description_calc`, `created_by/at`, `updated_by/at`
- FK internal: `costing_id -> iqp_costing`, `part_id -> iqp_part`
- Kemungkinan FK logis lintas-DB: `grammage_id` -> tm2mst.mat_grammage, `sheet_rate_id` -> tm2mst.mat_sheet_rate

### `iqp_costing_part_coating`
Coating per part, multi-layer berurutan. `index_coating` di-snapshot saat hitung (audit).
- PK: `id_coating`
- Kolom: `id_coating`, `costing_part_id`, `seq`, `coating_type` (WB/UV/varnish/laminating), `index_coating` (snapshot rate), `c_cost_per_lc`, `c_cost_per_pc`, `notes_coating`
- FK internal: `costing_part_id -> iqp_costing_part`

### `iqp_costing_price_tier`
Ladder harga per tier qty. Tooling diamortisasi di sini; parameter di-snapshot saat publish.
- PK: `id_price_tier`
- Kolom: `id_price_tier`, `costing_id`, `tier_id` (FK logis -> iqp_tier), `qty_pcs`, `is_primary`, `tolerance_pct`, `margin_pct` (mark-up: /(1-m)), `rounding_mode` (NEAREST/UP/DOWN), `rounding_step`, `c_tooling_pc`, `c_hpp_pc`, `c_after_tolerance_pc`, `c_after_round_pc`, `c_after_margin_pc` (=harga excl PPN), `c_after_ppn_pc`, `price_per_pcs`, `created_by/at`, `updated_by/at`
- FK internal: `costing_id -> iqp_costing`

### `iqp_costing_state_log`
Jejak transisi `doc_status` costing (audit pipeline).
- PK: `id_state_log`
- Kolom: `id_state_log`, `costing_id`, `from_state`, `to_state`, `changed_at`, `changed_by`
- FK internal: `costing_id -> iqp_costing`

### `iqp_external_cost`
Biaya eksternal (trucking/ekspedisi/jasa luar) per inquiry. Default taxable (masuk DPP).
- PK: `id_external_cost`
- Kolom: `id_external_cost`, `inquiry_id`, `cost_kind` (TRUCKING/EKSPEDISI/JASA_LUAR/LAINNYA), `description`, `amount`, `tax_treatment` (TAXABLE/REIMBURSEMENT), `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`

### `iqp_inquiry`
Header inquiry product (MTO). Sumber kebenaran spec/harga/pajak/state — tabel paling sentral di modul Sales.
- PK: `id_inquiry`
- Kolom: `id_inquiry`, `inquiry_no` (unique, IQ[YY][NNNNN]), `inquiry_date`, `source`, `pic_sales`, `customer_id` (FK logis -> cust_customer, nullable bila prospek), `is_prospect`, `prospect_name`, `deadline_quotation`, `product_draft_name`, `sales_note`, `inquiry_type` (FK -> iqp_type: NEW/RECALC/REVISE), `parent_product_id` (FK logis -> tm2mst.prod_product, utk RECALC/REVISE), `revision_no`, `revise_scope` (CSV: MATERIAL,DIMENSION,ARTWORK,COLOR,PROCESS), `revise_reason`, `material_source` (OWN/CUSTOMER), `job_nature` (PRODUCT/JASA), `cust_material_spec`, `cust_material_eta`, `tax_scheme` (PPN/NON_PPN), `pph_applicable`, `ppn_percent`, `pph_percent`, `discount_doc_type` (PERCENT/AMOUNT), `discount_doc_value`, `discount_qty_value`, `discount_qty_min_ton`, `prove_final_version`, `prove_approved_date` (mulai due-date clock), `due_date` (+14 HK), `design_number`, `co_id` (FK -> co_order, jejak balik), `sub_state` (DRAFT/WAITING_COST/SUBMITTED/PRICE_CALCULATED/PRICE_APPROVED/QUOTED/PROVING/PROVE_APPROVED/CONVERTED/CANCELLED/LOST), `stat_confirm`, `stat_cancel`, `cancel_reason`, `is_approved` (Direksi=publish harga), `approved_by/at`, `created_at/by`, `updated_at/by`
- FK internal: `inquiry_type -> iqp_type`, `co_id -> co_order`
- Kemungkinan FK logis lintas-DB: `customer_id` -> tm2mst.cust_customer, `parent_product_id` -> tm2mst.prod_product

### `iqp_layout`
Layout imposisi per part (1:1), hasil ProDev Engine (`ImpositionEngine.php`).
- PK: `id_layout`
- Kolom: `id_layout`, `part_id` (unique — 1 layout per part), `flat_w_mm`, `flat_h_mm`, `gap_mm`, `plano_size_id` (FK logis -> tm2mst.m_plano_size), `plano_w_mm`, `plano_h_mm`, `gripper_mm`, `margin_mm`, `method` (TEGAK/PUTAR/CAMPUR), `up_count`, `efficiency_pct`, `waste_pct`, `layout_detail` (json), `stat_confirm`, `confirmed_by/at`, `created_at/by`, `updated_at/by`
- FK internal: `part_id -> iqp_part`
- Kemungkinan FK logis lintas-DB: `plano_size_id` -> tm2mst.mat_plano_size

### `iqp_part`
Part mirror inquiry (snapshot dari master `prod_part`). Tiap part punya dieline & bahan sendiri.
- PK: `id_part`
- Kolom: `id_part`, `inquiry_id`, `part_seq`, `part_code`, `part_name` (mis. Body/Lid/Partisi), `part_type` (acuan routing standar), `qty_per_sku`, `cfg_color` (mis. 4/4, 4/0), `cfg_ink`, `dieline_spec` (json, snapshot DSL {canvas,flat,vars,elements}), `flat_w`, `flat_h`, `flat_area`, `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`

### `iqp_part_material`
Layer bahan per part (snapshot). Mendukung multi-layer (mis. duplex+SF).
- PK: `id_part_material`
- Kolom: `id_part_material`, `part_id`, `layer_seq`, `rawmat_id` (FK logis -> mat_raw, snapshot id), `rawmat_name` (snapshot nama), `gram_weight`, `flute_type`, `size_p`, `size_l`, `is_primary`, `material_source` (OWN/CUSTOMER per-layer), `created_at/by`, `updated_at/by`
- FK internal: `part_id -> iqp_part`
- Kemungkinan FK logis lintas-DB: `rawmat_id` -> tm2mst.mat_raw

### `iqp_part_process`
Proses per part, ber-branch & flow (SPLIT/MERGE). Snapshot routing produksi.
- PK: `id_part_process`
- Kolom: `id_part_process`, `part_id`, `branch_code` (MAIN/L/R/BODY/LID), `step_seq`, `flow_type` (LINEAR/SPLIT/MERGE), `process_id` (FK logis -> prod_process, snapshot id), `process_name` (snapshot), `machine_id` (FK logis -> mesin, snapshot id), `qty_factor`, `created_at/by`, `updated_at/by`
- FK internal: `part_id -> iqp_part`
- Kemungkinan FK logis lintas-DB: `process_id` -> tm2mst.prod_process, `machine_id` -> tm2mst (master mesin)

### `iqp_prove`
Prove final per inquiry (1:1). Repeat order = inquiry baru = prove baru.
- PK: `id_prove`
- Kolom: `id_prove`, `inquiry_id` (unique), `design_number`, `final_version`, `pdf_file`, `physical_approved_date` (approval fisik, tanda tangan basah), `is_approved`, `approved_by/at`, `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`

### `iqp_prove_log`
Log versi prove (v1/v2/v3) di bawah prove header.
- PK: `id_prove_log`
- Kolom: `id_prove_log`, `prove_id`, `version_no`, `separation` (CMYK+PMS), `pdf_file`, `note` (sebab revisi/molor), `logged_at`, `logged_by`
- FK internal: `prove_id -> iqp_prove`

### `iqp_tier`
Tier qty inquiry. 1 UTAMA dipaksa via generated-column unique index (`primary_key_guard`). Diskon item per tier.
- PK: `id_tier`
- Kolom: `id_tier`, `inquiry_id`, `tier_seq`, `qty`, `is_primary` (tepat 1 wajib per inquiry), `discount_item_type` (PERCENT/AMOUNT), `discount_item_value`, `price_excl` (snapshot dari costing), `price_incl`, `primary_key_guard` (generated column, `IF(is_primary=1, inquiry_id, NULL)`, dipakai unique index), `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`
- Catatan desain: pola generated-column + unique index untuk memaksa "exactly 1 primary row per inquiry" — pola reusable yang bisa dicontoh tabel lain dengan kebutuhan serupa.

### `iqp_tooling`
Line tooling (PLATE/PISAU/MATRAS/FILM). AUS/TIDAK_ADA -> biaya baru terpisah, tak disembunyikan di harga/pc.
- PK: `id_tooling`
- Kolom: `id_tooling`, `inquiry_id`, `tooling_kind` (PLATE/PISAU/MATRAS/FILM), `tooling_asset_id` (FK logis -> m_tooling_asset), `tooling_status` (ADA/ADA_WARNING/AUS/TIDAK_ADA), `is_new_charge`, `cost`, `remark`, `created_at/by`, `updated_at/by`
- FK internal: `inquiry_id -> iqp_inquiry`
- Kemungkinan FK logis lintas-DB: `tooling_asset_id` -> tm2mst (master tooling asset)

### `iqp_type`
Lookup tipe inquiry. Set once di header, tak berubah.
- PK: `type_code` (varchar: NEW/RECALC/REVISE)
- Kolom: `type_code`, `type_label`, `activate`
- FK internal: tidak ada (di-referensikan oleh `iqp_inquiry.inquiry_type`)

### `quo_quotation`
Header Sales Quotation. Kumpulan InqProd berharga (published) yang dipilih Sales.
- PK: `id_quotation`
- Kolom: `id_quotation`, `quotation_no` (unique), `customer_id` (FK logis -> cust_customer), `revision_no`, `issued_date`, `valid_until`, `payment_term`, `tax_scheme` (EXCL/INCL PPN), `currency_code`, `notes`, `doc_status` (DRAFT/PUBLISHED), `issued_by`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada
- Kemungkinan FK logis lintas-DB: `customer_id` -> tm2mst.cust_customer

### `quo_quotation_item`
Baris item quotation, sumber `source_type` bisa dari tier inquiry, tooling inquiry, atau baris bebas (FREEFORM).
- PK: `id_item`
- Kolom: `id_item`, `quotation_id`, `line_seq`, `source_type` (INQUIRY_TIER/INQUIRY_TOOLING/FREEFORM), `inquiry_id` (FK -> iqp_inquiry, nullable utk FREEFORM), `tier_id` (FK logis -> iqp_tier, tak dipaksa fisik), `description`, `spec_note`, `qty`, `qty_unit`, `unit_price`, `discount_pct`, `subtotal`, `created_at/by`, `updated_at/by`
- FK internal: `quotation_id -> quo_quotation`, `inquiry_id -> iqp_inquiry`

## Catatan
- Prefix domain sesuai CLAUDE.md §3: `iqp_` (Inquiry Product + costing turunan), `quo_` (Quotation), `co_` (Customer Order). Tidak ada tabel `do_` (Delivery Order) di dump ini — modul Delivery/Dispatch dibangun di layar lain (reuse grid `dispatch.do.index`, lihat memory `project_dispatch_spec`), belum tampak sebagai tabel fisik terpisah di tm2sdi per dump ini.
- Rantai FK inti: `iqp_inquiry` -> (`iqp_part` -> `iqp_part_material`/`iqp_part_process`/`iqp_layout`) + (`iqp_costing` -> `iqp_costing_part` -> `iqp_costing_part_coating`, `iqp_costing_price_tier`, `iqp_costing_state_log`) + `iqp_tier`, `iqp_tooling`, `iqp_prove` -> `iqp_prove_log`, `iqp_external_cost`.
- Alur dokumen: `iqp_inquiry` (berharga & published) -> `quo_quotation`/`quo_quotation_item` (Sales pilih & edit) -> `co_order`/`co_order_item` (snapshot saat Quotation PUBLISHED, 1:1 quotation) -> `co_order_delivery` (CPDEL, auto-generate saat CO confirm).
- Banyak kolom `i_*` (input manual officer) vs `c_*` (hasil kalkulasi CostingEngine, read-only) di `iqp_costing`/`iqp_costing_part` — konsisten dengan prinsip "engine suggests, officer confirms".
- Semua tabel transaksi ikut konvensi `created_at/by` + `updated_at/by`; yang ber-approval tambah `approved_by/at`; yang ber-siklus dokumen pakai `stat_confirm`/`stat_cancel` (bukan `is_confirmed`/`is_cancelled`) sesuai DEC-FIELDNAME-STATUS.
- FK lintas-DB (ke tm2mst: `cust_customer`, `prod_product`, `mat_raw`, `mat_grammage`, `mat_sheet_rate`, `mat_plano_size`, `prod_process`, `geo_*`) semuanya LOGIS (tidak ada CONSTRAINT fisik) — dijaga di level Laravel (`exists:mst.table,col`), sesuai aturan lintas-DB CLAUDE.md §2.
