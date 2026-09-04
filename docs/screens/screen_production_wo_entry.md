---
name: screen_production_wo_entry
description: WO Detail — layar kerja utama Work Order (Master Card, Bill of Process interaktif, Bill of Material, QC Gate, Breakdown/Subcontract, Production Output F15)
---

# WO Detail (Work Order)

**File**: `resources/views/pages/production/wo-entry.blade.php`
**Route**: `production.wo.entry` (dgn `{wo}`) dan `production.wo.new` (tanpa `{wo}`, entry point "Add New WO" — modal Plan Delivery langsung terbuka)
**Koneksi DB utama**: prd (tm2prd) — `prd_wo`, `prd_wo_operation`, `prd_crew_assignment`, `prd_breakdown_event`, `prd_wo_operation_output(_defect)`. Query lintas-DB read-only ke tm2mst (`prod_process`, `prod_machine`, `vw_master_card`, `prod_part`, `prod_part_artwork`, `prod_part_attachment`, `prod_mastercard_signoff`), tm2sdi (`iqp_part_material`, `iqp_layout`, `co_order_delivery`), tm2inv (`v_rm_available`).
**Modul**: Production Scheduling & Product Development

## Tujuan
Layar kerja utama 1 Work Order — dipakai PPIC/Operator/QA/Manajer Operasional. Deviasi sadar dari spec Tier4 (tombol "Buka" dari Worklist WO selalu ke sini dulu, bukan langsung Scheduling Board, karena Simulation Layer belum dibangun). Juga jadi entry point pembuatan WO baru (`production.wo.new`, modal picklist Plan Delivery). 3 tab: Master Card (kartu induk read-only), Bill of Process (routing interaktif — HANYA editable selagi status DRAFT/SIMULATED, prinsip snapshot/beku), Bill of Material (read-only, kalkulasi Qty Kebutuhan/Tersedia/Harus Dibeli).

## Field & Kolom
- Header aksi: `updatePriority` (NORMAL/URGENT/SUPER_URGENT).
- Bill of Process per baris (`PrdWoOperation`): `process_name`, `resource_type` (MACHINE/MANUAL), `machine_id`, `rate_band_value` (target/jam, bisa "Ambil Default" dari `prod_part_process_machine_rate`/`_employee_rate`), operator (crew `OPERATOR_UTAMA`), `target_start`/`target_finish` (override cepat via dblclick).
- Modal Breakdown & Subcontract: `breakdownDowntimeMinutes`, `rerouteChoice` (MACHINE_ALT/SUBCONTRACT), `altMachineId`, vendor search (`subVendorId/Name/Query`), `subDueOut`/`subDueReturn`, `subEstimatedCost`.
- Modal Catat Output (F15): `outputQtyGood`, `outputDefectRows[]` (multi-tipe reject/waste dalam 1 entri: `category`, `scrap_type_id`, `qty`), `outputShiftNote`, `outputNotes`.
- Modal QC Reject: `qcRejectReason` (wajib min 5 karakter).
- Modal Plan Delivery: picklist `eligibleDeliveries` (CO CONFIRMED, delivery status PLANNED, belum pernah digenerate WO).

## Aksi / Method
- `pickPlanDelivery()` → `WoGenerationService::generateFromDelivery()`, redirect ke WO baru atau list kalau >1 WO.
- Bill of Process: `addProcessRow()`, `removeProcessRow()` (hanya kalau task masih PENDING), `updateOperationField()`, `refillTargetDefault()` (manual, tidak otomatis — supaya tidak menimpa override PPIC), `updateOperator()`, `updateOperationDateTime()`.
- QC Gate: `qcPass()` (hanya QA, `Auth::user()->isQa()`), `openQcReject()`/`submitQcReject()`, `reworkFromReject()` — auto-transisi WO ke COMPLETED kalau semua operation DONE/QC_PASSED.
- Breakdown & Subcontract: `openBreakdown()`/`submitBreakdownReport()` (buat `PrdBreakdownEvent`, transisi task ke BREAKDOWN) → `rerouteToMachine()` ATAU `createSubcontractPo()` (hanya Manager Operasional ke atas, via `SubcontractPoService`) → `receiveFromSubcontract()` (transisi ke QC_HOLD, wajib QC ulang).
- Production Output (F15): `openOutputEntry()`, `addDefectRow()`/`removeDefectRow()`, `saveOutputEntry()` (wajib qty_good>0 ATAU minimal 1 baris defect valid).
- `openInSchedulingBoard()` — redirect ke Scheduling Board kalau ada operation terjadwal.

## Tabel & Model yang Disentuh
`PrdWo`, `PrdWoOperation` (+ `transitionTo()` state machine), `PrdCrewAssignment`, `PrdBreakdownEvent`, `PrdWoOperationOutput`/`PrdWoOperationOutputDefect`, `PrdScrapType` (reject()/waste() scope). Lintas-DB read-only: `IqpPartMaterial`/`IqpLayout`/`CoOrderDelivery` (tm2sdi), `ProdProcess`/`ProdMachine`/`OrgDivision`/`SupSupplier`/`EmpEmployee`/`ProdPartProcessMachineRate`/`ProdPartProcessEmployeeRate` (tm2mst), plus raw query builder ke `vw_master_card`/`prod_part`/`prod_part_artwork`/`prod_part_attachment`/`prod_mastercard_signoff` (tm2mst) dan `v_rm_available` (tm2inv).

## Alur / Business Logic Penting
- **Bill of Process hanya editable selagi DRAFT/SIMULATED** (`canEditRouting()`) — snapshot/beku begitu commit jadwal real.
- **Qty Kebutuhan BOM dibagi up_count** dari `iqp_layout` CONFIRMED (fix 2026-09-02, BACKLOG §6) — bukan lagi 1:1 qty WO mentah; fallback up_count=1 kalau imposition belum dikonfirmasi.
- **Master Card ambil baris PERTAMA** dari `vw_master_card` per product_id — keterbatasan sadar untuk produk multi-part (part_code cuma teks snapshot, tak ada FK balik ke `prod_part` spesifik).
- **Artwork Master Card**: coba `prod_part_artwork` resmi dulu, fallback ke `prod_part_attachment` JPEG dari ProDev kalau belum ada artwork resmi.
- QC Gate & Breakdown/Subcontract mengikuti state machine `PrdWoOperation::TRANSITIONS` (diperluas sesi 2026-09-03 utk fitur reroute-subkontrak "Sisip Hot Job").
- Gotcha window.top/window.parent utk tombol Back — dijelaskan panjang di komentar kode (halaman ini bisa dibuka sbg tab shell biasa ATAU popup nested dari mps-spreadsheet.blade.php, beda level iframe beda perilaku reload).

## Catatan
Beberapa gap disebutkan eksplisit di komentar kode: BOM belum penuh MRP/qty_factor per proses (tetap gap, lihat delta doc §3); part_id FK asli `iqp_part`→`prod_part` belum ada (dipakai jembatan product_id+part_code teks apa adanya).
