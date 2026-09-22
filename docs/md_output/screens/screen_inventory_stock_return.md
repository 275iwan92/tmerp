---
name: screen_inventory_stock_return
description: Entri retur barang dari produksi (RM & SFG saja) kembali ke gudang, wajib referensi 1 dokumen Stock Out POSTED, kondisi GOOD/SCRAP per baris.
---

# Return from Production

**File**: `resources/views/pages/inventory/stock-return.blade.php`
**Route**: `inv.stock-return.index` — `/inventory/stock-return/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Layar grid+form gabungan untuk mencatat barang yang diretur KEMBALI ke gudang dari lantai produksi (RM sisa tidak terpakai, atau SFG cacat/tidak terpakai kembali). **Hanya berlaku untuk RM dan SFG** (`mount()` menolak FG dengan pesan eksplisit) — FG tidak relevan karena FG tidak pernah bisa Stock Out (lihat `stock-out.blade.php`), jadi tidak ada sumber untuk diretur balik. Setiap dokumen retur WAJIB mereferensikan 1 dokumen Stock Out yang sudah POSTED dan belum dibatalkan, dan tidak bisa meretur lebih dari sisa yang belum diretur per baris. Alur DRAFT→POSTED tanpa approval — sumbernya (Stock Out) sudah tervalidasi POSTED, jadi menahan retur cuma menahan lantai produksi tanpa manfaat kontrol tambahan.

**Simplifikasi vs spec penuh (v1)**: tanpa tombol "Pecah Baris" (split GOOD/SCRAP dari 1 baris keluar — kalau perlu keduanya, buat 2 dokumen retur terpisah); tanpa tab "Rekonsiliasi WO" (butuh data Production/tm2prd yang belum ada).

## Field & Kolom
**Header form**: `doc_no` (readonly, auto prefix `RTN`), `doc_date`, **Ref. Stock Out** (`sourceDocId`/`sourceDocNo`, wajib — dipilih via modal picker `pickerOpen`, hanya bisa dipilih saat dokumen retur baru/belum tersimpan), `goodWarehouseId` (Gudang Tujuan untuk barang kondisi layak), `process_code` (auto terisi dari dokumen sumber saat dipilih, bisa diedit), `notes`.

**Baris item** (`lines[]`, ditarik otomatis dari baris dokumen Stock Out terpilih via `pickSource()`): Item | Dikeluarkan (`qty_issued`, readonly) | Sisa Boleh (`qty_returnable` = `qty_issued - qty_returned_before`, readonly) | Qty Retur (wajib, tidak boleh > `qty_returnable`) | Kondisi (GOOD/SCRAP) | Lokasi Tujuan (wajib — daftar lokasi beda per kondisi: `goodLocations` untuk GOOD, `scrapLocations` untuk SCRAP) | Keterangan (wajib bila SCRAP).

**Grid dokumen**: search (no. dokumen / `ref_doc_no`), filter tetap `doc_cat='RETURN'` + `doc_type=1`, kolom Dokumen/Tanggal/Ref. Stock Out/Status, Aksi Buka + Konfirmasi&Posting. Stat box: Total Dokumen, Draft, Sudah Diterima.

**Modal picker Stock Out**: tabel No. Dokumen/Tanggal/Sisa Bisa Diretur, hanya menampilkan dokumen dari `returnableSourceDocs()`.

Validasi `saveDraft()`: `doc_date` required date, `sourceDocId` required integer, `goodWarehouseId` required integer, `lines` min:1, tiap baris `qty` required numeric gt:0, `location_id` required integer — DITAMBAH validasi manual per baris: `qty` tidak boleh melebihi `qty_returnable` (+toleransi 0.0001), dan `note_detail` wajib bila `return_condition='SCRAP'`.

## Aksi / Method
- `mount(string $category)` — validasi category HANYA RM/SFG
- `itemKey()`/`itemLabel(int $itemId)` (private) — resolusi field kunci & label per category
- `warehouses()` [Computed] — dropdown gudang
- `goodLocations()` [Computed] — lokasi aktif+usable di `goodWarehouseId` terpilih
- `scrapLocations()` [Computed] — SEMUA lokasi dengan `is_usable=false` (lintas gudang — lokasi "afkir" ditandai lewat flag `is_usable`, bukan gudang terpisah)
- `returnableSourceDocs()` [Computed] — dokumen Stock Out POSTED, belum cancel, dan `SUM(qty - qty_returned) > 0` di minimal 1 baris (`return_wajib_ref_out`)
- `openPicker()`/`closePicker()`/`pickSource(int $docId)` — modal pilih dokumen sumber; `pickSource()` menarik baris yang `qty_returnable > 0` saja, isi `process_code` dari dokumen sumber
- `removeLine(int $index)` — hapus baris (baris ditarik otomatis, tapi masih bisa dihapus manual sebelum simpan)
- `documents()`/`statTotal()`/`statDraft()`/`statPosted()` [Computed] — grid & statistik
- `openNew()`/`openEdit(int $docId)`/`backToGrid()` — navigasi; `openEdit()` menghitung ulang `qty_returnable` dari baris sumber (`DETAIL_MODEL`) dikurangi retur dokumen lain YANG SUDAH tersimpan minus retur dokumen INI sendiri
- `saveDraft(StockDocumentService $svc)` — validasi + guard qty/SCRAP note, harga baris diambil dari `avg_unitprice` stockcard baris sumber (`reference_detail_id` = `source_detail_id`) via `StockPostingService::referenceName()`
- `postNow(StockPostingService $svc, ?int $docId = null)` — posting, LALU (di luar `StockPostingService`, logika khusus Return) akumulasi `qty_returned` di baris Stock Out sumber via `COALESCE(qty_returned,0) + qty` (NULL-safe, karena baris lama defaultnya NULL)
- `cancelDoc(StockDocumentService $svc)` — batalkan dokumen
- `subStateBadgeClass(string $status)` — warna badge status

## Tabel & Model yang Disentuh
- `App\Models\Inventory\RmStock`/`SfgStock` (tm2inv) — header retur DAN header Stock Out sumber (model sama, dibedakan `doc_cat`/`doc_type`), via const `HEADER_MODEL`
- `App\Models\Inventory\RmStockD`/`SfgStockD` (tm2inv, const `DETAIL_MODEL`) — baris dokumen sumber, kolom `qty_returned` diakumulasi di sini
- `App\Models\Inventory\RmStockcard`/`SfgStockcard` (tm2inv, const `CARD_MODEL`) — sumber `avg_unitprice` baris sumber
- `App\Models\Master\WhWarehouse`/`WhLoc` (tm2mst) — dropdown gudang & lokasi (termasuk lokasi scrap via flag `is_usable`)
- `App\Models\Master\ProdPart`/`MatRaw` (tm2mst) — label item
- `App\Services\Inventory\StockDocumentService` — `nextDocNo()` (prefix `RTN`), `saveDraft()`, `cancel()`
- `App\Services\Inventory\StockPostingService` — `postDocument()` (`docType` internal jadi `'IN'` karena `cat==='RETURN'`, qty selalu positif/`abs`) + `referenceName()`

## Alur / Business Logic Penting
- **Wajib referensi Stock Out POSTED** (`return_wajib_ref_out`) — retur tidak bisa berdiri sendiri, selalu terikat ke dokumen keluar sebelumnya. `qty_returnable` dihitung ulang setiap kali (baik saat pilih sumber maupun saat edit dokumen retur existing) supaya retur ganda melebihi sisa tercegah.
- **Kondisi GOOD vs SCRAP** menentukan lokasi tujuan yang tersedia (lokasi `is_usable=true` vs `is_usable=false`) — barang afkir masuk ke lokasi yang secara fisik/logis ditandai tidak bisa dipakai lagi untuk produksi, bukan gudang terpisah.
- FG SENGAJA tidak relevan (tidak pernah Stock Out, jadi tidak ada yang bisa diretur).
- Alur **DRAFT → POSTED tanpa approval**.
- Akumulasi `qty_returned` di baris Stock Out sumber adalah logika KHUSUS Return, sengaja tidak dimasukkan ke `StockPostingService` generik karena itu bukan tanggung jawab posting stok biasa.

## Catatan
- Tidak ada "Pecah Baris" (split GOOD/SCRAP dari 1 baris) dan tidak ada tab "Rekonsiliasi WO" — keduanya eksplisit di luar scope v1 (komentar kode), workaround: buat 2 dokumen retur terpisah bila 1 baris keluar perlu dipecah kondisinya.
