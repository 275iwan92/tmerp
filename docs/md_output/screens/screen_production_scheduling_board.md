---
name: screen_production_scheduling_board
description: MPS Gantt Chart -- drag/resize/reassign task produksi per mesin/manual, plus dialog Interruption "Sisip Hot Job" dengan opsi reroute subkontrak.
---

# Scheduling Board (MPS - Gantt Chart)

**File**: `resources/views/pages/production/scheduling-board.blade.php`
**Route**: `production.scheduling-board` (`/production/scheduling-board`)
**Koneksi DB utama**: prd (tm2prd) — model `PrdWo`, `PrdWoOperation`, `PrdCrewAssignment`; mst (tm2mst) — `ProdMachine`, `OrgDivision`, `EmpShiftRoster`, `CustCustomer`, `SupSupplier`; service `App\Services\Scheduling\PdrEngine`, `InterruptionOptionGenerator`, `App\Services\Pur\SubcontractPoService`
**Modul**: Production Scheduling & Product Development

## Tujuan
Papan Gantt harian (`mockup_scheduling_board.html`, `production_scheduling_tier4.md` §3) — baris = mesin (divisi OFFSET) atau operator manual, blok = task (`prd_wo_operation`). Drag/resize/reassign PERSIST langsung ke DB (bukan sekadar mockup). Gantt itu sendiri diekstrak jadi komponen reusable `resources/views/components/gantt/{board,row,task-block}.blade.php` — halaman ini HANYA meresolve data domain (WO/mesin/proses) ke bentuk row/block generik; komponen sendiri tidak tahu apa itu WO/proses. Fitur andalan: **Interruption Dialog** ("Sisip Hot Job", Tier4 §4) untuk menyisipkan pekerjaan mendadak, termasuk opsi reroute ke subkontraktor. Aktor: PPIC.

## Field & Kolom
Toolbar: date-picker + shift ±1 hari, filter Mesin (`filterMachine`), filter Proses (`filterProcess`), toggle Mode (Simulasi/Live — **murni tampilan**, Simulation Layer sungguhan belum dibangun), tombol "🔥 Sisip Hot Job", tombol "Submit ke Master Schedule" (placeholder, belum aktif). Stat-row 4 kotak (klik untuk sorot blok di Gantt via Alpine store): Total Task Aktif, Belum Terjadwal, Berisiko Telat (critical_ratio<1), Mesin Kosong >2 jam.

Gantt: baris mesin (label = nama mesin, sublabel = crew aktif) + baris manual (per operator), blok task dengan warna per proses (Cetak/Laminasi/Plong/Packing/default abu), progress bar dalam blok, ikon 🔥 (hot job) / ⚠ (breakdown) / 🔍 (QC hold), blok QC_HOLD dashed & terkunci (tak bisa digeser).

**Interruption Dialog** ("Sisip Hot Job"): pilih WO (`hotWoId`, kandidat = WO dengan task MACHINE PENDING belum terjadwal & belum hot job), Sumber (`hotSource`: DIREKSI/CUSTOMER/INTERNAL_REWORK), Customer (autocomplete, hanya jika Sumber=CUSTOMER), lalu "Cari Opsi Penyisipan" menampilkan daftar opsi radio (aman=hijau, berisiko=kuning, subkontrak=biru). Form Reroute Subkontrak (muncul jika opsi butuh PO subkontrak): `subVendorId` (autocomplete supplier `is_subcon=true`), `subEstimatedCost`, `subDueOut`, `subDueReturn`.

## Aksi / Method
**Data & render**: `machines()`, `processOptions()` [Computed]; `operationsForDay()`, `buildRows()`, `stats()`, `board()` (satu titik akses tunggal dari Blade, hindari query berulang) — menghasilkan `machineRows`/`manualRows`/`stats`. `operationVm()` (map operation ke VM generik untuk komponen Gantt), `idleGapsVm()`, `crewLabel()`, `processColor()`, `statusLabel()` — semua "pengetahuan domain" yang sengaja tidak ditaruh di komponen Gantt generik.

**Navigasi**: `shiftDate(deltaDays)`, `setMode(mode)` (tampilan saja), `notYetAvailable(feature)` (toast placeholder).

**Drag/resize/reassign** (WAJIB lewat `PdrEngine::validateManualReschedule()` sebelum commit — sejak revisi 2026-08-28, sebelumnya method ada tapi tidak pernah dipanggil dari board):
- `moveOperation(operationId, newStartHour)` — geser waktu mulai, pertahankan durasi.
- `resizeOperation(operationId, newDurationHour)` — ubah durasi (finish baru).
- `reassignOperation(operationId, resourceKey, processName)` — ganti mesin/operator & nama proses; blok QC_HOLD ditolak (terkunci); kalau assign ke manual (`resourceKey` prefix `e`), update `PrdCrewAssignment` role OPERATOR_UTAMA.

**Interruption Dialog**:
- `openHotJobDialog()`/`closeHotJobDialog()`/`cancelSubcontractForm()`.
- `updatedHotWoId($value)` — lifecycle hook, cari task MACHINE PENDING belum terjadwal di WO terpilih.
- `selectHotCustomer(id)`/`clearHotCustomer()`.
- `generateHotJobOptions()` — panggil `PdrEngine::backwardSchedule($wo)` (belum di-persist) untuk hitung target_start/finish task hot job, lalu `InterruptionOptionGenerator::generateOptions()` untuk bangun opsi penyisipan.
- `applyHotJobOption()` — terapkan opsi non-subkontrak: hot job ambil alih slot task yang digeser (right-shift lokal, BUKAN backward-schedule ulang total); set `is_hot_job=true`, `hot_job_source`, geser task yang displaced maju sejumlah `impact_hours`; catat keputusan via `InterruptionOptionGenerator::recordDecision()`. Jika opsi butuh subkontrak → tampilkan form (`hotShowSubcontractForm`), tidak langsung apply.
- `vendorResults()` [Computed], `selectVendor(id)`, `clearVendor()`.
- `confirmRerouteSubcontract()` — guard role `isOpsManagerOrAbove()`; validasi field lengkap; panggil `SubcontractPoService::createFromReroute()` untuk terbitkan PO Subkontrak nyata; catat keputusan; antrean mesin existing TIDAK digeser (beda dari opsi non-subkontrak).

**Helper**: `resourceOptions()` — dropdown operator manual HANYA berisi karyawan yang ter-roster (`emp_shift_roster`) di tanggal papan ini (query ulang tiap kali tanggal berganti, Tier4 §6 wajib), plus semua mesin.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdWo`, `PrdWoOperation`, `PrdCrewAssignment` → tm2prd.
- `App\Models\Master\ProdMachine`, `OrgDivision`, `EmpShiftRoster`, `CustCustomer`, `SupSupplier` → tm2mst.
- `App\Services\Scheduling\PdrEngine` — validasi & backward-schedule.
- `App\Services\Scheduling\InterruptionOptionGenerator` — generate & catat opsi penyisipan hot job.
- `App\Services\Pur\SubcontractPoService` — terbit PO Subkontrak nyata (lintas modul ke Procurement).

## Alur / Business Logic Penting
- **1 data, banyak tampilan**: board ini, MPS Kanban (`monitoring-dashboard.blade.php`), dan MPS Spreadsheet (`mps-spreadsheet.blade.php`) semua baca/tulis `prd_wo_operation` yang sama via `PdrEngine`.
- **Interruption Dialog** (Tier4 §4): scope disepakati — pilih WO EXISTING yang belum terjadwal, BUKAN form bikin WO baru on-the-spot (supaya tidak perlu ALTER enum `prd_wo.source_type` yang cuma punya CO_CONFIRMED/MTS_FORECAST).
- Opsi non-subkontrak = right-shift lokal (task lain mundur sejumlah jam dampak) — BUKAN backward-schedule ulang total.
- Opsi Reroute Subkontrak = HOT TASK itu sendiri dialihkan ke subkontraktor, antrean mesin existing sama sekali tidak disentuh; butuh approval role Manager Operasional ke atas.
- **F15 Production Output Recording** terintegrasi: `operationVm()` pakai `outputProgressPercent()` (qty baik aktual/target) untuk progress blok begitu ada catatan output nyata, fallback ke `progressPercent()` berbasis waktu sebelum ada catatan.
- **SENGAJA belum ada**: backward-scheduling otomatis/PDR auto-assign saat board dibuka (hanya jalan lewat Interruption Dialog), Simulation Layer penuh (toggle Mode murni tampilan, belum ada sandbox terpisah), tombol "Submit ke Master Schedule" masih placeholder.

## Catatan
- Ditemukan & diperbaiki bareng bug `WoGenerationService` yang menghitung backward-schedule tapi tidak pernah menyimpannya.
- `#[Url] $date` mendukung deep-link dari WO Detail ("Lihat di Scheduling Board" via `?date=YYYY-MM-DD`).
- Warna proses & label status adalah "pengetahuan domain Production Scheduling" — sengaja dipisah dari komponen Gantt generik supaya komponen bisa dipakai modul lain (mis. Schedule Maintenance, disebutkan sebagai rencana ke depan).
