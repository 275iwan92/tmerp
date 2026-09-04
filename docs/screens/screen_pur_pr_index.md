---
name: screen_pur_pr_index
description: Grid worklist Purchase Requisition — identitas pakai Departemen peminta (PR belum punya supplier), "+ New Record" via picker departemen
---

# Purchase Requisition (Grid)

**File**: `resources/views/pages/pur/pr/index.blade.php`
**Route**: `pur.pr.index` (redirect `pur.pr.entry` setelah create)
**Koneksi DB utama**: pur (tm2pur) — `pr_requisition` (+ relasi `lines`, `department`)
**Modul**: Procurement

## Tujuan
Worklist utama Purchase Requisition. Karena PR belum punya supplier (baru dipilih di tahap PO), kolom identitas pakai Departemen peminta — peran setara "nama customer" di grid Quotation/CO. "+ New Record" tetap ada di grid ini (bukan hanya di form) karena PR memang dibuat dari sini via picker departemen. Aktor: staf departemen mana pun yang mengajukan permintaan pembelian.

## Field & Kolom
Kolom grid: Departemen/No. PR, Tanggal (`i_doc_date`), Sumber (MANUAL/MRP AUTO — `i_source`), Dibutuhkan (`i_need_date`), Est. Nilai (`c_est_total`), Status (DRAFT/SUBMITTED/APPROVED/PARTIALLY_ORDERED/FULLY_ORDERED/CLOSED/REJECTED/CANCELLED/REVISION_NEEDED), Aksi. Search: no. PR / nama departemen / deskripsi item baris. Server-side pagination (`HasGridPagination`).
Modal "PR Baru": picker daftar `OrgDepartment` aktif — pilih 1 untuk membuat draft PR baru (`i_department_id` NOT NULL, wajib dipilih dulu, pola sama QuotationBuilder/CoOrderBuilder).

## Aksi / Method
- `openPicker()`/`closePicker()` — toggle modal pilih departemen.
- `createNew(int $departmentId)` — buat `PrRequisition` baru (`status=DRAFT`, `i_source=MANUAL`), redirect ke `pur.pr.entry`.
- `openPr(int $id)` — buka PR existing (dblclick row).

## Tabel & Model yang Disentuh
`App\Models\Pur\PrRequisition` (tm2pur), `App\Models\Master\OrgDepartment` (mst, tm2mst — lintas-DB query terpisah).

## Alur / Business Logic Penting
Statistik: Total PR, Menunggu Approval (SUBMITTED), Disetujui/Diproses (APPROVED+PARTIALLY_ORDERED+FULLY_ORDERED+CLOSED), Ditolak/Revisi (REJECTED+REVISION_NEEDED). Badge "MRP AUTO" vs "MANUAL" menandakan PR bisa lahir otomatis dari proses MRP (F10-F14, sebagian besar belum dibangun — lihat BACKLOG.md §6), bukan cuma input manual.

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode selain referensi umum ke `PROC_TIER_3` §2 utk aturan `i_department_id` NOT NULL.
