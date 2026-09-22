---
name: screen_pur_report_outstanding_pr
description: Laporan PR yang sudah APPROVED tapi baris-nya belum sepenuhnya jadi PO (dihitung dari qty baris, bukan status header saja).
---

# Outstanding PR

**File**: `resources/views/pages/pur/report/outstanding-pr.blade.php`
**Route**: `pur.report.outstanding-pr` — `/purchasing/report/outstanding-pr`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Laporan read-only menampilkan PR yang statusnya `APPROVED`/`PARTIALLY_ORDERED`, belum cancel, dan **masih punya baris yang qty-nya belum sepenuhnya di-order** (`i_qty > c_qty_ordered`). Klik 2x membuka PR terkait di layar entry.

Catatan desain penting: `pr_requisition.status` header **tidak pernah otomatis naik** ke `FULLY_ORDERED`/`CLOSED` saat semua baris sudah `FULLY_ORDERED` (gap lama di kode — `PoBuilderService::addAllocation()` cuma update `pr_line.c_line_status`, bukan status header). Karena itu laporan ini **menghitung ulang outstanding dari qty baris** (bukan cuma percaya status header) supaya PR yang sebenarnya sudah tuntas tidak ikut nyangkut di laporan.

## Field & Kolom
- Filter: `search` (no. PR / departemen), `dateFrom`, `dateTo` (terhadap `i_doc_date`)
- Kolom identitas: nama departemen + `doc_no`
- Tanggal PR (`i_doc_date`), Dibutuhkan (`i_need_date`), Baris Outstanding (`lineCount` / total baris), Nilai Outstanding (estimasi, dari `i_est_price`), Status (badge)
- Tombol "Reset filter" muncul kalau ada filter aktif

**Stat box**: PR Outstanding (jumlah dokumen), Baris Outstanding (total baris), Nilai Outstanding (Est.).

## Aksi / Method
- `rows()` [computed] — query `PrRequisition::with(['department', 'lines'])` where status APPROVED/PARTIALLY_ORDERED, `stat_cancel=false`, filter tanggal; lalu untuk tiap PR hitung `outstandingLines` = baris dengan `bcsub(i_qty, c_qty_ordered, 4) > 0`; hasil di-filter hanya yang `lineCount > 0`; filter search dilakukan di level koleksi PHP
- `statCount()`, `statLines()`, `statValue()` [computed]
- `resetFilter()`
- `openPr(int $id)` — redirect ke `pur.pr.entry`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PrRequisition` (+ relasi `department`, `lines`) — tm2pur, `department` merujuk lintas-DB ke `OrgDepartment` (tm2mst) via relasi Eloquent (query terpisah)

## Alur / Business Logic Penting
- Perhitungan pakai `bcsub()` (BC Math, presisi 4 desimal) untuk menghindari floating-point error saat membandingkan qty.
- Nilai Outstanding di sini murni **estimasi** (`i_est_price` dari PR, bukan harga PO aktual) — beda dari Outstanding PO yang memakai `c_line_net` (harga PO nyata).
- Referensi desain: `docs/procurement_tier3.md` view `v1_pr_outstanding` (nama view, kemungkinan laporan ini awalnya diniatkan sebagai DB view tapi diimplementasi sebagai computed PHP — TBD, perlu verifikasi apakah view SQL-nya benar ada di DDL).

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
