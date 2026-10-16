# Gudang: Peta gudang, Stack Monitor, Stock by Location, label, dan Scan

*Panduan pengguna untuk Officer Gudang, Supervisor Gudang, dan siapa pun yang mencari barang. Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test. Layar tidak dibuka di browser, printer dan pemindai tidak dicoba; hal yang belum terbukti ada di bagian 8.*

Aturan teknis: `docs/tg/inventory/warehouse/11_card_warehouse-map-and-monitors.md`, `14_card_pallet.md`, `18_card_labels-and-scan.md`.

## 1. Menu Warehouse > Map & Monitor

| Layar | Fungsi |
|---|---|
| **Warehouse Map** | Denah gedung per lantai: area, bin, isi, kapasitas |
| **Stack Monitor** | Satu baris per palet/pak dengan posisinya dalam tumpukan |
| **Stock by Location** | Semua stok per item per bin di semua gedung |

Ketiganya membaca angka yang sama dari saldo stok; tidak ada angka yang dihitung sendiri di layar.

## 2. Warehouse Map

1. Buka **Warehouse > Map & Monitor > Warehouse Map**. Pilih **Building** lalu tekan **Show**. Gedung yang belum punya panjang dan lebar menampilkan "This building has no size yet" dengan tombol **Buildings**.
2. Tab lantai (Floor 1, 2, 3) memilih lantai. Setiap area digambar sebagai persegi berwarna menurut jenisnya; bin berupa sel di dalamnya (lajur ke kanan, slot ke bawah; satu rak bertingkat = satu sel).
3. Status bin: **Blocked**, **Empty**, **In use**, **Full** (terisi 90% kapasitas atau lebih; angka 90 bisa diubah di konstanta `WH_BIN_FULL_PCT`), **Over** (di atas 100%). **Merah = dead stock**: item tidak bergerak lebih dari 6 bulan (konstanta `ASSISTANT_DEADSTOCK_MONTHS`). Memindahkan stok ke bin lain **tidak** dihitung sebagai bergerak.
4. Klik satu bin. Panel kanan menampilkan isi, bobot, volume, palet dalam tumpukan, dan tombol:
   - **Transfer**: membuat Draft Stock Transfer berisi semua isi bin; Anda memilih bin tujuan lalu Confirm (lihat `21_pick_list_dan_transfer`).
   - **Label**: label satu bin.
   - **Block**: tanyakan alasan (wajib), bin tidak lagi bisa menerima stok sampai **Unblock**.
   - Panah naik/turun pada palet: menukar urutan tumpukan; tanpa dokumen, jumlah tidak berubah.
5. Lapisan: **Pick allocation** (pilih Pick List terbuka) dan **Layer: Putaway** (bin usulan bertanda P).
6. **Generate Bins**: pilih area, isi rentang **lajur/rak**, **kolom/slot**, **tingkat** dan kapasitas; bin yang kodenya sudah ada dilewati. Pesan: ":n bins created in :zone (:s already existed)." Area virtual tidak punya bin fisik.
7. **Edit Layout** menggeser atau mengubah ukuran area (tetap di dalam gedung).
8. Di bawah peta: **Capacity per Floor** (luas, tinggi bersih, volume ruang, volume simpan, terpakai) dan ringkasan. Lantai tanpa tinggi bersih tidak punya volume dan diberi peringatan.

Berat dan volume hanya dapat diperkirakan untuk barang berkilogram dan kertas lembaran bergramatur dan berukuran; tinta, lem, dan barang jadi tidak dihitung di persentase kapasitas.

## 3. Stack Monitor

**Warehouse > Map & Monitor > Stack Monitor**. Setiap baris = satu palet atau pak berlabel dalam satu bin. Kolom penting: **Stack** (1 = paling bawah), **Kind** (Pallet/Pack), **Age (days)**, **Height in Bin**, **Stack Height**, **Remnant**, **Buried**, **Record**.

- **Buried**: unit yang seharusnya dipakai lebih dulu (pak sisa dulu, lalu kedaluwarsa paling awal, lalu tiba paling lama) tertimpa palet lain.
- **Remnant / Old remnant**: pak sisa; "old" bila tibanya lebih lama dari batas dead stock.
- **Near/Over height limit**: tinggi tumpukan dibanding tinggi tumpuk area. **Over stack limit**: lebih banyak palet daripada **Max. Stacked Pallets** area.
- **Stack not recorded**: ada stok di bin tanpa label palet; posisinya dalam tumpukan belum diketahui. Tekan **Open** untuk membuat labelnya.

## 4. Stock by Location

**Warehouse > Map & Monitor > Stock by Location**: stok sekarang, satu baris per item per bin. Kotak statistik: All, **Dead stock**, **Expired**, **Expires soon** (30 hari, konstanta `WH_EXPIRY_SOON_DAYS`). Baris merah = dead stock. Kolom **Stock** menunjukkan OK, Dead stock, Expired, Expires soon, atau Blocked bin.

## 5. Label

Ada **tiga label yang sengaja berbeda** (menu **Warehouse > Labels & Scan**):

| Label | Untuk | Pita warna | Isi utama |
|---|---|---|---|
| **Location** | satu bin | jenis area | kode bin, area, kapasitas (berat, volume, luas), QR, barcode |
| **Item** | satu posisi stok (item di satu bin) | kelas stok (RM biru, SFG kuning tua, FG hijau) | kode, nama, qty, bin, tanggal datang, lot, kedaluwarsa |
| **Pallet** | satu palet atau pak | bulan kedatangan | nomor palet, qty, tanggal datang, kode palet supplier, lot, penerimaan, bin + nomor tumpukan, kedaluwarsa |

Semua punya **QR** (membuka layar Track via QR/Barcode) dan **barcode** 1D. Tag merah: Dead stock, Expired; kuning: Expires soon.

Cara mencetak (ketiga layar sama):

1. Pilih **Building** (untuk label bin juga boleh **Storage area**, yang mencentang semua bin areanya) lalu centang yang akan dicetak.
2. Pilih **Label size**: 60 x 40 mm, 100 x 50 mm, atau 100 x 150 mm (palet). Pilih **Print On**: label roll (printer label) atau lembar A4 (printer kantor). Isi **Copies per label** (1-20); maksimal 300 label sekali cetak.
3. Tekan **Print**. Sistem mencatat riwayat cetak lalu membuka lembar label; cetak dari browser. Tombol **Back** kembali ke layar asal. Label palet yang dicetak ulang menambah hitungan cetaknya.
4. **Print History** membuka laporan **Label Print History** (siapa mencetak apa dan kapan).

Label palet wajib dibuat setiap barang datang dan setiap isi palet habis dipakai; langkahnya ada di `20_penerimaan_dan_putaway` (**Create & Print** dan **Relabel & Print**). Setelah **Confirm Pick** sistem sendiri mengecilkan qty palet dengan sisanya; cetak label baru untuk palet yang tersisa.

## 6. Track via QR / Barcode

**Warehouse > Labels & Scan > Track via QR/Barcode**: pindai QR dengan kamera HP (membuka layar ini langsung), atau pakai pemindai USB/Bluetooth yang mengetik kode lalu Enter, atau ketik sendiri. Kode yang dikenali: `LOC:kode`, `PLT:nomor`, `ITM:RM:kode`, alamat QR, atau kode polos (dicari berurutan sebagai bin, palet, nomor transfer, lalu item).

Hasil:

- **Bin**: label, isi bin, fakta kapasitas, tombol **Show on Map**, **Label**.
- **Pallet**: label, bin, nomor tumpukan, qty sekarang dan saat datang, kode palet supplier, kedaluwarsa, riwayat (Arrival label, Relabelled, Moved, Closed, Restacked), tombol **Move this Pallet** (membuat Draft Stock Transfer), **Relabel / Print**, **Show on Map**.
- **Stock Transfer**: tautan ke dokumen.
- **Item**: semua bin yang menyimpannya, palet aktif, dan 15 pergerakan terakhir.
- Tidak ada: "Nothing found for ":code". Check the code, or look it up in the Warehouse Map."

Menu **Scan Transaction (Mobile Camera)** disembunyikan karena belum dibangun.

## 7. Tips

- Bila stok di satu bin tidak muncul di Pick List, cek apakah palet-nya sudah dilabel (Stack Monitor: **Stack not recorded**).
- Palet yang kedaluwarsa tetap berada di bin dan hanya diberi tanda; pindahkan lewat Stock Transfer ke area **Reject** bila perlu.

## 8. Belum terverifikasi

- Tidak ada tangkapan layar; tombol dan teks dari kode dan templat. Perilaku seret dan ubah ukuran pada Edit Layout, zoom peta, dan kamera HP berasal dari JavaScript yang tidak dibaca.
- Hasil cetak (ukuran nyata, keterbacaan QR) belum dicoba di printer.
- Data contoh: tidak satu pun dari 225 bin punya koordinat sendiri; peta memakai posisi hitungan otomatis. 78 dari 105 posisi stok bahan baku belum berlabel palet.
- Tombol Block/Unblock/Generate Bins/Edit Layout tidak dibatasi hak yang lebih halus daripada modul Inventory (dari kode).
- Tes terkait (`WarehouseMapLayerTest`, `PalletServiceTest`, `WarehouseStructureTest`) tidak dijalankan.
