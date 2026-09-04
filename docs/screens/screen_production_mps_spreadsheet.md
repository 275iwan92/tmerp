---
name: screen_production_mps_spreadsheet
description: MPS ala Excel/Google Sheets -- DataTables+KeyTable, edit sel langsung (Mesin/Operator/Est Mulai/Selesai) via PdrEngine, filter AutoFilter per kolom.
---

# MPS - Spreadsheet

**File**: `resources/views/pages/production/mps-spreadsheet.blade.php`
**Route**: `production.mps-spreadsheet` (`/production/mps-spreadsheet`)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdWoOperation`, `PrdCrewAssignment`; mst (tm2mst) — `ProdMachine`, `EmpEmployee`; service `App\Services\Scheduling\PdrEngine`
**Modul**: Production Scheduling & Product Development

## Tujuan
UI/UX kompatibel dengan kebiasaan lama PPIC pakai Excel/Google Sheets untuk kelola MPS (permintaan user 2026-08-28) — edit langsung di sel + filter per kolom (dropdown di header, ala Excel AutoFilter). **BUKAN sumber data baru** — baca/tulis `prd_wo_operation` yang SAMA dengan WO Detail/Gantt/Kanban ("1 data, 4 tampilan"). Dibangun pakai DataTables 3 + KeyTable. Aktor: PPIC.

`griduistandard.md` (stat box pastel/avatar+nama) **sengaja tidak dipakai** — ini eksplisit UI lama gaya spreadsheet, bukan grid standar aplikasi.

## Field & Kolom
Tabel DataTables (`#mps-sheet`), kolom: WO No (link buka WO Detail via modal iframe), Customer, Produk/Part, Proses, Mesin (editable double-click), Operator (editable), Qty, Target/Jam, Est. Mulai (editable), Est. Selesai (editable), Status (badge), Due Date. Baris filter ke-2 di header: dropdown exact-match untuk Proses/Mesin/Operator/Status (ala Excel AutoFilter), teks bebas untuk kolom lain (WO No/Customer/Produk).

Hanya task dengan `task_status` PENDING/ASSIGNED (`EDITABLE_STATUSES`) yang bisa diedit — task RUNNING/BREAKDOWN dst dikelola lewat WO Detail ("Lapor Breakdown").

## Aksi / Method
- `operations()` [Computed] — PrdWoOperation, exclude DONE/QC_PASSED, with `wo.coOrder.quotation.customer`, `wo.product`, `machine`, `crew.employee`, order by `target_start IS NULL, target_start ASC`, limit 1000.
- `machines()`, `employeeOptions()`, `processNames()` [Computed] — sumber dropdown filter & edit.
- `editableOrFail(operationId)` — guard: tolak kalau status bukan PENDING/ASSIGNED, dispatch toast.
- `updateStart(operationId, value)` / `updateFinish(operationId, value)` — hitung ulang start/finish sambil pertahankan durasi (untuk updateStart), lalu panggil `applyManualReschedule()`.
- `applyManualReschedule(op, newStart, newFinish)` — panggil `PdrEngine::validateManualReschedule()` (cek konflik crew/durasi minimum/lock QC_HOLD) sebelum commit; return array kecil (`startLabel`/`finishLabel`) untuk di-patch Alpine tanpa reload.
- `updateMachine(operationId, machineId)` — dua jalur: (a) task BELUM terjadwal → assign mesin lalu `PdrEngine::backwardScheduleAndPersist()` (fallback estimasi sederhana jika gagal), transisi ke ASSIGNED; (b) task SUDAH terjadwal → `validateManualReschedule()` dulu, waktu TIDAK dihitung ulang otomatis (PPIC putuskan manual).
- `updateOperator(operationId, employeeId)` — validasi manual reschedule (kalau task sudah terjadwal), lalu `PrdCrewAssignment::updateOrCreate` (role `OPERATOR_UTAMA`) atau hapus assignment kalau `employeeId` null.

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdWoOperation` → tm2prd.
- `App\Models\Prd\PrdCrewAssignment` → tm2prd (PK per `operation_id`+`crew_role`).
- `App\Models\Master\ProdMachine`, `EmpEmployee` → tm2mst.
- `App\Services\Scheduling\PdrEngine` — sama dipakai Gantt/Kanban.

## Alur / Business Logic Penting
- **Engine MPS (PdrEngine) tetap satu-satunya jalur tulis**, kontekstual sama seperti Gantt/Kanban: task belum terjadwal → `backwardScheduleAndPersist()`; task sudah terjadwal + edit manual → `validateManualReschedule()` dulu.
- Tabel di-render SEKALI oleh Livewire lalu `wire:ignore` — DataTables mengelola DOM sendiri; sel editable memakai `$wire.call()` dari Alpine (komponen `mpsRow` per baris), BUKAN `wire:model`/full re-render, supaya tidak bentrok dengan wrapper DOM DataTables.
- Pola `<template x-if>` (bukan `x-show`) dipakai untuk select/input editable — supaya DataTables tidak ikut membaca opsi tersembunyi saat sort/search.
- **Keterbatasan disengaja**: assign mesin ke task belum terjadwal bisa reschedule SELURUH WO (baris lain) via PdrEngine, tapi baris lain itu TIDAK auto-update tampilannya — user diberi toast untuk klik "🔄 Refresh" (state DataTables dipertahankan via `stateSave`, localStorage).
- WO Detail dibuka sebagai popup iframe modal (bukan navigasi biasa) — supaya tombol Back browser tidak masalah; menutup modal = reload halaman spreadsheet penuh supaya perubahan dari WO Detail langsung terlihat.
- SENGAJA tidak pakai extension DataTables Editor (yang ikut ter-bundle) — supaya validasi/persist tetap 100% lewat method Livewire yang sama gayanya dengan WO Detail/Gantt/Kanban, bukan jalur validasi kedua yang bisa menyimpang.

## Catatan
- Filter kolom Mesin punya opsi khusus "— Manual —" untuk task non-mesin.
- Komentar kode menegaskan berulang kali bahwa ini bukan sumber data baru — auditor harus treat 4 layar (WO Detail/Gantt/Kanban/Spreadsheet) sebagai 1 kebenaran data.
