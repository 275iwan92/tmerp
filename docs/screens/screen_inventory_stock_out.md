---
name: screen_inventory_stock_out
description: Entri Stock Out manual (issue ke departemen/proses produksi) untuk RM & SFG saja — FG ditolak di level route, keluar hanya lewat Delivery Order.
---

# Stock Out

**File**: `resources/views/pages/inventory/stock-out.blade.php`
**Route**: `inv.stock-out.index` — `/inventory/stock-out/{category}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Layar grid+form gabungan untuk mencatat barang KELUAR gudang secara manual — dikirim/diserahkan ke departemen/proses produksi tertentu. **Hanya berlaku untuk RM (Bahan Baku) dan SFG (Barang Setengah Jadi)** — `mount()` menolak (`abort` 404 dengan pesan eksplisit) kalau `{category}` bukan RM/SFG. FG SENGAJA tidak pernah muncul di sini: barang jadi keluar gudang cuma lewat Delivery Order ke customer (modul Dispatch), bukan Stock Out generik — alurnya "masuk gudang dulu (Stock In FG) → sebagian diambil truk untuk kirim (itu DO)".

v1 dikunci MANUAL sepenuhnya — belum ada cabang SUBSTITUTE/PURCHASE yang butuh tarik kebutuhan dari `tm2prd.prd_wo_material` (modul Production/WO belum dibangun sama sekali di codebase ini saat layar ini ditulis). Alur DRAFT→POSTED TANPA approval.

## Field & Kolom
**Header form**: `doc_no` (readonly, auto prefix `OUT`), `doc_date`, `warehouse_id` (Gudang Sumber), `issued_to_dept` (dropdown `OrgDepartment`, wajib), `process_code` (teks bebas, mis. "Cetak Offset, Laminasi"), `notes`, "Diserahkan oleh" (readonly, nama user login).

**Baris item** (`lines[]`): item (live-search per category — `ProdPart` untuk SFG, `MatRaw` untuk RM), `lot_no`, `location_id` (wajib, Lokasi Sumber), `qty` (wajib, > 0), `uom_code` (readonly).

**Grid dokumen**: search (no. dokumen/notes), filter tetap `doc_cat='STOCK'` + `doc_type=-1`, kolom Dokumen/Tanggal/Diserahkan Kepada (`issuedToDepartment->department_name`)/Status, Aksi Buka + Konfirmasi&Posting (bila DRAFT). Stat box: Total Dokumen, Draft, Sudah Diserahkan.

Validasi `saveDraft()`: `doc_date` required date, `warehouse_id` required integer, `issued_to_dept` required integer, `lines` min:1, tiap baris `item_id` required, `qty` required numeric gt:0, `location_id` required integer.

## Aksi / Method
- `mount(string $category)` — validasi category HANYA RM/SFG (`CATEGORY_LABELS` cuma berisi 2 entri, beda dari Stock In)
- `warehouses()`/`departments()`/`selectedWarehouseLocations()` [Computed] — dropdown gudang, departemen (`OrgDepartment`), lokasi
- `itemResults()` [Computed] — live-search, cabang manual `if ($this->category === 'SFG')` (bukan `match` seperti layar lain) karena hanya 2 kelas yang mungkin
- `addLine()`/`removeLine()` — kelola baris
- `documents()`/`statTotal()`/`statDraft()`/`statPosted()` [Computed] — grid & statistik
- `openNew()`/`openEdit(int $docId)`/`itemLabel(int $itemId)`/`backToGrid()` — navigasi & resolusi label
- `saveDraft(StockDocumentService $svc)` — validasi + simpan; baris dikirim dengan `action_type='ISSUE'`
- `postNow(StockPostingService $svc, ?int $docId = null)` — posting; menangkap `journal_warning` dari hasil posting (lihat di bawah) dan flash ke session terpisah dari flash sukses biasa
- `cancelDoc(StockDocumentService $svc)` — batalkan dokumen
- `subStateBadgeClass(string $status)` — warna badge (DRAFT/POSTED/CANCELLED — tidak ada status approval di sini)

## Tabel & Model yang Disentuh
- `App\Models\Inventory\RmStock`/`SfgStock` (tm2inv) — header, via const `HEADER_MODEL` (hanya 2 entri)
- `App\Models\Master\MatRaw`/`ProdPart` (tm2mst) — live-search & label item
- `App\Models\Master\WhWarehouse`/`WhLoc`/`OrgDepartment` (tm2mst) — dropdown
- `App\Services\Inventory\StockDocumentService` — `nextDocNo()` (prefix `OUT`), `saveDraft()`, `cancel()`
- `App\Services\Inventory\StockPostingService` — `postDocument()`, TERMASUK **auto-jurnal pemakaian** (`postUsageJournal()`, khusus RM/SFG doc_type OUT): Dr HPP (`cogs_account_id`) / Cr Persediaan (`stock_asset_account_id`) ke `journal_code='USG'`, akun RM dari item override (`mat_raw`) → fallback kategori (`MatCategory` level 1 `default_coa_account_id`/`default_stock_asset_account_id`); akun SFG HANYA dari item override `prod_part` (tidak ada tabel fallback kategori setara untuk SFG) — baris tanpa akun lengkap dilewati dari jurnal (bukan membatalkan posting stok) dan masuk `journal_warning`
- `App\Services\Acc\JournalPostingService` — dipanggil dari `StockPostingService::postUsageJournal()`, lintas-DB ke tm2acc

## Alur / Business Logic Penting
- **FG TIDAK PERNAH lewat Stock Out** — ini keputusan bisnis eksplisit (dikonfirmasi user), bukan gap yang belum dikerjakan.
- Alur **DRAFT → POSTED tanpa approval** (`inventory_rules.yaml alasan_out_rtp_tanpa_approval`).
- Auto-jurnal pemakaian HANYA untuk `docType OUT` sungguhan (bukan BB/RETURN/AD/OP — beda makna akuntansi: konsumsi produksi vs koreksi/retur). Nilai jurnal pakai `avg_unitprice` SEBELUM baris ini diposting (valuasi moving-average yang sah saat barang keluar).
- Bila akun HPP/Persediaan belum lengkap di master item/kategori, posting stok TETAP JALAN tapi jurnal baris tsb dilewati + peringatan `journal_warning` ditampilkan ke user, mengarahkan user melengkapi "Master Card (part)" untuk SFG atau "Master Bahan Baku/kategori terkait" untuk RM.
- Data keluar diambil manual (tanpa cabang SUBSTITUTE/PURCHASE dari WO) — v1 sengaja disederhanakan karena modul Production/WO belum ada.

## Catatan
Tidak ada TODO eksplisit lain di komentar kode selain penjelasan scope v1 (WO belum dibangun) dan alasan FG dikecualikan.
