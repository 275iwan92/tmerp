# Work Order di PPIC: dari jadwal kirim sampai bahan siap

*Panduan untuk Jo. Ditulis dari membaca kode (bukan dari dokumen lama), 16 Oktober 2026. Hal yang tidak bisa saya pastikan ditandai "belum terverifikasi" dan dikumpulkan di bagian 7. Bagian terakhir (rumus) khusus johiwan.*

Bayangkan CO sudah Confirmed dan salah satu jadwal kirimnya (CPDEL) harus mulai dikerjakan. Pertanyaan PPIC sekarang: apa saja yang harus dikerjakan, di mesin mana, bahan apa yang dipakai, dan apakah bahannya sudah ada? Jawabannya tinggal di satu tempat: **Work Order (WO)**. Panduan ini menjelaskan bagaimana WO lahir, bagaimana PPIC memeriksanya di Form WO, bagaimana angka bahan (Bill of Material) dibaca, dan bagaimana "gate" kesiapan menahan atau melepas jadwal.

## 1. Untuk apa layar ini dan siapa yang memakainya

Layar **Planning > Work Order** (`production-wo`) adalah tempat PPIC menyiapkan satu pekerjaan sebelum masuk jadwal utama. Isinya satu kartu ringkas di atas (nomor WO, customer, produk, qty, tanggal jatuh tempo, perkiraan mulai/selesai, apakah terlambat) lalu lima bagian yang bisa dibuka-tutup: **Master Card, Bill of Process (BOP), Bill of Material (BOM), Simulation, dan QUE** (antrean semua proses di semua mesin).

Pemakainya:

- **PPIC** adalah pemakai utama. Hanya user yang punya flag akses *PPIC Scheduling* yang boleh mengubah apa pun (membuat, PROCESS, APPLY, hapus, ubah angka bahan).
- **Gudang** terlibat dari sisi data: angka *On hand* dan *Available* di BOM berasal dari stok gudang dan dari booking. Gudang tidak mengisi layar ini.
- **Produksi** baru menyentuh WO setelah WO diterapkan ke jadwal utama (operator memakai layar Production Output, bukan layar ini).

Layar ini sengaja tidak menghitung ulang logika ProDev atau ProCost. Daftar proses dan bahan berasal dari ProDev (engine BOP/BOM); PPIC hanya memeriksa, menyesuaikan, lalu menerapkannya.

## 2. Dari mana WO lahir

WO tidak dibuat dari nol. Ia lahir dari **satu baris jadwal kirim (CPDEL)** milik CO yang sudah **Confirmed dan approved**. Ada dua pintu yang memanggil mesin yang sama:

1. Tombol **Post to Production** di layar CPDEL.
2. Menu **Create Work Order** di layar Work Order: PPIC mencentang satu atau beberapa baris jadwal kirim, lalu menekan Create. Di balik layar ini juga memanggil Post to Production, jadi baris itu otomatis ditandai Posted.

Syaratnya hanya dua: CO sudah Confirmed/approved, dan baris kirim belum punya WO (kalau sudah, sistem menolak agar tidak ada WO ganda). Harga costing published **tidak** lagi disyaratkan; semua jenis CO, termasuk CO direct, boleh diproduksi.

**WO per part.** Kalau produk punya beberapa part (misalnya badan dan tutup), setiap part menjadi **satu WO sendiri**. Qty WO = qty kirim dikali jumlah part per SKU (kalau kosong dianggap 1). Tanggal jatuh tempo WO = tanggal kirim pada baris CPDEL. Proses dan bahan diambil dari ProDev sesuai qty itu.

**WO sederhana.** Kalau produk di CO direct tidak punya Inquiry atau tidak punya part, sistem tetap membuat **satu WO per baris kirim**, tetapi kosong: tanpa proses dan tanpa bahan. PPIC yang melengkapinya. Kalau baris CO belum punya Master Product, pembuatan ditolak dengan pesan untuk memilih produknya dulu. Untuk CO direct yang produknya pernah lewat Inquiry, sistem memakai Inquiry terakhir produk itu sebagai sumber proses dan bahan.

**Nomor dan status.** Nomor WO berbentuk `WO` + dua digit tahun + lima digit urut, misalnya `WO2600225`; urutan naik per tahun. Jenisnya MTO (make to order), sumbernya "CO Confirmed". Status yang terlihat di layar:

| Yang tersimpan | Yang tampil | Arti |
|---|---|---|
| DRAFT (atau SIMULATED) | DRAFT | Baru lahir, masih bebas diubah dan disimulasikan |
| SCHEDULED | APPLIED | Sudah diterapkan ke jadwal utama, terkunci |
| CANCELLED | CANCELLED | Dihapus dari perencanaan (wajib alasan) |

Tidak ada tombol "Confirm WO" lagi di header; penerapan ke jadwal lewat tombol **APPLY TO LIVE SCHEDULE**. Status selesai/closed WO sesudah produksi tidak saya telusuri (lihat bagian 7).

## 3. Alur langkah demi langkah di layar

**Langkah 1. Create Work Order.** Buka Planning > Work Order, tekan New. Muncul daftar baris jadwal kirim dari CO Confirmed yang belum punya WO. Kolom *Product Development* berkata "Complete, n parts" bila setiap part sudah punya dieline, artwork, dan ukuran; kalau kurang, tertulis "Master Card incomplete" beserta apa yang kurang. Baris yang produknya belum punya Master Card **tidak ditawarkan** sama sekali; sistem memberi peringatan berapa baris yang tersembunyi. Centang baris, tekan Create Work Order.

**Langkah 2. WO lahir sebagai Draft.** Untuk setiap WO baru sistem otomatis memanggil ProDev untuk proses dan bahan, melengkapi data penjadwalan (ukuran lembar cetak, jumlah up, gramatur, jumlah warna, dieline, mesin yang mampu per proses, target per jam, setup), memasang gate, dan meminta engine membuat rencana jadwal berstatus **Draft** (belum jadwal utama). Form WO terbuka pada WO pertama; WO lainnya menunggu lewat tombol *Next Work Order*.

**Langkah 3. Periksa Form WO.** Bagian-bagiannya:

- **Kartu atas**: nomor, customer, produk, qty, Due Date, Est. Start/Finish, penanda *On time/Late*, status dokumen. Ada pemilih **Priority** (Normal, Move up, Hot job) dan kolom **Earliest start** (engine tidak akan memulai operasi WO ini sebelum jam itu; kosong berarti secepat mungkin). Perubahan Priority dan urutan proses *tertunda* sampai PROCESS ditekan; bisa dibatalkan lewat Discard pending changes.
- **Master Card**: dirakit otomatis dari Inquiry dan ProDev, hanya dibaca. Tidak ada yang diketik di sini.
- **Bill of Process**: daftar operasi berurutan. PPIC boleh mengubah target per jam, waste/spoilage, urutan, Start dan Stop; menambah (ADD), menghapus (DELETE), atau RESET ke keluaran engine.
- **Bill of Material**: lihat bagian 4.
- **Simulation**: Gantt semua operasi yang belum dikonfirmasi, bisa digeser; panel dampak menyebut WO lain yang ikut bergeser.
- **QUE every process every machine**: antrean per proses dan mesin, lengkap dengan jeda (*Idle before*) dan alasannya.

**Langkah 4. PROCESS.** Tombol di bar atas ini menjalankan seluruh rantai satu kali: (a) perubahan urutan/prioritas yang tertunda diberlakukan, (b) operasi mengikuti BOP ProDev terbaru, (c) baris bahan dihitung ulang dari ProDev, (d) target dan setup dilengkapi, (e) gate dipasang ulang, (f) jadwal Draft dibuat ulang. Tombol ini menyala hanya bila ada perubahan atau geseran tertunda. Bila ada proses yang tidak mendapat tempat di jadwal (tidak ada mesin dan bukan subkon), PROCESS **ditolak** dengan daftar prosesnya, dan APPLY tetap mati.

**Langkah 5. APPLY TO LIVE SCHEDULE.** Satu-satunya jalan untuk "Confirm" WO. Tombol baru menyala bila (1) PROCESS sudah dijalankan dan tidak ada perubahan sesudahnya, (2) semua bahan baku siap (bagian 5), dan (3) tidak ada gate BLOCK serta setiap operasi dapat slot. Alasan tombol mati selalu ditulis di peringatan kuning. Saat ditekan, engine dijalankan sekali lagi terhadap jadwal yang berlaku *sekarang*, lalu seluruh operasi WO dikunci dan status menjadi APPLIED. Ada juga *APPLY ALL WOs to LIVE SCHEDULE* di toolbar Gantt untuk banyak WO sekaligus; WO yang belum siap dilewati tanpa menggagalkan yang lain.

**Apa yang bisa diedit dan kapan.** Semua sheet dan tombol hanya aktif selama WO **belum dikonfirmasi dan belum ada operasi yang mulai**. Begitu satu operasi Confirmed atau berjalan, angka bahan dan proses hanya bisa dibaca, tombol Delete mati ("A confirmed Work Order cannot be deleted"), dan perubahan harus lewat Undo Confirm. **Delete** pada WO yang masih bebas: WO menjadi CANCELLED dengan alasan, rencananya dikosongkan, dan baris jadwal kirimnya kembali muncul di daftar Create (kecuali masih ada WO hidup lain dari baris itu).

## 4. Bill of Material di Form WO

Satu baris BOM = satu bahan yang dipakai satu proses. Kolomnya:

| Kolom | Arti | Dari mana |
|---|---|---|
| Process | Proses BOP yang memakai bahan ini | ProDev; kosong ("Process not set") bila engine tidak menautkan, boleh dipilih PPIC dari proses BOP WO ini |
| Material, Role | Nama bahan dan perannya (kertas, tinta, laminasi, dan sebagainya) | ProDev; bahan yang belum dipilih di Inquiry tampil dengan nama kategorinya |
| **Required** | Kebutuhan bahan untuk qty WO ini, **sudah termasuk waste dan toleransi** | Hitungan mundur ProDev dari qty order |
| Waste, Spill | Waste dan toleransi langkah BOP yang memakai bahan itu, ditampilkan saja | Langkah BOP; hanya bila satuannya sama dengan satuan bahan |
| Unit | Satuan baris | ProDev |
| **On hand** | Stok fisik di lokasi yang boleh dipakai | Gudang |
| **Available** | Stok yang masih bebas: On hand dikurangi sisa booking yang masih terbuka | Gudang dan booking |
| **Release** | Berapa yang dilepas ke WO ini | Awalnya sama dengan Available; boleh diubah PPIC |
| **Purchase** | Berapa yang kurang dan harus dibeli | Required dikurangi Release, tidak pernah negatif |

Aturan yang ditetapkan Jo pada 13 Oktober: **Release = Available** (angka PPIC menang bila diisi), dan **Purchase = Required dikurangi Release**. Kalau stok lebih banyak dari kebutuhan, Purchase tampil 0, bukan minus.

Contoh sederhana. Kertas butuh 2.679 lembar; Available 2.500. Release otomatis 2.500, Purchase 179. Layar menulis bahwa bahan belum siap ("Buy 179 LBR ..."), gate bahan menunggu, dan APPLY mati sampai 179 lembar itu tersedia atau angkanya diubah dengan alasan.

**Mengedit.** PPIC boleh mengubah Required, Release, Purchase, Waste, dan Spill. Release dan Purchase saling menghitung (mengubah satu menggeser yang lain). Release **tidak boleh melebihi Available** dan tidak boleh di bawah 0; sistem menolak dengan pesan. Setiap angka yang berbeda dari hitungan engine wajib disertai **satu alasan**, dan perubahan itu dicatat (siapa, angka lama, angka baru). Mengubah Waste atau Spill menggeser Required sebesar selisihnya. Baris yang diedit ditandai "edited", yang ditambah PPIC ditandai "added". Tombol **RESET** mengembalikan sheet ke keluaran engine (baris yang dihapus muncul lagi, ubahan kembali, baris tambahan dibuang), dengan konfirmasi yang menyebut jumlahnya. RESET ditolak bila engine tidak memberi satu baris pun, agar BOM tidak kosong.

**Substitusi.** Bahan pengganti yang pernah dipilih untuk suatu baris dihormati: gate memeriksa stok bahan penggantinya, dan pilihan itu tetap ada setelah PROCESS. Tombol untuk memilih pengganti baru sudah dihapus dari layar ini.

**Kapan stok dipesan (booking) dan kapan tidak.** Ini sering disalahpahami: **Release di Form WO tidak memesan stok.** Pemesanan stok adalah pekerjaan engine Material Planning, bukan layar PPIC:

- Sejak costing terbit, sistem membuat *pre-booking* lunak per inquiry (tahap B0, lalu B1 saat Quotation terbit), berlabel "Not CO yet".
- Saat CO dibuat, booking ikut ke tahap B2 (draft), dan saat CO Confirmed ke B3. PPIC mengunci booking itu di Material Planning; baru saat itu stok dicadangkan per lokasi.
- Booking yang di-Hold berakhir sendiri setelah beberapa hari kerja; booking Confirmed yang tidak pernah menjadi WO diberi tanda basi dan akhirnya dilepas otomatis (angka hari ada di bagian rumus).
- Semua booking terbuka, apa pun tahapnya, **mengurangi** angka Available di BOM. Jadi pre-booking milik inquiry lain bisa membuat Available lebih kecil dari yang terlihat di gudang.

Akibatnya, dua WO yang memakai bahan sama bisa sama-sama melepas stok yang sama (butir terbuka, bagian 7). Kolom Purchase juga belum membuat permintaan pembelian (PR) otomatis; angka itu hanya tampilan dan alasan gate.

## 5. Gate kesiapan

Setiap operasi WO punya tiga gate: **material, tooling, dan artwork**, masing-masing **Ready, Waiting, atau Blocked**, plus satu catatan. Gate ditulis ulang setiap WO dibuat, di-PROCESS, atau angka BOM-nya diedit.

**Material.** Dicek **hanya di operasi pertama** WO (operasi lain otomatis Ready). Urutan pemeriksaan:

1. WO dari Inquiry tetapi tanpa baris bahan: **Waiting** ("run PROCESS or check the BOM"). Sistem tidak pernah menganggap "tidak ada bahan" sebagai "stok aman".
2. Ada bahan yang belum dipilih (kode bahan kosong, Required lebih dari 0): **Waiting** ("Material not chosen yet").
3. Release lebih besar dari Available: **Blocked** ("Release exceeds available stock").
4. Purchase lebih dari 0: **Waiting** ("Buy ..."). Untuk jenis ini tanggal bahan datang tidak diketahui, jadi jadwal tidak digeser; yang terjadi adalah APPLY ditolak.
5. Kalau semuanya lolos, baru hasil Material Planning (PO dan PR yang akan datang) dilihat: bahan yang menunggu PO/PR menjadi **Waiting** dengan tanggal datang; bahan kurang yang tak ada yang datang menjadi **Blocked**.

Baris dengan Required 0 (misalnya luas foil atau UV yang belum terhitung) diabaikan, jadi tidak pernah menahan.

**Tooling.** Hanya untuk proses yang butuh pisau (die) atau plate. Master tooling dicari menurut referensi dieline atau produk. Status *Ready* dan *In use* lolos; *Not made, In making, Needs repair* menjadi **Blocked** dengan catatan seperti "Die ... is in making". Tooling yang belum terdaftar di master tetap **Ready** dengan catatan.

**Artwork.** Saat ini **selalu Ready**: tidak ada pemeriksaan artwork di gate. Kelengkapan dieline, artwork, dan ukuran hanya tampil sebagai informasi di daftar Create Work Order, dan memengaruhi apakah baris ditawarkan lewat ada-tidaknya Master Card; bukan sebagai gate.

**Akibat pada jadwal.** Operasi dengan gate *Blocked* tidak ditempatkan, dan semua operasi sesudahnya di WO yang sama ditandai terblokir berantai. Operasi dengan gate *Waiting* tetap ditempatkan, tetapi ditandai bila slotnya lebih awal dari saat bahan siap. Operasi yang sudah Confirmed tetapi belum jalan, lalu gate-nya menjadi Blocked (atau bahan datang setelah slotnya), **tidak lagi menahan mesin**: engine menarik pekerjaan berikutnya yang siap ke slot itu dan mengusulkan ulang pekerjaan bermasalah di Draft untuk diputuskan petugas. Peringatan merah (*Blocked*) atau kuning (*Waiting*) tampil di kartu atas Form WO.

## 6. Hal yang sering membingungkan, dan batasnya

- **SUBCON dan proses tanpa mesin.** Proses BOP tanpa mesin adalah subkon dan itu sah, bukan celah. Di Gantt operasi seperti ini digambar di grup SUBCON, satu baris per proses, dan di Bill of Process kolom mesinnya tertulis "subcon". Lead time subkon bawaan 3 hari bila master proses tidak mengisi. Karena operasi tanpa mesin tidak memakai kapasitas, beberapa bar subkon bisa bertumpuk; ini butir terbuka engine.
- **Dua pintu pembuatan WO.** Post to Production di CPDEL tidak mensyaratkan Master Card, sedangkan daftar Create Work Order menyembunyikan baris yang produknya belum punya Master Card. Baris yang sama bisa diproduksi lewat satu pintu tetapi tidak muncul di pintu lain.
- **Release bukan booking**, dan **Purchase bukan PR** (bagian 4).
- **Available di BOM bisa lebih kecil dari layar lain.** Material Planning dan ProDev masih membaca angka stok versi lama (hanya mengurangi booking Confirmed), sedangkan BOM mengurangi booking semua tahap.
- **Satuan tidak dikonversi.** Required (LBR, M, KG) dibandingkan langsung dengan stok dalam satuan stok. Bila beda, Purchase dan Available tidak sebanding. Konversi hanya ada di ProDev.
- **Zona beku.** Alur dan data Inquiry, ProDev, dan ProCost tidak diubah dari layar ini. Form WO hanya *membaca* keluaran engine lalu menyimpan penyesuaian PPIC sebagai timpaan (override) berikut alasannya.
- **PROCESS menimpa sebagian.** PROCESS membangun ulang baris bahan dari ProDev, tetapi mempertahankan edit PPIC (Required dengan alasannya, Waste/Spill, substitusi, proses yang dipilih, baris yang ditambah) dan tidak menghidupkan lagi baris engine yang sengaja dihapus PPIC.

## 7. Catatan untuk Jo: selisih dokumen dan kode, dan yang belum pasti

Selisih yang saya temukan:

1. **Jadwal booking basi.** Komentar di perintah `inventory:booking-maintain` berkata "belum dijadwalkan", tetapi `routes/console.php` menjadwalkannya tiap hari pukul 01.45 (hanya bila kolom `hold_until` ada).
2. **Registry engine Readiness Gates** menulis "Waiting dengan tanggal datang". Untuk kekurangan yang terdeteksi dari baris BOM WO sendiri (jalur utama sekarang), tanggal datang kosong; tanggal hanya ada di jalur hasil Material Planning.
3. **Registry WO Generation** menulis target per jam diambil dari produk dan kode part. Di jalur utama target berasal dari BOP ProDev; pencarian lewat produk dan part hanya dipakai bila ProDev tidak memberi BOP.
4. **Registry WO Generation** menulis penolakan bila baris tak punya Master Product; di kode penolakan itu hanya terjadi pada jalur WO sederhana.
5. **ENGINE-ATTENTION.md** menyebut nomor baris `GateService` yang sudah bergeser, dan butir 01 sudah SELESAI (patch SpecEngine dipasang 14 Okt). Butir 02 sampai 08 masih terbuka sesuai kode yang saya baca.
6. `docs/handoff` tidak ada; `production_scheduling_wo_generation_delta.md` memakai istilah lama (Screen #1/#2) dan tidak saya jadikan acuan.

Yang tidak bisa saya pastikan (belum terverifikasi):

- **Rumus bahan di ProDev.** Saya membaca hitungan mundur lembar cetak (bagian 8), tetapi cara `figures()` mengubah lembar cetak menjadi Required untuk tinta, laminasi, lem, dan foil tidak saya telusuri satu per satu.
- **Status sesudah APPLIED.** Di layar ini saya hanya melihat DRAFT, SCHEDULED, CANCELLED. Kapan WO menjadi selesai/closed, dan siapa yang menutupnya, belum saya telusuri.
- **Isi data live.** Saya tidak membaca database, jadi tidak tahu berapa WO aktif, apakah ada booking ber-qty, atau apakah master tooling terisi. Menurut ENGINE-ATTENTION, per 13 Okt belum ada booking ber-qty di ddt*.
- **Operasi Draft tersisa** pada WO yang sudah APPLIED (WO 273/274/283/286) adalah pertanyaan Jo yang masih terbuka di ENGINE-ATTENTION butir 08.
- **Crew dan kalender.** Bentrok orang tidak dicek di jadwal sekarang (hanya mesin), dan libur/shift mesin belum diuji dengan kasus khusus.
- **Angka konstanta booking** (hari Hold, hari basi) saya ambil dari migrasi; nilai di tm2* bisa sudah diubah lewat Settings > Constants.

<!-- johiwan-only -->
## 8. Rumus nyata dari kode dan contoh angka (khusus johiwan)

Sumber: `SpecEngine::chain` dan `forWorkOrder`, `WoGenerationService`, `AvailableStock::of`, `GateService::release/purchase/materialShortages/tooling/apply`, `Ppic::bomSheet`, `PpicController::bomCells`. Contoh angka saya hitung sendiri dengan nilai rekaan (bukan data nyata).

**Qty WO dan jatuh tempo**

- `qty WO = ship_qty x (qty_per_sku; kosong atau 0 dianggap 1)`, per part.
- `due_date WO = ship_date` baris CPDEL. Nomor: `WO` + `yy` + urut 5 digit (nomor tertinggi tahun itu + 1).

**Required (hitungan mundur ProDev, `SpecEngine::chain`)**, dari langkah BOP terakhir ke pertama. `cut` = langkah die-cut (Plong) terakhir, atau langkah terakhir bila tidak ada. Langkah sampai `cut` dihitung dalam lembar (LBR), sesudahnya pcs.

```
need awal = qty order (pcs)
untuk tiap langkah dari belakang:
  out   = langkah lembar ? ceil(need / up) : need
  waste = ceil(out x waste_pct / 100)
  tol   = ceil(out x tolerance_pct / 100)      (toleransi = kolom "Spill")
  in    = out + waste + tol
  need  = langkah lembar ? in x up : in         (jadi kebutuhan langkah sebelumnya)
lembar cetak = qty "in" langkah pertama BOP -> dasar Required kertas
```

Contoh: qty 10.000 pcs, up 4. Plong (waste 2%, toleransi 1%): out = ceil(10.000/4) = 2.500; waste = 50; tol = 25; in = 2.575; need = 2.575 x 4 = 10.300. Cetak (waste 3%, toleransi 1%): out = ceil(10.300/4) = 2.575; waste = ceil(77,25) = 78; tol = ceil(25,75) = 26; in = 2.575 + 78 + 26 = **2.679 lembar**. Bila `up` layout belum ada, dipakai 1 up per lembar dengan peringatan. Dari lembar cetak ke Required kertas dan bahan lain lewat `figures()`: belum terverifikasi.

Waste/Spill di sheet BOM = `waste_qty` / `tolerance_qty` langkah BOP proses baris itu, **hanya bila satuan sama** (`waste_uom` sama dengan `uom` baris, tanpa konversi); selain itu kosong. Nilai tampil = timpaan PPIC bila ada, kalau tidak nilai ProDev. Required sudah memuatnya, jadi tidak ditambahkan lagi.

**Available (`AvailableStock::of`)**

```
onhand    = SUM(qty_onhand) dari v_rm_available (is_usable = 1) per bahan
booked    = SUM(qty_book - qty_issued) dari rm_booking_d
            syarat: baris tidak batal; header tidak batal dan tidak closed; doc_status bukan CANCELLED;
                    qty_book > qty_issued; dan bukan HELD yang hold_until-nya sudah lewat
Available = max(0, onhand - booked)
```

Semua tahap booking (Draft, PRE B0/B1, B2, Held, Confirmed) dikurangkan, sekali saja. Contoh: on hand 3.000, booking terbuka 400 + 100 -> Available 2.500. Booking 3.500 -> Available 0 (bukan -500).

**Release dan Purchase**

```
Release  = nilai PPIC bila terisi, kalau tidak = Available     (tidak dibatasi oleh Required)
Purchase = max(0, Required - Release)
```

Contoh: Required 2.679, Available 2.500 -> Release 2.500, Purchase 179. Required 2.679, Available 4.000 -> Release 4.000, Purchase 0. Available 0 atau stok tak ditemukan -> Release 0, Purchase 2.679. PPIC mengisi Release 2.000 -> Purchase 679.

**Validasi edit (`bomCells`)**: angka harus numerik dan 0 atau lebih. Release akhir harus di 0..Available (toleransi 0,0049), selain itu ditolak (422). Bila Release dan Purchase sama-sama diubah, `Purchase yang diketik` harus sama dengan `max(0, Required - Release)` dalam selisih 0,0149, kalau tidak ditolak. Bila hanya Purchase diubah: `Release = Required - Purchase`. Edit Waste/Spill tanpa mengetik Required: `Required baru = Required lama + (selisih waste + selisih spill)`. Dianggap "berbeda dari engine" bila `|Required - qty_standar| > 0,00005` atau `|Release - Available| > 0,00005`; yang berbeda wajib alasan (maksimum 190 karakter). Bila tidak berbeda, `qty_release` dan `qty_purchase` dikembalikan NULL (otomatis lagi). Override Waste/Spill yang sama dengan nilai ProDev (selisih < 0,00005) dibuang.

**Status gate material (`materialShortages`, lalu `ownLines`)**, hanya baris dengan `qty_required > 0`; bahan pengganti (`is_substituted`) memakai id material penggantinya:

| Kondisi | Status | Catatan |
|---|---|---|
| WO dari Inquiry tanpa baris bahan | WAIT | NO_LINES |
| Ada baris tanpa `material_id` | WAIT | NOT_CHOSEN (menang atas semua baris lain) |
| Release > Available + 0,0001 | BLOCK | OVER_RELEASE |
| Purchase > 0,0001 | WAIT | SHORT, "Buy ..."; `ready_at` kosong |
| Tidak ada kekurangan | lanjut ke Material Planning | tak ada baris untuk CO/part: READY; alokasi PEGGED_PO/PEGGED_PR/LATE_PO/LATE_PR: WAIT dengan tanggal terlambat pukul 08.00; status SHORT: BLOCK |

Gate sebuah operasi = BLOCK bila salah satu dari tiga gate BLOCK, WAIT bila ada yang WAIT, selain itu READY. Gate material hanya ditulis ke operasi pertama (urutan `seq_no`), `gate_artwork` selalu READY, `gate_note` dipotong 190 karakter. Tooling: proses `requires_die` memakai CUTTING_DIE, `requires_plate` memakai PLATE_SET; aset aktif dicari menurut `dieline_ref` atau `product_id`, diurutkan READY, IN_USE, IN_MAKING, NEEDS_REPAIR, NOT_MADE; READY/IN_USE lolos, lainnya BLOCK; tidak ada aset: READY dengan catatan.

**Syarat APPLY (`applyBlockedReason` dan `PlanConfirmService::confirm`)**: PROCESS sudah dijalankan dan sidik data tidak berubah (`process_hash`), tidak ada geseran tertunda, `materialShortages` kosong, tidak ada operasi dengan gate BLOCK atau tanpa slot mesin (`engineCheck`), tidak ada proses tak terjadwal (`ScheduleGuard`), dan tidak ada tabrakan mesin dengan operasi CONFIRMED milik WO lain. Setelah APPLY, `wo_status` menjadi SCHEDULED dan `schedule_layer` COMMITTED; sejumlah shift pertama dikunci (`lock_shifts` di PPIC Settings, nilainya tidak saya baca).

**Booking dan konstanta** (nilai bawaan dari migrasi; bisa berbeda di data live)

- `BOOKING_NEED_WORKDAYS` = 3: tanggal butuh bahan = yang lebih awal antara tanggal kirim dan tanggal CO ditambah 3 hari kerja (menurut registry engine; pre-booking punya konstanta sendiri `INV_PRE_BOOKING_NEED_WORKDAYS`, nilainya tidak saya baca).
- `INV_BOOKING_HOLD_WORKDAYS` = 3: Hold berakhir sendiri setelah 3 hari kerja.
- `INV_BOOKING_STALE_WARN_DAYS` = 7: booking Confirmed ber-CO tanpa WO diberi tanda basi 7 hari setelah tanggal butuh.
- `INV_BOOKING_STALE_RELEASE_DAYS` = 30: dilepas 30 hari setelah tanggal butuh, stok kembali Available. Booking yang sudah ada qty keluar atau sudah punya WO tidak pernah dilepas. Berjalan harian pukul 01.45.
- Rebutan stok di Material Planning: Confirmed lebih dulu, lalu Draft, lalu Forecast, tiap tingkat menurut tanggal butuh; pre-booking costing tingkat paling akhir.

**Enricher (`WoPlanningEnricher`)**: `qty_out` operasi terakhir = qty WO; operasi lain = `qty` operasi berikutnya, atau `ceil(qty berikutnya / ups_per_sheet)` bila satuan berganti (LBR ke PCS) dan up diketahui. Satuan: PRESS/POSTPRESS/PREPRESS atau PLONG ber-up = LBR, selain itu PCS. `run_minutes = ceil(qty / target_per_jam x 60)`. Mesin pertama yang layak (menurut urutan preferensi) memberi target dan setup bawaan bila operasi belum punya. Mesin tidak layak bila lembar/gramatur/qty di luar batas, atau, untuk proses "CETAK", jumlah warna WO melebihi warna per lintasan mesin.
