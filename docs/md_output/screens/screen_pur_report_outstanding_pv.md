---
name: screen_pur_report_outstanding_pv
description: Laporan Penerimaan Barang (PV/SPB) yang sudah POSTED (barang masuk stok) tapi belum ditandai diserahkan ke departemen peminta.
---

# Outstanding PV

**File**: `resources/views/pages/pur/report/outstanding-pv.blade.php`
**Route**: `pur.report.outstanding-pv` — `/purchasing/report/outstanding-pv`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Laporan menampilkan SPB/PV berstatus `POSTED` (barang sudah masuk stok gudang) tapi kolom `is_handed_over` masih `false` — artinya barang belum ditandai diserahkan secara fisik ke departemen peminta. Beda dari 2 laporan outstanding lain (PR/PO), layar ini **punya aksi mutasi** (`markHandedOver()`) langsung dari grid, bukan cuma read-only.

Keputusan desain: flag `is_handed_over` disimpan **header-level per-PV** (opsi paling sederhana dari 3 opsi yang pernah diajukan — migration `2026_09_02_000033`), mengikuti gaya visual Outstanding PR/PO yang sudah ada (`docs/BACKLOG.md §7`).

## Field & Kolom
- Filter: `search` (no. PV), `dateFrom`, `dateTo` (terhadap `i_doc_date`)
- Kolom identitas: nama supplier + `doc_no`
- Tgl Terima (`i_doc_date`), Gudang (`i_warehouse_code`), Ref. PO (`c_po_doc_list`), Menunggu (`daysWaiting`, dihitung `i_doc_date->diffInDays(now())`, merah+bold kalau > 3 hari)
- Aksi: tombol "✓ Sudah Diserahkan"

**Stat box**: PV Outstanding (belum diserahkan), Menunggu > 3 Hari.

## Aksi / Method
- `rows()` [computed] — query `PvReceipt` where `status='POSTED'`, `is_handed_over=false`, filter tanggal & search (`doc_no` like); resolve nama supplier via `SupSupplier::whereIn(...)` (Eloquent model tm2mst langsung, bukan raw `DB::connection`, beda pola dari layar PV lain); hitung `daysWaiting` per baris
- `statCount()`, `statOver3Days()` [computed]
- `resetFilter()`
- `markHandedOver(int $pvId)` — update `is_handed_over=true`, `handed_over_at=now()`, `handed_over_by=Auth::user()->login` langsung via query builder (bukan load model dulu), hanya kalau `status='POSTED'`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PvReceipt` — tm2pur
- `App\Models\Master\SupSupplier` — tm2mst, diakses via Eloquent model (bukan `DB::connection('tm2mst')->table(...)` seperti kebanyakan layar PUR lain — inkonsistensi pola kecil, TBD apakah model `SupSupplier` memang tersedia dan dipakai konsisten di modul lain)

## Alur / Business Logic Penting
- Ini satu-satunya laporan Outstanding di modul PUR yang mengubah data (`markHandedOver`) — 2 laporan lain (PR/PO) murni read-only dengan aksi "Buka" ke dokumen sumber.
- `daysWaiting` dihitung real-time dari `i_doc_date` ke `now()`, bukan disimpan sebagai kolom — angka berubah tiap hari tanpa perlu job terjadwal.
- Tidak ada link "Buka" ke layar PV entry di grid ini (beda dari Outstanding PR/PO yang punya `wire:dblclick` buka dokumen) — aksinya langsung "Sudah Diserahkan" di tempat.

## Catatan
- Model `App\Models\Master\SupSupplier` dipakai di sini via Eloquent langsung, sedangkan layar PUR lain (PR/PO/PQ/PRT/PV entry & index) konsisten pakai `DB::connection('tm2mst')->table('sup_supplier')` raw query. TBD - perlu verifikasi apakah ini disengaja (model sudah ada dan lebih disukai) atau sisa inkonsistensi refactor.
