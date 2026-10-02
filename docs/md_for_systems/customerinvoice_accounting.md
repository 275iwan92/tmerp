# CUSTOMER INVOICE, UANG MUKA, PIUTANG — KETENTUAN KEUANGAN & AKUNTANSI

> Dokumen ini menjelaskan **urusan keuangan dan akuntansi** modul Customer Invoice + Accounts Receivable:
> dokumen apa yang terbit, kapan nomornya lahir, jurnal apa yang terbentuk, pajak apa yang timbul, dan
> pengendalian apa yang dijaga aplikasi. Ditulis untuk Finance, Accounting, dan pemeriksa.
>
> Dasar: keputusan user 2026-10-02 (bagian 13), analisa data lama dan aturan di
> `customerinvoice_plan.md` bagian 9. Rancangan layar dan tabel ada di `customerinvoice_plan.md`.
> **Status: ketentuan disetujui user. Menagih, uang muka, dan penerimaan pelunasan sudah terpasang di mockup.test
> dan tmerp.test (2026-10-02); layar lama Order to Cash dan Down Payment dihentikan atas perintah user** — lihat
> `customerinvoice_review.md`. Butir pajak (bagian 8) wajib dikonfirmasi konsultan pajak perusahaan sebelum dipakai
> untuk transaksi nyata.
> **Tambahan 2026-10-02 (user: "setiap tagihan harus related dengan surat jalan pengiriman dan validasi dari customer
> nilai yang terkirim, karena bisa ada retur dan rusak di jalan; uang muka jelas dari CO yang mana dan historinya
> terlacak"):** bukti terima per baris Surat Jalan dengan alasan selisih (7A), buku uang muka per Customer Order (7B),
> daftar To Invoice, AR Aging, Down Payment Ledger, tab Billing di Customer Order, satu akun bank per rekening (4).
> Belum dibangun: PPh 23 / biaya bank / kelebihan bayar di penerimaan (5.4), Credit Note (5.6), pengembalian uang muka (5.7),
> Statement of Account dan Invoice Register (bagian 11), pengingat tenggat Faktur Pajak, pemisahan hak per aksi (bagian 10),
> saldo awal (bagian 12).

---

## 1. Dua identitas penagihan

| | Non PPN | PPN |
|---|---|---|
| Kop cetakan | Telasih Makmur | CV. Langgeng Jaya Makmur + NPWP |
| Penentu | `Customer Order` berstatus Non PPN | `Customer Order` berstatus PPN |
| PPN | tidak dipungut, tidak ada Faktur Pajak | dipungut, Faktur Pajak lewat Coretax |
| Rekening transfer bawaan | BCA 331.030.0313 a.n. Puspita Dewi Widodo | Maspion 124.200.1997 a.n. CV. Langgeng Jaya Makmur |
| Seri nomor invoice | seri sendiri | seri sendiri |

Status PPN ditetapkan di Customer Order dan ikut ke Delivery Order, Surat Jalan, tagihan uang muka, dan invoice.
Satu invoice tidak boleh mencampur order PPN dan Non PPN.

---

## 2. Dokumen dan artinya secara akuntansi

| Dokumen | Dibuat oleh | Arti | Jurnal |
|---|---|---|---|
| **Down Payment Invoice** (Tagihan Uang Muka) | Accounting | permintaan pembayaran di muka atas sebuah Customer Order | **tidak ada** |
| **Customer Receipt — uang muka** + cetakan Tanda Terima Uang Muka | Kasir | uang muka diterima | ya (bagian 5.1) |
| **Customer Invoice** | Penagihan | tagihan atas barang yang sudah diterima customer | ya (5.2) |
| Pemotongan uang muka di invoice | otomatis saat invoice di-Confirm | uang muka dipakai melunasi sebagian invoice | ya (5.3) |
| **Customer Receipt — pelunasan** | Kasir | pembayaran invoice | ya (5.4) |
| **Credit Note** (tahap 3) | Penagihan + persetujuan | retur / koreksi harga sesudah invoice di-Confirm | ya (5.6) |

Tagihan uang muka **bukan piutang** dan tidak masuk umur piutang. Alasannya: barang belum diserahkan, jadi
perusahaan belum berhak atas uang itu; yang timbul saat uang masuk adalah **kewajiban** (uang muka pelanggan),
bukan pendapatan.

---

## 3. Penomoran

| Dokumen | Format bawaan | Urut kembali ke 1 |
|---|---|---|
| Customer Invoice Non PPN | `275/TM/IX/2026` — urut / TM / bulan romawi / tahun | tiap bulan |
| Customer Invoice PPN | `118/LJM/IX/2026` — urut / LJM / bulan romawi / tahun | tiap bulan |
| Down Payment Invoice Non PPN | `12/UM-TM/IX/2026` | tiap bulan |
| Down Payment Invoice PPN | `5/UM-LJM/IX/2026` | tiap bulan |
| Customer Receipt | nomor bukti kas / bank yang sudah ada (`BBM/2026/09/001`, `BKM/…`) | tiap bulan |
| Credit Note | `3/CN-TM/IX/2026`, `1/CN-LJM/IX/2026` | tiap bulan |

Ketentuan:

1. **Nomor lahir saat Confirm**, dari seri. Tidak bisa diketik dan tidak bisa diubah.
2. **Draft belum bernomor.** Menghapus draft tidak membuat nomor melompat.
3. **Nomor tidak pernah dipakai ulang.** Invoice yang dibatalkan tetap memegang nomornya dan tetap terlihat di
   daftar dengan status Cancelled.
4. **Bulan pada nomor = bulan tanggal invoice** (bukan bulan saat diketik).
5. Format, kode (`TM`, `LJM`, `UM-…`), dan periode urut ulang disimpan sebagai **data** di master seri nomor dan
   bisa diubah dari layar oleh yang berwenang; perubahan berlaku untuk nomor berikutnya saja.
6. Laporan **nomor melompat dan nomor ganda** tersedia per seri per bulan.
7. **Nomor Faktur Pajak bukan nomor invoice.** Nomor Faktur Pajak diberikan Coretax saat faktur diunggah; aplikasi
   menyimpannya di invoice sesudah itu. Nomor invoice perusahaan diisikan sebagai referensi di Coretax.

Kode `LJM` dan `UM-…` adalah usulan bawaan; boleh diganti di master seri sebelum dipakai.

---

## 4. Akun yang dipakai

| Kode | Nama | Dipakai untuk |
|---|---|---|
| 111100 | Kas Operasional Pabrik | penerimaan tunai |
| 111210 – 111250 | Bank, **satu akun per rekening perusahaan** (lihat di bawah) | penerimaan transfer |
| 112100 | Piutang Usaha - Customer Lokal | invoice |
| 112200 | Piutang Usaha - Customer Ekspor | invoice customer ekspor |
| 212100 | Hutang PPN Keluaran | PPN atas uang muka dan invoice |
| 213200 | Uang Muka Penjualan dari Pelanggan | uang muka diterima, kelebihan bayar |
| 411000 / 412000 / 413000 | Penjualan / Pendapatan Jasa | pendapatan (per kategori product) |
| 421000 | Diskon Penjualan & Retur | Credit Note |
| 812000 | Beban Administrasi Bank | potongan bank saat transfer masuk |

**Akun bank per rekening** (dibuat 2026-10-02 atas perintah user "sarankan yang terbaik dan jalankan dulu"; di ddt dan tm2):

| Akun | Rekening | Identitas |
|---|---|---|
| 111210 | Bank MASPION - CV. Langgeng Jaya Makmur | PPN (bawaan) |
| 111220 | Bank BNI - CV. Langgeng Jaya Makmur | PPN |
| 111230 | Bank BCA - Puspita Dewi Widodo | Non PPN (bawaan) |
| 111240 | Bank BCA - Carrij Soehendra | Non PPN |
| 111250 | Bank MASPION - Carrij Soehendra | Non PPN |

Alasan satu akun per rekening: saldo buku tiap rekening bisa dicocokkan dengan rekening korannya (rekonsiliasi bank),
dan uang identitas PPN tidak tercampur dengan Non PPN. Akun 111200 lama tidak diubah dan tidak dipakai modul ini.
Nama akun dan pemetaannya boleh diubah Finance di Master of Accounts dan Company Bank Accounts.

Yang **belum ada** di daftar akun dan harus dibuat sebelum dipakai:

- **PPh 23 Dibayar Dimuka** (aset), untuk PPh 23 yang dipotong customer;
- akun selisih pembayaran kecil (pembulatan).

Akun piutang per customer mengikuti pemetaan customer (bila ada), selain itu 112100 / 112200.

---

## 5. Peristiwa dan jurnalnya

Semua jurnal dibuat aplikasi dan bisa dilihat di Journal Entries. Tanggal jurnal = tanggal dokumennya. Periode
yang sudah ditutup tidak bisa diisi.

### 5.1 Uang muka diterima

Tagihan uang muka sendiri tidak menjurnal. Jurnal lahir saat kasir mencatat uangnya.

**Non PPN**

| Akun | Debit | Kredit |
|---|---:|---:|
| Bank | 2.000.000 | |
| 213200 Uang Muka Penjualan | | 2.000.000 |

**PPN** — PPN terutang saat uang diterima; Faktur Pajak "Uang Muka" dibuat dengan tanggal penerimaan.

| Akun | Debit | Kredit |
|---|---:|---:|
| Bank | 5.550.000 | |
| 213200 Uang Muka Penjualan | | 5.000.000 |
| 212100 Hutang PPN Keluaran | | 550.000 |

Uang muka dicatat **per Customer Order**, sehingga sisa uang muka tiap order selalu bisa dilihat.

### 5.2 Customer Invoice di-Confirm

Tanggal invoice mengikuti tanggal Surat Jalan terakhir yang ditagih. Nilai invoice **selalu nilai penuh** barang
yang ditagih.

| Akun | Debit | Kredit |
|---|---:|---:|
| 112100 Piutang Usaha | 11.100.000 | |
| 411000 Penjualan | | 10.000.000 |
| 212100 Hutang PPN Keluaran (hanya order PPN) | | 1.100.000 |

### 5.3 Uang muka dipotongkan ke invoice

Terjadi bersamaan dengan Confirm invoice. Aplikasi mengusulkan uang muka order yang sama yang sudah diterima dan
belum terpakai; petugas boleh mengurangi nilainya (sisanya untuk invoice berikut).

**Non PPN**

| Akun | Debit | Kredit |
|---|---:|---:|
| 213200 Uang Muka Penjualan | 2.000.000 | |
| 112100 Piutang Usaha | | 2.000.000 |

**PPN** — PPN yang sudah dibayar saat uang muka dikeluarkan dari invoice, supaya tidak dipungut dua kali.

| Akun | Debit | Kredit |
|---|---:|---:|
| 213200 Uang Muka Penjualan | 5.000.000 | |
| 212100 Hutang PPN Keluaran | 550.000 | |
| 112100 Piutang Usaha | | 5.550.000 |

Faktur Pajak "Pelunasan" memuat sisa (di contoh: PPN 550.000) dan merujuk nomor Faktur Pajak uang mukanya.

### 5.4 Pelunasan diterima

Satu penerimaan boleh melunasi beberapa invoice, boleh sebagian.

**Yang sudah berjalan (Record Payment di form invoice, 2026-10-02):** satu penerimaan = satu bukti bank masuk
(`fin_voucher` sumber `AR_RECEIPT`) dengan jurnal **Dr Bank (akun rekening yang dipilih) / Cr 112100 Piutang Usaha**,
dan satu baris alokasi per invoice (`fin_payment_alloc`). Dijaga: invoice harus Confirmed, milik customer yang sama
dan identitas (PPN / Non PPN) yang sama dengan rekeningnya; nilai per invoice tidak boleh melebihi sisanya; tanggal
harus di periode buku terbuka; rekening harus punya akun buku besar. Invoice lunas = Closed. Invoice yang sudah
menerima pembayaran tidak bisa di-Cancel.

**Yang belum (sisa Tahap 2):** baris PPh 23, biaya administrasi bank, dan kelebihan bayar pada tabel di bawah.
Sampai itu ada, penerimaan dicatat sebesar uang yang masuk; selisihnya tetap terbuka sebagai sisa piutang.

| Akun | Debit | Kredit |
|---|---:|---:|
| Bank | 5.439.000 | |
| PPh 23 Dibayar Dimuka (bila customer memotong) | 100.000 | |
| 812000 Beban Administrasi Bank (bila ada) | 11.000 | |
| 112100 Piutang Usaha | | 5.550.000 |

- **Kelebihan bayar** masuk 213200 Uang Muka Penjualan atas nama customer dan bisa dipakai invoice berikutnya.
- **PPh 23**: bukti potong dari customer dicatat (nomor, tanggal). Selama bukti potong belum diterima, baris itu
  ditandai "bukti potong belum ada".

### 5.5 Invoice dibatalkan (Cancel)

Hanya bila periodenya masih terbuka dan belum ada pembayaran. Wajib alasan. Aplikasi membuat jurnal balik
5.2 dan 5.3, melepas uang muka dan qty Surat Jalan supaya bisa ditagih ulang. Nomor invoice tidak dipakai lagi.

### 5.6 Credit Note (tahap 3)

Untuk retur atau koreksi harga sesudah invoice di-Confirm, termasuk bila periode sudah ditutup atau invoice
sudah dibayar.

| Akun | Debit | Kredit |
|---|---:|---:|
| 421000 Diskon Penjualan & Retur | 1.000.000 | |
| 212100 Hutang PPN Keluaran (order PPN, sesudah nota retur diterima) | 110.000 | |
| 112100 Piutang Usaha | | 1.110.000 |

### 5.7 Uang muka dikembalikan (order batal, tahap 3)

Debit 213200 (dan 212100 untuk order PPN, dengan nota pembatalan) pada kredit Bank.

---

## 6. Contoh lengkap

### 6.1 Non PPN, uang muka nominal (dari lampiran invoice lama)

| Tanggal | Peristiwa | Jurnal |
|---|---|---|
| 15-Sep-2026 | uang muka Rp 2.000.000 diterima | Dr Bank 2.000.000 / Cr 213200 2.000.000 |
| 01-Okt-2026 | invoice 508 pcs × 7.200 = 3.657.600 | Dr 112100 3.657.600 / Cr 411000 3.657.600 |
| 01-Okt-2026 | uang muka dipotong | Dr 213200 2.000.000 / Cr 112100 2.000.000 |
| saat dibayar | pelunasan | Dr Bank 1.657.600 / Cr 112100 1.657.600 |

Di cetakan invoice: Subtotal 3.657.600 · Uang Muka 15-Sep-2026 (2.000.000) · **Sisa yang harus dibayar
1.657.600**. Saldo akhir: piutang 0, uang muka 0, penjualan 3.657.600, kas 3.657.600.

### 6.2 Non PPN, uang muka 50% (dari lampiran tagihan uang muka lama)

Nilai order 3.000 pcs × 5.000 = 15.000.000. Tagihan uang muka 50% = **7.500.000** (tanpa jurnal). Saat dibayar:
Dr Bank 7.500.000 / Cr 213200 7.500.000. Saat barang dikirim dan ditagih 15.000.000, uang muka 7.500.000
dipotong, sisa 7.500.000.

### 6.3 PPN, uang muka 50%, dua kali kirim

Harga jual 10.000.000. DPP nilai lain = 11/12 × 10.000.000 = 9.166.667. PPN 12% × 9.166.667 = 1.100.000. Total
11.100.000. Uang muka 50% = 5.550.000 (harga 5.000.000 + PPN 550.000).

| # | Peristiwa | Jurnal | Faktur Pajak |
|---|---|---|---|
| 1 | uang muka 5.550.000 diterima | Dr Bank 5.550.000 / Cr 213200 5.000.000 / Cr 212100 550.000 | Uang Muka: PPN 550.000 |
| 2 | kirim 40%, invoice 4.000.000 + PPN 440.000 | Dr 112100 4.440.000 / Cr 411000 4.000.000 / Cr 212100 440.000 | atas sisa invoice ini |
| 3 | uang muka dipotong 50% dari invoice | Dr 213200 2.000.000 / Dr 212100 220.000 / Cr 112100 2.220.000 | PPN bersih invoice ini 220.000 |
| 4 | kirim 60%, invoice 6.000.000 + PPN 660.000 | Dr 112100 6.660.000 / Cr 411000 6.000.000 / Cr 212100 660.000 | Pelunasan |
| 5 | sisa uang muka dipotong | Dr 213200 3.000.000 / Dr 212100 330.000 / Cr 112100 3.330.000 | PPN bersih invoice ini 330.000 |
| 6 | pelunasan dua invoice | Dr Bank 5.550.000 / Cr 112100 5.550.000 | — |

Jenis Faktur Pajak untuk pengiriman bertahap (langkah 2) mengikuti petunjuk konsultan pajak; angka PPN-nya
tetap seperti di tabel.

Pencocokan akhir: kas masuk 5.550.000 + 5.550.000 = **11.100.000**; penjualan **10.000.000**; PPN keluaran
550.000 + 220.000 + 330.000 = **1.100.000**; saldo uang muka **0**; saldo piutang **0**.

Bila barang yang dikirim lebih atau kurang dari pesanan (toleransi cetak), invoice mengikuti qty yang diterima
customer; uang muka tetap dipotong sebesar yang pernah diterima.

---

## 7. Dasar tagih, tanggal, dan pisah batas

1. **Yang ditagih = qty yang diterima customer** menurut Surat Jalan yang sudah kembali (Proof of Delivery), dikurangi
   yang sudah pernah ditagih. Aplikasi menolak menagih melebihi itu. Rinciannya di 7A.
2. **Baris Qty Minim** (harga borongan): nilai baris = harga yang disepakati, berapa pun qty-nya; ditagih satu
   kali, pada invoice pertama yang memuat baris itu.
3. **Tanggal invoice** bawaan = tanggal Surat Jalan terakhir yang ditagih. Ini juga saat pendapatan diakui dan
   (untuk order PPN) saat PPN terutang.
4. **Jatuh tempo** = tanggal invoice + hari pada syarat bayar. Bayar Dimuka / Kontan / Di Tempat = hari itu juga.
5. **Mundur tanggal** hanya boleh ke periode yang masih terbuka.
6. **Akhir bulan:** laporan *Unbilled Delivery* (sudah diterima customer, belum ditagih) dan *Invoiced Not
   Delivered* dipakai Accounting untuk pisah batas. Aplikasi tidak membuat jurnal akrual otomatis; bila
   diperlukan, Accounting membuatnya lewat Journal Entries berdasarkan laporan itu.

---

## 7A. Bukti di balik tiap tagihan (Surat Jalan + validasi customer)

Ketentuan user 2026-10-02: **setiap tagihan harus berasal dari Surat Jalan pengiriman dan dari apa yang divalidasi
customer**, karena barang bisa diretur atau rusak di jalan.

| Aturan | Cara aplikasi menjaganya |
|---|---|
| Tidak ada invoice tanpa Surat Jalan | invoice hanya bisa dibuat dari baris Surat Jalan satu Customer Order; Confirm ditolak bila ada baris tanpa Surat Jalan |
| Tidak ada tagihan sebelum customer menerima | baris Surat Jalan baru bisa ditagih sesudah **Proof of Delivery** dicatat (hasil Received atau Partially received) |
| Yang ditagih = yang diterima | qty diterima diisi per baris di Proof of Delivery; qty tagih tidak bisa melebihi qty diterima dikurangi yang sudah ditagih |
| Selisih harus beralasan | bila diterima < dikirim, **Reason for Difference** wajib: Damaged in transit, Returned by customer, Short shipped, Other; ditambah catatan kondisi |
| Barang ditolak seluruhnya | hasil Rejected by customer / Customer closed: tidak ada qty diterima, tidak bisa ditagih, Surat Jalan terbuka untuk kiriman ulang |
| Bukti tersimpan | nama penerima, jam tiba, foto Surat Jalan bertanda tangan; tampil di invoice (tab Delivery) dan di Customer Order (tab Billing) |

Secara akuntansi: **pendapatan dan piutang diakui hanya atas barang yang diterima customer**. Barang yang rusak di
jalan atau diretur saat serah terima tidak pernah menjadi piutang, sehingga tidak perlu jurnal koreksi.

**Yang belum:** retur **sesudah** invoice di-Confirm (customer menemukan cacat kemudian). Ini memerlukan Credit Note
(5.6) dan Nota Retur pajak untuk order PPN, dan terkait modul Complaint & Return (CRM). Sengaja belum dibangun karena
cara perusahaan menyelesaikannya belum diketahui (kirim ganti barang, potong tagihan berikutnya, atau kembalikan uang)
dan perlu wawancara pemakai. Sampai itu ada: invoice yang belum dibayar di-Cancel lalu dibuat ulang; invoice yang sudah
dibayar dikoreksi Accounting lewat Journal Entries.

**Nasib barang yang tidak diterima** (kembali ke gudang barang jadi, dikerjakan ulang, atau dimusnahkan) belum dicatat
aplikasi; alasan selisih di Proof of Delivery menjadi dasar keputusannya.

---

## 7B. Jejak uang muka per Customer Order

Ketentuan user 2026-10-02: **uang muka harus jelas dari Customer Order yang mana dan riwayatnya terlacak**.

| Aturan | Cara aplikasi menjaganya |
|---|---|
| Uang muka selalu milik satu order | tagihan uang muka hanya bisa dibuat dari Customer Order; Confirm ditolak bila tanpa order |
| Uang muka hanya memotong invoice order yang sama | usulan potongan di invoice hanya memuat uang muka order itu |
| Tidak dipotong melebihi yang diterima | potongan dibatasi sisa uang muka (uang diterima dikurangi yang sudah dipotong) |
| Riwayat lengkap | **buku uang muka** per order, urut tanggal: ditagih → uang diterima (nomor bukti bank) → dipotong di invoice (nomor invoice), dengan saldo berjalan |
| Tampil di semua dokumen terkait | Down Payment Invoice (tab Down Payment History), Customer Order (tab Billing), laporan Down Payment Ledger, cetakan invoice (Ref. Uang Muka) |

Saldo buku uang muka satu order = uang diterima yang belum dipotong. Angka di buku ini **termasuk PPN**: untuk order
Non PPN saldonya sama dengan bagian order itu di akun 213200 Uang Muka Penjualan; untuk order PPN bagian harganya ada
di 213200 dan bagian PPN-nya di 212100. Laporan Down Payment Ledger dipakai Accounting untuk mencocokkan saldo 213200
per order (tagihan uang muka cara lama, yang dulu dijurnal sebagai piutang, dikecualikan dari pencocokan itu).

---

## 8. Pajak

Semua butir di bawah perlu dikonfirmasi konsultan pajak perusahaan.

| Hal | Ketentuan di aplikasi |
|---|---|
| Tarif PPN | dibaca dari Settings → Constants, tidak diketik di invoice; tarif yang dipakai disimpan di invoice |
| Dasar pengenaan | disimpan tiga angka: harga jual, DPP nilai lain (11/12 × harga jual), PPN (12% × DPP nilai lain) |
| PPN uang muka | diakui saat uang muka diterima; Faktur Pajak "Uang Muka"; invoice akhir = Faktur Pajak "Pelunasan" atas sisa |
| Setelan cadangan | konstanta `DP_VAT_ON_RECEIPT`: 1 = seperti di atas (bawaan). 0 = cara lama: uang muka seluruhnya masuk 213200 tanpa PPN dan tanpa Faktur Pajak, PPN seluruhnya di invoice akhir. Diubah hanya atas keputusan tertulis |
| Nomor Faktur Pajak | diisi sesudah unggah Coretax, satu per dokumen (uang muka, invoice, credit note) |
| Tenggat | aplikasi mengingatkan Faktur Pajak yang belum bernomor menjelang tanggal 20 bulan berikutnya |
| Pembeli | NPWP / NIK diambil dari master customer; tidak diketik di invoice |
| PPh 23 | hanya bila customer memotong; dicatat saat penerimaan (5.4) |
| Bea Meterai | tempat meterai Rp 10.000 pada Tanda Terima Uang Muka dan kwitansi di atas Rp 5.000.000; invoice tidak bermeterai dan tidak bertulisan "LUNAS" |
| Mata uang | rupiah |

---

## 9. Piutang

- **Sisa tagihan invoice** = total invoice − uang muka yang dipotong − PPh 23 dipotong − yang sudah dibayar −
  credit note.
- **Status tagih**: Open, Partially paid, Due this week, Overdue, Paid. Invoice lunas = Closed.
- **Umur piutang** dihitung dari jatuh tempo, per customer; laporan **AR Aging** (Finance › Financial Reports) memuat
  invoice Confirmed yang belum lunas dalam kolom Not Due, 1-30, 31-60, 61-90, Over 90 hari, dengan saringan identitas
  PPN / Non PPN. Uang muka bukan piutang dan tidak ikut.
- **Statement of Account** per customer: saldo awal, invoice, penerimaan, uang muka, saldo akhir — untuk
  konfirmasi piutang.
- **Uang muka belum terpakai** per customer per order dilaporkan terpisah (laporan **Down Payment Ledger**, bagian 7B;
  bukan pengurang piutang di neraca).
- **Cadangan dan penghapusan piutang**: tahap 1 belum otomatis. Umur piutang menjadi dasar Accounting; penghapusan
  dilakukan sebagai transaksi tersendiri dengan persetujuan.

---

## 10. Pengendalian

| Pengendalian | Cara aplikasi menjaganya |
|---|---|
| Pemisahan tugas | sasaran: hak membuat tagihan uang muka, membuat invoice, mencatat penerimaan, dan membatalkan = hak terpisah. **Tahap 1: masih satu hak modul Finance & Accounting** |
| Tagih dobel | qty tertagih disimpan per baris Surat Jalan; total tertagih tidak bisa melebihi qty diterima |
| Harga | dari Customer Order lewat Surat Jalan; tidak diketik di invoice |
| Invoice tidak berubah | sesudah Confirm tidak ada Save dan tidak ada Delete; hanya Cancel (beralasan) atau Credit Note |
| Nomor | bagian 3 |
| Jejak | tab History di tiap dokumen: siapa, kapan, apa |
| Periode | dokumen tidak bisa bertanggal di periode yang sudah ditutup |
| Uang muka | tidak bisa dipotong melebihi yang diterima; sisa per order selalu terlihat |
| Cocok tiga dokumen | invoice selalu menunjuk Customer Order dan baris Surat Jalan asalnya |

---

## 11. Laporan

| Laporan | Status |
|---|---|
| **To Invoice** (Unbilled Delivery): order dengan Surat Jalan belum habis ditagih — siap ditagih / menunggu bukti terima | ada (Finance › Transaction Flows) |
| **AR Aging** | ada (Finance › Financial Reports) |
| **Down Payment Ledger** (Down Payment Outstanding + riwayat) | ada (Finance › Financial Reports) |
| Tab **Billing** di Customer Order | ada (hanya baca) |
| Statement of Account · Invoiced Not Delivered · Invoice Register per seri · Faktur Pajak belum bernomor | belum |

---

## 12. Saldo awal piutang

Sistem lama tidak pernah mencatat pembayaran customer, jadi saldo piutang tidak bisa dihitung dari data lama.
Saat pindah: aplikasi menampilkan invoice lama yang mungkin masih terbuka, Finance menandai lunas / belum dan
mengisi sisa serta uang muka yang belum terpakai, lalu saldo awal dijurnal sekali pada tanggal pindah.

---

## 13. Riwayat keputusan

| Tanggal | Keputusan user |
|---|---|
| 2026-10-02 | Customer Invoice dan AR satu modul di atas `tm2acc.fin_invoice`. |
| 2026-10-02 | **A.** Dua seri nomor invoice: satu untuk Non PPN (Telasih Makmur), satu untuk PPN (CV. Langgeng Jaya Makmur). |
| 2026-10-02 | **B.** PPN uang muka mengikuti aturan (Faktur Pajak saat uang muka diterima), dengan setelan cadangan ke cara lama. |
| 2026-10-02 | **C.** Tagihan uang muka tanpa jurnal sampai uangnya diterima — menggantikan butir LOCKED "jurnal piutang saat tagihan DP terbit" di `downpayment_tier1-4.md`. User meminta seluruh urusan keuangan dan akuntansi didokumentasikan jelas (dokumen ini). |
| 2026-10-02 | **D.** Lima rekening bank sistem lama boleh disalin ke master rekening perusahaan; bawaan PPN = Maspion CV. Langgeng Jaya Makmur, Non PPN = BCA Puspita Dewi Widodo. |
| 2026-10-02 | **E.** Qty Minim di Customer Order v2 dihitung sebagai harga borongan (untuk saat ini). |
| 2026-10-02 | **Tagihan mengikuti Surat Jalan dan validasi customer; uang muka terlacak per order** ("setiap tagihan harus related dengan surat jalan pengiriman dan validasi dari customer nilai yang terkirim, karena bisa ada retur dan rusak di jalan; uang muka jelas dari CO yang mana dan historinya terlacak") — bagian 7A dan 7B. |
| 2026-10-02 | **"Sarankan yang terbaik dan jalankan dulu"** (user belum cukup mewawancarai pemakai): dijalankan dengan pilihan bawaan — satu akun bank per rekening (111210–111250); kode seri `LJM`, `UM-TM`, `UM-LJM` dipakai; tanggal invoice bawaan = tanggal Surat Jalan terakhir; cetakan tetap seperti sekarang sampai dikoreksi. Semuanya bisa diubah sesudah wawancara pemakai. |
| 2026-10-02 | **Layar lama dihentikan** ("yang lama dihapus saja, gantikan dengan yang baru"): Order to Cash dan Down Payment lama diarsipkan; Customer Invoice satu-satunya pintu untuk invoice, uang muka, dan penerimaan uang dari customer. Akibat akuntansi: tidak ada lagi jalur yang menjurnal piutang saat tagihan uang muka terbit, dan tidak ada lagi invoice penjualan tanpa Surat Jalan. |

## 14. Yang perlu dikonfirmasi pihak luar

- Konsultan pajak: PPN uang muka (bagian 8), pembulatan PPN, perlakuan PPh 23 untuk pekerjaan cetak, kode
  transaksi Faktur Pajak.
- Akuntan / KAP: standar yang dipakai (SAK EP atau SAK EMKM), kebijakan cadangan piutang, perlu tidaknya jurnal
  akrual untuk barang terkirim yang belum ditagih di akhir periode.
- Finance: nama dan kode akun bank per rekening (bagian 4, sudah dibuat dengan usulan bawaan), cara menyelesaikan retur
  sesudah invoice (7A), dan saldo awal (bagian 12).
