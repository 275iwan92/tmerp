---
name: screen_quotation_print
description: Cetak Surat Penawaran Harga (Quotation) — hanya bisa diakses setelah Quotation berstatus PUBLISHED.
---

# Quotation Print (Surat Penawaran Harga)

**File**: `resources/views/pages/quotation/print.blade.php`
**Route**: `quotation.print` — `GET /quotation/{quotation}/print`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Cetakan resmi Surat Penawaran Harga (Quotation Letter) yang dikirim ke customer. Hanya bisa diakses kalau `doc_status === 'PUBLISHED'` — draft belum final tidak boleh dicetak, guard dipasang di `mount()` (bukan cuma sembunyikan tombol di layar entry) supaya akses langsung lewat URL pun ikut ketutup, redirect balik ke `quotation.entry` dengan flash error kalau belum PUBLISHED. Aktor: Sales (mencetak untuk dikirim ke customer), Customer (penerima dokumen).

## Field & Kolom
Read-only, tidak ada form. Menampilkan:
- Kop surat (`OrgCompany` where `company_code='TM'`), No. Quotation, tanggal terbit, no. revisi (`revision_no`, zero-padded), referensi Inquiry (`inquiryRefs` — gabungan `inquiry_no` unik dari semua item).
- Strip masa berlaku (`valid_until`) + termin pembayaran (`payment_term`).
- Party "Kepada Yth." (customer: nama, kode, kontak, phone) dan "Dari" (company, PIC Sales via `issued_by`, mata uang, skema pajak `tax_scheme` EXCL/INCL).
- Tabel Rincian Penawaran: per item — ref. inquiry, deskripsi + `spec_note`, qty+unit, harga satuan (+ diskon % kalau ada), subtotal. Baris `source_type='INQUIRY_TIER'` diberi highlight class `main`.
- Blok totals (`$quotation->totals()`): subtotal, DPP, PPN %, grand total.
- Panel "Alternatif Kuantitas" (`altRows`, opsional) — lihat Alur di bawah.
- Blok Termin & Pembayaran: info bank (`SysConstant::text('BANK_NAME'/'BANK_ACCOUNT_NAME'/'BANK_ACCOUNT_NO')`) atau pesan default; syarat & ketentuan (`notes` custom atau daftar default 5 poin).
- Blok tanda tangan (Direksi, Sales Executive/`issued_by`, Customer) + footer cetak.

## Aksi / Method
- `mount(int $quotation)` — guard `doc_status==='PUBLISHED'`, else redirect ke `quotation.entry` + flash error.
- `quotation()` [Computed] — `QuoQuotation::with(['customer', 'items.inquiry'])`.
- `company()` [Computed] — `OrgCompany::where('company_code', 'TM')->first()`.
- `totals()` [Computed] — `$quotation->totals()`.
- `inquiryRefs()` [Computed] — gabungan unik `inquiry.inquiry_no` semua item, dipisah koma.
- `altRows()` [Computed] — lihat Alur di bawah.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\QuoQuotation` → `tm2sdi.quo_quotation`, relasi `customer` (lintas-DB ke `tm2mst.cust_customer`), `items.inquiry` (`tm2sdi.iqp_inquiry`).
- `App\Models\Master\OrgCompany` → tm2mst (koneksi `mst`) — lintas-DB, query terpisah.
- `App\Models\Sys\SysConstant` → tm2sys — lintas-DB, dipakai untuk info rekening bank (`BANK_NAME`, `BANK_ACCOUNT_NAME`, `BANK_ACCOUNT_NO`).
- `App\Services\Inquiry\TierPricingResolver::rows()` — dipakai untuk panel Alternatif Kuantitas.

## Alur / Business Logic Penting
- Panel "Alternatif Kuantitas" (`altRows`) hanya untuk produk UTAMA (baris `source_type='INQUIRY_TIER'` pertama di quotation), murni informasi tambahan — bukan bagian harga resmi. Diambil via `TierPricingResolver::rows($inquiry, $priced)` dari `inquiry->tiers` + `inquiry->costing.priceTiers`; baris yang qty-nya sama dengan qty item utama (sudah tampil di tabel utama) di-`reject()`; baris tanpa `price_excl` di-`filter()` keluar. Kalau quotation gabungan >1 produk, cuma produk pertama yang ditampilkan.
- Kerangka cetak (bar, page A4, kop, parties, section bernomor, tanda tangan, footer) dari komponen reusable `resources/views/components/print/*` sesuai `docs/printnotastandard.md`; palet warna khusus quotation navy/accent `#2c3e69` (beda dari nota hijau Inquiry).

## Catatan
Tidak ada gap/TODO eksplisit dalam komentar kode file ini.
