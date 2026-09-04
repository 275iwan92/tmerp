---
name: screen_pur_po_approval
description: Antrian approval Purchase Order (PO) — murni grid daftar dokumen menunggu keputusan user login, keputusan aktual dilakukan di layar entry PO.
---

# PO Approval Center

**File**: `resources/views/pages/pur/po/approval.blade.php`
**Route**: `pur.po.approval` — `/purchasing/po-approval`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Layar ini murni **antrian** (grid) — menampilkan semua PO yang sedang menunggu keputusan approval dari user yang login. Aksi Approve/Reject/Hold TIDAK ADA di sini — dipindah ke form PO asli (`pur/po/entry.blade.php`), mengikuti pola action-pane ala SAP ME29N/Dynamics 365/Odoo (approver melihat & memutuskan langsung di dokumen aslinya). Klik 2x baris untuk membuka dokumen PO terkait.

## Field & Kolom
Grid:
- Kolom identitas: nama supplier (lintas-DB) + `doc_no`
- Diajukan (`pending->acted_at`), Posisi Approval (`pending->pending_label`), Nilai (`pending->amount_snapshot`), Status (badge MENUNGGU/DITAHAN berdasar `pending->action`)
- `search` — filter `doc_no` atau nama supplier

**Stat box**: Total di Antrian Saya, Menunggu Keputusan (`action='SUBMITTED'`), Ditahan/Hold (`action='HOLD'`).

## Aksi / Method
- `pendingQueue()` [computed] — via `FlowEngineService::pendingForUser('PO', Auth::id())`
- `orders()` [computed] — `PoOrder::whereIn('po_id', $ids)` (query terpisah dari `pendingQueue` karena flow_log di DB berbeda, lintas-DB — dicocokkan di PHP)
- `supplierNames()` [computed] — raw lintas-DB
- `rows()` [computed] — gabungkan `pendingQueue` + `orders` + filter search di level koleksi PHP
- `statTotal()`, `statWaiting()`, `statHold()` [computed]
- `openPo(int $id)` — redirect ke `pur.po.entry`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PoOrder` — tm2pur
- `App\Services\Flow\FlowEngineService` — flow_log kemungkinan di DB lain (tm2mst/tm2sys, TBD - perlu verifikasi), diakses via service, bukan Eloquent relation langsung — mencocokkan `doc_id` dengan `po_id` di PHP karena FK lintas-DB tidak bisa (CLAUDE.md §2)
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB

## Alur / Business Logic Penting
- Tidak ada mutasi state di layar ini sama sekali — 100% read-only worklist, semua keputusan approval terjadi di `pur/po/entry.blade.php`.
- Pola pencocokan data lintas-DB: ambil `doc_id` dari flow log (koneksi lain), lalu `whereIn` ke `PoOrder` (tm2pur), gabungkan di PHP — konsisten dengan pola default relasi lintas-modul di CLAUDE.md §2.

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
