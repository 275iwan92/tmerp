# Gudang: Master lokasi (Buildings, Storage Areas, Bins) dan Tool Register

*Panduan pengguna untuk Officer Gudang yang menyiapkan atau mengubah denah. Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test. Layar tidak dibuka di browser dan tes tidak dijalankan; hal yang belum terbukti ada di bagian 8.*

Aturan teknis: `docs/tg/inventory/warehouse/10_card_location-hierarchy.md` dan `19_card_tool-register.md`.

## 1. Tiga tingkat lokasi

Stok selalu berada di **bin**. Bin berada di **Storage Area** (area penyimpanan pada satu lantai), dan area berada di **Building** (gedung).

| Tingkat | Menu (Warehouse > Setup) | Contoh data |
|---|---|---|
| **Building** | **Buildings** | A10 (30 x 20 m, 2 lantai), A5, A6 |
| **Storage Area** | **Storage Areas** | `PPR` (kertas, lantai), `INK` (tinta, rak), `RCV` (penerimaan) |
| **Bin** | **Bins** | `A10-PPR-L03-05` (lajur 3, slot 5), `A10-INK-R02-03-2` (rak 2, kolom 3, tingkat 2) |

Semua layar ini berupa tabel yang diisi langsung di sel (tombol simpan di panel tabel). Urutan menyiapkan: Building, lalu Storage Areas, lalu bin (otomatis lewat **Generate Bins** di Warehouse Map, atau ketik di Bins). Menu **Warehouse & Location** (Master Warehouse) adalah layar lama untuk tabel gedung yang sama; gunakan **Buildings** karena memuat ukuran dan bentuk.

## 2. Buildings

Satu baris = satu gedung. Kolom: **Code** (huruf besar, angka, `-` atau `_`; tidak bisa diubah setelah disimpan), **Building Name**, **Stock Kept** (Mixed, Raw Material, Semi Finished Goods, Finished Goods, Waste, Transit), **Length (m)**, **Width (m)**, **Floors** (1-3), **Clear Height Floor 1/2/3 (m)**, **Shape**, **Cut-out Length/Width/Corner**, **Custom Outline (m)**, **Address**, **Active**.

- **Rectangle**: cukup panjang dan lebar.
- **L shape**: isi juga bagian yang dipotong (panjang, lebar, sudut); potongan harus lebih kecil dari gedung. Pesan: "L shape: fill the cut-out length, width and corner; the cut-out must be smaller than the building."
- **Custom outline**: ketik titik sudut dalam meter searah jarum jam, mis. `0,0; 30,0; 30,12; 0,12` (minimal tiga titik).
- **Clear Height**: tinggi bersih lantai sampai balok terendah. Luas lantai x tinggi = volume ruang. Lantai tanpa tinggi tidak punya volume dan Warehouse Map memberi peringatan.
- Gedung `VIRT` (Virtual Locations) dibuat sistem untuk bin Loss, In Transit, dan vendor subkon. Jangan diubah atau dihapus.

## 3. Storage Areas

Satu baris = satu area pada satu lantai gedung. Kolom penting:

| Kolom | Arti |
|---|---|
| **Building**, **Floor**, **Code** | Kode maksimal 6 huruf/angka, unik per gedung, tidak bisa diubah setelah disimpan (kode bin dibentuk darinya) |
| **Use** | Receiving, QC Hold, Storage, Production Staging, Shipping, Reject / Waste, Customer Consignment, Tooling (Die & Plate), WIP (Production Floor), serta tiga jenis **virtual**: At Subcontractor, In Transit, Loss |
| **How Stored** | **Rack** (rak x kolom x tingkat), **Floor (lane x slot)**, **Open area (one bin)** |
| **Stock Class** | Any, Raw Material, Semi Finished Goods, Finished Goods |
| **X, Y, Width, Depth (m)** | Letak dan ukuran di denah dari sudut kiri-atas; harus muat di dalam gedung |
| **Stack Height (m)** | Setinggi apa barang boleh ditumpuk; tidak boleh melebihi tinggi bersih lantai; kosong = tinggi lantai |
| **Max. Stacked Pallets** | Batas palet menumpuk di satu tapak lantai; kosong = tidak dibatasi (hanya dipantau di Stack Monitor) |
| **Mixing** | **Mixed items**, **One item per bin**, **One batch per bin**: aturan **keras** saat stok masuk bin (putaway dan transfer menolak pelanggaran) |
| **Pick Order** | **FIFO** (paling lama datang) atau **FEFO** (paling cepat kedaluwarsa), dipakai Pick List |
| **Count Every (days)** | Selang hitung stok otomatis (1-365); kosong = tidak dijadwalkan |
| **Virtual** | Mengikuti **Use**; tidak diketik |

Pesan yang mungkin Anda lihat: "This code is already used in that building.", "That building does not have this floor. Raise Floors in Buildings first.", "The stack height is higher than the clear height of that floor.", "The area does not fit inside the building: X + Width and Y + Depth must stay within the building size."

Area virtual tidak punya tempat di denah dan tidak dibuatkan bin lewat Generate Bins; bin-nya dibuat sistem bila diperlukan (Loss, In Transit, vendor).

## 4. Bins

Bin biasanya dibuat lewat **Generate Bins** (Warehouse Map): pilih area, isi rentang lajur/rak, kolom/slot, tingkat, kapasitas; bin yang sudah ada dilewati; maksimal 2.000 bin sekali jalan ("Too many bins in one run."). Kode: lantai `GEDUNG-AREA-Llajur-slot`, rak `GEDUNG-AREA-Rrak-kolom-tingkat`, area terbuka `GEDUNG-AREA`.

Di layar **Bins** Anda mengubah: **Storage Area**, **Short Name**, **Capacity (kg)**, **Pallets** (kapasitas jumlah palet), **Usable**, **Blocked Because**, **Active**. **Bin Code** dan **Building** terkunci setelah disimpan (kartu stok dan label merujuk ke kode itu).

- **Row + Column + Level** harus unik dalam satu gedung ("Another bin of this building already has the same Row, Column and Level.").
- Area bin harus milik gedung yang sama ("The storage area belongs to another building.").
- Bin yang tidak **Usable** wajib punya alasan: "A bin that is not usable needs the reason (Blocked Because)." Mengaktifkan lagi mengosongkan alasannya.
- Bin tanpa area tidak tampil di peta; Warehouse Map memberi tahu jumlahnya.
- Memblokir lewat tombol **Block** di Warehouse Map sama akibatnya (alasan wajib). Bin terblokir tidak menerima stok baru; stok yang sudah ada tidak diapa-apakan. Setelah masa blokir lewat, bin harus dibuka manual dengan **Unblock**.

## 5. Mengubah denah dengan aman

- Mengubah ukuran gedung atau area tidak memindahkan stok. Menurunkan tinggi atau mengecilkan area mengubah kapasitas yang dihitung.
- Jangan mengganti kode area atau bin: sistem mengunci. Untuk memindahkan isi bin gunakan Stock Transfer.
- Menonaktifkan (**Active** dihapus) bin yang masih berisi stok menyembunyikannya dari peta dan usulan, tetapi stok tetap tercatat di bin itu.
- Perubahan di sini mengubah usulan Putaway, alokasi Pick List, dan label; periksa Warehouse Map setelahnya.

## 6. Tool Register

**Warehouse > Tools > Tool Register**: daftar pisau pond (**Die**), plat (**Plate**), dan klise (**Block**) milik pabrik. Kolom: **Tool No.** (huruf besar, unik, maksimal 30 huruf; terkunci setelah disimpan), **Type**, **Description**, **Status** (Available, In use, At vendor, In repair, Retired), **Location (Bin)** (bin aktif mana pun, mis. di area **Tooling** `TLG` gedung A10 lantai 2), **Impressions** (jumlah cetakan yang Anda ketik sendiri), **Notes**, **Active**.

Daftar ini berdiri sendiri: belum terhubung ke Job Order, costing, atau Maintenance, dan perubahan status tidak meninggalkan riwayat. Data contoh kosong.

## 7. Pengaturan angka (Settings > Constants)

| Konstanta | Nilai contoh | Pengaruh |
|---|---|---|
| `WH_BIN_FULL_PCT` | 90 | Bin dianggap Full mulai persen ini |
| `WH_EXPIRY_SOON_DAYS` | 30 | Tag "Expires soon" |
| `ASSISTANT_DEADSTOCK_MONTHS` | 6 | Batas dead stock (merah) |
| `WH_REMNANT_MAX_SHEETS` | 1000 | Lembaran sebanyak ini atau kurang = pak sisa |
| `WH_PALLET_BASE_CM` | 15 | Tinggi palet kayu dalam perkiraan tumpukan |
| `WH_PAPER_BULK_CM3_PER_G` | 1,3 | Perkiraan tebal kertas bila master tidak punya |
| `INV_PICK_CONDITIONAL_ALLOWED` | 1 | 1 = batch Conditional boleh dialokasikan untuk pekerjaan non-kelas A |

## 8. Belum terverifikasi

- Tabel-tabel ini tidak dibuka di browser; nama kolom dan pesan dari kode. Cara menyimpan perubahan (tombol dan perilaku sel) mengikuti komponen tabel umum yang tidak dibaca di sini.
- Menyimpan baris gedung `VIRT` dari **Buildings** kemungkinan ditolak validasi (jenis *Virtual* tidak ada di pilihan). Dibaca dari kode, belum dicoba.
- Data contoh: 7 bin dari gedung lama tidak punya area; satu bin lama diblokir tanpa alasan; bin `A10-PPR-L06-06` masih terblokir dengan catatan "Floor repair until 15-Oct-2026" padahal tanggal itu sudah lewat; semua area bertipe Mixed items, FIFO, tanpa jadwal hitung.
- Hak khusus untuk mengubah master gudang: hanya hak modul Inventory yang terlihat.
