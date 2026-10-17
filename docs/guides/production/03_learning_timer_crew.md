# Belajar dari Kerja Nyata dan Eksekusi di Lantai Produksi: panduan untuk Jo

> Untuk dibaca dan direview Jo. Sumber kebenaran adalah kode (dibaca 2026-10-16), bukan dokumen lama. Nama tabel dan kelas ditulis dalam kurung kecil hanya sebagai petunjuk. Bagian setelah penanda `johiwan-only` di akhir berisi rumus; bagian sebelumnya narasi saja.

## 1. Untuk apa bagian ini dan siapa yang memakainya

Penjadwalan produksi di ERP ini tidak berhenti pada jadwal yang cantik di layar. Setelah jadwal berlaku, operator mengerjakannya di mesin, dan angka nyata dari mesin itu (berapa menit ganti job, berapa lembar per jam, urutan mana yang dipilih petugas) dipakai untuk memperbaiki saran berikutnya. Panduan ini membahas enam hal yang saling terkait:

- **Job Order Learning**: sistem merekam urutan job yang disusun petugas PPIC, lalu belajar menyarankan urutan serupa.
- **Learning Engine (changeover)**: koreksi menit ganti job (cuci warna, ganti tinta, ganti bahan, ganti ukuran, ganti plat, ganti pisau) dari waktu setup yang benar-benar dipakai operator.
- **Speed Target**: usulan target kecepatan tiap mesin dan tiap pasangan proses x mesin.
- **Hot Job**: job mendesak yang harus disisipkan, dengan pilihan dan dampaknya ditunjukkan dulu.
- **Operator Timer**: tombol Play, Pause, Stop di mesin dan Simpan Hasil Good/Bad.
- **Assign Crew**: daftar orang yang bertugas di tiap mesin per shift.

Pemakainya: **petugas PPIC** (menyusun jadwal, memutuskan saran, menerima atau menolak usulan), **operator** (memakai layar Production Output di mesin), dan **supervisor/Kabag** (mengatur crew dan membaca pemantauan Live Schedule). Prinsip yang berlaku di semua bagian ini adalah aturan A.7: engine menyarankan, petugas memutuskan. Tidak ada satu pun bagian di sini yang mengubah jadwal diam-diam.

## 2. Job Order Learning: belajar dari susunan urutan petugas

**Masalahnya.** Engine penjadwal menyusun urutan job di satu mesin menurut aturan tetap (mendesak, prioritas, kesamaan). Petugas PPIC sering punya alasan yang tidak tertulis: job ini dikelompokkan karena pisaunya sama, job itu didahulukan karena pelanggannya rewel. Job Order Learning menangkap kebijaksanaan itu.

**Apa yang direkam.** Ada dua momen di layar Work Order (Planning > Work Order):

1. Saat petugas menekan **PROCESS** dan lulus, sistem menyimpan foto urutan job Draft di mesin itu sebagai **TRIAL**. Ini hanya konteks. PROCESS berikutnya menimpa TRIAL yang lama, jadi tidak menumpuk.
2. Saat petugas menekan **APPLY TO LIVE SCHEDULE**, urutan yang berlaku saat itu disimpan sebagai **FINAL**. Inilah "jawaban benar" yang dipelajari. TRIAL milik WO itu dibuang.

Yang direkam untuk tiap job adalah enam ciri: seberapa mendesak (sisa waktu dibanding jam kerjanya), prioritas (Hot atau Move up), lama proses, kemiripan dengan job sebelumnya di mesin (pelanggan dan produk sama), kesiapan bahan, dan jam tunggu proses sebelumnya (misalnya pengeringan setelah cetak). Kegagalan perekaman apa pun hanya masuk log; PROCESS dan APPLY tidak pernah dibuat gagal karenanya. Bila mesin bermasalah atau job ditarik karena bahan belum siap, alasannya dicatat sebagai konteks, tanpa mengubah cara kerja penjadwal.

**Kapan belajar.** Pelatihan dijalankan otomatis tiap malam pukul 03:45 (perintah `sequence:train`). Dari setiap foto FINAL, sistem membuat pasangan: "job A didahulukan petugas sebelum job B". Dua hal tidak ikut dilatih: urutan yang berasal dari saran sistem sendiri yang dipakai tanpa diubah (agar tidak belajar dari dirinya sendiri), dan WO yang dibatalkan.

**Kapan boleh menyarankan.** Model baru aktif bila memenuhi dua syarat sekaligus: sudah ada sedikitnya **30 pasangan keputusan**, dan pada 20 persen APPLY terbaru (yang sengaja disisihkan, tidak ikut melatih) model menebak urutan petugas **lebih sering benar daripada urutan engine biasa**. Selama belum memenuhi, layar Simulation menampilkan "Learning n / 30" dan tidak menyarankan apa pun. Model yang tidak lebih baik dari engine tetap berstatus kandidat dan tidak dipakai.

**Apa yang dilihat petugas.** Bila model aktif dan ada dua job Draft atau lebih menunggu di satu mesin, di atas Gantt muncul strip "Suggested order" dan di antrean (QUE) muncul kolom Suggested. Dua tombol:

- **Use**: urutan saran dipasang lewat jalur yang sama dengan menyeret bar di Gantt. Hasilnya tetap **DRAFT** dan tetap harus melewati PROCESS dan APPLY.
- **Dismiss**: saran ditolak, dicatat sebagai SEQ_REJECT, dan strip disembunyikan sampai susunan berubah.

Preview tidak menyimpan apa pun. Bila petugas memakai saran lalu mengubahnya, susunan akhir itulah yang dianggap jawaban benar; bila memakai tanpa mengubah, tidak dihitung (lihat butir "tanpa umpan balik sendiri" di atas).

## 3. Learning Engine (changeover) dan Speed Target

### 3a. Waktu ganti job (changeover)

Jadwal memakai nilai awal "Assumed" (dugaan) untuk menit ganti job, ditulis di konfigurasi PPIC: tambah warna spot 20 menit, cuci penuh 38, ganti jenis tinta 55, ganti bahan 15, ganti ukuran 12, ganti pisau 15, ganti plat 10, ditambah setup dasar 15 menit. Semuanya tampil di Settings > Constants dan layar Setup and Changeover.

Learning Engine (`ppic:learn`, otomatis tiap malam 03:30, atau tombol **Learn now** di layar Learning Changes) mengoreksi nilai Assumed itu dari waktu setup yang benar-benar dipakai operator, yang diukur oleh Operator Timer (bagian 5). Aturannya sengaja hati-hati:

- Hanya nilai berstatus Assumed atau Learning yang disentuh. Nilai yang **dibekukan** (frozen) tidak pernah diubah.
- Perlu minimal 5 job selesai untuk kombinasi mesin dan jenis ganti yang sama.
- Nilai tipikal diambil dari median 5 job terakhir (bukan rata-rata, supaya satu kejadian aneh tidak menggeser), dikurangi setup dasar.
- Nilai lama digeser sebagian saja ke arah nilai tipikal, dan satu langkah paling banyak 20 persen dari nilai lama.
- Hanya job yang selesai **setelah** penyesuaian terakhir yang dihitung, sehingga menjalankan ulang tidak membuat nilai terus bergeser pada data yang sama.
- Setiap perubahan ditulis di log keputusan (event LEARN) dengan alasan. Petugas bisa memilih salah satu perubahan di layar Learning Changes lalu **Put back and freeze**: nilai lama dikembalikan dan dibekukan supaya engine tidak mengutak-atiknya lagi.

Engine hanya menyentuh nilai changeover berstatus Assumed. Data master lain tidak diubah (master hanya diubah pengguna).

### 3b. Target kecepatan (Speed Target)

Target kecepatan per mesin (lembar atau pcs per jam) dan per pasangan proses x mesin diusulkan engine tiap hari pukul 03:00 (`production:suggest-speed-targets`). Engine mencoba sumber berikut **berurutan**; sumber pertama yang menghasilkan angka menang:

1. Realisasi Work Order di sistem baru pada mesin itu (setahun terakhir).
2. Catatan produksi sistem lama untuk mesin itu.
3. Catatan sistem lama untuk proses utamanya, semua crew (hanya bila proses itu dikerjakan satu jenis mesin saja).
4. Mesin lain dengan model katalog yang sama dan sudah punya riwayat (dipinjam).
5. Mesin sejenis (jenis dan penggerak sama): efisiensi nyata pabrik dikalikan kecepatan maksimum mesin ini.
6. Kecepatan maksimum pabrikan dikalikan faktor efisiensi (dikalibrasi dari riwayat bila cukup mesin pembanding).
7. Kecepatan default proses di Master Proses, sebagai jaring terakhir.

Tiap usulan membawa **keyakinan** (Low, Medium, High) dan jumlah sampel. Catatan yang tidak wajar disaring: terlalu pendek (kurang dari 5 menit, biasanya salah ketik), terlalu panjang (lebih dari 16 jam, biasanya lupa Stop), dan proses non-produksi seperti setting, ganti, cleaning, QC (daftar kata di konstanta). Angka yang dipilih bukan rata-rata, melainkan angka "shift yang bagus" (persentil ke-75), bukan rekor tertinggi.

Pasangan proses x mesin hanya diusulkan dari riwayat pasangan itu sendiri; tanpa riwayat sendiri ia mewarisi target mesin. PPIC hanya "diganggu" bila ada yang layak diputuskan: nilai masih kosong, atau selisih lebih dari 5 persen dengan keyakinan minimal Medium. Usulan disimpan berstatus **PENDING**; target mesin baru berubah bila PPIC menerimanya. Engine tidak pernah menimpa diam-diam.

## 4. Hot Job dan interupsi: pilihan, bukan jawaban tunggal

Hot Job adalah pekerjaan mendesak yang harus didahulukan (lihat juga bagian 8). Jalurnya satu, lewat `PlanChangeService`: di Form Work Order, petugas memilih prioritas: **Move up** atau **Hot job**, dengan alasan wajib diisi. Pilihan itu menjadi perubahan **tertunda** sampai PROCESS dijalankan, dan sebelum disimpan dapat dibandingkan lewat simulasi tiga pilihan:

- **No priority**: kondisi apa adanya.
- **Move up**: WO didahulukan di depan antrean biasa, tetapi **tidak mendobrak** operasi yang sudah terkunci.
- **Hot job**: WO didahulukan paling depan dan **boleh mendobrak kunci** operasi lain.

Untuk tiap pilihan simulasi menunjukkan perkiraan selesai WO itu, apakah ia masih terlambat dari due date (dalam jam), WO lain yang baru menjadi terlambat, dan berapa operasi yang bergeser. Dampaknya ke jadwal: penjadwal menyusun ulang semua yang lain di sekitar prioritas itu, hasilnya masuk Draft, dan baru berlaku setelah APPLY. Prioritas yang sudah dipasang bisa ditarik kembali (event UNDO_PRIORITY). Setiap pemberian prioritas dicatat dengan alasan dan nama petugas.

## 5. Operator Timer: dari Play sampai Simpan Hasil

Layar **Production Output** (Manufacture > Production) dipakai operator di mesin. Alurnya:

1. Operator login. Sistem mencari mesin dan proses yang menjadi tugasnya, lalu menampilkan **LIST OF JOBS**: hanya operasi berstatus Confirmed (sudah di-Apply PPIC) untuk mesin dan proses itu. Job yang sedang jalan tampil dulu, job selesai ditaruh di bawah (lima terbaru).
2. Operator memilih job. Tampil Master Card ringkas (dieline, artwork, ukuran), target per jam, target qty, dan perkiraan durasi.
3. Ada dua timer per job: **SETUP** dan **PRODUCTION**. Tiap timer punya **Play**, **Pause**, **Stop**.
4. Hanya **satu timer yang berjalan per mesin**. Menekan Play pada timer lain menutup timer yang sedang jalan (statusnya menjadi jeda). Bila job lain di mesin yang sama masih punya timer setup/produksi berjalan, sistem menolak dan meminta job itu di-Pause atau di-Stop dulu. Job yang sedang jeda tidak menghalangi job berikutnya.
5. **Pause** menutup segmen yang berjalan dan membuka segmen jeda. **Stop** mengunci timer itu: tidak bisa Play atau Pause lagi. Stop sebelum pernah Play ditolak.
6. **Stop PRODUCTION** menyelesaikan operasi (status DONE) dan mengisi waktu selesai nyata; waktu setup nyata dihitung dari timer SETUP dan disimpan di operasi.
7. **SIMPAN HASIL**: operator mengisi **GOOD** dan **BAD**; angkanya **ditambahkan** ke yang sudah tersimpan (bukan menimpa). BAD boleh dipecah per alasan reject (satu baris per alasan) dan jumlahnya harus sama dengan BAD. Isian GOOD dan BAD yang sama-sama nol ditolak.

**Yang tercatat:** satu baris waktu untuk setiap segmen (SETUP, RUN, atau PAUSE, lengkap dengan operator, mulai, selesai, menit); baris hasil Good dan baris reject per alasan; serta **log aksi**, satu baris untuk setiap tekan tombol (kapan, siapa, dari komputer mana, nilai sebelum dan sesudah).

**Hubungan ke progres.** Progres sebuah operasi di Live Schedule dan Gantt adalah jumlah GOOD yang tersimpan dibagi qty keluaran yang direncanakan (maksimum 100 persen). Untuk operasi yang sedang berjalan dan sudah melewati 5 persen, layar memperkirakan jam selesai bila laju sekarang berlanjut dan menandainya bila lebih lambat dari rencana. Layar Live Schedule (Planning > Live Schedule) hanya memantau, tanpa tombol ubah. Waktu SETUP nyata per jenis ganti job adalah bahan Learning Engine (lihat catatan 8: ada celah di mata rantai ini).

## 6. Assign Crew: siapa di mesin mana, pada shift apa

**Layar.** Planning > Assign Crew adalah grid mesin proses Offset Printing (mesin Digital tidak tampil). Tiap baris: nama panggilan mesin besar, nama detail di bawahnya, jenis, lalu satu kolom per shift produksi berisi nama-nama crew yang berlaku hari ini. Klik baris membuka **Form Assign Crew** satu mesin.

**Form.** Periode **Period from / Until** berlaku untuk semua kotak. Kotak crew berdampingan, **sebanyak shift produksi yang aktif di Master Shift** (bila Master Shift menambah atau mengurangi shift, jumlah kotak ikut berubah). Tiap kotak punya pilihan Shift dan daftar anggota (maksimal 10 orang, masing-masing dengan peran: Main operator, Co-operator, Helper, atau Kabag). Satu Save menyimpan semua kotak sekaligus (semua atau tidak sama sekali). Kotak yang dikosongkan berarti crew shift itu dinonaktifkan (bukan dihapus). Nama crew otomatis "Nickname - Nama Shift".

**Aturan:**

- Hanya mesin Offset yang boleh punya crew, dan nickname mesin harus sudah diisi.
- Satu crew = satu mesin + satu shift; satu mesin paling banyak satu crew per shift produksi.
- Anggota hanya karyawan produksi aktif (departemen seperti Pra-Cetak, Press, Cetak, Plong-Diecut, Laminasi, Potong, Folder-Glue, Kupas, Shrink, Manual, Harian-Lepas). QC, lem manual, dan kupas manual belum punya crew.
- Periode crew yang sama tidak boleh tumpang tindih; satu orang tidak boleh berada di dua crew pada shift yang sama dalam periode yang bertabrakan (boleh di shift berbeda).
- Tanggal mulai wajib; tanggal akhir tidak boleh sebelum tanggal mulai.

**Siapa menentukan apa.** Supervisor atau petugas PPIC mengisi nama; sistem hanya memeriksa aturan di atas. Crew ini ditampilkan sebagai kolom Crew pada tabel "On the shop floor now" di PPIC Workspace (mesin Offset tanpa crew tertulis "No crew assigned"). Menugaskan **orang untuk kerja manual tanpa mesin ditunda** (keputusan sebelumnya; Assign Crew sementara hanya untuk mesin).

## 7. Hal yang sering membingungkan, dan batasnya

- **"Learning n / 30" bukan galat.** Itu hitungan pasangan keputusan petugas yang sudah terkumpul. Sebuah arrangement dengan 4 job menyumbang 6 pasangan; satu APPLY satu job di mesin kosong tidak menyumbang apa pun.
- **Saran urutan bukan perintah.** Use hanya memasang Draft; tetap perlu PROCESS dan APPLY.
- **Nilai changeover Assumed bukan fakta.** Ia dugaan awal; hanya setelah ada job selesai nilai itu bergeser, itu pun pelan-pelan.
- **Crew di Assign Crew bukan crew di layar operator.** Layar Production Output menentukan pekerjaan operator dari penugasan crew yang dipilih di BOP (work center) atau penugasan nama per operasi, bukan dari kotak Assign Crew (lihat catatan 8).
- **Bentrok orang belum diperiksa saat menjadwal.** Jadwal tidak mengecek apakah crew cukup atau bentrok.

## 8. Catatan untuk Jo: selisih dokumen vs kode dan hal yang tidak bisa dipastikan

Ini temuan jujur dari membaca kode. Tidak ada yang saya ubah.

1. **Nilai changeover hasil belajar belum memengaruhi jadwal.** Penjadwal memakai `PpicSettings::setupRules()` yang hanya membaca konfigurasi global (`config/ppic.php` dan konstanta). Kolom menit per mesin di `prod_machine_changeover` tidak dibaca oleh `SetupTimeEngine` meski komentar kelasnya menyebut "overridden per machine"; relasi `ProdMachine::changeovers()` tidak dipakai di mana pun (hasil pencarian kode). Jadi Learning Engine mengubah angka yang tampil di layar Learning Changes, tetapi belum mengubah menit setup di Gantt. Belum terverifikasi dari data (saya tidak menjalankan aplikasi).
2. **Sampel Learning Engine mungkin tidak pernah terisi.** Engine membaca segmen SETUP di `prd_operation_time_log` yang kolom `change_kind`-nya terisi. Saya tidak menemukan kode yang menulis `change_kind` saat operator menekan Play (kolom hanya dibuat oleh migration; `OperatorTimerService::begin` tidak mengisinya). Bila memang tidak ada jalur lain, Learning Engine selalu tidak menemukan sampel. Belum terverifikasi di basis data.
3. **Status CALIBRATED tidak mungkin tercapai lewat engine.** Kode memberi CALIBRATED bila jumlah sampel >= 3 x minimum, tetapi jumlah sampel selalu sama dengan minimum (hanya N job terakhir yang diambil). Jadi nilai hasil engine selalu LEARNING.
4. **Tidak ada tes untuk `LearningEngine`.** Tes yang ada: `SequenceLearningTest`, `SpeedTargetEngineTest`, `ProductionOutputTest`.
5. **Hot Job: simulasi tiga pilihan mungkin tidak terjangkau.** Layar v2 memakai `PlanChangeService` (Move up / Hot job / simulasi), yang di registry terdaftar sebagai "Plan Confirm and Change". Layar PPIC Workspace yang memanggil simulasi tiga pilihan berstatus disembunyikan di SCREEN-INDEX. Saya tidak bisa memastikan pilihan simulasi itu terjangkau dari menu yang terlihat petugas sekarang; yang pasti terjangkau adalah prioritas di Form Work Order (pending sampai PROCESS).
6. **Usulan target kecepatan belum punya layar keputusan.** `SpeedSuggestionStore::accept/reject` ada, tetapi tidak ada layar atau rute yang memanggilnya (pencarian di app, resources, routes, packages). Registry menulis dipakai oleh "Master Machine"; itu belum saya temukan. Artinya usulan menumpuk berstatus PENDING dan target berubah hanya bila diisi manual. Belum terverifikasi di UI.
7. **Dua "crew" yang tidak tersambung.** Assign Crew (`prod_machine_crew`) hanya dipakai grid Assign Crew dan kolom Crew di PPIC Workspace. Layar operator memakai `prd_crew_assignment` dan keahlian work center karyawan. Penjadwal sekarang tidak mengecek crew (ATTENTION-ENGINE-07). Jadi mengisi Assign Crew belum membuat job muncul di layar operator tertentu.
8. **Alasan jeda selalu OTHER.** `OperatorTimerService::act` menyimpan alasan jeda "OTHER" untuk setiap Pause; daftar alasan (material, istirahat, mesin rusak, QC, menunggu crew) ada tetapi saya tidak menemukan jalur yang mengisinya dari layar. Belum terverifikasi di UI.
9. **Registry menyebut "yield = good / (good + bad)".** Saya tidak menemukan perhitungan itu di kode timer atau layar operator (hanya progres dan batang proporsi Good/Bad). Tandai: belum terverifikasi.
10. **Aturan A.7 (engine menyarankan, petugas memutuskan)** konsisten dengan kode untuk urutan, target kecepatan, dan Hot Job. Satu pengecualian halus: Learning Engine mengubah nilai changeover Assumed otomatis tanpa persetujuan lebih dulu, tetapi selalu berlog dan dapat dikembalikan.

<!-- johiwan-only -->
## 9. Rumus dan aturan hitung nyata dari kode (hanya untuk johiwan)

Semua angka contoh dihitung dari konstanta di kode; konstanta bisa diubah di Settings > Constants.

### 9a. Learning Engine (changeover) `LearningEngine::run`

Konstanta awal: `PPIC_LEARN_MIN_SAMPLES` = 5, `PPIC_LEARN_MAX_STEP_PCT` = 20, `PPIC_LEARN_BLEND` = 0.3, `PPIC_BASE_SETUP_MIN` = 15. Nilai awal tiap jenis ganti dari `config/ppic.php`: COLOR memakai `color_add_spot` = 20 menit (bukan cuci penuh 38), INK_TYPE 55, SUBSTRATE 15, SIZE 12, PLATE 10, DIE 15.

```
sampel(operasi)  = SUM(menit tersimpan segmen SETUP bertanda change_kind), per operasi, mesin, jenis
recent           = N sampel terakhir (N = MIN_SAMPLES), urut waktu selesai
typical          = max(0, median(recent) - base)
step             = (typical - old) x blend
limit            = max(1, old x MAX_STEP_PCT/100)
new              = round(old + clamp(step, -limit, +limit))
```

Dilewati bila: jumlah sampel total < N; baris frozen atau berstatus selain ASSUMED/LEARNING; job terakhir selesai <= `updated_at` baris; atau new = old pada baris yang sudah ada. Bila baris belum ada, dibuat dengan old = nilai config. Status baru: LEARNING bila sampel < 3N, CALIBRATED bila >= 3N, tetapi sampel selalu = N (lihat catatan 8.3).

Contoh 1: COLOR, 5 setup selesai: 40, 50, 45, 60, 35 menit. Median = 45; typical = 45 - 15 = 30; old = 20; step = (30 - 20) x 0.3 = 3; limit = max(1, 20 x 0.2) = 4; new = 20 + 3 = 23.
Contoh 2 (terbatas langkah): old 20, typical 80 -> step = 18, dibatasi 4 -> new = 24.
Revert: mengembalikan `extra_minutes` lama dari teks log lama dan membekukan baris (`frozen` = 1); satu log LEARN hanya bisa dikembalikan sekali.

### 9b. Speed Target `SpeedHistory`, sumber, dan `shouldPropose`

Konstanta: `SPEED_PERCENTILE` 75, `SPEED_MIN_SAMPLE` 20, `SPEED_HIGH_SAMPLE` 200, `SPEED_RUN_MIN_MINUTES` 5, `SPEED_RUN_MAX_MINUTES` 960, `SPEED_EFF_MANUAL` 0.50, `SPEED_EFF_AUTO` 0.60, `SPEED_EFF_CALIBRATE` 1, `SPEED_EFF_CALIBRATE_MIN` 2, `SPEED_CHANGE_MIN_PCT` 5, `SPEED_V2_LOOKBACK_DAYS` 365.

```
rate catatan   = qty / (menit / 60)             (hanya qty > 0, 5 <= menit <= 960, proses bukan kata terkecuali)
satuan         = satuan proses tujuan bila sampelnya >= 20, selain itu satuan terbanyak (keyakinan diturunkan)
speed          = round( nilai urutan ke ceil(0.75 x (n-1) - 1e-9) dari rate yang diurutkan naik, mulai dari 0 )
keyakinan      = High bila n >= 200, selain itu Medium (v2 dan lama per mesin); proses lama semua mesin: Medium/Low
SAME_MODEL     = round(rata-rata speed mesin kembar), keyakinan Medium
PEER_RATIO     = round(maxSpeed x median(speed/maxSpeed mesin sejenis berriwayat)), Medium bila >= 3 mesin, selain itu Low
MODEL_SPEC     = round(maxSpeed x efisiensi), Low
efisiensi      = median(speed/maxSpeed mesin berpenggerak sama berriwayat) bila >= 2 mesin dan kalibrasi hidup;
                 selain itu 0.50 (MANUAL) / 0.60 (AUTO)
selisih%       = |usulan - sekarang| / sekarang x 100
diusulkan bila = sekarang kosong, ATAU (keyakinan bukan Low DAN selisih% > 5)
```

Contoh 1: n = 21 catatan memenuhi syarat, indeks = ceil(0.75 x 20 - 1e-9) = 15 (nol-based), yaitu catatan ke-16 dari yang terkecil. Satu catatan 3.000 lembar dalam 90 menit = 3.000 / 1,5 = 2.000 lembar/jam.
Contoh 2 (PEER_RATIO): dua mesin sejenis berriwayat: 6.000/10.000 = 0,60 dan 5.000/10.000 = 0,50 -> median genap = 0,55. Mesin ini maxSpeed 8.000 -> round(8.000 x 0,55) = 4.400; hanya 2 mesin, jadi keyakinan Low.
Contoh 4 (diusulkan?): sekarang 4.000, usulan Medium 4.300 -> selisih 7,5% -> diusulkan; usulan 4.150 -> 3,75% -> tidak; usulan Low selisih 20% -> tidak diusulkan kecuali sekarang kosong.

### 9c. Peringkat pasangan urutan (Job Order Learning)

Fitur per job (semua dibulatkan 4 desimal), dengan `now` = saat dihitung:

```
urgency      = clamp( -(jamSisaKeDue - jamKerja) / 240 , -3 , 3 ),  jamKerja = (setup + run menit)/60;  tanpa due = -3
priority     = 1 (Hot) | 0.5 (priority URGENT / Move up) | 0
duration     = clamp( (setup + run menit)/60/8 , 0 , 3 )
similarity   = 0.5 (pelanggan sama dgn job jangkar) + 0.5 (produk sama);  jangkar = op terakhir terkunci/confirmed/running di mesin
material_gate= READY 1 ; WAIT = clamp(-jamTunggu/24 , -3 , 0) (tanpa tanggal siap: -1) ; BLOCK -2
wait_before  = clamp( menitTungguProsesSebelumnya / 60 / 24 , 0 , 3 )
skor job     = jumlah( bobot_i x fitur_i )
```

Contoh: due 30 jam lagi, kerja 60 + 180 menit = 4 jam -> urgency = -(30 - 4)/240 = -0,1083. Due 10 jam lagi -> -(10 - 4)/240 = -0,025 (lebih mendesak, nilainya lebih besar). Terlambat 48 jam -> -(-48 - 4)/240 = +0,2167. duration = 240/60/8 = 0,5. wait_before setelah CETAK (aturan 120 menit) = 120/60/24 = 0,0833. material WAIT siap 12 jam lagi = -0,5.

Pelatihan (`SequenceModel::train`): tiap pasangan (A didahulukan petugas sebelum B) menjadi selisih fitur d = fA - fB, label 1.

```
p        = 1 / (1 + exp(-clamp(w . d, -30, 30)))
gradien  = jumlah( (p - 1) x d ) / n
w       <- w - 0.3 x ( gradien + 0.01 x w )        400 putaran, bobot awal 0
akurasi  = jumlah pasangan dengan w . d > 0 / jumlah pasangan   (4 desimal)
```

Contoh putaran pertama: satu fitur, empat pasangan d = 0,5, w = 0 -> p = 0,5; gradien = (0,5 - 1) x 0,5 = -0,25; w = 0 - 0,3 x (-0,25) = 0,075.

Gerbang (`SequenceTrainer`): jumlah pasangan = jumlah arrangement FINAL (min. 2 job) x n(n-1)/2. Hold-out = ceil(20% x jumlah arrangement) terbaru, minimal 1, tidak ikut melatih. Model ACTIVE bila `total >= 30` DAN hold-out ada DAN `akurasiModel > akurasiEngine` (engine benar bila posisi engine A < posisi engine B).
Contoh: 10 arrangement x 4 job = 10 x 6 = 60 pasangan; hold-out = ceil(10 x 0,2) = 2 arrangement (12 pasangan), latih 8 arrangement (48 pasangan). Model benar 11 dari 12 = 0,9167, engine benar 8 dari 12 = 0,6667 -> ACTIVE. Latihan tanpa data baru tidak membuat versi baru (tanda tangan sama).

Saran (`SequenceSuggester::suggest`): skor tiap job Draft yang dapat digeser dihitung, diurutkan skor turun, seri dipecah dengan posisi sekarang; hasil di-cache 30 detik.

### 9d. Lapisan prioritas Hot Job / Move up (`ScheduleSolver::rank`)

```
sisa        = jam kerja tersisa WO
left        = jam kerja kalender mesin dari sekarang sampai due
CR          = left / sisa
layer 0     = WO Hot                        (urut CR naik)
layer 1     = Move up ATAU CR < 1           (Move up dulu, lalu CR naik)
layer 2     = lainnya                       (urut due, lalu tier pelanggan)
```

Contoh: due 40 jam kerja lagi, sisa kerja 50 jam -> CR = 0,8 -> layer 1 walau tanpa tombol. Hot hanya boleh mendobrak operasi Confirmed yang terkunci; Move up tidak. Jalur lama: dampak geser = (target_finish - target_start job mendesak) / 60 jam; aman bila `target_finish job digeser + dampak <= due`.

### 9e. Hitungan waktu Operator Timer (`OperatorTimerService`)

```
detik timer     = jumlah( (selesai ?? sekarang) - mulai ) semua segmen sejenis
menit segmen    = round(detik / 60), disimpan saat segmen ditutup
menit di ringkasan = menit tersimpan (segmen tertutup) ; segmen terbuka = floor(detik / 60)
actual_setup_min = round( detik timer SETUP / 60 )   (diisi tiap Stop, dari detik, bukan dari menit tersimpan)
status timer    = stop (sudah ada log SETUP_STOP/RUN_STOP, atau operasi DONE untuk produksi) | run (ada segmen terbuka) | pause (ada segmen, tidak terbuka) | idle
status job      = setup (timer setup jalan) | prod (timer produksi jalan) | done (produksi stop) | pause (salah satu pause, atau setup stop tapi produksi belum mulai) | idle
progres %       = min(100, round( GOOD tersimpan / qty_out x 100 )),   qty_out = qty keluaran, atau qty bila kosong
perkiraan selesai = jamMulaiNyata + (jamBerlalu / (progres/100)), hanya bila RUNNING, 5 <= progres < 100, dan lebih dari 0,1 jam setelah rencana
```

Contoh: segmen 10:00:00 sampai 10:07:40 = 460 detik -> round(7,67) = 8 menit. Tiga segmen setup 20 detik: tiap segmen disimpan 0 menit (round(0,33)), tetapi `actual_setup_min` dari total 60 detik = 1 menit; Learning Engine menjumlah menit tersimpan, jadi bisa memberi 0. Job mulai 07:00, sekarang 09:00 (2 jam berlalu), progres 40% -> perkiraan selesai = 07:00 + 2 / 0,4 = 12:00; bila rencana selesai 11:00 ditandai terlambat.
SIMPAN HASIL: total GOOD = jumlah semua baris `qty_good` operasi itu; total BAD = jumlah baris defect. BAD terpecah per alasan harus berjumlah tepat BAD (toleransi 0,0001).

### 9f. Aturan Assign Crew (`MachineCrewService`)

```
jumlah crew maksimum mesin = jumlah shift aktif departemen produksi di Master Shift
anggota maksimum per crew  = 10
periode berlaku pada tanggal d : valid_from <= d DAN (valid_to kosong ATAU valid_to >= d)
bentrok periode            : mulaiBaru <= akhirLama DAN akhirBaru >= mulaiLama   (kosong = 9999-12-31)
orang bentrok              : orang sama, shift sama, crew lain aktif, periode bertabrakan
```

Contoh: crew Plong 1 periode 01-Okt s/d 31-Okt; periode baru 15-Okt s/d terbuka -> 15-Okt <= 31-Okt DAN terbuka >= 01-Okt -> bentrok, ditolak. Periode baru 01-Nov s/d terbuka -> 01-Nov <= 31-Okt salah -> diterima.
