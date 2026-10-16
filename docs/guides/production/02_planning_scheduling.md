# Planning dan Scheduling PPIC: panduan untuk Jo

Panduan ini dibuat dari membaca kode pada 2026-10-16, bukan dari dokumen lama. Bila dokumen berbeda dengan kode, yang ditulis di sini adalah kode, dan selisihnya dicatat di bagian "Catatan untuk Jo". Nama tabel dan kelas ditulis dalam kurung kecil hanya sebagai petunjuk. Bagian setelah penanda `johiwan-only` di akhir berisi rumus; bagian sebelumnya sengaja tanpa rumus.

## 1. Untuk apa layar Planning dan siapa yang memakainya

Planning adalah tempat Work Order (WO) yang sudah lahir dari Post to Production diberi jadwal: proses apa, di mesin mana, mulai kapan, selesai kapan. Hulunya tidak diubah di sini. Daftar proses (BOP) dan daftar bahan (BOM) datang dari ProDev; layar Planning hanya mewarisinya dan menjadwalkannya.

Pemakainya adalah petugas PPIC. Siapa pun yang punya akses Production boleh melihat. Mengubah rencana (menjalankan engine, menggeser, memberi prioritas, Apply, Undo) hanya boleh bagi pemegang flag akses **PPIC Scheduling**; admin otomatis memilikinya (`PpicAccess`). Tanpa flag itu, tombolnya ditolak server.

Menu Planning punya beberapa layar:

- **Work Order**: grid WO, lalu Form WO berisi Master Card, Bill of Process, Bill of Material, Simulation, dan QUE. Di sinilah petugas bekerja sehari-hari.
- **Live Schedule**: pantauan lihat-saja atas jadwal yang sudah berlaku (jumlah operasi, yang sedang jalan, terlambat, rincian rencana lawan aktual). Tidak ada tombol ubah.
- **Assign Crew**: crew per mesin per shift.
- PPIC Workspace (papan lama), Colour Sequence, Material Planning, dan Learning Changes ada di daftar layar tetapi ditandai disembunyikan di `docs/SCREEN-INDEX.md`.

Prinsipnya dari CLAUDE.md: scheduling harus secepat dan seakurat mungkin. Secepat berarti klik seminimal mungkin dan dampak terlihat seketika. Seakurat berarti angka dari data nyata, bentrok dicek di server, dan waktu serta persen berasal dari satu sumber.

## 2. Alur langkah demi langkah

**Dua jenis jadwal.** Jadwal dibedakan oleh status. **DRAFT** adalah usulan: bebas digeser, ditukar, dihitung ulang kapan saja. **LIVE** (di layar disebut Confirmed/Applied) adalah jadwal yang berlaku: terkunci dan tidak disentuh engine lagi. Aturan Jo 14 Okt: petugas boleh menukar semua WO yang belum di-Apply dengan bebas di Simulation; WO yang bermasalah tidak boleh menahan mesinnya.

**Alur harian satu WO:**

1. **Buka WO dari grid.** Form WO berbentuk accordion. Bagian Master Card, Bill of Process, dan Bill of Material sudah terisi dari ProDev. Petugas boleh mengubah target, crew/subcon, Required, Release, urutan proses, atau prioritas; semua perubahan itu **tertunda** (pending) sampai PROCESS ditekan, dan masih bisa dibatalkan dengan Discard.
2. **Simulation.** Gantt memperlihatkan campuran semua operasi yang belum Confirmed, dan operasi yang sudah Applied tampil bergaris dan terkunci. Setiap bar menyebut WO, customer, produk, jumlah, proses, mesin, status Draft/Live, waktu, dan persen.
3. **Memindah atau menukar.** Petugas menyeret bar ke mesin atau jam lain dan wajib menulis alasan. Sebelum apa pun disimpan, panel dampak menunjukkan: mulai dan selesai baru, setup dan lama proses, berapa operasi lain yang ikut bergeser (hingga enam ditampilkan, sisanya disebut "more"), dan WO mana yang jadi terlambat. Posisi lama digambar sebagai hantu. Tombol "Add to pending changes" menyimpannya sebagai perubahan tertunda.
4. **Penolakan di tahap geser.** Server menolak bila: WO sudah Applied ("Applied WO is locked; Undo Confirm first"), operasi sedang jalan atau sudah selesai, operasi terkunci tanpa status Hot job, jam mulai sudah lewat, mesin tidak mampu menjalankan proses itu, atau bahan baru datang setelah jam mulai.
5. **PROCESS.** Satu tombol menjalankan rantai penuh sekali: perubahan tertunda menjadi nyata; BOP dibangun ulang dari ProDev; BOM dibuat ulang dari ProDev (edit petugas dipertahankan); target, setup, dan kandidat mesin dihitung; gerbang bahan, tooling, artwork diperbarui; lalu jadwal Draft disusun. Hasilnya satu baris ringkasan, misalnya "n dari t operasi terjadwal, awal sampai akhir, bentrok, terlambat". Tombol ini hanya menyala bila input berubah atau ada geseran tertunda.
6. **Pengaman jadwal.** Sesudah PROCESS, bila ada proses BOP yang tidak punya waktu mulai, PROCESS diblokir dan sebuah popup menyebut WO, proses, dan alasannya. Dalam kondisi itu APPLY tetap mati.
7. **APPLY TO LIVE SCHEDULE.** Satu-satunya jalan Confirm per WO. Tombol hanya sah bila PROCESS sudah dijalankan dan datanya tidak berubah sesudahnya ("Click PROCESS first" atau "Data changed, click PROCESS again"), semua bahan baku siap, tidak ada gerbang terblokir, dan semua proses sudah punya jadwal. Saat Apply, engine dijalankan ulang terhadap jadwal berlaku saat itu (WO lain mungkin sudah di-Apply sejak Draft dibuat). Seluruh operasi WO itu dikunci, tanpa melihat jendela beku. Ada juga **APPLY ALL WOs** di toolbar Gantt untuk semua WO draft pada proses dan mesin terpilih; ia menolak jalan bila masih ada perubahan tertunda.
8. **Undo Confirm.** Hanya Confirm terakhir yang bisa dibatalkan, dan hanya bila belum ada operasinya yang mulai jalan.

**Yang ditandai otomatis:** bentrok mesin (dua WO tumpang tindih di satu mesin) diberi tanda dan dihitung; jam dan kalender mengikuti shift mesin, hari Minggu, dan hari libur; bahan lewat gerbang Ready/Waiting/Blocked; crew lewat Assign Crew (lihat bagian 6 untuk batasnya). Engine tidak pernah mengganti jadwal berlaku sendirian; semua usulannya berhenti di Draft sampai petugas menekan Apply.

## 3. Auto-Planner dan Schedule Solver

**Auto-Planner (`AutoPlanRunner`)** adalah pengelola satu putaran perencanaan. Ia berjalan otomatis setiap malam pukul 02:00 dan setiap awal shift (07:00 dan 15:00 menurut nilai awal), atau saat petugas menekan PROCESS, memberi prioritas, atau menggeser bar. Langkahnya: melengkapi WO yang belum punya id proses, menyegarkan gerbang bahan dan tooling, memuat operasi terbuka di luar jadwal terkunci, menyerahkan fakta ke solver, lalu membuang Draft lama dan menulis Draft baru bersama daftar pengecualian dan catatan putaran. Ia tidak pernah menyentuh bagian yang sudah Confirmed dan terkunci, dan tidak membatalkan penempatan buatan petugas.

**Schedule Solver (`ScheduleSolver`)** adalah perhitungan murni tanpa akses database: diberi fakta, ia mengembalikan jadwal. Cara kerjanya:

- **Peringkat WO**, tiga lapis. Lapis pertama Hot job. Lapis kedua WO yang dinaikkan petugas (Move up) atau yang waktunya sudah lebih sempit daripada pekerjaannya (rasio kritis di bawah satu), yang paling sempit lebih dulu. Lapis ketiga sisanya menurut tanggal tenggat, lalu tier customer.
- **Penghalusan di lapis ketiga.** WO yang tenggatnya berdekatan (jendela nilai awal 8 jam, atau sampai 48 jam bila memakai pisau/die yang sama) diurutkan ulang supaya pekerjaan yang mirip berurutan dan setup berkurang. Penghematan setup tidak pernah keluar dari jendela itu, jadi tenggat tidak dikorbankan.
- **Menempatkan operasi** satu WO menurut urutan BOP: setiap operasi mulai setelah operasi sebelumnya selesai ditambah jeda pengeringan, setelah bahan tiba, dan setelah Earliest start yang diisi petugas. WO rakitan menunggu WO komponennya selesai.
- **Memilih mesin.** Untuk tiap operasi dicoba semua mesin yang mampu, dan dipilih yang **selesai paling awal menurut jam dinding** (setup setelah pekerjaan sebelumnya sudah dihitung), lalu preferensi urutan mesin, lalu yang paling lengang. Slot kosong di antara pekerjaan yang sudah ada ikut dicari.
- **Pengelompokan die.** Bila ada mesin yang pekerjaan terakhirnya memakai die yang sama, mesin itu diutamakan selama ia selesai tidak lebih dari 8 jam setelah mesin tercepat dan tenggat tetap aman. Kelompok ini bisa dikeluarkan petugas per WO dengan alasan.
- **Operasi tertentu.** Pekerjaan yang sudah jalan atau terkunci tidak digeser. Gerbang terblokir menghentikan rantai WO itu dan operasi berikutnya ditandai "tertahan". Konfirmasi di mesin yang sedang rusak atau maintenance diusulkan pindah ke mesin lain di Draft, dengan alasan, dan petugas memutuskan saat Confirm. Pekerjaan Confirmed yang gerbangnya bermasalah tidak menahan mesinnya.

**Yang bisa dilihat petugas:** jadwal Draft (mesin, mulai, selesai, setup), daftar pengecualian dengan alasan (risiko terlambat, bahan menunggu, tooling terblokir, tidak ada mesin, rantai tertahan, dipindah dari mesin rusak), beban tiap mesin selama tujuh hari ke depan, dan angka perbandingan Draft melawan jadwal berlaku (jumlah operasi, terlambat, menit setup, jumlah pasang die, berapa yang bergeser, rata-rata beban). Mesin dengan beban mulai 90 persen dianggap macet (bottleneck).

## 4. Waktu setup, changeover, dan aturan urutan

Waktu setup antara dua pekerjaan di satu mesin dihitung dari tiga bagian: setup dasar mesin, ditambah perubahan antara pekerjaan sebelumnya dan sesudahnya, ditambah waktu memasang cetakan. Perubahan yang dihitung tergantung jenis proses:

- **Mesin cetak:** ganti warna (cuci sebagian bila hanya menambah warna khusus, cuci penuh bila lain), ganti jenis tinta (konvensional ke UV), ganti bahan (selisih gramatur lebih dari batas atau jenis karton lain), ganti ukuran lembar, pasang plate baru.
- **Pond/finishing:** ganti bahan, ganti ukuran, pasang die baru.
- **Postpress:** hanya ganti ukuran dan bahan.
- **Proses lain:** hanya setup dasar.

Bila kedua pekerjaan membawa daftar tinta aslinya dan tinta berikutnya lebih gelap, cuci diringankan dengan faktor yang masih berupa **asumsi** (belum terbukti untuk pabrik ini). Angka menit awal ada di konfigurasi dan di Settings > Constants; baris di Setup and Changeover master per mesin menimpanya, dan engine belajar mengoreksi nilai asumsi dari hasil produksi.

Selain setup, ada **jeda tunggu setelah proses** (pengeringan, curing), diambil dari master Wait Rules menurut awalan nama proses. Nilai awal: CETAK 2 jam, VARNISH 45 menit.

**Aturan urutan (scheduling rules).** Ada dua "mesin aturan" di kode, dan keduanya jangan dicampur:

1. **Yang dipakai layar Planning sekarang** adalah Solver di atas. Urutan murni ditentukan peringkat tiga lapis dan penghalusan setup/die. Selain itu ada saran urutan hasil belajar dari susunan petugas (Job Order Learning): strip "Suggested order" di Simulation baru muncul bila model sudah AKTIF, butuh minimal 30 pasangan keputusan petugas dan harus lebih akurat daripada urutan engine. Tombol Use memasukkannya sebagai perubahan tertunda, Dismiss menolaknya, dan Preview tidak menyimpan apa-apa.
2. **PDR Engine dan Scheduling Rule Engine** (`PdrEngine`, `SchedulingRuleEngine`, aturan di `prd_scheduling_rule`) adalah mesin lama untuk papan jadwal v1. Dari penelusuran kode, hanya halaman `production/scheduling-board` yang memakainya. Solver v2 tidak membacanya.

## 5. Pemeriksa tujuan: proses BOP yang tidak dikerjakan

Sasarannya: tidak boleh ada proses BOP yang tidak punya jadwal. Pengamannya (`ScheduleGuard`) menyebut sebuah proses "tak terjadwal" bila operasinya aktif (belum selesai atau jalan) dan tidak punya waktu mulai. Alasan yang ditampilkan, berurutan: gerbang terblokir, tidak ada mesin yang mampu, menunggu bahan atau tooling, atau tidak ada slot mesin dalam horizon.

Pengaman ini bekerja di tiga titik: PROCESS diblokir, APPLY ditolak, dan popup daftar proses. **SUBCON bukan celah.** Proses BOP tanpa mesin dianggap SUBCON dan selalu diberi waktu (lama sesuai hari tunggu subkon di master proses, awalnya 3 hari). Pekerjaan tangan (QC, hitung, pack) juga selalu diberi jadwal dengan asumsi 30 menit. Karena itu keduanya tidak pernah masuk daftar tak terjadwal. Di Gantt, operasi tanpa mesin digambar di grup SUBCON, satu baris per proses berlabel "nama proses subcon".

## 6. Hal yang sering membingungkan dan batasnya

- **PROCESS bukan Apply.** PROCESS hanya membuat Draft; jadwal berlaku baru berubah setelah APPLY. Menggeser bar pun hanya "tertunda" sampai PROCESS.
- **Apply per WO mengunci semuanya**, sedangkan Confirm seluruh rencana (di PPIC Workspace yang disembunyikan) hanya mengunci beberapa shift ke depan (nilai awal satu shift).
- **"Horizon" ada dua.** Horizon jam (nilai awal 72) dipakai menakar urgensi saat mengurutkan, bukan panjang perencanaan. Horizon hari (60) dipakai memuat hari libur.
- **Operasi tanpa mesin tidak memakai kapasitas.** SUBCON, QC, hitung, pack tidak menempati mesin, sehingga baris-barisnya boleh bertumpuk di Gantt.
- **Crew belum dicek solver.** Bentrok orang tidak diperiksa saat menyusun jadwal.
- **Pembagian satu proses ke beberapa mesin belum ada.** Solver menjadwalkan baris dengan urutan sama secara berurutan, dan PROCESS bisa menghapus baris tambahan.
- **WO yang sudah Applied terkunci.** Menggeser butuh Undo Confirm dulu, dan Undo ditolak begitu ada operasi yang mulai jalan atau ada Confirm yang lebih baru.
- **Hot job dan Move up berbeda.** Hot job paling depan dan boleh menembus kunci; Move up hanya mendahului antrean normal dan tidak menembus kunci. Keduanya wajib alasan.
- **Mesin dibandingkan dengan jam dinding**, bukan menit kerja kalender masing-masing, supaya mesin 1 shift dan 2 shift adil dibandingkan.

## 7. Catatan untuk Jo

**Selisih dokumen dan kode**

- EngineRegistry mencantumkan PDR Engine dan Scheduling Rule Engine di kelompok Production seolah bagian jalur penjadwalan. Menurut kode, jalur PPIC v2 (Auto-Planner, Solver, Plan Control) tidak memakainya; keduanya hanya dipakai papan v1. Aturan di `prd_scheduling_rule` karena itu tidak memengaruhi hasil Planning v2.
- Teks `docs/SCREEN-INDEX.md` menyebut tombol Confirm WO di header dihapus dan APPLY menjadi satu-satunya jalan; kode cocok. Namun controller `confirm` (satu rencana penuh) masih ada dan dipakai PPIC Workspace yang disembunyikan.
- Registry menyebut solver "mengenali pengecualian tooling terblokir"; di kode solver, tooling terblokir mengalir lewat gerbang (BLOCK) dan muncul sebagai pengecualian terblokir bersama gerbang lain, bukan jenis pengecualian tersendiri.

**Butir ENGINE-ATTENTION yang relevan**

- **07 (terbuka):** operasi tanpa mesin tidak memakai kapasitas; crew tidak dicek solver (`CrewAvailabilityChecker` hanya dipakai mesin lama); pembagian proses ke beberapa mesin belum ada.
- **06 (terbuka):** gerbang artwork selalu READY, bahan dengan Required 0 tidak menahan, tooling tak terdaftar dianggap READY dengan catatan. WO bisa tampak siap padahal belum.
- **03 dan 05 (terbuka):** Release tidak membuat booking sehingga dua WO bisa melepas stok yang sama; kolom Purchase belum memicu PR.
- **02 dan 04 (terbuka):** definisi "Available" berbeda antara PPIC dan layar lain; satuan Required dan stok dibandingkan tanpa konversi.
- **08 (terbuka):** HOT PRINT/FOIL STAMPING ditandai non-inhouse padahal ada mesin mampu, sehingga muncul sebagai SUBCON; keputusan master ada di Jo.
- **01 (selesai 14 Okt):** patch SpecEngine terapan; BOM tiap WO kini terisi.

**Yang tidak bisa saya pastikan**

- Apakah libur mesin yang sudah direncanakan di masa depan (`prod_machine_downtime`, jadwal maintenance) dihormati solver. Yang terbukti di kode: solver hanya menghindari mesin berstatus BREAKDOWN atau MAINTENANCE saat perencanaan dijalankan. Penggunaan blok maintenance hanya saya temukan di mesin lama. **Belum terverifikasi** untuk jendela masa depan.
- Apakah halaman papan v1 (`production/scheduling-board`) masih muncul di menu pengguna; rutenya masih terdaftar, tetapi isi `sys_app_menu` tidak saya baca.
- Perilaku penempatan di celah: setup operasi yang disisipkan dihitung terhadap pekerjaan sebelumnya, tetapi setup pekerjaan sesudahnya tidak dihitung ulang. Ini terbaca dari kode, **belum diuji** dengan kasus nyata.
- Arti SPILL, angka nyata data produksi, dan seberapa akurat nilai asumsi (faktor cuci terang-gelap, menit awal) untuk pabrik ini tidak bisa saya nilai dari kode.

<!-- johiwan-only -->
## 8. Rumus dan aturan hitung nyata (khusus johiwan)

Semua angka awal dari `config/ppic.php`; bisa ditimpa Settings > Constants (`PPIC_*`) dan master mesin. Contoh angka saya hitung tangan dari rumus kode, bukan dari data produksi.

**Durasi proses (`ScheduleSolver::runMinutes`)**
- Menit proses = `ceil(qtyIn / rate * 60)` minimal 1. `rate` = target per jam kandidat mesin; bila kosong atau 0: 60 menit tetap (dan diberi peringatan).
- Contoh: 6.000 lembar, 3.000 lembar/jam: 120 menit. Tanpa rate: 60 menit.
- Durasi blok mesin = setup total + menit proses. Contoh 120 + 60 = 180 menit.
- SUBCON: `max(1, hari tunggu)` hari kalender, dikali 1440 menit. Nilai kosong di master proses: 3 hari. Tidak memakai kapasitas mesin (machine 0).
- Pekerjaan tangan (MANUAL): 30 menit tetap pada kalender mesin 0, tidak memakai kapasitas.

**Kapasitas dan kalender (`WorkingCalendar`)**
- Menit kerja sehari = `min(1440, round(shifts x jam per shift x 60))`; awal 2 shift x 8 jam = 960 menit, mulai 07:00 (jadi 07:00 sampai 23:00). Minggu libur; tanggal di `sys_holiday` aktif libur. Menit per shift = jam per shift x 60 (480).
- Setiap mesin punya sumbu menit kerjanya sendiri; perbandingan antar mesin memakai jam dinding (`timeAt`).
- Beban mesin = menit terpakai / menit kerja tersedia dalam 7 hari kalender ke depan, dibatasi 100, satu desimal. Dengan 960 menit/hari dan satu Minggu libur, tersedia 6 x 960 = 5.760 menit. Contoh terpakai 4.000: 69,4 persen. Ambang bottleneck `PPIC_BOTTLENECK_LOAD_PCT` = 90.
- Jendela kunci: `lockUntil = menit kerja sekarang + lock_shifts x menit per shift`. Dengan 1 shift 480 menit, operasi yang Draft-start-nya sebelum itu terkunci pada Confirm seluruh rencana. Apply satu WO: semua operasi WO terkunci.

**Waktu setup (`SetupTimeEngine::between`)**
- `total = base + change + tool`. `base` = setup_minutes kandidat mesin, bila kosong `PPIC_BASE_SETUP_MIN` (15).
- Perubahan (menit / sheet waste ekstra), hanya bila ada pekerjaan sebelumnya:
  - tambah warna khusus (set lama termasuk di set baru): 20 / 30;
  - warna beda penuh: 38 / 60; bila kedua pekerjaan punya tinta asli dan rata-rata kegelapan baru lebih besar dari lama: kali `PPIC_COLOR_ORDER_FACTOR` 0,7 (menit dibulatkan, waste dibulatkan). Skala kegelapan: Yellow 1, Cyan 2, Magenta 3, Black 5, tinta lain 3;
  - tinta konvensional ke UV: 55 / 80;
  - bahan: gramatur selisih lebih dari 20 atau nama board berbeda: 15 / 20;
  - ukuran lembar (sisi panjang atau pendek selisih lebih dari 1 mm, setelah diurutkan): 12 / 15.
- Pasang cetakan (`tool`, juga pada start dingin): die bila die berbeda dari sebelumnya, menit = `tool_min` tooling master (`setup_first_min`), jika kosong 15; plate bila kode plate berbeda: 10. Die hanya terisi untuk proses berawalan PLONG; plate hanya proses CETAK pada tahap PRESS.
- Jenis yang dihitung per tahap: PRESS = warna, tinta, bahan, ukuran, plate, die; FINISHING = bahan, ukuran, die; POSTPRESS = ukuran, bahan; lainnya: hanya base.
- Contoh 1 (cetak, 4 warna ke 4 warna + 1 spot, gramatur 310 ke 350, plate beda): 15 + (20 + 15) + 10 = 60 menit, waste 30 + 20 = 50 lembar.
- Contoh 2 (tinta asli [Yellow, Cyan] ke [Magenta, Black], rata-rata 1,5 ke 4): cuci penuh 38 x 0,7 = 26,6 dibulatkan 27 menit, waste 60 x 0,7 = 42.
- Contoh 3 (pond, die baru, `tool_min` 25, bahan sama): 15 + 25 = 40 menit.

**Jeda tunggu setelah proses:** menit dari `prod_wait_rule` aktif atau `ppic.wait_after` (CETAK 120, VARNISH 45, UV 0, LAMINASI 0), cocok bila nama proses berawalan kunci; kunci terakhir yang cocok menang. Operasi berikutnya mulai paling cepat `selesai + jeda`.

**Peringkat WO (`ScheduleSolver::rank`)**
- `sisa jam` = jumlah (menit proses tercepat + setup dasar) semua operasi belum tetap dibagi 60, ditambah jeda tunggu; tanpa kandidat: 60 menit.
- `waktu tersisa` = `max(0, menit kerja(due) - menit kerja(sekarang)) / 60` pada kalender mesin kandidat pertama. `CR = waktu tersisa / sisa jam`. `slack = waktu tersisa - sisa jam`.
- Lapis: Hot job = 0; Move up (priority URGENT) atau CR < 1 = 1; selain itu 2. Lapis 0 urut CR naik lalu id; lapis 1 Move up dulu, lalu CR naik; lapis 2 urut due, tier, id. Tanpa due: CR kosong dan diurut paling akhir di lapis 2.
- Contoh: waktu tersisa 40 jam, sisa jam 50: CR = 0,8, masuk lapis 1.

**Skor penghalusan (lapis 2, `smooth`)**
- Kelompok: WO yang due-nya dalam 8 jam dari anggota pertama, atau die sama dan dalam 48 jam.
- `skor = 0,50 x urgensi + 0,35 x (1 - jarak/jarakMaks) + 0,15 x (4 - tier)/3`, dengan `urgensi = 1 - min(1, max(0, slack/72))` (slack kosong: 0,5). Tier dibatasi 1..3. Dipilih skor tertinggi, lalu dihitung ulang terhadap yang baru dipilih (greedy).
- Jarak: `min(3, |selisih gramatur|/20)` + 1 bila ukuran lembar beda + (bila kedua tinta asli) `1,5 x (1 - irisan/gabungan)`, atau 1,5 bila jumlah warna/spot beda + 3 bila tinta konvensional/UV beda + 1 bila die beda.
- Contoh: slack 24 jam: urgensi 0,667 -> 0,333; jarak 0 -> 0,35; tier 1 -> 0,15; skor 0,833. Pesaing: slack 60, jarak = jarakMaks, tier 3: 0,083 + 0 + 0,05 = 0,133.

**Pemilihan mesin:** kunci pembanding `[jam selesai (timestamp), preference_rank, total menit terpakai mesin, id mesin]`, terkecil menang. Aturan die: mesin dengan die terakhir sama dipilih bila jam selesainya <= terbaik + 8 jam dan (tidak ada due, atau selesai <= due, atau terbaik sudah lewat due). Slot dicari di celah antara blok yang ada bila muat.

**Terlambat:** `LATE_RISK` bila selesai operasi terakhir > due; jam terlambat = selisih menit / 60, satu desimal.

**Persen progres (`Ppic::operations`)**
- `progress = min(100, round(good / qty_out x 100))`; `qty_out` bila kosong memakai `qty`; 0 bila qty_out 0. Operasi tanpa mesin: persen hanya bila ada output tercatat atau sedang jalan, selain itu kosong.
- Prakiraan selesai (operasi RUNNING, progres 5 sampai 99): `mulai aktual + lama berjalan / (progres/100)`, ditampilkan bila lebih dari rencana + 0,1 jam.

**Gerbang bahan (`GateService`)**
- Release = nilai petugas bila diisi, selain itu Available. Purchase = `max(0, Required - Release)`. OVER_RELEASE (Release > Available + 0,0001) = BLOCK; Purchase > 0,0001 = WAIT; bahan belum dipilih = WAIT; tidak ada baris BOM pada WO dari Inquiry = WAIT. Baris Required 0 diabaikan.
- Available = on hand dikurangi sisa booking semua tahap, minimum 0 (`AvailableStock`).
- Contoh: Required 1.200, Available 900, Release kosong -> Release 900, Purchase 300, gerbang WAIT, APPLY ditolak.
- Tooling: die atau plate berstatus READY atau IN_USE lulus; NOT_MADE, IN_MAKING, NEEDS_REPAIR = BLOCK; tak terdaftar = lulus dengan catatan.

**Gerbang pada solver:** BLOCK menghentikan rantai WO (operasi berikutnya CHAIN_BLOCKED); WAIT menunda mulai ke `material_ready_at`; mesin berstatus BREAKDOWN/MAINTENANCE saat perencanaan dikeluarkan dari kandidat.
