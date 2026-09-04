---
name: screen_pur_pr_approval
description: Antrian PR yang menunggu keputusan approval milik user login — klik 2x membuka dokumen asli, bukan approve di sini
---

# PR Approval Center

**File**: `resources/views/pages/pur/pr/approval.blade.php`
**Route**: `pur.pr.approval` (pola `route('pur.pr.entry', ...)` dipakai untuk buka dokumen)
**Koneksi DB utama**: pur (tm2pur) — `pr_requisition`. Lintas-DB read-only ke `FlowEngineService` (tm2mst, `sec_flow_log`/sejenis, disebut "FlowLog (tm2mst)" di komentar kode).
**Modul**: Procurement

## Tujuan
Layar murni ANTRIAN (bukan tempat approve/reject) — pola sama ME29N SAP / action pane Dynamics 365 / approval Odoo: approver klik 2x untuk buka dokumen asli (`pur.pr.entry`) dan memutuskan di sana. Aktor: siapa pun yang jadi approver aktif di `FlowEngineService::pendingForUser('PR', ...)`.

## Field & Kolom
GRID (bukan form): kolom Departemen/No. PR (identity-cell), Diajukan (tgl acted_at), Posisi Approval (pending_label), Nilai (amount_snapshot), Status (MENUNGGU/DITAHAN), Aksi (buka). Search box: no. PR / nama departemen.

## Aksi / Method
- `openPr(int $id)` — redirect ke `pur.pr.entry`.
- Tidak ada aksi approve/reject/hold di layar ini (sengaja, lihat Tujuan).

## Tabel & Model yang Disentuh
`App\Models\Pur\PrRequisition` (tm2pur) — dicocokkan di PHP terhadap hasil `FlowEngineService::pendingForUser()` (lintas-DB, pola query terpisah — `FlowLog` tak bisa di-join fisik ke `pr_requisition` beda DB, CLAUDE.md §2).

## Alur / Business Logic Penting
Statistik: Total di Antrian Saya, Menunggu Keputusan (action=SUBMITTED), Ditahan (action=HOLD). Approval sesungguhnya terjadi di `pur/pr/entry.blade.php`, bukan di sini.

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode.
