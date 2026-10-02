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
| Menu, Help, dan gedung A10 / A5 / A6 di tmerp.test | **terpasang 2026-10-03** (bagian 10); tanpa stok / palet contoh |
| **Putaran 2** (bagian 9): kapasitas luas x tinggi (m3) + kg, Print Pallet Label, label item / palet / lokasi yang berbeda dengan pita warna dan tag kedaluwarsa, panduan ukuran label, cetak label dari Penerimaan Barang, approval Stock Transfer (bawaan mati) | jalan di mockup.test |
| Scan Transaction (kerja gudang lewat HP), putaway terarah, cycle count | **belum** (tahap berikut) |

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
| — | Ukuran gedung dan denah diisi perkiraan agen dulu (tanpa menunggu sketsa / foto); user atau officer warehouse mengubahnya nanti lewat layar. |
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
| ~~Print Pallet Label, pallet ber-identitas~~ | **Dibangun di putaran 2** (bagian 9); palet tidak masuk kartu stok, hanya label di dalam saldo |
| Scan Transaction (kerja gudang di HP): putaway, pick list | Butuh tugas gudang (`inv_wh_task`) |
| Aturan putaway, saran bin | Tahap 2 rancangan |
| Kedaluwarsa | **Dibangun di putaran 2** sebagai tanggal per palet + tag. Belum: saran ambil yang kedaluwarsa duluan (FEFO) |
| Titipan customer, subkon virtual, cycle count per bin | Tahap 2 rancangan |
| Transfer dua langkah (dalam perjalanan) antar lokasi yang jauh | Bila ada gudang beda kota |
| Stock In / Out / Adjustment v2 yang memilih bin dari peta | Layar v1 masih dipakai; bin baru sudah muncul di pilihan lokasinya |
| ~~Menu + Help di tmerp.test~~ | **Terpasang 2026-10-03** (bagian 10) |
| Denah nyata A5 / A6 / A10 dan gedung lain | Diisi officer di Buildings + Storage Areas (ukuran sekarang perkiraan) |

## 8. Pertanyaan untuk user

1. ~~Ukuran dan isi tiap gedung yang sebenarnya~~ **Dijawab user 2026-10-02:** denah sementara dibuat agen dulu, nanti diubah
   user / officer warehouse (Buildings, Storage Areas, Bins, Atur Tata Letak). Gedung lain di luar A5 / A6 / A10 ditambah
   user di Buildings saat namanya sudah ada.
2. ~~Kapasitas bin: kg atau pallet?~~ **Dijawab user 2026-10-02:** kapasitas dalam luasan dan tinggi gedung (lantai 1, 2, 3);
   kg boleh juga. Dibangun: bagian 9.
3. Batas dead stock tetap 6 bulan untuk semua jenis barang, atau berbeda per kategori (kertas, tinta, barang jadi)?
4. ~~Transfer perlu approval?~~ **Dijawab user 2026-10-02:** di-skip dulu, fasilitasnya dibuat dan bisa diaktifkan kapan
   saja. Dibangun: bagian 9.
5. Gudang lama buatan sistem (`WH-RAW-01`, dst) boleh dinonaktifkan setelah stok dummy-nya dipindah ke gedung nyata?
6. ~~Pasang di tmerp.test?~~ **Dijawab user 2026-10-02:** di mockup dulu, sesudah itu direview.

## 9. Putaran 2 (2026-10-02)

Perintah user sesudah melihat Tahap 1: kapasitas dalam luasan dan tinggi gedung (lantai 1, 2, 3), kg boleh juga;
approval transfer di-skip tetapi fasilitasnya dibuat dan bisa diaktifkan kapan saja; di mockup dulu; label palet dan
label item, dengan warna sebagai penanda dan penjelasan ukuran label; label harus ada QR code, barcode, dan tanggal
kedatangan; wajib buat label setiap kedatangan dan setiap habis pemakaian dalam satu palet; bedakan label item dan
label palet; kode plate dari supplier harus disertakan; kertas dan tinta punya tanggal kedaluwarsa, harus ada tag;
fasilitas cetak label palet dan label item saat penerimaan barang.

### 9.1 Yang ditambahkan

| Hal | Di mana | Gambar |
|---|---|---|
| Tinggi bersih tiap lantai (1, 2, 3) | Warehouse > Setup > Buildings | `10_buildings.jpg` |
| Tinggi tumpuk tiap area | Warehouse > Setup > Storage Areas | `11_storage_areas.jpg` |
| **Capacity per Floor**: luas, tinggi, volume ruang, area simpan, terpakai (m3 dan kg) | Warehouse Map, di bawah peta | `12_warehouse_map_capacity_expiry.jpg` |
| Panel bin: berat, volume, luas tapak x tinggi, jumlah palet | Warehouse Map, klik bin | `12_...jpg` |
| Tanda kedaluwarsa di peta (titik kuning + saringan) dan di tiap item | Warehouse Map | `12_...jpg` |
| **Print Pallet Label**: daftar pekerjaan label, label kedatangan, label ulang, cetak ulang | Label & QR Code | `13`, `14`, `15` |
| Label palet (pita = bulan tiba, kode palet supplier, lot, kedaluwarsa) | lembar cetak | `16_pallet_labels.jpg` |
| Label item (pita = kelas stok, tanggal tiba, lot, kedaluwarsa) | lembar cetak | `17_item_labels.jpg` |
| Label lokasi (pita = jenis area, berat maks, volume, luas tapak) | lembar cetak | `7_location_labels.jpg` |
| Tombol **Pallet Labels** dan **Item Labels** di Penerimaan Barang yang sudah Posted | Procurement > Receive | `18`, `22`, `23` |
| Lacak palet: label, isi, riwayat, Move this Pallet | Track via QR/Barcode | `19_track_pallet.jpg` |
| Panduan ukuran label + cara cetak + arti warna | tiap layar label dan lembar cetak | `20_print_item_label_size_guide.jpg` |
| **Stock Transfer Approval Center** + approval di form transfer (bawaan mati) | Warehouse | `21_stock_transfer_approval_center.jpg` |
| Stock by Location: volume, tanggal tiba, tanggal kedaluwarsa, status Expired / Expires soon | Warehouse > Report | `9_stock_by_location.jpg` |

### 9.2 Ukuran label (supaya mudah dicetak)

| Ukuran | Dipakai untuk | Isi per lembar A4 |
|---|---|---|
| 60 x 40 mm | label item untuk kemasan kecil: dus, kaleng tinta, rol | 21 |
| 100 x 50 mm | label bin / rak dan label item di tumpukan | 10 |
| 100 x 150 mm | label palet, terbaca dari jarak beberapa meter | 2 |

- **Gulungan label (printer label):** di jendela cetak pilih ukuran kertas sesuai gulungan, margin None, skala 100%.
  Satu label = satu halaman.
- **Kertas A4 (printer kantor):** kertas A4, margin None atau Minimum, skala 100%, nyalakan Background graphics supaya
  pita warna ikut tercetak. Potong mengikuti garis tepi label.
- Tanpa printer warna, pita tetap mencetak jenis label dan tandanya sebagai tulisan.
- Pilihan gulungan / A4 ada di form cetak (Print On) dan bisa diganti di lembar cetaknya (tombol Label Roll / A4 Sheet).

Arti warna pita: **label palet** = bulan tiba (Jan biru, Feb hijau, Mar jingga, Apr ungu, Mei hijau tua, Jun merah muda,
Jul biru tua, Agu kuning, Sep hijau toska, Okt jingga tua, Nov cokelat, Des abu-abu); **label item** = kelas stok (bahan
baku biru, setengah jadi kuning tua, barang jadi hijau); **label lokasi** = jenis area. Merah tidak dipakai untuk pita:
merah = dead stock atau kedaluwarsa.

### 9.3 Cara mencoba

1. **Warehouse Map** > gedung A10. Legenda sekarang punya **Kedaluwarsa / segera kedaluwarsa**. Klik bin
   `A10-PPR-L03-01`: panel menunjukkan Berat, Volume, Luas tapak x tinggi, Pallet, lalu tanggal tiba dan kedaluwarsa item.
   Gulir ke bawah: tabel **Capacity per Floor**.
2. **Setup > Buildings**: ubah Clear Height Floor 1 > Save. **Setup > Storage Areas**: ubah Stack Height. Kembali ke
   peta: volume berubah.
3. **Label & QR Code > Print Pallet Label** > gedung A10. Daftar berisi yang perlu **Label kedatangan** dan satu
   **Label ulang sesudah dipakai** (`A10-PPR-L02-04`).
4. Buka baris `A10-RCV` (kertas baru datang, belum berlabel): isi qty tiap palet, kode palet supplier, lot, tanggal
   kedaluwarsa; pakai **+ Pallet** untuk memecah > **Create & Print** > lembar label > Print.
5. Buka baris `A10-PPR-L02-04`: pilih palet yang dibuka, ketik qty sisa > **Relabel & Print**. Isi 0 untuk palet habis.
6. **Print Item Label**: pilih stok, ukuran, Print On > Print.
7. **Dari penerimaan barang:** Procurement > Receive > + New Record > supplier > gudang A10 + baris PO > isi Rack
   Location `A10-RCV`, QC > Confirm. Di form yang sudah Posted klik **Pallet Labels** lalu **Item Labels**.
8. **Track via QR/Barcode**: ketik nomor palet (mis. `PL2600015`) > Enter. Coba **Move this Pallet**.
9. **Approval transfer:** Setup > Setting Approval > buka `TF` > Activate. Buat Stock Transfer > Confirm: status menjadi
   Waiting Approval, stok belum pindah. Masuk sebagai pemeriksa (jabatan di level 1) > **Stock Transfer Approval
   Center** > Approve: stok pindah. Deactivate untuk mematikannya lagi.

### 9.4 Yang saya putuskan sendiri (mohon dikoreksi bila salah)

| # | Keputusan | Alasan |
|---|---|---|
| 1 | "Kode plate dari supplier" = **kode palet yang tercetak di label supplier** pada tiap palet | Nomor kendaraan sudah ada di form penerimaan. Bila yang dimaksud lain (mis. nomor lot pabrik), cukup ganti nama isiannya |
| 2 | Palet **tidak** masuk kartu stok: saldo tetap per item per bin, palet = label di dalam saldo itu | Tidak mengubah cara stok dan nilai dihitung; selisih saldo dan label menjadi daftar pekerjaan label |
| 3 | Sistem tidak menebak palet mana yang dipakai; petugas mengetik qty sisa | Sistem tidak tahu palet mana yang dibuka |
| 4 | Kedaluwarsa dicatat per palet; usulan = tanggal tiba + umur simpan master, boleh diganti | Tanggal sebenarnya ada di label supplier |
| 5 | Segera kedaluwarsa = 30 hari sebelum tanggalnya (Settings > Constants `WH_EXPIRY_SOON_DAYS`) | Angka awal |
| 6 | Status bin memakai yang lebih penuh antara berat dan volume; Penuh mulai 90% (`WH_BIN_FULL_PCT`) | Bin bisa penuh ruang sebelum penuh berat, dan sebaliknya |
| 7 | Tinggi lantai dan tinggi tumpuk contoh: A10 7 m / 3,5 m; A5 6 m / 3,2 m; A6 5 m / 3 m; tumpukan kertas 1,6 m | Perkiraan, diubah officer |
| 8 | Volume stok hanya diperkirakan untuk lembaran kertas (lembar x ukuran x tebal) | Tinta, lem, barang jadi belum punya data ukuran |
| 9 | Pemeriksa approval transfer = jabatan yang sama dengan level 1 Stock Adjustment (Operational Manager) | "Kepala Gudang" belum punya pemegang; jabatan diganti di Setting Approval |
| 10 | Saat approval aktif, nomor transfer lahir saat diajukan; Reject = berhenti, Duplicate untuk mengulang | Sama dengan dokumen ber-approval lain |
| 11 | Sebagian isi palet dipindah dan jumlahnya tidak persis sama dengan palet mana pun: palet tidak ikut pindah | Muncul di daftar pekerjaan label (asal: label ulang; tujuan: label baru) |
| 12 | Warna pita label palet = bulan tiba (12 warna) | Palet lama dan baru terlihat dari jauh; merah khusus untuk dead stock / kedaluwarsa |

### 9.5 Data contoh putaran 2 (mockup.test)

`UatWarehouseSeeder` dijalankan ulang: tinggi lantai dan tinggi tumpuk, 31 palet berlabel (dua kedaluwarsa, tiga segera
kedaluwarsa), 11 posisi yang belum berlabel (8 di gedung A10), 1 posisi yang perlu label ulang, 3 transfer Confirmed (dua di antaranya
membawa palet) + 1 Draft. Stage set dummy 1-12 tidak memuat stok gudang: sesudah memulihkan stage set, jalankan
`php artisan db:seed --class=UatWarehouseSeeder --env=mockup --force`.

### 9.6 Pertanyaan untuk user

1. "Kode plate dari supplier": benar yang dimaksud kode palet di label supplier?
2. Kedaluwarsa: cukup tag dan saringan, atau perlu **larangan** memakai / memindahkan palet yang sudah kedaluwarsa?
3. Batas "segera kedaluwarsa" 30 hari, sama untuk kertas dan tinta?
4. Label palet untuk **barang jadi** (hasil produksi) dibuat saat apa: saat hasil produksi masuk gudang?
5. Approval transfer: siapa pemeriksanya (Kepala Gudang / Operational Manager), dan apakah perlu batas nilai?
6. Masih terbuka dari Tahap 1: batas dead stock per kategori; gudang lama buatan sistem dinonaktifkan.

## 10. Terpasang di mockup.test dan tmerp.test (2026-10-03)

Perintah user: *"apply to mockup.test and tmerp.test, make sure all connected to table and database. After apply, don't
forget data dummy on mockup.test."*

| Hal | mockup.test (ddt) | tmerp.test (tm2) |
|---|---|---|
| Tabel, view, konstanta, kamus Indonesia | ada | ada |
| Menu Inventory > Warehouse, Label & QR Code, Scan Center | ada | **dipasang 2026-10-03** |
| Enam laporan stok semua kelas (Warehouse > Report) | ada | **dipasang 2026-10-03** |
| Help 12 topik | ada | **dimuat 2026-10-03** |
| Gedung A10, A5, A6 + area + bin (denah perkiraan) | ada (data contoh) | **dibuat 2026-10-03**: 16 area, 217 bin, **tanpa stok** |
| Stok, palet, transfer contoh | ada (31 palet, 4 transfer) | tidak ada — diisi dari transaksi nyata |
| Approval Stock Transfer (bawaan mati) + Approval Center | ada | **belum bisa**: Setting Approval belum terpasang di tmerp.test |

- Di tmerp.test Confirm pada Stock Transfer langsung memindahkan stok. Begitu Setting Approval terpasang di sana, jalankan
  `php artisan db:seed --class=StockTransferApprovalSeeder --force`; sesudah itu approval bisa dinyalakan dari layar.
- Gedung di tmerp.test dibuat `php artisan db:seed --class=WarehouseBuildingSeeder --force` (aman diulang; gedung yang sudah
  ada dilewati). Ukuran, tinggi, area, dan bin diubah lewat layar Buildings, Storage Areas, Bins, dan Atur Tata Letak.
- Gudang lama buatan sistem (`WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`, `WH-SCP-01`) masih aktif di kedua situs; stok yang ada
  sekarang masih di sana (pertanyaan 8.5).
- **Data contoh mockup.test** disimpan sebagai baseline Reset baru (2026-10-03 00:42) dan sebagai set "Mixed dummy";
  baseline lama dicadangkan di `storage/app/backups/`. Set stage 1-12 tetap tanpa stok gudang.
- Diuji di kedua situs dengan transaksi yang dibatalkan kembali: geser area, blokir bin, transfer (buat, Confirm, cetak,
  Cancel), label palet (buat, label ulang), label item dan lokasi, lacak. Semua layar terbuka tanpa galat.

---

## 11. Putaran 3 (2026-10-03): lokasi = X, Y dan stack; sisa 1 - 2 rim

Perintah user: "Lokasi penempatan barang selain posisi X dan Y juga ada stack, terutama untuk barang yang tersisa 1 atau 2
rim" lalu "mapping warehouse with X, Y, stack. FIX IT NOW". Rancangan dan aturan lengkap: `warehouselayout_tier1.md`
bagian 15 (15.7 = yang dibangun). Terpasang di mockup.test dan tmerp.test; data contoh hanya mockup.test.

### 11.1 Yang ditambahkan

| Hal | Di mana | Gambar (`mockups/warehouse_app/`) |
|---|---|---|
| Nomor tumpukan tiap palet / pak (1 = paling bawah), jumlah satuan di kotak bin, tumpukan tampak samping dengan panah tukar urutan | Inventory > Warehouse > Warehouse Map | `24` |
| Tumpukan sisa: banyak pak dari item berbeda di satu tapak; sisa lama berwarna merah | Warehouse Map | `25` |
| Layar pemantau semua tumpukan | Inventory > Warehouse > **Stack Monitor** | `26` |
| Jenis satuan Pallet / Pack, sisa otomatis di bawah batas, "Now in bin" saat label ulang | Label & QR Code > Print Pallet Label | `27` |
| Alamat di label = bin + nomor tumpukan; label sisa 100 x 50 mm | label palet / pak | — |
| Batas tumpuk per area (boleh kosong) | Inventory > Warehouse > Storage Areas | — |

### 11.2 Cara mencoba (mockup.test)

1. Inventory > Warehouse > **Warehouse Map**, gedung A10. Angka kecil di kotak bin = jumlah satuan yang ditumpuk.
2. Klik `L03-01`: panel menampilkan Stack 3 units · 2 pallets · 1 pack, tinggi tumpukan, dan daftar dari atas ke bawah
   (pak sisa lama di atas dua palet dead stock). Klik panah untuk menukar urutan.
3. Klik **Remnant** di atas peta lalu bin `L06-05`: tumpukan sisa enam pak (kertas, ambri, sheet), dua di antaranya sisa lama.
4. Klik **Buried** lalu bin `L04-02`: palet tertua berada di bawah palet baru; panel menyebut berapa yang harus dipindah.
5. Inventory > Warehouse > **Stack Monitor**: klik Buried, Remnant, Old remnant, Stack not recorded. Open pada satu baris
   membuka Print Pallet Label untuk satuan itu.
6. Di Print Pallet Label, bagian Relabel after Use: ketik sisa 300, biarkan Unit kind Automatic, pilih Now in bin
   `A10-PPR-L06-05`, lalu Relabel & Print. Hasil: palet menjadi pak sisa, Stock Transfer dibuat dan di-Confirm, pak itu
   menjadi nomor paling atas di tumpukan sisa, label kecil tercetak dengan bin + nomor tumpukan.
7. Inventory > Warehouse > Storage Areas: isi Max. Stacked Pallets untuk satu area, lalu lihat Stack Monitor.

### 11.3 Yang saya putuskan sendiri (mohon dikoreksi bila salah)

Lihat tabel di `warehouselayout_tier1.md` bagian 15.7. Yang paling perlu penilaian user:

| # | Keputusan |
|---|---|
| 1 | "Tertimbun" hanya dihitung bila yang menindih adalah palet (bukan pak sisa). |
| 2 | Sisa otomatis hanya untuk satuan lembar / rim; untuk kg dan pcs petugas memilih sendiri. |
| 3 | Di tmerp.test tinggi tumpuk area kertas masih perkiraan lama 1,6 m; officer mengubahnya di Storage Areas. |

### 11.4 Catatan data contoh

Banyak palet contoh berstatus **Over height limit**: isi palet contoh besar (mis. 5.000 lembar ivory 300 g = sekitar 2,1 m
per palet), jadi dua palet bertumpuk melebihi tinggi tumpuk 3,2 m. Itu hitungan yang benar atas data contoh, bukan cacat;
pada data nyata hasilnya mengikuti qty per palet dan tinggi tumpuk yang diisi officer.

### 11.5 Belum dibangun

- Saran "pakai sisa dulu" di dokumen Stock Out / booking bahan (urutannya sudah dihitung; tampilannya ikut Stock Out v2).
- Ink Mixing (tinta khusus hasil campuran): ditunda user, konsep di `warehouselayout_tier1.md` bagian 16.
