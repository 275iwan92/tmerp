---
name: screen_pur_prt_entry
description: Form entry Purchase Return (PRT) — retur barang ke supplier (dari reject QC saat penerimaan atau dari stok gudang), lengkap approval Manajer Operasional & follow-up disposisi.
---

# Purchase Return Entry

**File**: `resources/views/pages/pur/prt/entry.blade.php`
**Route**: `pur.prt.entry` — `/purchasing/prt/{prt}/edit`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
PRT mencatat retur barang ke supplier, dengan 2 sumber (`i_source`, ditentukan sekali saat pembuatan dokumen di grid, tidak bisa diubah di entry):
- **`QC_REJECT`** — baris ditarik dari `pv_line` yang `c_qty_rejected > 0` pada 1 PV tertentu (barang gagal QC saat penerimaan)
- **`FROM_STOCK`** — item ditambah manual dari `mat_raw` (barang sudah di gudang, masalah ditemukan belakangan)

Approve/Reject/Hold digabung ke form ini sendiri (pola sama PR/PO), field otomatis read-only begitu status bukan `DRAFT`. "Hold" di sini berlabel **"Minta Info"** di UI (mekanisme identik Hold Procurement lain: catatan saja, dokumen tetap di antrian approver). **v1 TIDAK otomatis membuat Stock Out di Inventory saat disetujui** — `rm_stock_doc_id` sengaja NULL/manual, follow-up fisik dilakukan officer terpisah di modul Inventory.

## Field & Kolom
**Header** (`updateHeader()`, hanya saat `status === 'DRAFT'`, whitelist):
- `i_doc_date`, `i_reason` (wajib), `i_disposition` (wajib: `CREDIT_NOTE`/`REPLACEMENT`), `replacement_due_date` (kalau disposisi REPLACEMENT), `qc_inspector` (kalau sumber QC_REJECT), `comm_method`, `supplier_pic`, `physical_return_date`, `expedition`, `cn_supplier_no`, `cn_received_date` (kalau disposisi CREDIT_NOTE), `i_notes`
- `supplier` — read-only dari `tm2mst.sup_supplier`
- `sourcePv` (relasi) — ditampilkan read-only "Ref. PV" kalau sumber QC_REJECT

**Baris** (`PrtLine`, editable via `updateLine()` whitelist `i_qty`, `c_unit_price`, `i_lot_no`; harga terkunci/disabled kalau baris berasal dari `pv_line_id`):
- `line_no`, `item_id`, `c_item_code`, `i_description`, `i_lot_no`, `i_qty`, `i_uom`, `c_unit_price`, `c_line_value` (dihitung `recalc()`), `pv_line_id` (nullable), `is_stock_moved`

**Picker sumber baris (hanya saat DRAFT)**:
- Kalau `QC_REJECT`: `returnablePvLines()` — baris PV `c_qty_rejected > 0` dikurangi yang sudah ditarik dokumen retur lain (`alreadyReturned()`)
- Kalau `FROM_STOCK`: `itemSearch` (live debounce 300ms, min 2 karakter) ke `MatRaw`

**Approval/pembatalan**: `approvalNote`, `approvalError`, `cancelReason`, `cancelError`

## Aksi / Method
- `mount(int $prt)`
- `prt()` [computed] — load `PrtReturn` + lines
- `supplier()` [computed] — raw lintas-DB
- `alreadyReturned(int $pvLineId)` [private] — sum qty retur baris PV ini di dokumen PRT lain (non-CANCELLED)
- `returnablePvLines()`, `itemResults()`, `pendingApproval()`, `approvalHistory()` [computed]
- `updateHeader(string $field, $value)`
- `addFromPvLine(int $pvLineId)` — hanya saat `i_source='QC_REJECT'`; qty default = sisa qty reject yang belum diretur
- `addManualItem(int $materialId)` — hanya saat `i_source='FROM_STOCK'`
- `updateLine(int $lineId, string $field, $value)`, `removeLine(int $lineId)`
- `recalcTotals()` [protected] — `PrtReturn::recalcCreditValue()`
- `amountSnapshot()` [private] — sum `c_line_value` semua baris, dipakai sebagai amount untuk flow approval
- `submitForApproval(FlowEngineService $flow)` — validasi ada baris, `i_reason` terisi, `i_disposition` terisi; generate `doc_no`, status `SUBMITTED`, `$flow->submit('PRT', ...)`
- `approveDoc(FlowEngineService $flow)` — set status hasil flow
- `rejectDoc(FlowEngineService $flow)` — wajib `approvalNote` >= 10 karakter (beda dari PR/PO yang cuma wajib non-kosong)
- `holdDoc(FlowEngineService $flow)` — label UI "Minta Info", wajib `approvalNote` non-kosong, status TIDAK berubah
- `markComplete()` — hanya saat `CONFIRMED`; set status `COMPLETED` (menandai barang pengganti/CN sudah diterima)
- `cancelDoc()` — hanya saat `DRAFT`; wajib `cancelReason` >= 10 karakter, set status `CANCELLED` + simpan `cancel_reason`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PrtReturn` — tabel `prt_return` (tm2pur/pur)
- `App\Models\Pur\PrtLine` — tabel `prt_line` (tm2pur/pur), relasi ke `PvLine` (dalam-DB, via `pv_line_id`) dan ke `return` (dokumen PRT-nya sendiri)
- `App\Models\Pur\PvLine`, relasi `material` — tm2pur
- `App\Models\Master\MatRaw` — tm2mst
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB
- `App\Services\Flow\FlowEngineService` — approval doc-type `'PRT'`

## Alur / Business Logic Penting
- Status siklus: `DRAFT` → `SUBMITTED` → (`CONFIRMED`|`REJECTED`→balik ke draft?|`CANCELLED`) → `COMPLETED`. (Lihat pesan konfirmasi tombol Tolak: "Akan dikembalikan ke draft" — TBD, perlu verifikasi apakah status benar-benar balik ke DRAFT atau `REJECTED` permanen, cek `FlowEngineService::reject()`.)
- Approval PRT ditujukan ke **Manajer Operasional** (disebutkan di pesan konfirmasi submit).
- Reject PRT beda validasi dari PR/PO: wajib alasan **minimal 10 karakter** (PR/PO cuma wajib non-kosong).
- Harga baris (`c_unit_price`) terkunci/disabled kalau baris berasal dari `pv_line_id` (harga ikut harga penerimaan asli) — hanya baris `FROM_STOCK` manual yang harganya bisa diedit bebas.
- `rm_stock_doc_id` NULL secara sengaja (bukan bug) — desain v1 tidak mengaitkan retur dengan dokumen Stock Out otomatis; ada catatan info di UI kalau status sudah `CONFIRMED`/`COMPLETED` tapi `rm_stock_doc_id` masih NULL, mengingatkan officer untuk follow-up manual di Inventory.

## Catatan
- Alur "Tolak" (`rejectDoc`) mengklaim di teks konfirmasi UI "akan dikembalikan ke draft" — perlu verifikasi langsung ke `FlowEngineService::reject()` apakah PRT benar kembali ke `DRAFT` (beda dari PR/PO yang statusnya jadi `REJECTED` final). TBD - perlu verifikasi.
