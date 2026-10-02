# INVENTORY & WAREHOUSE — DOKUMEN REVIEW

> Untuk direview user. Tanggal: 2026-10-02.
> Perintah user: *"ya saya tunggu warehouse mapping, lalu saya tunggu input moving item antar warehouse dan disana akan
> tertera capacity label dan barcode or QR code. Disana akan muncul warna merah untuk DEAD stock ... Facility for QR Code,
> Labelling, BUILD TOO."* Ditambah saat berjalan: gedung A5, A6, A10 terpisah dan masih ada gedung lain; area penyimpanan
> harus bisa ditambah; ukuran gedung diperkirakan dulu (mis. 30 x 20 m) dan disesuaikan officer; software harus bisa
> dipakai perusahaan lain (gedung mudah ditambah, bentuk boleh huruf L); gedung Telasih berbentuk kotak, 2 lantai;
> harus bisa 2 sampai 3 lantai.
> Rancangan: `docs/warehouselayout_tier1.md`. Tangkapan layar dari aplikasi: `docs/mockups/warehouse_app/`.

---

## 1. Ringkasan

| Hal | Status |
|---|---|
| **Warehouse Map**: denah per gedung per lantai, area, bin, isi bin, kapasitas, QR | jalan di mockup.test |
| Bentuk gedung: kotak, huruf L, garis luar sendiri; 1 sampai 3 lantai | jalan (contoh huruf L di devlib.test) |
| **Dead stock berwarna merah** di peta, transfer, laporan, label | jalan |
| **Stock Transfer** (pindah stok antar bin / antar gudang): Draft, Confirm, Cancel, cetak | jalan di mockup.test |
| Label kapasitas + QR code + barcode tiap bin di form transfer | jalan |
| **Label**: Print Location Label, Print Item Label, lembar cetak, Label Print History | jalan |
| **Track via QR/Barcode** (scanner atau kamera HP) | jalan (kamera hanya di halaman https) |
| Buildings, Storage Areas, Bins (tambah / ubah sendiri) + Generate Bins + Edit Layout (seret area) | jalan |
| Laporan **Stock by Location** | jalan |
| Help 10 layar + terjemahan Indonesia | sudah dimuat (mockup.test) |
| Menu di tmerp.test | **belum** — menunggu penilaian user (tabel dan view sudah terpasang di tm2) |
| Print Pallet Label, Scan Transaction (kerja gudang lewat HP), putaway terarah, cycle count | **belum** (tahap berikut) |

Menu (mockup.test): **Inventory > Warehouse** (Warehouse Map, Stock Transfer, Setup > Buildings / Storage Areas / Bins,
Report > Stock by Location), **Inventory > Label & QR Code** (Print Location Label, Print Item Label, Report > Label Print
History), **Inventory > Scan Center** (Track via QR/Barcode).

## 2. Cara mencoba (urutan yang disarankan)

1. **Inventory > Warehouse > Warehouse Map.** Pilih gedung A10 > Show. Lihat Lantai 1 dan Lantai 2. Klik bin merah
   (`A10-PPR-L03-01`): panel kanan menunjukkan isi, kapasitas, QR, tombol Transfer / Label / Block.
2. Ketik `kraft` di kotak cari: bin yang berisi item itu disorot. Klik **Dead stock** di legenda: hanya bin merah.
3. Klik **Atur Tata Letak**, seret satu area, lepaskan: posisi tersimpan. Seret sudut kanan-bawah area: ukuran berubah.
4. Klik **Generate Bins**: pilih area, lajur / slot, kapasitas > Generate. Bin baru muncul di peta.
5. **Setup > Buildings**: tambah baris gedung baru (kode, nama, panjang, lebar, lantai, bentuk) > Save. Pilih bentuk
   L shape dan isi potongan sudutnya untuk gedung berbentuk huruf L.
6. **Setup > Storage Areas**: tambah area di gedung itu > Save. Kembali ke Warehouse Map: area tampil.
7. **Stock Transfer > + New Record**: A10 ke A6 > Create > + Item > pilih item di bin asal, bin tujuan, qty > Save.
   Lihat **Bins & Capacity** (kapasitas bin tujuan sesudah pindah) dan **Bin Labels** (label + QR + barcode).
   Confirm > Print. Lalu Cancel dengan alasan: stok kembali.
8. Di peta klik bin berisi stok > **Transfer**: draft langsung berisi semua isi bin itu.
9. **Print Location Label**: pilih gedung, pilih area (semua bin tercentang) > Print > lembar label > Print.
10. **Track via QR/Barcode**: ketik `A10-PPR-L03-01` atau `PZ00083` > Enter.
11. **Report > Stock by Location**: klik Dead stock.

## 3. Yang diputuskan user (2026-10-02)

| # | Keputusan |
|---|---|
| G1 | A5, A6, A10 = gedung terpisah, dan masih ada gedung lain. Jadi **gudang = gedung**; gedung lain ditambah di Buildings. |
| G2 | Fungsi dan kelas stok (Receiving, Paper, Ink, Finished Goods, ...) = **area di dalam gedung**. |
| — | Ukuran gedung diisi perkiraan dulu; officer menyesuaikan di Buildings. |
| — | Area penyimpanan harus bisa ditambah sendiri. |
| — | Bentuk gedung bebas (contoh huruf L) supaya software bisa dipakai perusahaan lain; gedung Telasih kotak. |
| — | Gedung 2 lantai; sistem harus bisa sampai 3 lantai. |
| — | Dead stock tampil merah. |
| — | Transfer menampilkan label kapasitas dan barcode / QR code. |

## 4. Yang saya putuskan sendiri (mohon dikoreksi bila salah)

| # | Hal | Pilihan saya | Alasan |
|---|---|---|---|
| 1 | Arti **dead stock** | Sama dengan jawaban asisten dan widget Deadstock Value: item tidak bergerak lebih dari `ASSISTANT_DEADSTOCK_MONTHS` bulan (sekarang 6), dihitung per ITEM di lokasi mana pun | Satu angka di semua tempat |
| 2 | Transfer dan dead stock | **Transfer tidak dihitung sebagai pergerakan.** Stok mati yang dipindah tetap mati | Kalau dihitung, stok mati bisa "dihidupkan" hanya dengan memindahkannya |
| 3 | Dokumen transfer | Satu dokumen untuk RM, SFG, dan FG sekaligus (tabel baru `inv_transfer`), bukan satu dokumen per kelas stok | Petugas memindah barang, bukan kelas stok |
| 4 | Antar gedung | **Satu langkah** (Confirm = stok langsung pindah), belum ada "dalam perjalanan" | A5 / A6 / A10 berdekatan. Dua langkah bisa ditambah bila ada gudang beda kota |
| 5 | Jurnal | Transfer **tanpa jurnal**; nilai ikut pindah dengan harga rata-rata bin asal | Pemilik dan nilai tidak berubah |
| 6 | Lebih dari kapasitas | **Peringatan, bukan larangan** | Engine menyarankan, petugas memutuskan |
| 7 | Bin diblokir | Tidak bisa jadi tujuan transfer; stok di dalamnya tetap bisa dikeluarkan | Blokir = jangan diisi lagi |
| 8 | Kapasitas | Dalam **kg**. Berat diperkirakan: satuan KG = qty; kertas = qty x gramatur x panjang x lebar (dari master atau dari nama item, mis. "350 GR 84.5 X 72"). Item lain (FG, plat) tidak diketahui beratnya, tidak dihitung, dan disebut di panel bin | Master belum punya berat per item. Perlu keputusan: isi berat per item, atau kapasitas dalam pallet |
| 9 | Nomor transfer | `TF` + tahun + 5 digit (`TF2600001`), lahir saat Confirm | Sama dengan dokumen v2 lain |
| 10 | Isi QR | Alamat halaman Track (kamera HP langsung membuka isi bin / item). Barcode 1D = kodenya saja | Tanpa aplikasi khusus |
| 11 | Kode bin | `<gedung>-<area>-L<lajur>-<slot>` (lantai) atau `<gedung>-<area>-R<rak>-<kolom>-<tingkat>` (rak) | Terbaca manusia, unik |
| 12 | Gudang lama `WH-RAW-01`, `WH-FG-01`, ... | **Dibiarkan** (stok dummy lama menempel di sana, dipakai test) | Dinonaktifkan setelah user setuju dan stoknya dipindah |

## 5. Data contoh (mockup.test saja)

- Gedung **A10** 30 x 20 m, **A5** 24 x 16 m, **A6** 20 x 12 m; semuanya kotak, 2 lantai (ukuran = perkiraan).
- 16 area, 217 bin. Stok contoh masuk lewat Stock In sungguhan dengan tanggal berbeda: ada bin terisi, penuh, lebih
  dari kapasitas, diblokir, dan dead stock (kertas 275 hari, tinta 335 hari, label FG 245 hari).
- Tiga transfer: `TF2600001` (A5 ke A10, Confirmed), `TF2600002` (dead stock A10 ke A6, Confirmed), satu Draft.
- Dibuat ulang kapan saja: `php artisan db:seed --class=UatWarehouseSeeder --env=mockup --force`.
- **Catatan:** set data dummy per tahap (Stage 1 sampai 7 di layar Data Dummy) sengaja tidak punya dokumen stok, jadi
  peta kosong di set itu. Stok contoh gudang ada di set **mixed**, atau jalankan perintah di atas.

## 6. Tabel dan kode

| Bagian | Tempat |
|---|---|
| Tabel baru | `tm2mst.wh_zone`; `tm2inv.inv_transfer`, `inv_transfer_line`, `inv_label_print` |
| Kolom baru | `wh_warehouse`: `length_m`, `width_m`, `floor_count`, `shape`, `notch_*`, `outline`, `address`; `wh_loc`: `zone_id`, `capacity_pallet`, `map_x`, `map_y`, `block_reason` |
| View | `v_rm_onhand` / `v_sfg_onhand` / `v_fg_onhand` + kolom `last_item_movement`; `v_inv_movement` membawa nomor transfer |
| Migration | `2026_10_02_000200` (tabel), `000201` (view), `000202` (menu, ddt saja), `000203` (kamus) |
| Posting transfer | `App\Services\Inventory\StockTransferService` (dua baris kartu stok per baris, `doc_type` TF) |
| Isi bin, kapasitas, dead stock | `App\Services\Inventory\WarehouseStock` |
| Pembuat bin | `App\Services\Inventory\BinGenerator` |
| Layar | `resources/views/v2/screens/{warehouse-map, stock-transfer, label-location, label-item, scan-track}.blade.php`; `App\Support\V2\Grid\Inv\{BuildingLookup, StorageAreaLookup, BinLookup, StockByLocationReport, LabelHistoryReport}` |
| Objek DevLib baru | `x-dl::wh-map`, `x-dl::label`, `x-dl::label-sheet`, `x-dl::scan` (contoh: devlib.test > Boards, Schedules & Charts > Warehouse Map & Labels) |
| Cetakan | `App\Support\V2\Print\StockTransferPrint` (belum A) |
| Test | `tests/Feature/StockTransferServiceTest.php` (6 test) |
| Engine Catalog | Warehouse Stock, Stock Transfer Posting |

Yang ikut berubah: jawaban asisten Deadstock, widget Deadstock Value, dan laporan Slow Moving Item kini memakai
`last_item_movement` (transfer tidak dihitung); Stock Card menandai baris transfer (Transfer in / Transfer out).

## 7. Belum dibangun (dari rancangan)

| Hal | Catatan |
|---|---|
| Print Pallet Label, pallet ber-identitas | Butuh `inv_pallet` + pallet di kartu stok |
| Scan Transaction (kerja gudang di HP): putaway, pick list | Butuh tugas gudang (`inv_wh_task`) |
| Aturan putaway, saran bin | Tahap 2 rancangan |
| FEFO / kedaluwarsa, titipan customer, subkon virtual, cycle count per bin | Tahap 2 rancangan |
| Transfer dua langkah (dalam perjalanan) antar lokasi yang jauh | Bila ada gudang beda kota |
| Stock In / Out / Adjustment v2 yang memilih bin dari peta | Layar v1 masih dipakai; bin baru sudah muncul di pilihan lokasinya |
| Menu + Help + data di tmerp.test | Menunggu penilaian user |
| Denah nyata A5 / A6 / A10 dan gedung lain | Diisi officer di Buildings + Storage Areas (ukuran sekarang perkiraan) |

## 8. Pertanyaan untuk user

1. Ukuran dan isi tiap gedung yang sebenarnya (A5, A6, A10, dan gedung lain): boleh saya isi dari sketsa / foto, atau
   officer mengisi sendiri di Buildings dan Storage Areas?
2. Kapasitas bin: tetap dalam kg (perlu berat per item di master), atau cukup jumlah pallet?
3. Batas dead stock tetap 6 bulan untuk semua jenis barang, atau berbeda per kategori (kertas, tinta, barang jadi)?
4. Transfer perlu approval (mis. Kepala Gudang) atau cukup Confirm oleh petugas seperti sekarang?
5. Gudang lama buatan sistem (`WH-RAW-01`, dst) boleh dinonaktifkan setelah stok dummy-nya dipindah ke gedung nyata?
6. Setelah disetujui: pasang menu ini di tmerp.test?
