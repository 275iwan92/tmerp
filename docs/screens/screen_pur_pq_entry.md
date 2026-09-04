---
name: screen_pur_pq_entry
description: Form entry Purchase Quotation (PQ) — catat penawaran harga 1 supplier, opsional acuan PR, bandingkan antar-supplier, lalu jadi sumber pembuatan PO.
---

# Purchase Quotation Entry

**File**: `resources/views/pages/pur/pq/entry.blade.php`
**Route**: `pur.pq.entry` — `/purchasing/pq/{pq}/edit`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
PQ mencatat 1 penawaran harga dari 1 supplier (`i_supplier_id`), opsional mengacu ke 1 PR (`i_pr_id`) untuk keperluan perbandingan harga antar-supplier ("Perbandingan Penawaran Harga"). PQ bersifat **kondisional/boleh dilewati** — dikonfirmasi sendiri oleh officer (bukan approval berjenjang seperti PR/PO/PRT), sehingga tidak ada blok Approve/Reject di layar ini. Setelah CONFIRMED, PQ bisa langsung menjadi sumber pembuatan PO (`createPoFromPq()`).

## Field & Kolom
**Header** (`updateHeader()`, hanya saat `status === 'DRAFT'`, whitelist field):
- `i_doc_date`, `i_quotation_valid_until`, `i_pr_id` (opsional, dropdown PR berstatus `APPROVED`/`PARTIALLY_ORDERED` & `stat_cancel=false`), `i_currency_id` (dari `FinCurrency`), `i_fx_rate`, `i_payment_term`, `i_lead_time_day`, `i_notes`
- `supplier` — read-only, ditampilkan dari `tm2mst.sup_supplier` (lintas-DB, query raw)

**Baris** (`PqLine`, editable via `updateLine()` whitelist `i_qty`, `i_price_fx`, `i_lead_time_day`):
- `line_no`, `pr_line_id` (nullable, terisi kalau ditarik dari PR), `item_id`, `i_description`, `i_qty`, `i_uom`, `i_price_fx`, `c_price_idr` (computed dari `recalc()`), `c_subtotal_idr`

**Lainnya**:
- `itemSearch` — pencarian cepat item (min 2 karakter) ke `MatRaw`
- `cancelReason`, `cancelError` — untuk pembatalan (wajib min 10 karakter)

## Aksi / Method
- `mount(int $pq)`
- `pq()` [computed] — load `PqQuotation` + lines
- `supplier()` [computed] — raw query `tm2mst.sup_supplier`
- `currencies()`, `openRequisitions()` (PR berstatus APPROVED/PARTIALLY_ORDERED, belum cancel), `itemResults()` — sumber dropdown/picker
- `comparisonQuotations()` [computed] — semua PQ lain dengan `i_pr_id` sama (kecuali CANCELLED), untuk tabel perbandingan
- `comparisonSupplierNames()` [computed] — resolve nama supplier lintas-DB untuk tabel perbandingan
- `updateHeader(string $field, $value)`
- `addLine(int $materialId)` — tambah baris dari pencarian item, harga awal 0
- `pullFromPr()` — tarik semua baris `PrLine` (sumber MASTER_ITEM) dari PR acuan sekaligus, skip item yang sudah ada, harga awal 0
- `updateLine(int $lineId, string $field, $value)` — recalc harga IDR & subtotal via `$line->recalc($fxRate)`
- `removeLine(int $lineId)`
- `recalcTotal()` [protected] — panggil `PqQuotation::recalcTotal()`
- `confirmPq()` — validasi ada baris, generate `doc_no` via `PqQuotation::nextDocNo()`, set status `CONFIRMED` (harga terkunci)
- `cancelPq()` — wajib `cancelReason` >= 10 karakter, set status `CANCELLED` + simpan `cancel_reason`
- `createPoFromPq()` — hanya saat `CONFIRMED`; cari/buat PO draft untuk supplier ini via `PoBuilderService::findOrCreateDraft()`, copy semua baris PQ ke `po_line` dengan `alloc_id = null` (bukan lewat `pr_line_alloc` — simplifikasi disengaja karena PQ bisa berdiri sendiri tanpa PR), `i_department_id`/`i_coa_account_id` diambil dari `PrLine` terkait kalau ada (relasi `pqLine->prLine`), lalu redirect ke `pur.po.entry`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PqQuotation` — tabel `pq_quotation` (tm2pur/pur)
- `App\Models\Pur\PqLine` — tabel `pq_line` (tm2pur/pur), relasi `prLine` (dalam-DB ke `pr_line`)
- `App\Models\Pur\PrLine`, `App\Models\Pur\PrRequisition` — tm2pur
- `App\Models\Master\MatRaw`, `App\Models\Master\FinCurrency` — tm2mst
- `DB::connection('tm2mst')->table('sup_supplier')` — query raw lintas-DB
- `App\Services\Pur\PoBuilderService::findOrCreateDraft()` — tm2pur

## Alur / Business Logic Penting
- Status siklus: `DRAFT` → `CONFIRMED` | `CANCELLED`. Tidak ada approval berjenjang — hanya konfirmasi diri sendiri oleh officer pembuat.
- `i_pr_id` nullable (migration `2026_08_22_000018`) — PQ boleh berdiri sendiri tanpa PR acuan.
- Saat `createPoFromPq()`, baris PO dibuat dengan `alloc_id = null` (bukan lewat `pr_line_alloc` seperti alur PO-dari-PR biasa) — dicatat sebagai simplifikasi v1 yang disengaja di komentar kode.
- Perbandingan antar-supplier hanya muncul kalau `i_pr_id` terisi dan ada >1 PQ non-CANCELLED untuk PR yang sama; baris termurah (`c_total_idr` minimum) di-highlight hijau.
- Badge status EXPIRED (bukan kolom DB, computed di `statusLabel()`/`statusBadgeClass()` grid index) muncul kalau `status=CONFIRMED` tapi `i_quotation_valid_until` sudah lewat — hanya visual, tidak mengubah kolom `status` di DB.

## Catatan
- Tidak ada guard eksplisit "PQ expired tidak bisa dipakai buat PO" di `createPoFromPq()` — hanya cek `status === 'CONFIRMED'`, jadi PQ yang sudah EXPIRED (visual) tetap bisa dipakai membuat PO kalau belum di-cancel manual. TBD - perlu verifikasi apakah ini disengaja.
