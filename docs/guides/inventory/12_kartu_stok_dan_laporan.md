# Kartu Stok dan Laporan Stok: cara membaca angka dan dari mana asalnya

*Panduan untuk Jo, gudang, dan Akuntansi. Ditulis dari membaca kode laporan dan data mockup, 16 Oktober 2026. Laporan tidak membuka layar di browser oleh saya; hal yang belum pasti ada di bagian 8. Kartu teknis: `docs/tg/inventory/stock/11_card_stock-card-and-balance.md`, `18_card_stock-reports.md`.*

Semua laporan stok membaca satu sumber: **Kartu Stok**, yaitu baris-baris yang ditulis sistem setiap kali dokumen stok diposting (bab 10). Laporan tidak menulis apa pun dan tidak memposting apa pun. Kalau angka laporan salah, yang salah adalah dokumen atau kartunya, bukan laporannya.

## 1. Cara kerja satu kartu

Setiap baris Kartu Stok adalah satu pergerakan satu barang di satu **bin** (lokasi). Barisnya berurutan menurut **tanggal dokumen**, bukan menurut kapan diketik. Kolom pokoknya:

- **BB** (saldo awal): saldo sebelum baris itu.
- **In**, **Out**, **Adj**: masuk, keluar, penyesuaian (bertanda plus/minus).
- **EB** (saldo akhir) = BB + In - Out + Adj. BB baris berikutnya = EB baris sebelumnya.

Dokumen **bertanggal mundur** disisipkan di tempatnya, dan semua baris sesudahnya dihitung ulang. Karena itu saldo sekarang selalu = baris terakhir, dan jumlahnya tetap bersambung.

**Saldo sekarang (on hand)** sebuah barang di sebuah bin = EB baris terakhir bin itu. Itulah yang tampil di Stock Balance.

Stock Transfer (pindah bin atau gudang) tercatat sebagai **Out di bin asal dan In di bin tujuan**; jumlah barang secara keseluruhan tidak berubah. Biaya masuk tambahan (Landed Cost, bab 13) tercatat sebagai baris **Value adjustment** dengan qty nol: hanya nilainya yang berubah.

## 2. Dua kelompok menu laporan

1. **Per kelas**, di Inventory > Stock > (Raw Material | Finished Goods | Semi Finished Goods) > **Report**: **Stock Balance**, **Stock Card**, **Slow Moving Item**.
2. **Semua kelas**, di Inventory > Stock > **Reports**: **Stock Card**, **Stock Recapitulation**, **Stock Summary**, **Stock Opname**, **Stock Loss & Gain**, **Stock in Inspection**, **Valuation Check**.

(Laporan lokasi dan peta gudang, seperti Stock by Location dan Warehouse Map, ada di panduan Warehouse.)

Pada laporan berperiode, **periode bawaan = tanggal 1 bulan ini sampai hari ini**. Pilih tanggal lain di pemilih periode, lalu **Show**. Hampir semua kolom bisa difilter dan dikelompokkan lewat kontrol di atas tabel, dan kolom tambahan bisa dimunculkan lewat **Columns**.

## 3. Stock Balance (per kelas)

Buka **Inventory > Stock > Raw Material > Report > Stock Balance**. Satu baris = satu barang di satu bin: **Opening**, **In**, **Out**, **Adjustment** (semuanya **bulan berjalan**), **On Hand**, satuan, **Stock Value**, **Last Movement**. Kolom **Stock** (badge):

- **OK**.
- **Below minimum**: stok yang bisa dipakai (bin tidak diblokir) untuk barang itu lebih kecil dari **stok minimum** di master. Kotak filter di atas menghitung jumlahnya.
- **Empty**: saldo nol atau kurang.
- **Not usable**: stok ada di bin yang diblokir.

Untuk bulan lain gunakan Stock Card atau Stock Recapitulation.

## 4. Stock Card

**Per kelas:** semua pergerakan, terbaru di atas. Ketik kode barang di kotak cari, atau kelompokkan per **Item**, untuk membaca kartu satu barang. **BB dan EB di sini adalah saldo barang (jumlah semua bin)**, bukan saldo satu bin; saldo bin ada di kolom tersembunyi **Location Opening** dan **Location Balance**. Kolom **Movement** berisi badge: In, Out, Adjustment, Opening, Transfer in, Transfer out, Value adjustment.

**Semua kelas** (`Stock Card` di grup Reports): satu kepala per barang dan periode, lalu baris **Date, Location, BB, In, Out, Adj, EB**. Tidak ada harga dan nilai (versi gudang). Dokumen yang dibatalkan tampil dengan tanda "· Cancelled" dan pembaliknya "· Reversal", jadi rantai tetap bersambung. Baris **Total** setiap barang: BB = saldo awal periode, EB = saldo akhir periode. Untuk mencetak kartu satu barang, isi kotak **Item** lalu **Show** dan cetak.

## 5. Stock Recapitulation dan Stock Summary

- **Stock Recapitulation**: satu baris per barang untuk periode terpilih: **Opening, In, Out, Adjustment, Closing**, satuan. Kolom tersembunyi yang bisa dimunculkan: harga rata-rata, nilai awal/masuk/keluar/penyesuaian/akhir (**Opening Value** dan seterusnya), dan **Turnover** (perputaran = nilai keluar dibagi rata-rata nilai awal dan akhir; kosong bila tidak ada stok). Badge **Activity**: With movement atau No movement; barang yang punya saldo awal tetapi tidak bergerak tetap tampil. Transfer antar bin tidak dihitung masuk atau keluar.
- **Stock Summary**: ringkasan **nilai** per kelas dan kategori: jumlah barang, **Opening Value, In Value, Out Value, Adjustment Value, Closing Value**, dan **Share (%)** dari seluruh nilai. Rumusnya selalu cocok: awal + masuk - keluar + penyesuaian = akhir. Kuantitas tidak dijumlahkan karena satuannya berbeda. Cetakannya punya kotak tanda tangan (Prepared By gudang, Checked By Accounting, Approved By kepala gudang).

Nilai pergerakan dihitung sebagai selisih nilai saldo sebelum dan sesudah baris (harga rata-rata bergerak), sehingga totalnya selalu bersambung.

## 6. Slow Moving, Opname, Loss & Gain, Inspection

- **Slow Moving Item (per kelas)**: barang yang **masih punya stok di bin yang bisa dipakai** tetapi pergerakan terakhirnya 3 bulan atau lebih lalu; pergerakan terakhir = di bin mana pun, Stock Transfer tidak dihitung. Badge umur: 3-6, 6-9, 9-12, Over 12 months, atau **Never moved** (saldo lama tanpa tanggal gerak; ini yang paling perlu diperhatikan).
- **Stock Opname**: dokumen Adjustment dan Physical Count dalam periode, satu baris per barang per dokumen, semua status (supaya hitungan yang belum disetujui kelihatan): Qty sistem, Qty fisik, selisih (**Gain** lebih, **Loss** kurang, **Match** sama) dan nilainya. Hanya dokumen **POSTED** yang sudah mengubah stok.
- **Stock Loss & Gain**: selisih per barang per **bulan**, dari adjustment POSTED: Gain = bertambah, Loss = berkurang, dinilai dengan harga rata-rata saat itu.
- **Stock in Inspection**: barang yang ada di gudang tetapi **belum atau tidak boleh dipakai**, keadaan sekarang tanpa periode: menunggu inspeksi QC (baris penerimaan belum diposting dan QC belum selesai), ditolak QC dan belum diretur ke supplier, ada di area **QC Hold**, atau ada di bin yang **diblokir**. Kolom hari = sejak penerimaan atau pergerakan terakhir.

## 7. Valuation Check

**Valuation Check** membuktikan bahwa nilai persediaan per barang tidak bergeser: untuk tiap barang, jumlah nilai semua bin di kartu harus sama dengan nilai barang menurut rantai harga rata-rata per barang. Kolom **selisih harus 0**. Selisih bukan nol berarti kartu dan rantai tidak sepakat; perintah teknis `inv:revalue-per-item --check` dijalankan tim IT untuk menelusurinya. Laporan ini hanya berarti penuh bila pengaturan `INV_AVG_SCOPE` bernilai ITEM; di mockup masih BIN, sehingga laporan hanya mendaftar total nilai per bin.

## 8. Yang perlu Jo ketahui

**Sumber angka dan kesalahan umum:**

- **Nilai bisa nol di barang tanpa harga.** Baris kartu yang dibuat tanpa harga (Stock In dengan Harga Satuan 0, atau data lama) membuat nilai gerakannya nol di Recapitulation dan Summary. Di data contoh ada 29 baris bahan baku seperti itu.
- **Kartu mockup masih punya sisa data uji.** Di data contoh ada satu rantai yang tidak bersambung (BB 1.090 padahal EB sebelumnya 1.100, kartu penyesuaian 09-Jul-2026 barang nomor 1), satu baris keluar bersaldo minus (24-Apr-2026), dan tujuh dokumen pembalik `RV/26/00001` sampai `RV/26/00007` yang dokumen asalnya (`ZZV/1/...`) tidak ada lagi. Itu sisa uji, bukan kejadian operasional.
- **Stok barang jadi tidak otomatis** (bab 10, bagian 8): Stock Balance FG hanya benar bila gudang menyesuaikannya.
- **Kolom nilai tidak dibatasi hak harga.** Siapa pun yang boleh membuka modul Inventory melihat Stock Value, Recapitulation, dan Summary. Hanya Stock Card semua kelas yang memang bebas harga. Apakah nilai stok termasuk rahasia costing belum diputuskan di dokumen yang saya baca.
- **Dua ambang "stok mati"**: peta gudang memakai 6 bulan (pengaturan), Slow Moving 3 bulan, Stock Aging 90/180/365 hari. Barang yang sama bisa tampil "mati" di satu dan "bergerak lambat" di lain.

**Belum terverifikasi:** saya tidak membuka laporan di browser, tidak mencetak, dan tidak menjalankan tes (tes menulis ke database). Tampilan persis kontrol (urutan tombol, nama kolom dalam bahasa lain) belum saya lihat. Uji yang ada di repo (`StockReportsTest`) mencocokkan awal + gerak = akhir, rantai BB/EB lintas lokasi, dan Recapitulation dengan Summary, tetapi tidak saya jalankan. Tidak ada tes bernama untuk Slow Moving dan Stock Balance.
