---
name: screen_pur_prt_approval
description: Antrian approval Purchase Return (PRT) — grid murni daftar dokumen retur menunggu keputusan user login (Manajer Operasional), keputusan aktual di layar entry.
---

# Persetujuan Purchase Return

**File**: `resources/views/pages/pur/prt/approval.blade.php`
**Route**: `pur.prt.approval` — `/purchasing/prt-approval`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Layar antrian murni untuk approval PRT (Purchase Return) — Approve/Reject/"Minta Info" (Hold) TIDAK dilakukan di sini, dipindah ke form entry PRT (`pur/prt/entry.blade.php`), pola identik dengan PR/PO. Klik 2x baris untuk membuka dokumen retur terkait.

## Field & Kolom
Grid:
- Kolom identitas: nama supplier (lintas-DB) + `doc_no`
- Diajukan (`pending->acted_at`), Posisi Approval (`pending->pending_label`), Nilai (`pending->amount_snapshot`), Status (badge MENUNGGU / **MINTA INFO** — label khusus PRT untuk state HOLD, beda dari PR/PO yang berlabel "DITAHAN")
- `search` — filter `doc_no` atau nama supplier

**Stat box**: Total di Antrian Saya, Menunggu Keputusan, Minta Info (Hold).

## Aksi / Method
- `pendingQueue()` [computed] — `FlowEngineService::pendingForUser('PRT', Auth::id())`
- `returns()` [computed] — `PrtReturn::whereIn('prt_id', $ids)`, query terpisah lintas-DB dari flow log
- `supplierNames()`, `rows()`, `statTotal()`, `statWaiting()`, `statHold()` [computed]
- `openReturn(int $id)` — redirect ke `pur.prt.entry`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PrtReturn` — tm2pur
- `App\Services\Flow\FlowEngineService`
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB

## Alur / Business Logic Penting
- 100% read-only worklist, sama pola dengan `pur.pr.approval`/`pur.po.approval`.
- Label status HOLD khusus disebut "MINTA INFO" di grid ini (bukan "DITAHAN") — konsisten dengan istilah di layar entry PRT ("Minta Info" menggantikan istilah generik "Hold").

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
