# Kartu konsep: Dieline dan Master Card (sisi Sales)

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtsdi`/`ddtmst`, kode dengan baris, daftar berkas). Perilaku di layar browser TIDAK diuji.

- **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product (dieline lahir di sini) dan > Pro-Dev (Master Card dan koleksinya).
- **Nama kanonik**: Dieline = `iqp_part.dieline_spec` (JSON) + `flat_w/flat_h/flat_area`. Master Card = dua wujud (lihat Fakta): (a) `tm2mst.vw_master_card`, (b) koleksi `tm2mst.prod_master_card`.
- **Sinonim**: bentangan, flat size, dieline internasional, pisau/die, MC, kartu induk, Master Card Collection.
- **BUKAN sama dengan**: tipe produk (`prod_type`, kartu `10_kartu_tipe-produk.md`); layout/imposisi ProDev (`prod_layout_group`); artwork (gambar desain, kolom `artwork_path`).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Snapshot dieline di Inquiry | `InquiryWriter::persistParts` menyalin DSL `prod_type.dieline_spec` + `dims` isian user + `type_code` ke `iqp_part.dieline_spec`. Kunci yang ada: `dims, flat, vars, canvas, elements, type_code` (+ `gen` pada tipe tertentu). | FAKTA, `InquiryWriter.php:252-258`; `json_keys` pada `iqp_part` 1 dan 2 |
| Ukuran | `dims.product_length/width/height` dalam **mm** (P x L x T); `flat_w/flat_h/flat_area` kolom terpisah, **mm** (A.19). Dikonversi ke cm hanya saat membuat `prod_part` (`/10`). | FAKTA, `InquiryWriter.php:106-108` (`/ 10`); contoh `iqp_part` 1: 200 x 50 x 120, flat 515 x 190 |
| Kelengkapan ukuran (mockup) | 33 dari 36 part komponen punya P, L, T; 34 dari 36 punya flat W dan H; 31 punya artwork. Tiga part tanpa tinggi adalah tipe non-kotak (contoh BUKU memakai `page_count`). | FAKTA, query 2026-10-16 |
| Tipe yang boleh dipilih Sales | Hanya tipe AKTIF yang punya `dieline_spec.elements`: 27 dari 28 aktif (1 aktif tanpa spec). | FAKTA, `Sales.php:116-119`; query `prod_type` |
| Render | Pratinjau dieline hidup memakai `resources/js/tmdieline.js` (DSL TMDieline). | FAKTA bahwa berkasnya ada (`Test-Path`); "tidak diubah" DARI DOKUMEN (`claude_vital_flows.md`) |
| ProDev mengubah dieline | Panel "Part & Dieline" di ProDev menyimpan DUA ARAH: `iqp_part` (snapshot) dan `prod_part` (master); ukuran kepala Master Product ikut hanya bila produk ACTIVE dan part pertama. | FAKTA, `app/Services/ProDev/PartDieline.php:29-53`; aturan A.17 |
| Wujud (a) Master Card otomatis | View `vw_master_card` (69 baris) menggabungkan `prod_product` + `prod_part` + `prod_part_ink/_material/_process` + `prod_mastercard_signoff` (0 baris) menjadi kartu: kode item, no desain, dimensi, kode pisau, sambungan, finishing, isi per cetak, ukuran cetak, warna CS/KS, urutan pekerjaan. | FAKTA, `show create view vw_master_card`; `count(*)` |
| Wujud (b) koleksi kartu lama | `prod_master_card` 15 baris (XLSX 11, PHOTO 2, PDF 1, MANUAL 1), semuanya status `PRODUCT`, semuanya punya dimensi P/L/T; 14 punya `dieline_svg` dan flat; berkas `prod_master_card_file` 27 baris (SOURCE 6, PROOF 5, CUT_PLAN 2, ARTWORK 11, PHOTO 3). Kartu -> Inquiry (sumber `MASTER CARD`, 8 inquiry dummy) -> Master Product lewat `ProductMasterSync::fromInquiry`. | FAKTA, `show columns`, query 2026-10-16; `MasterCardService.php:17-19,328` |
| Berkas asli Jo | `docs/attachments/MC-*.xlsx`: 5 berkas. `docs/attachments/mastercard/`: 21 berkas (10 PDF, 2 PNG, 9 JPG dieline). Di `git status` awal sesi, PDF baru berstatus untracked (belum di git). | FAKTA, `Get-ChildItem`, `git status --short` |
| Master Product & kartu | `prod_product.card_status`: DRAFT 14.011, ACTIVE 14. Master Product resmi lahir saat WIN; sebelum itu ada produk DRAFT dari `ensureDraftProduct`. | FAKTA, query; `CoOrderBuilder.php:104-110`, `IqpInquiry.php:132-154` |

## Relasi
- **sumber**: dieline = `prod_type` (master) + isian Sales; Master Card (a) = Master Product + part master hasil ProDev/WIN; (b) = berkas lama Jo.
- **dipakai_oleh**: ProDev (imposisi membaca `dieline_spec`/flat), ProCost (geometri), Print Master Card, PPIC (mewarisi), Master Card Collection (menu Pro-Dev).
- **mengalir**: Inquiry (snapshot dieline) -> `prod_part` (sendToProDev menyalin ukuran dalam cm, bukan `dieline_spec`; salinan penuh terjadi di `ProductMasterSync::part`, baris 61-78, saat WIN) -> Master Card (a).
- **zona beku**: dieline Inquiry dan Master Card. Berkas asli Jo dipakai apa adanya; dieline tidak digambar ulang.

## Temuan (dicatat, tidak diperbaiki)
1. **"Master Card" punya dua wujud tanpa satu nama baku**: (a) tampilan dari product/part (`vw_master_card`) dan (b) koleksi kartu yang diimpor. Memori proyek menyebut Master Card "lahir di ProDev"; itu cocok dengan (a), sedangkan (b) lahir dari berkas luar lalu menjadi Inquiry. Butuh pernyataan Jo: dua-duanya disebut Master Card, atau (b) memakai nama lain.
2. **Kolom `prod_part.dieline_spec` baru terisi saat WIN.** `sendToProDev` hanya menyalin P/L/T (cm), `pack_qty`, `cfg_color`, finishing, artwork (`InquiryWriter.php:109-119`); tidak menyalin `part_type`, `dieline_spec`, flat. Di mockup, 2 dari 35 part master yang tertaut ke inquiry punya `part_type` NULL padahal `iqp_part.part_type`-nya `DC`. Dampak ke engine belum dibuktikan.
3. **Lampiran artwork selalu diberi `file_type='JPEG'`** walau upload boleh PNG (`InquiryWriter.php:144-146`; validasi `mimes:jpg,jpeg,png`, `routes/v2.php:276`).
4. **Tipe campuran di JSON**: `dims` menyimpan angka sebagai teks dengan desimal beragam (`"200"` vs `"40.0000"`); tidak merusak, tetapi perbandingan teks akan salah.
5. **Satu tipe aktif tanpa `dieline_spec`** tidak muncul di pilihan Sales (lihat juga kartu tipe produk, temuan 2 di sana).
6. **Berkas asli Jo belum di git** (PDF Master Card baru berstatus untracked pada awal sesi); jangan dianggap sudah aman di riwayat.

## Sudah ada? Dicari di mana, hasil
Dicari: `dieline`/`mastercard`/`master_card` di semua tabel dan view `ddt*` (`information_schema`), `app/`, `resources/views/v2`, `packages/`, `docs/attachments`. Hasil: seluruh konsep SUDAH ADA (dieline di `iqp_part`, Master Card (a) view, (b) koleksi). Tidak ada kolom atau tabel baru yang diperlukan untuk sisi Sales.
