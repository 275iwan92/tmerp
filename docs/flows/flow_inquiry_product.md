---
name: flow_inquiry_product
description: Alur Inquiry Product -- Hub sampai HPP published & Nota, termasuk 3 doc-type (BARU/REVISI/REKALKULASI) dan titik masuk ke ProDev/Costing/Quotation.
---

# Alur Inquiry Product

**Modul**: Inquiry Product (`tm2sdi`, koneksi `sdi`)
**Prefix route**: `/inquiry` (name prefix `inquiry.*`)

## Ringkasan
Inquiry Product adalah fondasi seluruh order di ERP ini — semua Customer Order pada akhirnya bermula dari 1 Inquiry yang sudah punya harga (`costing.published_flag=true`). Sales membuat/mengelola Inquiry lewat **Hub** (worklist + gerbang pemilihan jenis dokumen), mengisi spesifikasi di salah satu dari 3 layar doc-type (**BARU**/**REVISI**/**REKALKULASI**, masing-masing file Livewire terpisah — bukan mode di 1 form, CLAUDE.md §4), lalu meng-*confirm* untuk mengunci spec dan mengirimnya ke hilir: **Imposition/ProDev** (opsional, hitung layout produksi fisik) dan **Costing** (wajib, hitung HPP + ladder harga). Setelah Direksi *publish* harga (Sisi A -> Sisi B), Sales bisa cetak **Nota** untuk customer dan lanjut ke modul **Quotation** (di luar Inquiry Product).

## 3 Doc-Type — kapan pilih yang mana
| Doc-type | Kapan dipakai | Spec berubah? | Layar |
|---|---|---|---|
| **BARU** | Produk benar-benar baru, Master Product belum ada | N/A (dari nol) | `form.blade.php` |
| **REKALKULASI** | Repeat order, spec produk **SAMA PERSIS** dengan inquiry sebelumnya — cuma qty dan/atau harga bahan berubah | Tidak (100% reuse, read-only) | `recalc.blade.php` |
| **REVISI** | Reorder tapi spec **BERUBAH** (dimensi/bahan/artwork/warna/proses) — mis. customer minta tambah logo, ganti bahan, ganti ukuran | Ya, sebagian (scope manual) | `revise.blade.php` |

Pembeda kunci: REKALKULASI tidak punya satu pun field spec yang editable (kalau ternyata ada yang berubah, harus pindah ke REVISI). REVISI wajib pilih **scope** manual (MATERIAL/DIMENSION/ARTWORK/COLOR/PROCESS) — sistem tidak menebak apa yang berubah, supaya tidak memicu imposisi/proof ulang yang mahal secara tidak perlu. Keduanya sama-sama pola **copy-record + versi/dokumen baru** (bukan edit in-place inquiry lama) — REVISI eksplisit menaikkan `revision_no` dan menandai inquiry lama `CANCELLED`/superseded; dokumen hilir (Quotation/CO) yang sudah pakai versi lama tetap aman karena prinsip snapshot beku.

## Tahap -> Layar -> Aktor

| # | Tahap | Layar | Route | Aktor |
|---|---|---|---|---|
| 1 | Landing, pilih jenis dokumen / buka tersimpan | `inquiry/hub.blade.php` | `inquiry.hub` (`/inquiry`) | Sales |
| 2a | Isi spec Inquiry BARU | `inquiry/form.blade.php` | `inquiry.form` (`/inquiry/new`) | Sales |
| 2b | Isi Hitung Ulang Harga (repeat order, spec sama) | `inquiry/recalc.blade.php` | `inquiry.recalc` (`/inquiry/recalc`) | Sales |
| 2c | Isi Ubah Spesifikasi (reorder, spec berubah) | `inquiry/revise.blade.php` | `inquiry.revise` (`/inquiry/revise`) | Sales |
| 3 | (Opsional) Kirim part ke ProDev / hitung layout imposisi | `inquiry/imposition-list.blade.php` -> `inquiry/imposition.blade.php` | `inquiry.imposition.list`, `inquiry.imposition` | Product Designer / ProDev |
| 4 | Hitung HPP & ladder harga per qty-tier | `inquiry/costing-list.blade.php` -> `inquiry/costing.blade.php` | `inquiry.costing.list`, `inquiry.costing` | Costing officer |
| 5 | Approve & publish harga (deklasifikasi ke Sales) | (di luar file Inquiry Product — approval Direksi, grid/costing) | — | Direksi |
| 6 | Cetak dokumen penawaran untuk customer | `inquiry/nota.blade.php` | `inquiry.nota` (`/inquiry/{inquiry}/nota`) | Sales |
| 7 | Lanjut ke Quotation (modul lain) | `quotation/entry.blade.php` (dari tombol "+ Tambahkan ke Quotation" di Costing) | `quotation.entry` | Sales |

## Alur Detail

1. **Hub** (`inquiry.hub`): Sales pilih salah satu dari 3 kartu jenis dokumen. Untuk **BARU**, tombol "+ Buat data baru" langsung membuka `inquiry.form` kosong. Untuk **REKALKULASI**/**REVISI**, "+ Buat data baru" membuka modal picker yang memilih inquiry existing sebagai sumber salinan (proksi Master Product — modul Master Product/Quotation-source belum sepenuhnya menggantikan peran ini, lihat `screen_inqprod_grid.md`), lalu redirect ke `inquiry.recalc`/`inquiry.revise` dengan param `source_id`. "📂 Pilih data tersimpan" membuka worklist bawah — klik 2x baris routing otomatis sesuai `inquiry_type` tersimpan (RECALC->`inquiry.recalc`, REVISE->`inquiry.revise`, default->`inquiry.form`).

2. **Isi spec** (salah satu dari 3 layar, tergantung doc-type):
   - **BARU** (`inquiry.form`): header (customer/PIC Sales/Kategori Produk/Tipe Produk level-Inquiry), Qty Order berjenjang (tier, tepat 1 Utama), Part & Dieline (gerbang Peran Part -> Dimensi & Preview Dieline live, bahan, warna, finishing tambahan, artwork + saran otomatis), Tooling, Biaya Eksternal. Simpan bebas berkali-kali (`save()`) selama belum `stat_confirm`.
   - **REKALKULASI** (`inquiry.recalc`): reuse 100% spec dari inquiry baku (`cloneStructureFrom()`), hanya edit `qty` + `deadline_quotation`; tampilkan preview artwork/diecut + tabel perbandingan harga bahan (baku vs kini, dari `mat_raw`) supaya officer yakin tidak salah pilih inquiry sumber.
   - **REVISI** (`inquiry.revise`): pilih scope revisi (checkbox multi), isi hanya blok yang di-scope (field di luar scope tetap reuse read-only dari baku), matriks konsekuensi otomatis tampilkan apa yang wajib diproses ulang (imposisi/proof/costing/due-date reset).
   - Semua 3 layar punya tombol **Simpan Draft/Simpan** (boleh berkali-kali) dan **Confirm/Submit ke Costing** (mengunci form total via `stat_confirm=true`, `sub_state` jadi `SUBMITTED`). Untuk REVISI/REKALKULASI, submit inilah titik dokumen baru resmi lahir (nomor `inquiry_no` baru, revision_no naik untuk REVISI).

3. **Imposition (opsional, tapi disarankan untuk part DC yang perlu layout produksi)**: dari worklist `inquiry.imposition.list` (semua inquiry confirmed + punya part), buka `inquiry.imposition` per-inquiry. ProDev pilih part aktif, `ImpositionEngine` hitung UP/orientasi optimal dari ukuran plano (`mat_plano_size`), officer confirm hasilnya (`stat_confirm` pada `IqpLayout`) — hasil confirmed auto-mengalir ke `iqp_costing_part` (plano/cut) kalau baris costing untuk part itu sudah ada. Terpisah dari alur Mode #1 "Kirim ke ProDev" (`sendToProDev()` di `form.blade.php`) yang membuat `prod_part` sungguhan untuk Master Card/routing produksi lengkap — Imposition di sini murni hitung layout cetak, bukan development produk penuh.

4. **Costing (wajib sebelum bisa Quotation)**: dari worklist `inquiry.costing.list`, buka `inquiry.costing` per-inquiry. Officer Costing tambah baris part costing (manual atau "Ambil Data" dari `prod_layout_group` ProDev confirmed), isi parameter biaya, jalankan `CostingEngine::run()` (`hitung()`), atur toleransi/margin/rounding per qty-tier, lalu `submitToDireksi()` (`doc_status` DRAFT->CALCULATED->PENDING_APPROVAL).

5. **Approve & Publish (Direksi)**: di luar cakupan file-file yang didokumentasikan sesi ini — Direksi meng-approve `iqp_costing` (PENDING_APPROVAL -> PUBLISHED). Ini adalah titik deklasifikasi Sisi A (Costing/Direksi, rahasia HPP) ke Sisi B (Sales, cuma lihat harga jual final) — CLAUDE.md §4.

6. **Nota**: begitu `costing.published_flag=true`, Sales bisa cetak `inquiry.nota` — dokumen 2 halaman (penawaran harga per tier + spesifikasi teknis + dieline ukuran penuh + tanda tangan). Sebelum published, halaman ini tetap bisa dicetak tapi harga disembunyikan (murni spesifikasi teknis) — gate `priced()` di `nota.blade.php`.

7. **Quotation**: dari layar Costing, tombol "+ Tambahkan ke Quotation" (`addToQuotation()`) memanggil `QuotationBuilder::findOrCreateDraft()`/`addInquiry()` lalu redirect ke `quotation.entry` — mulai dari sini proses berpindah ke modul Quotation (di luar Inquiry Product), yang lanjut ke Customer Order sesuai alur `project_quotation_co_flow.md`.

## Wizard-bar lintas modul
`form.blade.php` (mode `crossView=1`), `costing.blade.php`, dan layar ProDev (`production/prodev.blade.php`) berbagi 1 pola navigasi **wizard-bar** (`<x-prodev.wizard-bar>`) dengan step tetap: 1. Inquiry -> 2. Development (ProDev) -> 3. Costing -> Master Card -> Analytics. Klik ke layar lain dari sini SELALU membuka mode **read-only lintas-navigasi** (Inquiry: ringkasan via `<x-inquiry.readonly-summary>`; Costing: ringkasan status+harga tanpa breakdown) — tujuannya supaya siapa pun bisa cek konteks penuh 1 job dari mana saja tanpa risiko mengubah data di luar layar "pemilik"-nya.

## Catatan Arsitektur Penting
- **Snapshot beku** (CLAUDE.md §4) adalah prinsip yang mengikat seluruh alur ini: `iqp_part.dieline_spec` membekukan DSL `prod_type` + dims yang dipakai user saat itu; REVISI membuat versi baru alih-alih mengedit in-place justru supaya snapshot inquiry lama (dan dokumen hilir yang memakainya) tidak pernah rusak.
- **Peran Part** (gerbang di `form.blade.php`, level BARU) dan **Scope Revisi** (gerbang di `revise.blade.php`) sama-sama contoh pola "pilih dulu, baru form aktif" — beda tujuan: Peran Part menentukan struktur part (Body/Lid/dst) sebelum dieline live; Scope Revisi menentukan blok field mana yang boleh diedit.
- Costing Mode #1 (Ambil Data dari ProDev) vs Mode #2 (Manual) di layar Costing WAJIB menghasilkan HPP sama untuk geometri setara — keduanya 2 jalur input ke `CostingEngine` yang sama, bukan 2 logika harga berbeda (CLAUDE.md §4).
- Modul Analytics (langkah terakhir wizard-bar) belum dibangun — semua link `null` dengan `reason` penjelas.

## Layar terkait (dokumentasi detail per-layar)
- `docs/screens/screen_inqprod_grid.md`
- `docs/screens/screen_inqprod_new.md`
- `docs/screens/screen_inqprod_recalc.md`
- `docs/screens/screen_inqprod_revise.md`
- `docs/screens/screen_inquiry_imposition_list.md`
- `docs/screens/screen_inquiry_imposition.md`
- `docs/screens/screen_procost_grid.md`
- `docs/screens/screen_procost_form.md`
- `docs/screens/printout_inqprod_nota.md`
