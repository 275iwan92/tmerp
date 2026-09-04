---
name: screen_dispatch_do_print_surat_jalan
description: Cetak Surat Jalan (eksternal, Sisi B) — dibawa kurir, kolom harga sengaja dikeluarkan dari query, bukan cuma disembunyikan CSS.
---

# Surat Jalan (Cetak — Eksternal)

**File**: `resources/views/pages/dispatch/do/print-surat-jalan.blade.php`
**Route**: `dispatch.do.print-surat-jalan` — `GET /dispatch/do/{doId}/print-surat-jalan`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery (Dispatch)

## Tujuan
Cetakan Surat Jalan versi EKSTERNAL (Sisi B) — dokumen yang dibawa kurir/dilihat customer saat serah terima barang, TIDAK boleh menampilkan harga. Sama seperti Delivery Order (internal), gate akses hanya `status` in `['CONFIRMED', 'SHIPPED']`. Data sumber sama (`DoDelivery`) tapi query baris di layar ini SENGAJA `select()` eksplisit tanpa kolom `unit_price`/`subtotal` sama sekali — bukan cuma disembunyikan lewat CSS/Blade, pola yang sama dipakai di cetakan Procurement PV. Aktor: Kurir/Ekspedisi, Customer (penerima barang).

## Field & Kolom
Read-only. Menampilkan:
- Kop surat (`OrgCompany::where('company_code', 'TM')`), No. DO (dipakai sebagai No. Surat Jalan), Tanggal, Kirim (`actual_ship_date` kalau ada), Ref. CO (`coRefs`).
- Party "Dikirim Ke" (customer nama, alamat lengkap — **tanpa kode customer**, beda dari DO internal) dan "Dikirim Dari" (company + alamat).
- Tabel Rincian Barang: No. CO, deskripsi barang, qty+unit — **TIDAK ADA kolom harga sama sekali**.
- Blok tanda tangan "SERAH TERIMA BARANG": Diserahkan (Pengirim/Checker Gudang), Diterima (nama jelas, tanggal & cap penerima) + footer cetak.

## Aksi / Method
- `mount(int $doId)` — guard `status` in `['CONFIRMED', 'SHIPPED']`, else redirect ke `dispatch.do.entry` + flash error.
- `do()` [Computed] — `DoDelivery::with('customer')` (TANPA eager-load lines — lines diambil terpisah lewat `lines()`).
- `lines()` [Computed] — **query khusus Sisi B**: `$this->do->lines()->where('status', '<>', 'CANCELLED')->with('order:id_order,co_no')->select(['id_line', 'delivery_id', 'co_order_id', 'item_description', 'qty_do', 'qty_unit', 'line_no'])->orderBy('line_no')->get()`. `select()` eksplisit ini SENGAJA tidak menyertakan `unit_price`/`subtotal` — komentar kode menegaskan "jangan tambah unit_price/subtotal ke select() ini, sekalipun cuma untuk ditampilkan nanti".
- `company()` [Computed] — `OrgCompany::where('company_code', 'TM')->first()`.
- `coRefs()` [Computed] — gabungan unik `order.co_no` dari `$this->lines`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\DoDelivery` → `tm2sdi.do_delivery`, relasi `customer` (lintas-DB ke `tm2mst.cust_customer`).
- `App\Models\Sdi\DoLine` → `tm2sdi.do_line` — diakses via query builder relasi dengan `select()` terbatas (Sisi B, no-harga).
- `App\Models\Master\OrgCompany` → tm2mst (koneksi `mst`) — lintas-DB, query terpisah.

## Alur / Business Logic Penting
- **Prinsip Sisi A/Sisi B**: dokumen internal (DO print) vs eksternal (Surat Jalan) berasal dari 1 objek data yang sama (`DoDelivery`, DISPATCH-00) tapi query cetak Surat Jalan secara eksplisit mengeluarkan kolom harga dari `select()` — bukan hanya menyembunyikan tampilan. Ini konsisten dengan pola proteksi data harga di modul lain (Procurement PV).
- Tema warna khusus hijau tua `#0d5c46` (beda dari DO internal `#2c3e69` navy dan Quotation `#2c3e69`).

## Catatan
Tidak ditemukan gap/TODO eksplisit dalam komentar kode file ini.
