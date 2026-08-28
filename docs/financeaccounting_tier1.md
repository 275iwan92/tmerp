# FIN · TIER 1 — Business Requirements
**Modul 06 Finance & Accounting · TMERP · PT. Telasih Makmur**
DB `tm2acc` · prefix `fin_` · target build paralel ± akhir Nov 2026.

## 1. Tujuan
General Ledger double-entry untuk pabrik cetak offset & packaging: mencatat, memposting,
dan merekonsiliasi seluruh transaksi keuangan dari hulu (Sales/Procurement/Produksi) menjadi
laporan keuangan (Neraca Saldo, AR/AP, L/R). **Menggantikan pembukuan manual/legacy.**

## 2. Ruang lingkup (fase-1)
- **Master COA** (`fin_account`) — bagan akun industri cetak, pohon 3 level, akun recon AR/AP.
- **Cost Center** (`fin_cost_center`) — terkontrol, per pusat biaya produksi/departemen.
- **Voucher/Jurnal** (`fin_voucher` + `fin_voucher_line`) — entri double-entry, posting.
- **Subledger AR/AP** (`fin_invoice`) — faktur penjualan & tagihan supplier + umur.
- **Laporan**: Trial Balance, AR Aging, AP Aging (VIEW `v1_`).

Di luar fase-1 (fase-2): auto-posting dari Sales/Procurement, Cashier/pembayaran, GL closing,
laba-rugi & neraca ter-format, multi-currency revaluation.

## 3. Aktor & peran
| Aktor | Hak |
|---|---|
| Costing/Finance (mis. Wiwik) | kelola COA, cost center, input & posting voucher |
| Direksi | lihat semua angka (Sisi A), approve |
| Sales | tak akses HPP (Sisi B) — hanya angka jual bila di-publish |
| Purchasing | sumber data AP (fase-2 auto) |

## 4. Aturan bisnis inti (LOCKED)
1. **Double-entry**: Σdebit = Σkredit per voucher. Baris = debit XOR kredit (CHECK DB).
2. **Akun recon (`is_recon=1`)**: setiap baris jurnal ke akun ini **wajib** menyertakan
   `partner_type`+`partner_id` (ref logical ke `cust_customer`/`sup_supplier`). Menjaga sinkron
   GL ↔ subledger. Di-enforce aplikasi (bukan constraint DB).
3. **Akun header (`is_postable=0`)**: tak boleh dijurnal langsung.
4. **COGS perpetual** (Keputusan B): jurnal HPP diakui saat **KIRIM**, nilai = snapshot HPP dari
   Costing V2 (bukan hitung ulang). Urutan proses produksi (COATING→LAMINASI) tak relevan di GL,
   tapi struktur HPP mengikuti komponen Costing.
5. **Revenue per CO** (kasus cross-CO shipment): `fin_voucher_line.co_id` & `fin_invoice.co_id`
   di **level baris** — satu DO bisa memecah revenue ke beberapa CO dengan harga berbeda.
6. **Rate pajak** tidak di-hardcode: `fin_account.tax_scheme_code` → `tm2mst.fin_tax_scheme`
   (PPN_IN/PPN_OUT/PPH22/PPH23). Nilai % diambil saat posting.
7. **Cost center terkontrol** (Keputusan C): `cc_id` FK ke `fin_cost_center`, bukan free-text.
8. **Snapshot & REVISE**: voucher confirmed tak diedit. Koreksi = voucher baru / REVISE
   (copy-record, `revision_no+1`, lama `is_cancelled=1 cancel_reason='SUPERSEDED'`).
9. **Integrasi bertahap** (Keputusan A): `source_type`/`source_id` polymorphic di voucher &
   invoice — fase-1 posting manual, fase-2 auto dari CO/DO/PO/RECEIVING.
10. **Hard delete** hanya status DRAFT.

## 5. Ketergantungan (logical, lintas-DB)
`tm2mst.cust_customer`, `tm2mst.sup_supplier` (partner) · `tm2mst.org_department` (cost center) ·
`tm2mst.fin_tax_scheme` (rate) · `tm2sdi.co_order` (revenue per CO) · Costing V2 (snapshot HPP).

## 6. Kriteria selesai fase-1
COA ter-seed & bisa CRUD; voucher bisa input+posting balance; trial balance nol selisih;
AR/AP aging tampil; grid full-page navy terintegrasi shell menu Finance.
