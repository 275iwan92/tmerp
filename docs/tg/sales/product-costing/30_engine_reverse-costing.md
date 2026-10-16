# Kartu ENGINE: Reverse Costing

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/Costing/ReverseCostingService.php` dibaca penuh (198 baris). Tes dijalankan: `CostingInstantModeTest` (2 lulus, 14 assert; satu tes mencakup Reverse). Tidak ada angka harga di kartu ini (angka HPP tidak berubah, hanya diasersi tes).

- **Nama kanonik**: Reverse Costing. **Kelas**: `App\Services\Costing\ReverseCostingService`. **Registry**: key `reverse-costing`, mode `auto`, grup Pro-Cost. **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Cost (menu Pro-Cost Instant, `v2:sales-procost-instant`).
- **Status beku**: ProCost dan Inquiry/ProDev = zona beku. Tiga keputusan Jo di komentar kode (jangan tanya ulang): Inquiry hasil Reverse selalu tipe BARU; ProDev (layout group) selalu lahir DRAFT; angka costing yang ada tidak berubah.
- **Sinonim**: tombol Reverse, Instant -> Integrated, "balik silsilah".
- **BUKAN sama dengan**: pembatalan costing atau `reversed_at` pada Stock Reversal (modul Inventory, hanya nama mirip).

## Pemicu (tidak ada background / jadwal)
Tidak ada di `routes/console.php`, Jobs, Observers. FAKTA: satu-satunya pemanggil adalah tombol **Reverse** di `resources/views/pages/inquiry/costing-instant.blade.php:467` (`wire:click="reverse"`, konfirmasi `wire:confirm`, nonaktif bila dokumen read-only) -> `reverse()` baris 237 -> `ReverseCostingService::reverse` (baris 240). Sukses mengarahkan ke `inquiry.costing` untuk Inquiry baru; gagal menampilkan `engineError`.

## Alur (kembaran teks, tanpa diagram: satu jalur lurus dengan satu transaksi)
1. Periksa prasyarat: mode INSTANT, `customer_id` terisi, `product_name_draft` terisi, minimal satu part. Gagal = `RuntimeException` dengan pesan Indonesia.
2. Buka dua transaksi (`tm2mst`, `tm2sdi`).
3. Buat nomor Inquiry, lalu Master Product DRAFT di `tm2mst` (kode `REV-<nomor inquiry>`).
4. Buat Inquiry BARU (`source = REVERSE`, `sub_state = PRICE_CALCULATED`).
5. Per part costing: buat `prod_part`, `prod_layout_group` DRAFT (+ pivot `prod_layout_group_part`), `iqp_part`; tautkan balik ke `iqp_costing_part` (`part_id`, `prod_layout_group_id`, `prodev_part_id`). `data_source` part TETAP manual; isian `i_*` dan `c_*` tidak disentuh.
6. Per tier harga: buat `iqp_tier` (qty, harga excl/incl dari tier costing) dan tautkan `tier_id`.
7. Balik costing: `inquiry_id` terisi, `mode` menjadi INTEGRATED, `reversed_at/by` diisi.
8. Commit `tm2mst` dulu, lalu `tm2sdi`; gagal di tengah = rollback keduanya (lihat Temuan 1).
**Titik keputusan officer (A.7)**: Reverse butuh klik dan konfirmasi manusia; ProDev hasilnya hanya bisa di-Confirm manusia.

## Input / Output
- **Input**: `tm2sdi.iqp_costing` (mode, `customer_id`, `product_name_draft`, dimensi), `iqp_costing_part` (tipe part, dimensi, plano, `i_cut_per_plano`, `qty_per_set`, `no_urut`), `iqp_costing_price_tier`.
- **Output (menulis)**: `tm2mst.prod_product`, `prod_part`, `prod_layout_group`, `prod_layout_group_part`; `tm2sdi.iqp_inquiry`, `iqp_part`, `iqp_tier`; memperbarui `iqp_costing_part` dan `iqp_costing_price_tier` (hanya kolom tautan) dan `iqp_costing` (`inquiry_id`, `mode`, `reversed_*`).
- **Siapa boleh menimpa**: tidak ada jalur kembali (INTEGRATED tidak bisa dibalik ke INSTANT lewat kode ini; tombol hanya untuk INSTANT). Master Product/ProDev hasilnya diedit officer seperti biasa.

## FORMULA
Tidak ada rumus harga. Hanya pemetaan satuan (FAKTA, baris 102-151): `cm_length = i_dim_panjang_mm / 10` (4 desimal), `cm_width = i_dim_lebar_mm / 10`; plano mm = plano cm * 10; `up_count = max(1, round(i_cut_per_plano))`; `qty_up_in_group = 1`; `flat_w = dimP (mm)`, `flat_h = dimL (mm)`, `flat_area = dimP * dimL`; `part_code = 'P' . no_urut`; `mastercard_code = product_code . '-' . no_urut`. Dimensi part dipakai bila ada, kalau tidak dimensi header costing.

## Kasus tepi
- Costing sudah INTEGRATED: ditolak ("cuma berlaku utk costing mode INSTANT").
- Tanpa customer atau nama produk atau part: ditolak dengan pesan.
- Reverse kedua kali pada costing yang sama: ditolak oleh pemeriksaan mode.
- Dimensi kosong: `cm_length/width` 0, `flat_*` null.
- Data ddt* sekarang: 9 costing INSTANT, 17 INTEGRATED, 0 punya `reversed_at`, 0 inquiry berasal `REVERSE` (FAKTA, query 2026-10-16), jadi jalur ini hanya tersentuh tes.

## Pemakai (relasi)
- **sumber**: Pro-Cost Instant. **hilir**: Inquiry (daftar), ProDev (layout DRAFT yang harus di-Confirm manusia), Master Product (DRAFT, `card_status` belum approve), Quotation (butuh costing PUBLISHED, A.1), Material Planning (pre-booking membaca costing PUBLISHED tanpa CO). Aturan: Quotation wajib integrated; Reverse adalah jalan masuk costing instant ke jalur itu.

## Butir terbuka
Lihat `docs/ENGINE-ATTENTION.md`: tidak ada butir khusus Reverse (dibaca penuh).

## Temuan (dicatat, tidak diperbaiki)
1. Komentar kelas (baris 37-40) mengakui dua transaksi terpisah: bila `tm2sdi` gagal commit setelah `tm2mst` commit, baris master tertinggal; `rollBack` pada koneksi yang sudah commit tidak lagi membatalkan baris itu (perilaku Laravel, tidak diuji). Kode: commit `tm2mst` baris 187, `tm2sdi` baris 188.
2. Inquiry hasil Reverse langsung `stat_confirm_sales = true` dan `stat_confirm_prodev = true` (baris 94-95), padahal ProDev yang dibuat berstatus DRAFT (baris 126) dan komentar kelas menyatakan "Confirmed cuma lewat klik manusia". Tanda hulu A.20 sudah menyala sebelum ProDev di-Confirm. Perilaku tanda ini terhadap `InquiryStage::syncProdevForParts` BELUM diuji (fungsi itu menghitung ulang tanda saat ProDev disimpan). Keputusan Jo diperlukan bila ini tidak disengaja.
3. `prod_layout_group.up_count` diisi dari `i_cut_per_plano` (baris 125). Di tempat lain `i_cut_per_plano` = lembar cetak per plano dan `i_isi_per_lb` = UP per lembar cetak (`ProCostContextService.php:110-113` dan `CostingEngine.php:80-81`); Material Planning memakai `up_count` layout CONFIRMED sebagai UP per plano (`MaterialPlanningEngine.php:554`). Bila `i_isi_per_lb > 1`, `up_count` hasil Reverse lebih kecil daripada UP sebenarnya. Dampak nyata baru terjadi setelah layout di-Confirm manusia (sampai itu layout DRAFT tidak dipakai Material Planning). DUGAAN, belum diuji.
4. `flat_w/flat_h/flat_area` `iqp_part` diisi dari dimensi produk (P x L), padahal di jalur Inquiry kolom itu berisi bentangan datar hasil TMDieline. DUGAAN salah arti; belum dicek terhadap pemakai kolom itu.
5. Part Reverse tidak membawa `dieline_spec`/`type_code`/artwork (tidak ada di `IqpPart::create`), sehingga Master Card yang lahir dari alur ini belum punya dieline sampai dilengkapi di ProDev (A.17).
6. Registry (`EngineRegistry.php:322`) menulis "Pro-Dev layout, Created as Draft; only a person can Confirm it": benar untuk layout, tetapi lihat Temuan 2 untuk tandanya.

## Tes
`tests/Feature/CostingInstantModeTest.php::test_instant_screen_reproduces_legacy_raja_bakery_and_reverse`: setelah Reverse, `mode` = INTEGRATED, Inquiry tipe `NEW`, dan HPP tier utama sama dengan sebelum Reverse. Tidak ada tes khusus untuk: kegagalan di tengah, tanda `stat_confirm_*`, nilai `up_count`, atau pemetaan `flat_*`.
