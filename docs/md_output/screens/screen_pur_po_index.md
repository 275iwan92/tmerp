---
name: screen_pur_po_index
description: Grid daftar Purchase Order (PO) dengan picker supplier untuk buat baru.
---

# Purchase Order — Grid

**File**: `resources/views/pages/pur/po/index.blade.php`
**Route**: `pur.po.list` — `/purchasing/po`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Grid worklist PO mengikuti `griduistandard.md`. Kolom Aksi hanya **↗ Buka** (tidak ada Confirm/Toggle di grid ini — approval dilakukan di layar entry, bukan dari grid). Tombol "+ New Record" membuka picker supplier — PO dibuat/ditemukan draft-nya via `PoBuilderService::findOrCreateDraft()`.

## Field & Kolom
- Kolom identitas: nama supplier (`tm2mst.sup_supplier`, lintas-DB) + `doc_no`
- Tanggal (`i_doc_date`), Tipe (`i_po_type` · `i_procure_type`), Total Tagihan (`c_grand_total`), Status (badge)
- `search` — filter `doc_no` atau `i_description` baris (live debounce 300ms)

**Stat box**: Total PO, Menunggu Approval (`SUBMITTED`), Disetujui/Diproses (`APPROVED`/`SENT`/`PARTIALLY_RECEIVED`/`RECEIVED`/`CLOSED`), Ditolak/Batal (`REJECTED`/`CANCELLED`).

**Picker New Record**: `supplierSearch` (live debounce 300ms, min 2 huruf) → cari `tm2mst.sup_supplier` where `activate=true`.

## Aksi / Method
- `orders()` [computed] — query `PoOrder` dengan filter search, paginate (server-side, `HasGridPagination`)
- `supplierNames()` [computed] — resolve nama supplier lintas-DB
- `supplierResults()` [computed] — hasil pencarian supplier untuk picker
- `statTotal()`, `statSubmitted()`, `statProgress()`, `statRejected()` [computed]
- `openPicker()`, `closePicker()`
- `startForSupplier(int $supplierId)` — `PoBuilderService::findOrCreateDraft($supplierId)`, redirect ke `pur.po.entry`
- `openPo(int $id)` — redirect ke entry

## Tabel & Model yang Disentuh
- `App\Models\Pur\PoOrder` — tm2pur
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB
- `App\Services\Pur\PoBuilderService`

## Alur / Business Logic Penting
- `startForSupplier()` memakai `findOrCreateDraft()` (bukan `create()` biasa) — kemungkinan supaya 1 supplier hanya punya 1 PO draft aktif pada satu waktu, baris baru ditambahkan ke draft yang sama lewat `addAllocation()`/`createPoFromPq()` di layar lain. TBD - perlu verifikasi detail idempotensi di `PoBuilderService`.

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
