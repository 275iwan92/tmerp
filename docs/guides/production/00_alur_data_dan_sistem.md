# Alur Data dan Alur Sistem Production/PPIC: ke mana informasi mengalir, dan mengapa

*Panduan untuk Jo. Ditulis dari membaca kode pada 16 Oktober 2026, bukan dari dokumen lama. Tanpa rumus: rumus ada di tiga panduan pendamping (`01_work_order_bom_gates.md`, `02_planning_scheduling.md`, `03_learning_timer_crew.md`). Hal yang tidak terbukti di kode ditandai "belum terverifikasi" dan dikumpulkan di bagian 7 dan 8. Nama layar dan tabel di dalam kurung hanya petunjuk.*

Tiga panduan pendamping menjelaskan tiap layar. Panduan ini menjawab pertanyaan lain: **kalau satu CPDEL di-Post ke Produksi, informasi apa yang lahir, ke mana ia pergi, siapa yang memakainya, dan apa yang terjadi di ujung rantai**. Pegangan utamanya satu prinsip dari CLAUDE.md bagian 2: hulu (Inquiry, ProDev, ProCost, CO) adalah pemilik data; PPIC hanya mewarisi dan melanjutkan, tidak menghitung ulang logika hulu.

## 1. Gambaran satu halaman

Urutan besar: **CPDEL (Post to Production) → Work Order → bahan (Available, Release, Purchase) → jadwal Draft → Apply → antrean operator → timer dan hasil → ... → DO → penagihan**. Tanda "...": di titik itu rantai tidak tersambung otomatis menurut kode (bagian 7).

| Dari | Ke | Yang mengalir | Salin / tautan | Alasan |
|---|---|---|---|---|
| CPDEL (`co_order_delivery`) | Work Order (`prd_wo`) | Qty kirim, tanggal kirim sebagai jatuh tempo, produk, part | Disalin sebagai nilai awal; WO menunjuk baris CPDEL dan CO asalnya (`source_delivery_id`, `source_co_id`) | WO harus tahu dari mana ia lahir agar bisa dilacak dan dibatalkan; qty dan tanggal dibekukan karena baris Posted tidak boleh berubah lagi |
| ProDev (BOP, BOM, layout) | Operasi dan bahan WO (`prd_wo_operation`, `prd_wo_material`) | Daftar proses, waste, toleransi, kebutuhan bahan untuk qty WO | **Disalin** (snapshot) saat WO lahir dan saat PROCESS | Produksi tidak boleh bergeser hanya karena master hulu diubah di tengah jalan; PPIC boleh menyesuaikan salinannya dengan alasan |
| Master Card | Form WO | Dieline, artwork, ukuran | **Ditautkan** (dibaca, tidak diketik ulang) | Satu sumber kebenaran; Master Card lahir di ProDev |
| Stok gudang dan booking | Kolom Available di BOM | On hand dikurangi booking terbuka | **Dihitung tiap dibuka**, tidak disimpan sebagai fakta | Angka stok berubah tiap jam; menyimpannya membuat angka basi |
| Release dan Purchase di BOM | Gate bahan | Angka yang diputuskan PPIC | Disimpan sebagai timpaan beralasan di WO | Keputusan manusia harus terlacak (siapa, angka lama, angka baru) |
| WO yang lolos gate | Jadwal Draft (Auto-Planner dan Solver) | Mesin, jam mulai, jam selesai, setup | Ditulis ke kolom Draft di operasi; jadwal berlaku terpisah | Engine menyarankan, petugas memutuskan (A.7) |
| Draft, saat Apply | Jadwal berlaku dan terkunci | Mesin dan waktu nyata rencana | **Disalin** dari kolom Draft ke kolom berlaku | Setelah Apply, pekerjaan operator tidak boleh tiba-tiba berubah |
| Jadwal berlaku | Layar operator (Production Output) | Daftar job per mesin dan proses | Dibaca; hanya operasi Confirmed yang muncul | Operator tidak melihat usulan, hanya yang sudah diputuskan PPIC |
| Operator (timer, Simpan Hasil) | Waktu dan hasil operasi | Menit setup/jalan/jeda, Good, Bad per alasan, log aksi | Ditulis baru, ditambahkan, tidak menimpa | Rekaman nyata jadi dasar progres, KPI, dan pembelajaran |
| Pick list gudang | Kartu stok bahan | Pengeluaran bahan ke WO | Dokumen Stock Out ke WO, lalu kartu stok | Saldo hanya boleh berubah lewat dokumen yang diposting |
| DO dan POD | Penagihan | Qty diterima customer | Baris DO ditagih sebesar qty diterima | Customer ditagih atas barang yang benar-benar diterima |

## 2. Alur data bahan

**Apa yang mengalir.** Kebutuhan bahan lahir di ProDev (BOM, dihitung mundur dari qty order dengan waste dan toleransi). Saat WO lahir, kebutuhan itu **disalin** ke baris bahan WO (`prd_wo_material`) sebagai kolom *Required*. Dari situ ada empat angka di Form WO:

1. **On hand**: stok fisik yang boleh dipakai (`v_rm_available`).
2. **Available**: On hand dikurangi sisa booking yang masih terbuka dari semua tahap (`AvailableStock`).
3. **Release**: yang dilepas ke WO ini; otomatis sama dengan Available, boleh diubah PPIC.
4. **Purchase**: kekurangan, yaitu Required dikurangi Release.

Angka 1 dan 2 **dibaca langsung** dari gudang dan booking; angka 3 dan 4 baru disimpan bila PPIC mengubahnya (disertai alasan). Dari sini lahir **gate material** pada operasi pertama WO (Ready, Waiting, Blocked), yang menentukan boleh tidaknya Apply (`GateService`).

**Dari bahan ke kartu stok.** Bahan baru benar-benar "keluar" lewat jalur gudang, bukan lewat Form WO: engine Material Planning menulis booking terkunci (`rm_booking`, `rm_booking_d`); gudang membuat **pick list per WO** dari booking yang Confirmed (`wh_pick_list`); petugas memindai palet dan bin; saat Confirm, sistem membuat dokumen **Stock Out RM ke WO** dan memostingnya lewat layanan posting stok. Posting itu menulis **kartu stok** (`rm_stockcard`), memperbarui saldo, dan untuk Stock Out sungguhan membuat **jurnal pemakaian** otomatis (Dr HPP, Cr Persediaan, kode jurnal USG). Terakhir, jumlah keluar mengisi `qty_issued` pada booking dan palet dikurangi.

**Kenapa Release tidak mereservasi stok.** Keputusan Jo 13 Oktober: reservasi adalah urusan engine ProCost (Material Planning), bukan layar PPIC. Alasan bisnisnya: PPIC melihat satu WO, sedangkan reservasi harus adil antar banyak CO (Confirmed lebih dulu, lalu Draft, lalu Forecast, tiap tingkat menurut tanggal butuh). Bila Form WO ikut mereservasi, dua tempat memutuskan hal yang sama dan bisa saling bertabrakan. Harga yang dibayar: dua WO yang memakai bahan sama bisa sama-sama melepas stok yang sama (ENGINE-ATTENTION-03, bagian 7).

**Kenapa booking dimulai sejak costing.** Aturan A.26 (Jo 8 Oktober): begitu ProCost selesai menghitung, sistem membuat *pre-booking* lunak per inquiry (tahap B0, lalu B1 saat Quotation terbit, berlabel "Not CO yet"), menjadi B2 saat CO dibuat dan B3 saat CO Confirmed. Tujuannya mencegah dua penawaran menjanjikan stok yang sama kepada dua customer, jauh sebelum WO ada. Karena semua booking terbuka mengurangi Available, Available di Form WO bisa lebih kecil daripada isi rak; itu disengaja. Booking yang tidak dipakai tidak menggantung selamanya: Hold berakhir sendiri, dan booking Confirmed yang tidak pernah menjadi WO diberi tanda basi lalu dilepas otomatis (`inventory:booking-maintain`, tiap hari 01.45).

## 3. Alur data jadwal

Urutannya: **WO → antrean → Simulation → Apply → Live → operator timer → progres**.

- **WO dan antrean.** Setiap WO baru langsung dilengkapi (Work Order Enrichment): mesin yang mampu per proses, target per jam, setup, ukuran lembar. Lalu Auto-Planner menaruhnya di antrean bersama semua WO terbuka. Pemicunya otomatis (tiap malam 02.00, tiap awal shift, WO baru, mesin rusak atau pulih, geseran petugas) atau manual lewat PROCESS.
- **Simulation (Draft).** Solver mengurutkan WO, memilih mesin yang selesai paling awal, dan menulis hasilnya sebagai **Draft** pada operasi. Draft boleh digeser, ditukar, dibuang, dan dihitung ulang kapan saja. Draft **tidak pernah menjadi jadwal berlaku sendirian**.
- **Apply (Live).** Tombol APPLY TO LIVE SCHEDULE menyalin Draft ke kolom berlaku, menandai operasi Confirmed, dan **mengunci** seluruh operasi WO itu. Status WO menjadi SCHEDULED (tampil APPLIED). Saat Apply, engine menghitung ulang terhadap jadwal yang berlaku *sekarang*, karena WO lain mungkin sudah di-Apply sejak Draft dibuat.
- **Operator timer.** Hanya operasi Confirmed yang muncul di layar operator. Play, Pause, Stop menulis satu baris waktu per segmen (SETUP, RUN, PAUSE), dan Stop produksi menandai operasi DONE serta mengisi waktu selesai nyata. Simpan Hasil menambahkan Good dan Bad (dipecah per alasan reject).
- **Progres.** Persen progres di Gantt dan Live Schedule adalah Good tersimpan dibanding qty keluaran rencana. Satu sumber, jadi layar mana pun menampilkan persen yang sama.

**Apa yang terkunci dan mengapa.** WO yang sudah Applied tidak bisa digeser, dihapus, atau diubah angka bahannya; harus Undo Confirm dulu, dan Undo ditolak bila ada operasi yang sudah mulai atau ada Confirm yang lebih baru. Alasannya: begitu operator dan gudang bertindak atas jadwal itu (bahan diambil, mesin disiapkan), mengubahnya diam-diam akan menyesatkan lantai produksi. Aturan Jo 14 Oktober melengkapinya: selama belum Apply, petugas bebas menukar semua WO; dan WO bermasalah (bahan terlambat, tooling terblokir) **tidak boleh menahan mesinnya**, jadi engine mengusulkan pekerjaan siap lain ke slot itu.

**Rekaman nyata kembali menjadi pembelajaran.** Ada empat jalur umpan balik, semuanya hanya menyarankan:

1. Susunan urutan petugas (saat PROCESS dan Apply) → Job Order Learning menyarankan urutan serupa (butuh 30 pasangan keputusan).
2. Menit setup nyata dari timer → Learning Engine mengoreksi nilai changeover "Assumed" pelan-pelan (tiap malam 03.30).
3. Output nyata per mesin dan proses → Speed Target Engine mengusulkan target kecepatan (tiap hari 03.00), berstatus PENDING.
4. Good dan defect nyata → UsageRateEngine di ProDev mengusulkan takaran bahan dan persen waste.

Mengapa semuanya "usul": data master hanya diubah pengguna; engine hanya menyentuh transaksi dan usulan. Jalur 2, 3, dan 4 punya hambatan di kode (bagian 7).

## 4. Alur sistem

**Status WO yang terlihat di kode.** DRAFT (atau SIMULATED) → SCHEDULED (tampil APPLIED) → CANCELLED. Model WO juga mengenal IN_PROGRESS, ON_HOLD, dan COMPLETED, tetapi saya tidak menemukan kode aplikasi yang memindahkan WO ke status itu (bagian 7). Status operasi berjalan sendiri: PENDING → ASSIGNED → RUNNING → DONE (diatur timer).

**Siapa memutuskan apa.**

| Keputusan | Siapa | Catatan |
|---|---|---|
| Post to Production, Create WO, PROCESS, Apply, Undo, prioritas, geser | PPIC (flag PPIC Scheduling) | Tanpa flag, server menolak |
| Release dan Purchase berbeda dari hitungan engine | PPIC, wajib satu alasan | Dicatat siapa, angka lama, angka baru |
| Hot job dan Move up | PPIC, wajib alasan | Hot job boleh menembus kunci; Move up tidak |
| Menerima saran urutan atau target kecepatan | PPIC | Dismiss dicatat sebagai penolakan |
| Play, Pause, Stop, Simpan Hasil | Operator | Satu timer jalan per mesin |
| Pick dan scan bahan | Petugas gudang | Tidak boleh percaya saran alokasi tanpa memindai |
| Nama crew per mesin per shift | Supervisor atau PPIC | Hanya diperiksa aturannya, belum memengaruhi jadwal |

**Engine di tiap titik (hanya yang terdaftar di `EngineRegistry`).**

| Titik | Engine |
|---|---|
| Post to Production | Work Order Generation (`wo-generation`) |
| WO baru dilengkapi | Work Order Enrichment (`ppic-wo-enrich`) |
| Booking sejak costing, netting, PR/PO | Material Planning (`material-planning`); pembersihan oleh Booking Maintenance (`booking-maintenance`) |
| Kesiapan material, tooling, artwork | Readiness Gates (`ppic-gates`) |
| Menyusun Draft | PPIC Auto-Planner (`ppic-auto-plan`) memakai Schedule Solver (`ppic-schedule-solver`) dan Setup Time Engine (`ppic-setup-time`) |
| Apply, Undo, prioritas, geser | Plan Confirm and Change (`ppic-plan-control`) |
| Saran urutan | Job Order Learning (`sequence-learning`) |
| Timer dan hasil di mesin | Operator Timer (`ppic-operator-timer`) |
| Koreksi changeover | Learning Engine (`ppic-learning`) |
| Usul target kecepatan | Speed Target Engine (`speed-target`) |
| Pengambilan bahan | Pick List Service (`pick-list`), Stock Document Posting (`stock-posting`), Batch Service (`batch`) |

## 5. Hasil produksi, limbah, dan efeknya

**Apa yang tercatat.** Tiap Simpan Hasil menulis satu baris hasil (`prd_wo_operation_output`, qty Good) dan, bila ada Bad, satu baris limbah per alasan reject (`prd_wo_operation_output_defect`, merujuk jenis limbah di `prd_scrap_type`). Setiap tekan tombol juga masuk log aksi (`prd_output_action_log`) lengkap dengan waktu, orang, dan angka sebelum/sesudah. Angka **ditambahkan**, tidak menimpa, jadi riwayat utuh dan koreksi tidak menghapus jejak.

**Siapa membaca hasil itu (terbukti di kode).**

- **Progres dan prakiraan selesai** di Gantt dan Live Schedule.
- **KPI**: papan target lawan hasil per hari (`Numbers::production`), widget Production Output dan tren, serta OEE mesin (`MachineOeeService`).
- **Target kecepatan** (`SpeedDataRepository`, `RateBandResolver`) dan **meter perawatan** (`MeterService`, jam atau qty mesin).
- **Deteksi kurang kirim** bahan (`ShortageAutoDetector`): membandingkan bahan yang keluar dari gudang dengan hasil baik operasi terakhir plus waste standar. Ia hanya jalan bila operasi terakhir DONE atau WO COMPLETED dan satuan bahan sama dengan satuan gudang.
- **Belajar waste**: UsageRateEngine memakai Good dan defect untuk mengusulkan persen waste per proses.

**Mengapa hasil wajib dicatat (A.3).** Hasil produksi adalah acuan KPI semua departemen. Karena DO boleh dibuat lewat *jalan pintas* untuk pekerjaan cepat (sekitar 30 menit; batasnya bukan angka mati), sistem tidak bisa memblokir pengiriman berdasarkan perkiraan. Sebagai penyeimbang, catatan hasil tetap wajib walaupun barang sudah terkirim, dan seharusnya ada laporan *Shipped Without Production Output* untuk menagih yang terlewat (lihat bagian 7: layarnya belum saya temukan).

**Jalan pintas DO dan penagihan.** Gerbang DO hanyalah bahwa baris CPDEL sudah **Posted** dan CO masih Confirmed (`DeliveryOrder::postedIds`, `DispatchService::confirm`). DO tidak memeriksa hasil produksi sama sekali: itulah arti jalan pintas. Penagihan berikutnya membaca baris DO yang sudah diterima customer (POD) dan belum ditagih, dengan harga dari CO (`CustomerInvoiceService::billable`); Confirm invoice menjurnal Piutang, Penjualan, dan PPN. Jadi **produksi tidak mengalirkan angka ke invoice**; yang mengalir ke invoice adalah qty diterima customer.

## 6. Bila hulu berubah

| Perubahan | Yang ikut bergerak | Yang tidak |
|---|---|---|
| **Baris CPDEL sudah Posted** | Tidak ada: tanggal dan qty dikunci ("Posted to Production... can no longer change here"), dan baris Posted tidak bisa digabung | WO tidak mengikuti perubahan karena perubahan memang ditolak |
| **CO ingin dibatalkan** | Ditolak selama ada WO hidup atau DO hidup; WO dan DO harus dibatalkan dulu | CO tidak bisa "direvisi diam-diam" di bawah WO yang berjalan |
| **WO dihapus/dibatalkan** (hanya yang belum terkunci, wajib alasan) | Rencana dikosongkan, baris CPDEL kembali tersedia (`posted_at` dikosongkan) bila tidak ada WO hidup lain | Operasi yang sudah Confirmed atau sudah punya hasil tidak bisa dihapus |
| **Master hulu berubah (BOP/BOM di ProDev)** | Hanya saat PPIC menekan PROCESS (operasi dan bahan dihitung ulang; edit PPIC dipertahankan) | Salinan WO yang sudah ada tidak berubah sendiri |
| **Due date bergeser** | Jatuh tempo WO diambil dari tanggal kirim saat WO lahir; karena baris Posted terkunci, pergeseran lewat CPDEL tidak mungkin. Solver membaca due date WO untuk peringkat dan tanda terlambat | Belum terverifikasi: jalur lain yang mengubah `prd_wo.due_date` setelah WO lahir |
| **Bahan terlambat** | Gate menjadi Waiting atau Blocked saat gate dihitung ulang (PROCESS, tiap malam, tiap shift, dan Material Planning 01.30). WO belum-Apply bergeser; operasi Confirmed yang gate-nya bermasalah tidak menahan mesin, dan pekerjaan siap ditarik maju | Operasi yang sudah berjalan tidak dihentikan |
| **Mesin rusak** | Perubahan downtime memicu perencanaan ulang; pekerjaan Confirmed di mesin itu diusulkan pindah di Draft dengan alasan | Jadwal berlaku tidak diubah sebelum petugas memutuskan |
| **Booking basi** | Dilepas otomatis setelah masa tunggu, stok kembali Available | Booking yang sudah ada qty keluar atau sudah punya WO tidak pernah dilepas |

Pola yang sama di semua baris: hulu yang sudah dibekukan ditolak perubahannya, dan hulu yang boleh berubah hanya menyentuh WO lewat tombol petugas (PROCESS) atau lewat Draft yang menunggu keputusan.

## 7. Di mana alur ini putus atau belum tersambung

Ini daftar jujur celah menurut kode. Tidak ada yang saya ubah.

1. **Hasil produksi tidak menjadi stok barang jadi.** `OperatorTimerService::saveResult` hanya menulis baris hasil dan defect. Tidak ada kode yang membuat dokumen stok FG atau SFG dari hasil operasi (stok FG hanya dibaca oleh `FgAvailability`, kartu stok, dan transfer). Akibatnya, hasil produksi dan saldo gudang barang jadi bisa berselisih sampai ada jalur lain. Belum terverifikasi: apakah penerimaan FG dari produksi dicatat manual lewat layar Stock In.
2. **Status WO tidak pernah maju ke IN_PROGRESS atau COMPLETED lewat kode aplikasi.** Setelah SCHEDULED, hanya status operasi yang berubah (`OperatorTimerService::finish`); `PrdWo::transitionTo` tidak dipanggil dari mana pun di `app/`. Dampaknya berantai: UsageRateEngine hanya membaca WO berstatus COMPLETED (`UsageRateEngine.php:120`) sehingga tidak ada bahan belajar waste/takaran; papan "WO terlambat" memasukkan semua WO yang bukan COMPLETED atau CANCELLED; Pick List menyaring WO berdasarkan status akhir. Belum terverifikasi: apakah ada tombol atau job di luar `app/`.
3. **Laporan "Shipped Without Production Output"** terdaftar di menu (`PROD_RPT_NO_OUTPUT`, target `v2:prod-no-output`) dan tertulis di `build.json` serta teks bantuan, tetapi saya tidak menemukan layar, rute, atau view dengan nama itu. Jadi penyeimbang jalan pintas A.3 belum teruji bisa dibuka.
4. **Nilai changeover hasil belajar belum memengaruhi jadwal**, dan sampel setup mungkin tidak pernah terisi (`change_kind` tidak ditulis timer). Lihat `03_learning_timer_crew.md` bagian 8, butir 1 dan 2.
5. **Usulan target kecepatan belum punya layar keputusan** (accept/reject tidak dipanggil dari UI), sehingga PENDING menumpuk (butir 7 di panduan 03).
6. **Dua "crew" tidak tersambung**: Assign Crew (`prod_machine_crew`) tidak menentukan siapa melihat job di layar operator (`prd_crew_assignment`); solver tidak mengecek crew (ENGINE-ATTENTION-07).
7. **Release tidak mereservasi dan Purchase tidak membuat PR** (ENGINE-ATTENTION-03 dan 05). Dua WO bisa melepas stok yang sama; kekurangan hanya muncul sebagai gate Waiting.
8. **Dua definisi Available** (ENGINE-ATTENTION-02) dan **satuan tidak dikonversi** (04): Material Planning dan ProDev memakai angka view lama, Form WO memakai angka baru.
9. **Gate artwork selalu Ready**, bahan Required 0 tidak menahan, tooling tak terdaftar dianggap Ready (ENGINE-ATTENTION-06).
10. **Tautan booking ke WO.** Pick list mencari booking lewat `wo_id` atau CO sumber. Saya tidak menemukan kode yang mengisi `rm_booking.wo_id`; pick list jalan lewat cadangan "booking CO sumber". Belum terverifikasi di data.
11. **Alasan jeda selalu OTHER** dan **yield belum dihitung** di timer (panduan 03, butir 9 dan 10): data jeda belum bisa dianalisis per penyebab.
12. **Operasi tanpa mesin tidak memakai kapasitas** dan pembagian satu proses ke beberapa mesin belum ada (ENGINE-ATTENTION-07).

## 8. Catatan untuk Jo

**Yang tidak bisa saya pastikan (belum terverifikasi).**

- **Isi data nyata.** Saya tidak membaca database: tidak tahu apakah ada booking ber-qty, apakah master tooling terisi, atau berapa WO yang sudah punya hasil. ENGINE-ATTENTION menyebut 0 booking ber-qty di ddt* per 13 Oktober.
- **Jalur di luar `app/`.** Penerimaan FG dari produksi (celah 1) dan penutupan WO (celah 2) mungkin dikerjakan lewat layar atau perintah yang tidak saya telusuri; pencarian saya hanya mencakup `app/`, `routes/`, dan migrasi.
- **Perilaku di UI.** Saya membaca kode, tidak menjalankan layar. Ketersambungan menu ke layar (terutama laporan Shipped Without Production Output) perlu dilihat di browser.
- **Nilai konstanta live** (hari Hold booking, hari basi, jumlah shift terkunci) bisa sudah diubah di Settings > Constants; angka di panduan lain adalah bawaan.
- **Siapa pemilik penutupan WO.** Saya tidak tahu apakah Jo menginginkan WO ditutup otomatis saat operasi terakhir DONE, atau manual oleh PPIC. Itu keputusan bisnis, bukan sesuatu yang bisa saya simpulkan dari kode.

**Selisih dokumen dan kode yang menonjol.**

- Registry Operator Timer menulis "yield = good / (good + bad)"; rumus itu tidak ada di kode timer.
- Aturan A.3 menulis laporan Shipped Without Production Output "sudah dibangun"; yang saya temukan baru menu dan migrasinya.

**Tiga keputusan yang paling menentukan arah rantai ini (usulan untuk didiskusikan, bukan keputusan saya):** (1) siapa dan kapan hasil produksi menjadi stok FG dan menutup WO; (2) apakah booking harus ikut berubah saat Release diisi (menutup celah dua WO satu stok); (3) apakah laporan jalan pintas harus dibangun sebelum UAT, mengingat A.3 mengandalkannya sebagai pengaman.
