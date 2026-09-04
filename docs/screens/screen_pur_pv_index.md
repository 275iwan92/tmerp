---
name: screen_pur_pv_index
description: Grid daftar Penerimaan Barang (SPB/PV) dengan picker 2 langkah (pilih supplier lalu baris PO terbuka) untuk buat dokumen baru.
---

# Penerimaan Barang (SPB) — Grid

**File**: `resources/views/pages/pur/pv/index.blade.php`
**Route**: `pur.pv.list` — `/purchasing/pv`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Grid worklist SPB/PV mengikuti `griduistandard.md`. Aksi grid cuma **↗ Buka** (tidak ada approval — QC & POST dilakukan di layar entry). Tombol "+ New Record" membuka picker 2 langkah: pilih supplier (dari worklist supplier yang punya PO terbuka), lalu pilih baris PO mana saja yang mau diterima + isi kode gudang penerima.

## Field & Kolom
Grid:
- Kolom identitas: nama supplier (lintas-DB) + `doc_no`
- Tanggal (`i_doc_date`), PO Terkait (badge jumlah PO + `c_po_doc_list`), Gudang (`i_warehouse_code`), Surat Jalan (`i_supplier_do_no`), Status (badge)
- `search` — filter `doc_no`, `c_po_doc_list`, atau `i_supplier_do_no` (live debounce 300ms)

**Stat box**: Total SPB, Menunggu QC (`QC_PENDING`), Terposting (`POSTED`), Dibatalkan (`CANCELLED`).

**Picker 2 langkah**:
- Langkah 1: `supplierWorklist()` — daftar supplier dengan PO terbuka (`open_po_count`, `open_line_count`, `oldest_po_date`) via `PvBuilderService::supplierWorklist()`
- Langkah 2: pilih `pickedWarehouse` (kode gudang, wajib) + `checkedLineIds` (checkbox multi baris dari `openLines()`, default semua ter-checklist saat pilih supplier)
- `pickerValid()` [computed] — valid kalau ada baris terpilih DAN `pickedWarehouse` tidak kosong

## Aksi / Method
- `receipts()` [computed] — query `PvReceipt` dengan filter search, paginate
- `statTotal()`, `statQcPending()`, `statPosted()`, `statCancelled()` [computed]
- `supplierNames()` [computed] — gabungan ID dari `receipts` + `supplierWorklist`, raw lintas-DB
- `supplierWorklist()` [computed] — via `PvBuilderService::supplierWorklist()`
- `openLines()` [computed] — via `PvBuilderService::openLinesForSupplier($pickedSupplierId)`
- `openPicker()`, `closePicker()`, `pickSupplier(int $supplierId)` (auto-checklist semua baris terbuka, lanjut ke step 2), `toggleLine(int $lineId)`
- `createReceipt()` — via `PvBuilderService::createFromLines($supplierId, $warehouse, $checkedLineIds)`, redirect ke entry
- `openReceipt(int $id)`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PvReceipt` — tm2pur
- `App\Services\Pur\PvBuilderService::supplierWorklist()`, `::openLinesForSupplier()`, `::createFromLines()`
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB

## Alur / Business Logic Penting
- Hanya PO berstatus terbuka (kemungkinan `SENT`/`PARTIALLY_RECEIVED`, lihat dokumentasi PO — status `SENT` adalah syarat PO tampil di worklist PV, sesuai catatan di `pur/po/entry.blade.php`) yang muncul di picker.
- Semua baris terbuka milik supplier otomatis ter-checklist saat memilih supplier di langkah 1 — mempercepat alur umum (terima semua barang sekaligus), officer bisa uncheck baris yang belum datang.
- Kode gudang penerima (`pickedWarehouse`) wajib diisi manual (bukan dropdown master gudang di picker ini — TBD, perlu verifikasi apakah ada validasi terhadap master `wh_*` di service `createFromLines()`).

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
