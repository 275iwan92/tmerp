---
name: screen_inventory_stock_in
description: Entri Stock In manual (STOCK/BB) untuk RM/SFG/FG, alur DRAFT->POSTED tanpa approval, posting langsung ke Kartu Stok.
---

# Stock In

**File**: `resources/views/pages/inventory/stock-in.blade.php`
**Route**: `inv.stock-in.index` — `/inventory/stock-in/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Layar grid+form gabungan (1 komponen, 2 panel `grid`/`form`) untuk entri barang masuk gudang secara manual, berlaku untuk 3 kelas stok RM/SFG/FG (`{category}` wajib di URL, pola sama dengan Laporan). Sumber dokumen v1 CUMA manual — integrasi otomatis dari PV (Purchase Voucher) Procurement atau WO Production belum dibangun (domain Purchase Receive, dikerjakan terpisah). Alur DRAFT→POSTED tanpa approval; Confirm di grid = posting sungguhan lewat `StockPostingService` (bukan sekadar ubah status), sesuai `griduistandard.md`: grid non-master = Buka + Confirm kondisional.

## Field & Kolom
**Header form**: `doc_no` (readonly, auto via `nextDocNo()` prefix `IN`), `doc_date`, `doc_cat` (STOCK = masuk biasa / BB = saldo awal), `warehouse_id`, `notes`, "Diterima oleh" (readonly, nama user login).

**Baris item** (`lines[]`): item (live-search `itemSearch`, min 2 karakter), `lot_no`, `location_id` (wajib, dropdown lokasi aktif+usable di gudang terpilih), `qty` (wajib, > 0), `uom_code` (readonly, dari master), `unit_price`.

**Grid dokumen**: search (no. dokumen/notes), filter tetap `doc_cat IN (STOCK,BB)` + `doc_type=1`, kolom Dokumen/Tanggal/Kategori/Status, Aksi Buka + Posting (bila `flow_status=DRAFT`). Stat box: Total Dokumen, Draft, Sudah Posting.

Validasi `saveDraft()`: `doc_date` required date, `doc_cat` required in STOCK/BB, `warehouse_id` required integer, `lines` min:1, tiap baris `item_id` required, `qty` required numeric gt:0, `location_id` required integer.

## Aksi / Method
- `mount(string $category)` — validasi category (RM/SFG/FG, ketiganya diizinkan — beda dari Stock Out yang FG ditolak)
- `warehouses()`/`selectedWarehouseLocations()` [Computed] — dropdown gudang & lokasi
- `itemResults()` [Computed] — live-search item per category
- `addLine()`/`removeLine()` — kelola baris
- `documents()`/`statTotal()`/`statDraft()`/`statPosted()` [Computed] — grid & statistik
- `openNew()`/`openEdit(int $docId)`/`itemLabel(int $itemId)`/`backToGrid()` — navigasi & resolusi label item saat edit
- `saveDraft(StockDocumentService $svc)` — validasi + simpan draft via service
- `postNow(StockPostingService $svc, ?int $docId = null)` — posting; `$docId` opsional dipakai saat tombol Posting diklik langsung dari grid (tanpa buka form dulu)
- `cancelDoc(StockDocumentService $svc)` — batalkan dokumen (hanya sebelum posting)
- `subStateBadgeClass(string $status)` — warna badge status (DRAFT abu, SUBMITTED biru — meski v1 tidak pernah menghasilkan status ini karena tanpa approval, POSTED hijau, CANCELLED merah)

## Tabel & Model yang Disentuh
- `App\Models\Inventory\RmStock`/`FgStock`/`SfgStock` (tm2inv, `rm_stock`/`fg_stock`/`sfg_stock`) — header, via const `HEADER_MODEL`
- `App\Models\Master\MatRaw`/`ProdPart`/`ProdProduct` (tm2mst) — live-search & label item
- `App\Models\Master\WhWarehouse`/`WhLoc` (tm2mst) — dropdown gudang & lokasi
- `App\Services\Inventory\StockDocumentService` — `nextDocNo()`, `saveDraft()`, `cancel()`
- `App\Services\Inventory\StockPostingService` — `postDocument()` (satu-satunya jalur sah insert `*_stockcard` & kunci header POSTED + update `cur_stock` master item)

## Alur / Business Logic Penting
- Alur sederhana **DRAFT → POSTED, tanpa approval** — beda dari Stock Adjustment/Opname yang wajib approval berjenjang.
- `doc_cat` STOCK vs BB: STOCK = penerimaan biasa, BB = input saldo awal (biasanya saat migrasi/setup). `doc_type=1` menandai arah masuk (dipakai `StockPostingService` untuk hitung `qty_in`).
- Item TIDAK BISA diedit setelah dokumen `POSTED`/`CANCELLED` (`readOnly`) — koreksi lanjutan harus lewat Stock Adjustment, bukan edit ulang dokumen In ini.
- Livewire component TIDAK BOLEH insert langsung ke `*_stock`/`*_stock_d`/`*_stockcard` — semua tulis lewat `StockDocumentService`/`StockPostingService` (`inventory_tier4.md §7`).

## Catatan
Tidak ada TODO eksplisit di komentar kode selain penjelasan sumber dokumen v1 cuma MANUAL (PV/WO auto-post domain terpisah, dikerjakan nanti).
