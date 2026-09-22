---
name: screen_prodev_print
description: Dokumen cetak Master Card -- hasil print out data prod_part & relasinya yang dikerjakan di layar ProDev, bukan layar input.
---

# Master Card (Cetak)

**File**: `resources/views/pages/production/prodev-print.blade.php`
**Route**: `production.prodev.print` (`/production/prodev/{part}/print`, param `part` numeric)
**Koneksi DB utama**: mst (tm2mst) — model `App\Models\Master\ProdPart`, `ProdMastercardSignoff`, `OrgCompany`
**Modul**: Production Scheduling & Product Development

## Tujuan
Master Card **BUKAN layar input** — ini hasil print out dari data `prod_part` + relasinya yang dikerjakan di layar ProDev (`prodev.blade.php`). Mengikuti pola dokumen cetak standar (`docs/printnotastandard.md`), komponen reusable `resources/views/components/print/*`. Berlaku sebagai acuan resmi produksi setelah ditandatangani (sign-off) seluruh pihak terkait.

## Field & Kolom
Read-only, ditampilkan dari relasi ProdPart. **Rebuild 2026-09-09** mendekati `docs/mockups/mockup_mastercard.jpg` (layout 2-kolom):
- Header: No. Desain, tgl cetak.
- Kolom kiri (tabel spesifikasi ringkas): Nama Barang, Nama Pelanggan, Part (nama+kode), Dimensi P x L x T, Kode Pisau, Ukuran Pisau, **Material** & **Kualitas** (READ-ONLY dari `iqp_part_material` Inquiry asal kalau part ini Mode #1 — ProdPart sendiri TIDAK punya field material; Mode #2 tampil "—" + keterangan), Ukuran Bahan, Ukuran Cetak, Pack Qty, Jenis Finishing (nama-nama digabung).
- Kolom kanan (`mc-visual`): gambar artwork resmi (`prod_part_artwork`) atau fallback attachment JPEG ProDev (`prod_part_attachment`, pola sama tab Master Card WO Detail) — placeholder jujur kalau belum ada gambar sama sekali. Caption di bawah gambar: plano/UP/metode dari `layoutGroup` terbaru.
- Baris bawah 2-kolom (`.print-two`): **Warna Pertama** (tabel Unit 1-4 dari `color_set_1`) bersebelahan dengan **Urutan Pekerjaan (Routing)** (`processes`, seq+nama proses+mesin dalam 1 baris).
- Section "Catatan Finishing" (opsional, cuma muncul kalau ada `finishings` yang punya `notes` terisi).
- Signatures: 5 role (DESAINER/Product Development, Sales Officer, PPIC, Quality Control, Kepala Pabrik) dari `ProdMastercardSignoff` per `product_id`.

## Aksi / Method
- `mount(part)` — set `$partId`.
- `part()` [Computed] — ProdPart with `product`, `processes.process`, `processes.machine`, `finishings.finishingType`, `attachments`, `layoutGroups`.
- `layoutGroup()` [Computed] — layout group terbaru (`sortByDesc('id_layout_group')->first()`).
- `company()` [Computed] — OrgCompany aktif pertama.
- `originIqpPart()` [Computed] (baru 2026-09-09) — reverse-lookup `IqpPart` (dgn `materials`) via `prod_part_id`, filter inquiry confirmed & bukan DRAFT/CANCELLED/LOST. Dipakai bareng oleh `wizardSteps()` (utk `inquiry_id`) dan `originMaterial()` — dulu 2 query terpisah, sekarang 1.
- `originMaterial()` [Computed] (baru) — material primary (atau pertama) dari `originIqpPart`, null kalau Mode #2.
- `officialArtwork()`/`fallbackArtwork()` [Computed] (baru) — sumber gambar kolom visual, pola sama persis `wo-entry.blade.php::officialArtwork()`/`prodevArtwork()`.
- `wizardSteps()` [Computed] — wizard-bar lintas modul (Inquiry → Development → Costing → Master Card → Analytics); `print:hidden`, tidak ikut tercetak.
- `signoffs()` [Computed] — gabungkan 5 role tetap dengan data `ProdMastercardSignoff` yang ada (by `role_name`), tampilkan status tanda tangan.

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdPart` → tm2mst (dengan banyak relasi).
- `App\Models\Master\ProdMastercardSignoff` → tm2mst, key `product_id`+`role_name`.
- `App\Models\Master\OrgCompany` → tm2mst.
- `App\Models\Sdi\IqpPart` (dgn `materials`) → tm2sdi (lintas-DB logis) — cari inquiry+material asal.
- Raw query `DB::connection('tm2mst')->table('prod_part_artwork')` (baru) — tidak ada Model Eloquent utk tabel ini, ikut pola WO Detail.

## Alur / Business Logic Penting
- Wizard-bar step "Costing" hanya aktif (punya url) jika ada Inquiry asal (`$origin`) — kalau part ini Mode #2 (manual/standalone), reason ditampilkan. Step "Inquiry" SELALU punya url (fallback ke form baru dengan `from_part`).
- **ProdPart TIDAK PUNYA field material/gramatur** (`app/Models/Master/ProdPart.php` `$fillable`, dicek 2026-09-09) — itu domain Inquiry, bukan ProDev (CLAUDE.md §4 "2 mode independen"). Material di layar ini murni REFERENSI read-only dari Inquiry asal (Mode #1), sama prinsip panel "Referensi Inquiry Asal" yang sudah ada di `prodev.blade.php` — tidak pernah bisa diedit dari sini.
- Kotak visual TIDAK render dieline generated (`TMDieline.render()`) — `ProdPart.dieline_ref` cuma teks referensi, bukan DSL spec lengkap spt `IqpPart.dieline_spec`, jadi tidak ada data untuk dirender. Kalau perlu preview dieline sungguhan, itu ada di layar Inquiry (`printout_inqprod_nota.md`), bukan di sini.

## Catatan
- Dokumen ini murni presentasi/cetak — semua data sudah dihasilkan sebelumnya di ProDev Engine; tidak ada logic hitung baru di sini.
- **Gap ditemukan+diperbaiki 2026-09-09**: file ini sebelumnya tidak punya `<style>` lokal sama sekali (beda dari nota/quotation/DO print lain) — `table.items`/`.desc`/`.amt`/`.cap` tampil tanpa styling apapun sebelum rebuild ini.
- Kotak visual mockup menampilkan pola/warna dekoratif ilustratif (bukan data nyata) — SENGAJA tidak ditiru; layar ini pakai artwork/attachment asli dari database, placeholder jujur kalau belum ada.
- **Format A4 landscape + hitam-putih PERMANEN (2026-09-09, permintaan user)** — beda dari default `docs/printnotastandard.md` §1 (keluarga "Nota" = A4 portrait) dan beda juga dari pola `.bw`/"hemat tinta" Nota Inquiry yang OPSIONAL via tombol toggle. Di sini bukan toggle — `theme` di-set langsung ke hex hitam/putih (`#000000`/`#ffffff` utk semua slot warna), BUKAN pakai class `.bw`, karena inline `style` (dari prop `theme`, dirender di root `.print-doc`) selalu menang lawan class-selector rule untuk custom property yang sama — override lewat class tidak akan efektif di sini. Kelas `landscape` + `@media print { @page{size:A4 landscape} }` lokal ke file ini saja, TIDAK menyentuh `@page` global `print/document.blade.php` (dokumen Nota lain tetap portrait). **Satu-satunya elemen yang SENGAJA tetap berwarna**: gambar artwork/preview di kotak visual (`.mc-visual img`) — tidak ada `filter:grayscale` di situ.
