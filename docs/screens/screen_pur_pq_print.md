---
name: screen_pur_pq_print
description: Cetak dokumen rekap Purchase Quotation (PQ) — lembar internal rekap penawaran, bukan surat RFQ ke supplier.
---

# Purchase Quotation — Cetak

**File**: `resources/views/pages/pur/pq/print.blade.php`
**Route**: `pur.pq.print` — `/purchasing/pq/{pq}/print`
**Koneksi DB utama**: pur (tm2pur)
**Modul**: Procurement

## Tujuan
Mencetak PQ sebagai lembar rekap penawaran resmi internal — dipakai untuk membandingkan >1 PQ pada PR yang sama saat memutuskan pemenang. **Bukan** surat RFQ (permintaan penawaran) ke supplier — arah dokumen ini mencatat HASIL penawaran, bukan meminta penawaran (dicatat eksplisit di komentar kode, di luar scope). Gate cetak: hanya status `CONFIRMED` (PQ cuma punya 2 status: DRAFT/CONFIRMED, plus CANCELLED).

## Field & Kolom
- Header dokumen: `doc_no`, `i_doc_date`, `i_quotation_valid_until`, kode mata uang (resolve dari `FinCurrency` via `i_currency_id`, default IDR)
- Party kiri: Supplier pemberi penawaran (`tm2mst.sup_supplier`, lintas-DB)
- Party kanan: Referensi — no. PR acuan (relasi `requisition`), `i_payment_term`, `i_lead_time_day`
- Tabel baris: `line_no`, `i_description`, `i_notes`, `i_qty`+`i_uom`, `i_price_fx` (dalam mata uang PQ), `c_subtotal_idr`
- Total: `c_total_idr`
- Catatan: `i_notes` (default teks generik kalau kosong)

## Aksi / Method
- `mount(int $pq)` — guard: kalau status masih `DRAFT`, redirect balik ke entry dengan flash error "konfirmasi dulu"
- `pq()` [computed] — load `PqQuotation` + lines + relasi `requisition`
- `supplier()` [computed] — raw query `tm2mst.sup_supplier`
- `currencyCode()` [computed] — resolve dari `FinCurrency`
- `company()` [computed] — `OrgCompany` aktif (kop surat)

## Tabel & Model yang Disentuh
- `App\Models\Pur\PqQuotation` (+ relasi `lines`, `requisition`) — tm2pur
- `App\Models\Master\FinCurrency`, `App\Models\Master\OrgCompany` — tm2mst
- `DB::connection('tm2mst')->table('sup_supplier')` — query raw lintas-DB
- Komponen reusable `resources/views/components/print/*` (`printnotastandard.md`), theme warna ungu (`accent: #7c5cd6`)

## Alur / Business Logic Penting
- Gate akses: hanya `CONFIRMED` boleh cetak (beda dari PR yang menggate di luar `DRAFT`/`SUBMITTED`).
- Tanda tangan: "Dicatat oleh" (Purchasing, `created_by`) + "Konfirmasi" (Kepala Purchasing, tanda tangan kosong).

## Catatan
Tidak ada TODO/FIXME eksplisit ditemukan di file ini.
