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

## RINGKASAN STATUS (update 2026-09-16 08:0x WIB, utk didiskusikan — pakai nomor R# saat bahas)

> **Cek otonom 2026-09-16 08:02 WIB (`tmerp-backlog-autowork-1700`, budget
> ~1 jam — hari ini Rabu, bukan Selasa):** commit terakhir sebelum sesi ini
> `1a47d2a` (2026-09-14 09:56 WIB, ~2 hari lalu) → AMAN, tidak ada sesi lain
> yang kelihatan aktif. **Catatan**: working tree sudah ada perubahan
> uncommitted dari sesi sebelumnya yang TIDAK disentuh sesi ini (bukan hasil
> kerja sendiri, sesuai aturan) — `docs/ddl/tm2sys.sql` (+1842/-927 baris),
> `resources/views/pages/production/prodev.blade.php` (153 baris), file
> `mockup_shift_roster_v2.html` terhapus, plus beberapa file aneh di root
> (`getColumnListing('iqp_costing'))`, `toArray())`) yang kelihatan sisa
> command PowerShell heredoc yang gagal (`feedback_tinker_restore_assumed_state`-
> style insiden) — **sesi berikutnya perlu cek/bersihkan ini**, bukan
> dibiarkan menumpuk di `git status`.
>
> **R42. [BARU] `<x-form.badge>` rollout ke 6 layar entry TUNTAS** (§10,
> item "Prioritaskan colokkan komponen ini dulu") — `pur/pr`, `pur/po`,
> `pur/pq`, `pur/prt`, `co`, `quotation` `entry.blade.php` semua sudah
> pakai `<x-form.badge :tone="...">` ganti span+match()/ternary manual.
> Diverifikasi `Livewire::test()` 4/6 (PR/PO/CO/Quotation ada data live,
> render OK, tone warna sesuai status) — `pq_quotation`/`prt_return`
> kosong 0 baris jadi 2 sisanya cuma lolos `php -l` (bukan bug baru,
> tabel memang belum ada transaksi). Tidak ada perubahan class Tailwind
> baru → tidak perlu `npm run build`. Detail: §10.

> **Cek otonom 21:49 WIB (`tmerp-backlog-autowork-1700`, budget 1 jam —
> hari ini Kamis, bukan Selasa):** `git log` commit terakhir `e6fe078`
> (09:08 WIB pagi ini, >12 jam lalu) — AMAN dari sisi commit. **TAPI**
> `list_scheduled_tasks` menemukan sinyal tabrakan yang lebih kuat: 3 task
> berbeda (`tmerp-backlog-autowork-1700` — sesi ini sendiri,
> `i18n-rollout-malam-2`, `button-label-standardization-rollout`) semua
> tercatat `lastRunAt` **detik yang SAMA PERSIS** (14:49:39 UTC = 21:49:39
> WIB) — bukti kuat 2 sesi otonom LAIN mulai kerja bersamaan detik ini
> juga, kemungkinan menyentuh area tumpang tindih (komponen shell/tombol
> lintas-layar). Sesuai `[[project_multisession_collision_20260831]]`:
> **sesi ini SENGAJA TIDAK mulai kerja baru** — cukup baca ulang
> `BACKLOG.md`/`LOG_JOB_DONE.md`, tidak ada progres kode baru dari sesi
> ini. R1-R36 di bawah masih snapshot pagi ini; kemungkinan besar sudah
> berubah begitu `i18n-rollout-malam-2` (Malam 2/7, komponen chrome/shell)
> & `button-label-standardization-rollout` (5 titik label tombol,
> §10) selesai jalan malam ini — **sesi berikutnya WAJIB `git fetch`
> ulang** sebelum lanjut kerja dari sini, jangan percaya nomor R# di bawah
> mentah-mentah.
>
> **Lanjutan 21:59 WIB — user eksplisit minta lanjut kerja** ("run now
> cause late 17.00"), jadi sesi ini lanjut coding walau tabrakan di atas
> belum hilang, dengan syarat pilih item yang file-nya TIDAK overlap
> dgn 2 task lain yang terdeteksi jalan bersamaan. **R37. [BARU] Rollout
> footer pagination `<x-grid.pagination-footer>` ke 7/8 layar yang masih
> pakai `->links()` polos** (§10, item lama) — `hrm/position.blade.php` +
> 6 layar `recruitment/*` (assessment-result, candidate-review/list,
> offer/list, requisition/list, talent-pool, vacancy). Pola: tambah trait
> `HasGridPagination`, `paginate($this->perPage)`, ganti markup footer.
> Diverifikasi `Livewire::test()` 7/7 OK. **`setup/support-tickets.blade.php`
> (item ke-8) SENGAJA DILEWATI** — file itu juga target
> `button-label-standardization-rollout` yang terdeteksi jalan
> bersamaan, dihindari supaya tidak tabrakan edit. Detail: §10. Commit
> terisolasi (7 file recruitment/hrm saja, tidak sentuh file lain yang
> berubah di working tree dari sesi lain).
>
> **Lanjutan 22:10-22:17 WIB — user minta lagi** ("run for Thu1700_2of7
> now" = task `i18n-rollout-malam-2`). **R38. [BARU] i18n Malam 2/7 TUNTAS**
> — sisa chrome shell (drawer Notifikasi/Chat/Asisten AI, command palette,
> tombol topbar, modal profil/password, Help Center index bagian chrome).
> 3 migration baru (`..._000002/3/4`), 82 key `shell_*` baru. Detail
> lengkap: §13 baris #2. `git fetch` ulang sebelum mulai: TIDAK ada commit
> baru dari `button-label-standardization-rollout`/`i18n-rollout-malam-2`
> versi terjadwal (keduanya sempat `lastRunAt` 21:49 tapi nihil hasil —
> pola sama seperti insiden Malam 1/2 lama yang "fire tapi 0 commit").
> Karena itu aman lanjut kerja penuh, bukan cuma isolasi file sempit lagi.
>
> **Lanjutan — user minta lagi** ("run session Thu* for now" lalu "if done
> delete routine Thu* and delete too from backlog"). **R39. [BARU]
> Standarisasi tombol aksi umum TUNTAS (5/5 poin)** — task terjadwal
> `button-label-standardization-rollout` (judul `Thu2107_TombolStandardisasi`,
> Thu*-named terakhir) juga sempat fire 21:49 tapi 0 progres, dieksekusi
> ulang manual. Detail lengkap: `docs/LOG_JOB_DONE.md` 2026-09-10 malam
> ("Standarisasi tombol aksi umum"). 1 koreksi manual thd draf instruksi
> (poin 3: "Close" bukan "Tutup" krn layar itu sengaja all-English) + 2
> perluasan opportunistic (poin 3 sekalian pagination-footer, poin 4
> sekalian fix tombol Filament asli bukan cuma teks referensinya). **Kedua
> task terjadwal Thu* (`i18n-rollout-malam-2` + `button-label-standardization-rollout`)
> DIHAPUS** dari scheduled tasks sesuai instruksi user (sudah tuntas
> dikerjakan manual malam ini, tidak perlu jadwal tersisa) — lihat §13
> baris #2 (status ✅) dan catatan di atas §10 utk hasil masing-masing.
>
> **Lanjutan 22:35-22:45 WIB — user minta lagi** ("run session Fri* for
> now and then delete if done, include backlog" = task
> `i18n-rollout-malam-3`, sehari lebih awal dari jadwal aslinya 2026-09-11).
> **R40. [BARU] i18n Malam 3/7 TUNTAS — Sales inti**: Inquiry Hub, header+
> toolbar Inquiry form, Quotation list, CO list. ~140 key `sales_*` baru +
> 2 key dipromosikan `shared_*` (action "Buka" + hint klik-2x, dipakai
> quotation/CO list malam ini — grid lain masih hardcode, generalisasi
> penuh dicatat sbg langkah lanjutan, bukan scope malam ini). Detail
> lengkap: §13 baris #3. Task `i18n-rollout-malam-3` **DIHAPUS** dari
> scheduler sesuai instruksi user (sudah tuntas, tidak perlu jadwal
> tersisa besok).
>
> **Lanjutan 22:48-23:11 WIB — user minta lagi** ("run routine session
> name Sat and Sun, for now. If done, delete routines and delete too from
> backlog if exists" = task `i18n-rollout-malam-4` + `-malam-5`, 2-3 hari
> lebih awal dari jadwal asli). **R41. [BARU] i18n Malam 4/7 (Procurement,
> PARTIAL 🟡) + Malam 5/7 (Inventory+Production, ✅ TUNTAS)**:
> - **Malam 4** — 5/5 grid list PR/PO/PV/PQ/PRT selesai (~85 key `proc_*`),
>   TAPI 5x `entry.blade.php` (400-660 baris tiap file, field kalkulasi
>   harga/pajak/approval) sengaja belum disentuh — di luar budget, status
>   🟡 bukan ✅. Detail: §13 baris #4.
> - **Malam 5** — 5/5 layar selesai penuh (WO Worklist, Stock In/Out/
>   Adjustment [grid+form 1 komponen, jadi form ikut tersentuh], Master
>   Card cetak) — ~155 key `inv_*`/`prod_*`. Detail: §13 baris #5.
> Kedua task terjadwal **DIHAPUS** dari scheduler sesuai instruksi user.
> **Sisa i18n 7-malam yang masih terjadwal**: Malam 6
> (`i18n-rollout-malam-6`, Sen 2026-09-14, Finance+HRM) & Malam 7
> (`i18n-rollout-malam-7`, Sel 2026-09-15, Master Data + QA checkpoint
> 12 jam) — BELUM disentuh, plus sisa 5 `entry.blade.php` Procurement dari
> Malam 4 di atas — tetap sesuai jadwal asli kecuali user minta dipercepat
> lagi.

> **Cek otonom 2026-09-10 ~07:00 WIB (`tmerp-backlog-autowork-1700`):**
> commit terakhir saat mulai cek (`3cb7153`, 2026-09-09 15:19) sudah >15 jam
> lalu → awalnya disimpulkan AMAN utk lanjut kerja BACKLOG. **TAPI** begitu
> mulai kerja, `git fetch` menemukan **2 commit baru muncul di tengah jalan**
> (`5812da8` 07:04 & `7bd4bf8` 07:10, keduanya edit `docs/BACKLOG.md`) — bukti
> ada **sesi interaktif lain aktif bersamaan pagi ini**, merespons instruksi
> user langsung (item #16 "New Chat" WhatsApp-style + **R35** Master Card
> tumpah halaman 2). Sesuai aturan tabrakan ([[project_multisession_collision_20260831]]):
> **sesi ini SENGAJA STOP mulai kerja BACKLOG baru** begitu temuan itu
> muncul — cuma menuntaskan 1 unit kerja yang SUDAH di tangan & TERISOLASI
> (tidak overlap file dgn sesi lain), lalu commit+push, lalu tulis ringkasan
> ini. **R35 (Master Card, prioritas malam ini) BELUM disentuh sesi ini** —
> dicadangkan utk sesi malam sesuai instruksi user sendiri di §0, JANGAN
> dikerjakan siang/pagi kecuali dikonfirmasi ulang tidak ada sesi lain aktif.
> - **R36. [BARU]** i18n Malam 1 leftover **TUNTAS**: `date-picker.blade.php`
>   + `spreadsheet-table.blade.php` (WIP sesi 2026-09-09 siang yg sempat
>   tertinggal uncommitted) selesai pakai `Translator::t()` penuh. Ditemukan
>   tambahan 4 komponen `x-form.*` BARU (dibangun sesi lain 2026-09-09 pagi,
>   belum ikut audit malam itu) masih 100% hardcode Indonesia:
>   `searchable-select`/`multi-select`/`file-dropzone`/`mini-calendar` — semua
>   diberi `Translator::t()` + migration follow-up
>   `2026_09_10_000001_seed_i18n_night1_followup.php` (18 baris `sys_translations`
>   baru, key prefix `shared_*`). Diverifikasi render via tinker (7 view OK)
>   + `php artisan test tests/Feature/MainMenuShellSmokeTest.php` (12/12
>   pass). Tidak ada perubahan class Tailwind → tidak perlu `npm run build`.
>   Commit `751b1f1`. **Tabel §13 baris "Malam 1" BELUM diubah ✅** (sengaja
>   dibiarkan 🟡 — sesi ini cuma menuntaskan sisa kerjanya, bukan
>   me-review scope Malam 1 secara utuh; sesi berikutnya boleh centang kalau
>   sudah yakin tidak ada string `shared_*` lain yg lolos).
>
> **Untuk sesi berikutnya**: cek `git log`/`git fetch` ulang dulu sebelum
> lanjut — kalau commit terbaru masih dalam ~1 jam, kemungkinan sesi
> interaktif di atas masih jalan. Item paling siap dikerjakan tanpa
> analisis ulang: **R35** (Master Card, §0 — root cause sudah lengkap,
> tinggal eksekusi) dan **item §16** (New Chat WhatsApp-style, sudah ada
> referensi kode lengkap `tmerp-chat-module.zip`, tinggal porting).

---

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
- **R31.** Dokumentasi per-layar (127 file) + flow per-modul (12 file) +
  database/tabel (12 file) + katalog komponen UI/UX (6 file) + index master
  — lihat `docs/LOG_JOB_DONE.md` 2026-09-05.
- **R32.** [BARU 2026-09-09] **Temuan + perbaikan jadwal scheduled task.**
  Sesi `tmerp-backlog-autowork-1700` (cek status siang 2026-09-09) menemukan
  `i18n-rollout-malam-1` (2026-09-07 21:00) & `-malam-2` (2026-09-08 21:00)
  sempat **fire tapi 0 commit dihasilkan** (gagal diam-diam, root cause
  belum diselidiki — bukan kerjaan kode, di luar scope Claude Code
  menyelidiki infra scheduler). Atas instruksi user: (a) task umum
  `tmerp-backlog-autowork` diubah dari cron harian 15:45 hari-kerja-saja
  jadi **7 hari/minggu** (`tmerp-backlog-autowork-1700` 21:00 hari kerja +
  `tmerp-backlog-autowork-weekend-2200` 22:00 akhir pekan, task baru); (b)
  seluruh jadwal 7-malam i18n **direset mulai hari ini** dgn jam seragam
  21:00 hari kerja/22:00 akhir pekan (lihat §13 utk tabel baru + WIP Malam 1
  yang sudah mulai dikerjakan siang ini, migration
  `2026_09_09_000001_seed_i18n_night1_shared.php` — pagination-footer/
  column-modal/sort-modal/toolbar-buttons SELESAI, date-picker SEBAGIAN,
  select+spreadsheet-table BELUM, akan dituntaskan `i18n-rollout-malam-1`
  malam ini).

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
  AR/AP belum ada. Lihat §7. (**Payroll TIDAK LAGI di-skip** — sudah
  dibangun penuh 11/11 layar per 2026-09-07, lihat R34/§15, catatan lama
  "sengaja di-skip total" di sini sudah usang.)
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
- **R33.** [BARU 2026-09-09] **Audit keterkaitan identitas sec_user ↔
  emp_employee** — insiden nyata ditemukan user sendiri lewat browser
  (bukan lewat pengecekan sistematis), akun login asli tertaut ke record
  HR dummy tanpa terdeteksi. Lihat §14.
- **R34.** [BARU 2026-09-09] **Payroll & Time Attendance — 11/11 layar
  SELESAI**, tapi ada 1 keputusan bisnis yang masih menggantung jawaban
  user (eligibilitas Uang Roti) + beberapa gap kecil belum dikerjakan
  (format transfer bank, tabel TJ_KEAHLIAN/rate lembur, logo PWA). Lihat
  §15 utk stage/handoff lengkap.

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
- **Payroll (tm2pyr) — fondasi (skema+enkripsi+engine) SUDAH ADA, UI masih
  0%.** **UPDATE 2026-09-05**: `docs/LOG_JOB_DONE.md` entri "Payroll (tm2pyr)
  fondasi" — skema `tm2pyr.pyr_*` + `tm2mst.att_*`/`emp_overtime_request`
  live, `App\Casts\PayrollEncrypted` (AES-256-GCM, VERBA_SANCTA) terpasang &
  diverifikasi, `AttendanceCalculationService` + `PayrollEngineService`
  jalan end-to-end (diuji tinker). **Yang MASIH belum ada**: 11 layar
  Livewire (import finger, grid review harian, payroll batch UI, cetak
  payslip, generator bank-transfer/cash-disbursement — lihat mockup di
  `docs/payroll_timeattendance/mockup_*.html`), UI pengajuan/approval
  `emp_overtime_request`, beberapa `[DELTA-CONFIRM]`/Open Item Tier 2 (KJK
  dijumlah vs window terpanjang, PAT-5/PAT+5 prorata vs zero-total, aturan
  tidak-finger ke-3+) belum dikonfirmasi ke bisnis. GPS mobile clock-in/ESS
  approval workflow (fitur v4.0 di paket referensi) sengaja tidak
  diprioritaskan, lihat §12.5. **UPDATE 2026-09-05 lanjutan (sore)**: user
  menulis alur bisnis lengkap end-to-end (bukan cuma hitung gaji) —
  `pyr_payroll_run` didesain ulang total: periode **WEEKLY atau MONTHLY**
  (bukan tahun+bulan, dari sampel Excel riil `docs/attachments/*GAJI
  KARYAWAN*.xlsb` yang semuanya "WEEKLY"), + siklus status penuh DRAFT→
  CALCULATED (bisa diulang, estimasi H-1 & proses ulang H)→VERIFIED (cek
  jam masuk/keluar semua/random sample)→CONFIRMED (kunci presensi)→PAID
  (transfer/cash)→CLOSED (slip terkirim, next_run_id periode berikutnya
  auto-dibuat DRAFT kosong). `emp_employee.payment_frequency` baru
  (WEEKLY/MONTHLY, default MONTHLY) menentukan pegawai masuk run yang mana.
  Diuji end-to-end via tinker (lifecycle penuh 1 pegawai). **Belum**:
  layar UI utk tiap tahap, hook `JournalPostingService` saat CLOSED, dan
  generator bank-transfer/cash-disbursement fisik (baru pencatatan status
  PAID). Detail: `docs/LOG_JOB_DONE.md` entri lanjutan hari yang sama.
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

### 9.1 [BELUM DIPUTUSKAN — DISKUSI DULU] Klik menu sidebar saat form/modal
terbuka "tidak merespons" (dilaporkan user 2026-09-10)

**Root cause (sudah diinvestigasi, BUKAN bug kode project):** kedua Resource
yang sudah ada (`EmpEmployeeResource`, `OrgPositionResource`) pakai pola
Filament **`ManageRecords` (`--simple`)** — form Edit/Create dibuka sebagai
**modal** di atas halaman index, bukan halaman terpisah
(`EmpEmployeeResource.php` `getPages()` cuma daftar `index`). Modal Filament
pasang backdrop full-viewport dgn z-index di atas sidebar
(`vendor/filament/support/resources/views/components/modal/index.blade.php`
§160-181) — klik pertama ke sidebar kena backdrop dulu (cuma `close()` modal,
tidak tembus ke link), klik kedua baru jalan. Ada kontributor tambahan: bug
kecil vendor Filament sendiri (`modal.js`, referensi upstream
filamentphp/filament#18022) yang kadang menelan 1 klik berikutnya di mana
saja di halaman. Dikonfirmasi TIDAK ada custom overlay/CSS/JS/unsaved-changes-
guard buatan project yang jadi penyebab — 100% perilaku bawaan pola modal
Filament v5.

**Riset banding aplikasi lain (2026-09-10):** Odoo (auto-save begitu navigasi,
tanpa dialog), SAP Fiori classic (dialog konfirmasi "unsaved data, leave?"),
SAP Fiori Elements draft-enabled (auto-save sbg draft → bebas navigasi kapan
saja tanpa dialog), Dynamics 365 (dialog konfirmasi kalau auto-save off, auto-
save kalau on). **Tidak ada satu pun yang "klik = tidak terjadi apa-apa"** —
selalu langsung navigasi(+autosave/draft) atau muncul dialog. Jadi ekspektasi
user (klik menu harus selalu direspons) sesuai standar industri; perilaku
Filament panel saat ini menyimpang.

**Arah yang user SETUJU secara prinsip (2026-09-10) tapi MINTA DIDISKUSIKAN
LEBIH LANJUT dulu — JANGAN eksekusi sebelum ada konfirmasi ulang:** adopsi
pola **auto-save sebagai draft ala SAP Fiori Elements** — perubahan tersimpan
otomatis begitu diketik/pindah field, user bebas klik menu lain kapan saja
tanpa dialog/blokir, karena data sudah aman. Ini kemungkinan juga berarti
pindah dari pola `ManageRecords --simple` (modal) ke Resource dgn halaman
Create/Edit terpisah (route sungguhan) supaya tidak ada backdrop yg menutupi
sidebar sama sekali — tapi ini mengubah standar yg sudah didokumentasikan di
`docs/admindashboardstandard.md` §6, berlaku ke semua Resource Filament ke
depan (belum tentu cocok utk transaksi bernilai besar yg butuh Save eksplisit
— lihat prinsip approval/costing rahasia §4 CLAUDE.md, perlu ditimbang saat
diskusi). **Hal yang perlu diputuskan bareng user sebelum mulai:**
1. Draft/auto-save berlaku utk SEMUA Resource Filament, atau cuma tertentu
   (master kecil vs transaksi ber-approval)?
2. Kalau pindah dari modal ke halaman Create/Edit terpisah — apa konsekuensi
   ke UX yang sudah ada (mis. list index jadi 2 klik jauh dari form)?
3. Auto-save di Filament (Livewire) butuh mekanisme sendiri (bukan bawaan
   native seperti draft SAP) — perlu didesain: kapan trigger save (per
   keystroke/blur/interval), bagaimana tandai record "draft" vs "final" di DB
   (kolom `stat_*` yg sudah ada, atau kolom baru?), race condition kalau ada
   2 tab/user edit record sama.

**Mitigasi cepat [SELESAI 2026-09-11]** — supaya klik di luar modal tidak
diam-diam menutup form tanpa disadari user, selagi solusi jangka panjang
(draft/halaman terpisah) masih menunggu diskusi. **Koreksi API saat eksekusi**:
draf di atas menyebut `->closedByClickingAway(false)` sbg method fluent per-
`EditAction`/`CreateAction` — dicek ke vendor, method itu **TIDAK ADA** di
`filament/actions` versi ini (bukan tebakan yang benar). Yang benar:
`Filament\Support\View\Components\ModalComponent::closedByClickingAway()`
adalah **static config global**, dipanggil sekali di
`AdminPanelProvider::boot()` (baru ditambah, sebelumnya cuma ada `panel()`).
Efeknya jadi berlaku ke SEMUA modal panel `/admin` sekaligus (bukan cuma 2
Resource ini) — sejalan dgn niat asli "dan Resource --simple lain yg
menyusul", jadi tidak perlu diulang tiap Resource baru. Root cause (backdrop
z-index di atas sidebar) TIDAK hilang — mitigasi ini murni cegah "form
ke-close tanpa sadar". Diverifikasi: `php artisan route:list --path=admin`
(panel boot tanpa error) + tinker cek `ModalComponent::$isClosedByClickingAway`
= `false`. Reversibel (hapus method `boot()` di provider).

---

## 10. TEKNIS / UI STANDAR — TECHNICAL DEBT

- **[SELESAI 2026-09-10 malam] Standarisasi tombol aksi umum (Save/Cancel/Close)**
  — 5 titik yang menyimpang dari standar LOCKED (`CLAUDE.md` §3,
  `FORM_UI_STANDARD.md` §1a) sudah diperbaiki (`button-label-standardization-rollout`,
  dieksekusi manual krn task terjadwal 21:07 WIB fire tapi 0 progres nyata).
  Detail lengkap: `docs/LOG_JOB_DONE.md` 2026-09-10. **Belum termasuk**
  mekanisme toggle label ikut `lang_code` scr otomatis (masih perlu
  integrasi ke `Translator::t()`, lihat `project_i18n_shell_framework`).

- **Halaman transaksi iframe** (Inquiry, Stock In, Quotation, dst) —
  belum ikut sistem tema `sys_theme` (themestandard.md §6), masih warna
  Tailwind literal.
- **Rename `is_active`→`activate` global** — sudah selesai di semua DB
  KECUALI `sec_user.active` (kolom legacy terpisah, sengaja tidak diubah,
  BUKAN item terbuka — dicatat di sini supaya tidak disangka lupa).
- **WYSIWYG template-editor untuk Nota/print** — ide sempat dilontarkan
  saat print/nota component diekstrak (2026-08-28), sengaja ditunda
  (keputusan setingkat modul baru, belum diminta).
- **[BARU 2026-09-05] Standarkan SEMUA komponen UI, bukan cuma per-kasus
  saat ditemukan** (instruksi eksplisit user setelah komponen scrollbar
  `.tm-scroll` diangkat jadi standar bersama Grid+Form —
  `griduistandard.md` §13, `FORM_UI_STANDARD.md` §4). Pola yang sama harus
  ditempuh utk elemen UI lain yang saat ini masih ditempel manual/beda-beda
  per file, alih-alih 1 komponen reusable:
  - Audit lintas `docs/uistandardindex.md` — cek tiap `*standard.md` sudah
    benar-benar ditegakkan di SEMUA layar turunannya (grid/form/print/
    gantt/kanban/admin dashboard), bukan cuma di layar contoh yang ditulis
    saat standar itu dibuat.
  - Cari pola visual/interaksi yang berulang di ≥2 layar tapi masih
    di-copy-paste (bukan `<x-...>` component/class bersama) — kandidat
    kuat: border-radius/shadow modal, warna badge status, tombol
    icon-only, spacing card statistik, dst. — index sendiri sudah
    menegaskan "jangan ekstrak standar dari 1 contoh", jadi baru jadi
    komponen begitu polanya kepakai berkali-kali (persis kasus tm-scroll).
  - Kalau ditemukan gap (dokumen bilang standar X, tapi kode Y tidak
    ikut), JANGAN langsung refactor massal sendiri — laporkan dulu daftar
    gap-nya ke user (dampaknya lintas banyak layar, berpotensi
    architectural), baru eksekusi per-batch setelah dikonfirmasi, sama
    seperti pola kerja rekonsiliasi PowerGrid+Filament (§12 griduistandard.md).
  - Belum ada scope/urutan modul yang ditentukan — ini masih ITEM
    BACKLOG terbuka, bukan tugas yang sudah mulai dikerjakan.

  **Temuan konkret dari audit awal 2026-09-05** (screenshot user: header
  form entry + footer pagination grid) — dicatat di sini, **BELUM
  dikerjakan**:
  - **Header form entry (ikon+judul+doc_no+badge status+tombol
    cetak+tombol Back) — 0 komponen reusable.** Struktur identik di-copy-
    paste manual di ≥6 layar (`pur/pr/entry.blade.php:404-424`,
    `pur/po/entry.blade.php:258-278`, `co/entry.blade.php:328-361`,
    `pur/pq/entry.blade.php:295-311`, `pur/prt/entry.blade.php:343-357`,
    `quotation/entry.blade.php:177-184+`). Variasi yang harus diakomodasi
    komponen barunya: badge status via `match()` inline berbeda-beda tone
    per file (padahal cuma 4 tone yang sama: abu DRAFT/kuning
    SUBMITTED/hijau approved-selesai/merah reject-cancel), label+emoji
    tombol cetak beda (`🖨 Cetak PR` vs `🖨️ Preview Cetak`), kondisi
    kemunculan tombol cetak beda (`@unless` vs `@if`), sebagian layar (CO,
    Quotation) tambah elemen ekstra kanan (ringkasan angka, tombol
    wire:click) di wrapper `ml-auto flex items-center gap-4` sendiri.
  - **[SELESAI 2026-09-16 pagi] Komponen `<x-form.badge>` sekarang
    dipakai di 6/6 layar** (`pur/pr/entry.blade.php`, `pur/po/entry.blade.php`,
    `pur/pq/entry.blade.php`, `pur/prt/entry.blade.php`, `co/entry.blade.php`,
    `quotation/entry.blade.php`) — sebelumnya masing-masing menulis ulang
    span+`match()`/ternary warna badge sendiri (menganggur sebelum ini).
    Diverifikasi `Livewire::test()` 4/6 render OK (PR/PO/CO/Quotation, ada
    data live); `pq_quotation`/`prt_return` kosong 0 baris (bukan bug
    dari perubahan ini) jadi 2 sisanya cuma lolos `php -l` + kesamaan
    pola persis dgn yang sudah terverifikasi — sesi berikutnya boleh
    re-verify begitu ada transaksi PQ/PRT nyata. Header form entry
    (ikon+judul+doc_no+tombol cetak+Back) masih BELUM diekstrak jadi
    komponen — itu tugas berikutnya di item ini kalau mau dilanjutkan.
  - **Tombol Back — instruksi eksplisit user: standarkan juga** (bukan
    cuma didokumentasikan). Polanya sudah tertulis detail di
    `FORM_UI_STANDARD.md` §1 (pill navy `bg-[#43508a]` + lingkaran panah
    putih `w-6 h-6`, kapan pakai `ml-auto` vs tidak) tapi **belum ada
    komponen `<x-form.back-button>` yang mengeksekusinya** — semua 6+
    layar di atas menulis ulang markup pill yang sama persis manual.
    Jadikan bagian dari komponen header form (§ di atas), atau komponen
    berdiri sendiri kalau ternyata dipakai di luar konteks header juga
    (cek dulu — ada pengecualian tercatat: `inquiry/form.blade.php` dkk
    pakai sistem visual beda, `master/supplier.blade.php`/
    `master/employee.blade.php` tombol "Kembali" untuk toggle state
    bukan navigasi, JANGAN ikut disamakan).
    **[SELESAI 2026-09-11]** Rollout ikon `←` Unicode → Font Awesome
    `fa-solid fa-arrow-left` ke SEMUA 24 layar nyata yang masih pakai
    pola lama (1 lokasi acuan `component-library.blade.php` sudah lebih
    dulu jadi 25). Diverifikasi: grep pola lama nihil hasil, `npm run
    build` sukses, `MainMenuShellSmokeTest` 12/12 tetap pass. Detail
    daftar file: `docs/LOG_JOB_DONE.md`.
  - **Footer pagination — komponennya SUDAH ada & baik**
    (`components/grid/pagination-footer.blade.php`, dipakai konsisten 13+
    layar). **[SELESAI 2026-09-10 malam, 8/8]**: `hrm/position.blade.php`,
    `recruitment/assessment-result.blade.php`,
    `recruitment/candidate-review/list.blade.php`,
    `recruitment/offer/list.blade.php`,
    `recruitment/requisition/list.blade.php`,
    `recruitment/talent-pool.blade.php`, `recruitment/vacancy.blade.php`,
    `setup/support-tickets.blade.php` (terakhir, tadinya sengaja dilewati
    krn dikira tabrakan dgn `button-label-standardization-rollout` — task
    itu ternyata tidak pernah benar-benar jalan, jadi dituntaskan sekalian
    saat file itu disentuh utk perbaikan label tombol) — pola sama semua:
    tambah `use App\Livewire\Concerns\HasGridPagination;` (import + trait),
    `->paginate(20)` → `->paginate($this->perPage)`, ganti
    `<div class="mt-3">{{ $this->rows->links() }}</div>` jadi
    `@include('components.grid.pagination-footer', ['paginator' => $this->rows])`.
    Diverifikasi via `Livewire::test()` (8/8 render OK, footer baru
    terkonfirmasi muncul). Tidak ada class Tailwind baru → tidak perlu
    `npm run build`.

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
- **[BARU 2026-09-11] Grup A (Kategori Produk → akun COA, §2 dokumen di atas)
  SELESAI** — ALTER `tm2mst.prod_category` (2 kolom nullable, TANPA seed nilai),
  resolve 3-lapis `ProdPart`/`ProdProduct::resolveStockAccountId()`/
  `resolveCogsAccountId()`, wiring ke `StockPostingService::postUsageJournal()`
  (dulu baca kolom `prod_part` langsung TANPA fallback sama sekali, sekarang
  jatuh ke `product` lalu `category`). **UI layar Master Kategori Produk BELUM
  dibangun** (ternyata belum pernah ada screen CRUD utk `prod_category` sama
  sekali, cuma dropdown read-only di `produk.blade.php`) — kolom akun tetap
  NULL sampai ada cara isi, tapi behavior saat ini TIDAK berubah (resolve()
  null-safe, sama seperti sebelumnya kalau semua lapis kosong). Diverifikasi
  tinker (resolve chain part→product→category benar) + `PvStockIntegrationTest`
  2/2 pass. Detail: `docs/LOG_JOB_DONE.md`.
- **Grup B (Partner Account local/import, §3.1-3.2) — TERNYATA BUKAN "boleh
  jalan sekarang" lagi, dicabut dari kategori itu.** Verifikasi §4 (2026-09-11,
  sesuai instruksi dokumen sendiri "jangan ALTER buta") menemukan akun `211`/
  `212` di `fin_account` **BUKAN** "Hutang Dagang Lokal/Impor" seperti
  diasumsikan dokumen — kenyataannya segmentasi AP di COA aktual per
  **kategori supplier/komoditas** (Kertas & Board/Tinta & Chemical/dst),
  bentrok konsep dgn model "4 kombinasi CUSTOMER/SUPPLIER × local/import" di
  §3. Kemungkinan sudah digantikan mekanisme `mat_category.default_coa_account_id`
  yang sudah ada (§4 Procurement di atas). **Butuh rekonsiliasi desain dgn
  user sebelum lanjut** — apakah AP tetap mau disegmentasi local/import
  (perlu akun COA baru yang belum ada) atau `fin_partner_account` cukup
  dipetakan dari `mat_category` yang sudah jalan. §3.3 (Observer) tetap
  diblokir seperti semula, TIDAK disentuh.
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

## 13. I18N APP-WIDE — ROLLOUT BERTAHAP 7 MALAM (mulai 2026-09-07)

> Kerangka i18n (`App\Support\Localization\Translator`, `sec_user.lang_code`,
> `sys_translations`) sudah dibangun 2026-09-07 (lihat
> `docs/configurationstandard.md` §2.3 + memory `project_i18n_shell_framework`)
> — baru cakup ~8 string chrome shell paling dasar. **Keputusan user
> 2026-09-07**: lanjutkan perluasan cakupan **bertahap 1 malam/hari** (bukan
> 1 sesi maraton), dieksekusi via **scheduled task** (fitur bawaan Claude
> Code, tersimpan `~/.claude/scheduled-tasks/`).
>
> **RESET JADWAL 2026-09-09**: Malam 1 (`i18n-rollout-malam-1`, 2026-09-07
> 21:00) dan Malam 2 (`i18n-rollout-malam-2`, 2026-09-08 21:00) **fire tapi
> 0 commit dihasilkan** (gagal diam-diam, ditemukan oleh sesi
> `tmerp-backlog-autowork-1700` 2026-09-09 — root cause belum diselidiki).
> Atas instruksi user, seluruh jadwal 7-malam **digeser mulai hari ini**
> dengan jam seragam **21:00 WIB hari kerja / 22:00 WIB akhir pekan**
> (menggantikan skema lama Sabtu 22:30/Minggu 20:00): Malam 1=Rab 2026-09-09,
> ..., Malam 7=Sel 2026-09-15. Konten/scope tiap malam TIDAK berubah, cuma
> tanggal & jam. Task otonom umum `tmerp-backlog-autowork` juga diselaraskan
> ke pola yang sama (jalan 7 hari/minggu: `tmerp-backlog-autowork-1700`
> 21:00 hari kerja + `tmerp-backlog-autowork-weekend-2200` 22:00 akhir
> pekan, sebelumnya cuma 15:45 hari kerja).
>
> **RESET JADWAL #2 (2026-09-09 siang, instruksi user)**: jam mulai
> hari-kerja digeser lagi dari 21:00 → **17:00 WIB**, akhir pekan TETAP
> **22:00 WIB**. Durasi kerja tiap malam/sesi **dibatasi HANYA ~1 jam**
> (turun dari ~2 jam) — TERKECUALI **hari Selasa dapat budget PENUH ~12
> jam** (kerja maraton spt sesi `tmerp-b5` dulu). Berlaku utk task
> `tmerp-backlog-autowork-1700`/`-weekend-2200` (recurring, cek hari via
> `date` tiap kali jalan) DAN task `i18n-rollout-malam-1..7` (one-time,
> jam per-tanggal sudah disesuaikan langsung: Malam 1-3/6=17:00 1 jam,
> Malam 4-5=22:00 1 jam, **Malam 7=17:00 tapi 12 jam penuh krn jatuh hari
> Selasa 2026-09-15**). Tabel di bawah sudah mencerminkan jam baru ini.
>
> **TAMBAHAN SCOPE (instruksi user 2026-09-09 siang)**: selain translate
> string ke `sys_translations`, **setiap malam juga WAJIB pastikan
> komponen yang disentuh benar-benar TURUNAN dari library komponen shared**
> (`resources/views/components/form/*.blade.php`,
> `resources/views/components/grid/*.blade.php`) — showcase-nya sekarang ada
> di **Settings > Library Component for UI/UX** (`setup/component-library.blade.php`,
> menu `SET_UILIB`, dibangun sesi paralel 2026-09-09 pagi, 16 komponen
> `x-form.*` baru: password-input/switch/radio-group/color-picker/
> range-slider/number-stepper/otp-input/star-rating/masked-input/
> searchable-select/multi-select/phone-input/currency-input/file-dropzone/
> split-button/mini-calendar). Tujuannya: kalau nanti komponen ini perlu
> diubah (styling/behavior), cukup ubah 1 file component, bukan cari-ganti
> di puluhan layar. **Saat menyentuh 1 layar malam ini** (utk translate
> string-nya), sekalian cek: kalau layar itu punya widget yang POLANYA SAMA
> dengan salah satu komponen di library (mis. custom dropdown manual yang
> sebetulnya cocok jadi `<x-form.searchable-select>`, toggle checkbox manual
> yang cocok jadi `<x-form.switch>`) — migrasikan ke komponen library
> tsb SEKALIAN (bukan proyek terpisah, cukup selagi file itu sudah dibuka).
> **JANGAN** cari-cari gap di layar LAIN yang tidak termasuk scope malam itu
> (itu di luar anggaran waktu ~1 jam/malam, kecuali malam Selasa yang 12 jam) — cukup opportunistic di file
> yang memang sudah disentuh. Kalau ketemu gap besar/lintas-layar yang perlu
> keputusan (refactor massal), JANGAN eksekusi sendiri — catat di §10 sbg
> temuan baru, biar dibahas terpisah (pola audit-dulu-baru-rollout §10 yang
> sudah ada). Konteks lengkap 3 file WIP terkait ini per 2026-09-09 13:00 —
> `date-picker.blade.php`, `spreadsheet-table.blade.php`,
> `component-library.blade.php` — masih ada sesi paralel aktif mengerjakannya
> siang ini, cek `git log`/`git status` dulu sebelum malam ini menyentuh
> ketiga file itu supaya tidak tabrakan.
>
> **PENTING — realistis, bukan overclaim**: 7 malam ini TIDAK akan menutup
> SEMUA string aplikasi (ratusan file) — cakupannya sengaja dibatasi ke
> layar/komponen paling sering dipakai (leverage tertinggi dulu). Malam ke-7
> WAJIB checkpoint jujur (persentase progress + daftar yang masih tersisa),
> bukan klaim "selesai". Kalau mau lanjut sesudah hari ke-7, itu keputusan
> baru user, bukan otomatis diperpanjang.
>
> **Ritual penutup WAJIB tiap malam** (checklist untuk agent yang jalan):
> 1. Kerjakan HANYA scope malam itu (lihat tabel di bawah) — jangan melebar.
> 2. Tambah baris `sys_translations` (id+en) via migration baru
>    (`database/migrations/2026_09_0X_..._seed_i18n_night_N.php`), ganti
>    string literal Blade relevan jadi `Translator::t('key', 'default lama')`
>    (import `use App\Support\Localization\Translator;`).
> 3. `php artisan test` (minimal file terkait area yg disentuh malam itu) +
>    `npm run build` (kalau ada perubahan class Tailwind).
> 4. **Centang** ⬜→✅ baris malam itu di tabel bawah ini (edit file ini
>    langsung), tulis 1-2 kalimat ringkasan di kolom Catatan (key apa saja,
>    file apa saja, ada blocker atau tidak).
> 5. `git add` (spesifik, bukan `-A`) + commit + **push** ke `origin`
>    (CLAUDE.md §7.11 — wajib tiap sesi kerja selesai, bukan cuma diminta).
> 6. Kalau mepet 1 jam (atau 12 jam khusus malam Selasa) sebelum scope
>    selesai: STOP, commit apa yang sudah beres, catat sisa di kolom
>    Catatan sbg "belum tuntas: ...", JANGAN lanjut lewat batas waktu.
>
> | # | Tanggal (WIB) | Jam | Budget | Scope | Status | Catatan |
> |---|---|---|---|---|---|---|
> | 1 | Rab 2026-09-09 | 17:00 | 1 jam | **Komponen shared** (leverage tertinggi — dipakai puluhan layar sekaligus): `components/grid/*`, `components/form/*`, pagination footer, badge status generik, tombol generik (Simpan/Batal/Hapus/Tambah/Cari/Export/Refresh), modal konfirmasi Ya/Tidak/Batal, pesan validasi generik. Key prefix `shared_*`. | 🟡 | WIP dimulai sesi `tmerp-backlog-autowork-1700` 2026-09-09 siang (migration `2026_09_09_000001_seed_i18n_night1_shared.php` + pagination-footer/column-modal/sort-modal/toolbar-buttons SELESAI, date-picker SEBAGIAN, select+spreadsheet-table BELUM) — diselesaikan malam ini oleh `i18n-rollout-malam-1`. |
> | 2 | Kam 2026-09-10 | 17:00 | 1 jam | **Sisa chrome shell** yang belum ditarik malam sebelumnya (dari sesi 2026-09-07): drawer Notifikasi, drawer Chat, drawer Asisten AI, command palette (Ctrl+K), Help Center index. Audit ulang `main-menu.blade.php` cari string yg lolos. Key prefix `shell_*` (lanjutan). | ✅ | Dikerjakan 22:10-22:17 WIB (task terjadwal `i18n-rollout-malam-2` sempat fire 21:49 tapi 0 progres nyata, jadi dieksekusi ulang manual atas instruksi user langsung "run for Thu1700_2of7 now" lewat sesi `tmerp-backlog-autowork-1700`). **3 migration baru**: `2026_09_10_000002_seed_i18n_night2_shell.php` (24 key: drawer Notifikasi/Chat/Asisten AI + command palette + tombol topbar), `..._000003_seed_i18n_night2_followup.php` (27 key: ditemukan saat audit ulang `main-menu.blade.php` — modal Edit Profil/Ubah Password, greeting home, hint keyboard, dll), `..._000004_seed_i18n_night2_help_center.php` (31 key: chrome Help Center — header/tab-bar/form+list Tiket Bantuan). Total 82 key baru (164 baris id+en). Reuse `shared_btn_cancel` yang sudah ada (Malam 1) utk tombol Batal di 2 modal, tidak duplikat. Diverifikasi `Livewire::test()` manual (main-menu + help.index render OK, 0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind → tidak perlu `npm run build`. **BELUM disentuh (gap terbuka, sengaja)**: Tab "Cara Pakai Aplikasi" (konten edukasi ratusan baris) & Tab "Modul" (deskripsi tiap modul di array `$modules`) di `help/index.blade.php` — itu PROSA panjang, bukan chrome, jauh di luar budget 1 jam; juga sidebar kiri/mega-menu modul (data-driven dari `sys_app_menu`, beda mekanisme, bukan scope i18n literal-string). |
> | 3 | Jum 2026-09-11 | 17:00 | 1 jam | **Sales inti**: Inquiry Hub grid (`inquiry/hub.blade.php`), header+toolbar Inquiry form (BUKAN detail teknis dieline/costing formula), Quotation list, CO list — label & tombol saja. Key prefix `sales_*`. | ✅ | Dikerjakan 22:35-22:45 WIB, sehari lebih awal dari jadwal (task terjadwal `i18n-rollout-malam-3` fire besok 2026-09-11 17:00, dieksekusi manual malam ini atas instruksi user "run session Fri* for now"). 4 layar: `inquiry/hub.blade.php` (stat box, 3 kartu NEW/RECALC/REVISE, picker produk existing, grid Inkuiri Tersimpan — cakupan LUAS, bukan cuma toolbar), `inquiry/form.blade.php` (HANYA header+toolbar: judul mode, tombol Simpan/Confirm/Batalkan/Tandai Kalah/Imposition/Cetak Nota, form alasan cancel/lost, section title "Header Inkuiri" — TIDAK menyentuh field dimensi/bahan/proses/dieline sesuai batasan scope), `quotation/list.blade.php`, `co/list.blade.php` (termasuk detail expand-row item & external cost). 1 migration baru (`2026_09_10_000005_seed_i18n_night3_sales.php`, ~140 key `sales_*` + 2 key baru dipromosikan `shared_grid_action_open`/`shared_grid_dblclick_hint` — dipakai quotation+CO list malam ini, grid lain msh hardcode, generalisasi penuh BUKAN scope malam ini). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor, 1 false-positive "sales_note" field JSON dicek manual) + test existing `CoListExpandRowTest`+`InquiryHubStatBoxTest`+`InquiryMaterialSmokeTest`+`InquiryToolingCostSmokeTest` semua pass. Tidak ada perubahan class Tailwind. Task `i18n-rollout-malam-3` **DIHAPUS** dari scheduler (sudah tuntas, atas instruksi user).|
> | 4 | Sab 2026-09-12 | 22:00 | 1 jam | **Procurement inti**: PR/PO/PV/PQ/PRT list+entry — toolbar & label status saja (bukan field kalkulasi). Key prefix `proc_*`. | 🟡 | Dikerjakan 22:48-22:58 WIB, 2 hari lebih awal dari jadwal (task `i18n-rollout-malam-4` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 grid list SELESAI** (`pur/{pr,po,pv,pq,prt}/index.blade.php`): stat box, toolbar search, header tabel, tooltip aksi, badge status, modal picker (termasuk picker 2-langkah PV & 3-cabang PRT). ~85 key `proc_*` baru + reuse `shared_*`/`sales_*` yg identik (Buka, klik-2x, Batal, Confirm, Draft). **5x entry.blade.php (400-660 baris masing2, field kalkulasi harga/pajak/approval) BELUM disentuh** — sengaja, di luar budget, lihat instruksi asli "prioritaskan 1-2 dokumen dulu" — 5 list dianggap prioritas lebih tinggi (dipakai lebih sering) drpd entry. Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `PvStockIntegrationTest` pass. Tidak ada perubahan class Tailwind. 🟡 (bukan ✅) krn scope "list+entry" cuma separuh tuntas — sesi berikutnya lanjutkan 5 entry.blade.php kalau mau dituntaskan penuh. |
> | 5 | Min 2026-09-13 | 22:00 | 1 jam | **Inventory + Production inti**: grid Stock In/Out/Adjustment, Master Card, WO Worklist — toolbar & label status. Key prefix `inv_*`/`prod_*`. | ✅ | Dikerjakan 22:59-23:11 WIB, 3 hari lebih awal dari jadwal (task `i18n-rollout-malam-5` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 layar SELESAI**: `production/wo-list.blade.php` (WO Worklist, kecil, cepat), `inventory/stock-in.blade.php` + `stock-out.blade.php` + `stock-adjustment.blade.php` (grid+form 1 komponen di file yg sama, jadi keduanya ikut tersentuh — bukan cuma toolbar krn struktur file menyatukan grid+form; Stock Adjustment termasuk section approval/reject), `production/prodev-print.blade.php` (Master Card, dokumen cetak — label spesifikasi, section, tanda tangan, footer). 1 migration baru (`2026_09_10_000007_seed_i18n_night5_inventory_production.php`, ~155 key `inv_*`/`prod_*`). Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `ProdevPrintMastercardTest`+`ProductionWorklistWoTest` 16/16 pass. Tidak ada perubahan class Tailwind. |
> | 6 | Sen 2026-09-14 | 17:00 | 1 jam | **Finance + HRM inti**: COA, Cash & Bank, grid Employee, Attendance — toolbar & label status. Key prefix `fin_*`/`hrm_*`. | ✅ | Dikerjakan 23:17-23:28 WIB, 4 hari lebih awal dari jadwal (task `i18n-rollout-malam-6` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue"). **4/4 layar SELESAI**: `finance/coa.blade.php` (stat box, header tabel, badge tipe/saldo/recon, modal tambah/edit akun lengkap), `finance/cash-bank.blade.php` (grid + form jurnal BKM/BKK/BBM/BBK, 3 tab Rincian/Pajak/Audit — label saja, TIDAK menyentuh kalkulasi debit/kredit), `hrm/attendance-daily.blade.php` (16 label status presensi H/T-5/dst, modal override), `master/employee.blade.php` **grid saja** (list+card view, form profil karyawan 1300+ baris SENGAJA tidak disentuh — di luar scope "grid"). Payroll (tm2pyr) TIDAK disentuh sama sekali sesuai instruksi. 1 migration baru (`2026_09_10_000008_seed_i18n_night6_finance_hrm.php`, ~150 key `fin_*`/`hrm_*`). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind. |
> | 7 | Sel 2026-09-15 | 17:00 | **12 jam penuh** (jatuh hari Selasa) | **Master Data** (Customer/Supplier/Product/Material/UOM — toolbar & label) + **QA checkpoint**: `npm run build`, full `php artisan test`, tulis laporan status akhir DI SINI (perkiraan % string tertarik vs total, daftar area yg masih 100% hardcode, rekomendasi lanjutan) — JANGAN klaim 100% selesai. Key prefix `mdata_*`. Budget 12 jam berlebih dari cukup utk scope ini — kalau selesai lebih awal, boleh lanjut item BACKLOG.md lain (§0-12) sampai budget habis. | ✅ | Dikerjakan 23:28-23:42 WIB, **5 hari lebih awal** dari jadwal (task `i18n-rollout-malam-7` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue" — budget 12 jam Selasa TIDAK dipakai literal krn hari sebenarnya Kamis, dikerjakan dgn pacing efisien normal spt malam2 lain, bukan maraton 12 jam). **Bagian A (Master Data) 5/5 grid selesai**: `raw-material/index`, `produk`, `uom` (+panel konversi), `supplier`, `customer` — toolbar/label/badge saja, form lengkap tidak disentuh. ~75 key `mdata_*` baru. **Bagian B (QA Checkpoint) lengkap** — lihat laporan detail di §13.1 di bawah: `npm run build` sukses, full test suite 122 test (112 pass, 1 fail **pre-existing tidak terkait i18n** — dicatat sbg temuan, 9 skip normal Firebase-off), total 788 key unique tertarik. Diverifikasi `Livewire::test()` 5/5 Bagian A render OK + `MasterCustomerSmokeTest`/`MasterUomSmokeTest` 8/8 pass. |
>
> Riwayat lengkap tiap malam (bukan cuma centang) pindah ke
> `docs/LOG_JOB_DONE.md` begitu selesai, sama seperti item backlog lain.
> Terkait: memory `project_i18n_shell_framework`,
> `project_settings_menu_datadriven`, §11 di atas (kebijakan menu Inggris —
> BEDA sumbu, jangan digabung: itu soal `sys_app_menu.label`, ini soal
> `sys_translations`/`Translator::t()`).

### 13.1 Checkpoint Malam 7 — Status Akhir (2026-09-10 23:42 WIB)

> **Konteks eksekusi**: seluruh rencana 7 malam (awalnya dijadwalkan
> 2026-09-09 s/d 2026-09-15, 1 malam/hari) **dipercepat dan dituntaskan
> dalam SATU sesi malam ini** (2026-09-10) atas 4 instruksi berurutan dari
> user ("run session Thu*", "run session Fri*", "run routine Sat and Sun",
> "run routine Mon and Tue") — bukan dijalankan otomatis sesuai jadwal
> aslinya. Semua 5 task terjadwal (`i18n-rollout-malam-3` s/d `-malam-7`)
> **sudah dihapus dari scheduler** karena sudah tuntas dieksekusi manual.

**Ringkasan per malam — apa yang BENERAN selesai vs belum:**

| Malam | Scope | Status | Catatan |
|---|---|---|---|
| 1 | Komponen shared (grid/form/pagination/badge/tombol) | 🟡 selesai substansi, checkbox sengaja dibiarkan 🟡 | Dikerjakan sesi lain 2026-09-09, follow-up 2026-09-10 pagi. Belum ada review menyeluruh final utk pastikan 0 string `shared_*` lolos. |
| 2 | Sisa chrome shell (drawer notif/chat/AI, command palette, modal profil/password, Help Center chrome) | ✅ tuntas | Tab "Cara Pakai Aplikasi" & "Modul" di Help Center (ratusan baris prosa) **SENGAJA tidak disentuh** — bukan chrome. |
| 3 | Sales inti (Inquiry Hub, header Inquiry form, Quotation list, CO list) | ✅ tuntas | ~140 key. Field kalkulasi HPP/costing formula tidak disentuh (sesuai batasan). |
| 4 | Procurement inti (grid PR/PO/PV/PQ/PRT) | 🟡 **PARTIAL** | **5/5 grid list selesai**, **5x `entry.blade.php` (400-660 baris/file) BELUM disentuh sama sekali** — field approval/kalkulasi harga & pajak di layar entri PR/PO/PV/PQ/PRT masih 100% hardcode Indonesia. Ini gap TERBESAR dari seluruh rollout. |
| 5 | Inventory + Production inti (Stock In/Out/Adjustment, WO Worklist, Master Card) | ✅ tuntas | Termasuk alur approval Stock Adjustment. |
| 6 | Finance + HRM inti (COA, Cash & Bank, Attendance Daily, grid Employee) | ✅ tuntas (grid saja utk Employee) | Form profil karyawan (1300+ baris: keluarga, riwayat, dst) **SENGAJA tidak disentuh**. Payroll (tm2pyr) **tidak disentuh sama sekali** sesuai instruksi. |
| 7A | Master Data (grid Customer/Supplier/Produk/Raw Material/UoM) | ✅ tuntas (grid saja) | Form lengkap tiap master (alamat/kontak/dimensi/dst) tidak disentuh. |
| 7B | QA Checkpoint | ✅ selesai (laporan ini) | Lihat detail di bawah. |

**Area besar yang MASIH 100% hardcode Bahasa Indonesia** (bukan generalisasi
"sisanya", ini daftar konkret per modul):
- **Procurement — 5 layar entry** (`pur/{pr,po,pv,pq,prt}/entry.blade.php`):
  seluruh field kalkulasi harga, pajak, approval PR/PO/PV/PQ/PRT.
- **Master Employee — form profil penuh** (`master/employee.blade.php`,
  1300+ baris): tab identitas, alamat, keluarga, riwayat pendidikan,
  kontrak, kedisiplinan detail, foto — hanya grid yang tertarik.
- **Semua form Master Data lainnya** (Customer/Supplier/Produk/UoM/Raw
  Material): field detail (alamat, kontak, dimensi fisik, konversi
  satuan, dst) — hanya grid+toolbar yang tertarik.
- **Help Center — konten edukasi** (`help/index.blade.php` tab "Cara Pakai
  Aplikasi" & "Modul"): ratusan baris teks panduan & deskripsi modul.
- **Payroll (tm2pyr) — seluruh 11 layar**: SENGAJA di luar scope 7 malam
  ini (risiko tabrakan, modul sedang aktif dikembangkan terpisah).
- **Production Scheduling** (Scheduling Board, MPS Spreadsheet, MTS
  Forecast, Rule Library, dll) — belum tersentuh sama sekali oleh
  rollout 7 malam ini (di luar scope yang diminta).
- **HRM lainnya** (Recruitment 9 layar, Org Chart, Shift Roster, Leave
  Request, Correction Request, GPS Approval) — belum tersentuh.
- **Dispatch/Delivery, Finance lanjutan** (DP Invoice, laporan-laporan
  Outstanding PR/PO/PV, Win-Rate) — belum tersentuh.
- **Semua layar cetak/print** kecuali Master Card (Nota Inquiry, Nota CO,
  cetak PO/PR/PQ/PRT, dll) — belum tersentuh.
- **Panel Filament `/admin`** (HRM cluster) — pakai native Filament
  labels, mekanisme i18n `Translator::t()` shell TIDAK terhubung ke panel
  Filament (arsitektur terpisah, `APP_LOCALE` env-based, bukan
  `sec_user.lang_code`).

**Perkiraan cakupan (KASAR, bukan presisi)** — metode: hitung baris
`sys_translations` (lang_code='id') dibagi 2 (krn tiap key = 1 baris
id + 1 baris en):
- **Total 788 key unik** tertarik (9 prefix domain: `sales_`134,
  `inv_`122, `fin_`102, `shell_`91, `proc_`81, `mdata_`75, `hrm_`60,
  `shared_`55, `prod_`52, sisanya varian lama `login_`/`btn_`/dst).
  Dipanggil via 987 titik `Translator::t()` di seluruh Blade (reuse key
  yang sama di banyak tempat, jadi titik panggil > jumlah key unik).
- Total file layar (`resources/views/pages/**/*.blade.php`): **135
  file**. Proxy kasar (grep atribut `label=`/`placeholder=`/`title=` yang
  BELUM diwrap `Translator::t()`, TIDAK termasuk teks polos di
  `<td>`/`<h1>`/tombol tanpa atribut/string Alpine JS): **masih ~1360
  atribut belum tertarik** — rasio proxy ini ~42% tertarik, TAPI proxy ini
  cuma subset kecil dari total teks UI beneran (tidak menghitung teks
  polos/JS/komponen `<x-form.*>` internal yang jumlahnya jauh lebih
  banyak). **Perkiraan realistis cakupan keseluruhan aplikasi: sekitar
  15-25% dari total string UI** — MASIH SEBAGIAN KECIL, BUKAN mayoritas.
  **Aplikasi ini TIDAK bisa diklaim "sudah multi-bahasa"** — toggle
  `lang_code` ID/EN baru benar-benar berfungsi penuh di area shell +
  9 modul/layar yang disebut di atas, sisanya (mayoritas modul transaksi,
  semua form detail, semua cetak/print kecuali Master Card, Payroll,
  Production Scheduling, Recruitment, panel Filament) masih 100%
  hardcode Indonesia apa pun pilihan bahasa user.

**QA — hasil `npm run build`**: sukses, 0 error baru (1 warning lama
tak terkait — parsing komentar CSS di `docs/mockups/login.html`, bukan
kode aplikasi, sudah ada sebelum sesi ini).

**QA — hasil `php artisan test` (full suite)**: 122 test, **112 pass**,
**1 fail**, 9 skip (skip normal — Firebase emulator off, baseline lama).
**1 kegagalan**: `ProductionSchedulingBoardSmokeTest::test_board_renders_seeded_operations`
— **BUKAN disebabkan perubahan i18n malam ini** (file yang disentuh
malam ini tidak menyentuh Production Scheduling sama sekali). Diinvestigasi:
data dummy WO2600231 ada di DB, tapi assert mencari string **"Plong 1"**
(nama mesin, dari migration `2026_08_28_000002_seed_prd_scheduling_board_dummy.php`
baris 30) sementara operasi tersimpan sekarang bernama proses **"Plong"**
(tanpa "1") — dan employee **"Budi Santoso"** (dicari migration yg sama
baris 68) **TIDAK DITEMUKAN** di `emp_employee` sama sekali. Indikasi kuat:
data Master Employee/Machine sudah berubah (rename/hapus) oleh sesi lain
sejak seeder dummy ini dibuat 2026-08-28, seeder tidak pernah di-refresh
mengikuti perubahan itu. **Dicatat sbg temuan terpisah, TIDAK diperbaiki
malam ini** (di luar scope i18n, perlu investigasi Master Employee/Machine
mana yang berubah dan kapan, keputusan ada di sesi lain/user).

**Rekomendasi lanjutan (kalau user mau meneruskan i18n)**, urut prioritas:
1. ~~Tuntaskan 5 entry.blade.php Procurement~~ — **SELESAI 2026-09-11**
   (instruksi user "run now" setelah checkpoint §13.2). PR/PO/PV/PQ/PRT
   semua label/placeholder/tooltip/confirm/error dibungkus `Translator::t()`,
   ~190 key baru (`2026_09_11_000001_seed_i18n_procurement_entry_screens.php`).
   Diverifikasi render PR/PO/PV via `Livewire::test()` data live; PQ/PRT
   (tabel kosong) pakai record dummy sementara lalu dihapus lagi (balik ke
   0 baris). Smoke test 12/12 tetap pass. Commit `659982c`.
2. **Form lengkap Master Data** (Customer/Supplier/Produk/Employee) — saat
   ini cuma grid, form detail (mayoritas field per-master) masih hardcode.
3. **Production Scheduling** — modul paling kompleks (Scheduling Board,
   MPS, MTS Forecast) sama sekali belum tersentuh, volume string besar.
4. **Recruitment (9 layar)** — modul besar berikutnya yang 100% hardcode.
5. **Semua cetak/print selain Master Card** — Nota Inquiry/CO, cetak
   PO/PR/PQ/PRT — dokumen resmi yang customer/supplier lihat langsung.
6. **Panel Filament `/admin`** — perlu keputusan arsitektur terpisah dulu
   (jembatani `Translator`/`sec_user.lang_code` ke Filament yang pakai
   `APP_LOCALE`, bukan sekadar "lanjut key baru").
7. Payroll (tm2pyr) baru masuk antrian **setelah** modul lain, sesuai
   alasan risiko tabrakan yang sudah dicatat berkali-kali di file ini.

### 13.2 Audit kesiapan infrastruktur multi-bahasa (2026-09-10 23:50 WIB)

> Instruksi user setelah checkpoint §13.1: "make sure app ready for
> multilanguage" — DIBACA sebagai audit kesiapan **mekanisme/infrastruktur**
> i18n (bukan menambah cakupan terjemahan string baru lagi, itu sudah
> dicatat lengkap di §13.1). Ditemukan 1 bug nyata (diperbaiki malam ini)
> + 2 gap desain yang butuh keputusan user (BELUM diperbaiki, sengaja).

**BUG NYATA ditemukan & DIPERBAIKI**: `App::setLocale()` **TIDAK PERNAH**
dipanggil di mana pun di aplikasi ini sebelum malam ini. Akibatnya
`Carbon::translatedFormat()` (dipakai ratusan kali utk tanggal di
hampir semua layar transaksi) SELALU render pakai `config('app.locale')`
statis dari `.env` (`APP_LOCALE=en`) — **TERLEPAS dari `sec_user.lang_code`
masing-masing user**. Efeknya nyata utk SEMUA user, bukan cuma yang pilih
EN: user ber-`lang_code='id'` (default, mayoritas — kolom defaultnya
memang 'id') tetap melihat nama hari/bulan Bahasa Inggris ("Thursday, 10
September") walau seluruh UI di sekitarnya Bahasa Indonesia. Dibuktikan
lewat tinker: `Carbon::parse(...)->translatedFormat('l, d F Y')` sebelum
fix = "Thursday, 10 September 2026" (locale selalu 'en'); sesudah
`App::setLocale('id')` = "Kamis, 10 September 2026" (benar).
**Perbaikan**: middleware baru `App\Http\Middleware\SetUserLocale`
(`app/Http/Middleware/SetUserLocale.php`) — baca `$request->user()->lang_code`
(fallback `'id'` kalau guest/kosong), panggil `App::setLocale()`. Didaftarkan
sbg alias `'locale'` di `bootstrap/app.php`, dipasang di grup route utama
(`Route::middleware(['auth', 'locale'])` di `routes/web.php`, dulu cuma
`'auth'`) **dan** di `authMiddleware()` panel Filament
(`app/Providers/Filament/AdminPanelProvider.php`) supaya tanggal di
`/admin` (Filament Table `->date()` columns, pakai Carbon jg) ikut
konsisten. `Translator::t()` sendiri **TIDAK terpengaruh bug ini** — itu
baca `sys_translations` langsung per `lang_code`, tidak lewat
`App::getLocale()`, jadi teks yang sudah diterjemahkan (§0-13 di atas)
tetap benar dari awal; ini murni soal FORMAT TANGGAL yang lolos.
Diverifikasi: HTTP kernel riil 2 user (`lang_code` id vs en) ->
`App::getLocale()` ikut berubah sesuai; full test suite 122 test tetap
112 pass/1 fail (sama persis, gagalnya yg sudah dicatat §13.1, tidak
bertambah) — 0 regresi dari perubahan middleware ini.

**GAP DESAIN #1 — format angka TIDAK ikut bahasa (BELUM diperbaiki,
butuh keputusan user)**: `sys_languages` punya kolom
`decimal_separator`/`thousand_separator` per bahasa (EN: `.`/`,` ala
"1,234.56", ID: `,`/`.` ala "1.234,56") yang bisa diedit lewat Setup >
Language (`setup/language.blade.php`) — TAPI kolom ini **tidak pernah
dibaca** oleh kode manapun. Setiap `number_format($x, 0, ',', '.')` di
seluruh aplikasi (ratusan titik panggil, semua angka uang/qty/dst)
HARDCODE format Indonesia, terlepas dari `lang_code` user. Ini **BUKAN
bug sesederhana tanggal** — mengganti semua titik panggil `number_format()`
jadi dinamis ikut `sys_languages` adalah pekerjaan besar (ratusan file)
DAN ada pertanyaan desain nyata yang perlu dijawab user dulu: apakah
Rupiah/angka bisnis di ERP ini **memang harus ikut format Indonesia
SELALU** (banyak ERP perusahaan Indonesia sengaja begitu, konsisten
dgn dokumen resmi/faktur pajak yang WAJIB format Indonesia terlepas
bahasa UI) atau **harus ikut toggle bahasa** juga? Kalau jawabannya
"selalu Indonesia" (dugaan paling masuk akal utk konteks ERP lokal),
maka kolom `decimal_separator`/`thousand_separator` di `sys_languages`
justru harusnya DIHAPUS/diabaikan (bukan gap yang perlu ditutup) —
**JANGAN mulai kerjakan ini tanpa konfirmasi user**, sesuai arahan
"jangan tebak keputusan arsitektural".

**GAP DESAIN #2 — `sys_languages.is_default` tidak konsisten (kosmetik,
prioritas rendah)**: baris `en` punya `is_default=1`, baris `id` py
`is_default=0` — padahal migration `2026_09_07_000008_add_lang_code_to_sec_user.php`
(komentarnya) menyatakan asumsi `sys_languages.is_default` SEHARUSNYA
`id` saat itu ditulis. Ditelusuri: kolom ini **cuma dipakai utk urutan
tampil** di 2 layar Setup (`setup/language.blade.php`,
`setup/localization.blade.php`, `orderByDesc('is_default')`) — TIDAK
memengaruhi `sec_user.lang_code` default (kolom itu py default `'id'`
sendiri di level DB, independen). Jadi ini murni salah label
display-order, bukan bug fungsional. **Tidak diubah malam ini** (data
production, bukan kode — di luar wewenang mengubah tanpa diminta
eksplisit), cukup dicatat di sini kalau user mau membetulkan lewat UI
Setup > Language sendiri (centang "Default" di baris Bahasa Indonesia).

**Kesimpulan kesiapan**: mekanisme INTI (`Translator::t()` + toggle
`lang_code` + fallback aman utk key hilang + sekarang date-locale ikut
benar) sudah **solid & production-ready** utk cakupan yang SUDAH
diterjemahkan (§0-13). Yang BELUM "ready" murni soal **cakupan konten**
(sudah dijelaskan detail di §13.1 — modul mana saja masih hardcode),
bukan lagi soal mekanisme yang rusak/bocor.

---

## 14. DATA INTEGRITY — AUDIT KETERKAITAN IDENTITAS (sec_user ↔ emp_employee ↔ dst)

> **Instruksi user 2026-09-09**: "masukkan ke pekerjaan backlog bahwa hal
> ini tidak boleh terjadi, tulis apa saja yang harus di-check agar tidak
> terulang". **Dikerjakan (prosesnya) nanti malam** — entry ini HANYA
> menulis rencana pengecekan, BELUM ada eksekusi apa pun.

**Kronologi insiden**: user melapor lewat screenshot Self-Service Karyawan
— header profil masih menampilkan "Budi Santoso" / "Senior Heidelberg
Operator" padahal akun login (`sec_user` id=1, login `johiwan`) baru saja
dibetulkan namanya jadi "Johannes Iwan". Ditemukan bahwa header itu
sumbernya BUKAN `sec_user`, melainkan `emp_employee` (master HR) yang
ditaut lewat `sec_user.employee_id` — dan `employee_id` itu menunjuk ke
`emp_employee` id=1 (TM-00001) yang isinya masih 100% data dummy testing
dari `HrmMasterSeeder.php`. Root cause ganda:
1. **Niat seeder vs kenyataan DB sudah menyimpang tanpa jejak.**
   `HrmMasterSeeder.php:39-42` eksplisit menaut TM-00001 ke login **`admin`**
   (dulu juga kebetulan bernama "Budi Santoso", niatnya cuma sample data
   testing). Tapi di DB live, `admin.employee_id` = NULL dan yang tertaut ke
   TM-00001 justru **`johiwan`** (akun asli user) — perubahan ini tidak ada
   di kode/migration manapun (dicek via `grep`), berarti dilakukan manual
   (tinker/DB langsung) di sesi lampau tanpa dicatat.
2. **Tidak ada mekanisme yang mendeteksi mismatch.** Begitu `sec_user.name`
   dibetulkan tapi `emp_employee.full_name` yang ditaut tidak ikut, tidak
   ada validasi/dashboard/audit apa pun yang akan bunyi — baru ketahuan
   karena user kebetulan buka halaman itu di browser.

**Yang harus DICEK malam ini** (audit dulu, jangan langsung ubah data tanpa
lapor — ikuti pola konfirmasi yang sudah dipakai sesi ini):

1. **Audit semua `sec_user` dengan `employee_id IS NOT NULL`** — bandingkan
   `sec_user.name` vs `emp_employee.full_name` di baris yang ditaut untuk
   TIAP baris (bukan cuma id=1). Laporkan tiap ketidakcocokan nama, jangan
   langsung perbaiki sepihak.
2. **Cek tabel lain yang ikut menyimpan identitas per-`employee_id`**
   (`emp_family`, data attendance/payroll kalau sudah ada isinya untuk
   employee_id=1, dst) — pastikan tidak ada sisa data dummy lain yang
   nyasar ke identitas akun real user selain yang sudah dibetulkan sesi ini
   (email, keluarga, jabatan, NIK, NPWP, telepon, tanggal join).
3. **Cek balik akun dummy `admin`** (id_user=213, `employee_id` masih NULL)
   — sesuai niat asli seeder, apakah dia yang seharusnya ditaut ke TM-00001
   supaya portal self-service dummy tetap ada sample data untuk testing.
   Kalau iya: usulkan tautkan `admin` ke TM-00001 (JANGAN hapus/ubah isi
   TM-00001 — tetap dummy, cuma pindah pemiliknya — sesuai prinsip
   `feedback_keep_dummy_data`).
4. **Cari pola seeder/migration serupa** yang menautkan `employee_id`/
   entity lain berdasarkan ASUMSI (nama kebetulan sama, urutan insert,
   dsb.) tanpa constraint tegas — pola persis yang menyebabkan bug ini
   (lihat komentar `HrmMasterSeeder.php:39-42`). Kalau ada pola serupa di
   modul lain (Payroll `pyr_*`, Attendance `att_*`, Recruitment), tandai
   sebagai fragile, jangan langsung diubah.
5. **Usulkan (jangan langsung bangun — ini keputusan arsitektural, perlu
   konfirmasi user dulu) mekanisme pencegahan jangka panjang**, sampaikan
   opsi & trade-off, contoh kandidat:
   - Artisan command audit (`php artisan data:audit-identity` atau
     sejenis) yang expose mismatch `sec_user.name` vs
     `emp_employee.full_name` untuk semua baris tertaut, bisa dijalankan
     manual/terjadwal.
   - Validasi di layar yang mengubah `employee_id` (Master Employee/
     Security User): tampilkan preview nama employee yang akan ditaut,
     supaya operator sadar kalau salah SEBELUM simpan.
   - Aturan kerja (non-kode): setiap koreksi data identitas manual
     (tinker/DB langsung, di luar migration/seeder) WAJIB cross-check
     SEMUA tabel yang overlap (checklist poin 2 di atas) dalam satu sesi,
     bukan reaktif menunggu user menemukan sendiri per-layar.
6. Tulis hasil audit (temuan mismatch, keputusan `admin`↔TM-00001, opsi
   pencegahan yang dipilih) ke `docs/LOG_JOB_DONE.md`, lalu hapus/tandai
   selesai section ini. **Tidak ada perubahan data/kode tanpa persetujuan
   eksplisit user** untuk temuan apa pun di luar yang sudah dikonfirmasi
   sesi ini (email/keluarga/jabatan/NIK/NPWP/telepon/join_date TM-00001↔
   johiwan, itu sudah selesai & tidak perlu diulang).

---

## 15. PAYROLL & TIME ATTENDANCE — STATUS & STAGE HANDOFF (2026-09-09)

> Ditulis atas instruksi user ("buatkan stage buat saya pindah ke prompt
> lain, agar prompt lain paham") setelah checklist 11-layar Payroll
> selesai. Tujuannya supaya sesi/prompt berikutnya yang menyentuh modul
> ini tidak perlu menggali ulang dari nol.

**Branch kerja:** `claude/bentuk-customer-order-clarity-jnehjg` (branch
bersama, dipakai beberapa sesi paralel — lihat peringatan multi-sesi di
bawah). **Commit terakhir milik Payroll:** `44d0e09` "Payroll: Modul 07
(Rekening Bank) + Modul 09 (GPS Clock-in sungguhan)".

### Checklist 11 layar — SEMUA SELESAI
01 Upload Finger/Excel · 02 Grid Presensi Harian + Override · 03 Payroll
Batch Run · 04 Cetak Slip Gaji · 05 Generator Transfer Bank · 06 Roster
Cash + Pecahan Uang · 07 Master Karyawan + Rekening Bank · 08 Laporan
Kedisiplinan Harian · 09 Mobile GPS Clock-in (backend nyata, pakai
`navigator.geolocation` browser sungguhan + geofence Haversine, BUKAN
simulasi lagi) · 10 ESS Pengajuan Cuti/Sakit · 11 HR Approval Workflow.

Termasuk: enkripsi gaji AES-256-GCM (`App\Casts\PayrollEncrypted`, key
`VERBA_SANCTA`), BPJS + PPh21 opt-in per-karyawan (flag, bukan global),
PWA installable di HP (manifest dinamis + service worker + ikon GD),
validasi engine terhadap data Excel lapangan riil (`docs/attachments/`).

### ⚠️ Pertanyaan bisnis TERBUKA — belum dijawab user
**Eligibilitas Uang Roti**: engine saat ini cuma bayar ke jabatan yang
namanya mengandung "Staff"/"SPV" (mengikuti teks literal Tier2 §4.4),
tapi data Excel HR asli menunjukkan level **Operator** di Produksi juga
menerima Uang Roti. **Belum diubah** — menyangkut uang, jangan ubah tanpa
konfirmasi eksplisit user. Rujukan: memory Claude Code
`project_payroll_real_data_validation.md`.

### Gap kecil belum dikerjakan (bukan permintaan eksplisit, dicatat "belum")
- Format file transfer bank masih CSV generik, belum format clearing
  resmi BCA/Mandiri.
- Tabel TJ_KEAHLIAN & rate lembur-per-jam masih free-form per-karyawan,
  belum berbasis lookup jabatan.
- PPh21/BPJS belum direkonsiliasi ke sistem faktor "GAJI PER JAM" di
  slip cetak.
- Logo perusahaan asli belum dipasang di ikon PWA (masih ikon generik
  hasil PHP GD, `Brand::logoPath()` sudah ada tapi belum dipakai di sini).

### ⚠️ Peringatan multi-sesi (per 2026-09-09)
Working tree branch ini punya perubahan uncommitted milik sesi LAIN yang
sedang berjalan paralel (Component Library/i18n/Kanban) — bukan bagian
modul Payroll: `resources/js/component-library.js`,
`date-picker.blade.php`, `spreadsheet-table.blade.php`,
`component-library.blade.php`, `routes/web.php`, `docs/ddl/tm2sys.sql`,
plus file baru di `docs/ddl/`, `docs/md_sales/`, `docs/mockups/`,
`resources/views/components/kanban/`. **Sesi Payroll berikutnya jangan
commit/revert file-file itu** — bukan tanggung jawab modul ini.

### Rujukan lengkap
`docs/LOG_JOB_DONE.md` (entri "2026-09-07 (lanjutan 6)"), memory Claude
Code: `project_gps_clockin_and_bank_screens.md`,
`project_payroll_flow_screens.md`, `project_payroll_foundation_built.md`,
`project_payroll_real_data_validation.md`, `project_pwa_mobile_install.md`.

---

## 16. CHAT ANTAR USER — LENGKAPI "NEW CHAT" ALA WHATSAPP (instruksi user 2026-09-09)

> **Permintaan user**: "untuk user profile, saya ingin membuat interface nya
> mirip whatsapp jadi awalnya bisa ada nama-nama yang di ajak chat. Karena
> chat ini tujuan nya komunikasi antar pengguna yang saat ini login." —
> **BELUM dikerjakan, ini catatan backlog saja** (user eksplisit minta
> dicatat dulu, bukan dieksekusi sekarang).

**State saat ini** (`resources/views/pages/main-menu.blade.php`, panel chat
sekitar baris 622-707 & 1010-1036, model `App\Models\Sys\SysChat{Conversation,Message,Participant}`,
tabel `tm2sys.sys_chat_*`): panel chat HANYA menampilkan daftar **percakapan
yang sudah ada** (`conversations()` — query `SysChatParticipant` milik user
login, join `SysChatConversation`). **TIDAK ADA cara memulai chat baru** —
kalau `conversations()` kosong, panel cuma menampilkan teks statis "Belum
ada percakapan." tanpa tombol/aksi apa pun. Ini gap-nya: user tidak bisa
memilih nama rekan kerja utk diajak ngobrol pertama kali, persis keluhan di
atas.

**Referensi implementasi SUDAH ADA, tinggal di-adaptasi** —
`docs/mockups/tmerp-chat-module.zip` (paket referensi eksternal, di-drop ke
repo 2026-09-07, SEBAGIAN sudah diintegrasikan jadi kode live di
`main-menu.blade.php` tapi bagian "New Chat"-nya TIDAK ikut terbawa). Isi
zip (`app/Livewire/Chat/ChatDrawer.php`) sudah py fitur yang persis
diminta:
- `public $showNewChatModal` + `public $userSearchQuery` — toggle modal
  pencarian user.
- `availableUsers()` (`#[Computed]`) — `SecUser::where('activate', 1)
  ->where('login', '!=', Auth::user()->login)->where(name/login LIKE
  %query%)->limit(10)` — daftar nama yang bisa diajak chat, filter search,
  MAKSIMAL 10 (pertimbangkan naikkan/tambah pagination kalau user makin
  banyak).
- `startDirectChat($targetLogin)` — cek dulu apakah sudah ada thread 1-on-1
  dgn user itu (`is_group=0` + kedua login jadi participant), kalau ada
  buka thread itu, kalau belum `DB::transaction()` bikin `SysChatConversation`
  baru + 2 baris `SysChatParticipant` (punya sendiri `last_read_at=now()`,
  punya lawan bicara `last_read_at=null`).

**Catatan porting (BUKAN drop-in langsung)**: file referensi pakai
namespace/koneksi model GENERIK (`App\Models\SecUser`,
`App\Models\SysChatConversation`, dst — TANPA `Sys\` + tanpa `$connection`
eksplisit), sedangkan app ini sungguhan pakai `App\Models\Sys\SecUser` +
`App\Models\Sys\SysChat*` dgn `$connection = 'sys'` (lihat CLAUDE.md §2:
default DB `tm2sys`, model Sys/Master/Sdi/Acc). Referensi ini juga
komponen Livewire TERPISAH (`<livewire:chat.chat-drawer>`) sedangkan live
app menyatukan logicnya langsung di `pages::main-menu` (SFC) — porting
harus disesuaikan pola arsitektur project ini (properti `showNewChatModal`/
`userSearchQuery` + method `availableUsers()`/`startDirectChat()` masuk ke
class Livewire yang SUDAH ADA di `main-menu.blade.php`, bukan bikin
komponen Livewire baru terpisah), bukan copy file mentah-mentah.

**Desain UI (instruksi eksplisit)**: mirip WhatsApp — state kosong/awal
panel chat menampilkan **daftar nama pengguna** yang bisa diajak ngobrol
(bukan cuma teks "Belum ada percakapan"), klik satu nama langsung buka/
mulai thread. Cek dulu `resources/views/livewire/chat/chat-drawer.blade.php`
di dalam zip utk contoh markup modal "New Chat"-nya sebelum bikin dari nol.

**[TAMBAHAN instruksi user 2026-09-10] Tema WAJIB ikut pilihan tema user
yang sedang aktif, JANGAN hardcode.** Paket referensi `tmerp-chat-module.zip`
hardcode tema GELAP sendiri (`bg-[#101b30]`/`bg-[#1f2742]`, dst) yang beda
total dari shell utama — ini **TIDAK BOLEH dibawa apa adanya**. Saat porting,
warna drawer/modal chat harus tunduk ke sistem tema yang sudah ada
(`docs/themestandard.md`: `tm2sys.sys_theme`, 4 mode Pastel/Cerah/Dark/Hitam
x 3 corak Biru/Hijau/Merah, dipilih user lewat picker di dropdown avatar) —
sama seperti komponen shell lain (`main-menu.blade.php`), BUKAN palet warna
sendiri milik modul chat. Kalau user sedang pilih mode terang, drawer chat
harus ikut terang; kalau pilih Dark/Hitam, baru ikut gelap — bukan gelap
selalu apa pun tema aktifnya.

**Scope**: chat ini **internal antar pengguna yang sedang login** ke ERP
(karyawan/staff), BUKAN chat dengan pihak luar (customer/portal rekrutmen)
— jangan campur dgn `recruitment/portal` atau modul lain manapun.

**Belum diputuskan**: apakah daftar "New Chat" tampilkan SEMUA `sec_user`
aktif (bisa banyak kalau perusahaan besar) atau dibatasi per-modul/per-role
tertentu — cek dgn user kalau soal ini jadi relevan pas eksekusi.

---

## 18. [PRIORITAS — SEGERA] RANTAI DATA INQUIRY → PRODEV → MASTER CARD (instruksi user 2026-09-17)

> User: "Kekurangan di atas itu tulis di backlog dan HARUS segera dikerjakan
> agar bisa menjadi rangkaian data ke ProDev." Acuan hasil = Master Card
> ALSAMSI EXCLUSIVE TOP (DS003047): routing 8 langkah, Warna Pertama CS
> (K-C-M-Y), 4 UP, bahan 84,5 x 72 LG, pisau 83,4 x 70,1.

Keputusan user 2026-09-17 (jangan ditanya ulang):
- Rancangan engine DISETUJUI, dengan syarat **tidak ada tabel dobel** —
  optimasi jumlah tabel (pakai ulang `ref_finishing_type`, `prod_process`,
  `prod_part_finishing`, `prod_part_ink` yang sudah ada).
- Urutan finishing default Laminasi → UV Spot → Hot Print → Plong, tapi
  **bisa diubah admin lewat setting**. Emboss TIDAK selalu bareng Plong —
  harus fleksibel (aturan per finishing, bukan hardcode).
- "CS" = warna proses full color (C, M, Y, K) — istilah umum cetak offset.
- Sampel UAT AL SAMSI Top diselaraskan ke Master Card (pelanggan PAK MUKRI).
- Flag data dummy/legacy: tujuannya **disembunyikan, bukan dihapus**; ada
  fasilitas **reset dummy kembali seperti semula** (kalau terhapus/terubah
  saat UAT); hide/show diatur **setting admin** dengan UI sendiri.

| # | Gap | Status |
|---|---|---|
| 18.1 | Ukuran pisau + kode pisau ("…ISI n MATA") disimpan dari hasil ProDev | SELESAI 2026-09-17 (prod_layout_group/iqp_layout.knife_*) |
| 18.2 | Finishing Inquiry terstruktur: pilih dari `ref_finishing_type` + atribut (warna foil "Gold Puyeh", doff/glossy) | SELESAI 2026-09-17 (iqp_part_finishing) |
| 18.3 | Engine Ink: `cfg_color` + analisa warna artwork (% & cm² per tinta, warna dominan), unit K-C-M-Y | SELESAI 2026-09-17 (analisa di browser; file PDF/AI belum -- server tanpa Imagick/Ghostscript, perlu pdf.js) |
| 18.4 | Engine BOP: routing standar offset + sisipan dari finishing (aturan diatur admin) | SELESAI 2026-09-17 (prod_bop_rule + layar BOP Rule Setting) |
| 18.5 | Engine BOM: bahan utama + bahan finishing (plastik laminasi lebar roll >= lebar lembar, foil) + tinta | SELESAI 2026-09-17 |
| 18.6 | Isi per pack (Pack Wrapping /400 pcs) diinput di Inquiry | SELESAI 2026-09-17 (iqp_part.pack_qty) |
| 18.7 | Flag `data_origin` (LIVE/DUMMY/LEGACY) + setting admin hide/show + reset dummy | SELESAI 2026-09-17 (210 tabel; Settings > Dummy & Legacy Data; query DB::table/VIEW belum ikut tersaring) |
| 18.8 | BOP/BOM/Ink bisa di-edit (override) officer di ProDev dengan dropdown; tidak ditimpa engine | SELESAI 2026-09-17 |
| 18.9 | BOM: qty kebutuhan + stok tersedia gudang + tombol Buat PR per baris yang kurang; tinta & lem dgn estimasi pemakaian | SELESAI 2026-09-17 -- item kategori Others/Lamination belum punya akun COA sehingga PR-nya ditolak; takaran = estimasi awal |
| 18.10 | BOP: mesin, target default, waste & toleransi (qty + %) per langkah | SELESAI 2026-09-17 (persen default = estimasi awal) |
| 18.11 | BOP: tombol Subkon per langkah yang tidak in-house -> PR subkon -> Surat Jalan subkon yang menunjuk CO (track costing) | SELESAI 2026-09-17 (layar entry DO belum khusus SUBKON) |
| 18.12 | BOM & BOP mengalir ke Work Order; officer PPIC yang memastikan final | BELUM -- alur dicatat di bawah; WoGenerationService belum membaca BOM/BOP baru |

**Alur data & sistem (dikunci user 2026-09-17):**

```
Inquiry Product (finishing, bahan, warna, isi/pack, artwork, qty)
   -> ProDev: engine BOP / BOM / Ink  (officer ProDev boleh override)
        -> SAVE  : tulis 2 arah (prod_part_* master + iqp_part_* Inquiry)
        -> CONFIRM: ke Product Costing
   -> BOM kurang stok -> Purchase Requisition bahan (MRP)
   -> BOP langkah non in-house -> PR subkon -> Surat Jalan subkon (wajib CO asal)
   -> Customer PO -> CO -> Work Order: BOM & BOP jadi bahan & operasi WO
        -> officer PPIC yang memastikan FINAL (bukan otomatis terkunci)
```
- Surat jalan subkon = surat jalan tetap yang terikat CO (customer PO -> CO);
  CO wajib tertulis supaya biaya subkon terlacak ke costing order itu.

---

## 19. [MALAM] LANJUTAN ENGINE PRODEV & SUBKON (instruksi user 2026-09-18)

| # | Pekerjaan | Status |
|---|---|---|
| 19.1 | Layar Surat Jalan Subkon sendiri (entry + cetak): penerima supplier, rujukan PR & CO, barang setengah jadi keluar/masuk. Saat ini surat jalan SUBKON dibuat otomatis dari ProDev dan tampil di daftar DO, tapi layar entry & cetaknya masih memakai bentuk surat jalan customer. **User minta dikerjakan malam** (pengingat dipasang 2026-09-18 20:00) | BELUM |
| 19.2 | Engine takaran pemakaian bahan otomatis dari data pemakaian nyata 1 periode (bulan), officer tetap bisa override manual | SELESAI 2026-09-18 (UsageRateEngine + prodev:usage-rates, usulan + saklar AUTO per aturan) |
| 19.3 | Jalur BOM & BOP -> Work Order setelah Inquiry berharga -> CO CONFIRMED & APPROVED; officer PPIC memastikan final | SELESAI 2026-09-18 (WoGenerationService: syarat CO CONFIRMED + costing dipublish) |
| 19.4 | Imagick + Ghostscript supaya artwork PDF/AI bisa dianalisa di server | SELESAI 2026-09-18 (Imagick 3.8.1 + Ghostscript 10.08 terpasang; upload PDF/AI di ProDev dianalisa & dipratinjau server) |

---

## 17. KOP DOKUMEN PRINT — NAMA COMPANY HARUS IKUT ADA/TIDAKNYA PPN
(instruksi user 2026-09-11, diperjelas 2026-09-11, **BELUM DIEKSEKUSI** —
user eksplisit minta audit+catat dulu, perbaikan menyusul)

> **Permintaan user (versi awal)**: "semua print out HARUS pakai HEADER nama
> company 'TELASIH MAKMUR' bukan CV Langgeng Jaya Makmur. Nama 'CV Langgeng
> Jaya Makmur' digunakan hanya pada dokumen yang ada PPN nya dan itupun pada
> bagian tertentu seperti 'Surat Jalan', 'Invoice', 'Purchase Order with
> PPN'."
>
> **Koreksi/klarifikasi user (penting, ganti pemahaman di atas)**: "INGAT
> semua yang mengandung PPN pakai CV Langgeng Jaya Makmur, itupun bisa di
> override oleh user. Yang tidak mengandung PPN pakai Telasih Makmur, baik
> itu surat jalan, Invoice, ataupun Purchase Order." — **jadi Surat
> Jalan/Invoice/PO BUKAN otomatis exception tetap** (koreksi audit awal
> sesi ini yg sempat salah generalisasi "Surat Jalan = selalu Langgeng").
> Aturannya murni **per-dokumen, berdasar ada/tidaknya PPN di dokumen ITU**,
> berlaku SAMA ke semua jenis dokumen, PLUS ada kemampuan **override manual
> oleh user** (belum jelas di level mana — per dokumen? per transaksi?
> perlu diklarifikasi saat eksekusi).

**Konteks — ini MEMBALIK sebagian keputusan lama:** `docs/configurationstandard.md`
baris 151 & 233-236 (keputusan 2026-09-02) bilang kop dokumen **sengaja**
pakai `org_company.company_name` (nama LEGAL, "CV. Langgeng Jaya Makmur"),
sementara "Telasih Makmur" cuma nickname buat konteks eksternal (careers
portal, dst). Keputusan itu **kini disempurnakan**: nama legal cuma dipakai
kalau dokumen itu benar-benar mengandung PPN, sisanya (termasuk dokumen yg
SECARA JENIS biasanya berpotensi PPN tapi instance-nya kebetulan tidak, mis.
PO non-PPN) pakai nickname "Telasih Makmur". `docs/configurationstandard.md`
§233-236 PERLU diperbarui saat item ini dieksekusi (saat ini masih
menyatakan aturan lama tanpa pengecualian — jangan dianggap kontradiksi,
itu memang belum di-update).

**Root cause teknis (header):** HANYA ADA 1 baris di `tm2mst.org_company`
(`company_id=1`, `company_code='TM'`, `company_name='CV. Langgeng Jaya
Makmur'`, dikonfirmasi via tinker 2026-09-11). **Setiap** print blade query
`OrgCompany::where('company_code','TM')` atau `where('activate', true)` —
keduanya selalu balikin baris yang sama itu — lalu oper ke
`<x-print.header :company="...">` yang render `$company->company_name` apa
adanya (`resources/views/components/print/header.blade.php:9`). Makanya
**SEMUA print out saat ini seragam "CV. Langgeng Jaya Makmur"**, termasuk
Master Card (`prodev-print.blade.php`, ini yg discreenshot user) yang
harusnya netral/internal (Master Card = dokumen produksi, tidak pernah
mengandung PPN sama sekali).

**Temuan penting — sinyal "dokumen ini ada PPN atau tidak" TIDAK seragam,
beda kolom/tabel per modul** (audit DDL 2026-09-11):

| Modul/dokumen | Tabel & kolom sinyal PPN | Catatan |
|---|---|---|
| Inquiry | `iqp_inquiry.tax_scheme` enum `PPN`\|`NON_PPN` (`tm2sdi.sql:487`) | Flag eksplisit di header inquiry |
| Costing | `iqp_costing.ppn_mode` (`tm2sdi.sql:200`, snapshot dari inquiry) | Ikut inquiry asalnya |
| Quotation | `quo_quotation.tax_scheme` (`tm2sdi.sql:875`) | **HATI-HATI beda arti**: ini `EXCL`\|`INCL` (harga blm/sdh termasuk PPN dlm angka yg ditampilkan), **BUKAN** flag "ada PPN atau tidak" — jangan tertukar dgn `tax_scheme` di `iqp_inquiry` yg maknanya beda walau nama kolom sama |
| Customer Order (`co_order`) | **TIDAK PUNYA kolom PPN sendiri** | Kemungkinan warisan dari Quotation/Inquiry asal (`quotation_id` FK) — perlu ditelusuri rantainya saat eksekusi, jangan asumsi |
| Delivery Order/Surat Jalan (`co_order_delivery`) | **TIDAK PUNYA kolom PPN sendiri** | Sama, kemungkinan warisan dari CO/Quotation — **ini yg bikin generalisasi awal "Surat Jalan = selalu PPN" SALAH**, PPN Surat Jalan ikut status dokumen sumbernya, bukan otomatis selalu ada |
| Purchase Order | `pur_po.c_ppn` decimal (`tm2pur.sql:136`) | Nilai nominal PPN hasil hitung — `>0` kemungkinan sinyal "ada PPN", tapi ini ANGKA HASIL bukan flag input eksplisit, perlu cek apakah bisa 0 karena memang non-PPN vs 0 karena belum dihitung |
| Purchase Request/PQ/PRT | **TIDAK ADA kolom PPN di `tm2pur.sql`** | Dokumen ini secara struktur TIDAK PERNAH mengandung PPN — selalu Telasih Makmur, tidak perlu logika kondisional |
| Invoice | `fin_invoice.tax_amount` decimal (`tm2acc.sql:148`) | Tabel ADA tapi masih KOSONG (belum ada print/data nyata) — `>0` kemungkinan sinyal, sama catatan spt PO |
| Payment Voucher | `fin_voucher.ppn_type` enum `NONE`\|`MASUKAN_11`\|`KELUARAN_11` (`tm2acc.sql:308`) | Flag eksplisit paling jelas dari semua tabel yg ditemukan |
| Customer master | `cust_customer.tax_profile` enum `PPN_11`\|`EXEMPT` (`tm2mst.sql:107`) | Kemungkinan ROOT signal default per customer — banyak dokumen transaksi (Inquiry/Quotation/CO) kemungkinan mewarisi dari sini saat dibuat, perlu ditelusuri |
| WO, Master Card (ProDev), Slip Gaji | Tidak ada & tidak relevan | Dokumen produksi/internal/HR — PPN secara konsep tidak berlaku, selalu Telasih Makmur, tidak perlu logika kondisional |

**Kesimpulan dari temuan di atas — ini BUKAN pekerjaan kecil "ganti 1
kondisi di beberapa file", tapi butuh keputusan desain dulu:**
1. **Sumber kebenaran PPN per dokumen** — apakah tiap print controller cek
   kolom lokalnya sendiri (`iqp_inquiry.tax_scheme`, `pur_po.c_ppn`, dst),
   atau ditelusuri naik ke induk kalau dokumen itu sendiri tidak punya
   kolom PPN (CO/DO/Surat Jalan → ikut Quotation/Inquiry asal)? Perlu
   dipetakan 1-1 per jenis dokumen, tidak bisa 1 rule generik krn nama
   kolom & makna beda-beda (lihat catatan `tax_scheme` Quotation vs
   Inquiry di tabel atas — JANGAN disamakan).
2. **Mekanisme "override oleh user"** — belum ada kolom/UI utk ini di
   mana pun. Perlu diputuskan: override per dokumen (kolom baru tiap
   tabel transaksi, mis. `company_name_override`) atau 1 toggle
   global per print (tombol di layar cetak, tidak persisten)? Field baru
   di tiap tabel transaksi = kerja migration lumayan banyak (Inquiry, CO,
   DO, PO, Invoice minimal).
3. **Rekomendasi arah (belum diputuskan, utk didiskusikan saat eksekusi)**:
   bikin 1 helper terpusat (mis. `App\Support\CompanyResolver::forDocument($doc)`
   atau method di tiap model transaksi `hasPpn(): bool`) yang jadi SATU
   tempat logika "dokumen ini PPN atau tidak", dipanggil oleh 11
   `company()` method print controller — supaya kalau sumber sinyal PPN
   berubah/ditemukan lebih akurat, cukup ubah 1 tempat. `org_company`
   sendiri kemungkinan perlu kolom kedua (nickname "Telasih Makmur" di
   samping `company_name` legal) supaya 1 baris tetap cukup, komponen
   `<x-print.header>` tinggal terima hasil resolve (bukan `$company`
   mentah) atau prop bool `:has-ppn`.

**Invoice — BELUM ADA print template, tabel `fin_invoice` juga masih
kosong.** Dicatat di sini supaya begitu Invoice print dibangun, langsung
pakai logika `tax_amount > 0 → CV Langgeng Jaya Makmur` dari awal, bukan
hardcode salah satu nama.

**Setelah dieksekusi:** update juga `docs/configurationstandard.md` §233-236
(saat ini menyatakan kop SELALU pakai nama legal tanpa pengecualian — akan
jadi salah/usang begitu perbaikan ini jalan).

---

## 20. PAYROLL — TARIF LEMBUR ENGINE ≠ FILE GAJI HR (temuan 2026-09-19)

Sample payroll dari file gaji mingguan (`hrm:import-hr-sample --step=payroll`) menunjukkan
lembur engine cuma ~52% dari Excel HR. HR memakai tarif **"LEMBUR PER JAM" per karyawan**
(sheet PROPOSAL, mis. Rp19.000/jam), sedangkan `PayrollEngineService::overtimePay()`
menurunkannya dari gaji pokok harian ÷ durasi shift × multiplier. Belum ada kolom tarif
lembur per karyawan di `pyr_employee_salary`. Selisih kecil lain: prorata telat
(≤ Rp2.350/minggu) dan 3 kasus izin/alpha (±Rp35-50 rb). Perbandingan per orang:
`storage/app/private/hr/payroll_compare_*.csv`. Perlu keputusan user sebelum mengubah engine.

Juga menunggu HR: jabatan 9 staf (RONY, DENI IRA, APRILIANTI, M. RAFLY, WIDITA, INDIRA,
DIDIN, SHOFIA, TEGAR), tgl lahir SHOFIA AZKIA (Excel 2026-04-10), 8 nama panggilan formasi
(ARIF x2, MUCHLAS, RENDA, ERNA, MUJI, DEA, SITI, TYAS).

---

## 21. INPUT TEKS & KOMPONEN FORM — SISA ROLLOUT (instruksi user 2026-09-21)

**Aturan tetapnya sudah dikunci** di `CLAUDE.md` §3 + `FORM_UI_STANDARD.md` §1b
(+ skill `tmerp-screen` & `tmerp-ui-mockup`): input teks string = `x-form.input`/
`x-form.textarea` dgn badge huruf aa/Aa/AA, lookup dulu kalau nilainya ada di
master, warna lewat token tema. **KOREKSI:** catatan di section ini sebelumnya
(pagi 2026-09-21) menulis case-toggle "sengaja tidak di-rollout" — itu keputusan
sementara yang DIBALIK user di hari yang sama: alasannya user banyak input lewat
HP/tablet, menekan Shift tiap kata merepotkan. Yang berlaku = versi ini.

### 21.1 Lookup — field teks bebas yang nilainya sudah ada di master
Audit 2026-09-21 atas seluruh `x-form.input`: mayoritas field ber-nama-master
ternyata SAH teks bebas (itu nama/kode master yang sedang dibuat di layar itu).
Yang benar-benar perlu diubah jadi lookup tinggal sedikit:
- `production/rule-library` → `applies_to_process` (teks bebas, harusnya pilih
  dari master proses `prod_process`; sebelahnya `applies_to_machine_id` sudah
  dropdown, jadi tinggal samakan polanya).
- `production/process-rate` → `part_code` (teks bebas "mis. BODY"; kandidat
  sumber: `part_code` yang sudah dipakai `prod_*`/`iqp_part` — perlu dicek
  dulu masternya yang mana, JANGAN asumsi).
- `production/bop-rule` → `material_pattern` + `material_role` (teks bebas
  + di-`strtoupper` manual saat simpan; kandidat: master material/role).
- `hrm/payroll-employee-bank` → `bankName` (teks bebas; belum ada master bank
  di tm2mst — butuh keputusan: bikin master bank kecil atau `ref_` lookup).
- Sisanya (`customer_name`, `product_code`, `uom_name`, dst) **BUKAN** item
  terbuka: itu form master-nya sendiri.

### 21.2 Layar yang inputnya masih HTML mentah (belum lewat komponen)
- **Inquiry Form / Revise / Hub** — dialek CSS lama `.f`/`.srch`/`.filters`,
  ~14 input. Tampilan sudah sama (CSS global yang mendandani) TAPI tidak
  mendapat badge huruf, jadi sekarang jadi item nyata, bukan sekadar kerapian.
  Hati-hati: `x-form.input :legacy` mengeluarkan wrapper `.f` sendiri — kalau
  input aslinya sudah di dalam `<div class="f ac-wrap">` (autocomplete
  Customer) akan jadi `.f` bersarang. Manual per field, bukan skrip massal.
- **Input di dalam sel tabel** (~25, mis. `mps-spreadsheet`, grid editable) —
  komponen berlabel tidak cocok. Perlu komponen sel tersendiri
  (`x-form.cell-input`) yang membawa badge huruf versi ringkas. Belum
  diputuskan bentuknya — jangan bangun tanpa persetujuan.
- **Kotak cari toolbar grid** (~55 layar) — sengaja tetap mentah (standar grid
  sendiri, `griduistandard.md` §6) dan memang TIDAK perlu badge huruf.
- **Sengaja beda / tidak aktif**: Login & Register, Portal Karier, kotak chat
  & AI (`shell/user-panels`), field COA "MENYUSUL" di Master Customer, layar
  legacy `production/old_prodev*` dan `inquiry/imposition*`.

### 21.3 Warna: sisa hex literal di luar komponen form
Token `--color-field-*` + `--color-accent-soft` sudah dipakai SEMUA komponen
`resources/views/components/form/*`. Yang belum: **hex literal di file layar**
(ratusan titik, mis. `border-[#c7cfda]` di modal/kartu/tabel per layar) dan
komponen non-form (`grid/`, `print/`, `report/`). Efeknya: corak tema baru
kelihatan di field, belum di seluruh kerangka layar. Ini lanjutan langsung dari
`themestandard.md` §6 ("halaman iframe belum ikut sistem tema") — kerjakan
per-modul, bukan sekali sapu, dan verifikasi tiap layar setelahnya.

### 21.4 Mode gelap untuk layar transaksi
Token field saat ini dihitung untuk latar TERANG (dicampur ke abu-abu terang).
Di tema Dark/Hitam, sidebar & shell ikut gelap tapi isi layar transaksi masih
putih — field akan tetap terbaca, tapi belum benar-benar "dark mode". Ini
scope terpisah yang sudah lama tercatat (`themestandard.md` §6), jangan
dianggap regresi dari pekerjaan 2026-09-21.
## 22. AGENDA GRID — 4 KETENTUAN BARU (instruksi user 2026-09-21 malam)

Semua berlaku untuk SELURUH grid (pola sama), bukan per layar. Referensi visual
dari user: aplikasi grid pihak ketiga (tangkapan layar di sesi 2026-09-21).

### 22.1 Kotak statistik jadi FILTER DATA — [TERJADWAL 22:30, 2026-09-21]
4 kotak di atas grid (mis. EMPLOYEE AKTIF / NON AKTIF / MINGGUAN / BULANAN)
harus bisa diklik untuk menyaring isi grid; klik lagi = lepas. Angka di kotak
tetap menghitung keseluruhan, bukan hasil saringan. Detail lengkap ada di
prompt tugas terjadwal `grid-statbox-jadi-filter`.

### 22.2 Ikon kolom Aksi — PILIHAN C (diputuskan user 2026-09-21)
Ganti karakter Unicode `✎`/`⏻` (tipis, beda-beda antar perangkat, tidak sebaris
dgn Font Awesome tombol Back) menjadi **ikon berbingkai kotak**:
- Edit = `fa-pen` dalam kotak bergaris.
- Nonaktifkan = `fa-ban` (merah) · Aktifkan = `fa-circle-check` (hijau).
- Bingkai = varian `bordered` yang SUDAH ada di `x-grid.action-icon` tapi selama
  ini cuma dipakai 2 layar pilot (`co/list`, `raw-material/index`) — sekarang
  jadi standar, jadi `griduistandard.md` §5 & §10 WAJIB diperbarui (§5 masih
  menyatakan default tanpa border).
- Mockup pembanding: `docs/mockups/pilihan_ikon_aksi_grid_konteks.jpg`.

### 22.3 Kerapatan baris + Baris per sel (mockup disetujui konsepnya)
Panel dari toolbar grid, **dua setelan yang berdiri sendiri**:
- **Kerapatan**: Rapat / Normal / Longgar → mengubah padding sel saja.
- **Baris per sel**: Otomatis (teks membungkus penuh, tinggi baris ikut isi) atau
  Tetap N (dipotong `line-clamp` + titik tiga, semua baris sama tinggi). Saat
  Otomatis, kotak angka N dikelabukan.
- Plus tautan "Kembalikan ke bawaan".
- Mockup: `docs/mockups/grid_kerapatan_baris.jpg` (6 kombinasi).
- **Gotcha implementasi**: `-webkit-line-clamp` TIDAK bisa dipasang langsung di
  `<td>` (merusak layout sel) — bungkus isi sel dalam `<div>`.
- **2 keputusan user yang belum dijawab** (kalau tak dijawab, pakai saran ini):
  (a) satu setelan berlaku semua grid, boleh ditimpa per grid; (b) disimpan di
  PERANGKAT (browser), bukan ikut user seperti `sys_grid_preference` — karena
  kerapatan bergantung layar yang sedang dipakai, bukan siapa yang memakai.

### 22.4 Navigasi keyboard di grid (instruksi user 2026-09-21)
Sel grid bisa difokus & dijelajahi dengan keyboard, plus panel bantuan pintasan
(ikon keyboard di toolbar). Daftar dari contoh user:
`↑`/`↓`/`←`/`→` pindah 1 sel · `Home`/`End` sel pertama/terakhir baris ·
`Ctrl+Home`/`Ctrl+End` sel pertama/terakhir halaman · `PageUp` baris terakhir
halaman sebelumnya · `PageDown` baris pertama halaman berikutnya ·
`Enter` buka detail/aksi sel · `Esc` tutup · `Ctrl+C` salin nilai sel terfokus.
- Perlu diputuskan: apakah `Enter` membuka baris (pola `dblclick` yang sudah ada
  di `griduistandard.md`) atau membuka menu aksi sel seperti contoh.
- Catatan: ini yang paling besar dari empat item — sentuh semua grid, perlu
  fokus DOM + status sel aktif, dan harus tidak bentrok dgn pintasan F1 (Help)
  serta hotkey shell yang sudah ada.
## HISTORY PENGERJAAN

Dipindah ke **[`docs/LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** (file terpisah,
2026-09-02) supaya file ini tetap ramping. Item selesai dihapus dari
section angka di atas dan dicatat lengkap di sana, BUKAN di sini.
