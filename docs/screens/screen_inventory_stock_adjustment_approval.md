---
name: screen_inventory_stock_adjustment_approval
description: Antrian persetujuan dokumen Stock Adjustment & Opname (AD/OP) yang menunggu approval user login, klik 2x membuka dokumen asli untuk Approve/Reject.
---

# Persetujuan Adjustment & Opname

**File**: `resources/views/pages/inventory/stock-adjustment-approval.blade.php`
**Route**: `inv.stock-adj.approval` — `/inventory/stock-adjustment-approval/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Layar antrian murni (grid saja, tanpa form) yang menampilkan dokumen Stock Adjustment (AD) dan Stock Opname (OP) yang sedang menunggu keputusan approval dari user yang login, digabung jadi 1 antrian per kelas stok. Klik 2x membuka dokumen ASLI di `stock-adjustment.blade.php` (deep-link via param `{doc}`) — Approve/Reject dan riwayat approval ada di sana, TIDAK diduplikasi di layar ini. Sama pola dengan PR/PO Approval Center di modul Procurement.

## Field & Kolom
Filter: `search` (cari no. dokumen / `reason_code`, debounce 300ms).

Kolom grid: Dokumen (identity-cell: no. dokumen + `reason_code`) | Jenis (Opname/Adjustment dari `doc_cat`) | Diajukan (`pending->acted_at`) | Posisi Approval (`pending->pending_label`) | Nilai Absolut (`pending->amount_snapshot`) | Status (badge statis "MENUNGGU") | Aksi (Buka).

Statistik ringkas (2 stat box): Total di Antrian Saya, Nilai Absolut Total.

## Aksi / Method
- `mount(string $category)` — validasi category (RM/SFG/FG)
- `pendingQueue()` [Computed] — gabungan `FlowEngineService::pendingForUser('AD', Auth::id())` + `pendingForUser('OP', Auth::id())`
- `documents()` [Computed] — header dokumen (`RmStock`/`FgStock`/`SfgStock` sesuai category) untuk semua `doc_id` di `pendingQueue`
- `rows()` [Computed] — gabungan pending+doc, filter dokumen yang ternyata tidak ditemukan, filter `search`, sort by `pending->acted_at` desc
- `statTotal()`/`statValue()` [Computed] — jumlah antrian & total nilai absolut
- `openDoc(int $id)` — redirect ke `inv.stock-adj.index` dengan param `category`+`doc` (deep-link ke form)

## Tabel & Model yang Disentuh
- `App\Services\Flow\FlowEngineService` — sumber antrian pending (baca `tm2mst.flow_log`/`flow_config`/`flow_config_step`/`flow_position_acting`, lintas-DB dari tm2inv)
- `App\Models\Inventory\RmStock` / `FgStock` / `SfgStock` (tm2inv, tabel `rm_stock`/`fg_stock`/`sfg_stock`) — header dokumen AD/OP dipilih via const `HEADER_MODEL`
- Lintas-DB: `FlowEngineService` beroperasi di tm2mst, header dokumen di tm2inv — dicocokkan di PHP (bukan JOIN lintas koneksi).

## Alur / Business Logic Penting
- Modul flow `AD` (Adjustment) dan `OP` (Opname) digabung jadi 1 antrian tampilan, meski secara flow config keduanya berbeda kode modul.
- Grid non-master: Aksi = Buka saja (tidak ada Confirm langsung di grid ini — keputusan Approve/Reject wajib lewat form `stock-adjustment.blade.php`, konsisten `griduistandard.md`: grid non-Master = Buka + Confirm kondisional, di sini Confirm-nya "dipindah" ke halaman detail karena butuh alasan penolakan).
- Avatar helper dipindah ke `App\Support\Grid\Avatar` (komentar di kode, componentization 2026-08-27) — namun kolom identity di grid ini pakai `<x-grid.identity-cell>` langsung, bukan avatar manual.

## Catatan
Tidak ada TODO eksplisit di komentar kode.
