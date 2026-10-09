# Fakta nyata modul PPIC (hasil baca kode, 9 Oktober 2026)

Hanya dibaca dari kode dan database dummy ddt* (menu dibaca dari ddtsys). Tidak ada tulis database, ProDev tidak disentuh.
Tiap butir ditandai sumber. Kode = path di repo tmerp. "Diverifikasi" = saya menjalankannya di ddt* hari ini; "dibaca" = hanya dari kode.

## 1. Menu dan jalur klik (sumber: tabel sys_app_menu di ddtsys, kolom label dan target)

| Layar | Jalur klik | Target |
|---|---|---|
| Production Scheduling (Schedule, Material Control, Incoming Purchases, Monitoring; tampilan Gantt, Kanban, Calendar, Grid) | Production > Planning > Production Scheduling | v2:production-scheduling (PROD_MPS) |
| Work Order (daftar + kartu Create Work Order) | Production > Planning > Work Order | v2:prod-work-order lalu tombol Open ke layar WO PPIC. Catatan: kartu Create Work Order ada di layar slug production-wo; menu PROD_WO_LIST menunjuk v2:prod-work-order (grid WorkOrderGrid) |
| Layar Work Order PPIC (dieline, BOP, mesin, BOM, jadwal, output) | dari Work Order > Open, atau slug production-wo?id=N | resources/views/v2/screens/production-wo.blade.php |
| Material Planning (MRP) | Production > Planning > Material Planning (MRP) | v2:material-planning |
| Material Booking / Pre-Booking | Production > Planning > Material Booking / Pre-Booking | v2:material-booking, v2:material-prebooking |
| Rule Library, Demand Forecast (MPS) | Production > Planning > Rule Library / Demand Forecast (MPS) | v2:prod-rule-library, v2:demand-forecast |
| Learning Changes | Production > Planning > Learning Changes | v2:production-learning (PROD_LEARNING) |
| Operator Queue | Production > Output > Operator Queue | v2:production-operator (PROD_OPERATOR) |
| Production Output | Production > Output > Production Output | v2:prod-work-order (PROD_OUTPUT menunjuk grid yang sama dengan Work Order) |
| Tooling Master | Production > Master > Tooling Master | v2:prod-tooling (PROD_MST_TOOLING) |
| Setup and Changeover | Production > Master > Setup and Changeover | v2:prod-changeover |
| Wait Rules | Production > Master > Wait Rules | v2:prod-wait-rule |
| Master Machine, Master Process, Routing Template, BOP Rule, Machine Model | Production > Master > ... | v2:prod-machine, prod-process, prod-routing, prod-bop-rule, prod-machine-model |
| Customer Tier | Master Data > Master Customer > form Sales, kolom Customer Tier (Production priority) | v1 pages::master.customer (resources/views/pages/master/customer.blade.php) |
| Maintenance Request (tujuan tombol Problem) | Maintenance > Breakdown Maintenance > Maintenance Request | v2:maintenance-request?new=1&asset_id&symptom |
| Pengaturan penjadwalan | Setup > Constants, kategori PRODUCTION, grup PPIC | sys_constant PPIC_* |

**TEMUAN PENTING (diverifikasi di ddt*):** menu Production > Planning > Work Order menuju slug prod-work-order, yaitu daftar WorkOrderGrid saja; halaman itu TIDAK memuat kartu Create Work Order (0 kemunculan). Kartu itu hanya ada di slug production-wo, yang tidak punya baris menu. Jadi lewat menu, Create Work Order tidak terjangkau. Perbaikan yang diusulkan (belum dikerjakan, karena tugas ini baca-saja): ubah target menu PROD_WO_LIST menjadi v2:production-wo, atau pindahkan kartu ke grid WorkOrderGrid.

Fakta aneh: dua menu berbeda (Work Order dan Production Output) menunjuk slug yang sama v2:prod-work-order. Menu lama PROD_MON_WO (Kanban) dan PROD_MPS_SHEET dinonaktifkan (migration 2026_10_09_000020), karena Kanban dan Grid kini tampilan di Production Scheduling.

## 2. Field dan parameter input per layar

### 2.1 Work Order > Create Work Order (production-wo tanpa id)
- Daftar (baca): Customer Order, Customer, Product, Quantity, Ship Date, Product Development (Complete n parts / simple). Sumber: `App\Support\V2\Data\Ppic::pendingDeliveries`.
- Input: select "Delivery plan line" lalu Create Work Order, atau Create all. Sumber: `PpicController::createWo`.
- Syarat baris muncul: status PLANNED, posted_at kosong, belum ada prd_wo dengan source_delivery_id itu, CO CONFIRMED (via `Cpdel::query`).

### 2.2 Work Order PPIC (production-wo?id=)
- Header (baca saja): status, qty, due date, priority, Ups per sheet, Press sheet, Board gsm, Colours, Dieline, Customer tier, Source, Layout. Semua dari prd_wo (diisi `WoPlanningEnricher` dari ProDev). Tidak ada kolom input untuk mengubahnya.
- Tombol: Back, Open in Gantt, Refresh from ProDev (`PpicController::refreshWo`).
- Form prioritas: Priority (Hot job / Move up), Reason (wajib). `PpicController::prioritize`.
- Tabel BOP (baca): seq, process, machine, qty in, good out, target/jam, waste qty, spoilage qty, setup min, run h, start, finish, status, gates, output.
- Mesin yang bisa mengantre (baca): eligible Yes/No, alasan, rank, target, setup. Sumber prd_wo_operation_machine.
- BOM (baca) + tautan Open Material Planning dan Substitute material (ke layar WO lama production.wo.entry).
- Input output: Operation, Good quantity, Note (`PpicController::output`).

### 2.3 Production Scheduling
- Tombol: Re-plan, Confirm plan (muncul bila ada Draft), Undo (membuang Draft).
- Form prioritas: Work Order, Priority, Reason.
- Filter: Process, Machine, Search. Tampilan: Gantt, Kanban, Calendar, Grid.
- Gantt: drag bar (waktu, pindah mesin pada baris) memanggil `PpicController::impact` lalu `moveOp`; alasan yang tersimpan tetap "Moved on the Gantt" (tidak ditanyakan ke user). Kanban: drag ditolak server (`kanbanMove`).
- Grid (sheet): kolom target per jam, waste, spoilage bisa diketik, disimpan lewat `sheetSave` ke rate_band_value, waste_qty, tolerance_qty.
- Tab lain: Material Control, Incoming Purchases, Monitoring (baca).

### 2.4 Operator Queue
- Pilih mesin (tombol per mesin yang punya antrean terkonfirmasi).
- Play (setup lalu run), Pause (Reason: Waiting material, Break, Machine problem, Quality hold, Waiting crew, Other), Add quantity (Good quantity), Stop (Good, Waste, Reject, Reject reason scrap type, What changed at setup), Problem (membuka Maintenance Request terisi). Sumber `OperatorTimerService`, `PpicController::op*`.

### 2.5 Learning Changes
- Learn now, Put back and freeze (pilih perubahan), tabel nilai changeover dan log.

### 2.6 Master
- Tooling Master: Code, Type, Dieline Reference, Size, Location, Status, Planned/Used Impressions, Setup First Run, Setup Repeat Run, Active. (`ToolingLookup`)
- Setup and Changeover: Machine, Change, From, To, Extra Minutes, Extra Waste, Value Status, Samples (baca), Frozen, Note, Active. (`ChangeoverLookup`)
- Wait Rules: After Process (nama diawali), Reason, Wait (min), Active. (`WaitRuleLookup`)
- Konstanta PPIC_*: LOCK_SHIFTS 1, WEIGHT_URGENCY 0,50, WEIGHT_SETUP 0,35, WEIGHT_TIER 0,15, SIMILAR_WINDOW_HOURS 8, HORIZON_HOURS 72, HORIZON_DAYS 60, BOTTLENECK_LOAD_PCT 90, DEFAULT_SHIFTS 2, HOURS_PER_SHIFT 8, BASE_SETUP_MIN 15, LEARN_MIN_SAMPLES 5, LEARN_MAX_STEP_PCT 20, LEARN_BLEND 0,3. (migration 2026_10_09_000030, `PpicSettings`)
- Jam kerja per mesin: shifts_per_day, hours_per_shift di Master Machine (v1 pages::master.machine); hari libur dari kalender hari libur (SysHoliday). Hari mulai: config ppic.day_start 07:00 (env PPIC_DAY_START), bukan konstanta.

## 3. Cara kerja engine (sumber kelas; rumus dibaca dari kode)

1. **WoGenerationService** (app/Services/Scheduling/WoGenerationService.php, lama): satu WO per part inquiry; operasi dan bahan dari SpecEngine untuk qty itu; tanpa inquiry/part = WO sederhana tanpa routing. Dipanggil `Cpdel::post`.
2. **WoPlanningEnricher** (Plan/WoPlanningEnricher.php): mengisi ups, ukuran lembar, gsm, warna, dieline, tier dari PlanFacts; per operasi mengisi process_id, satuan (LBR sampai plong, PCS sesudah), qty_out (qty langkah berikut; bila satuan beda dibagi ups, dibulatkan atas), daftar mesin dan alasan tolak (MachineCapabilityService + aturan warna per lintasan). Target/setup pertama dari mesin eligible pertama. rate_band_value hanya diisi bila masih kosong.
3. **GateService** (Plan/GateService.php): material dari hasil MaterialPlanningEngine per CO dan part: status SHORT = Blocked, PEGGED/LATE PO atau PR = Waiting dengan tanggal terakhir dari alokasi non-stok. Tooling: tooling_type CUTTING_DIE bila proses butuh pisau, PLATE_SET bila butuh plat; READY/IN_USE lolos, lainnya Blocked; tidak terdaftar lolos dengan catatan. Artwork selalu Ready.
4. **SetupTimeEngine** (Plan/SetupTimeEngine.php): total = dasar + perubahan + pasang tooling. Perubahan menurut tahap: PRESS (warna, jenis tinta, bahan, ukuran, plat, pisau), DIE (bahan, ukuran, pisau), FEED (ukuran, bahan). Nilai dari config ppic.setup (warna tambah spot 20 menit/30 waste, cuci penuh 38/60, tinta 55/80, bahan 15/20 selisih gsm 20, ukuran 12/15, pisau 15, plat 10); dasar dari konstanta.
5. **ScheduleSolver** (Plan/ScheduleSolver.php, murni): rangking tiga lapis: lapis 0 Hot job, lapis 1 Critical Ratio kurang dari 1 (CR = sisa waktu kerja / sisa pekerjaan), lapis 2 menurut due date lalu tier; di dalam lapis 2 job yang due date-nya dalam jendela 8 jam diurut skor = 0,50 x urgensi + 0,35 x (1 - jarak setup/jarak terjauh) + 0,15 x ((4 - tier)/3), urgensi = 1 - min(1, slack/72). Penempatan: tiap operasi berurutan pada mesin kandidat dengan selesai paling awal, lalu rank, lalu beban terendah; slot dicari di sela blok yang ada; run = ceil(qty masuk / rate x 60); rate kosong = 60 menit; langkah manual 30 menit; subkon = hari lead. Operasi fixed (terkunci/berjalan) tidak dipindah. Pengecualian: BLOCKED, CHAIN_BLOCKED, NO_MACHINE, WAITING, LATE_RISK.
6. **PlanLoader** (Plan/PlanLoader.php): mengumpulkan WO aktif, kandidat mesin eligible (kini membuang mesin BREAKDOWN/MAINTENANCE), aturan tunggu (prod_wait_rule menimpa config wait_after: CETAK 120, VARNISH 45), kalender kerja.
7. **AutoPlanRunner** (Plan/AutoPlanRunner.php): pemicu MANUAL, DAILY, SHIFT, EVENT. Memperkaya WO yang belum punya process_id, memperbarui gerbang, membuang Draft lama, menulis Draft baru dan log AUTO_PLAN.
8. **PlanConfirmService** (Plan/PlanConfirmService.php): Confirm menyalin draft ke kolom nyata, status CONFIRMED, kunci bila mulai sebelum (sekarang + N x menit shift mesin); WO DRAFT/SIMULATED menjadi SCHEDULED; log CONFIRM.
9. **PlanChangeService** (Plan/PlanChangeService.php): prioritize (is_hot_job = true untuk HOT maupun MOVE_UP; hanya label priority beda HOT/HIGH, sehingga di ranking keduanya lapis 0); impact (menolak: sedang jalan/selesai, terkunci tanpa Hot job, mulai di masa lalu, mesin tidak eligible, bahan datang setelah mulai) dan move (menyimpan sebagai penempatan OFFICER lalu re-plan).
10. **OperatorTimerService**, **LearningEngine**, **PlanEvents + MachineDowntimeObserver** (lihat laporan lengkap). Learning: median N job terakhir minus setup dasar; baru = lama + blend x (tipikal - lama), dibatasi 20 persen lama (minimal 1 menit); hanya status ASSUMED/LEARNING dan tidak frozen; hanya dari job selesai sesudah penyesuaian terakhir.

Engine lain yang sudah ada sebelum ini dan dipakai: MaterialPlanningEngine, PdrEngine, SchedulingRuleEngine, SpeedTargetEngine, InterruptionOptionGenerator (app/Services/Scheduling dan Planning; terdaftar di EngineRegistry). Catatan: layar baru memakai AutoPlanRunner/ScheduleSolver, bukan PdrEngine (PdrEngine masih dipakai Scheduling Board v1 yang lama). Hubungan keduanya belum saya telusuri.

## 4. Fasilitas override

### Sudah ada
| Override | Cara | Sumber |
|---|---|---|
| Prioritas WO (Hot job / Move up + alasan) | Form di Production Scheduling dan di layar WO | PlanChangeService::prioritize |
| Pindah operasi (waktu, mesin eligible) dengan simulasi dampak | Drag Gantt; terkunci hanya bila WO Hot job | PlanChangeService::impact/move |
| Jalankan ulang / buang Draft / Confirm | Tombol Re-plan, Undo, Confirm plan | PpicController::run/discard/confirm |
| Ketik target/jam, waste, spoilage | Tampilan Grid | PpicController::sheetSave |
| Baca ulang dari ProDev | Tombol Refresh from ProDev | PpicController::refreshWo |
| Nilai changeover, tunggu, tooling | Master Setup and Changeover, Wait Rules, Tooling | Grid Lookup |
| Bobot skor, jendela, horizon, ambang bottleneck, jam kerja default, kunci N shift | Settings > Constants PPIC_* | PpicSettings |
| Kembalikan dan bekukan nilai hasil belajar | Learning Changes | LearningEngine::revert |
| Tier customer | Master Customer | CustCustomer::CUSTOMER_TIERS |
| Substitusi bahan | Tautan ke layar WO lama (alur persetujuan ada di sana) | production.wo.entry |

### Belum ada, atau ada tapi tidak efektif (fakta dari kode)
- **Target yang diketik di Grid kemungkinan tidak dipakai planner:** PlanLoader mengambil rate dari kandidat mesin dulu dan memakai rate_band_value hanya bila rate kandidat kosong (Plan/PlanLoader.php baris sekitar 118). Waste dan spoilage yang diketik tidak dibaca solver. Belum saya uji.
- Alasan pada drag Gantt tidak ditanyakan: tersimpan teks tetap "Moved on the Gantt".
- Move up dan Hot job sama di ranking (keduanya lapis 0).
- Tidak ada override untuk: mesin yang ditandai tidak eligible (memaksa pakai), ups/ukuran lembar/board/warna di WO, due date WO, pecah lot, lembur, kirim ke subkon (teks pengecualian menyebutnya tetapi tidak ada tombol), kunci jumlah shift per run (hanya konstanta global), ambang bottleneck per proses (kolom is_bottleneck ada di prod_process tetapi tidak dibaca planner), flag inhouse pada proses sebagai pemicu subkon sudah dibaca (PlanLoader) tetapi belum ada layar khusus selain Master Process.
- Booking material otomatis terkonfirmasi bila stok cukup (keputusan 2): saya tidak mengimplementasikannya; belum saya cek apakah MaterialPlanningEngine sudah melakukannya.
- Gerbang artwork selalu Ready; barang titipan customer dan gerbang kapasitas belum ada.
- PPIC_INVENTORY belum dipakai di layar mana pun.
- Print Work Order resmi belum ada.

## 5. Yang belum sempat saya baca
- Hubungan layar baru dengan Scheduling Board v1 dan PdrEngine (apakah v1 masih dipakai harian).
- Isi MaterialPlanningEngine dan perilaku auto-confirm booking.
- Aturan mesin eligible di MachineCapabilityService (hanya hasilnya yang saya lihat di layar).
- Layar v1 Master Machine (field jam kerja persisnya) dan Master Process (field inhouse, is_bottleneck).
- Perilaku layar jika data ProDev lengkap (dummy 14405/14406 tidak punya ups, ukuran lembar, dieline).
