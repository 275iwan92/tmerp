# PPIC: engine terbaik, drag and drop mudah, simulasi sebelum diterapkan, satu layar kerja

Status 10 Okt 2026: **hasil audit baca-saja dan PLAN. Belum ada kode yang diubah.** Semua dari database dummy ddt* dan kode di repo tmerp.
Tanda bukti: file:baris dibaca di kode. "Terbukti di layar" = saya jalankan lewat skrip atau render halaman di ddt* (bukan klik di browser).

## 1. Ringkasnya

- Engine sudah bisa menjadwalkan **kapasitas terbatas per mesin** dengan setup, tunggu pengeringan, kalender shift, gerbang material dan tooling, prioritas tiga lapis, dan menghormati operasi yang sudah terkunci. Ini sudah di atas rata-rata untuk pabrik cetak.
- Yang **belum ada** dan paling terasa: pecah lot, operator/crew sebagai kapasitas, join antar komponen, undo sesudah Apply, dan **simulasi otomatis sebelum/sesudah** untuk setiap pemicu.
- Drag and drop sudah jalan (mesin yang boleh disorot, snap 15 menit, kotak dampak, alasan wajib), tetapi **validasi baru terjadi sesudah dilepas** dan belum ada Undo.
- Pemicu re-plan otomatis sudah ada untuk 6 kejadian, tetapi **hasilnya langsung menimpa Draft lama**: tidak ada tampilan sebelum/sesudah dan tidak bisa dibuang tanpa kehilangan Draft sebelumnya.
- Menu sekarang tersebar di 5 tempat. Usulan: **satu layar kerja** (bagian 5).

## 2. Engine: sudah dimodelkan dan belum

| Aspek | Status | Bukti |
|---|---|---|
| Kapasitas terbatas per mesin (satu pekerjaan sekali, mengisi celah) | Ada | `ScheduleSolver.php:316` (bestSlot), `:355` (occupy) |
| BOP multi-operasi berurutan dalam satu WO, tunggu antar langkah | Ada | `ScheduleSolver.php:217` (placeWo), wait rules `PlanLoader.php:75,123` |
| Join antar WO / komponen paralel (LEM MESIN menunggu dua komponen) | **Belum**: satu WO per part, urut per WO | `ScheduleSolver.php:217` memproses per WO; tidak ada ketergantungan antar WO |
| Setup dan changeover antar dua job di mesin | Ada | `SetupTimeEngine.php:29`, dipakai `bestSlot` |
| Tooling (pisau/plat): gerbang dan menit pasang | Ada | `GateService.php:72`, `PlanLoader.php:140,180` |
| Material: Waiting dengan tanggal, Blocked | Ada | `GateService.php:34`; `ScheduleSolver.php:240,257` memakai tanggal siap |
| Prioritas: Hot, Move up, Critical Ratio, due date, tier, skor setup | Ada | `ScheduleSolver.php:76` (rank), `:110` (smooth) |
| Operasi terkunci/berjalan tidak dipindah | Ada | `PlanLoader.php:152`, `ScheduleSolver.php:54` |
| Mesin rusak dibuang dari kandidat | Ada (operasi belum terkunci) | `PlanLoader.php:49` |
| **Operasi terkunci di mesin rusak** | **Tidak dipindah** engine; hanya muncul sebagai "Machine down" | `PlanLoader.php:156` (locked = fixed) |
| Operator / crew sebagai kapasitas | **Belum**: langkah manual dianggap 30 menit tanpa batas kapasitas | `ScheduleSolver.php:24,297` |
| Pecah lot | **Belum** | tidak ada di solver maupun `PlanChangeService` |
| Lembur, subkon sebagai tindakan | **Belum** sebagai tombol; subkon dihitung hari lead | `ScheduleSolver.php:297` |
| Jam kerja per mesin, hari libur | Ada | `PlanLoader.php` (calendar), `WorkingCalendar` |
| Beban mesin dan ambang bottleneck | Ada (7 hari ke depan) | `ScheduleSolver.php:369` |
| Test otomatis solver | 12 test, **tanpa test untuk bug antar-mesin** yang diperbaiki | `tests/Unit/Ppic/ScheduleSolverTest.php` |
| Target CETAK 8.000/jam vs tabel mesin 2.544/jam | Layar sudah memakai target mesin; **nilai tersimpan di operasi masih 8.000** dan Grid yang diketik kemungkinan tidak dipakai | `Ppic.php` (targetOn); `PlanLoader.php` rate kandidat dulu |
| Algoritma | **Rakus (greedy) per operasi**, tidak mencari jadwal global terbaik | `ScheduleSolver.php:217-292` |

Catatan kualitas: solver sekarang memilih mesin dengan selesai paling awal per operasi. Itu cepat dan mudah dijelaskan, tetapi bukan "terbaik" secara global. Pilihan untuk naik kelas ada di bagian 6.

## 3. Drag and drop di Gantt

| Kemudahan | Sekarang | Bukti |
|---|---|---|
| Baris mesin yang boleh disorot saat drag | Ada | `gantt.js:149-157` (markRows/canRun) |
| Snap waktu 15 menit | Ada | `gantt.js:18,50` |
| Tidak bisa ditaruh sebelum batas kunci | Ada | `gantt.js:140` (lockedFor) |
| Penanda bentrok sesama baris | Ada (hanya penanda sesudah) | `gantt.js:259` |
| Server menilai dampak sebelum disimpan, kotak dampak | Ada | `PlanChangeService.php:42`, `gantt.js:196-240` |
| Alasan wajib | Ada (B1) | `gantt.js` reason, `PlanChangeService.php:101` |
| **Pratinjau saat drag** (bayangan jam selesai, WO yang jadi telat) | Belum; baru setelah dilepas | - |
| **Snap ke celah kosong terdekat** | Belum | - |
| **Validasi mesin, tooling, bahan sebelum drop** | Mesin: sorotan saja; tooling dan bahan: baru setelah drop | `PlanChangeService.php:42` |
| **Undo** satu langkah sesudah Apply | Belum | - |
| Operator/crew divalidasi | Belum (tidak dimodelkan) | - |

## 4. Re-plan otomatis

| Pemicu | Sekarang | Bukti |
|---|---|---|
| WO baru lewat Create Work Order | Draft baru (EVENT) | `PpicController.php:179` |
| Prioritas (Hot job, Move up) | Draft baru | `PlanChangeService.php:23` |
| Geser di Gantt (Placement officer) | Draft baru | `PlanChangeService.php:101` |
| Release placement | Draft baru | `PlanChangeService.php:214` |
| Mesin rusak atau pulih (downtime) | Draft baru | `MachineDowntimeObserver.php` |
| Malam 02:00 dan awal tiap shift | Draft baru | `routes/console.php:40-43` |
| **CO baru Confirmed / Post to Production dari layar CPDEL** | **Belum**: WO lahir tetapi tidak memicu | `Cpdel.php` memanggil WoGenerationService saja |
| **Bahan datang atau terlambat** (PO diterima/berubah) | **Belum** | tidak ada pendengar |
| **Keterlambatan realisasi** (operasi lewat jadwal) | Hanya daftar "Behind plan", tidak memicu | `Ppic::exceptions()` |
| Tampilan **sebelum / sesudah** untuk pemicu otomatis | **Belum**: Draft lama langsung dibuang dan diganti | `AutoPlanRunner.php:39` |
| Confirm atau Buang tanpa kehilangan Draft sebelumnya | Belum (hanya satu Draft aktif) | `AutoPlanRunner.php:39` |
| Undo sesudah Confirm | Belum | - |

Simulasi yang sudah ada hanya untuk satu WO mendesak (`PlanChangeService.php:168`) dan untuk satu geseran (`:42`).

## 5. Kesesuaian dengan video PPIC-Demo-v1.5

| Yang tampil di video | Status di tmerp | Bukti |
|---|---|---|
| Menu Work Order membuka Create Work Order | Ada (B0) | menu `PROD_WO_LIST` -> `production-wo`, terbukti di layar |
| Create Work Order (satu atau semua) | Ada | `PpicController.php:179` |
| Draft di Gantt, Confirm plan | Ada | `PlanConfirmService.php` |
| Kunci jadwal setelah Confirm | Ada | `PlanConfirmService.php:40` |
| Operator Queue: Play, Start run, Pause, Add, Stop, Problem | Ada (urutan tombol beda dari demo) | `production-operator.blade.php` |
| Problem membuka Maintenance Request terisi, Send Request | Ada | `PpicController.php` (problemUrl) |
| Mesin rusak lalu Re-plan | Sebagian: Re-plan otomatis ada; operasi terkunci tidak pindah | bagian 2 |
| Hot job vs Move up berbeda | Ada | `PlanChangeService.php:23` |
| Geser bar dengan alasan wajib, Placements, Release | Ada (belum diklik di browser) | `gantt.js`, `PlanChangeService.php:197,214` |
| Urgent order: simulate first, Apply dengan Reason | Ada; **Undo sesudah Apply dan pecah lot belum** | `PlanChangeService.php:168` |
| Graf BOP dengan join, jalur kritis | **Belum** | bagian 2 |
| Pemberitahuan ke operator/inventory | **Belum** | - |
| Satu layar kerja | **Belum** (bagian 5) | - |

## 6. Peta menu sekarang dan usulan satu layar kerja

Sekarang: menu Production Scheduling punya 4 tab (Schedule, Material Control, Incoming Purchases, Monitoring); di dalam Schedule ada daftar pengecualian, form prioritas, kartu simulasi, kartu Placements, filter, 4 tampilan (Gantt, Kanban, Calendar, Grid). Create Work Order dan detail WO ada di layar lain (Work Order). Operator Queue, Learning, dan master (Tooling, Setup and Changeover, Wait Rules) menu terpisah.

Usulan **satu layar kerja "PPIC Workspace"** (officer scheduling), bagian-bagiannya:
- **Bilah atas, selalu terlihat:** angka ringkas (operasi, perlu perhatian, telat), nama rencana aktif, dan **spanduk Draft**: "Draft menunggu: sebelum / sesudah" dengan tombol **Confirm** dan **Buang**.
- **Kiri, antrean kerja:** (1) WO baru siap dibuat (Create), (2) Perlu perhatian dikelompokkan dan bisa disaring, (3) Urgent order simulasi.
- **Tengah, papan jadwal:** Gantt sebagai bawaan, pindah ke Kanban/Calendar/Grid dengan satu klik; filter proses dan mesin; drag and drop.
- **Kanan, panel detail yang berubah menurut pilihan:** klik bar = detail WO (dieline, BOP, mesin, BOM, output); klik pengecualian = pilihan tindakan; pemicu otomatis = hasil simulasi (sebelum/sesudah: order telat, utilisasi mesin, operasi bergeser) dengan Confirm / Buang.
- **Bawah, laci yang bisa dibuka:** Material Control, Incoming Purchases, Monitoring, Placements by an officer, log keputusan.
- **Tetap terpisah:** Operator Queue (tablet di mesin), Learning Changes, master.

## 6B. Dua kelompok menu: layar kerja utama dan menu penunjang

Prinsip: officer sehari-hari hanya memakai **satu layar kerja** (bagian 6) dan satu layar lagi untuk operator di mesin. Semua yang jarang diubah dikumpulkan di **satu kelompok "Pengaturan PPIC"** dengan kategori yang jelas, supaya mengubah konfigurasi mudah dan menu harian tetap ringkas.

### Kelompok 1: kerja harian (3 menu saja)
| Menu | Isi |
|---|---|
| **PPIC Workspace** | Create Work Order, jadwal (Gantt, Kanban, Calendar, Grid), simulasi dan Draft, override, detail WO, material, monitoring |
| **Operator Queue** | Tablet di mesin (Play, Pause, Stop, qty, Problem) |
| **Learning Changes** | Peninjauan nilai yang dikoreksi engine (jarang, mingguan) |

### Kelompok 2: Pengaturan PPIC, per kategori (sudah ada di tmerp / belum, berdasarkan kode dan menu ddtsys)
| Kategori | Sudah ada | Belum ada atau perlu dirapikan |
|---|---|---|
| **Mesin dan kapasitas** | Master Machine (`v2:prod-machine`), Machine Model Catalog (`prod-machine-model`); jam kerja per mesin di form mesin | Master Machine muncul di 3 menu berbeda (Production, Maintenance, Sales); cukup satu pintu. Kemampuan mesin per proses (kecepatan, setup, batas ukuran) ada di tabel `prod_process_machine` tetapi layarnya tersebar di form Master Process/Machine |
| **Proses dan rute** | Master Process (`prod-process`), Routing Template (`prod-routing`), BOP Rule Setting (`prod-bop-rule`) | Penanda subkon (`inhouse`) dan bottleneck per proses ada di kolom, belum jelas di layar; bottleneck tidak dibaca planner |
| **Tooling dan setup** | Tooling Master (`prod-tooling`), Setup and Changeover (`prod-changeover`) | Menit setup dasar per mesin hanya di tabel kemampuan; belum ada layar tunggal "setup per mesin" |
| **Waktu kerja dan kalender** | Master Shift (`md-shift`), Holiday Calendar (`holiday-calendar`), Office Hours (`office-hours`), Shift Roster (`hrm-roster`) | Jadwal pemeliharaan preventif (PM) dari modul Maintenance **belum dibaca** planner baru; hanya status mesin saat ini yang dibaca |
| **Aturan prioritas dan penjadwalan** | Rule Library (`prod-rule-library`, tabel `prd_scheduling_rule`), Wait Rules (`prod-wait-rule`) | Rule Library dipakai mesin penjadwal **lama** (`SchedulingRuleEngine`), belum dibaca planner baru. Bobot skor (urgensi, setup, tier) hanya ada sebagai konstanta, tanpa layar khusus |
| **Parameter engine** | Konstanta `PPIC_*` di Setup > Constants (kunci shift, bobot, jendela, horizon, ambang bottleneck, shift per hari, jam per shift, setup dasar, batas pembelajaran) | Layar khusus yang mengelompokkan 14 konstanta ini dengan penjelasan dan nilai bawaan; hari mulai (07:00) masih di file konfigurasi, bukan konstanta |
| **Orang (operator dan crew)** | Master Crew (`prod-crew`) | Planner baru **tidak memakai** crew sebagai kapasitas; keahlian/sertifikasi operator per proses belum ada sebagai layar |
| **Customer dan prioritas** | Tier di Master Customer (`md-customer`) | Aturan "customer prioritas" selain tier belum ada |
| **Bahan dan MRP** | Material Planning, Material Booking, Pre-Booking, Usage Standard, Purchase Lead Time | Aturan konfirmasi booking otomatis belum ada |
| **Output dan mutu** | Scrap Types (`prod-scrap`), Production Output | - |
| **Pembelajaran** | Setup and Changeover (status nilai, beku), Learning Changes | Batas pembelajaran hanya sebagai konstanta |

Saran jumlah menu: kelompok 1 = **3 menu**; kelompok 2 = **sekitar 10 menu terkelompok** dalam satu cabang "Pengaturan PPIC" dengan sub-judul sesuai tabel di atas (Mesin, Proses, Tooling dan setup, Kalender, Aturan, Parameter, Orang, Bahan). Menu yang sama di tiga tempat (Master Machine) diganti satu pintu.

## 6C. Pengelompokan otomatis job dengan mall (pisau/pond) yang sama

**Istilah.** "Mall" di plong/pond = pisau atau matras cetakan. Padanan di tmerp: `die` / `knife` / `dieline_ref` (kode pisau). Di data ada beberapa tempat menyimpannya (lihat poin 3).

### 1. Yang dilakukan engine sekarang (bukti kode)
- Setup antar dua job di mesin plong **menghitung** pasang pisau hanya bila kode pisau berbeda dari job sebelumnya: `SetupTimeEngine.php:68-70`. Jadi dua job berurutan dengan mall sama memang tidak membayar setup pisau.
- Pemilihan mesin memasukkan setup itu ke waktu selesai (`ScheduleSolver.php` bestSlot), jadi job bermall sama **cenderung** ditempatkan setelah job bermall sama bila celahnya kebetulan bersebelahan.
- Pengurutan berkelompok (`smooth`) memakai jarak setup, termasuk pisau: `ScheduleSolver.php:179`. **Tetapi** jaraknya dibaca dari operasi **pertama** WO (`:169,173`), yaitu CETAK, yang tidak membawa kode pisau (kode pisau hanya dibawa operasi PLONG: `PlanLoader.php:138`). Akibatnya pengelompokan berdasarkan mall **tidak pernah aktif** di jalur ini.
- Pengelompokan hanya berlaku untuk WO dengan due date berdekatan (jendela 8 jam) dan lapis normal; tidak sadar mesin. Tidak ada konsep "kelompok" yang tampil, dihitung penghematannya, atau bisa di-override.
- Kesimpulan: engine **menghitung** setup pisau, tetapi **belum mengelompokkan** job bermall sama secara sengaja.

### 2. Rancangan pengelompokan
- **Aturan kelompok:** operasi PLONG dengan kode pisau sama, mesin eligible yang sama, tidak lewat batas **toleransi due date** (bawaan 2 hari kerja; konstanta yang bisa diubah) dan **tidak boleh membuat order mendesak (Hot / Critical Ratio kurang dari 1) telat**.
- **Cara kerja:** sesudah urutan prioritas ditetapkan, job bermall sama yang berdekatan di mesin yang sama dirapatkan menjadi satu urutan; yang melanggar batas dilepas dari kelompok.
- **Ditampilkan di simulasi Draft sebelum Confirm:** jumlah kelompok, "pasang pisau berkurang N kali, hemat M menit", dan daftar job per kelompok; bila ada job yang ditunda, ditunjukkan berapa jam dan apakah masih sebelum due date.
- **Override oleh officer:** lihat kelompok di panel kanan Workspace; **pecah kelompok** (keluarkan satu job) atau **gabungkan** manual; keduanya wajib alasan dan tercatat di log, sama seperti Placement officer.

### 3. Data mall di ddt* (hasil baca)
| Tempat | Terisi | Catatan |
|---|---|---|
| `prod_part.die_code` | 5 dari 50 part | satu-satunya yang dibaca planner (`PlanFacts.php:53`) |
| `prod_part.dieline_ref` | 0 dari 50 | kolom ada, kosong |
| `prod_layout_group.knife_code` | 0 dari 34 | ProDev menyimpan ukuran pisau tetapi kode kosong |
| `prod_master_card.knife_code` | 5 dari 7 | di kartu master |
| `prod_diecut_legacy`, `prod_card_diecut_legacy` | ada tabel | kode pisau lama (`diecut_code`), belum dipakai planner |
| `prod_tooling_asset` | 0 baris | master tooling baru; kunci cocok lewat `dieline_ref` |
| `prd_wo.dieline_ref` | **0 dari 11 WO** | **akibatnya kode pisau kosong untuk semua WO dummy sekarang** |
| Dipakai bersama lintas produk | **0** pada `prod_layout_group.knife_code` | belum ada contoh nyata mall bersama di data dummy |

**Yang perlu diputuskan bersama:** sumber kode mall yang dianggap benar (die_code part, kartu master, atau tabel pisau lama), dan melengkapi data (itu menyentuh data ProDev, perlu izin Jo). Tanpa kode pisau yang terisi, pengelompokan tidak punya bahan.

## 6D. Urutan warna cetak dan biaya ganti warna (digabung dengan pengelompokan mall)

Dua hal berbeda, jangan dicampur:
- **Urutan unit warna dalam satu job** (misalnya K, C, M, Y): keputusan prepress. Engine hanya **membaca**, tidak mengubah.
- **Urutan job di mesin** yang menentukan berapa kali dan seberapa besar cuci (wash-up) antar job: ini yang bisa dioptimalkan engine.

### Yang ada di tmerp sekarang (bukti kode dan data ddt*)
| Hal | Kenyataan | Bukti |
|---|---|---|
| Warna per unit di ProDev | **Ada**: `prod_part_ink` menyimpan lintasan, nomor unit, nama tinta, persentase tutupan, kode warna. 40 baris untuk 10 dari 50 part (di Inquiry, `iqp_part_ink` 40 baris) | tabel `prod_part_ink` di ddt* |
| Yang dibaca planner | **Hanya jumlah warna** dari `cfg_color` ("4/0" menjadi 4). Tinta khusus dan jenis tinta **tidak dibaca**: `spots` kosong dan jenis tinta tetap CONVENTIONAL | `PlanLoader.php:136`, `PlanFacts.php` (colours) |
| Biaya ganti warna | Tiga kemungkinan saja: sama (0), tambah spot (cuci ringan 20 menit, 30 lembar), lainnya (cuci penuh 38 menit, 60 lembar). Bukan matriks per pasangan tinta | `SetupTimeEngine.php:94-112`, nilai awal di `config/ppic.php` |
| Nilai cuci bisa diubah per mesin | Ada, lewat Setup and Changeover (jenis perubahan COLOR) | `ChangeoverLookup` |
| Urutan terang ke gelap atau kelompok per substrat/tinta | **Belum**: hanya jarak setup (gsm, ukuran, jumlah warna, jenis tinta, pisau) di pengurutan berkelompok, dan itu terbatas seperti di bagian 6C | `ScheduleSolver.php:167-181` |
| Persentase warna spesial | Belum ada di data; harus dihitung dari `prod_part_ink` (nama tinta dan kode warna) | - |

### Rancangan
1. **Baca warna per unit** dari `prod_part_ink` ke fakta job (himpunan tinta, bukan hanya jumlah), supaya "warna sama" benar-benar sama.
2. **Matriks biaya transisi** menggantikan tiga kemungkinan: sama 0 menit; cuci ringan (tinta berkeluarga sama, tambah spot di akhir); cuci penuh; ganti keluarga tinta (konvensional ke UV); varnish; pisau. **Semua angka bobot adalah asumsi** sampai ada job nyata; nilainya di Setup and Changeover dan dikoreksi Learning.
3. **Pengelompokan** (digabung dengan 6C): kelompok = substrat sama, himpunan tinta serupa, pisau sama; urutan dalam kelompok terang ke gelap sebagai **usulan awal** (belum terbukti untuk proses Jo; satu sumber menyebutnya, sumber lain tidak terbuka). Batas: toleransi due date; Hot dan Critical Ratio di bawah 1 tidak boleh telat.
4. **Simulasi Draft** menampilkan strip warna per job, jumlah cuci sebelum dan sesudah, dan menit yang dihemat, sebelum Confirm.
5. **Override:** officer bisa memecah atau menggabung kelompok dengan alasan wajib.

### Data yang belum ada atau perlu dilengkapi
- Warna per unit untuk 40 dari 50 part dummy (ProDev; butuh izin Jo bila dilengkapi).
- Keluarga tinta dan jenis tinta (konvensional/UV) per tinta: belum ada kolomnya.
- Persentase warna spesial per produk: perlu hitung dari `prod_part_ink`; belum ada.
- Pertanyaan ke Jo yang sedang di thread riset: jenis mesin dan tinta, serta persentase warna spesial.

## 7. Urutan kerja yang disarankan (setelah Jo setuju)

1. **Satu Draft simulasi per pemicu dengan sebelum/sesudah, Confirm atau Buang** (inti permintaan Jo). Draft lama tidak ditimpa sampai Anda Confirm; tambahkan pemicu CO baru dan bahan datang/terlambat.
2. **Undo sesudah Apply dan sesudah Confirm** (riwayat rencana yang bisa dikembalikan).
3. **Layar kerja tunggal** (bagian 6), memakai bagian-bagian yang sudah ada.
4. **Drag and drop lebih mudah:** bayangan hasil saat drag, snap ke celah kosong, validasi mesin/tooling/bahan sebelum drop.
5. **Pengelompokan mall dan warna** (6C dan 6D): satukan sumber kode pisau, baca warna per unit, matriks biaya ganti warna, kelompokkan di planner, tampilkan penghematan di simulasi, override dengan alasan.
6. **Engine:** pecah lot, join antar komponen, operator/crew sebagai kapasitas, memindahkan operasi terkunci di mesin rusak dengan persetujuan, test otomatis untuk bug antar-mesin, dan penyelarasan target.
7. Opsional: pencarian jadwal yang lebih baik dari rakus (mis. coba beberapa urutan dan pilih terbaik).

## 8. Dua keputusan untuk Jo

1. **Perilaku Draft otomatis:** setiap pemicu (WO baru, bahan telat, mesin rusak) membuat Draft simulasi yang menunggu Confirm. Setuju bahwa **jadwal utama tidak pernah berubah sendiri**, dan Draft otomatis hanya jadi usulan? (Saran saya: ya.)
2. **Operasi terkunci di mesin rusak:** boleh engine **mengusulkan** memindahkannya (tetap menunggu Confirm), atau tetap hanya ditandai untuk Anda geser sendiri? (Saran saya: usulkan, tetap Confirm.)
