# ProCost: Cara Sistem Menghitung HPP dan Harga Jual

Panduan ini ditulis untuk Jo sebagai pemilik sistem. Isinya menjelaskan apa yang sebenarnya dilakukan ProCost menurut kode yang berjalan hari ini (bukan menurut dokumen lama). Semua bagian sebelum penanda di paling bawah aman dibaca siapa pun karena tidak memuat rumus, angka harga, maupun margin. Rumus nyata dan contoh hitungan hanya ada di bagian terakhir, khusus login pemilik.

## 1. Untuk apa ProCost, dan siapa melihat apa

ProCost adalah tahap tempat Tim Costing menjawab dua pertanyaan: berapa harga pokok (HPP) satu produk per pcs, dan berapa harga jual yang pantas untuk tiap jenjang jumlah order (qty-tier). Hasilnya satu dokumen costing (`iqp_costing`) yang berisi bagian-bagian produk (part), lapisan coating, dan satu baris harga per jenjang qty.

Sistem membagi dunia menjadi dua sisi:

- **Sisi A (rahasia).** Rincian biaya, HPP, margin, dan rumus. Menurut aturan A.8, ini hanya untuk Costing dan Direksi, dan rumusnya hanya terlihat oleh login yang ditunjuk. Di layar, siapa yang ditunjuk dibaca dari satu konstanta (`PROCOST_FORMULA_LOGINS`), bukan ditulis mati di kode. Login lain melihat gembok pada kotak rincian biaya dan pada angka HPP.
- **Sisi B (Sales).** Hanya harga jual per jenjang qty, dan itu pun baru setelah dokumen di-publish. Sebelum publish, layar baca-saja untuk Sales hanya menampilkan "Belum Diproses". Penyaringan harga dilakukan di server (`TierPricingResolver`): bila costing belum published, kolom harga memang dikosongkan sebelum sampai ke dokumen Nota Inquiry atau Quotation, bukan sekadar disembunyikan di tampilan.

**Publish adalah deklasifikasi.** Saat Direksi menekan Publish, harga jual dibuka untuk Sales; HPP dan margin tetap tertutup. Hanya costing yang sudah published yang bisa ditarik ke Quotation (aturan A.1, Quotation wajib integrated). Setelah itu tanda `stat_confirm_procost` pada Inquiry menyala (aturan A.20).

Posisi ProCost dalam rantai: Inquiry, lalu ProDev (layout, BOP, BOM), lalu ProCost, lalu Quotation, lalu Customer Order. ProCost tidak membuat keputusan produksi; ia hanya memberi harga.

## 2. Alur langkah demi langkah

Ada dua pintu masuk. Keduanya memakai mesin hitung yang sama (aturan A.6, dikunci).

### Mode #1: Terintegrasi (dari Inquiry dan ProDev)

1. Officer Costing membuka Inquiry yang sudah Confirm Sales dan ProDev-nya selesai, lewat grid Pro-Cost. Saat layar dibuka pertama kali, sistem otomatis membuat dokumen costing berstatus **DRAFT**.
2. Jenjang qty ikut disalin dari jenjang yang diminta Sales di Inquiry. Bila Inquiry tidak punya jenjang sama sekali, sistem membuat satu jenjang pengganti dan menandainya di layar supaya hitungan tidak kosong.
3. Tiap part Inquiry yang sudah punya layout ProDev langsung menjadi baris part costing. Ukuran plano, jumlah potong per plano, isi per lembar cetak, dan ukuran cetak terisi dari layout dan terkunci (tidak bisa diketik ulang). Layout yang berstatus CONFIRMED didahulukan, tetapi bila belum ada, layout terbaru yang masih Draft tetap dipakai (lihat bagian 6).
4. Officer melengkapi sisanya yang memang tidak datang dari ProDev: pilihan kertas, ongkos proses per unit, coating, laminasi, jumlah warna, dan hal sejenis.
5. Officer menentukan untuk tiap jenjang qty: toleransi, margin, dan pembulatan (mode serta kelipatannya). Di header ia menentukan apakah harga memakai PPN atau tidak, dan persen PPN-nya.
6. Tekan **Hitung**. Mesin menghitung semua part, menjumlahkannya ke header, lalu menghitung tiap jenjang. Status menjadi **CALCULATED**. Perubahan pada isian belum mengubah angka sampai Hitung ditekan lagi.
7. Tekan **Submit ke Direksi**. Status menjadi **PENDING_APPROVAL** dan dokumen terkunci (baca-saja).
8. Direksi (atau admin) menekan **Publish**. Status menjadi **PUBLISHED**, dokumen ditandai approved dengan nama dan waktu penyetuju, dan status Inquiry ikut maju. Tombol **+ Quotation** lalu membawa Inquiry ini ke Quotation draft customer yang sama.

Hanya admin dan anggota grup DIREKSI yang bisa Publish. Tidak ada langkah "reject" atau "hold" di layar ini; persetujuan di sini sama dengan Publish (lihat bagian 6).

### Mode #2: Manual atau Instant (tanpa Inquiry)

Officer membuka kalkulator mandiri (menu Pro-Cost Instant) dan mengetik semua parameter sendiri, dibantu nilai awal dari konstanta Costing. Dokumen bermode INSTANT, tanpa Inquiry. Tombol yang tersedia: Save, Confirm (ke PENDING_APPROVAL setelah Hitung sukses), Cancel, Delete, dan **Reverse** (bagian 4). Mode ini sah dan bukan celah: ia mencerminkan cara kerja Excel yang dipakai sekarang.

### Recalc

"Recalc" dipakai untuk tiga hal yang berbeda, dan ini sering membingungkan:

- **Inquiry bertipe Recalc** (repeat order, spesifikasi sama, harga bahan berubah). Sales membuatnya, Confirm, lalu langsung dikirim ke Pro-Cost tanpa lewat ProDev. Di layar Pro-Cost ia memakai halaman costing yang sama dengan Inquiry baru.
- **Tombol Hitung** di costing terintegrasi.
- **Tombol Recalc** di kalkulator Instant (menghitung ulang dokumen itu saja).

Layar Sales untuk Recalc sengaja tidak menampilkan HPP maupun harga; ia hanya mengirim ke Costing.

## 3. Dari mana komponen biaya berasal

Satu part dihitung sesuai jenisnya. Keluarga offset (DC, DC+SF, DC+SHEET, KRAFT) dan keluarga bergelombang (SHEET, SHEET_DIGITAL) punya aturan masing-masing. Jenis part di luar daftar ini membuat mesin berhenti dengan pesan galat, bukan menebak.

- **Bahan (kertas dan sheet).** Harga kertas diambil dari master gramatur (`mat_grammage`), atau dari angka yang diketik officer bila ia menimpanya. Untuk sheet bergelombang, harga diambil dari master substansi sheet (`mat_sheet_rate`) dengan diskon atau surcharge menurut jenjang running meter per supplier (`MatSheetDiscountTier`), dan geometri sheet diturunkan dari dimensi, jenis flute (`mat_flute_allowance`), serta lebar mesin.
- **Proses (cetak, plong, lem, ongkos lain).** Ini diketik officer per part. Mesin **tidak** menurunkan ongkos ini dari BOP ProDev. Yang ada hanya peringatan (bukan blokir): bila proses CETAK, PLONG, atau LEM MESIN ada di routing ProDev tetapi ongkosnya masih nol, atau sebaliknya, layar memberi tahu. Peringatan ini hanya untuk part bertipe DC.
- **Tinta.** Daftar tinta dari Inquiry tampil sebagai informasi baca-saja. Harga tinta **tidak** masuk hitungan; yang masuk hanya jumlah warna, untuk biaya plate (lihat tooling di bawah).
- **Finishing.** Coating berlapis dihitung dari luas cetak dan indeks coating tiap lapis. Laminasi dan SF/EF hanya berlaku untuk DC+SF dan DC+SHEET. Finishing dari ProDev tampil baca-saja sebagai konteks.
- **Tooling.** Film, plate per warna, dan pisau dihitung sebagai biaya per order (bukan per pcs), lalu dibagi qty tiap jenjang. Itu sebabnya HPP per pcs berbeda antar jenjang qty meskipun biaya per pcs lainnya sama.
- **Biaya luar.** Di sistem ada tabel biaya luar per Inquiry (`iqp_external_cost`), tetapi saya tidak menemukan `CostingEngine` membacanya. Jadi biaya luar tercatat di Inquiry, namun belum terbukti ikut ke HPP (lihat bagian 6).
- **Subkon.** Proses tanpa mesin (subkon) tampil di panel BOP baca-saja. Biayanya masuk HPP hanya bila officer mengetiknya sebagai ongkos proses.

Semua konstanta (isi satu rim, luas plano acuan, tarif film, tarif plate, tarif pisau, pembagi PPN bahan) dibaca dari Setup > Constants kelompok Costing. Dengan demikian mengubah angka itu tidak perlu mengubah kode.

**Mengapa HPP kedua mode harus sama.** Mode #1 dan Mode #2 hanyalah dua cara mengisi parameter ke satu mesin yang sama (`CostingEngine`). Bila geometrinya sama, hasilnya sama. Karena itu aturan A.6 meminta keduanya identik, dan tes-tes (`CostingEngineTest`, `CostingExcelParityTest`, `CostingLegacyParityTest`, `CostingInstantModeTest`) membandingkannya dengan Excel dan program HPP lama.

## 4. Reverse Costing

Bayangkan Costing sudah menghitung harga di kalkulator Instant untuk produk yang belum pernah dibuatkan Inquiry. Lalu customer setuju dan produknya harus masuk jalur resmi. Tombol **Reverse** membalik urutan: dari costing, sistem membuat dokumen yang normalnya lahir lebih dulu (Master Product draft, Inquiry bertipe BARU, dan layout ProDev berstatus DRAFT), lalu menautkan semuanya ke costing yang sama dan mengubah mode costing dari INSTANT ke INTEGRATED.

Syarat: costing harus bermode Instant, customer sudah diisi, nama produk sudah diisi, dan minimal ada satu part. Tiga keputusan yang sudah dikunci di kode: Inquiry hasil Reverse selalu bertipe BARU; layout ProDev selalu lahir Draft dan hanya manusia yang boleh meng-Confirm; angka costing yang sudah ada tidak boleh berubah (part tetap "diketik officer", tidak dipindah ke sumber ProDev).

Kapan dipakai: bila hitungan awal sudah ada di kalkulator dan kemudian produknya perlu masuk Quotation. Setelah Reverse, costing itu masih harus melewati Submit dan Publish di layar terintegrasi seperti biasa.

## 5. Hal yang sering membingungkan, batas, dan zona beku

- **Semua zona beku.** Alur Inquiry, ProDev, ProCost, Quotation, dan CO tidak diubah tanpa izin Jo. Panduan ini hanya menjelaskan, tidak mengusulkan perubahan.
- **Hitung belum otomatis.** Mengubah margin, toleransi, atau pembulatan tidak langsung menggeser harga; harga baru muncul setelah Hitung. Ini wajar, tetapi mudah terlupa sebelum Submit. Submit hanya aktif bila statusnya CALCULATED.
- **Dokumen terkunci setelah Submit.** Saat PENDING_APPROVAL atau PUBLISHED, isian part dan header tidak bisa diubah. Untuk mengubah harga yang sudah published, jalurnya adalah Inquiry Recalc atau Revise, bukan menyunting dokumen lama (aturan Rekalkulasi = versi harga bertanggal).
- **Margin dan PPN dipisah.** Jenjang qty punya margin untuk jalur tanpa PPN dan margin sendiri untuk jalur dengan PPN. Bila margin PPN dikosongkan, mesin memakai margin biasa.
- **Aturan toleransi berbeda per jenis part.** Toleransi untuk keluarga offset dan untuk sheet bergelombang tidak diperlakukan sama, sehingga dua produk dengan persen toleransi yang sama bisa menghasilkan selisih yang tidak sama besar. Ini ikut Excel dan program lama, bukan kekeliruan.
- **Pembulatan bisa "tidak membulatkan".** Mode NONE (bawaan) tidak membulatkan. Pembulatan hanya terjadi bila officer memilih mode dan kelipatan.
- **Batas diskon sheet.** Diskon atau surcharge sheet di luar batas per supplier membuat mesin berhenti dengan pesan galat. Itu disengaja agar salah ketik pecahan terlihat.
- **Jenjang qty kosong tidak diam-diam.** Tanpa jenjang qty, mesin memakai qty dasar nol dan hasilnya tidak berarti; layar menyiapkan jenjang pengganti untuk mencegahnya.
- **Mengisi Ukuran Cetak.** Bila ukuran cetak belum diisi, mesin memakai perkiraan dari luas plano dibagi jumlah potong, dan menandainya (UK_FALLBACK) pada catatan hitungan. Layak diperiksa sebelum Submit.

## 6. Catatan untuk Jo: selisih dokumen dan kode, serta yang tidak bisa saya pastikan

Sumber kebenaran saya adalah kode di `app/Services/Costing/` dan layar `resources/views/pages/inquiry/costing*.blade.php`. Temuan:

1. **Siapa boleh melihat rumus.** Aturan A.8 menyebut login `johiwan`. Kode membaca konstanta `PROCOST_FORMULA_LOGINS`, dan nilainya di database yang saya baca (koneksi bawaan; saya tidak memastikan itu mockup atau live) berisi dua login, `johiwan` dan `wiwik2`. Komentar kode menyebut Wiwik dan Carrij Suhendra disetujui 2026-09-18. Jadi aturan tertulis dan perilaku nyata tidak sama; perlu Jo putuskan mana yang benar, lalu salah satunya disesuaikan.
2. **"Harga bahan dibekukan di dokumen."** Registry engine menyebut tarif dibekukan pada dokumen. Di kode, `Hitung` membaca tarif master (gramatur, sheet, flute, konstanta) pada saat itu juga; tidak saya temukan salinan tarif per dokumen maupun pemilihan tarif berdasarkan tanggal. Yang membuat angka lama stabil hanyalah kuncinya setelah Submit atau Publish. Tombol Hitung memang dinonaktifkan di layar saat terkunci, tetapi saya tidak memeriksa apakah ada jalur lain yang bisa memanggilnya. **Belum terverifikasi.**
3. **Persetujuan Direksi hanya satu klik.** Di layar, Submit lalu Publish adalah satu-satunya persetujuan; tidak ada Flow Engine bertingkat, tolak, atau tahan seperti dokumen lain. Dokumen terintegrasi juga tidak punya tombol Cancel (kalkulator Instant punya). Kode `InquiryStage` mengembalikan tanda procost ke 0 bila costing tidak lagi published, tetapi saya tidak menemukan tombol atau jalur kode yang membatalkan Publish di layar. Jadi catatan di aturan A.20 ("kembali 0 bila publish dibatalkan") belum punya pemicu yang saya lihat.
4. **Reverse menyalakan tanda ProDev.** `ReverseCostingService` membuat Inquiry dengan `stat_confirm_sales` dan `stat_confirm_prodev` langsung benar, padahal layout ProDev-nya berstatus Draft (dan aturan A.20 berkata tanda ProDev baru menyala setelah semua part Confirmed). Registry berkata hanya manusia yang bisa Confirm layout; itu benar untuk layoutnya, tetapi tanda pada Inquiry tidak mengikuti. Selain itu Reverse menulis ke dua database dengan dua transaksi terpisah; bila yang kedua gagal setelah yang pertama commit, sisa data di master bisa tertinggal (kode sendiri mencatat batasan ini).
5. **Reverse mengisi harga di jenjang Inquiry.** Reverse menyalin harga jual hasil hitung ke tabel jenjang Inquiry (`iqp_tier`) walau costing belum published. Apakah kolom itu pernah sampai ke Sales saya belum telusuri. **Belum terverifikasi**; kalau Jo memandangnya penting untuk kerahasiaan, perlu dicek.
6. **Mode #1 dan layout Draft.** Dokumen vital menulis bahwa Mode #1 menarik dari layout ProDev yang CONFIRMED. Kode lebih longgar: ia mengurutkan CONFIRMED lebih dulu tetapi memakai layout terbaru berstatus apa pun bila tidak ada yang Confirmed.
7. **`calc_type`.** Dokumen vital menyebut kolom ini hardcode `NEW`. Itu sudah basi: layar kini mengisinya dari tipe Inquiry (NEW, REVISE, RECALC).
8. **Biaya luar dan tinta.** Biaya luar (`iqp_external_cost`) dan harga tinta tidak terbaca oleh `CostingEngine`. Bisa jadi memang disengaja (tinta sudah tercakup di ongkos cetak, biaya luar ditagih terpisah), tetapi itu hanya dugaan saya; tidak ada dokumen yang saya baca yang menyatakannya.
9. **Butir ATTENTION-ENGINE.** Dokumen `ENGINE-ATTENTION.md` memakai nama "ProCost" untuk Material Planning, booking, dan PR/PO, bukan untuk mesin harga. Tidak ada butir terbuka yang menyangkut `CostingEngine` itu sendiri.
10. **Dokumen pendamping tidak saya padankan penuh.** `procost_formula_library.md` dan `productcosting_tier1-4.md` tidak saya baca rumus demi rumus. Rumus di bagian bawah murni dari kode; bila ada selisih dengan dokumen itu, ikuti kode.
11. **Tes tidak saya jalankan.** Contoh angka di bawah saya hitung dari kode dan input tes yang ada, bukan dari keluaran aplikasi yang berjalan.

<!-- johiwan-only -->

## 7. Rumus nyata (khusus login pemilik)

Sumber: `app/Services/Costing/CostingEngine.php`. Konstanta (dibaca dari `sys_constant`, nilai pada database yang saya baca): `RIM_SHEETS` 500, `PLANO_REF_AREA` 8611, `CUT_PER_PLANO_DEFAULT` 1, `PCS_PER_LB_DEFAULT` 1, `FILM_FACTOR_DEFAULT` 0,25, `FILM_RATE_DEFAULT` 55, `PLATE_PER_COLOR` 90.000, `KNIFE_FACTOR_DEFAULT` 0,25, `KNIFE_RATE_DC` 900.000, `KNIFE_RATE_KRAFT` 1.500.000 (tarif pisau per jenis part di `prod_part_type.pisau_rate_rp` didahulukan bila ada), `PPN_INPUT_MATERIAL_DIVISOR` 1,1, `SHEET_MACHINE_WIDTH_CM` 180, `SHEET_TRIM_GAP_CM` 2. Bawaan header: PPN 11 persen dan mode PPN; bawaan jenjang: toleransi 0, margin 0, pembulatan NONE kelipatan 1.

### 7.1 Satu part keluarga offset (DC, DC+SF, DC+SHEET, KRAFT)

Notasi: `cut` = potong per plano, `isi` = isi per lembar cetak (keduanya diganti bawaan bila nol atau kosong), `Aplano` = panjang plano x lebar plano (cm), `Acetak` = ukuran cetak P x L (cm). Bila ukuran cetak kosong, `Acetak = Aplano / cut`.

- Faktor DC duplex: `dcf = 1 + persen_DC`, dengan persen_DC dari master (misal 5,30 menjadi 6,30), bukan 1 + 5,30 persen.
- Kertas duplex: `kertas = (Aplano / 8611) x harga x dcf / 500 / cut / isi`.
- Kertas non-duplex (ivory/kraft per kg): `kertas = (Aplano / 10000) x harga_per_kg x (gsm / 1000) / cut / isi`.
- Harga kertas = angka timpaan officer bila diisi, selain itu dari master gramatur. Bila harga nol, tanda `PAPER_GAGAL` dan kertas bernilai 0.
- Cetak = `ongkos_cetak_per_rim / 500 / cut / isi`. Plong = `ongkos_plong_per_lb / isi`. Lem = ongkos lem per pcs (apa adanya). Ongkos lain (hotprint, kupas, QC/pack, kirim, packing) dijumlah apa adanya.
- Coating (tidak untuk KRAFT): per lapis `Acetak x indeks_coating / isi` (tidak dibagi `cut`), dijumlah semua lapis.
- Laminasi (hanya DC+SF dan DC+SHEET): `J36` = laminasi sheet flat per pcs (bila dipakai); `J37 = (Acetak / 10000) x rate_SF_EF / isi` bila rate > 0; `J38 = luas_lam x rate_sheet_per_cm2 / isi + luas_lam x rate_SF_per_cm2 / isi` (luas_lam = luas piece SF bila dicentang, selain itu `Acetak`); bila kedua rate nol, `J38` = ongkos laminasi per pcs yang diketik.
- Proses per pcs = cetak + plong + lem + ongkos lain + J36 + J37 + J38.
- Total part per pcs = kertas + proses + coating.
- Tooling per order = film + plate + pisau, dengan `film = ff^2 x Acetak x rate_film` (ff bawaan 0,25; rate bawaan 55), `plate = tarif_plate_per_warna x jumlah_warna` (bawaan 90.000), `pisau = faktor_pisau x tarif_pisau` (faktor bawaan 0,25).

### 7.2 Part keluarga bergelombang (SHEET, SHEET_DIGITAL)

- Proses per pcs = ongkos print x 2 + diecut + slitter + slotter + join + partisi + layer + pisau + kupas + QC/pack + kirim + packing.
- Biaya sheet per pcs = `(lebar_trim x panjang_sheet / 10000) x indeks_substansi / isi_per_trim x (1 - diskon)`. Lebar trim, isi per trim, dan panjang diketik (mode manual) atau diturunkan dari dimensi + flute: `panjang = ((P + L) x 2 + total_P_flute + lidah_lem) / 10`, `lebar = (L + T + total_L_flute) / 10`, `isi_trim = max(floor((180 - 2) / lebar), 1)`, `lebar_trim = ceil((lebar x isi_trim + 2) / 5) x 5`.
- Running meter (untuk memilih tier diskon) = `(qty / isi_kerja) / isi_trim x (panjang / 100)`. Diskon di luar batas per supplier membuat mesin berhenti.
- Total part per pcs = sheet + proses. Tooling = 0 (TOOL_SKIP).

### 7.3 Roll-up header

Dengan `per_set` = qty per set part (bawaan 1): material = jumlah((kertas + sheet) x per_set); proses = jumlah(proses x per_set); finishing = jumlah(coating x per_set); tooling = jumlah tooling per order. Qty dasar part = qty pada jenjang utama (atau jenjang pertama).

### 7.4 Tangga harga per jenjang qty

Untuk tiap part: `tooling_pc = tooling_order / qty_jenjang`; `kotor = total_part x per_set + tooling_pc`; `bahan_ber_PPN = (kertas + sheet + laminasi_flat + SF_EF) x per_set`; `kredit = 1 - 1/1,1` (sekitar 9,09 persen); `bersih = kotor - bahan_ber_PPN x kredit` (hanya bila mode PPN; tanpa PPN `bersih = kotor`). Toleransi `t` = toleransi part bila ada, selain itu toleransi jenjang (dibatasi paling tinggi 99,9999 persen).

- Keluarga offset: setelah toleransi (kotor) = `kotor x (1 + t)`; setelah toleransi (bersih) = `bersih + kotor x t`.
- Keluarga bergelombang: `kotor / (1 - t)` dan `bersih / (1 - t)`.
- HPP per pcs = jumlah semua `kotor`. Setelah dijumlah antar part, dibulatkan sesuai mode (UP/CEILING, DOWN/FLOOR, NEAREST ke kelipatan; NONE tidak membulatkan; kelipatan nol juga tidak membulatkan).
- Jalur tanpa PPN: `harga = bulat(setelah_toleransi_kotor) / (1 - m)`. Margin adalah margin atas harga jual (pembagi `1 - m`), bukan markup atas biaya; `m` dibatasi 99,9999 persen.
- Jalur PPN: `DPP = bulat(setelah_toleransi_bersih) / (1 - m_ppn)`, dengan `m_ppn` = margin PPN jenjang, bila kosong memakai margin biasa. `harga_dengan_PPN = DPP x (1 + persen_PPN/100)`.
- Disimpan: `price_per_pcs` = DPP (atau harga jalur tanpa PPN); `c_after_ppn_pc` = harga dengan PPN. Kolom `c_after_margin_pc` selalu memuat jalur kotor, jadi pada mode PPN nilainya berbeda dari `price_per_pcs`.

### 7.5 Contoh angka satu produk

Input mengikuti tes `CostingEngineTest::test_engine_calculates_dc_part_and_price_ladder`, bukan data produk nyata: satu part DC; plano 65 x 100 cm; potong per plano 2; isi per lembar 4; ukuran cetak kosong (jadi `Acetak` = 6.500 / 2 = 3.250 cm2); 4 warna; kertas Duplex 300gsm dengan harga acuan 12.500 dan faktor DC 6,30 (angka dari tes; **belum saya cocokkan dengan master saat ini**); ongkos cetak 200.000 per rim; plong 5.000 per lb; lem 300 per pcs; satu lapis coating dengan indeks 0,035 (dari tes); faktor pisau 0,5; qty 1.000; toleransi 5 persen; margin 20 persen; pembulatan NEAREST kelipatan 100; mode PPN 11 persen.

| Komponen | Hitungan | Hasil |
|---|---|---|
| Kertas | (6.500/8.611) x 12.500 x 6,3 / 500 / 2 / 4 | 14,86 per pcs |
| Cetak | 200.000 / 500 / 2 / 4 | 50,00 |
| Plong | 5.000 / 4 | 1.250,00 |
| Lem | apa adanya | 300,00 |
| Coating | 3.250 x 0,035 / 4 | 28,44 |
| Total part per pcs | jumlah di atas | 1.643,30 |
| Film per order | 0,25^2 x 3.250 x 55 | 11.171,88 |
| Plate per order | 90.000 x 4 | 360.000 |
| Pisau per order | 0,5 x 900.000 | 450.000 |
| Tooling per order | jumlah | 821.171,88 |
| Tooling per pcs | / 1.000 | 821,17 |
| HPP per pcs | 1.643,30 + 821,17 | 2.464,47 |
| Setelah toleransi (kotor) | 2.464,47 x 1,05 | 2.587,69 |
| Dibulatkan | NEAREST kelipatan 100 | 2.600 |
| Jalur kotor setelah margin | 2.600 / 0,8 | 3.250 |
| Kredit PPN bahan | 14,86 x (1 - 1/1,1) | 1,35 |
| Bersih | 2.464,47 - 1,35 | 2.463,12 |
| Bersih setelah toleransi | 2.463,12 + 2.464,47 x 0,05 | 2.586,34 |
| Dibulatkan | NEAREST kelipatan 100 | 2.600 |
| DPP (`price_per_pcs`) | 2.600 / 0,8 | 3.250 |
| Dengan PPN (`c_after_ppn_pc`) | 3.250 x 1,11 | 3.607,50 |

Catatan contoh: kebetulan kedua jalur dibulatkan ke 2.600, sehingga DPP sama dengan jalur kotor. Pada angka lain keduanya bisa berbeda. Pada qty 10.000 tooling per pcs menjadi 82,12 sehingga HPP turun ke sekitar 1.725,42 (tooling dibagi qty yang lebih besar).
