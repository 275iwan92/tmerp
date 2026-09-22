# TIER 3 — PRODUCTION SCHEDULING (PPIC) · DDL, DELTA & VIEW
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Database: **`tm2prd`** (baru, belum ada tabel — `tm2prd.sql` saat ini kosong).
> Prefix transaksi: **`prd_`** — konsisten kontrak yang sudah dirujuk `inventory_rules.yaml`
> (`prd_wo`, `prd_wo_material`). FK lintas-DB **LOGICAL** (Eloquent), bukan constraint MySQL,
> sesuai `globalrules.yaml`.
> Konvensi kolom: `i_` = input, `c_` = computed (tanpa DEFAULT), audit `created_by/at` +
> `updated_by/at`, uang `DECIMAL(18,4)`, dimensi mm `DECIMAL(10,2)`.

---

## 1. `prd_wo` — Work Order (header)

```sql
CREATE TABLE `prd_wo` (
  `wo_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor dokumen, sequence per tahun',
  `wo_type` enum('MTO','MTS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('CO_CONFIRMED','MTS_FORECAST') COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_co_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2sdi.co_header, wajib bila MTO',
  `source_forecast_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK prd_mts_forecast, wajib bila MTS',
  `product_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tmmst.prod_product',
  `parts_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.prod_part, NULL bila single-part',
  `qty` decimal(18,4) NOT NULL,
  `due_date` datetime NOT NULL COMMENT 'Dari CO (MTO) atau replenishment cycle (MTS)',
  `material_needed_by` datetime DEFAULT NULL COMMENT 'Hasil backward schedule — Tier2 §4.1',
  `wo_status` enum('DRAFT','SIMULATED','SCHEDULED','IN_PROGRESS','ON_HOLD','COMPLETED','CANCELLED')
      COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `schedule_layer` enum('SIMULATED','COMMITTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIMULATED',
  `is_hot_job` tinyint(1) NOT NULL DEFAULT '0',
  `hot_job_source` enum('DIREKSI','CUSTOMER','INTERNAL_REWORK') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hot_job_customer_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.cust_customer, isi bila hot_job_source=CUSTOMER',
  `confirmed_at` datetime DEFAULT NULL COMMENT 'Dipakai FIFO tie-break Composite Priority Score (Tier2 §4.2 Lapis 4)',
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wo_id`),
  UNIQUE KEY `uk_prd_wo_no` (`wo_no`),
  KEY `idx_prd_wo_status` (`wo_status`,`schedule_layer`),
  KEY `idx_prd_wo_source_co` (`source_co_id`),
  KEY `idx_prd_wo_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Work Order header — satuan kerja produksi (MTO dari CO, MTS dari forecast)';
```

---

## 2. `prd_wo_material` — Kebutuhan Bahan per WO

> **Kontrak sudah ada** (dirujuk `inventory_tier1-4.md`) — struktur di bawah **melengkapi**, bukan
> mengganti kontrak. Kolom yang sudah dijanjikan Inventory (`wo_id`, `qty_required`) wajib ada.

```sql
CREATE TABLE `prd_wo_material` (
  `detail_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` bigint unsigned NOT NULL COMMENT 'FK prd_wo',
  `seq_no` int unsigned NOT NULL DEFAULT '0',
  `material_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tmmst.mat_raw — kebutuhan ASLI (sebelum substitusi)',
  `qty_required` decimal(18,4) NOT NULL COMMENT 'Kontrak Inventory: Stock Out tarik dari sini',
  `qty_standar` decimal(18,4) NOT NULL COMMENT 'Kebutuhan teoritis dari BOM (utk hitung varian di Inventory)',
  `is_substituted` tinyint(1) NOT NULL DEFAULT '0',
  `substitute_material_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.mat_raw, hasil §5A.10 Tier1',
  `substitute_reason` enum('DEAD_STOCK','SLOW_MOVING','OTHER') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `substitute_approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wajib minimal Manager Operasional (Tier1 §9.4)',
  `substitute_approved_at` datetime DEFAULT NULL,
  `cutting_yield_waste_qty` decimal(18,4) DEFAULT NULL COMMENT 'Sisa potong, masuk rm_stockcard via Inventory',
  `material_needed_by` datetime DEFAULT NULL COMMENT 'Salinan dari prd_wo.material_needed_by, per baris (beda material bisa beda titik pakai)',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`detail_id`),
  KEY `idx_prd_wo_material_wo` (`wo_id`),
  KEY `idx_prd_wo_material_item` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Kebutuhan bahan per WO — output MRP netting, dibaca Inventory utk Stock Out';
```

---

## 3. `prd_wo_operation` — Task (Job Operation)

```sql
CREATE TABLE `prd_wo_operation` (
  `operation_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `wo_id` bigint unsigned NOT NULL COMMENT 'FK prd_wo',
  `seq_no` int unsigned NOT NULL COMMENT 'Urutan routing, dari tmmst.prod_parts_process',
  `process_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_type` enum('MACHINE','MANUAL') COLLATE utf8mb4_unicode_ci NOT NULL,
  `machine_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.prod_machine, wajib bila resource_type=MACHINE',
  `qty` decimal(18,4) NOT NULL,
  `target_start` datetime DEFAULT NULL COMMENT 'Hasil Backward Schedule (Tier2 §4.1)',
  `target_finish` datetime DEFAULT NULL,
  `actual_start` datetime DEFAULT NULL,
  `actual_finish` datetime DEFAULT NULL,
  `task_status` enum('PENDING','ASSIGNED','RUNNING','DONE','BREAKDOWN','SUBCONTRACT',
                      'QC_HOLD','QC_PASSED','QC_REJECTED')
      COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `qc_gate_required` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Titik kritis routing (Tier1 F5)',
  `rejection_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_hot_job` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Salinan dari WO, bisa beda kalau hot job cuma sebagian task',
  `batched_with_operation_id` bigint unsigned DEFAULT NULL COMMENT 'Self-FK — Batching Rule (Tier1 §5A.7)',
  `rate_band_value` decimal(18,4) DEFAULT NULL COMMENT 'Snapshot rate dipakai saat backward schedule (audit)',
  `critical_ratio` decimal(8,4) DEFAULT NULL COMMENT 'Computed, refresh tiap re-run PDR (Tier2 §4.2)',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`operation_id`),
  KEY `idx_prd_wo_op_wo` (`wo_id`,`seq_no`),
  KEY `idx_prd_wo_op_machine` (`machine_id`,`target_start`,`target_finish`),
  KEY `idx_prd_wo_op_status` (`task_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task/Job Operation — unit terkecil yang dijadwalkan (Tier1 §4, §5A)';
```

---

## 4. `prd_crew_assignment` — Crew per Task Mesin

```sql
CREATE TABLE `prd_crew_assignment` (
  `assignment_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_id` bigint unsigned NOT NULL COMMENT 'FK prd_wo_operation',
  `employee_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tm2hrm.emp_employee',
  `crew_role` enum('OPERATOR_UTAMA','CO_OPERATOR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `uk_prd_crew_op_role_utama` (`operation_id`,`crew_role`)
    COMMENT 'Guard aplikasi: hanya 1 OPERATOR_UTAMA per operation; co-operator boleh banyak, dicek di service bukan unique key ini',
  KEY `idx_prd_crew_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Crew (Operator Utama + Co-Operator) per Task mesin — Tier1 §5A.9';
```
> Catatan: `UNIQUE(operation_id, crew_role)` **hanya cocok murni untuk OPERATOR_UTAMA** (memang
> maksimal 1). Untuk CO_OPERATOR (boleh N orang), guard "tidak duplikat orang yang sama di
> operation yang sama" ditambah lewat `UNIQUE(operation_id, employee_id)` terpisah — lihat index
> tambahan di §10 migration note.

---

## 5. `prd_breakdown_event`

```sql
CREATE TABLE `prd_breakdown_event` (
  `event_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_id` bigint unsigned NOT NULL COMMENT 'FK prd_wo_operation, task yang breakdown',
  `machine_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tmmst.prod_machine',
  `reported_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reported_at` datetime NOT NULL,
  `downtime_estimate_minutes` int unsigned DEFAULT NULL,
  `reroute_decision` enum('MACHINE_ALT','SUBCONTRACT') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_machine_id` bigint unsigned DEFAULT NULL COMMENT 'Bila reroute_decision=MACHINE_ALT',
  `subcontract_vendor_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.sup_supplier, bila SUBCONTRACT',
  `subcontract_po_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tm2pur.po_header (po_type=SUBCONTRACT)',
  `subcontract_due_out` datetime DEFAULT NULL COMMENT 'Tanggal kirim ke vendor',
  `subcontract_due_return` datetime DEFAULT NULL COMMENT 'Tanggal wajib kembali',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_prd_breakdown_op` (`operation_id`),
  KEY `idx_prd_breakdown_machine` (`machine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Insiden breakdown mesin + keputusan reroute — Tier1 §5A prinsip #4, Tier2 §7';
```

---

## 6. `prd_scheduling_rule` — Constraint Rule Library

```sql
CREATE TABLE `prd_scheduling_rule` (
  `rule_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rule_type` enum('BATCHING','SEQUENCING') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label mudah dibaca, mis. "Gabung dieline sama"',
  `applies_to_machine_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.prod_machine — NULL = berlaku semua mesin sejenis applies_to_process',
  `applies_to_process` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama proses, mis. CETAK, PLONG',
  `condition_field` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama atribut task/produk yang dibandingkan, mis. dieline_ref, ink_darkness_level',
  `condition_operator` enum('EQUALS','SORT_ASC','SORT_DESC') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_status` enum('DRAFT','ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Wajib diisi utk rule_status=ACTIVE — Manager Produksi/Operasional/Admin App/di atasnya (Tier1 §9.7)',
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`rule_id`),
  KEY `idx_prd_rule_machine` (`applies_to_machine_id`,`rule_status`),
  KEY `idx_prd_rule_type` (`rule_type`,`rule_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rule Library Batching/Sequencing sebagai DATA — Tier1 §5A.7, bukan hardcode';
```

---

## 7. `prd_decision_feedback_log`

```sql
CREATE TABLE `prd_decision_feedback_log` (
  `log_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` enum('INTERRUPTION','SIMULATION_ADJUST') COLLATE utf8mb4_unicode_ci NOT NULL,
  `wo_id` bigint unsigned DEFAULT NULL COMMENT 'FK prd_wo terkait',
  `context_customer_id` bigint unsigned DEFAULT NULL COMMENT 'LOGICAL FK tmmst.cust_customer, utk Customer Interruption Profile',
  `context_machine_id` bigint unsigned DEFAULT NULL,
  `options_presented` json DEFAULT NULL COMMENT 'Snapshot opsi yang ditawarkan sistem',
  `option_chosen` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chosen_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chosen_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_prd_feedback_customer` (`context_customer_id`),
  KEY `idx_prd_feedback_wo` (`wo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log keputusan PPIC — sumber Customer Interruption Profile & kalibrasi bobot (Tier1 §5A.5/§9.6)';
```

---

## 8. `prd_mts_forecast`

```sql
CREATE TABLE `prd_mts_forecast` (
  `forecast_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL COMMENT 'LOGICAL FK tmmst.prod_product',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `forecast_qty` decimal(18,4) NOT NULL,
  `actual_qty` decimal(18,4) DEFAULT NULL COMMENT 'Terisi setelah periode berjalan',
  `accuracy_pct` decimal(6,4) DEFAULT NULL COMMENT 'Computed: 1-ABS(forecast-actual)/forecast; target 85-90%',
  `input_by` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Direksi/Top Management (Tier1 §5A.8)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`forecast_id`),
  KEY `idx_prd_forecast_product_period` (`product_id`,`period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Forecast MTS oleh Direksi + banding actual — Tier1 §5A.8';
```

---

## 9. VIEW BACA

```sql
-- Exception Dashboard (Tier1 F17) — job belum terjadwal, berisiko telat, resource idle
CREATE OR REPLACE VIEW `v_prd_exception_dashboard` AS
SELECT
    o.operation_id, o.wo_id, o.process_name, o.machine_id, o.target_finish,
    o.critical_ratio, o.task_status,
    CASE WHEN o.task_status = 'PENDING' AND o.target_start < NOW() THEN 1 ELSE 0 END AS is_unscheduled_risk,
    CASE WHEN o.critical_ratio < 1 THEN 1 ELSE 0 END AS is_at_risk
FROM prd_wo_operation o
WHERE o.task_status NOT IN ('DONE','QC_PASSED');

-- Customer Interruption Profile (Tier1 F19) — agregat, bukan tabel tersimpan
CREATE OR REPLACE VIEW `v_prd_customer_interruption_profile` AS
SELECT
    context_customer_id,
    COUNT(*) AS total_interruption,
    option_chosen,
    COUNT(*) AS count_per_option
FROM prd_decision_feedback_log
WHERE event_type = 'INTERRUPTION' AND context_customer_id IS NOT NULL
GROUP BY context_customer_id, option_chosen;

-- Dead Stock / Slow Moving candidate untuk Cutting Yield (Tier1 §5A.10)
-- CATATAN: query lintas-DB ke tm2inv.rm_stockcard, dieksekusi di application layer
-- (join lintas server aman — sesuai pola inventory_tier3.md §528), BUKAN sebagai view
-- fisik MySQL cross-database. Placeholder logika:
--   SELECT material_id, gramatur, kualitas, ukuran, MAX(doc_date) AS last_out_date,
--          DATEDIFF(NOW(), MAX(doc_date)) AS days_since_last_out
--   FROM tm2inv.rm_stockcard WHERE movement_type='OUT' GROUP BY material_id
```

---

## 10. MIGRATION NOTE — Tambahan guard yang tak muat di DDL murni

```sql
ALTER TABLE `prd_crew_assignment`
  ADD UNIQUE KEY `uk_prd_crew_op_employee` (`operation_id`,`employee_id`)
  COMMENT 'Cegah 1 orang didaftar dobel (baik sbg utama maupun co-op) di operation yang sama';
```

---

## 11. THRESHOLD & KONSTANTA (isi ke `sys_constant`, bukan hardcode)

| Konstanta | Kegunaan | Nilai default usulan |
|---|---|---|
| `PRD_DEAD_STOCK_DAYS` | Ambang hari tanpa OUT → Dead Stock (Tier1 §5A.10) | 180 |
| `PRD_SLOW_MOVING_RATIO` | Ambang turnover ratio → Slow Moving | Tier 2 lanjutan — butuh data historis dulu |
| `PRD_LEADTIME_MIN_DATAPOINT` | Minimum transaksi sebelum lead time per supplier+material dianggap reliable (Tier1 §9.5) | 5 |
| `PRD_RATEBAND_MIN_DATAPOINT` | Sama, untuk Rate Band historis (§5A.6) | 5 |
| `PRD_FEEDBACK_CALIBRATION_CYCLE` | Kadensi kalibrasi bobot Decision Feedback (§9.6) | WEEKLY |
| `PRD_MTS_ACCURACY_TARGET` | Target akurasi forecast MTS (§5A.8) | 0.85–0.90 |
| `PRD_MTS_REPLENISH_CYCLE_DAYS` | Kadensi replenishment MTS | 2-3×/minggu (mis. Senin/Rabu/Jumat) |

---

## 12. LANGKAH BERIKUTNYA

1. ~~Verifikasi `tmmst.prod_machine`~~ ✅ **sudah ada** (`masteremployee_tier1-4.md` — spesifikasi
   lengkap: jenis, merk/model, tahun, no seri, max kertas, jumlah warna, kecepatan max,
   kapasitas/shift, gramatur min-max). Semua LOGICAL FK ke `prod_machine` di atas **valid**,
   tidak perlu tabel baru.
2. ⚠️ **Temuan cross-module perlu direkonsiliasi** (bukan blocker, tapi harus disadari sebelum
   eksekusi): HRM sudah punya **Shift Roster Board** (`emp_shift_roster.machine_id` +
   `emp_shift_roster_batch`) yang menjadwalkan **siapa hadir di mesin mana per shift/hari**
   (granularitas hari/shift, workforce planning). `prd_crew_assignment` di sini beroperasi di
   granularitas **lebih halus** (siapa jadi Operator Utama/Co-Operator untuk 1 Task/WO Operation
   spesifik, dalam window jam tertentu di dalam shift itu).
   **Rekomendasi**: `prd_crew_assignment` **tidak boleh independen penuh** — saat assign crew ke
   Task, sistem **wajib validasi** orang itu memang ter-roster (`emp_shift_roster`) di mesin &
   tanggal yang sama. Jangan biarkan PPIC assign orang yang tak ter-roster hari itu. Detail
   integrasi (query lintas `tm2hrm`↔`tm2prd`) masuk Tier 2 lanjutan — dicatat di sini supaya
   tidak terlewat saat Claude Code eksekusi.
3. Lanjut **Tier 4** — `production_scheduling_tier4.md`.
