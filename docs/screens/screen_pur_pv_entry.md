---
name: screen_pur_pv_entry
description: Form entry Penerimaan Barang (SPB/PV) — dual UOM beli/stok, QC kualitas & quantity per baris, toleransi varians dengan override, lalu POST ke stok.
---

# Penerimaan Barang (SPB) Entry

**File**: `resources/views/pages/pur/pv/entry.blade.php`
**Route**: `pur.pv.entry` — `/purchasing/pv/{pv}/edit`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
"PV" di modul ini adalah **Penerimaan Barang / SPB** (Surat Penerimaan Barang, goods receipt) — bukan Payment Voucher. Layar ini dipakai gudang/QC untuk mencatat kedatangan barang dari 1 supplier (boleh mencakup >1 PO sekaligus, badge "N PO"), dengan dual UOM (Qty Beli vs Qty Stok, konversi `c_conv_used`), proses QC per baris (kualitas PASS/PARTIAL/FAIL dan status kuantitas), penanganan toleransi varians (dengan mekanisme override beralasan), lalu di-**POST** ke stok (merekalkulasi PO terkait).

Layar ini secara eksplisit ditandai **"Sisi B"** — badge header "🔒 Sisi B — tanpa harga di layar ini kecuali panel nilai" — konsisten dengan prinsip Costing rahasia (CLAUDE.md §4): staff penerimaan tidak melihat harga/nilai pembelian di layar ini.

## Field & Kolom
**Header** (`updateHeader()`, hanya saat status `DRAFT`/`QC_PENDING`, whitelist): `i_doc_date`, `i_supplier_do_no`, `i_vehicle_no`, `i_notes`. `c_po_doc_list`/`c_po_count` (read-only, badge PO tercakup), `supplier` (read-only, raw lintas-DB), `i_warehouse_code`.

**Baris** (`PvLine`, dual UOM):
- `line_no`, `c_po_doc_no`+`c_po_line_no` (asal PO), `c_item_code`, `i_description`
- `i_qty_purchase` + `i_uom_purchase`, `i_qty_stock` + `i_uom_stock` (editable via `updateLine()`, whitelist juga `i_lot_no`, `i_location_code`, `i_supplier_do_no`, `is_conv_manual`) — mengubah salah satu qty memicu recalc `c_conv_used` dan `recalcVariance()`
- `qc_quality_status` (PENDING/PASS/PARTIAL/FAIL, via `setQuality()`), `qc_qty_status` (via `markQtyDone()`)
- `c_qty_accepted`, `c_qty_rejected` (auto terisi penuh untuk PASS/FAIL; manual editable hanya saat PARTIAL via `updateQcQty()`)
- `is_tol_exceeded`, `c_qty_variance_pct`, `c_tol_pct` — baris di-highlight kuning kalau toleransi terlampaui dan belum di-override
- `tol_override_by`/`tol_override_at`/`tol_override_reason` — via `overrideTolerance()`

**Picker "+ PO lain"**: `checkedLineIds` (checkbox multi-select) dari `openLinesForAdd()` (baris PO terbuka milik supplier yang sama).

## Aksi / Method
- `mount(int $pv)`
- `pv()` [computed] — load `PvReceipt` + lines
- `supplier()` [computed] — raw lintas-DB
- `blockers()` [computed] — via `PvPostingService::blockers($pv)`, daftar alasan kenapa POST masih terkunci
- `openLinesForAdd()` [computed] — via `PvBuilderService::openLinesForSupplier()`
- `updateHeader(string $field, $value)`
- `updateLine(int $lineId, string $field, $value)` — kalau field qty berubah, recalc `c_conv_used` dan `recalcVariance($outstandingBefore)` (outstanding dihitung dari `poLine` terkait)
- `markQtyDone(int $lineId)` — set `qc_qty_status='DONE'`; kalau PV masih `DRAFT`, auto-naikkan status PV jadi `QC_PENDING`
- `setQuality(int $lineId, string $value)` — PASS: `c_qty_accepted = qtyStock`, `c_qty_rejected = 0`; FAIL: sebaliknya; PARTIAL: officer isi manual lewat `updateQcQty()`; juga hitung `c_line_value` (nilai, walau tak ditampilkan di layar ini)
- `updateQcQty(int $lineId, string $field, $value)` — whitelist `c_qty_accepted`/`c_qty_rejected`, recalc `c_line_value`
- `overrideTolerance(int $lineId, string $reason)` — wajib alasan non-kosong, catat `tol_override_by/at/reason`
- `openAddPicker()`, `closeAddPicker()`, `toggleAddLine(int $lineId)`, `addSelectedLines()` — via `PvBuilderService::addLines()`
- `post()` — via `PvPostingService::post($pv)`; kalau gagal (`!$result['ok']`) tampilkan `postMessage`; kalau berhasil, tampilkan `journalWarning` bila ada (akun Persediaan/GR-IR belum lengkap di Master Bahan Baku)
- `delete()` — hanya saat `DRAFT`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PvReceipt` — tabel `pv_receipt`? (nama tabel persis belum diverifikasi langsung dari model — TBD; alias domain "SPB") (tm2pur/pur)
- `App\Models\Pur\PvLine` — relasi `poLine` (dalam-DB ke `po_line`)
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB
- `App\Services\Pur\PvPostingService::blockers()`, `::post()`
- `App\Services\Pur\PvBuilderService::openLinesForSupplier()`, `::addLines()`

## Alur / Business Logic Penting
- Status siklus: `DRAFT` → `QC_PENDING` (otomatis begitu 1 baris ditandai `qc_qty_status=DONE`) → `POSTED` | `CANCELLED`.
- Header & sebagian besar baris HANYA bisa diedit saat status `DRAFT`/`QC_PENDING` — setelah `POSTED` seluruhnya read-only (guard di `updateHeader`/`updateLine`).
- **Toleransi varians**: kalau `is_tol_exceeded=true` dan belum ada `tol_override_by`, PV terkunci untuk POST (`blockers()` dari `PvPostingService`) — officer wajib isi alasan lalu klik Override per baris sebelum POST bisa dilakukan.
- POST men-trigger rekalkulasi PO terkait (badge konfirmasi: "PO terkait akan direkalkulasi") — kemungkinan update `c_qty_received` di `po_line` dan status PO (`PARTIALLY_RECEIVED`/`RECEIVED`). TBD - detail persis di `PvPostingService::post()` belum dibaca.
- `journalWarning` mengindikasikan integrasi ke auto-jurnal akuntansi (akun Persediaan/GR-IR) — kalau akun belum lengkap di Master Bahan Baku/kategori, PV tetap ter-POST tapi ada warning informasional (bukan blocker).
- 1 SPB bisa mencakup baris dari >1 PO sekaligus, selama supplier sama (badge "N PO" muncul kalau `c_po_count > 1`); baris tambahan bisa ditarik kapan saja selama belum `POSTED` via picker "+ PO lain".

## Catatan
- Nama tabel fisik `PvReceipt`/`PvLine` (mis. `pv_receipt`/`pv_line`) tidak diverifikasi langsung dari definisi model di sesi dokumentasi ini — hanya diketahui dari penggunaan Eloquent di blade. TBD - perlu verifikasi ke file model kalau detail nama kolom DB dibutuhkan presisi.
- Detail lengkap `PvPostingService::post()` (efek ke `po_line`/status PO/jurnal) tidak dibaca file service-nya di sesi ini — dokumentasi ini hanya mencatat efek yang terlihat dari sisi UI (blocker, journalWarning).
