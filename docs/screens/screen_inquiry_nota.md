---
name: screen_inquiry_nota
description: Cetak Nota Inquiry (dokumen pelanggan) — harga per jenjang qty (bila sudah published) + spesifikasi teknis + dieline.
---

# Nota Inquiry (Cetak)

**File**: `resources/views/pages/inquiry/nota.blade.php`
**Route**: `inquiry.nota` (`/inquiry/{inquiry}/nota`)
**Koneksi DB utama**: sdi (tm2sdi), baca `org_company` dari mst (tm2mst)
**Modul**: Inquiry Product

## Tujuan
Dokumen cetak 2 halaman untuk dikirim ke customer: halaman 1 berisi penawaran harga (per jenjang qty tier) + spesifikasi produk ringkas per part; halaman 2 berisi dieline ukuran penuh per part, syarat & ketentuan, dan kolom tanda tangan konfirmasi customer. Mengikuti `printnotastandard.md` (komponen `resources/views/components/print/*`). Menegakkan aturan Sisi A/Sisi B (CLAUDE.md §4): harga jual HANYA ditampilkan kalau `costing.published_flag = true` — kalau belum, halaman 1 menampilkan pesan peringatan "Harga belum tersedia" dan dokumen jadi murni spesifikasi teknis.

## Field & Kolom
Bukan form input — halaman cetak read-only. Toggle UI: `bw` (bool, Url-bound `as: bw`) — mode hemat tinta (grayscale) vs versi warna.

Data yang ditampilkan:
- Header: perusahaan (`OrgCompany` code TM), no. inquiry, tanggal, revisi, badge tipe dokumen (NEW/RECALC/REVISE/CANCELLED), badge PROSPECT, deadline quotation.
- Parties: Kepada Yth (customer/prospect + alamat + kontak), Produk (nama produk draft + jumlah part), PIC Sales (+ sumber + mata uang).
- Section 1 "Penawaran Harga": hero tier utama (qty, harga incl/excl PPN, nilai order, delta vs target customer) + tabel semua jenjang qty tier.
- Section 2 "Spesifikasi Produk": per part — dimensi P×L×T, lidah lem, bentangan flat + luas, material + gramatur, warna/tinta, proses/finishing, mini dieline preview.
- Section 3 "Catatan": catatan sales + info tambahan (no. desain, sumber material, sifat pekerjaan).
- Halaman 2: dieline ukuran penuh per part (render TMDieline), syarat & ketentuan (termasuk biaya tooling kalau ada), blok tanda tangan (Customer/Sales/Sales Manager).

## Aksi / Method
- `mount(int $inquiry)` — set `inquiryId`.
- `#[Computed] inquiry()` — load `IqpInquiry` dengan eager-load `customer`, `parentProduct`, `parts.materials`, `parts.processes`, `tiers`, `costing.priceTiers`, `toolings`.
- `#[Computed] company()` — `OrgCompany` where `company_code='TM'`.
- `#[Computed] priced()` — true hanya jika `costing.published_flag` true DAN `priceTiers` tidak kosong (gate Sisi A/B).
- `#[Computed] tierRows()` — delegasi ke `TierPricingResolver::rows()`.
- `#[Computed] primaryRow()` — delegasi ke `TierPricingResolver::primary()`.
- `#[Computed] toolingTotal()` — sum `inquiry.toolings.cost`.
- `#[Computed] partsData()` — array data part terformat untuk render (termasuk `spec`/`dims` mentah untuk TMDieline render via JS).
- Tombol toolbar: toggle `bw`, link ke Costing, link ke Inquiry Hub.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (+ relasi `parts`, `parts.materials`, `parts.processes`, `tiers`, `costing`, `costing.priceTiers`, `toolings`) — semua dalam tm2sdi.
- `App\Models\Master\OrgCompany` — koneksi mst (tm2mst), query terpisah (pola 1).
- `App\Services\Inquiry\TierPricingResolver` — service untuk resolve baris harga per tier.
- Komponen Blade: `resources/views/components/print/*` (document/sheet/header/parties/party/section/signatures/signature/footer).
- JS: `window.TMDieline.render()` (resources/js/tmdieline.js) dipanggil dari `<script>` inline memakai `PARTDATA` (dari `partsData()`, di-`@json`) untuk render dieline mini (halaman 1) dan besar (halaman 2) — DSL yang sama dipakai Inquiry New, bukan reimplementasi lokal.

## Alur / Business Logic Penting
- **Gate harga (Sisi A/B)**: computed `priced()` adalah satu-satunya titik kontrol — kalau `false`, section 1 menampilkan warning dan dokumen jadi murni spesifikasi teknis (tidak ada angka harga bocor sebelum Direksi publish).
- Mode hemat tinta (`bw`) diimplementasikan via CSS class modifier `.bw` (bukan override warna variabel jadi hitam pekat) — desain sengaja begini agar hasil print tetap bagus.
- 3 tipe dokumen (NEW/RECALC/REVISE) memakai TEMPLATE BLADE YANG SAMA — komentar kode menyebut mockup terpisah (`inquiry_nota_new/recalc/revise.html`) ternyata strukturnya identik, beda cuma warna badge tipe.
- Dieline di-render via JS di sisi klien (bukan server-side SVG) memakai data `dieline_spec` mentah per part (snapshot asli), bukan digambar ulang dari `part_type` generik.

## Catatan
Tidak ada TODO/gap eksplisit tersisa di file ini — komentar kode menjelaskan keputusan desain (bw mode, template sharing 3-tipe) bukan pekerjaan belum selesai.
