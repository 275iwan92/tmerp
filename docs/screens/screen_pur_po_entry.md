---
name: screen_pur_po_entry
description: Form entry Purchase Order (PO) — tarik alokasi PR ke baris pesanan, hitung pajak (PPN/PPh23), submit approval, dan aksi approve/reject/hold/kirim-ke-supplier digabung di layar yang sama.
---

# Purchase Order Entry

**File**: `resources/views/pages/pur/po/entry.blade.php`
**Route**: `pur.po.entry` — `/purchasing/po/{po}/edit`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Layar entry PO dipakai Purchasing untuk membuat draft PO per-supplier, menarik baris dari alokasi PR yang sudah APPROVED (`pr_line_alloc`, boleh gabung dari beberapa PR selama supplier sama), lalu mengajukan approval. Sama seperti PR, aksi Approve/Reject/Hold digabung di form yang sama (pola action-pane ala SAP ME29N/Dynamics 365/Odoo) — bukan layar approval terpisah. Setelah `APPROVED`, ada aksi manual "Kirim ke Supplier" (`sendToSupplier()`) yang mengubah status jadi `SENT` — ini yang jadi syarat PO muncul di worklist PV.

## Field & Kolom
**Header** (`updateHeader()`, hanya saat `status === 'DRAFT'`, whitelist):
- `i_doc_date`, `i_po_type` (LOCAL/IMPORT), `i_procure_type` (REPEAT/ONE_TIME/CAPEX), `i_payment_term`, `i_delivery_date`, `i_ship_to`, `i_notes`
- `supplier` — read-only, dari `tm2mst.sup_supplier`

**Sumber (hanya saat DRAFT)**: `availableAllocations()` — daftar `PrLineAlloc` dengan `c_is_converted=false`, `i_supplier_id` null atau sama dengan PO ini, dan `line.requisition.status = 'APPROVED'`; tombol "+ Tambah" per baris.

**Baris** (`PoLine`, editable via `updateLine()` whitelist `i_price`, `i_disc_pct`):
- `line_no`, `c_item_code`, `i_description`, `i_line_class` (RAW/SERVICE, badge warna beda), `i_qty`, `i_uom`, `i_price`, `i_disc_pct`, `c_line_net`, asal PR (`allocation.line.requisition.doc_no`)

**Ringkasan Pajak** (read-only, dihitung `recalcTax()`): `c_dpp`, `c_ppn` (11%), `c_grand_total`, `c_pph` (PPh 23 jasa 2%, ditampilkan sebagai pengurang), `c_net_payable` (dibayar ke supplier)

**Approval**: `approvalNote`, `approvalError`; `blockedMessage` (info kalau tak ada konfigurasi approval aktif)

## Aksi / Method
- `mount(int $po)`
- `po()` [computed] — load `PoOrder` + lines
- `pendingApproval()`, `approvalHistory()` [computed] — via `FlowEngineService` doc-type `'PO'`
- `supplier()` [computed] — raw query `tm2mst.sup_supplier`
- `departments()` [computed] — tidak dipakai langsung di blade (kemungkinan sisa/legacy — TBD)
- `availableAllocations()` [computed]
- `addAllocation(int $allocId, PoBuilderService $builder)` — tarik 1 alokasi PR jadi baris PO via `PoBuilderService::addAllocation()`, lalu `recalcTax()`
- `updateLine(int $lineId, string $field, $value)` — update harga/diskon baris, `recalcNet()` per baris lalu `recalcTax()` header
- `updateHeader(string $field, $value)`
- `recalcTax()` [protected] — panggil `PoOrder::recalcTax()`
- `submit(FlowEngineService $flow)` — validasi ada baris, generate `doc_no` via `PoOrder::nextDocNo()`, status `SUBMITTED`, panggil `$flow->submit('PO', ...)`; kalau tidak ada konfigurasi approval, set `blockedMessage`
- `sendToSupplier()` — hanya saat status `APPROVED`; set status `SENT` + `sent_at = now()` (PROC_TIER_2 §C-2: aksi manual, bukan otomatis saat approval final)
- `approveDoc(FlowEngineService $flow)`, `rejectDoc(...)` (wajib `approvalNote`), `holdDoc(...)` (wajib `approvalNote`, status TIDAK berubah)
- `delete()` — hanya saat DRAFT; lepas kembali alokasi PR yang sudah ditarik (`c_is_converted = false`) sebelum hapus PO, redirect ke `pur.po.list`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PoOrder` — tabel `po_order` (tm2pur/pur)
- `App\Models\Pur\PoLine` — tabel `po_line` (tm2pur/pur), relasi `allocation` ke `PrLineAlloc`
- `App\Models\Pur\PrLineAlloc` — tm2pur, relasi `line.requisition` (dalam-DB ke `pr_line`/`pr_requisition`)
- `App\Models\Master\OrgDepartment` — tm2mst (computed `departments()` tapi tampak tidak dipakai di blade)
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB
- `App\Services\Flow\FlowEngineService` — approval generik
- `App\Services\Pur\PoBuilderService::addAllocation()` — tm2pur

## Alur / Business Logic Penting
- Status siklus: `DRAFT` → `SUBMITTED` → (`APPROVED` | `REJECTED` | `CANCELLED`) → `SENT` → `PARTIALLY_RECEIVED` → `RECEIVED` → `CLOSED`.
- Warning kontekstual saat pending approval: kalau `i_procure_type === 'CAPEX'` tampil warning "nilai besar, non-rutin, periksa lebih cermat"; kalau `i_po_type === 'IMPORT'` tampil warning bahwa checklist dokumen impor (`po_import_doc`) masih INACTIVE fase ini (belum dibangun).
- `sendToSupplier()` adalah tahap terpisah dan manual dari approval — bukan otomatis saat approval terakhir, supaya officer menegaskan PO benar-benar sudah dikirim. Status `SENT` inilah syarat PO tampil di worklist PV (Payment Voucher / penerimaan? — lihat dokumentasi PV).
- Alokasi PR dari beberapa PR berbeda boleh digabung ke 1 PO selama supplier sama; tiap baris tetap membawa referensi PR asalnya (`allocation.line.requisition`).
- `delete()` melepas kembali flag `c_is_converted` pada `PrLineAlloc` supaya alokasi bisa dipakai PO lain — penting untuk integritas data karena FK lintas-modul di sini adalah tanggung jawab Laravel, bukan DB.

## Catatan
- Property `departments()` computed ada tapi tidak terlihat dipakai di template — kemungkinan sisa refactor. TBD - perlu verifikasi apakah masih dipakai di tempat lain (mis. via wire:model tersembunyi) atau memang dead code.
