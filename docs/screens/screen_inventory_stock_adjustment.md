---
name: screen_inventory_stock_adjustment
description: Entri Stock Adjustment (koreksi tunggal) & Stock Opname (hitung fisik terjadwal) dengan approval berjenjang berbasis nilai absolut selisih, posting final ke Kartu Stok.
---

# Stock Adjustment & Opname

**File**: `resources/views/pages/inventory/stock-adjustment.blade.php`
**Route**: `inv.stock-adj.index` — `/inventory/stock-adjustment/{category}/{doc?}`
**Koneksi DB utama**: inv (tm2inv)
**Modul**: Inventory

## Tujuan
Layar grid+form gabungan (1 komponen, 2 panel: `grid`/`form`) untuk entri dokumen Stock Adjustment (`AD`, koreksi tunggal 1 item) dan Stock Opname (`OP`, hitung fisik terjadwal, biasanya cakupan lebih luas/`full_scope`). Berbeda dari Stock In/Out/Return yang langsung DRAFT→POSTED tanpa approval, dokumen ini WAJIB approval berjenjang (flow module `AD`/`OP`, level & threshold nilai dikonfigurasi di `flow_config`: Lvl1 Kepala Gudang, Lvl2 Manajer Operasional, Lvl3 Direksi, berdasar `SUM(ABS(qty*unit_price))`). Approve/Reject digabung ke form ini (bukan layar approval terpisah untuk keputusannya — layar `stock-adjustment-approval.blade.php` cuma antrian yang deep-link ke sini). `{doc}` opsional di URL untuk deep-link langsung ke dokumen tertentu.

## Field & Kolom
**Header form**: `doc_no` (readonly, auto), `doc_date`, `doc_kind` (AD/OP, dikunci setelah `editingId` terisi), `warehouse_id`, `reason_code` (dropdown: COUNT_ERROR/DAMAGED/LOST/FOUND/EXPIRED/OTHER), `notes`.

**Blok Opname** (muncul hanya bila `doc_kind === 'OP'`): `opn_officer`, `opn_start`/`opn_stop` (datetime), `opn_witness`, `full_scope` (checkbox — cakupan penuh semua item bersaldo di gudang).

**Baris item** (`lines[]`): item (dari live-search `itemSearch`), `location_id` (wajib, memicu `setLineLocation()` yang menarik `qty_system`+`unit_price` dari stockcard terakhir), `qty_system` (readonly, snapshot), `qty_physical` (wajib, input hitung fisik), Selisih (dihitung `qty_physical - qty_system`), Nilai Selisih (`abs(selisih * unit_price)`), `note_detail` (wajib bila ada selisih & bukan edit dokumen lama).

**Grid dokumen**: search (no. dokumen/notes), kolom Dokumen/Tanggal/Jenis/Status, Aksi Buka. Stat box: Total Dokumen, Menunggu Approval, Sudah Posting.

Validasi `saveDraft()`: `doc_date` required date, `warehouse_id` required integer, `reason_code` required, `lines` min:1, tiap baris `location_id` required integer, `qty_physical` required numeric min:0. Khusus OP: `opn_officer`/`opn_start`/`opn_stop`/`opn_witness` wajib diisi (dicek manual, bukan lewat `$this->validate()`).

## Aksi / Method
- `mount(string $category, ?int $doc = null)` — validasi category, buka `openEdit($doc)` bila param `{doc}` ada
- `warehouses()`/`selectedWarehouseLocations()` [Computed] — dropdown gudang & lokasi aktif
- `itemResults()` [Computed] — live-search item per category (min 2 karakter, limit 15)
- `systemBalance(int $itemId, int $locationId)` (private) — snapshot `qty_eb`/`avg_unitprice` dari baris `*_stockcard` TERAKHIR item+lokasi
- `addLine()`/`setLineLocation()`/`removeLine()` — kelola baris
- `pullFullScope()` — khusus OP dengan `full_scope=true`: tarik SEMUA item bersaldo (`qty_eb != 0`) di gudang terpilih dari stockcard terbaru per item+lokasi, MENIMPA baris yang sudah ada
- `documents()`/`statTotal()`/`statPending()`/`statPosted()` [Computed] — grid & statistik (filter `doc_cat` IN AD,OP)
- `openNew()`/`openEdit(int $docId)`/`backToGrid()` — navigasi panel grid↔form
- `buildLinesPayload()` (private) — bentuk baris untuk service, `qty` = `qty_physical - qty_system` (SUDAH signed), `action_type='ADJUST'`
- `saveDraft(StockDocumentService $svc)` — validasi + simpan draft (nomor dokumen auto via `nextDocNo()` prefix ADJ/OPN)
- `absoluteValue()` (private) — `SUM(ABS((qty_physical-qty_system) * unit_price))` untuk threshold approval
- `submitForApproval(StockDocumentService $svc, FlowEngineService $flow)` — save dulu, set `flow_status='SUBMITTED'`, panggil `flow->submit($doc_kind, ..., 'SUBMITTED', $amount)`; bila `null` (tidak ada config approval aktif) tetap tersimpan SUBMITTED tapi flash error "belum masuk antrian approval siapapun"
- `pendingApproval()`/`approvalHistory()` [Computed] — status approval dokumen aktif dari `FlowEngineService`
- `approveDoc(FlowEngineService $flow, StockPostingService $posting)` — approve step; bila `final=true` langsung panggil `StockPostingService::postDocument()` (posting ke Kartu Stok); bila belum final, update `flow_status` ke step berikutnya
- `rejectDoc(FlowEngineService $flow)` — wajib `approvalNote` min 10 karakter, set `flow_status` hasil reject (biasanya `REVISION_NEEDED`)
- `cancelDoc(StockDocumentService $svc)` — batalkan dokumen (hanya status DRAFT/REVISION_NEEDED)
- `subStateBadgeClass(string $status)` — warna badge status

## Tabel & Model yang Disentuh
- `App\Models\Inventory\RmStock`/`FgStock`/`SfgStock` (tm2inv, `rm_stock`/`fg_stock`/`sfg_stock`) — header, dipilih via const `HEADER_MODEL`
- `App\Models\Inventory\RmStockcard`/`FgStockcard`/`SfgStockcard` (tm2inv) — sumber `qty_system`/`unit_price` snapshot, hanya DIBACA di sini (insert HANYA lewat `StockPostingService`)
- `App\Models\Master\MatRaw`/`ProdPart`/`ProdProduct` (tm2mst) — live-search & label item
- `App\Models\Master\WhWarehouse`/`WhLoc` (tm2mst) — dropdown gudang & lokasi
- `App\Services\Inventory\StockDocumentService` — `nextDocNo()`, `saveDraft()`, `cancel()` (satu-satunya jalur sah tulis header+baris draft)
- `App\Services\Inventory\StockPostingService` — `postDocument()` (satu-satunya jalur sah insert stockcard & kunci header POSTED, dipanggil saat approval final)
- `App\Services\Flow\FlowEngineService` — `submit()`/`approve()`/`reject()`/`pendingForUser()`/`history()`, baca-tulis `tm2mst.flow_log`/`flow_config*` (lintas-DB dari tm2inv, dicocokkan di PHP)

## Alur / Business Logic Penting
- **Beda dari Stock In/Out/Return**: dokumen ini WAJIB approval berjenjang (bukan langsung POSTED) — level approval & threshold `min_amount`/`max_amount` datang dari `flow_config`/`flow_config_step` (module AD/OP), dihitung dari `SUM(ABS(qty*unit_price))` (nilai absolut selisih, bukan neto — supaya selisih plus dan minus dalam 1 dokumen tidak saling meniadakan nilai risikonya).
- **Posting hanya terjadi saat approval level TERAKHIR (final) tercapai** — `StockPostingService::postDocument()` dipanggil dari `approveDoc()`, bukan dari `saveDraft()`/`submitForApproval()`. "Final" ditentukan oleh tidak-adanya step berikutnya yang cocok, TERLEPAS dari nama status levelnya (mis. `APPROVED_L1` bisa jadi final untuk nilai kecil).
- `unit_price`/`qty_system` diambil dari baris `*_stockcard` TERAKHIR (`avg_unitprice`), BUKAN dari field harga di master item — ini levelnya cost-per-item riil (harga jual FG beda konsep, SFG tidak punya field cost di master).
- `full_scope` (khusus Opname): baris hasil `pullFullScope()` seharusnya tidak boleh dihapus per spec (`inventory_tier4.md §4.2`), tapi guard-nya untuk v1 cuma di server saat simpan (baris kosong disaring), belum dicegah di UI.
- Reject wajib alasan minimal 10 karakter (`approvalNote`), status hasil reject biasanya `REVISION_NEEDED` — dokumen kembali bisa diedit oleh pembuat.
- 3 kelas stok RM/SFG/FG dilayani 1 file yang sama lewat parameter route (bukan 3 file terpisah), sesuai pola layar inventory lain.

## Catatan
- Baris kode `saveDraft()` punya blok `foreach` kosong (comment-only) yang menjelaskan validasi `note_detail` per baris HANYA keras di simpanan pertama (baris lama saat edit ulang dianggap sudah lolos) — tidak ada enforcement tambahan aktual di situ, murni penjelasan desain.
- Tidak ada TODO eksplisit lain di komentar kode.
