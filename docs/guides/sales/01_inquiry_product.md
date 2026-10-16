# Inquiry Product dan Dieline Engine: panduan untuk Jo

Panduan ini dibuat dari membaca kode pada 2026-10-16, bukan dari dokumen lama. Bila dokumen lama berbeda dengan kode, yang ditulis di sini adalah kode, dan selisihnya dicatat di bagian "Catatan untuk Jo". Nama tabel, kolom, dan kelas ditulis dalam kurung kecil hanya sebagai petunjuk.

## 1. Untuk apa layar ini dan siapa yang memakainya

Inquiry Product adalah pintu masuk seluruh alur ERP: dari sinilah satu pekerjaan kemasan (karton box, dus, kemasan) lahir sebagai dokumen. Pemakainya adalah petugas Sales. Sales mencatat siapa customer-nya, produk apa yang diminta, ukuran dan bentuknya, bahan awal, finishing, dan berapa jumlah yang ingin dihitung harganya (tingkat jumlah atau "tier").

Layar ini hanya mencatat permintaan. Ia tidak menghitung bahan, proses mesin, atau harga. Semua itu dikerjakan hulu berikutnya:

- Pro-Dev (Product Development) menyusun layout cetak, daftar proses (BOP), daftar bahan (BOM), dan Master Card.
- Pro-Cost menghitung harga pokok dan harga jual per tier. Angka biaya hanya terlihat oleh Costing dan Direksi.
- Setelah Pro-Cost dipublikasikan, Sales bisa membuat Quotation.

Di dalam layar Inquiry ada satu alat bantu yang penting: **Dieline Engine**. Begitu Sales memilih jenis produk dan mengetik ukuran, engine ini langsung menggambar pola pisau (dieline) di layar dan menghitung ukuran lembar datar (bentangan) yang dibutuhkan. Gambar itu juga dipakai kembali di Pro-Dev, Master Product, dan Master Card Collection, jadi ukuran yang diketik Sales menjadi dasar semua tahap sesudahnya.

## 2. Tiga jenis dokumen: Baru, Revisi, Rekalkulasi

Ketiganya disimpan di satu tabel yang sama (`iqp_inquiry`), dibedakan oleh kolom jenis. Menu dan layarnya dipisah supaya petugas tidak perlu memilah sendiri.

- **Baru (New).** Dipakai untuk produk yang belum pernah ada di Master Product. Sales mengisi semuanya dari nol: header, jenis produk, ukuran, artwork, bahan, finishing, tier jumlah. Alurnya Inquiry, lalu Pro-Dev, lalu Pro-Cost.
- **Revisi (Revise).** Dipakai untuk pesan ulang produk lama yang spesifikasinya BERUBAH. Produk dipilih dari Master Product (produk yang lahir dari Quotation yang menang). Sales memilih bagian yang berubah (artwork, warna, dimensi, bahan, proses atau finishing, struktur sambungan) dan wajib menulis alasan. Hanya bagian yang dipilih yang bisa diubah, dan setiap perubahan dicatat sebagai "sebelum menjadi sesudah" supaya Pro-Dev dan Pro-Cost tahu apa yang harus diulang. Alurnya sama dengan Baru: Inquiry, Pro-Dev, Pro-Cost.
- **Rekalkulasi (Recalc).** Dipakai untuk pesan ulang produk lama yang spesifikasinya SAMA, hanya harga bahan yang naik atau turun. Yang bisa diisi hanya jumlah order dan batas waktu penawaran. Alurnya lebih pendek: Inquiry langsung ke Pro-Cost, tidak lewat Pro-Dev. Dokumen ini tetap dibuat dan dikonfirmasi Sales lebih dulu supaya tercatat Sales siapa yang memegangnya (dasar komisi).

Cara memilih: spesifikasi sama persis dan hanya harga yang perlu diperbarui, pakai Rekalkulasi. Ada satu saja yang berubah (ukuran, warna, bahan, artwork, finishing), pakai Revisi. Produk belum pernah ada, pakai Baru. Pesan ulang tanpa perubahan apa pun dan tanpa perlu hitung ulang harga tidak lewat Inquiry sama sekali, melainkan langsung Customer Order jalur direct (aturan A.2).

## 3. Alur langkah demi langkah dari sisi pengguna

**Menu.** Di grup Inquiry Product ada: All Inquiries, Inquiry New, Inquiry Revise, Inquiry Recalc, ditambah heading Reports. Sejak 2026-10-16, All Inquiries adalah laporan lihat-saja: tidak ada tombol New, Edit, atau aksi massal. Dokumen dibuat dan diubah hanya lewat tiga menu jenisnya.

**Alur untuk dokumen Baru:**

1. Buka Inquiry Product, lalu Inquiry New. Tombol New di sana langsung membuka form jenis Baru, tanpa pilihan jenis.
2. Isi header: tanggal, Customer Need Date (bawaan 14 hari ke depan), Sales PIC, sumber permintaan (EMAIL, WA, PHONE, VISIT, MARKETPLACE), customer, dan nama produk sementara. Customer dipilih dari daftar. Bila belum ada, bisa ditambah cepat dari form; customer baru itu berstatus Draft dan tetap harus ditinjau Finance atau Credit.
3. Pilih Kategori Produk, Detail Kategori, lalu Ukuran Pasaran. Pilihan ini hanya pemandu. Ia mengisi jenis produk dan ukuran awal dari master pasaran, dan Sales boleh mengubah ukuran sesudahnya. Jenis produk (jenis dieline) tidak disimpan sebagai kategori, hanya kodenya.
4. Di bagian Part dan Dieline, pilih peran part bila jenis produknya terdiri dari beberapa bagian (misalnya kotak dengan tutup: badan dan tutup). Jenis yang bukan kotak berbagian langsung aktif sebagai satu bagian. Ketik ukuran produk. Gambar dieline di sebelah kanan ikut berubah seketika, begitu juga ukuran bentangan.
5. Unggah artwork sisi luar, dan bila perlu sisi dalam (JPG atau PNG, batas ukuran dari setelan `INQUIRY_ARTWORK_MAX_MB`, bawaan 10 MB).
6. Pilih bahan awal (jenis dan gramatur saja, merek dan ukuran plano diputuskan Purchasing dan Pro-Dev), finishing, tooling, dan biaya luar bila ada.
7. Isi tier jumlah: minimal satu baris, jumlahnya harus lebih dari nol, dan harus ada tepat satu tier utama.
8. Tekan **Save**. Dokumen diberi nomor otomatis dan berstatus Draft. Selama Draft semuanya masih bisa diubah.
9. Tekan **Confirm**. Dokumen disimpan lagi, berstatus Submitted, dan form terkunci. Pada saat ini tanda `stat_confirm_sales` menjadi menyala.

**Tombol lain pada form:** Duplicate (membuat dokumen Baru berstatus Draft untuk customer lain dengan spesifikasi yang sama), Cancel (hanya setelah Confirm, wajib alasan), Mark Lost (hanya setelah Confirm dan sebelum selesai, wajib alasan, tercatat untuk laporan win-rate), Print Note, dan tautan ke Imposition. Artwork tetap boleh diganti setelah Confirm.

**Alur untuk Revisi dan Rekalkulasi:** buka menunya, pilih produk dari Master Product (atau mulai dari baris di grid), isi bagian yang diperbolehkan, Save, lalu Confirm. Confirm Revisi dan Rekalkulasi meminta minimal satu tier jumlah. Dokumen Draft yang belum dikonfirmasi boleh dihapus.

**Tiga tanda tahap.** Posisi dokumen di alur ditentukan oleh tiga tanda di satu tabel, masing-masing dengan siapa dan kapan:

- `stat_confirm_sales`: Sales sudah Confirm. Dipasang oleh layar Inquiry sendiri.
- `stat_confirm_prodev`: dihitung otomatis. Menyala ketika SEMUA part inquiry sudah Confirm di Pro-Dev (layout terbaru tiap part berstatus Confirmed). Pro-Dev boleh mengonfirmasi part demi part, tetapi tanda baru menyala setelah part terakhir. Tanda ini hanya berlaku untuk Baru dan Revisi. Bila layout dibuka kembali, tanda turun ke nol, kecuali costing sudah mulai dibuat.
- `stat_confirm_procost`: dihitung otomatis. Menyala saat costing inquiry itu dipublikasikan, dan kembali mati bila publikasi dibatalkan.

Setiap grid hanyalah saringan atas tabel yang sama: jenis dan tanda. Pro-Dev To Do adalah Sales sudah Confirm dan Pro-Dev belum. Pro-Cost To Do untuk Baru dan Revisi adalah Pro-Dev sudah dan Pro-Cost belum. Pro-Cost To Do untuk Rekalkulasi adalah Sales sudah dan Pro-Cost belum. Dokumen yang dibatalkan atau berstatus Lost tidak muncul di grid kerja. Tidak ada lagi tombol "Kirim ke Pro-Dev": inquiry yang sudah Confirm Sales muncul sendiri di Pro-Dev, dan part masternya baru dibuat ketika petugas Pro-Dev membukanya.

Kolom Stage pada laporan All Inquiries merangkum ini sebagai Draft, Pro-Dev, Pro-Cost, atau Ready to Quote.

## 4. Dieline Engine di balik layar

**Apa itu.** Dieline Engine (dalam kode bernama TMDieline) adalah penggambar pola pisau. Ia tidak menyimpan bentuk kotak di dalam programnya. Setiap jenis produk (`prod_type`) membawa "resep" tertulis (`dieline_spec`) yang berisi daftar garis dan bentuk beserta rumus posisinya. Engine hanya membaca resep itu, memasukkan ukuran yang diketik, lalu menggambar. Jenis produk baru berarti resep baru di data, tanpa mengubah program.

**Masukan.**

- Resep jenis produk: ukuran kanvas gambar, variabel turunan, ukuran bentangan, dan daftar elemen (garis potong, garis lipat, bleed, lubang).
- Ukuran yang diketik di form, semuanya dalam **milimeter**: panjang, lebar, tinggi produk, diameter, tinggi tutup, lidah lem, lidah tutup atas dan bawah, lidah debu, dan margin bleed (kode kodenya ada di bagian rumus). Setiap jenis produk hanya menampilkan ukuran yang relevan baginya, dengan batas minimum dan maksimum dari master (`prod_type_param`).
- Ukuran yang kosong atau salah ketik dianggap nol, tidak menimbulkan error.

**Keluaran.**

- Gambar SVG: garis potong merah, garis lipat hijau, bleed abu-abu putus-putus, lubang biru.
- Bentangan (flat size): lebar dan tinggi lembar datar dalam milimeter, dibulatkan dua desimal, ditambah luas dalam milimeter persegi. Di layar, luas ditampilkan dalam sentimeter persegi.
- Panjang garis potong, garis lipat, dan lubang, dijumlah dari gambar, ditampilkan di panel dieline versi terbaru.
- Jendela 3D kecil yang bisa diputar, hanya ilustrasi kotak tertutup, bukan simulasi lipatan.

**Mode Auto dan Manual.** Bentangan dihitung otomatis dari resep. Sales boleh beralih ke Manual dan mengetik lebar dan tinggi sendiri (misalnya dari file pisau pelanggan). Pada mode Manual, luas dihitung dari angka yang diketik, dan resep tetap dipakai untuk gambar.

**Satuan, jangan tertukar.** Semua yang berasal dari Inquiry (ukuran produk, bentangan) berada dalam **milimeter**. Kolom teknis Pro-Dev (ukuran pisau, area cetak, kanvas, plano) berada dalam **sentimeter**. Setiap perpindahan wajib dikonversi (aturan A.19). Pernah terjadi bentangan terbaca sebagai sentimeter sehingga blank menjadi "250 kali 161 cm" dan BOM tidak pernah terhitung; itu sudah diperbaiki.

**Tabel yang dibaca dan ditulis.**

- Dibaca: jenis produk beserta resepnya (`tm2mst.prod_type.dieline_spec`), parameter ukuran per jenis (`prod_type_param`), dan ukuran pasaran (`prod_market`, `prod_type_size`).
- Ditulis saat Save: salinan beku resep beserta ukuran yang dipakai ke bagian inquiry (`tm2sdi.iqp_part.dieline_spec`), serta bentangan lebar, tinggi, dan luas (`flat_w`, `flat_h`, `flat_area`). Salinan beku ini berarti perubahan resep di master kemudian tidak mengubah inquiry lama.
- Dibaca ulang oleh hilir: Pro-Dev menyalin ukuran produk ke master part (sentimeter) dan memakai bentangan sebagai ukuran awal imposition. Pro-Dev juga boleh mengubah dimensi, bentangan, dan artwork (aturan A.17) walau inquiry sudah Confirm, dengan peringatan bila layout sudah Confirmed, costing sudah published, atau produk sudah aktif.

**Dua salinan mesin yang sama.** Penggambar ada sebagai JavaScript (`resources/js/tmdieline.js`, untuk tampilan langsung) dan sebagai PHP (`App\Support\Dieline\TmDieline`, untuk membuat SVG di server saat impor Master Card massal). Keduanya wajib sama; `TmDielineTest` menjaga angkanya.

## 6. Hal yang sering membingungkan, batas, dan zona beku

- **Zona beku.** Inquiry Product, Pro-Dev, dan Pro-Cost adalah zona beku (aturan C.1): esensi dan alur data maupun dokumen tidak diubah tanpa izin Jo. Kemampuan menggambar dieline secara khusus sudah dinyatakan bagus dan tidak boleh diubah; pemindahan letak pemilihan jenis produk adalah perubahan tampilan, bukan perubahan mesin.
- **Gambar tidak sama dengan pola pisau pelanggan.** Gambar adalah pola resep, bukan file dieline asli pelanggan. File dieline dan Master Card asli Jo dipakai apa adanya dan tidak digambar ulang.
- **Mode Manual tidak mengikuti resep.** Bila Sales mengetik bentangan sendiri, ukuran produk di atasnya tidak lagi menentukan bentangan.
- **Jenis produk tanpa resep.** Jenis yang resepnya kosong tidak muncul di daftar pilihan Inquiry.
- **Jenis produk berbagian banyak.** Kotak dengan tutup, kotak bulat, dan kotak dangkal memaksa memilih peran part dulu (badan dan tutup). Sebelum itu ukuran dan gambar belum aktif.
- **Kategori, Detail, dan Ukuran Pasaran tidak disimpan.** Hanya pemandu isian. Pada dokumen tersimpan, sistem menebak ulang dari ukuran yang cocok persis; bila tidak ada yang cocok dibiarkan kosong.
- **Rekalkulasi tidak melewati Pro-Dev.** Tanda Pro-Dev tidak berlaku untuknya, dan tidak ada layout baru.
- **Setelah Confirm, form terkunci.** Perubahan spesifikasi sesudahnya tidak lewat Inquiry yang sama, tetapi lewat dokumen Revisi baru atau lewat tab Part dan Dieline di Pro-Dev.
- **Quotation wajib integrated.** Satu inquiry baru bisa masuk Quotation setelah costing-nya published. Tidak ada Quotation yang melompati Pro-Cost.
- **Data dummy.** Data uji berada di database dummy (`ddt*`); data nyata di `tm2*`. Master mesin dan pegawai nyata tidak disentuh.

## 7. Catatan untuk Jo: selisih dokumen lama dan kode, serta yang belum pasti

Selisih yang saya temukan:

1. `docs/claude_vital_flows.md` masih menulis bahwa jalur Inquiry ke Pro-Dev "belum ada". Di kode jalur itu ada dan jalan (`InquiryStage::openInProdev`, part master dibuat saat Pro-Dev membuka inquiry). Catatan itu basi.
2. Dokumen yang sama menyebut REVISI dan REKALKULASI sebagai layar Livewire v1 (`inquiry/revise.blade.php`, `recalc.blade.php`). Layar yang aktif sekarang adalah layar v2 (`inquiry-new`, `inquiry-revise`, `inquiry-recalc`). Layar v1 masih ada di repo dan form v2 ditulis sebagai salinan v1, sehingga perubahan harus dibuat di keduanya.
3. Registri engine (`EngineRegistry`, kunci `dieline`) hanya menyebut tabel yang dibaca dan tidak menyebut salinan PHP. Registri juga menyebut fungsi bentangan mengembalikan lebar dan tinggi; di kode ia juga mengembalikan luas.
4. Rulebook A.20 menulis menu "All, New, Revise, Recalc" dengan tombol New di tiap submenu. Per 2026-10-16 All Inquiries sudah menjadi laporan lihat-saja, dan item menu Inquiry New menunjuk ke grid `inquiry-list-new`. Aturan A.20 belum diperbarui.
5. `docs/SCREEN-INDEX.md` tidak punya baris untuk layar Inquiry New, Revise, Recalc, atau All Inquiries (hanya satu baris tentang Master Card Dummy yang menyebut Inquiry), padahal CLAUDE.md mewajibkannya.
6. Tombol Delete: form Inquiry New tidak memiliki tombol Delete untuk Draft pada toolbar yang saya baca (hanya Save, Confirm, Cancel, Mark Lost, Duplicate), sedangkan Revisi dan Rekalkulasi punya hapus Draft. Aturan UI di CLAUDE.md menyebut Delete sebagai tombol standar. Belum terverifikasi apakah ini disengaja.
7. Pada Revisi dengan lingkup Dimension, kode hanya memperbarui panjang, lebar, dan tinggi di resep tersimpan. Bentangan (`flat_w`, `flat_h`, `flat_area`) tidak dihitung ulang di titik itu. Belum terverifikasi apakah layar Revisi atau Pro-Dev menghitung ulangnya di tempat lain. Mohon dicek karena bentangan lama bisa terbawa.
8. Warna garis dieline (merah, hijau, abu-abu, biru) tertulis sebagai nilai heksadesimal tetap di `tmdieline.js` dan salinan PHP-nya, bukan token tema. Ini bertentangan dengan aturan "warna lewat token" di CLAUDE.md, tetapi mesin itu dalam zona beku, jadi tidak saya sentuh.
9. Panjang garis potong dan lipat yang tampil di panel diambil dari gambar. Komentar kode menyebutnya dasar meter lari pisau, tetapi saya tidak memastikan apakah Pro-Cost atau Imposition memakai angka layar ini atau menghitung sendiri (`dieRuleMeterLari`).
10. Test: `TmDielineTest` menguji versi PHP terhadap angka kunci tiga Master Card nyata dan sebuah SVG yang dicocokkan dengan keluaran node. Tidak ada test otomatis yang menjalankan JavaScript-nya langsung.
11. Kolom Stage dan "Ready to Quote" pada laporan dihitung oleh `QuotationBuilder::availableInquiries`. Aturan persisnya tidak saya baca, jadi uraian di bagian 3 tentang Ready to Quote belum terverifikasi.

Urutan bagian: bagian rumus sengaja diletakkan paling akhir karena diawali penanda khusus pemilik. Karena itu urutannya 1, 2, 3, 4, lalu 6 dan 7, lalu rumus (dinomori 5).

<!-- johiwan-only -->
## 5. Rumus Dieline Engine

Semua ukuran dalam milimeter. Nama pendek yang dibaca resep: A = panjang produk, B = lebar produk, C = tinggi produk, D = diameter, LH = tinggi tutup, G = lidah lem, TT = lidah tutup atas, DF = lidah debu, BT = lidah tutup bawah, P = margin bleed (overflow). Di kode, A sampai P hanyalah alias dari nama kolom (`product_length`, `product_width`, `product_height`, `diameter`, `lid_height`, `glue_flap`, `top_tuck_flap`, `dust_flap`, `bottom_tuck_flap`, `overflow_margin`).

**Pembaca rumus.** Rumus dalam resep boleh memakai tambah, kurang, kali, bagi, kurung, minus di depan, konstanta PI, dan fungsi max, min, abs, sqrt, round, floor, ceil. Aturan pengamannya: variabel yang tidak dikenal bernilai 0, pembagian dengan 0 menghasilkan 0, hasil yang bukan angka menjadi 0. Fungsi di luar daftar membuat elemen itu gagal dan dilewati. Nilai ukuran yang tidak bisa dibaca menjadi 0.

**Urutan perhitungan.** (1) ukuran form masuk sebagai variabel; (2) alias A sampai P diisi; (3) variabel turunan resep (`vars`) dihitung berurutan; (4) bentangan dan kanvas dihitung dari rumus resep.

**Bentangan** (dibulatkan 2 desimal), contoh resep Regular Slotted Carton 2-Piece (`RSC_2PC`, FEFCO 0201, satu part adalah setengah kotak):

- Lebar bentangan = G + A + B + 2 x P
- Tinggi bentangan = C + B + 2 x flap_extra + 2 x P
- Luas bentangan (mm persegi) = lebar x tinggi, dibulatkan 2 desimal
- Luas dalam cm persegi (yang tampil di layar) = luas mm persegi / 100

Setiap jenis produk punya rumus bentangannya sendiri di resepnya; yang di atas hanya untuk `RSC_2PC`, resep yang saya baca langsung. Resep jenis lain belum saya telaah satu per satu.

**Kanvas gambar.** Lebar kanvas = G + A + B. Tinggi kanvas = C + B + 2 x flap_extra. Jarak tepi gambar M = lebih besar dari antara 24 dan (lebar kanvas + tinggi kanvas) / 16.

**Variabel turunan yang dipakai gambar RSC_2PC.** Tinggi flap atas dan bawah yT = B / 2 + flap_extra. Batas bawah panel yB = yT + C. Dimensi miring lidah lem ga = min(10, G / 3).

**Contoh 1: karton aki KS Power N120** (pisau nyata 72,9 x 46,4 cm). Masukan: A = 507, B = 187, C = 267, G = 35, flap_extra = 5, P = 0.

- Lebar bentangan = 35 + 507 + 187 = 729 mm
- Tinggi bentangan = 267 + 187 + 10 = 464 mm
- Luas = 729 x 464 = 338.256 mm persegi = 3.382,56 cm persegi
- Kanvas 729 x 464; M = max(24, (729 + 464) / 16 = 74,5625), digambar sebagai 74,56
- yT = 187 / 2 + 5 = 98,5; yB = 98,5 + 267 = 365,5; ga = min(10, 35 / 3 = 11,67) = 10

Angka 729 x 464 sama dengan ukuran pisau pada Master Card nyata, dan dikunci oleh `TmDielineTest`.

**Contoh 2: Osima N100** (pisau nyata 62,3 x 45,2 cm). Masukan: A = 413, B = 180, C = 260, G = 30, flap_extra = 6, P = 0.

- Lebar = 30 + 413 + 180 = 623 mm
- Tinggi = 260 + 180 + 12 = 452 mm
- Luas = 623 x 452 = 281.596 mm persegi = 2.815,96 cm persegi

**Contoh 3: dampak bleed.** Dengan data contoh 1 tetapi P = 3: lebar = 729 + 6 = 735; tinggi = 464 + 6 = 470; luas = 735 x 470 = 345.450 mm persegi.

**Bentuk khusus.** Lidah tutup (tuck) berujung melengkung dengan jari-jari min(8, lebar lidah / 6); lidah debu (dust) berujung miring sebesar min(tinggi, lebar / 3); lidah lem menyempit sebesar ga di kedua ujung. Bila tinggi lidah nol atau lebarnya tidak positif, elemen itu tidak digambar. Bleed digambar sebagai kotak yang lebih besar P di setiap sisi, hanya bila P lebih dari 0. Garis lipat dan lubang yang berulang dibagi rata pada lebar yang ditentukan.

**Konversi ke hilir.**

- Ukuran produk Inquiry ke master part Pro-Dev (cm) = milimeter / 10, dibulatkan 2 desimal.
- Luas bentangan sebagai dasar BOM Pro-Dev (cm persegi) = flat_w x flat_h / 100 (hanya bila ukuran pisau Pro-Dev belum ada).
- Ukuran bentangan sebagai ukuran blank di kanvas Pro-Dev (cm) = milimeter / 10.

**Panjang garis** (potong, lipat, lubang) = jumlah panjang tiap garis, jalur, atau lingkaran dengan warna tersebut pada gambar, dalam mm atau dibagi 10 bila tampil dalam cm. Rumusnya ditulis di antarmuka gambar, bukan di resep.
