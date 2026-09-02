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

## 2026-09-02

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
