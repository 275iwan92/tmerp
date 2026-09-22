---
name: screen_production_monitoring_dashboard
description: MPS Kanban Board -- kolom mesin/proses ala Trello, kartu = 1 prd_wo_operation, drag-drop assign mesin via PdrEngine backward-schedule.
---

# MPS - Kanban Board (Monitoring Dashboard)

**File**: `resources/views/pages/production/monitoring-dashboard.blade.php`
**Route**: `production.monitoring` (`/production/monitoring`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdWoOperation`; mst (tm2mst) — `ProdMachine`, `OrgDivision`; service `App\Services\Scheduling\PdrEngine`
**Modul**: Production Scheduling & Product Development

## Tujuan
Papan Kanban visual (Screen #3, dulu bernama "MPS - Cards", direname 2026-08-28 supaya sesuai isinya) — kolom = mesin/proses, kartu = 1 `prd_wo_operation` (BUKAN 1 WO utuh; 1 WO bisa punya beberapa task di mesin berbeda). Drag antar kolom = pindah/assign mesin. Direview via mockup HTML (Claude Artifact) sebelum dibangun. Aktor: PPIC/operator lantai produksi.

3 kelompok kolom:
1. **Antrean** — task MACHINE yang belum punya mesin/jadwal (`target_start` NULL), backlog global lepas dari filter tanggal.
2. **Per-mesin** — ProdMachine aktif divisi OFFSET, difilter tanggal `$date`, kolom merah kalau tak ada task RUNNING/ASSIGNED (mesin kosong/idle).
3. **Manual** — task `resource_type=MANUAL` hari itu, tidak ikut logika kosong/merah karena bukan mesin fisik.

## Field & Kolom
Tidak ada form — murni kartu drag-drop. Setiap kartu: WO No, badge status, nama proses, nama customer, progress bar (qty baik / qty total) jika ada output atau sedang RUNNING. Header: date-picker (`$date`, live), link ke "MPS - Gantt Chart". Stat-row 5 kotak: Total Task Hari Ini, Mesin Kosong, Sedang Jalan, Breakdown/QC Hold, Antrean Belum Terjadwal.

## Aksi / Method
- `mount()` — set `$date = now()`.
- `machines()` [Computed] — ProdMachine aktif divisi OFFSET.
- `queueOperations()` [Computed] — task MACHINE, `target_start` NULL, `task_status=PENDING`, limit 50. **Sengaja bukan dicek dari `machine_id` null** — WoGenerationService sudah pre-fill `machine_id` sebagai saran dari snapshot routing Inquiry, belum "live" sampai PPIC assign `target_start`.
- `machineOperations()` [Computed] — task MACHINE dengan `machine_id` terisi, filter tanggal, group by `machine_id`.
- `manualOperations()` [Computed] — task MANUAL, filter tanggal.
- `machineIsIdle(machineId)` — cek tidak ada task RUNNING/ASSIGNED di mesin itu.
- `statTotalToday()`, `statIdleMachines()`, `statRunning()`, `statAttention()` (BREAKDOWN/QC_HOLD), `statQueue()` [Computed].
- `moveOperation(operationId, machineId)` — inti drag-drop:
  - Tolak jika bukan task MACHINE atau task_status bukan PENDING/ASSIGNED (dispatch toast).
  - `machineId=null` → kembali ke Antrean: reset `target_start`/`target_finish`/`task_status=PENDING`. **`machine_id` sengaja TIDAK dihapus** (akan salah dibaca `RateBandResolver` sebagai task manual — insiden nyata pernah terjadi, ketahuan dari test `test_hot_job_dialog` gagal).
  - `machineId` diisi → set `machine_id`, lalu panggil `PdrEngine::backwardScheduleAndPersist($op->wo)` — menghitung ULANG seluruh urutan task WO ini dari due_date (bukan cuma task yang digeser). Kalau gagal (`RuntimeException`, rate band belum lengkap) → fallback estimasi sederhana (`qty/rate`, minimal 0.5 jam) untuk task ini saja.
  - Transisi `PENDING` → `ASSIGNED` via `$op->transitionTo('ASSIGNED')`.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdWoOperation` → `tm2prd.prd_wo_operation` (PK `operation_id`), relasi `wo.coOrder.quotation.customer` (lintas-DB logis ke tm2sdi/tm2mst).
- `App\Models\Master\ProdMachine`, `OrgDivision` → tm2mst.
- `App\Services\Scheduling\PdrEngine` — engine scheduling tunggal (sama dipakai Gantt Chart & MPS Spreadsheet).

## Alur / Business Logic Penting
- **1 data, 3 tampilan**: Kanban, Gantt (`scheduling-board.blade.php`), dan Spreadsheet (`mps-spreadsheet.blade.php`) semua baca/tulis `prd_wo_operation` yang sama lewat `PdrEngine` — bukan sumber data terpisah.
- Assign dari Antrean TIDAK LAGI pakai estimasi qty/rate sendiri ("quick-assign visual") sejak revisi 2026-08-28 — sekarang selalu memanggil `PdrEngine::backwardScheduleAndPersist()` yang sama dengan Gantt Chart.
- Task lain di WO yang sama BISA ikut pindah tanggal/kolom setelah 1 drag — ini memang tujuan (cascade reschedule), bukan efek samping.
- Hanya task PENDING/ASSIGNED yang bisa digeser drag — task RUNNING/BREAKDOWN dst harus lewat "Lapor Breakdown" di WO Detail.

## Catatan
- Nama historis: "MPS - Cards" (statis) → "MPS - Kanban Board" (drag-drop, versi ini).
- Fallback estimasi sederhana bukan bug tersembunyi — komentar kode eksplisit menjelaskan ini keterbatasan yang disengaja, PPIC diminta lengkapi rate band via Master Data > Target Rate.
