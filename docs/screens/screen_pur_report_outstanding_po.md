---
name: screen_pur_report_outstanding_po
description: Laporan PO yang sudah dikirim (SENT/PARTIALLY_RECEIVED) tapi baris-nya belum sepenuhnya diterima dari supplier.
---

# Outstanding PO

**File**: `resources/views/pages/pur/report/outstanding-po.blade.php`
**Route**: `pur.report.outstanding-po` — `/purchasing/report/outstanding-po`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Laporan read-only menampilkan PO berstatus `SENT`/`PARTIALLY_RECEIVED`, belum cancel, dengan baris yang belum (sepenuhnya) diterima (`i_qty > c_qty_received`), khusus baris `is_receivable=true` (baris `SERVICE` tidak pernah lewat proses PV/penerimaan barang, sesuai catatan PROC PV-09). Klik 2x membuka PO terkait di layar entry.

Beda dari Outstanding PR: `po_order.status` header **DI-MAINTAIN dengan benar** oleh `PvPostingService::recalcPo()`, jadi filter status di query cukup dipercaya — perhitungan qty ulang di sini hanya untuk **nilai tampilan** (baris & nilai outstanding), bukan sebagai penjaga utama seperti di Outstanding PR.

## Field & Kolom
- Filter: `search` (no. PO / supplier), `dateFrom`, `dateTo` (terhadap `i_doc_date`)
- Kolom identitas: nama supplier (lintas-DB) + `doc_no`
- Tanggal PO (`i_doc_date`), Tgl Kirim (`i_delivery_date`), Baris Outstanding (`lineCount` / total baris receivable), Nilai Outstanding (proporsional dari `c_line_net`), Status (badge)
- Tombol "Reset filter"

**Stat box**: PO Outstanding, Baris Outstanding, Nilai Outstanding.

## Aksi / Method
- `orders()` [computed] — query `PoOrder::with('lines')` where status IN (SENT, PARTIALLY_RECEIVED), `stat_cancel=false`, filter tanggal
- `supplierNames()` [computed] — raw lintas-DB
- `rows()` [computed] — untuk tiap PO, hitung `outstandingLines` (baris `is_receivable` dengan `bcsub(i_qty, c_qty_received, 4) > 0`); nilai outstanding dihitung **proporsional**: `(outstandingQty / qty) * c_line_net` per baris, dijumlahkan; filter search di level koleksi PHP
- `statCount()`, `statLines()`, `statValue()` [computed]
- `resetFilter()`
- `openPo(int $id)` — redirect ke `pur.po.entry`

## Tabel & Model yang Disentuh
- `App\Models\Pur\PoOrder` (+ relasi `lines`) — tm2pur
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB

## Alur / Business Logic Penting
- Nilai outstanding dihitung proporsional terhadap `c_line_net` (bukan estimasi seperti Outstanding PR) — karena PO sudah punya harga final/net per baris.
- Kolom "Baris Outstanding" menampilkan `lineCount / total baris receivable` (bukan total semua baris — baris SERVICE dikecualikan dari pembagi juga).
- Referensi desain: `docs/procurement_tier3.md` view `v1_po_outstanding`.

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
