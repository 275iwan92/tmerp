---
name: screen_pur_pr_print
description: Cetak dokumen Purchase Requisition (internal, departemen->approval) — gate status APPROVED ke atas
---

# Cetak Purchase Requisition

**File**: `resources/views/pages/pur/pr/print.blade.php`
**Route**: `pur.pr.print` (dgn `{pr}`)
**Koneksi DB utama**: pur (tm2pur) — `pr_requisition` (+ `lines`); mst (tm2mst) — `OrgCompany` (kop surat)
**Modul**: Procurement

## Tujuan
Cetak dokumen PR — dokumen INTERNAL (departemen peminta → approval), beda dari PO yang eksternal ke supplier (BACKLOG.md §12.7). Aktor: siapa pun yang perlu bukti cetak PR yang sudah disetujui.

## Field & Kolom
Read-only print view: kop perusahaan, No. PR + tanggal + tgl dibutuhkan, Departemen Peminta + pembuat (`created_by`), baris item (dari `lines`, urut `line_no`).

## Aksi / Method
- `mount(int $pr)` — guard: kalau status masih DRAFT/SUBMITTED, flash error "belum di-approve" dan redirect balik ke `pur.pr.entry` (tidak bisa cetak dokumen belum disetujui).

## Tabel & Model yang Disentuh
`App\Models\Pur\PrRequisition` (tm2pur, eager load `lines`+`department`), `App\Models\Master\OrgCompany` (mst, tm2mst, untuk kop surat).

## Alur / Business Logic Penting
Pakai komponen cetak reusable `resources/views/components/print/*` (pola sama semua Nota — `printnotastandard.md`), tema warna `#2c3e69`/`#eef1f7`.

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode.
