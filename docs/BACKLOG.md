# BACKLOG — SEMUA YANG SUDAH DIRENCANAKAN TAPI BELUM DIKERJAKAN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-09-02 (permintaan user: "catat dulu dalam satu file... semua
> apa yang harusnya di kerjakan dan belum di kerjakan"). **Satu file untuk
> SEMUA item tertunda lintas modul** — bukan cuma Fase 2 (Report System),
> tapi juga item lama dari sesi-sesi sebelumnya yang belum pernah
> dikumpulkan di satu tempat.
>
> **Sumber**: gabungan dari (a) `docs/*.md` (bagian "yang sengaja belum
> dikerjakan"/GAP di tiap standar & spec Tier), dan (b) memori sesi Claude
> Code sebelumnya (`project_*` notes). Item dari sumber (b) **belum
> di-verifikasi ulang ke kode terkini** — beberapa mungkin sudah
> terselesaikan tanpa tercatat di sini (sudah ditandai kalau saya temukan
> indikasi itu saat menyusun file ini), jadi **cek kondisi kode dulu**
> sebelum mulai kerjakan item apa pun dari sini, jangan langsung percaya.
>
> **Cara pakai**: kalau mau kerjakan 1 item, cari section-nya, baca
> rujukan filenya, verifikasi masih relevan, baru mulai. Kalau sudah
> selesai, HAPUS dari section asalnya di sini dan catat lengkap di
> **[`docs/LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** (file terpisah, bukan
> ditulis di dua tempat) — supaya file ini tetap ramping, cuma isi item
> yang MASIH terbuka.

---

## RINGKASAN STATUS (update 2026-09-03 15:47 WIB, utk didiskusikan — pakai nomor R# saat bahas)

> **Cek otonom 15:47 WIB (`tmerp-backlog-autowork-1700`, sesi `tmerp-79`):**
> ada **3 sesi interaktif lain aktif bersamaan** (`tmerp-b8` mulai ~1 jam
> lalu, `tmerp-0c` ~4 jam, `tmerp-47` ~3 jam) + commit terakhir
> (`f553517`) baru ~55 menit lalu + WIP uncommitted besar di working tree
> (`prodev.blade.php` +750 baris, `costing.blade.php`/`form.blade.php`/
> `prodev-print.blade.php` ikut berubah, komponen baru
> `components/prodev/wizard-bar.blade.php`, migration
> `..._seed_prodev_press_geometry_constants.php`, beberapa mockup form
> baru di `docs/attachments/`). Ini pola yang persis pernah bikin tabrakan
> sebelumnya ([[project_multisession_collision_20260831]]) — jadi sesi ini
> **SENGAJA TIDAK mulai kerja paralel**, cuma baca & rangkum status (sesuai
> aturan task otonom). R1-R30 di bawah masih snapshot dari sesi sebelumnya
> (siang tadi) — kemungkinan sudah ada progres lanjutan (ProDev wizard-bar,
> dst) yang belum ter-commit/ter-log saat cek ini dilakukan, jadi verifikasi
> ulang ke `git log`/kode sebelum lanjut kerja dari sini.

> Snapshot setelah task otonom `tmerp-backlog-autowork-1700` jalan ~13 jam
> nonstop semalam (mulai 2026-09-02 15:45) + lanjutan pagi/siang ini (2 sesi
> paralel — `tmerp-b5` terus kerja BACKLOG, sesi lain kerjakan permintaan
> UI/UAT langsung dari user). Nomor di bawah **beda skema** dari nomor
> section (0-12) di bawahnya — sengaja, supaya "bahas R3" tidak ketuker
> sama "section 3 SALES". Update lagi tiap ada progres besar.

**SUDAH SELESAI** (30+ commit total, semua terverifikasi test, bukan asumsi):

- **R1.** ProDev + Product Costing Engine (misi utama) — TUNTAS. Skema DB,
  Engine Tingkat 1+2, layar workbench, cetak Master Card, 4 tipe part
  Costing yang tadinya belum ada, toggle Manual/Ambil-Data.
- **R2.** Grid tech-debt `class="grid"` — 9 lokasi/6 file diperbaiki.
- **R3.** Win-Rate Sales report (baru).
- **R4.** Outstanding PV report (baru).
- **R5.** Cetak PO/PR/PQ **+ Cetak Nota CO** — 4 dokumen transaksi lengkap.
- **R6.** BOM Qty Kebutuhan — dibagi UP count imposition (bug nyata).
- **R7.** Rate Band task MANUAL **+ prioritas qty_good F15 di atas qty
  target** (§6) — 2 fix, keduanya SELESAI & diverifikasi (termasuk
  fallback legacy kalau F15 kosong).
- **R19.** Sidebar Sales: "Imposition Engine"→"Pro-Dev",
  "Product Costing"→"Pro-Cost" (repoint ke layar ProDev baru + fix gap
  akses modul PROD utk staf/supervisor Sales).
- **R20.** Dropdown profil direstrukturisasi jadi grup collapsible
  "Configuration" (Tema+Bahasa+User Configuration+Aktivitas saya); sidebar
  kiri "Configuration"→"Settings" (Security tetap).
- **R21.** 7 akun contoh UAT lintas 4 level (operator/supervs/
  4x manager per-bidang/direksi, password sama) + panel referensi di
  layar login.
- **R22.** [BARU] `StockConfirmService` — koreksi dokumentasi, ternyata
  sudah lengkap (`StockPostingService`), bukan gap nyata.
- **R23.** [BARU] `MachineCalendarService` — sadar hari libur nasional
  (`sys_holiday`) + jam kerja per-mesin dari `emp_shift_roster`+
  `emp_shift_detail` (fallback jam default kalau mesin tak pakai roster).
- **R24.** [BARU] Master Card tab WO Detail — artwork nyata (query
  `prod_part_artwork` + fallback referensi ProDev), bukan placeholder statis.
- **R25.** [BARU] `SchedulingRuleEngine::applySequencing()` — terapkan
  SEMUA rule SEQUENCING aktif (bukan cuma rule pertama), chained sort.
- **R26.** [BARU] Reroute-Subkontrak dialog "Sisip Hot Job" — kini bisa
  benar-benar di-apply (PO Subkontrak nyata terbit), termasuk perluasan
  state machine `PrdWoOperation::TRANSITIONS` (keputusan arsitektural
  otonom, dicatat & low-risk).
- **R27.** Dropdown avatar (Pesan & Info + Akun) jadi data-driven dari
  `sys_user_menu` — QUEUE item #9 lama akhirnya tuntas.
- **R28.** Shell responsive di layar sempit (<768px, `qc_feedback.md`) —
  sidebar jadi overlay, topbar/avatar dropdown tidak lagi kegencet.
- **R29.** Costing Hub: tambah Ongkos Kupas + pisah QC/Packing (gap analysis
  vs legacy ScriptCase) + default periode Accounting/Stock Opname/Payroll
  di `sys_constant` + keputusan desain enkripsi Payroll (`VERBA_SANCTA`).
- **R30.** ProDev Tool #2 (Best Sheet Finder) + Tool #4 (Die-Rule Estimator)
  + Tool #10 (Intra-Product Part Combiner) + alokasi biaya proporsional
  multi-part di Costing — SEMUA §0 follow-up TUNTAS (kecuali item DC+SHEET
  formula yg memang menunggu Excel sumber, bukan kerjaan kode).

**MASIH TERBUKA** (kategori besar, lihat section angka di bawah utk detail):

- **R9.** Fase 2 Report System — didesain lengkap, TAPI 0 layar dibangun,
  pilot module belum ditunjuk user. Lihat §1.
- **R10.** Sales — Marketing Management (modul baru, 0%), klarifikasi
  manual input CO/Costing. Lihat §3.
- **R11.** Procurement — Purchase Receive 3-alur (baru 1/3), 12 kategori
  COA kosong (`StockConfirmService` sudah dicoret, lihat R22). Lihat §4.
- **R12.** Production Scheduling — sisa item makin sedikit (Simulation
  Sandbox, WO-part convergence, F10-F20, Decision Feedback learning loop
  [sengaja ditunda, butuh data historis dulu], MTS auto-replenishment).
  Lihat §6.
- **R13.** Finance — Fixed Asset (0%), Auto-jurnal DO/Invoice, seluruh
  AR/AP belum ada. **Payroll SENGAJA di-skip total** (aturan CLAUDE.md,
  butuh keputusan desain enkripsi dulu — TAPI materi referensi baru sudah
  masuk repo `docs/payroll_timeattendance/`, belum diverifikasi/dipakai).
  Lihat §7.
- **R14.** HRM — `org_position` mapping, Flutter mobile channel. Lihat §8.
- **R15.** Migrasi Filament `/admin` — baru HRM, modul lain 100% shell
  lama. Lihat §9.
- **R16.** Tech debt — iframe belum ikut tema, WYSIWYG print editor. Lihat
  §10. (`sys_user_menu` SELESAI 2026-09-03, dicoret dari sini.)
- **R17.** Rename semua menu ke Bahasa Inggris — diputuskan, belum
  dieksekusi (`tmerp-b5` sengaja TIDAK mengeksekusi ini otonom, dianggap
  perubahan UX besar yang perlu sign-off user dulu). Lihat §11.
- **R18.** Katalog §12 — banyak item Procurement/Finance/HR/Delivery
  eksplisit di luar scope v1, plus keputusan bisnis Inquiry/CO yang masih
  menggantung (§12.2).
- **R31.** [BARU 2026-09-04] Dokumentasi per-layar (md per screen, utk user
  koreksi) + flow per-modul + standarisasi UI/UX & detail komponen
  (termasuk admin dashboard Filament) — belum mulai. Lihat §13.

---

## 0. PRODUCT DEVELOPMENT — FOLLOW-UP FASE 1 (2026-09-02)

Fase 1 ProDev SUDAH SELESAI & LIVE (lihat `docs/LOG_JOB_DONE.md` tanggal
2026-09-02 "ProDev Fase 1 TUNTAS", spesifikasi lengkap
`docs/inquiry_product_costing/productdevelopment_tier1.md`). Item di bawah
ini ada di katalog tools §4.3 dokumen itu tapi TIDAK termasuk checklist
"Fase 1 SELESAI kalau" eksplisit — follow-up, bukan blocker:

- **DC+SHEET/DC+SF formula** (`CostingEngine::calculateDcSheetPart()`) masih
  DITANDAI belum diverifikasi ke Excel/sumber asli (lihat docblock class) —
  kalau ada Excel riil kasus DC+SHEET/DC+SF, cross-check dulu sebelum dipakai
  harga produksi sungguhan.

---

## 1. FASE 2 — SISTEM REPORT 4 LEVEL x BIDANG (permintaan user 2026-09-02)

> **UPDATE sore 2026-09-02**: sebagian besar keputusan desain di section
> ini **SUDAH DIKUNCI** lewat diskusi lanjutan dgn user (bukan lagi
> "terbuka") — 3 dokumen jadi rujukan lengkap, JANGAN baca cuma section
> ini: **`docs/reportstandard.md`** (dokumen INDUK — 4 level, matrix
> level x bidang §2.3, mekanisme kode supervisor §3, keputusan chart),
> **`docs/topmanagementreport_tier1.md`** (katalog laporan detail level
> Top Management per domain), **`docs/security_brief.md`** §6 (sisi
> akses/menu-beda-per-level dari model yang sama). Ringkasan di bawah ini
> CUMA POINTER, bukan isi lengkap.

**Model final**: 2 sumbu independen — **Level** (`sec_group.mgmt_level`
1-5: Staff/Supervisor/Manager/GM/Direksi — kolom SUDAH ADA, komentar DB-nya
sendiri sudah define skala ini) x **Bidang** (Sales/Purchasing/Inventory/
Production/Finance & Accounting/HR). GM = grup `OPS_MGR` yang sudah ada
(BUKAN grup baru). Level 1-3 terkurung 1 bidang, Level 4 (Top Management,
Direksi+GM sekaligus) lintas SEMUA bidang. Detail penuh tiap sel matrix
(bidang x level, status ADA/belum per domain): `reportstandard.md` §2.3.

**Yang SUDAH diputuskan** (bukan lagi open item):
- Akses per level → reuse `mgmt_level` (BUKAN dimensi baru).
- Library grafik → **Chart.js via CDN**.
- Kode otorisasi Supervisor (bukan bagian katalog awal, muncul dari
  penjelasan user 2026-09-02) → alur "Supervisor generate kode sekali-pakai,
  Data Entry ketik utk buka akses edit sementara" — detail teknis
  (durasi, tabel/session, audit trail) **masih perlu didetailkan** saat
  mulai implementasi, tapi POLA-nya sudah dikunci.
- Menu beda per level → **kebutuhan BARU dikonfirmasi**, ternyata BUTUH
  perluasan skema (`sys_app_menu` belum punya gating per-item by
  `mgmt_level`, cuma warisan dari modul induknya) — lihat
  `security_brief.md` §6.2, JANGAN diimplementasi sepihak (ubah tabel
  menu inti, dampaknya ke semua menu existing).

**Yang MASIH terbuka** (`topmanagementreport_tier1.md` §5, `reportstandard.md` §5):
1. Lokasi menu laporan (grup "Laporan Eksekutif" lintas-modul vs tersebar).
2. Engine pivot/agregasi — **dicek 2026-09-02**: `HasCustomizableGrid`
   (grid custom view/sort/group-by yang sudah ada) TERNYATA group-by-nya
   cuma VISUAL, BUKAN agregasi SUM/COUNT — TIDAK bisa langsung jadi
   fondasi, harus dibangun baru.
3. Refresh data — real-time vs snapshot periodik.
4. **Katalog laporan Production** — `topmanagementreport_tier1.md` TIDAK
   cakup domain ini (cuma Sales/Procurement-Inventory/Finance/HR) — kalau
   Production jadi pilot, katalognya harus ditulis dulu dari nol.

**Koreksi atas catatan lama**: `topmanagementreport_tier1.md` §4.1 bilang
"laporan pipeline Customer Order belum bisa dibangun — CO belum ada layar
sama sekali" (per 2026-08-27) — **ini sudah TIDAK BENAR**, `co/entry.blade.php`
+ route `co.entry` sudah live. Laporan pipeline CO kemungkinan sudah bisa
dibangun, re-scope kalau diminta.

**Pilot pertama BELUM ditunjuk** ("saya tentukan sendiri nanti", user
2026-09-02) — **belum ada 1 layar report/kode-supervisor pun dibangun**,
JANGAN mulai modul/bidang mana pun tanpa arahan eksplisit.

---

## 2. FASE 1 (2026-09-02) — SISA YANG SENGAJA DITUNDA

Dari kerja Configuration/branding hari ini (`docs/configurationstandard.md`,
`docs/admindashboardstandard.md` §10-12) — sisa yang genuinely masih terbuka
(item selesai sudah dipindah ke History Pengerjaan):

- **Email template** (`resources/views/emails/portal/verify-email.blade.php`)
  — SENGAJA tetap hardcode "TELASIH MAKMUR" (bukan lupa): ini email portal
  karir ke CALON KARYAWAN eksternal, representasi NICKNAME company (sama
  alasan `layouts::portal.blade.php`), bukan APP_NAME aplikasi internal.
- **`sys_constant_category`** baru punya 5 kategori (Branding/Finance/
  Production/Procurement/Inventory) — kategori lain (mis. HRM, kalau nanti
  ada konstanta khusus HR) belum ditambahkan, tambah manual saat perlu.
- **Lapis kosmetik tema dashboard Filament** — mekanismenya sudah benar
  (lihat History Pengerjaan), TAPI belum ada screenshot visual browser
  asli (cuma verifikasi via HTTP kernel) — kalau mau yakin 100% tampilannya
  benar, perlu login browser manual & lihat langsung tiap mode.

---

## 3. SALES / INQUIRY / CUSTOMER ORDER (tm2sdi)

- **Marketing Management** (CLAUDE.md §2, ditambahkan 2026-08-30) — modul
  BARU, belum ada 1 baris kode/dokumen Tier pun. Cakupan yang dideskripsikan
  user: log aktivitas sales (email/kirim quotation/kunjungan per customer)
  + tracking supply chain customer reguler/berkala. **Belum di-scoping
  detail** — kalau mulai dikerjakan, treat seperti modul baru (baca/tulis
  Tier doc dulu, jangan langsung bikin skema dari 1 paragraf deskripsi).
- **Manual input Customer Order & Product Costing** — user minta CO dan
  Costing bisa diisi manual (bukan cuma hasil engine/turunan Inquiry).
  Costing Hub saat ini engine-driven penuh. CO sudah ada layarnya
  (`co/entry.blade.php`) tapi belum jelas apakah "manual input" di sini
  maksudnya field tambahan atau alur terpisah — perlu klarifikasi ulang
  scope-nya sebelum eksekusi (catatan lama 2026-08-27, kemungkinan sudah
  berubah konteksnya sejak CO dibangun).
- **ProCost konsumsi "urutan proses" sbg parameter HPP — SEBAGIAN SELESAI
  2026-09-04** (keputusan user: "keputusan spt bahan baku, urutan proses,
  jumlah UP dan lain sebagainya menjadi masukan parameter bagi procost").
  Bahan baku & jumlah UP **SUDAH LIVE** (via `addPartFromInquiry()` /
  toggle "Ambil Data (ProDev)" → `prod_layout_group.up_count`).
  **Urutan proses**: dicek ulang ke kode nyata dulu (bukan tebak) — ternyata
  ProDev SUDAH punya fitur routing lengkap & terpakai (`prod_part_process`,
  tm2mst, diisi via `prodev.blade.php::addProcess()`, live sejak
  2026-09-02), TAPI `prod_process`/`prod_part_process` **TIDAK punya kolom
  rate Rupiah sama sekali** (satu-satunya "rate" yg ada,
  `process-rate.blade.php::target_qty_per_hour`, itu KECEPATAN produksi utk
  WO Generation, besaran beda total dgn biaya) — auto-hitung HPP dari situ
  akan MENEBAK angka, berbahaya utk engine yg "rahasia" (CLAUDE.md §4).
  **Yang dibangun**: `costing.blade.php` sekarang tarik routing part (via
  `prodev_part_id` yang sudah ada sejak `pickLayoutGroup()`) dan validasi
  silang 2 arah terhadap 3 field ongkos manual DC (cetak/plong/lem) —
  tampil sbg chip "Urutan Proses (ProDev)" + warning kalau step ADA di
  routing tapi field masih 0, atau field TERISI tapi step TIDAK ada di
  routing (indikasi data basi). Pola "engine suggests (warning), officer
  confirms (isi manual)" — TIDAK ada angka Rupiah ditebak/di-auto-fill.
  Diuji `tests/Feature/CostingRoutingWarningTest.php` (7 assertion).
  **Scope sengaja DC saja** — proses SHEET_DIGITAL/KRAFT (diecut/slitter/
  slotter/join/partisi/layer/packing/kirim, 8 dari 10 bucket ongkos) TIDAK
  punya padanan nama di `prod_process` saat ini (isinya migrasi offset lama:
  CETAK/LAMINASI/PLONG/LEM MESIN/POTONG/UV/KETREK/KSS/KUPAS/QC PACK/QC
  CETAK/QC/QC HITUNG/HITUNG/JEMUR/WRAPPING/STITCHING/LAMINATING DOFT — cuma
  KUPAS & QC PACK yang match) — memaksa map 8 bucket sisanya jadi tebakan,
  SENGAJA di-skip. **Masih terbuka kalau mau dilanjutkan**: (a) rate master
  Rupiah per proses (kolom baru di `prod_process` atau tabel versioned baru
  pola `mat_coating_rate` — perlu keputusan desain + angka rate ASLI dari
  user, bukan ditebak Claude), (b) tambah process_name SHEET_DIGITAL/KRAFT
  ke `prod_process` kalau memang proses itu nyata dipakai perusahaan (cek
  ke user dulu, jangan asumsi nama).
---

## 4. PROCUREMENT (tm2pur)

- **Purchase Receive — 3 alur, baru 1 yang ada**: PV/SPB saat ini cuma
  cover "Receiving dari PO" (supplier vs PO). **Belum dibangun**:
  "Receiving dari Customer" (consignment, customer kirim bahan sendiri,
  butuh stok terpisah/`ownership_type` — skema belum diputuskan) dan
  "Receiving dari Subkontraktor" (SFG kembali dari pihak ketiga, masuk
  `sfg_stock`). Detail lengkap: `docs/SIDEBAR_MENU_STANDARD.md` §6/§6a.
- **12 dari 18 kategori Level-1 `mat_category`** masih kosong default COA
  account (`default_coa_account_id`/`default_stock_asset_account_id`) —
  layar isinya **SUDAH ADA** (`master/mat-category.blade.php`, bagian
  "Default Akuntansi" muncul otomatis utk kategori Level 1, dicek ulang
  2026-09-02 — sempat salah dicatat seolah belum dibangun). Kategori kosong:
  Gasoline, Solasi, Plastik, CTP Plate, Pack Material, Stationery/ATK, 2×
  Spare Part (Machine/Vehicle), Tools/Equipment, Laptop/Desktop/Networking,
  Jasa/Service, Peralatan Kebersihan — murni tinggal user isi manual per
  kategori (keputusan akun, bukan kode), akun GR/IR universal (813) sudah
  terisi semua.

---

## 5. INVENTORY (tm2inv)

- Sebagian besar sudah live (Stock In/Out/Return/Adjustment+Approval,
  3 kelas RM/FG/SFG). Yang tersisa mengikuti dari gap Procurement di atas
  (Purchase Receive 3-alur → tujuan akhirnya nulis ke `sfg_stock`).

---

## 6. PRODUCTION SCHEDULING / PPIC (tm2prd)

Modul paling matang cakupannya tapi masih banyak sisa — daftar dari
`project_production_scheduling_spec` (memori sesi, verifikasi ulang ke kode
sebelum eksekusi):

- Decision Feedback calibration loop masih sebatas log, belum ada loop
  belajar sungguhan.
- MTS Forecast → WO auto-replenishment generator belum ditulis (entry +
  accuracy tracking sudah ada, trigger generate WO-nya belum).
- **Simulation Sandbox penuh** ("Submit ke Master Schedule", Tier1 §5A.1 —
  tarik CO ke sandbox → PDR suggest → review → Submit) belum ada sama
  sekali — Worklist WO + WO Detail sudah jadi fondasi natural utk ini.
- WO-part convergence (1 operasi hilir dilayani >1 WO, mis. Badan+Tutup
  ketemu di Packing) — skema saat ini 1 `iqp_part` = 1 `prd_wo`, belum
  bisa model ini, butuh desain tabel baru (`prd_wo_convergence` atau
  sejenis).
- `iqp_part` → `prod_part` (master part reusable lintas-order) belum
  ter-link — `prod_part` sendiri ternyata tidak pernah terisi.
- **F10-F14 & F18-F20** (MRP netting, Material Substitution/Cutting Yield
  engine, Time-Phased Procurement, Lead Time Historical Tracking, Customer
  Interruption Profile reporting) — semua secara eksplisit ditunda user,
  belum digarap sama sekali.
- **Maintenance submodule** (`PROD_MTN_SCH`/`PROD_MTN_PREV`) — di luar
  cakupan modul Production Scheduling menurut Tier1 §6 sendiri, belum ada
  spec sama sekali.
- `PROD_MON_WO` sekarang sudah kepakai (Screen #3 Monitoring Dashboard) —
  **BUKAN lagi item terbuka**, catatan lama yang bilang ini ambigu sudah
  usang.

---

## 7. FINANCE & ACCOUNTING (tm2acc)

- **Aset Tetap (Fixed Asset)** — modul **belum ada sama sekali** (dicek
  2026-09-02: cuma akun COA "ASET TETAP PABRIK"/"Akumulasi Penyusutan
  Mesin" di `fin_account`, bukan tabel register). Menu placeholder
  `ACC_FIXEDASSET` (NOT_BUILT) sudah ditaruh di grup ACC (migration
  `2026_09_02_000019`), permintaan user. Butuh desain dulu sebelum
  dibangun: skema register (kode aset, tgl/nilai perolehan, metode &
  umur penyusutan, lokasi/departemen pemegang, link ke akun aset vs
  akumulasi penyusutan di COA), siapa yang input, apakah auto-jurnal
  penyusutan bulanan (mirip `JournalPostingService` PV) atau manual.
- **Auto-jurnal Delivery/Invoice** — PV & Stock Out(RM) sudah auto-posting
  (`JournalPostingService`), tapi Delivery(DO)/Invoice sengaja ditunda
  karena butuh mekanisme snapshot Costing V2 di titik shipment (Keputusan B
  `FIN_TIER_1.md`: COGS diakui saat DO, bukan saat Stock-Out RM). **DO
  sekarang sudah ada layarnya** (`dispatch/do/entry.blade.php`, live sejak
  2026-08-28) — prasyarat "DO belum ada" di catatan lama SUDAH TERPENUHI,
  tapi status mekanisme snapshot Costing V2 itu sendiri **belum
  dikonfirmasi ada atau tidak** — cek dulu sebelum mulai.
- **Payroll (tm2pyr) — SELURUH MODUL belum dibangun** (bukan cuma
  enkripsi). CLAUDE.md §2 sendiri menandai ini requirement yang belum
  diimplementasikan sama sekali. **UPDATE 2026-09-03**: keputusan desain
  enkripsi SUDAH DIKUNCI (scope kolom, lokasi kunci `VERBA_SANCTA` di
  `sys_constant`, algoritma) — baca `docs/payroll_encryption_decision.md`
  SEBELUM mulai modul ini. Kunci sendiri sudah tersimpan di DB (prasyarat),
  TAPI modul Payroll (skema tabel, model, service, UI) masih 0% — dokumen
  itu murni catatan desain, bukan implementasi.
- **12 kategori `mat_category` tanpa default COA** — sama item dengan §4
  Procurement (mempengaruhi Finance juga karena ini soal akun jurnal),
  layarnya sudah ada, tinggal user isi.

---

## 8. HRM / RECRUITMENT (tm2hrm, tm2hrr)

- **`org_position` masih ada posisi HR Manager/Dept Head yang tidak
  ter-mapping** ke pegawai asli — approval Recruitment saat ini jalan
  lewat tabel terpisah (`rcr_approver_assignment`, role→user langsung)
  sebagai workaround, bukan lewat `org_position` yang seharusnya jadi
  sumber kebenaran jangka panjang. Perlu HR data-cleanup tersendiri, di
  luar cakupan Recruitment itu sendiri.
- **Flutter mobile channel** untuk Portal Rekrutmen — dicatat sebagai
  rencana di CLAUDE.md §2, backend Firestore sudah siap dipakai bareng,
  tapi kanal HP-nya sendiri belum dibangun sama sekali.
- **Master Komponen / arah final Master Card** — pertanyaan lama (Jo,
  2026-08-18) soal di mana data product/part/material/proses seharusnya
  diinput (WO vs Costing). **DITUNDA (keputusan user 2026-09-02)**: akan
  dibahas nanti bersamaan modul **Sales — Product Costing**, bukan ditutup
  dari sisi WO Detail Master Card saja. Jangan asumsikan tab Master Card
  (2026-08-28) sudah jadi jawaban final sebelum diskusi itu terjadi.

---

## 9. MIGRASI FILAMENT `/admin` (lintas-modul)

Baru HRM (2 Resource + 1 Page) yang dipindah sebagai bukti pola —
**Sales/Procurement/Inventory/Production/Finance/9 layar Recruitment
lain/Setup masih 100% di shell lama.** Pola & 2 gotcha crash yang sudah
terpecahkan (generator `--cluster` crash, tipe union `$navigationGroup`)
didokumentasikan di `docs/admindashboardstandard.md` §6 — dipakai ulang
begitu modul berikutnya digarap. **Jangan mulai modul berikutnya tanpa
arahan eksplisit user soal modul mana & kapan** (aturan yang sudah ada di
`admindashboardstandard.md` §12, dipertegas ulang di sini).

---

## 10. TEKNIS / UI STANDAR — TECHNICAL DEBT

- **Halaman transaksi iframe** (Inquiry, Stock In, Quotation, dst) —
  belum ikut sistem tema `sys_theme` (themestandard.md §6), masih warna
  Tailwind literal.
- **Rename `is_active`→`activate` global** — sudah selesai di semua DB
  KECUALI `sec_user.active` (kolom legacy terpisah, sengaja tidak diubah,
  BUKAN item terbuka — dicatat di sini supaya tidak disangka lupa).
- **WYSIWYG template-editor untuk Nota/print** — ide sempat dilontarkan
  saat print/nota component diekstrak (2026-08-28), sengaja ditunda
  (keputusan setingkat modul baru, belum diminta).

---

## 11. KEBIJAKAN BESAR — DIPUTUSKAN TAPI BELUM DIEKSEKUSI

- **Semua menu aplikasi jadi Bahasa Inggris** (keputusan user 2026-08-29,
  bukan cuma grup HRM) — belum dieksekusi. Butuh rencana tersendiri
  (kemungkinan per-modul: pilih 1 modul, translate `sys_app_menu.label`
  lewat migration, verifikasi, `npm run build`, lanjut modul berikutnya).
  **Jangan lakukan mass-rename sebagai efek samping tugas lain** — hanya
  item BARU yang sudah pakai Inggris sejak keputusan ini (mis. "Help &
  Support", "Configuration" yang dibangun hari ini), label lama tetap
  Indonesia sampai giliran modul itu tiba.

---

## 12. TAMBAHAN DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Hasil sisir 107 file Tier/standar yang belum saya baca sendiri. **3 item
yang kedengarannya kritis SUDAH DICEK LANGSUNG ke DB dan TERNYATA SUDAH
BERES** (dokumen sumbernya `openingapp_tier1-4.md`, jelas ditulis sebelum
banyak sesi berikutnya): `sec_group_permission` sekarang 14 baris (bukan 0
— B-OA2 resolved), `sys_login_hero` tabelnya sudah ada (B-OA5 resolved),
akun admin bisa login normal (B-OA12 kemungkinan resolved juga, sudah
diverifikasi hari ini via login `johiwan`/`admin`). **Pelajaran: dokumen
`openingapp_tier*.md` secara umum sudah usang berat** — kalau baca dokumen
itu, JANGAN percaya "blocker"-nya tanpa cek ulang ke DB dulu.

### 12.1 Komponen UI — batas yang SENGAJA (bukan gap nyata)

Ganttboard/Kanban/Nota komponen semuanya punya daftar "yang sengaja tidak
ditangani" (auto-assign resource, validasi konflik, zoom dinamis, drag
lintas-board, reorder manual kartu, dll) — ini keputusan desain sadar,
BUKAN kerjaan tertunda, jangan dikira backlog. Yang beneran masih terbuka:
- `spreadsheetgridstandard.md` §8 — tanpa arrow-key nav, tanpa multi-cell
  select, tanpa indikator dirty per-sel, tanpa undo/redo.
- `griduistandard.md` §10 — beberapa filter dropdown (tipe/kategori/
  divisi/dimensi/sembunyikan-nonaktif) pernah DIHAPUS dari beberapa
  toolbar grid, belum dikembalikan — butuh keputusan user eksplisit kalau
  mau balik.
- `printnotastandard.md` §8 — ide "edit isi cetak lewat GUI" (WYSIWYG,
  sudah dicatat di §10 backlog ini juga) masih sekadar ide.

### 12.2 Inquiry/CO — keputusan bisnis masih menggantung

`inquiryproduct_tier1.md` §6.2 & `tier2.md` §15 — sederet keputusan bisnis
BELUM dikunci (bukan soal teknis, soal kebijakan): granularity bahan
customer-supplied (all-or-nothing vs per-material), basis handling-fee,
kebijakan waste bahan customer, definisi umur/impresi maksimal tooling,
invoice-per-CO vs split, pajak external-cost. Plus 2 dokumen terkait yang
belum pernah dibangun sama sekali: `FORECAST_ENGINE_spec.md` (forecast
demand, butuh ≥1 tahun data) dan `SALES_INITIATIVE_note.md` (modul
"jual kapasitas nganggur" — masih verbal, belum ada sistem).

### 12.3 Procurement/Finance — subsistem yang belum ada sama sekali

- **Purchase Invoice (SUPPLIER_DP/SUPPLIER_BILL)** — belum dibangun,
  butuh tabel delta `fin_invoice_line` yang belum ada.
- **Seluruh subsistem AR/AP Finance** (Invoice Entry+print, e-Faktur,
  Billing Status, Payment Record, AR/AP Aging, Statement of Account,
  Kwitansi, GL Trial Balance dashboard) — belum ada 1 layar pun,
  Journal Entry sendiri sudah live (Cash & Bank + auto-posting).
- **Partner Account auto-fill** (`financeaccounting_delta_partner_category_coa_v2.md`
  §3.3) — mekanisme Observer-nya **secara eksplisit diblokir**, menunggu
  konfirmasi state `fin_partner_account` di `tm2acc` — cek dulu sebelum
  lanjut, mungkin sudah tidak relevan.
- Blanket PO/kontrak jangka panjang, Supplier performance rating — "belum
  dibutuhkan"/"menyusul", tidak ada urgensi tercatat.

### 12.4 Delivery/Dispatch — 8 item eksplisit di luar scope v1

`dispatch_tier1.md` §8: picking list granular (per-lokasi/barcode),
Packing List (koli/tonase/m³), Load Planner, Route Planner, Shipment
Manifest, approval 3-pihak perubahan harga (kolom sudah disiapkan, TANPA
flow aktif), FG Stock Out otomatis saat SHIPPED (butuh keputusan valuasi
AVG/FIFO dulu), Proof of Delivery digital (foto/e-signature — v1 masih
tanda tangan fisik), Delivery Return (retur pasca-SHIPPED), Invoicing/
Billing (AR-lite, Modul 6 terpisah).

### 12.5 HR — Attendance & Payroll, gap teknis konkret

- **DELTA-01**: `emp_shift` butuh dimensi lokasi/gedung baru untuk pola
  shift Satpam (S1-S5) — skema saat ini tidak punya konsep gedung.
- **DELTA-03**: business unit ke-3 (Indostry Packaging) belum punya kode
  dokumen huruf (setelah TLS/TDP) — perlu diputuskan.
- Semua perhitungan Rupiah (lembur, denda, potongan, uang makan/roti,
  prorata gaji, slip gaji) tetap domain **Payroll**, konsisten dengan §7
  di atas (modul Payroll belum dibangun sama sekali).
- Koneksi real-time mesin fingerprint (SDK/ADMS) — ditunda ke v2, v1
  cuma berbasis file-export.
- GPS/selfie mobile clock-in — tidak diminta, tidak dibangun.

### 12.6 Recruitment — 4 item tambahan di luar yang sudah tercatat §8

- Kandidat WNA (bukan WNI) butuh kunci dedup alternatif (paspor, bukan
  KTP) — **belum didesain**.
- Kebijakan retensi/purge/anonimisasi data kandidat lama (kepatuhan PDP)
  — belum ada keputusan sama sekali.
- App Flutter (kanal ke-2 portal, Android+iOS) — belum mulai, effort
  setingkat proyek terpisah.
- Publikasi ke Google Play ($25)/App Store ($99/th) + privacy policy —
  dicatat eksplisit sebagai tugas OPERASIONAL user, bukan sesuatu yang
  bisa dikerjakan Claude Code — jangan lupa saat rilis.

### 12.7 Checklist master lintas-modul (`checklistmockuperp.md`)

File ini sendiri berfungsi sebagai daftar centang ⬜/🟡 raksasa. Layar yang
masih ⬜ (belum dibangun) di luar yang sudah tercatat di section lain file
ini: Prove Warna (versioning v1/v2/v3), Nota Alokasi Inventory (masih belum
jelas — "posisi stok transaksional" yg dulunya dimaksud di mockup TIDAK
relevan lagi setelah Inventory ledger jadi, perlu klarifikasi ulang
maksudnya kalau mau dikerjakan), Period Manager (buka/tutup periode stok —
`PeriodService` genuinely TAK PERNAH DIBUAT, bukan cuma "belum dipanggil"
seperti catatan lama bilang — dicek 2026-09-03, 0 file ditemukan — TAPI
default konstanta periode Accounting/Stock Opname/Payroll sudah ada di
`sys_constant`, lihat `docs/LOG_JOB_DONE.md` 2026-09-03), FIFO/LIFO
valuation (`StockValuationService`, saat ini cuma AVG), Forecast Engine &
Flow Engine (state-machine) mockup produksi lama (kemungkinan besar sudah
digantikan oleh `production_scheduling_tier*.md` yang jauh lebih baru —
cek dulu sebelum kerjakan, potensi duplikat). Sisa: Setup > Audit Trail
Viewer & generic Lookup Master maintainer (sebagian sudah tercakup
Configuration hari ini, sebagian belum).

### 12.8 Catatan konsistensi (bukan actionable, cuma disclosure)

Beberapa dokumen lama (`masteremployee_tier1.md`, `TIER_1_ATTENDANCE.md`)
masih bilang Recruitment/Payroll/Attendance "ditunda total", padahal
dokumen lain yang lebih baru (`recruitment_tier1-4.md`, dst) menunjukkan
Recruitment sudah dibuka lagi & dibangun penuh sejak keputusan
"DEC-SCOPE-OPEN" 2026-08-26. Dicatat apa adanya sesuai sumbernya, bukan
diselaraskan paksa — kalau baca dokumen lama soal 3 modul ini, cek dulu
tanggal & dokumen yang lebih baru sebelum percaya frasa "ditunda".

---

## 13. DOKUMENTASI PER-LAYAR, FLOW PER-MODUL & STANDARISASI UI/UX (permintaan user 2026-09-04)

**Belum mulai** — 3 permintaan terpisah, dicatat verbatim supaya scope tidak
melenceng saat mulai dikerjakan:

1. **Dokumentasi per-layar** — 1 file `.md` per layar (Livewire SFC di
   `resources/views/pages/**` + Resource Filament di `/admin`), isinya
   deskripsi field/aksi/alur yang SUNGGUH ADA di kode saat ini (bukan
   rencana/mockup) — tujuan eksplisit user: "**agar saya bisa benahi isi
   md file jika ternyata menyimpang**", jadi dokumen ini jadi alat verifikasi
   dua-arah (Claude tulis apa yang dilihat di kode, user koreksi kalau
   keliru/kurang), bukan spec yang didikte dari awal. Ikuti konvensi
   penamaan `sys_app_menu.doc_slug` yang sudah ada (CLAUDE.md §6) — jangan
   bikin skema penamaan baru.
2. **Flow per-modul** — dokumen alur kerja lintas-layar per modul (Sales,
   Procurement, Inventory, Production, Finance, HRM, dst) — beda level dari
   poin 1 (per-layar) — ini mengikat urutan/keterkaitan antar-layar dalam 1
   modul (mis. Inquiry → Quotation → CO → CPDEL → DO, atau ProDev → ProCost).
   Kemungkinan besar overlap dgn dokumen Tier1/Tier2 yang sudah ada per
   modul — **cek dulu yang sudah ada di `/docs` sebelum bikin baru**,
   jangan duplikat, cukup lengkapi/perbarui yang sudah ada kalau relevan.
3. **Standarisasi UI/UX + detail komponen** — beda dari poin 1/2, ini
   dokumen DESAIN SISTEM (bukan per-layar): styling/pola/komponen yang
   dipakai berulang lintas modul, level detail sampai component. Sebagian
   BESAR kemungkinan sudah ada tersebar (`griduistandard.md`,
   `printnotastandard.md`, `ganttboardstandard.md`, `kanbanboardstandard.md`,
   `spreadsheetgridstandard.md`, `themestandard.md`,
   `admindashboardstandard.md`, `uistandardindex.md` sbg peta) — task ini
   kemungkinan besar **konsolidasi & lengkapi**, bukan mulai dari nol.
   **Admin dashboard (Filament) WAJIB masuk cakupan** secara eksplisit
   (permintaan user) — `admindashboardstandard.md` sudah ada tapi baru
   HRM cluster yang jadi bukti pola (lihat §9), pastikan detail komponen
   Filament (Resource/Cluster/Page/Widget) ikut terdokumentasi setara
   dgn pola shell lama.

**Syarat tambahan dari user (2026-09-04, susulan)**:
- **Prefix nama file wajib** — supaya gampang dicari (konsisten dgn konvensi
  `doc_slug`/prefix yang sudah ada di project, CLAUDE.md §6). Perlu
  ditentukan skema prefix per kategori (mis. `screen_*` utk poin 1,
  `flow_*` utk poin 2, `uistandard_*`/nama existing yang sudah prefix-
  konsisten utk poin 3) sebelum mulai generate file — jangan mulai nulis
  file lalu baru mikir prefix belakangan.
- **Wajib ada file index** — 1 file `.md` yang mendaftar SEMUA file `.md`
  hasil task ini (dan idealnya seluruh `/docs`, bukan cuma yang baru),
  supaya user bisa navigasi tanpa buka folder satu-satu. Pola serupa sudah
  ada (`docs/attachments_index.md`, `uistandardindex.md`) — reuse pola itu,
  jangan bikin format index baru yang beda gaya.

**Belum diputuskan**: urutan pengerjaan 3 poin di atas, modul mana duluan
utk poin 1/2 (kandidat wajar: mulai dari modul yang paling matang/stabil
dulu, mis. Sales/Inquiry atau HRM), dan apakah poin 1 mencakup SEMUA layar
sekaligus (besar) atau bertahap per-modul menyusul poin 2. **Jangan mulai
otonom tanpa arahan** — scope-nya besar & lintas seluruh aplikasi, beda
kelas dari item BACKLOG lain yang biasanya 1 fitur/1 modul.

---

## HISTORY PENGERJAAN

Dipindah ke **[`docs/LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** (file terpisah,
2026-09-02) supaya file ini tetap ramping. Item selesai dihapus dari
section angka di atas dan dicatat lengkap di sana, BUKAN di sini.
