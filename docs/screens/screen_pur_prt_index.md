---
name: screen_pur_prt_index
description: Grid daftar Purchase Return (PRT) dengan picker 2 jenis sumber (dari PV reject QC / dari stok gudang) untuk buat dokumen baru.
---

# Purchase Return — Grid

**File**: `resources/views/pages/pur/prt/index.blade.php`
**Route**: `pur.prt.list` — `/purchasing/prt`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Grid worklist PRT mengikuti `griduistandard.md`. PRT butuh approval sungguhan (Manajer Operasional) sehingga Approve/Reject/Hold digabung ke form entry (bukan di grid ini) — aksi grid cuma **↗ Buka**. Tombol "+ New Record" membuka picker 2 langkah: pilih jenis sumber dulu (Dari PV / Dari Stok), lalu pilih dokumen PV atau supplier.

## Field & Kolom
Grid:
- Kolom identitas: nama supplier (lintas-DB) + `doc_no`
- Sumber (badge "DARI PV"/"DARI STOK" dari `i_source`), Alasan (`i_reason`), Disposisi (`i_disposition`: Credit Note/Ganti Barang), Status (badge)
- `search` — filter `doc_no` atau `i_reason`

**Stat box**: Total Retur, Menunggu Approval (`SUBMITTED`), Nilai Retur (sum `c_line_value` dokumen `CONFIRMED`/`COMPLETED`).

**Picker New Record** (2 langkah, `pickerSource`: null → 'pv'|'stock'):
- Langkah 1: pilih jenis sumber
- 'pv': tabel `returnablePvs()` — `PvReceipt` status `POSTED` dengan `sum(c_qty_rejected) > 0`, klik baris langsung `startFromPv()`
- 'stock': cari supplier (`supplierSearch`, live debounce 300ms, min 2 huruf) → `startFromStock()`

## Aksi / Method
- `returns()` [computed] — query `PrtReturn` dengan filter search, paginate
- `supplierNames()` [computed] — raw lintas-DB
- `statTotal()`, `statPending()`, `statValue()` [computed]
- `returnablePvs()` [computed] — `PvReceipt::with('lines')` where `status='POSTED'`, filter yang punya reject qty
- `returnablePvSupplierNames()`, `supplierResults()` [computed]
- `openPicker()`, `closePicker()`, `pickSourceType(?string $source)`
- `startFromPv(int $pvId)` — buat `PrtReturn` baru: `i_supplier_id`/`po_id` diambil dari PV, `i_source='QC_REJECT'`, `i_disposition` default `CREDIT_NOTE`, `i_reason=''`, status DRAFT; redirect ke entry. Baris sungguhan ditarik di layar entry, picker ini cuma menentukan sumber PV.
- `startFromStock(int $supplierId)` — buat `PrtReturn` baru `i_source='FROM_STOCK'`, redirect ke entry
- `openReturn(int $id)`, `statusBadgeClass(string $status)`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PrtReturn` — tm2pur
- `App\Models\Pur\PvReceipt` (+ relasi `lines`) — tm2pur
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB

## Alur / Business Logic Penting
- Dokumen PRT dari sumber PV selalu mewarisi `i_supplier_id` dan `po_id` (dari `lines->first()->c_po_id`) milik PV asal — memastikan retur tetap terikat ke supplier & PO yang benar meski baris dipilih belakangan di entry.
- Disposisi default selalu `CREDIT_NOTE` saat dokumen baru dibuat (baik dari PV maupun stok) — officer bisa ganti ke `REPLACEMENT` di layar entry.

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
