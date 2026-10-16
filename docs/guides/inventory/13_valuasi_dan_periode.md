# Valuasi, Landed Cost, Stok Usang, dan Tutup Periode Inventory

*Panduan untuk Jo, Akuntansi, dan kepala gudang. Ditulis dari membaca kode, konfigurasi, dan data mockup, 16 Oktober 2026. Nilai uang dan akun sengaja dipisah di bagian khusus johiwan di akhir; hal yang tidak bisa saya pastikan ada di bagian "Belum terverifikasi". Kartu teknis: `docs/tg/inventory/stock/` (kartu 14 sampai 17, 19, 23, 25, 26).*

Bab ini menjawab: berapa nilai barang di gudang, bagaimana biaya yang datang terlambat masuk ke nilai itu, bagaimana stok mati disisihkan, bagaimana bulan ditutup, dan bagaimana kita tahu angka stok sama dengan buku besar. Semuanya ada di menu **Inventory > Stock > Valuation & Period**.

## 1. Cara nilai dihitung (tanpa angka)

Metode nilai perusahaan adalah **rata-rata bergerak (moving average)**. LIFO tidak dipakai (tidak diizinkan standar akuntansi), FIFO ditunda. Prinsipnya:

- **Barang masuk** dengan harga tertentu menggabungkan harganya dengan stok yang sudah ada, sehingga harga rata-rata baru berada di antara keduanya.
- **Barang keluar** dan **penyesuaian** dinilai dengan harga rata-rata saat itu dan **tidak mengubah** harga rata-rata.
- **Stock Transfer** memindahkan nilai bersama barangnya; total nilai tidak berubah.
- Barang milik **customer atau supplier** (titipan) tidak dinilai dan tidak dijurnal.
- Dokumen bertanggal mundur menghitung ulang semua baris sesudahnya.

Harga rata-rata dapat dihitung **per bin** atau **per barang** (semua bin). Pengaturan **INV_AVG_SCOPE** menentukannya; di data contoh nilainya masih **BIN** (per bin), padahal titik peralihan ke per-barang sudah disiapkan untuk 62 barang. Peralihan dilakukan IT dengan perintah `inv:revalue-per-item` setelah memeriksa total nilai tidak berubah. Pengaturan live belum saya periksa.

## 2. Layar Valuation & Period

Buka **Inventory > Stock > Valuation & Period > Valuation & Period**. Tiga kartu:

1. **Value by Class**: nilai stok sekarang per kelas (Raw Material, Semi Finished Goods, Finished Goods) dan hasil **Daily Reconciliation** terakhir (tanggal, selisih, status **Balanced** atau **Difference**). Di bawahnya tombol pintas: Landed Cost, Obsolete Stock, GR/IR Aging, Daily Reconciliation, Repost, Stock Aging.
2. **Inventory Period**: tabel 24 periode terakhir, pemilih periode, dan tombol **Add Next Period**, **Open**, **Close**.
3. **Close Landed Cost Variance**: kolom **Period (YYYYMM)** dan tombol **Close**.

## 3. Tutup dan buka periode inventory

Periode inventory adalah satu bulan. Ketika **Close**, dokumen stok **bertanggal di dalam atau sebelum bulan itu ditolak** saat diposting; koreksinya dibuat sebagai Stock Adjustment bertanggal di bulan yang masih terbuka.

1. Pastikan bulan sudah ada: bila belum, klik **Add Next Period**. Sistem membuat bulan sesudah yang terakhir (atau bulan ini bila belum ada satu pun). Periode **tidak** dibuat otomatis.
2. Pilih periode di pemilih, klik **Close**, jawab "Close this period? Stock documents dated inside it are refused."
3. **Open** (dialog "Open this period again?") membuka lagi.

Aturan dan pesan penolakan:

| Pesan | Artinya |
|---|---|
| "The period was not found." | Kode periode tidak ada |
| "The period is already closed." | Sudah tertutup |
| "Close the earlier periods first." | Ada bulan yang lebih awal belum ditutup; tutup berurutan |
| "Only a closed period can be opened again." | Hanya periode tertutup yang bisa dibuka |
| "Open the later closed periods first." | Hanya periode tertutup yang **paling akhir** yang bisa dibuka |
| "The general ledger period of that month is closed; it cannot be opened for inventory." | Bulan buku besar sudah ditutup Finance |

Urutan tutup bulan yang benar: (1) Landed Cost bulan itu dikonfirmasi, (2) **Close Landed Cost Variance** (bagian 4), (3) rekonsiliasi ditinjau (bagian 7), (4) **Close** periode inventory, (5) Finance menutup bulan buku besar. Finance tidak bisa menutup bulan bila periode inventory bulan itu belum **CLOSED**; bila baris bulannya belum ada, pesannya "DOES NOT EXIST". Di data contoh baru ada periode Agustus 2026 (OPEN), padahal dokumen sampai Oktober 2026 sudah diposting.

**Yang tidak diperiksa saat Close:** sistem tidak mengecek dokumen DRAFT atau SUBMITTED bertanggal di bulan itu, Pick List yang masih terbuka, atau selisih rekonsiliasi. Cek sendiri sebelum Close: dokumen DRAFT bertanggal di bulan tertutup nanti ditolak saat diposting dan harus diberi tanggal baru. Siapa pun dengan hak modul Inventory dapat Close dan Open; tidak ada hak terpisah yang saya temukan.

## 4. Landed Cost (biaya angkut, bea, asuransi yang datang belakangan)

Dipakai bila tagihan ongkos datang **sesudah** barangnya diterima dan diposting. Hanya untuk **bahan baku (RM)**.

1. **Landed Cost > New** (di form tertulis **+ New Record — Landed Cost**). Isi **Date**, **Cost Type** (Freight, Duty, Insurance, Other), **Amount**, **Share By** (By value atau By quantity), **Charged By** (supplier penagih), **Description**. **Save**: "Saved. Add the goods receipts this cost belongs to."
2. Di kartu **Goods Receipt**, pilih penerimaan PO yang sudah **POSTED**, klik **Add**: semua baris stok penerimaan itu ditambahkan ("Receipt lines added."). Tabel menampilkan rencana pembagian: **Receipt Value**, **Share of Cost**, **To Inventory**, **To Variance**. **Remove · kode** menghapus satu baris.
3. Klik **Confirm** ("Confirm the landed cost? The journal is posted."). Sistem membagi biaya ke baris menurut nilai atau jumlah. Untuk tiap baris, bagian biaya dibagi dua: bagian untuk **stok yang masih ada** masuk ke nilai persediaan (menaikkan harga rata-rata), bagian untuk **stok yang sudah keluar** dicatat di akun **Selisih Landed Cost**. Nomor dokumen berbentuk `LC26-00001`. "Confirmed. The journal is posted."
4. **Revise Amount** (bila tagihan forwarder dikoreksi): isi **New Amount** dan **Reason** minimal 10 karakter. Jurnal lama tidak diubah; sistem menumpuk jurnal koreksi berisi selisihnya (lihat Repost, bagian 8).
5. **Cancel**: alasan minimal 10 karakter; jurnal pembalik diposting.
6. Akhir bulan: di layar Valuation & Period, isi **Period (YYYYMM)** lalu **Close** pada kartu **Close Landed Cost Variance**: bagian biaya yang jatuh ke stok yang sudah keluar dipindah dari akun selisih ke HPP. Bila tidak ada selisih: "There is no landed cost variance to close for that period."

Penolakan yang akan Anda lihat: "Only a posted goods receipt can carry a landed cost."; "That receipt has no stock line left to add."; "Enter the cost amount and add at least one receipt line before Confirm."; "The inventory period up to dd-Mmm-yyyy is closed; use a later document date."; "The landed cost date cannot be earlier than the goods receipt date."; "Item #n has no Inventory account (item or category); the landed cost was not posted."; "The variance of this landed cost was already closed to cost of goods sold; it cannot be cancelled."; atau akun belum diisi: "The account for ... is not set or not found (Settings > Constants)."

**Hati-hati:** (a) **Revise** setelah variansnya ditutup tidak menutup ulang varians yang berubah; hindari merevisi dokumen bulan yang sudah ditutup variansnya. (b) Jurnal dibuat di database akuntansi terpisah; jika langkah penulisan kartu stok gagal sesudah jurnal terposting, jurnal tidak ikut dibatalkan (belum pernah saya lihat terjadi). (c) Data contoh belum punya satu pun dokumen Landed Cost.

## 5. Obsolete Stock (penyisihan stok usang) dan Stock Aging

**Stock Aging** (Valuation & Period > Report) menggolongkan setiap barang di setiap bin menurut hari tanpa pergerakan: **Active**, **Slow**, **Non-moving**, **Dead**. Barang yang tidak pernah bergerak otomatis Dead. Pergerakan = masuk, keluar, atau penyesuaian di bin mana pun; **Stock Transfer tidak dihitung**. Kolom **Provision %** dan **Provision** memperlihatkan usulan penyisihan; batas hari dan persennya dapat diubah di Settings > Constants. Laporan ini hanya dibaca.

**Obsolete Stock** (Valuation & Period > Obsolete Stock) merangkum per golongan dan menampilkan "Proposed provision", "Posted before", dan "To post". **Tidak ada yang diposting sebelum Anda klik Post provision** (dialog "Post the provision journal for the difference to the last posting?"). Yang dijurnal hanya **selisih** terhadap posting sebelumnya: naik = debit beban penyisihan, kredit penyisihan persediaan usang; turun = sebaliknya. Bila tidak ada selisih: "The provision is already up to date; there is nothing to post." Tiap posting menyimpan foto barisnya di **Provision History**.

Catatan: tanggal "To date" pada Stock Aging hanya menggeser hitungan hari; stok dan nilai selalu keadaan sekarang. Posting provisi memakai posting **terakhir** sebagai pembanding. Data contoh belum punya posting provisi.

## 6. GR/IR Aging

**GR/IR Aging** menunjukkan (a) **Received not invoiced**: barang sudah diterima (SPB PO yang POSTED) tetapi belum ditagih, dan (b) **Invoiced not received**: tagihan sudah ada tetapi barangnya belum diterima. Umurnya dikelompokkan 0-30, 31-60, 61-90, dan 90+ hari sampai tanggal **To date**. Tagihan menutup penerimaan **tertua** lebih dulu, per PO. Pencocokannya perkiraan: nilai tagihan dibandingkan apa adanya dengan nilai terima, tanpa memisahkan PPN dan diskon. Hanya baris Tanda Terima Tagihan yang **Confirmed dan menunjuk PO** yang dihitung. Laporan ini hanya dibaca.

## 7. Daily Reconciliation (buku besar vs Kartu Stok)

Setiap hari pukul 04:00 sistem membandingkan, per kelas, **saldo akun persediaan di buku besar** dengan **nilai Kartu Stok** hari itu. Selisih (di luar toleransi pengaturan) berstatus **DIFF**. Rumusnya: selisih = buku besar - (Kartu Stok + landed cost yang sudah dijurnal tetapi belum masuk kartu). Hasil disimpan per hari dan kelas, dan ditampilkan di **Daily Reconciliation** serta di kartu Value by Class.

Yang harus dipahami sebelum panik melihat DIFF: **tidak semua pergerakan stok membuat jurnal.** Jurnal hanya lahir dari penerimaan barang (SPB), Stock Out bahan baku dan barang setengah jadi, Landed Cost, penilaian ulang, dan provisi. **Stock In manual, Retur, Saldo Awal, Adjustment/Opname, serta semua pergerakan barang jadi dan barang setengah jadi selain Stock Out tidak dijurnal**, sehingga selisih adalah hal yang diharapkan setelah pekerjaan tersebut sampai Akuntansi menjurnalnya. Di data contoh, tabel hasil rekonsiliasi masih kosong; saya menjalankan perhitungan tanpa menyimpan, dan ketiga kelas berstatus DIFF (angkanya di bagian johiwan).

## 8. Repost (jurnal koreksi bertumpuk)

Jurnal yang sudah diposting **tidak pernah diubah atau ditimpa**. Bila nilai atau dokumen lampau berubah, sistem membuat jurnal **baru** berisi hanya selisih per akun. Layar **Repost** mencatat tiap koreksi (**CORRECTION**), pembalikan (**REVERSAL**), dan penilaian ulang (**REVALUATION**) dengan nilai sebelum, sesudah, dan selisih, serta alasan. Penilaian ulang terjadi bila dokumen bertanggal mundur mengubah harga rata-rata barang keluar yang sudah dijurnal: untuk **bahan baku**, jurnalnya dikoreksi otomatis; untuk barang jadi dan setengah jadi, perubahan hanya diperingatkan. Jurnal koreksi bertanggal hari koreksi; jika bulan buku besar hari itu sudah ditutup, pembuatan jurnal gagal dan hanya muncul peringatan (stok tetap terposting).

## 9. Belum terverifikasi dan catatan untuk Jo

- Saya tidak membuka layar di browser dan tidak menjalankan tes; semua dari kode dan data mockup. Satu-satunya perintah yang saya jalankan adalah `inv:reconcile --no-save` (tanpa menulis).
- Belum diperiksa: nilai `INV_AVG_SCOPE` dan data periode di tmerp.test, hak akses Close/Open (hanya terlihat satu hak modul), dan perilaku terhadap dokumen live.
- Aturan lama A.24 di buku aturan masih menulis bahwa jurnal koreksi harga keluar "belum diputuskan"; kode sudah mengerjakannya untuk bahan baku. Perlu Jo pastikan mana yang berlaku.
- Pada Retur, jurnal pemakaian tidak dibalik (bab 10, bagian 7); itu menimbulkan DIFF yang wajar.
- Pengaturan seperti `INV_ALLOW_NEGATIVE` dan `INV_BACKDATE_DAYS` ada di Settings tetapi belum dibaca program.

<!-- johiwan-only -->
## 10. Angka dan akun (khusus johiwan)

Sumber: pengaturan Settings > Constants di mockup dan catatan desain `docs/design-notes/inventory/08_invwh-tech-rules.md` (dari dokumen, belum diuji ulang). Semua nilai uang memakai kode IDR.

- **Contoh rata-rata (dari dokumen):** 1.000 kg dibeli @IDR 15.000, lalu 500 kg @IDR 16.500 menghasilkan harga rata-rata IDR 15.500 per kg. Rumus: (qty lama x harga lama + qty masuk x harga masuk) / (qty lama + qty masuk). Keluar dan penyesuaian tidak mengubah harga rata-rata.
- **Stok usang:** batas hari Slow 90, Non-moving 180, Dead 365; penyisihan 10 persen, 25 persen, 50 persen dari nilai stok golongan itu. Akun beban 620000, akun penyisihan 113900.
- **Landed cost:** akun selisih 518000, akun hutang biaya 211950, akun HPP 511000.
- **Rekonsiliasi:** toleransi IDR 1; akun persediaan RM 113100, 113200, 113600; SFG 113400; FG 113500. Hasil perhitungan tanpa simpan pada 16-Oct-2026: RM buku besar IDR 1.305.309.211,46 melawan Kartu Stok IDR 558.324.000,00 (selisih IDR 746.985.211,46); SFG IDR 214.000.000,00 melawan 0; FG IDR 392.000.000,00 melawan 0. Ketiganya DIFF; sebabnya dugaan saya adalah jurnal pembukaan contoh di buku besar yang tidak punya kartu stok, belum diverifikasi.
- **Persetujuan Adjustment lama:** di atas IDR 5.000.000 Manajer Operasional, di atas IDR 25.000.000 Direksi; kini dinonaktifkan (bab 11).
