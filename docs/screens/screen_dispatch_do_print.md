---
name: screen_dispatch_do_print
description: Cetak Delivery Order (internal, Sisi A) — harga tampil penuh, dokumen verifikasi Sales/Finance sebelum barang berangkat.
---

# Delivery Order (Cetak — Internal)

**File**: `resources/views/pages/dispatch/do/print.blade.php`
**Route**: `dispatch.do.print` — `GET /dispatch/do/{doId}/print`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery (Dispatch)

## Tujuan
Cetakan Delivery Order versi INTERNAL (Sisi A) — harga tampil penuh, dipakai sebagai dasar verifikasi Sales/Finance sebelum barang berangkat. Gate akses: hanya `status` in `['CONFIRMED', 'SHIPPED']` (belum ada `do_no` selama DRAFT); kalau belum, redirect ke `dispatch.do.entry` + flash error. Berbeda dari `print-surat-jalan.blade.php` yang merupakan salinan Sisi B (tanpa harga, dibawa kurir) — dua cetakan dari satu sumber data `DoDelivery` (DISPATCH-00). Aktor: Sales/Finance/Dispatcher (verifikasi internal).

## Field & Kolom
Read-only. Menampilkan:
- Kop surat (`OrgCompany::where('company_code', 'TM')`), No. DO, Tanggal DO, Terkirim (`actual_ship_date`, kalau ada), Ref. CO (`coRefs` — gabungan `co_no` unik dari semua lines).
- Party "Dikirim Ke" (customer nama, kode, alamat lengkap) dan "Dikirim Dari" (company, status DO).
- Tabel Rincian Barang: No. CO, deskripsi barang, qty+unit, harga satuan, subtotal (semua kolom termasuk harga — beda dari Surat Jalan).
- Blok total nilai (`$totals['value']`, sum `subtotal` semua lines).
- Catatan: "Dokumen internal Sales/Finance — dasar verifikasi sebelum barang berangkat. Untuk salinan tanpa harga (dibawa kurir), lihat cetakan Surat Jalan."
- Blok tanda tangan (Admin Sales/Dispatch — Disiapkan; Kepala Gudang — Diperiksa) + footer cetak.

## Aksi / Method
- `mount(int $doId)` — guard `status` in `['CONFIRMED', 'SHIPPED']`, else redirect ke `dispatch.do.entry` + flash error.
- `do()` [Computed] — `DoDelivery::with(['customer', 'lines' (exclude CANCELLED, order by line_no)])`.
- `company()` [Computed] — `OrgCompany::where('company_code', 'TM')->first()`.
- `totals()` [Computed] — sum `qty_do` dan `subtotal` dari lines.
- `coRefs()` [Computed] — gabungan unik `order.co_no` dari semua lines.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\DoDelivery` → `tm2sdi.do_delivery`, relasi `customer` (lintas-DB ke `tm2mst.cust_customer`), `lines`.
- `App\Models\Master\OrgCompany` → tm2mst (koneksi `mst`) — lintas-DB, query terpisah.

## Alur / Business Logic Penting
- Kerangka cetak dari `resources/views/components/print/*` (`printnotastandard.md`) — DO/Surat Jalan eksplisit termasuk keluarga "Nota" (beda dari Laporan Inventory yang landscape+navy-header).
- Data sama persis (`DoDelivery` + `lines`) dengan `print-surat-jalan.blade.php`, cuma query `lines` di sana sengaja `select()` tanpa kolom harga sama sekali (bukan cuma disembunyikan CSS).

## Catatan
Tidak ditemukan gap/TODO eksplisit dalam komentar kode file ini.
