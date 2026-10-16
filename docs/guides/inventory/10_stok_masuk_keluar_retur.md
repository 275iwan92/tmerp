# Stok Masuk, Stok Keluar, dan Retur dari Produksi: cara kerja dan apa yang ditolak sistem

*Panduan untuk Jo dan staf gudang. Ditulis dari membaca kode dan data mockup, 16 Oktober 2026. Tombol dan pesan ditulis persis seperti di layar; hal yang tidak bisa saya pastikan ada di bagian 8 "Belum terverifikasi". Kartu teknis: `docs/tg/inventory/stock/`.*

Setiap barang yang masuk ke gudang, keluar ke produksi, atau kembali dari produksi dicatat sebagai **dokumen stok**. Dokumen itu sendiri belum mengubah stok. Stok baru berubah ketika dokumen **diposting**: sistem menulis satu baris di **Kartu Stok** per baris dokumen, lalu menghitung ulang saldo. Aturannya sama untuk tiga kelas barang: **Raw Material** (bahan baku, RM), **Semi Finished Goods** (barang setengah jadi, SFG), dan **Finished Goods** (barang jadi, FG).

## 1. Dari mana membukanya

Menu **Inventory > Stock**, lalu pilih kelasnya: **Raw Material**, **Semi Finished Goods**, atau **Finished Goods**. Di tiap kelas ada lima butir: **Stock In**, **Stock Out**, **Return from Production**, **Stock Adjustment & Physical Count** (bab 11), dan **Adjustment Approval**, ditambah grup **Report** (bab 12).

Setiap butir membuka **daftar dokumen** (grid) dengan kolom nomor dokumen dan gudang, tanggal, kategori, sumber, referensi, catatan, dan status. Tombol **New** (di form lama tertulis **+ New Record**) membuka form dokumen baru; mengklik dua kali sebuah baris membuka dokumennya.

Dua batasan penting yang langsung terlihat:

- **Stock Out dan Return from Production hanya untuk RM dan SFG.** Di kelas Finished Goods, butir menunya ada tetapi daftarnya hanya bisa dibaca: tidak ada tombol New dan tidak bisa dibuka. Barang jadi memang dirancang keluar lewat Delivery Order, **tetapi saat ini Delivery Order tidak mengurangi stok barang jadi** (lihat bagian 7).
- **Stock In otomatis dari penerimaan barang.** Dokumen Stock In juga lahir sendiri ketika bagian Purchasing memposting penerimaan barang (SPB); itu muncul di daftar dengan Source `PV`, bukan `MANUAL`.

## 2. Status sebuah dokumen stok

| Status | Artinya | Bisa diubah? |
|---|---|---|
| DRAFT | Tersimpan, belum mengubah stok | Ya, bisa disimpan ulang |
| POSTED | Sudah masuk Kartu Stok | Tidak pernah diedit; dikoreksi dengan Cancel (pembalik) |
| CANCELLED | Draft yang dibatalkan, atau dokumen POSTED yang sudah dibalik | Tidak |
| SUBMITTED, REJECTED, REVISION_NEEDED | Hanya untuk Adjustment/Opname (bab 11) | Lihat bab 11 |

Catatan: dokumen stok tidak punya status **Closed**. Sebagian dokumen lama di data contoh (28 dokumen bahan baku dan 1 barang jadi) tertulis DRAFT tetapi sebenarnya sudah ditandai terkonfirmasi; dokumen seperti itu terbuka hanya-baca dan tidak bisa diposting atau diedit ulang. Itu data lama, bukan perilaku normal.

## 3. Membuat Stock In (stok masuk)

1. Buka **Inventory > Stock > Raw Material > Stock In**, klik **New**.
2. Isi header: **Tanggal Dokumen** (wajib), **Kategori Dokumen** (**STOCK** = masuk biasa, atau **BB** = saldo awal), **Gudang** (wajib), **Catatan**. **No. Dokumen** terisi otomatis saat disimpan, berbentuk `IN/26/00031` (kode IN, dua digit tahun, lima digit urut). **Diterima oleh** terisi nama Anda.
3. Cari barang di kotak pencarian (ketik minimal dua huruf kode atau nama), klik hasilnya untuk menambah baris.
4. Per baris isi **Batch** (pilih batch yang ada, atau **+ New batch…**), **Lokasi** (bin di gudang itu, wajib), **Qty** (wajib, lebih dari nol), dan **Harga Satuan**. Bila memilih batch baru, isi **Supplier Lot**, **Production Date**, **Expiry Date**. Bila barang itu punya masa simpan di master, **Expiry Date** wajib: pesan "This item has a shelf life: fill in the expiry date."
5. Klik **Simpan Draft** (**Save Draft**). Sistem menjawab "Draft IN/26/000nn tersimpan." dan kembali ke daftar.
6. Buka dokumennya lagi, klik **Konfirmasi & Posting** (**Confirm & Post**), jawab dialog "Posting IN/26/000nn ke Kartu Stok? Setelah posting tidak bisa diedit lagi." Hasilnya "Dokumen berhasil di-posting ke Kartu Stok."

**Harga Satuan** bawaannya 0 dan tidak wajib. Harga itulah yang menjadi nilai barang masuk dan membentuk harga rata-rata; jangan dibiarkan 0 untuk barang yang berharga, karena nilai stok dan harga rata-ratanya akan turun. Untuk barang titipan customer atau supplier (pemilik bukan perusahaan) sistem tidak menilai dan tidak menjurnal, apa pun harga yang diketik.

**Saldo awal (BB)** memakai form yang sama dengan kategori BB; barisnya tetap tercatat sebagai barang masuk di kartu.

**Menghapus sebelum posting:** pada dokumen DRAFT ada tombol **Batalkan** (dialog "Batalkan dokumen ini?"). Dokumen menjadi CANCELLED dan stok tidak berubah. Ini berbeda dengan Cancel pada dokumen yang sudah POSTED (bagian 6).

## 4. Membuat Stock Out (stok keluar ke produksi)

Hanya RM dan SFG. Alurnya sama dengan Stock In, dengan isian berbeda:

- **Gudang Sumber** (wajib), **Diserahkan Kepada (Dept.)** (wajib), **Proses Tujuan** (opsional, mis. Cetak Offset, Laminasi), **Catatan**; **Diserahkan oleh** terisi nama Anda.
- Per baris: **Lokasi Sumber**, **Qty Keluar** (lebih dari nol), **Lot/Batch**.
- Nomornya berbentuk `OUT/26/00005`.
- Tombol **Konfirmasi & Posting**: dialog "Barang akan keluar dari Kartu Stok, tidak bisa diedit lagi."

Sistem **tidak** mewajibkan nomor Work Order pada Stock Out manual dan **tidak** membandingkan dengan stok yang sudah dibooking. Yang menjaga hanya aturan "saldo tidak boleh minus" (bagian 5). Stock Out yang terarah ke Work Order dan terjaga booking-nya dibuat oleh **Pick List** di Warehouse saat dikonfirmasi; dokumennya muncul di daftar ini dengan Source `PICK_LIST`.

**Jurnal otomatis.** Stock Out RM dan SFG yang diposting membuat jurnal pemakaian: debit HPP, kredit Persediaan, bernilai jumlah keluar dikali harga rata-rata saat itu. Jika akun HPP atau Persediaan barang itu belum diisi, stok **tetap keluar**, tetapi muncul peringatan "... lines have no COGS/Inventory account yet..." disertai petunjuk melengkapi akun di Master Bahan Baku (atau kategorinya) atau Master Card (part). Peringatan itu perlu ditindaklanjuti bagian Akuntansi.

## 5. Yang ditolak sistem saat posting, dan alasannya

Posting selalu **ditolak seluruhnya**: bila satu baris gagal, tidak ada satu baris pun yang tertulis.

| Pesan di layar | Penyebab | Yang dilakukan |
|---|---|---|
| "The document has already been posted before." / "The document is already cancelled, it cannot be posted." | Dokumen sudah POSTED atau CANCELLED | Tidak perlu apa-apa |
| "The document date is in a closed inventory period (closed through dd-Mmm-yyyy). Enter a Stock Adjustment dated in an open period instead." | Tanggal dokumen jatuh di periode inventory yang sudah ditutup (bab 13) | Ubah tanggal draft ke periode terbuka, atau pakai Stock Adjustment |
| "Saldo item #... di lokasi #... akan negatif (...) -- posting dibatalkan." | Barang keluar lebih banyak dari saldo bin itu pada tanggal tersebut | Kurangi qty, pilih bin lain, atau masukkan dulu Stock In-nya |
| "Item #... in location #... would go below zero on dd-Mmm-yyyy (...) because of movements dated after this document -- posting refused." | Dokumen **bertanggal mundur** akan membuat saldo di tanggal yang lebih baru menjadi minus | Periksa pergerakan sesudah tanggal itu |

**Tanggal boleh mundur.** Sistem tidak membatasi berapa hari ke belakang (pengaturan batas hari mundur ada di Settings tetapi belum dibaca program). Dokumen bertanggal mundur disisipkan di tempat yang benar dalam Kartu Stok, lalu semua baris sesudahnya dihitung ulang: saldo awal, saldo akhir, harga rata-rata, nilai. Akibatnya, **harga rata-rata barang keluar yang sudah diposting bisa berubah**. Sistem menampilkan peringatan "The average price of n later stock-out row(s) changed; their value changed by ..." dan, untuk bahan baku, membuat jurnal koreksi baru; jurnal lama tidak diubah.

Stok juga tidak pernah bisa minus lewat Stock Out; satu-satunya pengecualian lama adalah Adjustment/Opname (bab 11).

## 6. Salah input sesudah posting: Cancel (pembalik) dan Duplicate

Dokumen POSTED tidak pernah diedit atau dihapus. Buka dokumennya: di atas form ada tombol **Cancel** dan **Duplicate**.

1. Klik **Cancel**. Kotak **Reason** terisi usulan "Kesalahan transaksi no. IN/26/00031"; boleh diubah, tidak boleh kosong ("Fill in the reason for cancelling.").
2. Klik **Cancel Document**. Sistem membuat dokumen **pembalik** bernomor `RV/26/0000n` dengan arah berlawanan, memostingnya lewat jalur biasa, lalu menandai dokumen asal **Cancelled** (dengan alasan dan penunjuk ke pembaliknya). Pembalik bertanggal **hari ini**, bukan tanggal dokumen asal. Jurnal pemakaian dokumen asal ikut dibalik; bila pembalikan jurnal gagal, hanya muncul peringatan.
3. **Duplicate** membuat salinan DRAFT baru (nomor baru, tanggal hari ini) untuk diperbaiki lalu diposting ulang.

Yang ditolak (pesan di bawah form): "Only a posted document can be cancelled with a reversal. A Draft is edited or deleted."; "The document is already cancelled."; "A reversal document cannot be cancelled again."; "Adjustment, Opname and opening balance are corrected from their own screens, not by a reversal."; "This document was created by another process (...). Cancel it from its source document." (mis. Stock In dari SPB dibatalkan di SPB; Stock Out dari Pick List di Pick List); serta penolakan posting (periode tertutup atau saldo menjadi minus, misalnya membatalkan penerimaan yang barangnya sudah dipakai).

## 7. Return from Production (retur dari produksi)

Hanya RM dan SFG; hanya untuk barang yang **sebelumnya keluar lewat Stock Out yang sudah POSTED**.

1. **Return from Production > New**. Klik pilihan **Ref. Stock Out** dan pilih Stock Out; hanya yang POSTED, belum dibatalkan, dan masih punya sisa yang bisa diretur yang tampil. Barisnya terisi otomatis dengan sisa yang boleh diretur.
2. Isi **Tanggal Dokumen**, **Gudang Tujuan (bahan layak)**, lalu per baris: **Qty** retur, kondisi **GOOD** atau **SCRAP**, **Lokasi**, dan keterangan.
3. GOOD kembali ke bin yang bisa dipakai di gudang tujuan. SCRAP (afkir) masuk ke bin yang ditandai **tidak bisa dipakai** dan **keterangannya wajib**: "Keterangan wajib diisi utk baris afkir/SCRAP (...)".
4. Qty retur tidak boleh melebihi sisa: "Qty retur ... melebihi sisa yang boleh diretur (...)".
5. **Simpan Draft**, lalu **Konfirmasi & Posting**: "Retur berhasil diposting & Kartu Stok ter-update." Nomornya `RTN/26/0000n`. Sisa retur di baris Stock Out asal ikut berkurang.

Nilai retur = harga rata-rata saat barang itu keluar, jadi barang kembali dengan nilai keluarnya.

**Dua hal yang perlu diketahui:** (a) retur **tidak** membuat jurnal pembalik atas jurnal pemakaian Stock Out, sehingga nilai persediaan di buku besar lebih kecil daripada Kartu Stok sampai Akuntansi menjurnal manual (ini terlihat di Daily Reconciliation, bab 13); (b) barang SCRAP tetap dihitung sebagai nilai persediaan sampai dihapuskan lewat Obsolete Stock atau Adjustment.

## 8. Catatan untuk Jo: temuan dan yang belum terverifikasi

**Temuan yang memengaruhi pemakaian harian:**

- **Barang jadi tidak punya pergerakan otomatis.** Stok FG hanya bertambah lewat Stock In manual (atau hitung fisik) dan tidak berkurang saat Delivery Order dikirim. Stok FG di Kartu Stok karenanya hanya benar bila gudang menyesuaikannya sendiri.
- **Pembatalan Stock Out yang sudah punya retur tidak ditolak**, dan membatalkan Retur tidak mengembalikan sisa retur di Stock Out asal. Bisa membuat stok bertambah dua kali.
- **Stok di master bahan baku ("Stok" di Raw Material) bisa berbeda dari Kartu Stok.** Di data contoh, 46 dari 81 bahan berbeda (45 di antaranya kosong). Gunakan **Stock Balance** sebagai acuan.
- **Dua pembatalan berbeda**: tombol **Batalkan** (Draft, tanpa efek stok) dan **Cancel** (dokumen POSTED, lewat pembalik).
- **Tombol berbahasa Indonesia**: form lama memakai **Simpan Draft**, **Konfirmasi & Posting**, **Batalkan**; aturan tampilan menginginkan tombol Inggris (Save, Confirm, Cancel). Form Return malah tertulis tetap Indonesia.
- Dokumen stok bernomor ganda atau lama (mis. `26BB00005-DUP2`, `26SPB...`) ada di data contoh.

**Belum terverifikasi:** saya tidak membuka layar di browser dan tidak menjalankan tes (tes akan menulis ke database); semua dari kode dan data mockup. Tidak diperiksa: hak akses per tombol (hanya terlihat bahwa seluruh Inventory memakai satu hak modul), hasil terjemahan bahasa Inggris tiap label di tmerp.test, dan perilaku Stock In dari SPB selain yang terbaca di kode. Nilai uang dan akun jurnal sengaja tidak dicantumkan di sini.
