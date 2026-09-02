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
