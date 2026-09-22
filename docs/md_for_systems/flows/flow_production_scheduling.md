---
name: flow_production_scheduling
description: Alur ProDev (imposition) -> Master Card -> WO auto-generate dari CPDEL -> Scheduling Board -> Monitoring, plus master data pendukung
---

# Flow: Production Scheduling & Product Development

Koneksi utama `prd` (tm2prd) untuk WO/scheduling; `mst` (tm2mst) untuk ProDev part/layout (`ProdPart`/`ProdLayoutGroup`). 2 engine berbeda goal dalam 1 bucket modul ini: **ProDev** (cari layout produksi fisik paling hemat bahan dari geometri part) dan **Production Scheduling/PPIC** (jadwalkan & eksekusi WO).

## Tahap -> Layar -> Aktor

| Tahap | Layar | Aktor |
|---|---|---|
| 1. Rancang layout produksi | `screen_prodev_form` (workbench: dimensi part, Engine Tingkat 1/2, Tool #2/#4/#10) | ProDev Officer |
| 2. Lihat worklist part ProDev | `screen_prodev_grid` | ProDev Officer, Supervisor |
| 3. Cetak Master Card | `screen_prodev_print` | ProDev Officer |
| 4. Master data pendukung ProDev | `screen_production_master-process` (proses), `screen_production_master-crew` (SDM), `screen_production_scrap-type` (jenis reject/waste), `screen_production_process-rate` (target rate mesin/pegawai) | PPIC Admin |
| 5. Forecast permintaan | `screen_production_mts-forecast` (MTS) | PPIC/Demand Planner |
| 6. Susun Master Production Schedule | `screen_production_mps-spreadsheet` | PPIC |
| 7. CO Confirm -> CPDEL -> WO auto-generate | (trigger dari modul CO, lihat `flow_sales_order_delivery`) → `screen_production_wo_entry` (`WoGenerationService::generateFromDelivery()`) | Sistem (auto), PPIC review |
| 8. Kelola Bill of Process, QC Gate, Breakdown/Subcontract, Catat Output (F15) | `screen_production_wo_entry` | Operator, QA, Manajer Operasional |
| 9. Jadwalkan ke mesin (drag/resize) | `screen_production_scheduling-board` | PPIC |
| 10. Pantau progres WO real-time | `screen_production_monitoring-dashboard` | PPIC, Manajemen |
| 11. Cetak WO/Kartu Kerja | `screen_production_wo-print`, `screen_production_wo-list` (worklist) | Operator, PPIC |
| 12. Rujuk aturan sequencing/kalender | `screen_production_rule-library` | PPIC Admin |

## Catatan Arsitektur
- WO lahir dari `co_order_delivery` (CPDEL), **1 `iqp_part` = 1 `prd_wo`** — konvergensi antar-part (mis. Badan+Tutup ketemu di 1 titik Packing) sengaja belum dimodelkan.
- Bill of Process (`prd_wo_operation`) hanya bisa diedit selagi WO `DRAFT`/`SIMULATED` — begitu commit ke jadwal real, snapshot/beku (prinsip CLAUDE.md §4).
- Target rate default per Produk+Part+Proses+Mesin/Pegawai dikelola di `screen_production_process-rate`, dipakai `WoGenerationService::refillTargetDefault()` tapi selalu bisa di-override manual per WO.
- ProDev dan Production Scheduling adalah 2 GOAL berbeda dalam 1 bucket dokumentasi ini (lihat CLAUDE.md §4 "3 ENGINE inti") — ProDev fokus geometri/bahan, Scheduling fokus jadwal/eksekusi mesin.
