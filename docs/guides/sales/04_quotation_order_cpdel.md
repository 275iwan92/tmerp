# Dari Quotation sampai Delivery Order: satu rantai, enam dokumen

*Panduan untuk Jo. Ditulis dari membaca kode (bukan dari dokumen lama), 16 Oktober 2026. Hal yang tidak bisa saya pastikan ditandai "belum terverifikasi" dan dikumpulkan di bagian 8.*

Bayangkan satu pesanan kardus dari awal sampai barang diterima customer. Ada enam dokumen yang saling menyambung: **Quotation**, **Customer Order (CO)**, **CPDEL** (jadwal kirim), **Work Order** (lahir saat Post to Production), **Delivery Order (DO)** yang dicetak sebagai **Surat Jalan**, dan terakhir **bukti terima** yang menjadi dasar tagihan. Artikel ini menceritakan alurnya, siapa yang memegang tiap langkah, dan di mana sistem menahan atau mengizinkan.

## 1. Rantai lengkap dalam satu gambaran

Urutannya begini. Inquiry yang sudah melewati ProDev dan ProCost (harga sudah **published**) masuk ke **Quotation**. Begitu customer setuju, Quotation di-**WIN**, dan lahirlah **Customer Order**. Saat CO di-Confirm, ia tidak langsung jadi Confirmed: ia **diajukan ke approval** (F&A SPV, lalu Operational Manager). Setelah approval terakhir lolos, CO menjadi Confirmed dan sistem otomatis membuat **jadwal kirim (CPDEL)**. Sales dan PPIC mencocokkan dua janji pada tiap jadwal, lalu PPIC menekan **Post to Production** dan **Work Order** lahir. Dari jadwal yang sudah di-Post, bagian Pengiriman menyusun **Delivery Order**, mengirim barang, dan petugas mencatat **bukti terima**.

| Langkah | Dokumen | Pemilik utama | Status yang ada di kode |
|---|---|---|---|
| 1 | Quotation | Sales | Draft, Published (tombolnya bernama Confirm), lalu tampil sebagai Won / Lost; atau Cancelled |
| 2 | Customer Order | Sales | Draft, Submitted (menunggu approval), Confirmed; atau Rejected / Cancelled |
| 3 | Jadwal kirim (CPDEL) | Sales (slot customer) dan PPIC (siap produksi) | Planned atau Shipped, plus penanda Posted |
| 4 | Work Order | PPIC / Produksi | Draft saat lahir, lalu mengikuti alur produksi |
| 5 | Delivery Order / Surat Jalan | Pengiriman | Draft, Confirmed, Shipped (ditampilkan sebagai Closed), atau Cancelled |
| 6 | Bukti terima (Proof of Delivery) | Officer pengiriman | Satu catatan per percobaan kirim |

Dua hal dari tabel ini. Pertama, **tidak semua dokumen punya keempat status Draft/Confirmed/Cancelled/Closed** persis seperti aturan siklus hidup. Quotation tidak punya "Closed" (akhirnya Won/Lost/Cancelled); CO tidak punya "Closed" sama sekali di kode; DO memakai status teknis Shipped yang di layar tampil sebagai Closed. Kedua, **CO tidak lahir otomatis saat Quotation di-Confirm**. CO lahir ketika Sales menekan WIN di Quotation, atau memilih Quotation itu di layar Customer Order (detail di bagian 3 dan 8).

## 2. Quotation: wajib terintegrasi, boleh banyak inquiry, banyak pilihan qty

**Quotation hanya boleh dibuat dari inquiry yang harganya sudah published.** Daftar "inquiry siap ditawarkan" hanya berisi inquiry yang costing-nya published, punya customer, belum dibatalkan/kalah/menjadi order, dan belum dipakai quotation lain. Tidak ada jalur membuat Quotation "bebas" tanpa inquiry; ini sesuai aturan yang Jo kunci (Quotation wajib integrated, CO boleh direct). Quotation yang dibatalkan melepas inquiry-nya sehingga boleh ditawarkan lagi; Draft yang dihapus juga mengembalikan inquiry ke daftar siap ditawarkan.

**Satu Quotation boleh memuat banyak inquiry, asal customernya sama.** Kalau Sales memilih inquiry dari customer berbeda sekaligus, sistem menolak. Kalau Sales memilih ulang dengan customer yang berbeda dari isi quotation, seluruh baris diganti dan header diisi ulang dari customer baru.

**Setiap jenjang qty (tier) dari inquiry menjadi satu baris.** Inquiry yang punya tiga jenjang (misalnya 10.000, 50.000, 200.000 pcs) menghasilkan tiga baris dengan harga per pcs dari ProCost. Sales boleh mengedit deskripsi, qty, satuan, harga, dan diskon per baris selama masih Draft. Biaya sekali bayar (pisau/matras, plat cetak, film) dipecah menjadi baris terpisah per jenis dan hanya ditambahkan sekali, yaitu saat inquiry itu pertama masuk.

Header terisi otomatis dari customer dan inquiry: masa berlaku 30 hari, deadline 14 hari (kalender) sejak tanggal dokumen, payment term, mata uang, kontak, dan Sales PIC. Skema pajak Quotation ada dua: **Exclusive** atau **Inclusive PPN**. Nomor berformat `QUO` + 2 digit tahun + 5 digit urut.

**Publish.** Tombol di layar bernama **Confirm**; di balik layar statusnya menjadi *Published*. Quotation kosong tidak bisa di-Confirm. Setelah itu Sales mencatat follow-up (telepon, WA, kunjungan) dan akhirnya:

- **WIN**: Customer Order lahir (satu Quotation = satu CO), semua inquiry di dalamnya menjadi *Converted*, dan setiap inquiry menjadi data pokok **Master Product** (lewat sinkronisasi master). Di grid, status Quotation lalu tampil *Won*.
- **LOST**: wajib isi alasan; inquiry-nya ditandai *Lost*.
- **Cancel**: hanya untuk Quotation Published yang belum Won/Lost, wajib alasan.

## 3. Customer Order: dua pintu masuk, satu dokumen

**Pintu pertama: Integrated.** CO lahir dari Quotation Published. Semua baris Quotation disalin sebagai *snapshot*: sesudahnya harga atau qty CO boleh berbeda dari Quotation bila perlu, dan Quotation tidak ikut berubah. Toleransi cetak dari tier ProCost ikut dibekukan sebagai batas qty minimum dan maksimum per baris. Flag PPN CO diambil dari inquiry asal (bila ada), kalau tidak dari profil pajak customer, dan boleh diubah selama Draft.

**Pintu kedua: Instant (direct).** CO dibuat langsung dari customer tanpa Quotation, misalnya repeat order tanpa perubahan atau percepatan dari Direksi. Syaratnya satu: **setiap baris Product harus menunjuk Master Product**, dan harga, satuan, serta toleransi diambil (di-snapshot) dari master saat baris dibuat. Jalur ini sengaja tidak dipersulit.

**Satu Quotation hanya boleh punya satu CO.** CO yang Rejected atau Cancelled tidak membuka Quotation lagi; pesanan ulang dilakukan dengan **Duplicate**, yang selalu menghasilkan CO *Instant* baru berisi salinan baris (tanpa jadwal kirim) untuk diperbaiki lalu diajukan lagi.

**Memilih satu qty (tombol Choose).** Kalau Quotation menawarkan beberapa qty untuk produk yang sama, CO Draft menampilkan tombol **Choose** per pilihan. Customer memilih satu; baris pilihan lain dibuang dari CO (jejaknya tetap ada di Quotation). Selama satu produk masih punya lebih dari satu baris qty, CO tidak bisa diajukan ke approval.

**Due date.** Setiap baris Product **wajib punya due date** sebelum CO bisa di-Confirm, dan layar menandai baris yang kosong. Aturan bisnisnya: due date = 14 hari kerja dari approval proof fisik, boleh diubah. **Catatan penting:** di kode saya tidak menemukan perhitungan otomatis itu; due date diisi tangan dengan date picker (lihat bagian 8).

**PPN / Non PPN.** Satu bendera pada CO menentukan apakah PPN dihitung. DO juga mengikuti: dalam satu DO tidak boleh mencampur CO PPN dan Non PPN.

**Qty Minim.** Baris yang ditandai Qty Minim dihargai **borongan**: nilai baris adalah harga itu sendiri, berapa pun qty-nya. Aturan yang sama berlaku di baris DO. Contoh: harga 500.000 dengan qty 3.000 tetap bernilai 500.000.

**Baris Service dan biaya luar.** CO bisa memuat baris jasa (Service), jadwal kirim manual per baris, dan biaya luar. Baris jasa dikenai PPh 23; ini dibahas di bagian rumus.

**Confirm = ajukan approval.** Ketika Sales menekan Confirm, sistem memeriksa: ada isi, semua baris Product punya due date, (untuk Instant) semua baris punya Master Product, dan tidak ada produk dengan lebih dari satu pilihan qty. Bila lolos, status menjadi *Submitted* dan masuk antrean approval **F&A SPV**, lalu **Operational Manager** (dua tingkat, diatur lewat jabatan sehingga pengganti/PLT ikut otomatis). Approver bisa **Approve**, **Reject** (wajib alasan; final, status Rejected), atau **Hold** (menunda, status tidak berubah). Hanya setelah approval terakhir lolos CO berstatus **Confirmed** dan jadwal kirim dibuat. Confirmed CO masih bisa **Cancel** (wajib alasan), tetapi hanya jika belum ada Work Order dan belum ada Delivery Order yang hidup; kalau sudah ada, dokumen itu harus dibatalkan dulu.

Draft CO jalur Instant boleh dihapus; CO jalur Integrated tidak boleh dihapus karena Quotation-nya sudah Won dan Master Product sudah lahir.

## 4. CPDEL: jadwal kirim dan dua janji

**Yang dibuat otomatis saat CO Confirmed.** Untuk setiap baris Product yang belum punya jadwal buatan tangan, sistem membuat satu baris jadwal berstatus *Planned* dengan **qty penuh pada tanggal due date**. Baris yang jadwalnya sudah diatur manual oleh Sales (misalnya sudah dipecah) tidak disentuh. CPDEL hanya menampilkan CO yang Confirmed, dan tidak menyentuh stok; urusan stok ada di Delivery Order.

**Split dan Gabung.** *Split* memecah satu baris: qty yang dipisah menjadi baris baru di tanggal baru, sisanya tinggal di baris lama (qty yang dipecah harus lebih dari nol dan kurang dari qty baris). Split tanpa qty hanya memindah tanggal. *Gabung* menyatukan dua baris atau lebih dari CO dan produk yang sama: qty dijumlahkan ke baris dengan tanggal paling awal, baris lainnya dihapus. Baris yang sudah Shipped tidak bisa diubah, dan baris yang sudah **Posted to Production** terkunci: tanggal dan qty tidak boleh berubah, hanya jam slot yang boleh disesuaikan, dan tidak boleh digabung.

**Dua janji.** Setiap jadwal kirim memegang dua janji yang harus cocok:

1. **Janji ke customer**: *slot* (tanggal dan jam kirim), dipegang Sales.
2. **Janji produksi**: *kapan barang siap*, dipegang PPIC.

Slot dianggap sah hanya bila tanggalnya hari kerja Telasih (bukan libur di Holiday Calendar), jamnya di dalam jam kantor Telasih, **dan** di dalam jam terima customer pada hari itu (jam istirahat customer tidak boleh). Janji awal ke customer tidak pernah berubah; setiap perubahan dicatat sebagai riwayat negosiasi (siapa, kapan, lewat WhatsApp/telepon/email/meeting, kontak customer, alasan).

Perjalanan status sebuah jadwal:

- **Waiting for PPIC**: PPIC belum mengisi kapan barang siap.
- **Aligned**: janji produksi tidak melewati slot; semua beres.
- **Need negotiation**: PPIC bilang barang siap *setelah* slot (wajib pilih alasan). Sales menghubungi customer, lalu mengubah slot atau memecah pengiriman (wajib alasan, kecuali saat jam slot pertama kali ditetapkan di tanggal yang sama).
- **Escalated**: customer menolak. Sales mencatat penolakan, dan **Operational Manager** memutuskan: lembur, prioritaskan produksi, subkon, atau tawaran baru. Setelah keputusan, PPIC wajib mengisi ulang janji siap (kecuali keputusan "tawaran baru").
- **Late**: tanggal slot sudah lewat dan barang belum dikirim. Status ini mengalahkan Escalated.
- **Shipped**: sudah dikirim.

**Warna jatuh tempo.** Baris grid diwarnai menurut jarak ke tanggal slot, dihitung dalam **hari kerja** (Sabtu bekerja, Minggu libur, hari besar mengikuti Holiday Calendar): merah bila tanggal sudah lewat ("Late n days"), oranye bila hari ini, kuning bila tinggal sedikit hari kerja (batas diatur lewat konstanta `CPDEL_DUE_SOON_DAYS`, bawaan 2), polos bila masih jauh, dan abu-abu bila sudah dikirim.

## 5. Post to Production dan jalan pintas DO

**Post to Production** adalah tombol pada satu baris jadwal yang berstatus Planned dan belum Posted. Saat ditekan, sistem membuat **Work Order**: **satu WO per part** dari inquiry produknya, lengkap dengan urutan proses (BOP) dan kebutuhan bahan (BOM) dari ProDev untuk qty tersebut; tanggal jatuh tempo WO mengikuti tanggal slot. Untuk produk yang tidak punya inquiry atau part (misalnya CO Instant untuk produk lama), sistem membuat **WO sederhana**, satu per baris kirim, tanpa proses dan bahan; PPIC yang melengkapinya. Syaratnya: CO sudah Confirmed dan approved, baris punya Master Product, dan baris itu belum punya WO aktif. Setelah WO lahir, baris ditandai Posted (siapa dan kapan) dan mesin penjadwalan menyiapkan rencana *Draft* (bukan jadwal yang sedang berjalan).

**Jalan pintas DO.** Delivery Order hanya bisa menarik jadwal yang **sudah Posted**. Inilah "jalan pintas" untuk pekerjaan cepat (sekitar 30 menit): begitu di-Post, barisnya langsung bisa ditarik ke DO/Surat Jalan tanpa menunggu hasil produksi diinput. Hasil produksi tetap wajib diinput kemudian karena menjadi dasar KPI semua departemen; ada laporan "Shipped Without Production Output" untuk memantaunya. **Angka 30 menit hanya konstanta rujukan (`QUICK_JOB_MINUTES`) yang ditampilkan sebagai keterangan; kode tidak memeriksa lamanya pengerjaan.** Dengan kata lain, setiap DO wajib lewat Post, bukan hanya pekerjaan cepat.

## 6. Delivery Order, Surat Jalan, bukti terima, dan tagihan

**Menyusun DO.** Petugas membuat DO untuk satu customer, lalu menarik jadwal kirim yang memenuhi syarat: CO Confirmed, sudah Posted, dan masih ada sisa qty. Satu jadwal menjadi satu baris DO (tidak pernah digabung). Qty awal baris adalah yang lebih kecil antara sisa tersedia dan qty jadwal, dan boleh diturunkan, tidak boleh melebihi sisa. Harga baris ikut CO; bila petugas mengubahnya, sistem mencatat harga asli dan menandai DO "ada perubahan harga".

**Confirm, Shipped, Cancel.** Confirm memeriksa ulang di server (ada baris, qty lebih dari nol, qty masih tersedia, CO sumber masih Confirmed), lalu memberi nomor `DO/YY/NNNNN`. **Shipped** menandai seluruh baris sekaligus; jadwal CPDEL yang total qty terkirimnya sudah mencukupi otomatis berstatus Shipped. **Cancel** dari Draft atau Confirmed wajib alasan, dan qty kembali tersedia.

**Surat Jalan** adalah DO yang Confirmed atau Shipped yang ditampilkan dan dicetak **tanpa harga**.

**Bukti terima (Proof of Delivery).** Officer pengiriman mencatat jam tiba dan hasil: Received, Partially received, Customer closed, Rejected, Not shipped, atau Accident. Janji kirim dianggap **ditepati** hanya jika hasilnya Received, truk tiba di tanggal yang dijanjikan, dan tidak melewati jam slot (bila slot berjam). Bila meleset, wajib memilih penyebab, yang menunjuk departemen penanggung jawab (dasar KPI). Untuk penerimaan sebagian, tiap baris diisi qty yang benar-benar diterima beserta alasan selisih.

**Hubungan ke invoice.** Customer Invoice hanya bisa menagih baris DO yang sudah punya **qty diterima**, sebesar qty diterima dikurangi yang sudah ditagih. Jadi yang tidak diterima tidak ditagih.

## 7. Hal yang sering membingungkan

- **"Confirm" di Quotation bukan Confirm yang sebenarnya.** Itu *Publish*. Status akhir Quotation adalah Won / Lost / Cancelled, bukan Confirmed/Closed.
- **Confirm CO belum berarti Confirmed.** Setelah Confirm, CO berstatus Submitted; Confirmed baru terjadi setelah Operational Manager menyetujui.
- **CPDEL belum ada sebelum approval selesai.** Jadwal kirim hanya lahir setelah approval terakhir lolos.
- **Posted bukan Shipped.** Posted artinya Work Order sudah dibuat dan DO boleh menarik baris itu; Shipped artinya barang sudah keluar.
- **Tiga tanggal berbeda.** Due date di baris CO adalah patokan awal; slot di CPDEL adalah janji yang bisa dinegosiasi; tanggal siap adalah komitmen produksi.
- **Satu Quotation, satu CO, selamanya.** CO yang dibatalkan tidak membuka Quotation untuk CO kedua; jalannya CO Instant (Duplicate).
- **PPN di Quotation dan di CO berbeda wujud.** Quotation memilih Exclusive/Inclusive PPN; CO memakai bendera PPN / Non PPN.
- **Harga dan HPP.** Costing rahasia; harga jual baru boleh dilihat Sales setelah published. Penyaringan harga untuk Produksi dan Purchasing di layar-layar ini tidak saya periksa.

## 8. Catatan untuk Jo: selisih dokumen vs kode, dan yang tidak bisa saya pastikan

**Selisih dokumen dan kode (kode yang saya ikuti):**

1. `claude_vital_flows.md` menulis CO dibuat otomatis begitu Quotation Published. **Di kode tidak otomatis**: CO dibuat oleh aksi WIN pada Quotation, atau saat Sales memilih Quotation Published di layar Customer Order.
2. `SALES_QUO_ORDER, CPDEL.md` menulis CO Mode #2 (tanpa Quotation) belum ada dan PPN/PPh 23 belum dikonfirmasi. **Kode sudah punya** CO Instant, dan tarif dibaca dari Settings > Constants (kunci `PPN`, `PPH23_JASA`). Dokumen itu juga masih menyebut Confirm CO ada di layar v1 (`confirmCo`); sekarang Confirm = pengajuan approval lewat `CoOrderBuilder::submit`.
3. Dokumen yang sama menyebut tombol Confirm CO disabled bila due date kosong; di kode v2 pengecekan dilakukan saat Confirm dan layar memunculkan peringatan.
4. Aturan A.16 lama (WO hanya untuk costing published) sudah dicabut; kode mengikuti versi baru (semua CO Confirmed boleh Post).

**Yang tidak bisa saya pastikan atau perlu Jo putuskan:**

- **Due date 14 hari kerja dari approval proof fisik: tidak ada di kode.** Tidak ada perhitungan atau kolom tanggal approval proof di alur CO; due date diisi tangan. Yang ada: deadline Quotation = tanggal dokumen + 14 hari *kalender*, dan default deadline Inquiry +14 hari kalender. Belum terverifikasi apakah aturan itu dijalankan manual atau ada layar lain.
- **Batas 30 menit tidak ditegakkan**, hanya keterangan. Setiap DO wajib melewati Post.
- **Baris biaya sekali bayar (pisau, plat, film) dari Quotation menjadi baris Product di CO** (hanya baris bebas yang menjadi Service), dan berpotensi (a) meminta due date, (b) membuat jadwal kirim, dan (c) ikut terhapus atau memicu "pilih satu qty" karena berbagi inquiry yang sama dengan baris tier. Ini terbaca dari kode, **belum saya uji**, dan mungkin ada penanganan di layar yang tidak saya telusuri.
- **Konfigurasi approval:** langkah F&A SPV lalu Operational Manager terbaca dari migrasi; saya tidak membuka isi tabel konfigurasi sungguhan di mockup.test maupun tmerp.test. Bahwa nilai CO tidak mengubah rute (rentang nilai tanpa batas) belum terverifikasi di data live.
- **Gabung jadwal**: baris yang menang (tanggal paling awal) mempertahankan jam slot dan janji siap miliknya sendiri; saya tidak melihat penyesuaian lain. Belum jelas apakah itu yang Jo inginkan bila jam slot berbeda.
- **Efek stok pada Shipped** tidak saya telusuri (tidak terlihat di layanan DO yang saya baca).
- Nilai konstanta di artikel ini adalah nilai bawaan dari migrasi; nilai di database live bisa sudah diubah.
- Saya tidak menjalankan pengujian; semua isi berasal dari membaca kode.

<!-- johiwan-only -->
## 9. Rumus nyata dari kode dan contoh angka (khusus johiwan)

Sumber: `QuoQuotation::totals`, `QuoQuotationItem::recalcSubtotal`, `CoOrderItem::recalcSubtotal`, `CoOrder::totals`, `TaxRate`, `CoOrderBuilder`, `CpdelPromiseService`, `WorkCalendar`, `DeliveryPromiseService`. Angka contoh saya hitung sendiri; tarif PPN 11 dan PPh 23 2 adalah nilai bawaan migrasi (konstanta `PPN`, `PPH23_JASA`), bisa berbeda di data live.

**Baris Quotation**
- `subtotal_baris = round(qty × harga_satuan × (1 − diskon_pct/100), 2)`
- Baris biaya sekali bayar: `qty` = jumlah item tooling, `harga_satuan = total_biaya ÷ jumlah item`, jadi `subtotal = total_biaya`.
- Contoh: 10.000 pcs × 1.250 dengan diskon 5% = 12.500.000 × 0,95 = **11.875.000**.

**Total Quotation** (`subtotal = jumlah subtotal baris`; tidak ada diskon tingkat dokumen)
- Exclusive: `dpp = subtotal`; `ppn = round(dpp × ppn_pct/100, 2)`; `grand = dpp + ppn`.
- Inclusive: `dpp = round(subtotal ÷ (1 + ppn_pct/100), 2)`; `ppn = round(subtotal − dpp, 2)`; `grand = subtotal`.
- Contoh subtotal 15.500.000: Exclusive PPN = 1.705.000, grand = **17.205.000**. Inclusive: dpp = 13.963.963,96; ppn = 1.536.036,04; grand = **15.500.000**.

**Baris CO**
- Biasa: `subtotal_baris = round(qty × harga × (1 − diskon_pct/100), 2)`.
- Qty Minim (borongan): `subtotal_baris = round(harga × (1 − diskon_pct/100), 2)` (qty diabaikan). Baris DO memakai `round(harga, 2)` bila Qty Minim, selain itu `round(qty_do × harga, 2)`.
- Toleransi (CO Integrated, bila `tolerance_pct` > 0): `qty_min = round(qty × (1 − tol/100), 2)`; `qty_max = round(qty × (1 + tol/100), 2)`. Contoh qty 10.000, toleransi 10%: 9.000 sampai 11.000.

**Total CO** (`CoOrder::totals`)
1. `item_subtotal = Σ subtotal baris Product + Σ subtotal baris Service`
2. `diskon = round(item_subtotal × (diskon_dokumen_pct + diskon_qty_pct) / 100, 2)` (kedua persen dijumlah, bukan berantai)
3. `dpp = item_subtotal − diskon + biaya_luar`
4. `ppn = round(dpp × ppn_pct / 100, 2)` bila bendera PPN aktif, selain itu 0
5. `total_tagihan = dpp + ppn`
6. `pph23 = round(Σ subtotal baris Service × pph23_pct / 100, 2)` (dari baris Service sebelum diskon dokumen; tidak dihitung dari DPP)
7. `net_receivable = total_tagihan − pph23`

Contoh: baris Product 10.000 × 1.250 = 12.500.000; baris Service 1 × 2.000.000; item_subtotal = 14.500.000. Diskon dokumen 2% + diskon qty 1% = 3% menjadi 435.000. Biaya luar 300.000. dpp = 14.500.000 − 435.000 + 300.000 = **14.365.000**. PPN 11% = **1.580.150**. total_tagihan = **15.945.150**. PPh 23 = 2% × 2.000.000 = **40.000**. net_receivable = **15.905.150**.

**Nilai untuk approval.** Yang dikirim ke Flow Engine saat Confirm dan dipakai saat approve/reject/hold adalah `net_receivable` (contoh: 15.905.150), bukan total tagihan. Langkah approval CO terbaca sebagai F&A SPV lalu Operational Manager dengan `min_amount = 0` dan `max_amount` kosong (dari migrasi). Pemilihan langkah: `status_from` cocok dan `min_amount ≤ nilai ≤ max_amount` (batas kosong = tak terbatas).

**Diskon di CO Integrated.** `diskon_dokumen_pct` dan `diskon_qty_pct` diambil dari inquiry asal (`discount_doc_value`, `discount_qty_value`); di layar CO v2 yang bisa diedit hanya diskon qty, diskon dokumen tidak ada di daftar field header.

**Jadwal kirim awal (CPDEL otomatis)**
- Per baris Product tanpa jadwal: `ship_date = due_date baris`, `ship_qty = qty baris`, `shipment_type = FULL`, status Planned.
- Split dengan qty `s`: baris baru `ship_qty = s`, baris lama `ship_qty = lama − s`, syarat `0 < s < lama`.
- Gabung: `ship_qty_baris_menang = Σ ship_qty`; baris menang = tanggal paling awal.
- Slot tanpa jam dihitung sebagai pukul 23:59:00 hari itu.
- Selisih janji: `gap_hari = tanggal_slot ke tanggal_siap` dalam hari kalender; positif = produksi terlambat.
- Urutan penentuan status: Shipped; lalu Late bila `ship_date < hari ini`; lalu Escalated bila `escalated_at` terisi; lalu Waiting for PPIC bila `ready_at` kosong; lalu Need negotiation bila `ready_at > slot` (tanggal dan jam); selain itu Aligned.

**Hari kerja dan warna jatuh tempo**
- Hari kerja = bukan hari dengan `is_workday = 0` di Office Hours (cadangan: hanya Minggu bila tabel kosong) dan bukan tanggal di Holiday Calendar.
- `hari_kerja_antara(a, b)` = jumlah hari kerja setelah `a` sampai dengan `b` (a sendiri tidak dihitung). Contoh Jumat ke Senin = 2 (Sabtu dan Senin).
- Terlambat: `n = max(1, hari_kerja_antara(slot, hari ini))`, tampil "Late n days", merah.
- Hari ini: oranye. Mendatang: `n = max(1, hari_kerja_antara(hari ini, slot))`; kuning bila `n ≤ CPDEL_DUE_SOON_DAYS` (bawaan 2), selain itu polos dengan "n days left". Penanda "minggu ini" = slot paling lambat hari ini + 6 hari kalender.
- Due date CO "14 hari kerja dari approval proof": **tidak ada rumusnya di alur CO**. Satu-satunya penambah hari kerja yang saya lihat ada di mesin perencanaan bahan (`addWorkdays`: maju satu hari, hitung hanya bila hari kerja, sampai n tercapai), dan tidak dipakai untuk mengisi due date CO. Contoh bila aturan itu diterapkan dengan kalender yang sama: approval Senin 5 Oktober 2026, 14 hari kerja (Sabtu kerja, Minggu libur, tanpa hari besar) jatuh pada Rabu 21 Oktober 2026. Ini hitungan ilustrasi saya, bukan keluaran kode.
- Deadline Quotation = `tanggal_dokumen + 14 hari kalender`; berlaku sampai = `tanggal_dokumen + 30 hari kalender`.

**Janji produksi dan slot**
- Slot sah bila: tanggal bukan libur dan hari kerja kantor, jam di antara jam mulai dan jam selesai kantor (bila jam diisi), hari itu customer buka, dan jam di dalam jam terima customer di luar jam istirahatnya. Aturan jam customer: `buka ≤ jam ≤ tutup` dan bukan `istirahat_mulai ≤ jam < istirahat_selesai`.
- Perlu alasan: PPIC mengisi `ready > slot`; Sales mengubah tanggal atau memecah (kecuali saat jam slot pertama kali ditetapkan di tanggal yang sama).

**Work Order dari jadwal**
- Per part: `qty_WO = ship_qty × qty_per_sku` (qty_per_sku kosong dihitung 1); `due_date WO = ship_date`; status WO awal Draft. Contoh ship_qty 10.000 dengan 2 set per SKU = WO 20.000 untuk part tersebut.

**Delivery Order**
- `qty_do_awal = min(qty_tersedia, ship_qty)`; `qty_tersedia = ship_qty − Σ qty_do baris DO berstatus Planned/Shipped pada jadwal itu`. Batas edit: `qty_do ≤ qty_tersedia + qty_do_baris_ini`.
- Jadwal CPDEL jadi Shipped bila `Σ qty_do baris Shipped ≥ ship_qty`.
- Nomor: `DO/` + 2 digit tahun + `/` + 5 digit urut.

**Bukti terima dan tagih**
- Janji ditepati = (hasil = Received) dan (tanggal tiba = tanggal janji) dan (tanpa jam janji, atau jam tiba ≤ jam janji).
- Qty diterima baris (hasil Partial): `qty = max(qty_sudah_ditagih, min(qty_do, max(0, qty_ketik)))`; hasil Received: `qty = qty_do` (juga dibatasi di bawah oleh qty_sudah_ditagih).
- Sisa yang boleh ditagih = `qty_diterima − qty_sudah_ditagih`; baris tanpa qty diterima tidak bisa ditagih. Contoh DO 1.000, diterima 960 (rusak 40): tagih 960; bila sudah ditagih 500, sisa 460.
