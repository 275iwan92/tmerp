# Panduan ProDev (Product Development): dari Inquiry menjadi Master Card

> Untuk dibaca dan direview Jo. Sumber kebenaran adalah kode (dicek 2026-10-16), bukan dokumen lama.
> Semua rumus ada di Lampiran paling akhir; "Hal yang membingungkan" dan "Catatan untuk Jo" karena itu ditaruh sebelum Lampiran.

## 1. Untuk apa ProDev, dan hubungannya dengan Inquiry dan ProCost

Inquiry hanya menyimpan spesifikasi kasar yang sudah dikunci Sales: ukuran dan bentuk dieline, bahan kertas,
finishing, notasi warna (misalnya 4/0), isi per pack, dan jumlah order. ProDev adalah tempat spesifikasi kasar itu
diterjemahkan menjadi cara membuatnya secara fisik: berapa buah muat di satu lembar cetak, kertas plano apa
yang dipakai, proses apa saja urutannya, bahan dan tinta apa yang terpakai, dan berapa banyak.

Yang keluar dari ProDev:
- **Layout** (susunan di lembar cetak dan plano, jumlah up, efisiensi). Disimpan sebagai layout group (`prod_layout_group`)
  dan sebagai salinan beku milik Inquiry (`iqp_layout`).
- **BOP** (Bill of Process, urutan proses), **BOM** (Bill of Material, bahan dan jumlahnya), dan **konfigurasi tinta**.
  Disimpan di master part (`prod_part_process`, `prod_part_material`, `prod_part_ink`) dan salinan Inquiry (`iqp_part_*`).
- **Master Card**. Kodenya lahir di sini: begitu layout pertama disimpan, sistem memberi kode `MC-<nomor layout>`.
  Kartunya sendiri (layout, BOM, tinta, BOP dalam satu lembar A4 landscape) dicetak atau dikirim email dari panel atas ProDev.
  Selama layout belum Confirm, kartu dicap "NOT CONFIRMED".

Posisi dalam alur: Inquiry (Sales Confirm) -> **ProDev** -> ProCost -> Quotation. Ada tiga tanda di satu dokumen
inquiry: Sales, ProDev, ProCost. Inquiry jenis New dan Revise wajib lewat ProDev; jenis Recalc (spesifikasi sama,
hanya harga bahan berubah) langsung ke ProCost. ProCost Mode terintegrasi membaca layout ProDev yang sudah Confirmed.
PPIC hanya mewarisi hasil ProDev; tidak ada logika ProDev yang boleh ditambah di layar PPIC.

## 2. Alur langkah demi langkah di layar ProDev

**Masuk.** Dari menu Pro-Dev > New atau Revise, satu baris grid = satu inquiry yang sudah Confirm Sales. Saat dibuka,
sistem membuat master part untuk part yang belum punya (tombol "Kirim ke ProDev" sudah tidak ada) lalu membuka part
pertama yang belum Confirmed. Bagian rakitan (assembly) dilewati karena tidak punya dieline sendiri.

**Dua mode, dua pintu.**
- *Mode terintegrasi*: part berasal dari Inquiry. Simpan menulis ke master dan ke salinan Inquiry.
- *Mode manual* (Pro-Dev > Manual): part tanpa Inquiry, meniru kerja lama di Excel dan Illustrator. Simpan hanya ke master.
  Upload artwork, Subkon, dan hubungan ke ProCost lewat Inquiry tidak tersedia di mode ini.

**Layar.** Dua tab. Tab *Imposition* adalah workbench dengan lima bagian akordeon (satu terbuka lebar):
Imposition & Nesting, Plano Configuration, Bill of Material, Ink Configuration, Bill of Process.
Panel atas memuat badge Draft/Confirmed, Email, Print (Master Card atau ACC Digital), Confirm, Save, dan Back.
Tab *Part & Dieline* ada di sebelahnya.

Urutan kerja yang lazim:
1. **Part & Dieline (bila perlu).** ProDev berhak mengubah dimensi, bentangan (flat size), dan artwork luar/dalam.
   Sistem tidak memblokir tetapi memberi peringatan bila layout sudah Confirmed, costing sudah published, atau produk
   sudah aktif di Master Product. Tersimpan dua arah (Inquiry dan master). Nama part, tipe part, qty per set, isi per pack,
   dan tipe produk tetap milik Inquiry.
2. **Imposition & Nesting.** Pilih Auto Up atau Manual Up, isi jarak antar-gambar (gap), pilih *Canvas limit*,
   lalu tekan Suggestion (menyarankan ukuran lembar cetak dan plano) atau Auto Nesting (menyusun ulang di lembar yang ada).
   Officer boleh menggeser, memutar 90/180 derajat, dan mencerminkan tiap gambar. Layar memberi peringatan bila garis
   potong bertabrakan, ada gambar keluar lembar, atau target up tidak muat.
3. **Plano Configuration.** Pilih mesin cetak dan mesin plong, pilih ukuran plano pasar (atau ketik ukuran sendiri),
   lihat ranking plano, dan pakai kalkulator PCS ke PLANO (berapa lembar plano untuk qty order) atau sebaliknya.
4. **BOM, Ink, BOP.** Engine mengisi otomatis dan menghitung ulang setiap perubahan layout. Officer boleh mengubah baris
   (menjadi OVERRIDE), menambah baris, atau menekan reset untuk kembali ke hasil engine. Dari BOM bisa dibuat Purchase
   Requisition draft untuk bahan yang kurang; dari BOP bisa dibuat PR subkon dan surat jalan subkon (wajib menunjuk CO asal).
5. **Save lalu Confirm.** Save menyimpan Draft. Confirm mengunci layout dan, pada mode terintegrasi, membuka layar ProCost
   untuk inquiry itu. Confirm dilakukan **part demi part**; tanda ProDev pada inquiry baru menyala setelah SEMUA part Confirmed.

Satu hal penting: Save pada layout yang sudah Confirmed tidak menimpanya, tetapi membuat layout Draft baru. Karena status
part dibaca dari layout terbaru, part itu kembali berstatus Draft, dan tanda ProDev inquiry ikut turun selama ProCost belum
membuat costing (kesimpulan saya dari membaca kode, belum saya uji di layar).

## 3. Imposition Engine: cara memilih susunan

Di layar ProDev, perhitungan susunan dikerjakan **di browser** (kode JavaScript workbench), bukan oleh kelas PHP
`ImpositionEngine`. Kelas PHP itu masih ada dan terdaftar, tetapi dipakai oleh layar arsip dan halaman lain (lihat bagian 7).

Cara kerja di layar:
- **Bentuk nyata, bukan kotak.** Dieline digambar lalu diubah menjadi "cetakan piksel". Dua gambar dianggap bertabrakan
  bila jaraknya kurang dari gap. Jadi lidah tutup bisa saling masuk.
- **Pola yang dicoba:** grid standar; sisipan antar-baris (gambar berselang-seling diputar 180 derajat) dengan geseran
  bertahap; sisipan antar-kolom; semuanya juga dalam posisi diputar 90 derajat. Pola yang masih bertabrakan dibuang.
  Pemenang adalah yang up-nya paling banyak.
- **Area cetak sebenarnya** = lembar cetak dikurangi gripper (jepitan mesin, satu sisi) dan margin keliling.
  Bawaan sistem: gap 3 mm, gripper 12 mm, margin 8 mm (diatur di Configuration: `PRODEV_GAP_MM`, `PRODEV_GRIPPER_MM`,
  `PRODEV_MARGIN_MM`).
- **Istilah.** *Canvas* = satu lembar cetak yang masuk mesin. *Plano* = kertas mentah di pasaran; satu plano dipotong
  menjadi beberapa canvas (potongan guillotine yang sama besar). *Up* = jumlah gambar per canvas.
- **Canvas limit** menentukan batas ukuran lembar: *Custom* (bawaan part baru, ikut dieline, tanpa batas meja, bawaan 1 up),
  *Press bed* (meja mesin cetak), *Die-cut bed* (meja mesin plong), atau *Press + Die-cut* (yang terkecil dari keduanya).
  Layout lama yang tersimpan tanpa catatan dibuka sebagai Press + Die-cut. Bila ukuran meja mesin belum diisi di Master
  Mesin, batas itu tidak dikarang: hanya diperingatkan. Mesin cetak tanpa data meja dianggap 102 x 72 cm dan ditandai "estimasi".
- **Suggestion** menyapu semua plano aktif dan semua cara membagi plano menjadi kolom x baris (sampai 14 x 14), mencari
  yang **luas kertas per buah** paling kecil. Setelah plano dan ukuran canvas terpilih, susunan terbaik dibangun dan diuji
  tabrakan. Bila officer sudah menyusun manual, Suggestion tidak membuang susunan itu, hanya merapatkan dan memaskan canvas.

## 4. Spec Engine: BOP, BOM, dan tinta diturunkan otomatis

Spec Engine (`SpecEngine`) tidak menulis apa pun saat menghitung; baru menulis saat Save. Masukannya: data Inquiry
(finishing, bahan, notasi warna, isi per pack, qty), keputusan layout (ukuran canvas dan plano, jumlah up, mesin cetak,
mesin plong), dan **aturan admin** di Setup > BOP Rules (`prod_bop_rule`). Urutan proses tidak ditulis di kode;
semuanya data.

**BOP.** Aturan berjenis *STANDARD* (selalu, atau bersyarat: plano perlu dipotong, bahan berlapis, perlu lem) dan
*FINISHING* (muncul bila part memakai finishing itu). Aturan awal dari migrasi: Potong Plano, Cetak, QC Cetak, lalu
sisipan finishing (Varnish, Laminating, UV, Hot Print, Foil), Laminasi Bahan, Plong, Emboss/Deboss, Kupas, Lem, Hitung,
Wrapping. Langkah bersebelahan dengan kelompok gabung yang sama dijadikan satu baris (misalnya "Plong + Emboss").
Hanya langkah bertanda mesin cetak dan mesin plong yang otomatis mendapat mesin dari pilihan officer; sisanya tanpa mesin
(proses tanpa mesin sah, itu SUBCON). Finishing yang belum punya aturan diberi peringatan, bukan diabaikan diam-diam.
Tiap langkah punya **waste** dan **toleransi** (persen), dan jumlah masuk dihitung **mundur** dari qty order, sehingga
lembar pertama yang harus disiapkan sudah memuat waste semua langkah sesudahnya. Perkiraan jam = jumlah masuk dibagi
target per jam.

**BOM.** Selalu ada baris KERTAS PLANO dan baris SHEET/SF (boleh "None"). Bahan lain berasal dari aturan: pola nama
di master bahan, lalu dipilih yang stoknya terbanyak; plastik laminasi dipilih roll terkecil yang lebarnya cukup untuk lembar
cetak; foil dicocokkan dengan atribut finishing ("Gold" dan seterusnya). Tinta dibuat satu baris per warna. Setiap baris
dinilai terhadap stok: CUKUP, KURANG, TIDAK ADA, CEK SATUAN, atau BELUM ADA ITEM. Jumlah kebutuhan inilah **Required** yang
dibaca PPIC saat Work Order dibuat; di PPIC, Release memakai stok yang tersedia (tidak melebihi Required) dan Purchase
adalah sisanya.

**Tinta.** Notasi "a/b" dibaca sebagai a warna sisi depan dan b warna sisi belakang. Empat warna atau lebih = CMYK
ditambah warna khusus; kurang dari empat = satu hitam ditambah warna khusus. Persentase tutupan diambil dari analisa artwork
(sisi depan saja) dan luasnya dihitung terhadap luas blank. Officer mengisi sendiri empat slot warna khusus (Unit 5-8).

**Sumber tiap baris** ditandai ENGINE, OVERRIDE (diubah officer), atau MANUAL (data lama atau ketikan). Saat Save, hanya
bagian ber-ENGINE yang ditulis ulang; bagian yang dipegang officer tidak pernah ditimpa dan dilaporkan "Not overwritten".
Work Order (PPIC) memanggil `forWorkOrder`: BOP dan BOM dihitung ulang untuk qty WO memakai layout yang sudah disimpan
ProDev, dan tiap baris BOM membawa proses BOP yang memakainya. Bila layout belum ada, sistem memakai 1 up per lembar
dan menulis peringatan.

## 5. Usage Rate dan Routing Template

**Dari mana angka pemakaian dan waste.** Semua takaran (gram per meter persegi, meter per lembar, dan seterusnya) dan
persen waste/toleransi tersimpan di aturan BOP. Nilai awalnya dari migrasi dan ditandai "estimasi umum industri, bukan data
pabrik ini". Setiap tanggal 1 pukul 02:00 (dan bisa dijalankan manual lewat `prodev:usage-rates`), **Usage Rate Engine**
belajar dari kenyataan: membandingkan bahan yang direncanakan di WO dengan bahan yang benar-benar keluar dari gudang
(dikurangi yang kembali), dan menghitung waste nyata dari output produksi. Hasilnya hanya **usulan** di kolom khusus aturan.
Aturan baru berubah sendiri bila admin menandainya auto apply **dan** sampelnya cukup (bawaan minimal 5 WO selesai,
`PRODEV_RATE_MIN_SAMPLE`). Nilai ketikan officer tidak pernah ditimpa.

**Dari mana kecepatan (target per jam).** Prioritas di BOP: (1) angka yang diketik officer; (2) Target Rate proses x mesin
milik part itu; (3) kecepatan maksimum mesin dari Master Mesin. Sumbernya ditampilkan di kolom BOP.

**Routing Template** (`RoutingTemplateService`) adalah pemeriksa urutan proses dan penerap template. Ia membandingkan urutan
dengan tabel aturan pendahulu proses: *BLOCK* untuk yang mustahil (kupas sebelum plong, atau proses tanpa mesin sama sekali
yang tidak bisa dijadwalkan) dan *WARN* untuk yang tidak lazim tetapi ada kasusnya (tidak pernah menghentikan officer).
Saat template diterapkan, tiap langkah diberi mesin usulan dari matriks kemampuan proses x mesin yang cocok dengan ukuran
lembar dan gramatur; bila mesin pilihan template tidak memenuhi syarat, alasannya ditulis. Template yang cocok dicari menurut tipe
produk atau kategori; yang bertanda default didahulukan, dan tidak pernah dipilih diam-diam bila tidak ada default.
**Penting:** dari kode yang saya baca, urutan BOP di ProDev berasal dari aturan BOP, bukan dari Routing Template
(lihat bagian 7).

## 6. Hal yang sering membingungkan, batas, dan zona beku

- **ProDev, Inquiry, ProCost adalah zona beku.** Alur data dan dokumen tidak diubah tanpa izin Jo. Teks UI Indonesia ke English
  sudah diizinkan. Perbaikan satuan bentangan (milimeter di Inquiry, sentimeter di kolom teknis ProDev) sudah pernah dilakukan.
- **Dua "engine" imposition.** Yang bekerja di layar adalah kode browser; kelas PHP `ImpositionEngine` hanya
  menyisakan alat bantu. Jangan mengira keduanya selalu memberi angka yang sama.
- **Satuan.** Lembar cetak dan plano di layar dalam sentimeter, hitungan kelas PHP dalam milimeter; gap di layar dalam cm,
  di Configuration dalam mm.
- **Canvas bukan plano.** Satu plano bisa menjadi beberapa canvas. Qty "lembar cetak" di BOP adalah canvas; qty BOM kertas
  dalam plano mengikuti jumlah canvas per plano.
- **BOP berpindah satuan.** Langkah sampai Plong dihitung dalam lembar (LBR), sesudah Plong dalam buah (PCS).
- **BOM dihitung dari lembar yang masuk langkah pertama** (sudah termasuk waste semua langkah), bukan per langkah. Bahan di
  langkah belakang (misalnya plastik laminasi) sedikit berlebih dibanding lembar yang benar-benar melewatinya.
- **Meja mesin belum terisi.** Menurut catatan di kode, ukuran meja mesin cetak dan mesin plong banyak yang kosong di Master
  Mesin, sehingga batas yang tampak bisa berupa asumsi (ditandai "estimasi"). Status data sekarang belum saya cek.
- **Override tidak ikut berubah** saat layout berubah. Gunakan reset bagian itu bila ingin kembali mengikuti engine.
- **Proses tanpa mesin = SUBCON** dan bukan celah. Di ProDev, hanya langkah cetak dan plong yang otomatis dapat mesin.

## 7. Catatan untuk Jo: selisih dokumen dengan kode, dan yang belum pasti

Selisih yang saya temukan (kode menang):
1. Registri engine menyebut Imposition Engine "dipakai workbench ProDev (tab Imposition)". Kenyataannya workbench v2
   memakai perhitungan JavaScript sendiri. `ImpositionEngine` PHP dipakai oleh layar arsip `old_prodev` (tidak ditaut dari menu mana pun),
   halaman Inquiry imposition (v1), WO entry (v1), dan `applyFlap` oleh perhitungan luas bentangan (layout group). Dokumen tier 1
   dan alur vital juga menulis seolah PHP itulah mesin ProDev. Remark di `v2/screens/prodev.blade.php` dan deskripsi konstanta
   `PRODEV_GAP_MM` (migrasi) juga masih menyebut `ImpositionEngine`.
2. Alat PHP sapuan lidah (flap), sapuan interlock, pencari plano terbaik, penggabung dua part, dan penghitung meter pisau
   hanya dipakai layar arsip. Konstanta `PRODEV_FLAP_MIN_MM` juga hanya dipakai di sana; konstanta interlock yang disebut
   dokumen tidak saya temukan dipakai di kode.
3. Registri Usage Rate: "apply = false hanya menampilkan". Di kode, usulan tetap ditulis ke kolom usulan di aturan BOP setiap
   kali dijalankan; yang tidak ditulis hanyalah angka takaran dan waste. Selain itu usulan takaran dikelompokkan menurut nama
   peran bahan, jadi dua aturan berperan sama (misalnya Doff dan Glossy) menerima usulan yang sama; hitungan waste tidak
   dibatasi pada WO berstatus selesai (hitungan bahan dibatasi).
4. Registri Routing: "dipakai ProDev (BOP)". Saya tidak menemukan pemanggilan Routing Template dari kode ProDev; hanya halaman
   Routing Template dan registri. Urutan BOP berasal dari urutan (`step_seq`) aturan BOP.
5. Margin: kelas PHP mengurangi gripper dan dua kali margin pada sisi pendek; kode layar mengurangi gripper dan satu kali margin.
   Selisih ini bisa membuat hasil beda satu baris.
6. `docs/SCREEN-INDEX.md` tidak punya baris khusus ProDev (hanya menyebut ProDev di baris Planning dan Master Card Dummy).

Butir terbuka engine (`docs/ENGINE-ATTENTION.md`) yang menyentuh ProDev:
- 02: Spec Engine membaca stok dari view yang hanya mengurangi booking Confirmed; PPIC mengurangi booking semua tahap. Angka Available bisa beda antar layar.
- 08: HOT PRINT/FOIL STAMPING bertanda bukan in-house padahal ada mesin mampu (data master REAL, perlu keputusan Jo); arti SPILL belum terverifikasi.

Belum bisa saya pastikan:
- Nilai aktual aturan BOP (waste, toleransi, takaran), ukuran plano, dan ukuran meja mesin di database: angka di artikel ini
  adalah nilai awal dari migrasi atau contoh; saya tidak membaca database.
- Apakah tombol Confirm membawa ke layar ProCost v2: kode memakai rute `inquiry.costing` milik v1.

<!-- johiwan-only -->
## Lampiran: rumus dan contoh angka (khusus Jo)

Angka masukan di contoh adalah **contoh buatan**, bukan data nyata. Konstanta (gap 3 mm, gripper 12 mm, margin 8 mm, takaran
tinta 1,5 g/m2, lem 10 g/m2, plastik 1 m per lembar, waste/toleransi per langkah) adalah nilai awal dari migrasi dan
kode. Contoh: canvas 100 x 70 cm, plano 109 x 79 cm, blank 20 x 15 cm (300 cm2), qty order 10.000 pcs, finishing Laminating Doff,
tinta hitam 30 persen, tanpa lem, plano perlu dipotong.

### A. Susunan (layar, JavaScript)
- Area cetak (mm): panjang = L - 2 x margin; lebar = W - gripper - margin. Contoh: 1000 - 16 = 984; 700 - 12 - 8 = 680.
- Jumlah kolom atau baris dalam grid = lantai dari (ruang tersedia + gap) / (ukuran + gap).
  Tegak: 4 kolom x 4 baris = 16. Diputar: 6 x 3 = 18. Pemenang: 18 up (diputar). Pola interlock hanya menang bila lebih dari ini.
- Canvas per plano (JS `cut`): n = lantai(P1 / L) x lantai(P2 / W), diputar: lantai(P1 / W) x lantai(P2 / L), ambil yang besar.
  Contoh: 1 x 1 = 1 canvas. Hasil plano = n x L x W / (P1 x P2) = 7000 / 8611 = 81,29 persen.
- Pemakaian canvas = up x luas blank / luas canvas = 18 x 300 / 7000 = 77,14 persen.
- Efisiensi menyeluruh (yang disimpan) = pemakaian canvas x hasil plano = 62,71 persen. Waste = 100 - itu = 37,29 persen.
- Bahan per buah (patokan Suggestion) = luas plano / (canvas per plano x up) = 8611 / 18 = 478,4 cm2.
- Kalkulator PCS ke PLANO: lembar cetak = pembulatan ke atas (qty / up) = 556; plano = pembulatan ke atas (lembar cetak / canvas per plano) = 556.
  Kalkulator PLANO ke PCS: qty = jumlah plano x canvas per plano x up.
- Canvas Custom untuk n up: coba kolom c = 1..n, baris r = ceil(n / c), kedua orientasi: sx = c x a + (c - 1) x gap, sy = r x b + (r - 1) x gap;
  L = naik ke 0,1 dari (maks(sx, sy) + 2 x margin); W = naik ke 0,1 dari (min(sx, sy) + gripper + margin); pilih luas L x W terkecil.
  Contoh 1 up (cm): L = 20 + 1,6 = 21,6; W = 15 + 1,2 + 0,8 = 17,0.

### B. Susunan (kelas PHP `ImpositionEngine`, bukan yang dipakai layar)
- Area: lebar = plano W - 2 x margin; tinggi = plano H - gripper - 2 x margin. Grid: lantai((ruang + gap) / (ukuran + gap)).
- Campur: blok utama tegak, sisa lebar = area W - (kolom x (W + gap) - gap) - gap diisi blok diputar; dipakai bila total up lebih banyak.
  Contoh pada area 984 x 680: tegak 16, diputar 18, campur 16 + 3 = 19 (sisa lebar 172 mm, 1 kolom x 3 baris). Efisiensi = 19 x 30.000 / 669.120 = 85,19 persen.
- Lidah (flap) dalam sapuan: bentangan = badan + lidah pada sisi terpilih; interlock: lidah efektif = lidah - geseran, geseran maksimal sama dengan lidah.
  Urutan hasil: up terbanyak, lalu efisiensi.
- Plano terbaik: dengan qty: lembar kotor = ceil(qty / up), biaya patokan = lembar kotor x luas plano; tanpa qty: luas plano / up. Yang terkecil menang.

### C. BOP: qty dihitung mundur
Dimulai dari langkah terakhir. Untuk tiap langkah: keluar = qty yang dibutuhkan langkah berikutnya (dalam lembar sampai Plong:
pembulatan ke atas dari pcs / up; sesudah Plong dalam pcs); waste = pembulatan ke atas (keluar x waste persen / 100);
toleransi = pembulatan ke atas (keluar x toleransi persen / 100); masuk = keluar + waste + toleransi.
Kebutuhan langkah sebelumnya = masuk (dikali up bila langkah itu lembar). Langkah yang digabung menjumlah waste dan toleransi.
Perkiraan jam = masuk / target per jam (satu desimal).

Contoh (waste dan toleransi persen awal dari migrasi; Kupas dan Hitung digabung):

| Langkah | waste, toleransi (persen) | masuk |
|---|---|---|
| Wrapping (pcs) | 0, 0 | 10.000 |
| Kupas & Hitung (pcs) | 0,5, 0 | 10.000 + 50 = 10.050 |
| Plong (lembar) | 1,5, 0,5 | 559 + 9 + 3 = 571 |
| Laminating Doff | 1, 0,5 | 571 + 6 + 3 = 580 |
| QC Cetak | 0,5, 0 | 580 + 3 = 583 |
| Cetak | 3, 1 | 583 + 18 + 6 = 607 |
| Potong Plano | 0,5, 0 | 607 + 4 = 611 |

Lembar cetak yang disiapkan = 611 (tanpa waste hanya 556), selisih sekitar 10 persen.

### D. BOM: kebutuhan per lembar dan total
Kebutuhan total = per lembar x lembar masuk langkah pertama BOP (di contoh 611); satuan LBR dibulatkan ke atas; hasil dibulatkan 4 desimal.
- Kertas plano: per lembar = 1 / canvas per plano (satuan LBR). Contoh 611 LBR; konversi ke RIM = LBR / 500 (konstanta `RIM_SHEETS`) = 1,222 rim.
- Plastik laminasi (meter): sisi pendek lembar (m) x takaran = 0,70 x 1 = 0,70 m per lembar; total 427,7 m.
- Lem laminasi (kg): panjang x lebar lembar (cm) / 10.000 x takaran (g/m2) / 1000 = 0,7 x 10 / 1000 = 0,007; total 4,277 kg.
- Tinta (kg): tutupan per lembar (cm2) / 10.000 x takaran / 1000. Tutupan per lembar = persen x luas blank x up = 0,30 x 300 x 18 = 1.620 cm2;
  per lembar = 0,162 x 1,5 / 1000 = 0,000243 kg; total 611 lembar = 0,1485 kg.
- Foil dan varnish: luas pakai belum bisa dihitung otomatis, diisi manual (kebutuhan tetap kosong).
- Konversi satuan stok: KG ke G dikali 1000; M ke CM dikali 100; M ke ROLL dibagi panjang roll dari nama item.
- Luas blank = die W x die H (cm2); bila kosong, flat W x flat H (mm) / 100.
- PPIC: Release = stok tersedia (tidak melebihi Required); Purchase = maks(0, Required - Release).

### E. Usage Rate (pembelajaran bulanan)
- Takaran usulan = takaran sekarang x (bahan nyata terpakai / bahan direncanakan). Direncanakan = jumlah kebutuhan di WO selesai;
  nyata = keluar gudang - kembali. Contoh: takaran tinta 1,5; rencana 20 kg; nyata 24 kg; usulan = 1,5 x 24 / 20 = 1,8.
- Waste usulan (persen) = cacat / (baik + cacat) x 100, per nama proses. Contoh: baik 9.700, cacat 300; waste = 300 / 10.000 x 100 = 3,00 persen.
- Dipakai langsung hanya bila aturan bertanda auto apply dan sampel (jumlah WO) lebih dari atau sama dengan batas minimal (bawaan 5).
