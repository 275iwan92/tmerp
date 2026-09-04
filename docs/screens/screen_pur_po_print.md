---
name: screen_pur_po_print
description: Cetak dokumen resmi Purchase Order (PO) yang dikirim ke supplier, lengkap dengan rincian pajak dan tanda tangan 3 pihak.
---

# Purchase Order — Cetak

**File**: `resources/views/pages/pur/po/print.blade.php`
**Route**: `pur.po.print` — `/purchasing/po/{po}/print`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Mencetak PO resmi yang dikirim ke supplier — dokumen PO dianggap paling kritis di antara PR/PO/PQ karena keluar ke pihak eksternal, sehingga dikerjakan lebih dulu (catatan komentar kode). Gate cetak: hanya setelah `APPROVED` (di luar `DRAFT`/`SUBMITTED`) — draft belum final, alasan sama dengan gate Quotation harus `PUBLISHED` dulu.

## Field & Kolom
- Header dokumen: `doc_no`, `i_doc_date`, `revision_no` (ditampilkan "Rev. NN" kalau > 0), kode mata uang
- Party kiri "Kepada Supplier": nama, kode, `default_address`, `contact_name`, `phone` (dari `tm2mst.sup_supplier`)
- Party kanan "Pengiriman": `i_ship_to`, `i_delivery_date`, `i_payment_term`
- Tabel baris: `line_no`, `i_description`, `c_item_code`, `i_qty`+`i_uom`, `i_price`, `i_disc_pct`, `c_line_net`
- Ringkasan: `c_dpp`, `c_ppn`, `c_pph` (ditampilkan hanya kalau > 0, sebagai pengurang "-"), `c_net_payable` (TOTAL DIBAYAR, dengan kode mata uang)
- Catatan: `i_notes` (default teks generik "mohon konfirmasi penerimaan PO...")

## Aksi / Method
- `mount(int $po)` — guard: kalau status `DRAFT`/`SUBMITTED`, redirect balik ke entry dengan flash error
- `po()` [computed] — load `PoOrder` + lines
- `supplier()` [computed] — raw query `tm2mst.sup_supplier`
- `currencyCode()` [computed] — resolve dari `FinCurrency`
- `company()` [computed] — `OrgCompany` aktif (kop surat)

## Tabel & Model yang Disentuh
- `App\Models\Pur\PoOrder` (+ relasi `lines`) — tm2pur
- `App\Models\Master\FinCurrency`, `App\Models\Master\OrgCompany` — tm2mst
- `DB::connection('tm2mst')->table('sup_supplier')` — raw lintas-DB
- Komponen `resources/views/components/print/*`, theme hijau (`accent: #1f6f4a`)

## Alur / Business Logic Penting
- Tanda tangan 3 pihak: Diterbitkan oleh (Purchasing, `created_by`), Approval (Kepala Purchasing/Direksi, kosong), Diterima & disetujui oleh (Supplier, kosong).
- Intro tambahan di blok signature: pesan resmi bahwa PO diterbitkan oleh perusahaan, minta konfirmasi ketersediaan & jadwal pengiriman.

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
