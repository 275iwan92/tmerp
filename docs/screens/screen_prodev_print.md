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
Read-only, ditampilkan dari relasi ProdPart:
- Header: nama produk, customer, part name/code, dimensi (badan, bahan mentah, cetak).
- Section 1 Spesifikasi Teknis: kode/ukuran pisau, bentuk sambungan, warna sisi 1&2, pack qty, lidah (flap).
- Section 2 Imposition: dari `layoutGroup` terbaru (plano, metode, UP count, efisiensi %, status) — jika belum ada, tampil pesan "Belum ada hasil Engine Imposition tersimpan".
- Section 3 Routing: daftar `processes` (seq, proses, mesin).
- Section 4 Finishing: daftar `finishings` (jenis + catatan).
- Signatures: 5 role (DESAINER/Product Development, Sales Officer, PPIC, Quality Control, Kepala Pabrik) dari `ProdMastercardSignoff` per `product_id`.

## Aksi / Method
- `mount(part)` — set `$partId`.
- `part()` [Computed] — ProdPart with `product`, `processes.process`, `processes.machine`, `finishings.finishingType`, `attachments`, `layoutGroups`.
- `layoutGroup()` [Computed] — layout group terbaru (`sortByDesc('id_layout_group')->first()`).
- `company()` [Computed] — OrgCompany aktif pertama.
- `wizardSteps()` [Computed] — wizard-bar lintas modul (Inquiry → Development → Costing → Master Card → Analytics); `print:hidden`, tidak ikut tercetak. Cari `inquiry_id` asal via `IqpPart::where('prod_part_id', ...)` dengan filter inquiry confirmed & bukan DRAFT/CANCELLED/LOST.
- `signoffs()` [Computed] — gabungkan 5 role tetap dengan data `ProdMastercardSignoff` yang ada (by `role_name`), tampilkan status tanda tangan.

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdPart` → tm2mst (dengan banyak relasi).
- `App\Models\Master\ProdMastercardSignoff` → tm2mst, key `product_id`+`role_name`.
- `App\Models\Master\OrgCompany` → tm2mst.
- `App\Models\Sdi\IqpPart` → tm2sdi (lintas-DB logis, hanya untuk cari inquiry asal di wizard-bar).

## Alur / Business Logic Penting
Wizard-bar step "Costing" hanya aktif (punya url) jika ada Inquiry asal (`$origin`) — kalau part ini Mode #2 (manual/standalone), reason ditampilkan: "Part ini Mode #2 (manual/standalone) -- belum ada Inquiry utk dihitung Costing-nya." Step "Inquiry" SELALU punya url (baik ada origin maupun tidak, fallback ke form baru dengan `from_part`).

## Catatan
- Dokumen ini murni presentasi/cetak — semua data sudah dihasilkan sebelumnya di ProDev Engine; tidak ada logic hitung baru di sini.
