# Laporan Stok Semua Kelas — dokumen review untuk user

Dibangun 2026-10-02 atas permintaan user: *"build all reports related with inventory movement, stock card and other
reports"* — *"stockcard, summary all stocks, recapitulation of all items, inspection stocks, report items group by location
warehouse, loss and gain all items every monthly"* — dengan acuan ERP populer (`inventoryreport_references.md`).

Situs: **mockup.test** (database dummy `ddt*`). Menu: **Inventory > Warehouse > Report**.
Tangkapan layar dari situs dummy: `docs/mockups/inventory_report_app/` (1 Stock Card, 2 Stock Recapitulation, 3 Stock Summary,
4 Stock Opname, 5 Stock Loss & Gain, 6 Stock in Inspection, 7 Stock by Location, 8 dan 9 cetakan).

## 1. Yang bisa dibuka sekarang

| Laporan | Isi | Dibuka dengan |
|---|---|---|
| **Stock by Location** | Stok sekarang per item per bin, terkelompok per gedung; baris merah = dead stock | sudah ada; kini langsung terkelompok per gedung |
| **Stock Card** | Per item: saldo awal, tiap pergerakan urut tanggal, saldo berjalan item di semua bin | Period + Show; kotak Item untuk satu item |
| **Stock Recapitulation** | Satu baris per item: awal, masuk, keluar, penyesuaian, akhir, nilai akhir | Period + Show |
| **Stock Summary** | Nilai semua stok per kelas x kategori: awal, masuk, keluar, penyesuaian, akhir, porsi % | Period + Show |
| **Stock Opname** | Hasil hitung fisik dan penyesuaian per dokumen per item: qty sistem, qty fisik, selisih, nilai | Period + Show |
| **Stock Loss & Gain** | Selisih lebih dan kurang tiap item per bulan (qty dan nilai) | Period + Show; terbuka awal tahun sampai hari ini |
| **Stock in Inspection** | Barang menunggu QC, ditolak belum diretur, di area QC Hold, di bin diblokir | keadaan hari ini |

Laporan per kelas (Stock Balance, Stock Card, Slow Moving Item untuk Raw Material / Finished Goods / Semi Finished Goods)
tetap ada di menu kelasnya dan tidak diubah.

## 2. Cara pakai

1. Buka laporannya dari menu. Laporan berperiode terbuka pada **bulan berjalan** (Stock Loss & Gain: tahun berjalan).
2. Ubah **Period** (tanggal awal dan akhir), klik **Show**.
3. Di atas tabel: kotak cari, saringan cepat (mis. In / Out / Adjustment, Loss / Gain), **Filters**, **Group**, **Columns**
   (kolom tambahan seperti nilai per pergerakan, harga rata-rata, Turnover, lot).
4. **Print** membuka versi kertas (A4 landscape): kop perusahaan, periode, baris yang sama, total, kotak tanda tangan.
   Kolom yang tercetak = kolom bawaan laporan.
5. Kartu satu item: di Stock Card ketik kode atau sebagian nama di **Item**, klik Show, lalu Print.

## 3. Dari mana angkanya

- Semua angka periode dihitung satu tempat: `App\Services\Inventory\StockLedger` (terdaftar di Developer > Engines).
- **Saldo awal** = baris kartu stok terakhir tiap item di tiap bin sebelum tanggal awal; **saldo akhir** = baris terakhir
  di dalam periode (atau saldo awal bila tidak bergerak). Sama dengan cara view saldo (`v_*_onhand`) membaca stok.
- **Nilai pergerakan** = nilai posisi sesudah baris itu dikurangi nilainya sebelum baris itu (harga rata-rata bergerak).
  Karena itu nilai awal + masuk - keluar + penyesuaian = nilai akhir selalu cocok.
- **Stock Transfer bukan masuk / keluar item** (aturan A.22): di Stock Card tampil di kolom Transfer, di rekap tidak dihitung.
- **Stock Loss & Gain** hanya memuat penyesuaian yang sudah **Posted** (sudah mengubah stok); **Stock Opname** memuat
  semua dokumen termasuk Draft dan yang menunggu approval.
- Dicek di ddt: total nilai akhir Stock Recapitulation = total nilai stok di view saldo (457.975.000).

## 4. Yang diputuskan agen sendiri — menunggu koreksi user

| # | Keputusan | Alasan | Bila ingin lain |
|---|---|---|---|
| 1 | Letak menu: Inventory > Warehouse > Report (bukan grup laporan sendiri) | aturan menu: laporan ada di submenu Report grup fungsinya; Warehouse = grup lintas kelas | pindah grup = ubah baris menu saja |
| 2 | "Summary all stocks" = ringkasan NILAI per kelas x kategori; "recapitulation of all items" = satu baris per item (qty + nilai) | qty lintas item tidak bisa dijumlah (satuan berbeda); acuan: NetSuite Inventory Valuation Summary, Accurate Nilai Persediaan, ERPNext Stock Balance | tukar nama, atau tambah kolom |
| 3 | "Inspection stocks" dibaca dua arah: **Stock Opname** (pemeriksaan fisik stok) dan **Stock in Inspection** (stok tertahan QC / diblokir, padanan quality inspection stock SAP) | kalimat user bisa berarti keduanya | hapus salah satu bila bukan itu maksudnya |
| 4 | Loss & Gain per bulan dihitung dari penyesuaian Posted, dinilai harga rata-rata saat itu | hanya yang Posted mengubah stok; penyesuaian tidak mengubah harga rata-rata | — |
| 5 | Kolom nilai terlihat oleh semua yang boleh membuka Inventory | sama dengan Stock Balance yang sudah ada | batasi ke Finance / Costing / Ops Manager ke atas (backlog 05.34) |
| 6 | Kelas stok ditulis lengkap (Raw Material, Semi Finished Goods, Finished Goods) | lebih jelas di judul kelompok dan cetakan | pakai kode RM / SFG / FG |
| 7 | Cetakan laporan tidak punya salinan online + QR (beda dari cetakan dokumen) | laporan internal berisi nilai stok | tambahkan bila dibutuhkan |
| 8 | Tanda tangan cetakan: Prepared By (Warehouse), Checked By (Head of Warehouse / Accounting / Quality Control), Approved By | perkiraan; belum ada ketetapan penanda tangan laporan | sebutkan jabatannya |
| 9 | Stock Card di layar dibatasi 20.000 baris | grid memuat semua baris sekaligus | persempit periode atau isi Item |
| 10 | Menu, Help, dan data contoh hanya di mockup.test; kamus terjemahan di mockup.test dan tmerp.test | menu Warehouse belum ada di tmerp.test (backlog 05.20) | pasang bersama menu Warehouse |

## 5. Yang terlihat di data dummy (bukan salah laporan)

- Satu item setengah jadi lama (Body Box) punya kartu stok yang saldo berjalannya tidak cocok dengan pergerakannya
  (masuk 380, saldo 300) — sudah tercatat di backlog 05.17 (dummy lama).
- Beberapa item lama bernilai 0 karena harga rata-ratanya kosong; karena itu Stock Loss & Gain bulan Juli bernilai 0
  walau qty-nya ada.
- Dokumen opname dummy lama berada di gudang buatan sistem (`WH-TEST-01`).
- Empat dokumen dummy lama (`26OP00014`, `26AD00014`, `26OP00016`, `26OP00018`) berstatus Draft tetapi sudah punya baris
  kartu stok. Di Stock Opname tampil "Not posted yet", sedangkan angkanya ikut Stock Loss & Gain (yang membaca kartu stok).
  Dokumen yang diposting lewat aplikasi selalu berstatus POSTED; ini sisa dummy lama, ikut dibersihkan bersama 05.17.

## 6. Belum dibangun (ada di ERP acuan)

Umur stok per rentang hari (05.30), analisis ABC (05.31), nilai stok vs saldo akun persediaan (05.32), barang diterima
belum ditagih (05.33), kedaluwarsa (menunggu FEFO, 05.7), stok dalam perjalanan (hanya bila ada gudang beda kota, 05.13),
Stock On Hand gabungan dengan Booked / Available / Incoming (05.14), lembar hitung kosong dan berita acara opname (05.36).

## 7. Pertanyaan untuk user

1. Apakah pembagian nama di butir 4.2 dan 4.3 sudah sesuai maksud Anda?
2. Siapa yang boleh melihat nilai stok di laporan ini (butir 4.5)?
3. Siapa penanda tangan tiap laporan cetak (butir 4.8)?
4. Rentang hari untuk laporan umur stok (mis. 0-30 / 31-60 / 61-90 / lebih dari 90)?
5. Menu laporan ini di tmerp.test dipasang bersama menu Warehouse (backlog 05.20) — tunggu penilaian Anda, atau sekarang?
