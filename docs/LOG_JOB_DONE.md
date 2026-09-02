# LOG JOB DONE — RIWAYAT ITEM YANG SUDAH DIKERJAKAN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dipisah dari `docs/BACKLOG.md` 2026-09-02 (permintaan user: "pindahkan ke
> file LOG Job Done md file, untuk yang sudah sehingga file backlog md
> mengecil") — supaya `BACKLOG.md` tetap ramping, cuma isi item yang MASIH
> terbuka. File ini murni arsip/riwayat, urutan dari **terbaru di atas**.
>
> **Cara pakai**: setiap kali 1 item di `BACKLOG.md` selesai dikerjakan,
> HAPUS dari `BACKLOG.md` dan catat lengkap di sini (bukan disalin ke
> dua-duanya).

---

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #3)

- **§7 — Outstanding PV report dibangun.** Prasyarat sudah terpenuhi
  (Stock Out live sejak 2026-08-22) tapi 1 keputusan desain masih terbuka
  (3 opsi tracking serah-terima gudang->departemen peminta) — diputuskan
  OTONOM (low-risk/reversibel, murni pilihan teknis bukan kebijakan bisnis):
  opsi PALING SEDERHANA, flag header-level `pv_receipt.is_handed_over`
  (+`handed_over_at`/`handed_over_by`), konsisten gaya Outstanding PR/PO
  existing yang juga header-level (bukan opsi per-line `c_qty_received` atau
  dokumen baru "Bukti Serah Terima Barang" — keduanya lebih rumit tanpa
  kebutuhan granularitas jelas). Layar baru `pur/report/outstanding-pv`
  (route `pur.report.outstanding-pv`): PV POSTED yg belum diserahkan, badge
  merah kalau menunggu >3 hari, tombol "✓ Sudah Diserahkan". Menu
  `PROC_MON_PV` (placeholder sejak 2026-08-21) di-flip READY_FOR_QC.
  Diverifikasi `Livewire::test()` (toggle + restore state PV dummy existing
  ke nilai asli, bukan tebakan) + HTTP kernel 200. `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #2)

- **§3/§1 — Win-Rate Sales dibangun.** Temuan: `iqp_inquiry.sub_state` SUDAH
  punya value `CONVERTED`/`LOST` di skema sejak awal (enum-teks, dipakai
  badge/filter di banyak layar) — TAPI 0 titik kode yang pernah menuliskan
  kedua value itu (grep dipastikan dulu sebelum tambah kolom baru, sesuai
  CLAUDE.md §7). 2 titik tulis baru: `CoOrderBuilder::findOrCreateFromQuotation()`
  set `sub_state=CONVERTED` bersamaan `co_id` (saat CO pertama lahir dari
  Quotation = deal resmi menang), `inquiry/form.blade.php::markLost()` (tombol
  "❌ Tandai Kalah" baru, mirror pola `cancel()` existing, reuse kolom
  `cancel_reason` utk alasan kalah — beda dari `cancel()`: LOST boleh dari
  sub_state manapun sepanjang belum terminal, bukan cuma dari state tertentu).
  Layar baru `sales/win-rate` (route `sales.win-rate`, menu `SALES_WINRATE` di
  grup Laporan Sales): stat card won/lost/win-rate%, breakdown per PIC Sales,
  detail deal kalah + alasan. Diverifikasi `Livewire::test()` end-to-end
  (buat inquiry->confirm->markLost->cek report) + HTTP kernel 200.
  `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2)

- **§10 — `class="grid" grid-cols-N` literal DIPERBAIKI** (audit 2026-09-02
  pagi selesai, sesi ini eksekusi perbaikannya). 6 file/9 lokasi: grid-cols-2
  → dialek `.grid.two` existing (`production/scheduling-board.blade.php:865`,
  `rule-library.blade.php:230,245`, `mts-forecast.blade.php:238`), grid-cols-3
  → `.grid.three` (`recruitment/interview-schedule.blade.php:315,336`),
  grid-cols-7 → `[display:grid]` (tak ada dialek `.seven`, dipakai
  `components/form/date-picker.blade.php:87,92` — dampak terluas krn dipakai
  lintas modul — + `interview-schedule.blade.php:320,342,348`),
  `careers/landing.blade.php:38` (`md:grid-cols-2` responsive, dicek pakai
  `app.css` yg sama → `[display:grid]` supaya breakpoint md: tetap jalan,
  BUKAN `.two` flat yg akan hilangkan behaviour 1-kol mobile). `npm run build`
  dijalankan, `php -l` semua file lolos. Belum verifikasi visual browser
  langsung (butuh login) — pola fix identik yg sudah terbukti jalan di file
  lain (`imposition.blade.php` dst), risiko rendah.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`)

- **ProDev Fase 1 TUNTAS** (`docs/inquiry_product_costing/productdevelopment_tier1.md`
  jadi spesifikasi resminya, sudah dikunci sesi sebelumnya). Rangkaian 6 commit
  (`98abe9a`..`031c5e4`), semua diverifikasi HTTP kernel 200 + `Livewire::test()`
  end-to-end (bukan asumsi):
  1. **Skema DB** — ALTER `prod_part` (14 kolom teknis baru: mastercard_code,
     die_code/die_w_cm/die_h_cm, sheet_raw_w_cm/h_cm, color_set_1/2 JSON, 6 kolom
     flap) + 5 tabel baru `prod_layout_group`/`prod_layout_group_part`/
     `prod_part_attachment`/`prod_part_finishing`/`ref_finishing_type`(8 seed)/
     `mat_flap_rule`(1 placeholder). **Temuan penting**: `prod_part_process`
     TERNYATA sudah ada dari DDL awal TIER_3A (bukan migration Laravel) dgn
     skema lebih lengkap dari rencana — dipakai apa adanya (bukan duplikasi),
     cuma ditambah FK `machine_id` yg tadinya logis. `printsize_p/l`,
     `joint_type`, `pack_qty` di `prod_part` juga direuse (bukan kolom baru).
  2. **Model Eloquent**: `ProdPartProcess`/`ProdLayoutGroup`/`ProdPartAttachment`/
     `ProdPartFinishing`/`RefFinishingType`/`MatFlapRule`/`ProdMastercardSignoff`
     baru + `ProdPart::$fillable` diperluas besar (banyak kolom lama existing
     ternyata tak pernah masuk fillable, jadi tak pernah bisa diisi lewat layar
     apapun sebelumnya).
  3. **ENGINE Tingkat 1** (`ImpositionEngine::sweepFlap()`) — parametric flap
     sweep, reuse `run()` existing (3 orientasi TEGAK/PUTAR/CAMPUR), ranking
     top-5 by UP lalu efisiensi.
  4. **Layar `production/prodev`** (route `production.prodev`) — workbench
     pilih part + edit spesifikasi teknis + jalankan engine + preview visual
     SVG-style top-5 kandidat + pilih 1 (Engine suggests, Officer confirms) →
     tersimpan `prod_layout_group` + routing (`prod_part_process`) + finishing
     (`prod_part_finishing`) inline + upload referensi JPEG/PDF/AI
     (`WithFileUploads`, batas `PRODEV_UPLOAD_MAX_MB`). Bug ditemukan+diperbaiki:
     `withTimestamps(false)` di relasi many-to-many `prod_layout_group_part`
     ternyata TETAP menyalakan kolom timestamp (bukan false-kan) — pivot tak
     punya `updated_at`, query gagal; fix: hapus `withTimestamps()` total.
  5. **Cetak Master Card** (`production.prodev.print`) — komponen
     `resources/views/components/print/*`, 5 kolom tanda tangan dari
     `prod_mastercard_signoff` (model baru). Menu `PROD_CARD`/`PROD_LAYOUT`
     (`sys_app_menu`) diaktifkan SETELAH verifikasi lolos (keduanya arah ke
     layar workbench yang sama).
  6. **Costing 4 tipe part baru** (`docs/inquiry_product_costing/gemini_spark_productcosting.md`
     §IV jadi acuan formula hasil audit `calc_cogs` V1 asli):
     SHEET (identik SHEET_DIGITAL, dialiaskan bukan diduplikasi — diekstrak
     `computeSheetGeometry()` reusable), KRAFT (literal §IV.2.B, area/10000
     TANPA pembagi rim 500), DC+SHEET/DC+SF (Paper+Cetak+J36 Laminasi+J37 SF EF
     +Plong+Tooling — DITANDAI "BELUM DIVERIFIKASI Excel" di `description_calc`,
     dokumen sumber sendiri mencatat rumus RM tak konsisten di 3 titik V1).
     **Side-fix**: jalur ivory/AP di `calculateDcPart()` (tipe DC, sudah lama
     live) ternyata salah pakai `PLANO_REF_AREA`(8611, harusnya cuma duplex) —
     dikoreksi ke /10000 sesuai §IV.2.A, ditemukan saat cross-check formula
     KRAFT yg share paper-cost logic. Test lama yg assert "SHEET belum
     diimplementasi" direvisi pakai kode BOGUS (semua 3 test lolos, termasuk
     regresi byte-presisi Excel SHEET_DIGITAL — refactor tidak mengganggu).
  7. **Toggle Manual/Ambil-Data per-part** di `inquiry/costing` — pilih Master
     Card/layout CONFIRMED dari dropdown → plano/cut auto-fill dari
     `prod_layout_group` + HPP LANGSUNG terhitung (tanpa klik "Hitung" terpisah,
     tujuan eksplisit: 1 officer bukan 2 spt sebelumnya). Field terkait
     di-disable saat mode PRODEV.
  8. **ENGINE Tingkat 2** (`ImpositionEngine::sweepInterlock()`) — perluasan
     Tingkat 1 dgn variabel `shift_offset_mm` (lever utama, sesuai observasi
     user "kebanyakan kasus geser bukan kecilkan lidah") + `flap_length`
     (sekunder). Model pitch efektif = body+flap-shift, DICATAT JUJUR di
     docblock sbg interpretasi (dokumen sumber §4.3a level pseudocode/observasi
     lapangan, bukan spek presisi — user akan trial sampai cocok data riil).

  **Migration**: `2026_09_02_000021` s/d `2026_09_02_000031` (11 file — skema
  ProDev, kolom Costing baru, aktivasi menu, `is_engine_ready` 4 part_type).
  **Belum dikerjakan** (dicatat `docs/BACKLOG.md`, follow-up bukan blocker
  Fase 1): Tool #10 Intra-Product Combiner, Tool #2 Pcs→Plano, Tool #4
  Die-Rule Estimator, alokasi biaya proporsional multi-part layout group.

## 2026-09-02

- **Recruitment L8 (Rekap Sync Log) dibangun.** Menu placeholder
  `HRM_RCR_SYNCLOG` sudah ada sejak 2026-08-29 (status NOT_BUILT, sengaja
  dilewati) — data `tm2hrr.rcr_sync_log` **memang sudah terisi** (3 baris
  nyata dari `PortalSyncService`, jadwal tiap 2 menit), jadi tinggal
  dibuatkan grid, bukan bangun dari nol. Layar baru
  `resources/views/pages/recruitment/sync-log.blade.php`, murni **read-only**
  (kandidat, lowongan, backend FIREBASE/MARIADB, hasil OK/ERROR, waktu,
  catatan error) — SENGAJA tidak ada Aksi buka/edit, karena layar Offer yang
  jadi kandidat tujuan link `mount()`-nya bikin baris `AppOffer` baru kalau
  belum ada (efek samping tak diinginkan cuma dari klik baris log). Route
  `hrm.recruitment.sync-log`, menu status diflip ke READY_FOR_QC (migration
  `2026_09_02_000018`). Diverifikasi via HTTP kernel (bukan browser login) —
  200, render kandidat+backend dgn benar.
- **§4/§7 — koreksi catatan "13 kategori COA kosong belum ada layar".**
  Ternyata **layarnya SUDAH ADA** (`master/mat-category.blade.php`, section
  "Default Akuntansi" utk kategori Level 1, dibangun 2026-08-26 bersamaan
  redesign `mat_category`) — catatan lama di `BACKLOG.md` salah, sempat
  dikira belum dibangun. Dicek ulang ke DB: 18 kategori Level-1 total, 12
  masih kosong `default_coa_account_id`/`default_stock_asset_account_id`
  (akun GR/IR universal sudah terisi semua di 813). BACKLOG.md dikoreksi
  supaya tidak salah lagi — item pengisian akun tetap TERBUKA (keputusan
  bisnis user, bukan kode), cuma "perlu bangun layar"-nya yang ditutup.
- **§10 — Audit `class="grid"` tech debt SELESAI** (murni audit, TIDAK
  diperbaiki — sesuai scope item ini). Ditemukan lewat grep sistematis:
  beberapa file (`master/customer`, `employee`, `supplier`,
  `production/wo-entry`, `master/raw-material/entry`, `pur/pr/entry`) sudah
  pakai workaround `[display:grid]` (AMAN). File yang MASIH pakai
  `class="grid grid-cols-N"` mentah (N≠4, kena override diam-diam ke
  4-kolom oleh `.grid{grid-cols-4}` unlayered di `app.css`): daftar lengkap
  8 file/13 lokasi di `BACKLOG.md` §10 — termasuk `components/form/date-picker.blade.php`
  (dampak terluas, dipakai lintas modul) dan kalender Interview Recruitment.
  Belum diverifikasi visual di browser, keputusan perbaiki-atau-tidak
  menyusul terpisah.
- **§12.5 — `sys_holiday` dibangun** (tabel hari libur nasional, sebelumnya
  tidak ada sama sekali). Migration `2026_09_02_000015` bikin tabel
  `tm2mst.sys_holiday` (id_holiday, holiday_date unique, holiday_name,
  holiday_type enum NATIONAL/COMPANY, activate, created_by/updated_by) +
  seed 5 hari libur tanggal-tetap 2026 (1 Jan, 1 Mei, 1 Jun, 17 Agu, 25 Des
  — SENGAJA tidak isi hari libur kalender lunar/hasil sidang isbat, itu
  perlu update manual tiap tahun). Layar CRUD baru "Master Hari Libur"
  (`resources/views/pages/master/holiday.blade.php`, pola spreadsheet-
  editable sama seperti Master Currency/Kurs) + `App\Models\Master\SysHoliday`
  (ada helper `isHoliday(string $date): bool` utk dipakai modul Attendance
  nanti) + route `master.holiday` + menu `MD_HOLIDAY` (migration
  `2026_09_02_000016`). **Catatan**: ini baru tabel+CRUD-nya saja — aturan
  lembur H-7/H-1 yang MEMAKAI tabel ini di modul Attendance belum digarap
  (Payroll/Attendance masih belum dibangun, lihat `project_payroll_encryption_requirement.md`).
- **Label grup Sales diseragamkan**: `SALES_MON` (`sys_app_menu`) dari
  "Monitoring & Laporan" → **"Laporan"** polos, konsisten dengan modul lain
  yang sudah diseragamkan sejak 2026-08-22 (`docs/sidebarmenustandard.md`
  §1). Migration `2026_09_02_000017`.
- **QUEUE item #9 (2026-08-20, theme picker via `sys_user_menu`) — diverifikasi
  ulang, TERNYATA CUMA SETENGAH SELESAI.** Fitur pilih-tema yang terlihat di
  UI sekarang jalan lewat mekanisme LAIN (`sys_theme` + dropdown avatar,
  lihat `docs/themestandard.md`) — bukan lewat `sys_user_menu`. Tabel
  `sys_user_menu` sendiri **masih ada & terisi 10 baris** di `tm2sys` tapi
  **grep `main-menu.blade.php` = 0 referensi**, jadi genuinely orphaned:
  data tersimpan tapi tak pernah dibaca aplikasi. Item ini TETAP TERBUKA di
  `BACKLOG.md` §10 (bukan ditutup) — bukan kandidat "mudah" lagi karena
  perlu keputusan dulu: dipakai betulan (refactor dropdown menu jadi
  data-driven dari tabel ini) atau dihapus sebagai dead table.
- **§2 poin 1 — ~97 judul tab browser jadi dinamis.** Solusi:
  bukan edit 97 file `#[Title('X — TELASIH MAKMUR ERP')]` satu-satu (yang
  tidak mungkin krn PHP attribute wajib constant expression) — literal
  "TELASIH MAKMUR"/"TELASIH MAKMUR ERP" di dalam string attribute yang
  SUDAH ADA dijadikan placeholder, di-cari-ganti SEKALI di titik render
  `<title>` (`layouts::shell`/`guest`/`app`, `str_replace([...], [Brand::name(),...], $title)`).
  0 dari 97 file individual disentuh. `main-menu.blade.php` sedikit beda
  pola (`'TELASIH MAKMUR — ERP'`, bukan `'X — TELASIH MAKMUR ERP'`) —
  diseragamkan ke `'Home — TELASIH MAKMUR ERP'` supaya konsisten. Detail:
  `docs/configurationstandard.md` §5.
- **Koreksi nama company**: `org_company.company_name` dulu
  "CV Telasih Makmur Group" (salah) → **"CV. Langgeng Jaya Makmur"** (nama
  legal terdaftar sebenarnya). "Telasih Makmur" tetap dipakai sebagai
  NICKNAME company di permukaan customer-facing (portal karir, dst) —
  TIDAK dihapus, cuma diklarifikasi bukan nama legal. APP_NAME ("ERP TM",
  identitas aplikasi internal) tidak terpengaruh, konsep terpisah.
- **§2 — PDF footer dinamis**: `exports/generic-grid-pdf.blade.php`
  sekarang baca `Brand::name()`.
- **§2 — Master Kurs (`fin_exchange_rate`) dapat layar CRUD**:
  `resources/views/pages/master/exchange-rate.blade.php`, pola spreadsheet-
  editable sama spt Master Currency, versioned (1 baris `activate=1` per
  currency, baris lama otomatis nonaktif saat baris baru diaktifkan).
  **Bonus temuan**: menu "Master Currency" (`MD_CURRENCY`) ternyata
  **link mati sejak dibuat 2026-08-30** (tidak pernah dipetakan ke route di
  `main-menu.blade.php`) — diperbaiki bersamaan.
- **§2 — `geo_country.flag_emoji` terisi semua 250 baris**:
  DIHITUNG, bukan ditebak — `iso_code` (ISO 3166-1 alpha-3, sudah ada)
  dipetakan ke alpha-2 lewat tabel referensi ISO baku, lalu emoji dihitung
  via rumus Unicode Regional Indicator Symbol (deterministik, 0 karakter
  emoji diketik manual). 0 baris NULL tersisa.
- **§2 — BUG ARSITEKTURAL Filament ditemukan & diperbaiki (temuan
  terbesar hari ini)**: saat verifikasi lebih dalam lapis kosmetik
  tema (5 mode x 4 halaman, bukan cuma 1 seperti sebelumnya), ketahuan
  personalisasi aksen/mode dashboard **TIDAK PERNAH benar-benar jalan**
  sejak dibangun kemarin — `AdminPanelProvider::panel()` cuma dieksekusi
  SEKALI saat boot (`Filament::registerPanel()` via `app()->resolving()`),
  JAUH SEBELUM auth resolve utk request itu, jadi `Auth::user()` di dalam
  `panel()` SELALU null. Semua user sebenarnya melihat aksen/mode DEFAULT
  yang sama, walau preferensi tersimpan benar di DB & form Save tidak
  error — nyaris lolos tak ketahuan kalau cuma dites 1 mode. **Diperbaiki**:
  personalisasi dipindah total ke render hook (`filament.partials.theme-matrix.blade.php`,
  HEAD_END) yang TERBUKTI jalan per-request — aksen via `Color::hex()`
  dipanggil ulang di situ (override CSS var `--primary-*` via cascade
  urutan render), dark/light via script inline set `localStorage`+toggle
  class `.dark` langsung. Diverifikasi ulang 20 kombinasi (5 mode x 4
  halaman), 0 gagal. **Pelajaran dicatat** di `admindashboardstandard.md`
  §10.2 utk Resource/Page Filament lain: jangan pernah panggil
  `Auth::user()`/logic per-user di dalam `PanelProvider::panel()`.
