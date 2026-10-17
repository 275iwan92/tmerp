---
name: db_prd
description: Skema database Production (Work Order, operasi/routing, penjadwalan, breakdown, forecast MTS) modul tm2prd
---

# Database: tm2prd (koneksi Eloquent: `prd`)

**Sumber DDL**: `docs/ddl/tm2prd.sql`
**Jumlah tabel**: 6 (+ 1 VIEW) (prd_scheduling_rule dan prd_decision_feedback_log dihapus 2026-10-18)

## Daftar Tabel

### `prd_wo`
Header Work Order produksi -- unit kerja utama modul ini, 1 `iqp_part` = 1 `prd_wo` (sesuai catatan CLAUDE.md di `production_scheduling_wo_generation_delta.md`).
- PK: `wo_id`
- Kolom: `wo_no` (unik, sequence per tahun), `wo_type` (enum MTO/MTS), `source_type` (enum CO_CONFIRMED/MTS_FORECAST), `source_co_id` (LOGICAL -> tm2sdi.co_order, wajib bila MTO), `source_forecast_id` (LOGICAL -> prd_mts_forecast, wajib bila MTS), `product_id` (LOGICAL -> tm2mst.prod_product), `parts_id` (LOGICAL -> tm2mst.prod_part, NULL bila single-part), `qty`, `due_date` (dari CO utk MTO, atau replenishment cycle utk MTS), `material_needed_by` (hasil backward schedule), `wo_status` (enum DRAFT/SIMULATED/SCHEDULED/IN_PROGRESS/ON_HOLD/COMPLETED/CANCELLED), `schedule_layer` (enum SIMULATED/COMMITTED), `is_hot_job`, `hot_job_source` (enum DIREKSI/CUSTOMER/INTERNAL_REWORK), `hot_job_customer_id` (LOGICAL -> tm2mst.cust_customer), `confirmed_at` (FIFO tie-break Composite Priority Score), `cancel_reason`, `created_by`, `created_at`, `updated_by`, `updated_at`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `source_co_id` -> tm2sdi.co_order, `product_id`/`parts_id` -> tm2mst.prod_product/prod_part, `hot_job_customer_id` -> tm2mst.cust_customer

### `prd_wo_material`
Kebutuhan bahan baku per WO, mendukung substitusi material (mis. dead stock/slow moving).
- PK: `detail_id`
- Kolom: `wo_id`, `seq_no`, `material_id` (LOGICAL -> tm2mst.mat_raw, kebutuhan asli sebelum substitusi), `qty_required` (kontrak Inventory: Stock Out tarik dari sini), `qty_standar` (kebutuhan teoritis dari BOM), `is_substituted`, `substitute_material_id` (LOGICAL -> tm2mst.mat_raw), `substitute_reason` (enum DEAD_STOCK/SLOW_MOVING/OTHER), `substitute_approved_by` (wajib minimal Manager Operasional), `substitute_approved_at`, `cutting_yield_waste_qty` (sisa potong, masuk rm_stockcard via Inventory), `material_needed_by`, `created_by/at`, `updated_by/at`
- FK internal: `wo_id -> prd_wo`
- Kemungkinan FK logis lintas-DB: `material_id`/`substitute_material_id` -> tm2mst.mat_raw

### `prd_wo_operation`
Baris operasi/routing per WO -- 1 baris = 1 task di 1 mesin/manual, dengan target & actual timing, status, QC gate.
- PK: `operation_id`
- Kolom: `wo_id`, `seq_no` (urutan routing, dari tm2mst.prod_parts_process), `process_name`, `resource_type` (enum MACHINE/MANUAL), `machine_id` (LOGICAL -> tm2mst.prod_machine, wajib bila MACHINE), `qty`, `target_start`/`target_finish` (hasil backward schedule), `actual_start`/`actual_finish`, `task_status` (enum PENDING/ASSIGNED/RUNNING/DONE/BREAKDOWN/SUBCONTRACT/QC_HOLD/QC_PASSED/QC_REJECTED), `qc_gate_required` (titik kritis routing), `rejection_reason`, `is_hot_job` (salinan dari WO, bisa beda kalau hot job cuma sebagian task), `batched_with_operation_id` (self-FK, Batching Rule), `rate_band_value` (snapshot rate saat backward schedule), `critical_ratio` (computed, refresh tiap re-run PDR), `created_by/at`, `updated_by/at`
- FK internal: `batched_with_operation_id -> prd_wo_operation` (self), `wo_id -> prd_wo`
- Kemungkinan FK logis lintas-DB: `machine_id` -> tm2mst.prod_machine

### `prd_crew_assignment`
Penugasan operator/co-operator ke 1 operasi.
- PK: `assignment_id`
- Kolom: `operation_id`, `employee_id` (LOGICAL -> tm2mst.emp_employee), `crew_role` (enum OPERATOR_UTAMA/CO_OPERATOR), `created_by`, `created_at`
- FK internal: `operation_id -> prd_wo_operation`, unique(`operation_id`,`employee_id`)
- Kemungkinan FK logis lintas-DB: `employee_id` -> tm2mst.emp_employee (atau tm2hrm)

### `prd_breakdown_event`
Log kejadian mesin breakdown pada suatu operasi, dgn opsi reroute (mesin alternatif atau subkontrak).
- PK: `event_id`
- Kolom: `operation_id`, `machine_id` (LOGICAL -> tm2mst.prod_machine), `reported_by`, `reported_at`, `downtime_estimate_minutes`, `reroute_decision` (enum MACHINE_ALT/SUBCONTRACT), `alt_machine_id` (bila MACHINE_ALT), `subcontract_vendor_id` (LOGICAL -> tm2mst.sup_supplier, bila SUBCONTRACT), `subcontract_po_id` (LOGICAL -> tm2pur.po_order/po_header, po_type=SUBCONTRACT -- catatan: nama kolom komentar `po_header`, kemungkinan nama lama utk `po_order`), `subcontract_due_out`, `subcontract_due_return`, `created_at`
- FK internal: `operation_id -> prd_wo_operation`
- Kemungkinan FK logis lintas-DB: `machine_id`/`alt_machine_id` -> tm2mst.prod_machine, `subcontract_vendor_id` -> tm2mst.sup_supplier, `subcontract_po_id` -> tm2pur.po_order

### `prd_mts_forecast`
Forecast Make-To-Stock per produk per periode, dengan tracking akurasi forecast vs actual.
- PK: `forecast_id`
- Kolom: `product_id` (LOGICAL -> tm2mst.prod_product), `period_start`, `period_end`, `forecast_qty`, `actual_qty` (terisi setelah periode berjalan), `accuracy_pct` (1-ABS(forecast-actual)/forecast, target 85-90%), `input_by` (Direksi/Top Management), `created_at`
- FK internal: -
- Kemungkinan FK logis lintas-DB: `product_id` -> tm2mst.prod_product

## VIEW (read-only, sudah live di MySQL)

### `v_prd_exception_dashboard`
Dashboard task `prd_wo_operation` yang belum selesai (task_status NOT IN DONE/QC_PASSED) dgn flag risiko: `is_unscheduled_risk` (PENDING tapi target_start sudah lewat), `is_at_risk` (critical_ratio < 1). Kolom: `operation_id`, `wo_id`, `process_name`, `machine_id`, `target_start`, `target_finish`, `critical_ratio`, `task_status`, `is_unscheduled_risk`, `is_at_risk`.

## Catatan
- Skema ini sudah cukup matang & sinkron dgn deskripsi CLAUDE.md §6 (`production_scheduling_wo_generation_delta.md`) -- 1 `iqp_part` = 1 `prd_wo`, target rate dikunci ke `prod_product`+part_code teks (bukan `prod_part` FK langsung di beberapa tempat, konsisten dgn catatan "prod_part ternyata tak pernah diisi").
- `prd_breakdown_event.subcontract_po_id` komentarnya menyebut `tm2pur.po_header` -- nama tabel PO aktual di tm2pur adalah `po_order` (lihat `docs/database/db_pur.md`), jadi ini kemungkinan sisa penamaan draft lama, bukan tabel terpisah.
- Dua VIEW (`v_prd_*`) valid contoh pola "VIEW read-only di dalam DB tujuan" (CLAUDE.md §2 pola 2) tapi keduanya JOIN/agregasi 100% dalam-DB tm2prd sendiri (tidak lintas-DB) -- jadi bukan contoh cross-DB view, murni view analitik biasa.
- Modul ini konsisten pakai audit kolom standar baru `created_at/by`+`updated_at/by` (bukan gaya lama `create_log`/`update_log` yang dipakai sebagian besar tm2inv) -- menunjukkan tm2prd dibangun lebih baru/konsisten dgn konvensi CLAUDE.md §3 terkini.
- Field `is_hot_job`/`hot_job_source`/`hot_job_customer_id` di `prd_wo` mengindikasikan ada mekanisme prioritas darurat (hot job) yang sudah dimodelkan di skema, sejalan dgn Composite Priority Score yang disebut di `confirmed_at`.
