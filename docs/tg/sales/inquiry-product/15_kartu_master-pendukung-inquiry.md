# Kartu konsep: Master pendukung Inquiry New

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtmst`/`ddtsys`, kode dengan baris). Hanya master yang benar-benar dibaca layar `inquiry-new` / `InquiryWriter` / `InquiryAssembly`, ditelusuri dari `inquiry-new.blade.php` dan `Sales.php`. Customer dan Sales PIC punya kartu sendiri (`../master-data/`); tipe produk di `10_kartu_tipe-produk.md`.

- **Modul/sub-modul**: Sales > Inquiry & Quotation > Inquiry Product; pemilik data: Pro-Dev (Product Market), Master Data (kategori bahan), Setup.
- **Nama kanonik**: Product Market (`tm2mst.prod_market`), Ukuran pasaran (`prod_type_size` + `prod_type_size_val`), Parameter tipe (`prod_type_param`), Kategori bahan (`mat_category`), Jenis finishing (`ref_finishing_type`), Jenis sambungan (`mat_join_type`).
- **Sinonim**: Lookup Product Market, Kategori Produk -> Detail -> Ukuran Pasaran, Standard Size, rantai bahan Paper/Sheet, Jenis + Gramatur, finishing, Joint (Double Join).
- **BUKAN sama dengan**: `prod_category` (kategori Master Product, akun stok/COGS; layar `md-product-category`), `mat_raw` (bahan baku bermerek, dipilih Purchasing/ProDev, bukan Sales).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Product Market | `prod_market` 45 baris: 12 induk (Kategori Produk), 33 detail (Category Detail); semua detail punya `type_code` yang aktif di `prod_type`; semua detail punya `paper_category_id`; `photo_path` 0 terisi. Dibaca `Sales::productMarkets` (induk -> detail -> ukuran). | FAKTA, query; `Sales.php:135-148` |
| Alur isian | Kategori -> Detail -> Standard Size -> Tipe Produk terisi otomatis dan mengisi dimensi semua part. Pilihan Kategori/Detail/Ukuran TIDAK disimpan di inquiry; untuk dokumen tersimpan ditebak dari kecocokan ukuran (`marketOf`). Pilihan "Custom" menyimpan nama custom hanya sebagai catatan layar. | FAKTA, `inquiry-new.blade.php:58-79`, `Sales.php:197-216` |
| Ukuran pasaran | `prod_type_size` 162 baris (83 umum `market_id` NULL, 79 milik detail Product Market), `prod_type_size_val` 507 nilai (`value_num`/`value_str` per `param_key`); `prod_type_param` 328 baris. | FAKTA, query |
| Kategori bahan | `mat_category` 557 baris, 5 tingkat (20, 75, 103, 201, 158). Sales hanya memakai akar Paper dan Sheet: Jenis = tingkat 2, Gramatur = tingkat 3; yang tersimpan `iqp_part_material.category_id` = simpul terdalam, `rawmat_name` = rantai nama, `gram_weight` dari angka di nama. Merek dan ukuran plano SENGAJA kosong (diputuskan Purchasing/ProDev). | FAKTA, `Sales.php:61-76`, `InquiryPayload.php:107-141` |
| Pemakaian di data | `iqp_part_material` 129 baris: 43 punya `category_id`, 86 tidak; 80 punya `rawmat_id` (diisi engine/seeder). | FAKTA, query |
| Finishing | `ref_finishing_type` 8 baris aktif (Laminating Doff, Laminating Glossy, UV Spot, Hot Print, Varnish, Emboss, Deboss, Foil Stamping). Tersimpan ke `iqp_part_finishing` (nama dibekukan di `finishing_name`). | FAKTA, query; `InquiryWriter.php:325-340` |
| Jenis sambungan | `mat_join_type` 4 baris: STITCHING_SINGLE, STITCHING_DOUBLE, GLUE_SINGLE, GLUE_DOUBLE (label "kawat/lem 1 atau 2 lapis"). Dipakai pada rakitan (`iqp_part.join_type_id`), bukan pada part biasa. | FAKTA, query; `inquiry-new.blade.php:177`, `InquiryAssembly.php:35` |
| Konstanta | Batas ukuran artwork = `SysConstant::number('INQUIRY_ARTWORK_MAX_MB', 10)`. Kunci `INQUIRY_ARTWORK_MAX_MB` TIDAK ada di `sys_constant` mockup, jadi selalu bawaan 10 MB. | FAKTA, `routes/v2.php:276`; `select ... from sys_constant where constant_key='INQUIRY_ARTWORK_MAX_MB'` = 0 baris |
| Layar input master | Product Market: `v2:sales-product-market` (menu Pro-Dev). Kategori bahan: `v2:md-matcat`. Finishing, jenis sambungan, tipe produk, ukuran/parameter tipe: **tidak ditemukan item menu** (dicari label/target finish, join, product-type, dieline di `sys_app_menu`). | FAKTA untuk menu; v1 `pages/production/bop-rule` memakai finishing sebagai rujukan, bukan layar input |

## Relasi
- **sumber**: master netral `tm2mst` (diisi officer ProDev/Purchasing/admin, bukan Sales; aturan B.14: master hanya diubah user).
- **dipakai_oleh**: Inquiry New, Inquiry Revise (scope Material/Process/Join), Duplicate, ProDev (membaca kembali), Master Card (b) lewat `joinTypeId`/`category`.
- **mengalir**: nilai master dibekukan ke transaksi (`finishing_name`, `rawmat_name`, `dieline_spec`); perubahan master tidak merambat ke inquiry lama (A.13).
- **zona beku**: pilihan di form Inquiry (Inquiry Product).

## Temuan (dicatat, tidak diperbaiki)
1. **Tiga master tanpa layar input yang ditemukan**: `ref_finishing_type`, `mat_join_type`, dan `prod_type` / `prod_type_size` / `prod_type_param` (butir 7 CLAUDE.md). Belum dicari di Setup lintas-menu selain kata kunci di atas; mungkin hanya terisi lewat migration/seeder.
2. **Konstanta `INQUIRY_ARTWORK_MAX_MB` tidak punya baris** di `sys_constant` (selalu 10) sehingga tidak bisa diubah dari layar Constants.
3. **`Sales::masters()['materials']` memuat seluruh `mat_raw` aktif-confirmed setiap kali `masters()` dipanggil**, padahal Inquiry New hanya memakai `finishing_types` (`inquiry-new.blade.php:19`, `inquiry-revise.blade.php:31`); satu-satunya pemakai `materials` adalah lookup `paper` (`Lookups.php:19`). Beban baca tidak berguna bila lookup itu tidak dipakai; BELUM diukur dan BELUM dicek apakah lookup `paper` dipakai layar lain.
4. **Dua cara menyimpan bahan**: Sales memilih kategori (`category_id`), engine/seeder mengisi `rawmat_id`; di data lebih banyak baris bermaterial pasti daripada berkategori (80 vs 43). Tidak salah, tetapi pembaca harus menangani keduanya (`Sales::materialRows` sudah melakukannya, baris 93-108).

## Sudah ada? Dicari di mana, hasil
Dicari: tabel/kolom `market`, `size`, `finishing`, `join`, `category`, `gram` di `ddtmst`; menu `sys_app_menu`; `grep` `app/Support/V2`, `resources/views/v2`, `packages`. Hasil: semua master SUDAH ADA; tidak ada tabel baru yang diperlukan. Kekurangan hanyalah layar input (temuan 1) dan baris konstanta (temuan 2).
