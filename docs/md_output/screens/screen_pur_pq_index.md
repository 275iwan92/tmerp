---
name: screen_pur_pq_index
description: Grid daftar Purchase Quotation (PQ) dengan picker supplier untuk buat baru dan aksi Confirm langsung dari grid.
---

# Purchase Quotation — Grid

**File**: `resources/views/pages/pur/pq/index.blade.php`
**Route**: `pur.pq.list` — `/purchasing/pq`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Grid worklist PQ mengikuti `griduistandard.md`. Karena PQ tidak punya approval berjenjang (cuma konfirmasi sendiri), kolom Aksi = **↗ Buka** + **✓ Confirm** (bukan pola Approval terpisah seperti PR/PO/PRT). Tombol "+ New Record" membuka picker supplier untuk membuat PQ baru langsung dari grid ini.

## Field & Kolom
Grid (server-side pagination via `HasGridPagination`):
- Kolom identitas: nama supplier (dari `tm2mst.sup_supplier`, lintas-DB) + `doc_no`
- Tanggal (`i_doc_date`), Berlaku s/d (`i_quotation_valid_until`), Total IDR (`c_total_idr`), Status (badge, termasuk status turunan `EXPIRED`)
- `search` — filter `doc_no` atau `i_description` baris (live debounce 300ms)

**Stat box**: Total PQ, Draft, Confirmed.

**Picker New Record**: `supplierSearch` (live debounce 300ms, min 2 huruf) → cari `tm2mst.sup_supplier` where `activate=true`.

## Aksi / Method
- `quotations()` [computed] — query `PqQuotation` dengan filter search, paginate
- `supplierNames()` [computed] — resolve nama supplier lintas-DB untuk baris di halaman aktif
- `supplierResults()` [computed] — hasil pencarian supplier untuk picker New Record
- `statTotal()`, `statDraft()`, `statConfirmed()` [computed]
- `openPicker()`, `closePicker()`
- `startForSupplier(int $supplierId)` — buat `PqQuotation` baru (status DRAFT, `i_fx_rate=1`, `i_quotation_valid_until` default +14 hari), redirect ke `pur.pq.entry`
- `openPq(int $id)` — redirect ke entry
- `confirmPq(int $id)` — set status CONFIRMED langsung dari grid (validasi ada baris)
- `statusBadgeClass(PqQuotation $pq)`, `statusLabel(PqQuotation $pq)` — hitung status visual EXPIRED kalau CONFIRMED tapi `i_quotation_valid_until` lewat

## Tabel & Model yang Disentuh
- `App\Models\Pur\PqQuotation` — tm2pur
- `DB::connection('tm2mst')->table('sup_supplier')` — tm2mst, query raw lintas-DB

## Alur / Business Logic Penting
- Pola Aksi grid PQ beda dari grid PR/PO/PRT: tidak ada link ke halaman approval terpisah karena PQ tidak diapprove berjenjang.
- Status EXPIRED murni label tampilan (dihitung dari tanggal, tidak disimpan ke kolom `status`).

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
