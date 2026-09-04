---
name: db_inv
description: Skema database Inventory (stok RM/SFG/FG, kartu stok, layer FIFO/LIFO, booking material, periode) modul tm2inv
---

# Database: tm2inv (koneksi Eloquent: `inv`)

**Sumber DDL**: `docs/ddl/tm2inv.sql`
**Jumlah tabel**: 16

## Daftar Tabel

### `fg_stock`
Header movement stok Finished Goods (FG). Dikonfirm -> trigger otomatis isi `fg_stockcard`.
- PK: `doc_id`
- Kolom: `doc_no` (unik), `doc_date`, `doc_type` (tinyint: IN=1 dari produksi, OUT=-1 ke customer), `doc_cat` (BB|STOCK|ADJ|RETURN|OPNAME), `ref_doc_type` (default MANUAL), `ref_doc_id`, `ref_doc_no`, `warehouse_id`, `issued_to_dept`, `process_code`, `reason_code`, `attachment_path`, `product_id` (FK -> tmmst.m_product, SKU induk dokumen), `notes`, `stat_confirm` (0=draft,1=confirmed), `stat_cancel`, `stat_closed`, `post_date`, `period_id`, `flow_status` (default DRAFT), `posted_by`, `posted_at`, `opn_start`/`opn_stop`/`opn_officer`/`opn_witness` (data opname), `full_scope`, `create_by`/`update_by`/`create_log`/`update_log`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `product_id` -> tm2mst.m_product (komentar DDL masih pakai nama lama `tmmst`), `period_id` -> `inv_period`

### `fg_stock_d`
Detail movement stok FG per produk/komponen.
- PK: `doc_detail_id`
- Kolom: `doc_id`, `seq_no`, `product_id` (FK -> tmmst.m_product), `parts_id` (FK -> tmmst.m_product_parts, NULL utk produk single-part), `qty`, `qty_returned`, `qty_system`, `qty_physical`, `action_type`, `return_condition`, `source_detail_id`, `uom_code`, `lot_no`, `unit_price` (dasar hitung FIFO/LIFO/AVG, tidak ditampilkan), `qty_unitprice`, `reference_id`, `reference_name`, `location_id`, audit kolom (`create_by`/`update_by`/`create_log`/`update_log`)
- FK internal: -
- Kemungkinan FK logis lintas-DB: `product_id`/`parts_id` -> tm2mst.m_product/m_product_parts (catatan: `m_product_parts` di komentar DDL, TBD apakah masih sama nama dgn `prod_part` versi baru CLAUDE.md)

### `fg_stockcard`
Kartu stok FG per periode -- diisi otomatis oleh trigger saat `fg_stock` confirm.
- PK: `doc_id`
- Kolom: `previous_doc_id` (chain audit), `doc_date`, `product_id`, `parts_id`, `doc_type` (BB/IN/OUT/ADJ), `location_id`, `warehouse_id`, `lot_no`, `qty`, `uom_code`, `reference_detail_id` (FK -> fg_stock_d.doc_detail_id), `qty_bb/in/out/adj/eb` (saldo awal/masuk/keluar/adj/akhir), `post_date`, audit kolom, `value_bb/in/out/adj/eb`, `avg_unitprice`, `period_id`, `c_sku_key` (GENERATED STORED: concat(product_id,'-',parts_id), kunci saldo FG)
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

### `fg_stockmst`
Master stok FG per periode. **Komentar DDL: "BELUM TENTU TERPAKAI"**.
- PK: `doc_id`
- Kolom: `period_id`, `product_id`, `parts_id`, `location_id`, `warehouse_id`, `lot_no`, `uom_code`, `qty_bb/in/out/adj/eb`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

### `inv_layer_consumption`
Jejak konsumsi layer stok (FIFO/LIFO) -- baris ini dicatat tiap kali sebuah `inv_stock_layer` dipakai/dikonsumsi OUT.
- PK: `consumption_id`
- Kolom: `i_layer_id`, `i_doc_detail_id`, `i_reference_name`, `i_qty`, `i_unit_cost` (snapshot harga layer saat dikonsumsi), `c_value` (i_qty * i_unit_cost), `created_by`, `created_at`
- FK internal: `i_layer_id -> inv_stock_layer` (RESTRICT)
- Kemungkinan FK logis lintas-DB: -

### `inv_period`
Periode akuntansi/tutup buku inventory.
- PK: `period_id`
- Kolom: `i_period_code` (unik, format YYYYMM), `i_date_start`, `i_date_end`, `i_status` (OPEN|CLOSING|CLOSED), `c_closed_by`, `c_closed_at`, `i_notes`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

### `inv_stock_layer`
Layer stok utk metode costing FIFO/LIFO -- 1 baris = 1 batch masuk dengan harga & sisa qty tersendiri.
- PK: `layer_id`
- Kolom: `i_stock_class` (RM|SFG|FG), `i_item_id`, `i_location_id`, `i_warehouse_id`, `i_lot_no`, `i_qty_in`, `c_qty_remaining` (dikurangi saat OUT sesuai FIFO/LIFO), `i_unit_cost`, `i_uom_code`, `i_received_at` (dasar urutan FIFO/LIFO), `i_reference_id`, `i_reference_name`, `c_is_exhausted`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `i_item_id` -> tm2mst (m_raw_material/m_product tergantung i_stock_class)

### `rm_booking`
Header booking/reservasi bahan baku -- **belum menyentuh ledger stok** (murni reservasi).
- PK: `booking_id`
- Kolom: `booking_no` (unik, format 26BK00001), `booking_date`, `booking_type` (MANUAL | WO -- generate dari prd_wo_material), `warehouse_id` (FK -> tmmst.m_warehouse), `wo_id` (FK -> tmprd.prd_wo, opsional), `notes`, `stat_confirm` (0=draft/Simpan Draft, 1=terkunci/reservasi aktif), `stat_cancel`, `stat_closed`, `confirm_by`, `confirm_date`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `warehouse_id` -> tm2mst.m_warehouse, `wo_id` -> tm2prd.prd_wo

### `rm_booking_d`
Detail kebutuhan material per baris booking, dgn dukungan substitusi & tracking ke PR.
- PK: `booking_detail_id`
- Kolom: `booking_id`, `seq_no`, `co_id`/`co_detail_id` (FK -> tmsdi.s_co/.s_co_detail, denormalisasi filter -- catatan: nama tabel lama, cek ulang vs `co_order`/`co_order_item` versi baru), `product_id` (snapshot dari CO detail), `material_id` (FK -> tmmst.m_raw_material), `uom_code`, `location_id` (FK -> tmmst.m_wh_loc), `qty_need`, `qty_book` (dipesan dari stok tersedia), `qty_purchase` (kekurangan -> dibeli), `qty_replacement`, `qty_issued` (realisasi keluar, dari rm_stock_d saat issue), `material_replace_id`, `uom_replace`, `replace_ratio`, `replace_reason`, `substitution_id` (FK -> tmprd.rawmat_substitution_recommendation, rekomendasi MCDM), `pr_detail_id` (FK -> tmpur.p_requisition_d -- catatan: nama tabel lama, versi baru kemungkinan `pr_line`), `note_detail`, `stat_cancel`, audit kolom
- FK internal: `booking_id -> rm_booking` (CASCADE)
- Kemungkinan FK logis lintas-DB: `co_id`/`co_detail_id` -> tm2sdi.co_order/co_order_item, `material_id`/`material_replace_id` -> tm2mst.m_raw_material, `pr_detail_id` -> tm2pur.pr_line, `substitution_id` -> tm2prd

### `rm_stock`
Header movement stok Raw Material (RM) -- dokumen paling kompleks di modul ini, ada approval flow bertingkat.
- PK: `doc_id`
- Kolom: `doc_no` (unik), `doc_date`, `doc_type` (IN=1/OUT=-1), `doc_cat` (BB|STOCK|ADJ|RETURN|OPNAME), `ref_doc_type` (PV|WO|OUT|MANUAL), `ref_doc_id`, `ref_doc_no`, `wo_id` (LOGICAL -> tm2prd.prd_wo), `wo_no`, `warehouse_id`, `issued_to_dept`, `process_code`, `reason_code` (wajib utk AD/OP/RETURN, daftar di sys_constant INV_REASON_*), `attachment_path`, `notes`, `stat_confirm`, `stat_confirm_plan`, `stat_cancel`, `stat_closed`, `post_date`, `period_id`, `flow_status` (DRAFT|SUBMITTED|APPROVED_L1..Ln|REVISION_NEEDED|POSTED|CANCELLED), `posted_by`, `posted_at`, `opn_start/stop/officer/witness`, `full_scope`, audit kolom, `stat_auth` (DRAFT/WAITING/AP_1/RJ_1), `auth_module` (OP atau AD), `current_approval_level`, `current_approval_login`, `doc_status` (text keterangan)
- FK internal: -
- Kemungkinan FK logis lintas-DB: `wo_id` -> tm2prd.prd_wo

### `rm_stock_d`
Detail movement stok RM per bahan baku, dgn dukungan substitusi/self-referencing untuk retur & parent-child.
- PK: `doc_detail_id`
- Kolom: `doc_id`, `seq_no`, `material_id` (FK -> tmmst.m_raw_material), `worm_id` (LOGICAL -> tm2prd.prd_wo_material), `qty`, `qty_returned`, `uom_code`, `qty_required`, `qty_available`, `qty_booked`, `qty_system` (snapshot, tidak dibaca ulang saat posting), `qty_physical`, `action_type` (ISSUE/SUBSTITUTE/PURCHASE), `return_condition` (GOOD|SCRAP, hanya doc_cat=RETURN), `source_detail_id` (FK self, baris OUT yg diretur), `parent_detail_id` (FK self -> rm_stock_d, baris kebutuhan induk), `co_detail_id` (FK CO detail dari tmsdi), `unit_price`, `qty_unitprice`, `lot_no`, `reference_id`, `reference_name`, `location_id`, audit kolom
- FK internal: `parent_detail_id -> rm_stock_d` (self-reference, CASCADE)
- Kemungkinan FK logis lintas-DB: `material_id` -> tm2mst.m_raw_material, `worm_id` -> tm2prd.prd_wo_material, `co_detail_id` -> tm2sdi.co_order_item

### `rm_stock_d_bak`
Tabel backup/legacy dari `rm_stock_d` (nama eksplisit `_bak`) -- struktur mirip tapi field beda (mis. `material_replacement_id` bukan `material_replace_id`), tanpa PRIMARY KEY aktif (doc_detail_id NOT NULL DEFAULT 0, bukan AUTO_INCREMENT).
- PK: - (tidak ada PRIMARY KEY dideklarasikan)
- Kolom: mirip `rm_stock_d` versi lama: `doc_detail_id`, `doc_id`, `material_id`, `worm_id`, `qty`, `uom_code`, `qty_required`, `qty_available`, `qty_booked`, `qty_replacement`, `material_replacement_id`, `action_type`, `parent_detail_id`, `co_detail_id`, `unit_price`, `qty_unitprice`, `lot_no`, `reference_id`, `reference_name`, `location_id`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: -
- **Catatan**: kemungkinan tabel arsip/dead code dari migrasi skema RM sebelumnya, bukan tabel aktif transaksi. Tidak direkomendasikan dipetakan ke Model Eloquent baru.

### `rm_stockcard`
Kartu stok RM per periode -- diisi otomatis oleh trigger saat `rm_stock` confirm.
- PK: `doc_id`
- Kolom: `previous_doc_id` (chain audit trail), `doc_date`, `material_id`, `doc_type` (BB/IN/OUT/ADJ), `location_id`, `warehouse_id`, `lot_no`, `qty` (signed: + masuk, - keluar), `uom_code`, `reference_detail_id` (FK -> rm_stock_d.doc_detail_id atau p_receive_d.doc_detail_id), `reference_name` (isi literal `'tm2inv.rm_stock_d'` atau `'tm2pur.pv_line'`), `qty_bb/in/out/adj/eb`, `post_date`, audit kolom, `value_bb/in/out/adj/eb`, `avg_unitprice`, `period_id`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `reference_name`+`reference_detail_id` -> polymorphic ke tm2pur.pv_line atau rm_stock_d (dalam-DB)

### `sfg_stock`
Header movement stok Semi-Finished Goods (SFG/WIP) -- dari produksi, dikonfirm -> trigger isi `sfg_stockcard`.
- PK: `doc_id`
- Kolom: `doc_no` (unik, bisa nomor WO/SPK), `doc_date`, `doc_type` (IN=1/OUT=-1), `doc_cat` (BB|STOCK|ADJ|OPNAME|CANCEL -- WO dibatalkan), `ref_doc_type`, `ref_doc_id`, `ref_doc_no`, `product_id` (FK -> tmmst.m_product, SKU induk), `wo_id` (FK -> tmprd.prd_wo), `warehouse_id`, `issued_to_dept`, `process_code`, `reason_code`, `attachment_path`, `notes`, `stat_confirm`, `stat_cancel`, `stat_closed`, `post_date`, `period_id`, `flow_status`, `posted_by`, `posted_at`, `opn_start/stop/officer/witness`, `full_scope`, audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `product_id` -> tm2mst.m_product, `wo_id` -> tm2prd.prd_wo

### `sfg_stock_d`
Detail movement stok SFG per komponen produk (part/badan/tutup dst).
- PK: `doc_detail_id`
- Kolom: `doc_id`, `seq_no`, `parts_id` (FK -> tmmst.m_product_parts, badan/tutup/komponen lain), `product_id` (denormalisasi dari m_product_parts.product_id), `qty`, `qty_returned`, `qty_system`, `qty_physical`, `action_type`, `return_condition`, `source_detail_id`, `uom_code`, `lot_no`, `unit_price`, `qty_unitprice`, `reference_id`, `reference_name`, `location_id`, `cancel_reason` (diisi jika doc_cat=CANCEL), audit kolom
- FK internal: -
- Kemungkinan FK logis lintas-DB: `parts_id`/`product_id` -> tm2mst

### `sfg_stockcard`
Kartu stok SFG per periode -- diisi otomatis oleh trigger saat `sfg_stock` confirm.
- PK: `doc_id`
- Kolom: `previous_doc_id`, `doc_date`, `parts_id`, `product_id` (denormalisasi), `doc_type` (BB/IN/OUT/ADJ/CANCEL), `location_id`, `warehouse_id`, `lot_no`, `qty`, `uom_code`, `reference_detail_id` (FK -> sfg_stock_d.doc_detail_id), `qty_bb/in/out/adj/eb`, `post_date`, audit kolom, `reference_name`, `value_bb/in/out/adj/eb`, `avg_unitprice`, `period_id`
- FK internal: -
- Kemungkinan FK logis lintas-DB: -

## Catatan
- Konvensi kolom di modul ini **beda** dari standar CLAUDE.md §3 terbaru: pakai `create_by`/`update_by`/`create_log`/`update_log` (bukan `created_by`/`created_at`/`updated_by`/`updated_at`) untuk sebagian besar tabel `*_stock`/`*_stockcard`/`*_stockmst`/`rm_booking*`, sementara tabel `inv_period`/`inv_stock_layer`/`inv_layer_consumption` sudah pakai standar baru `created_at/by`+`updated_at/by`. Kemungkinan sisa migrasi V1->V2 yang belum diseragamkan -- perlu klarifikasi/keputusan user sebelum menyentuh tabel `*_stock` di kode baru.
- Status flag: sebagian sudah pakai `stat_confirm`/`stat_cancel`/`stat_closed` (sesuai DEC-FIELDNAME-STATUS), tapi ini prefix `stat_` untuk flag numeric tinyint (0/1) yang fungsinya beda dari `status` varchar/`flow_status` varchar yang juga ada di tabel sama (`rm_stock` punya keduanya: `stat_confirm` tinyint DAN `flow_status` varchar DAN `stat_auth` varchar -- tumpang tindih 3 mekanisme status, TBD mana yang aktif dipakai kode PHP).
- Komentar DDL banyak merujuk nama skema lama `tmmst`/`tmsdi`/`tmprd`/`tmpur` (tanpa "2") -- ini peninggalan V1/draft awal V2, BUKAN referensi ke database V1 yang sungguhan terpisah; secara semantik tetap merujuk tm2mst/tm2sdi/tm2prd/tm2pur versi permanen.
- Nama tabel lintas-DB yang disebut di komentar kadang tidak match nama final (`s_co`/`s_co_detail` vs `co_order`/`co_order_item`, `p_requisition_d` vs `pr_line`, `m_product_parts` vs kemungkinan `prod_part`) -- ini gap dokumentasi lama, cek App\Models sungguhan sebelum asumsi nama tabel tujuan FK logis.
- `rm_stock_d_bak` kemungkinan besar tabel arsip peninggalan refactor skema RM detail, tidak punya PK aktif -- jangan dipetakan ke Model Eloquent kecuali dikonfirmasi masih dipakai.
- 3 keluarga movement (RM/SFG/FG) sangat simetris strukturnya (header+detail+stockcard, kadang +stockmst) -- pola stock ledger yang konsisten across stock class, sesuai catatan memory `project_inventory_3_stock_classes.md` (switch via view `v_inv_movement`).
- `inv_stock_layer`+`inv_layer_consumption` adalah mekanisme costing FIFO/LIFO generik lintas RM/SFG/FG (`i_stock_class` enum) -- terpisah dari struktur `*_stockcard` per-kelas di atas.
