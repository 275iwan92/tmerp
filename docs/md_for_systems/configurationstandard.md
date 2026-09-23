# STANDAR — CONFIGURATION (KONSTANTA SISTEM, BRANDING, BAHASA)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-09-02 (keputusan user). **Dipecah 2026-09-07**: dulu 1 layar
> gado-gado (`setup/configuration.blade.php`, route `/setup/configuration`)
> menumpuk 3 bagian di 1 halaman scroll — sekarang jadi grup sidebar
> **"Settings"** (mirror pola grup "Security" tepat di bawahnya) dengan 4
> submenu/route/layar sendiri-sendiri, semua di **shell LAMA**, dipasak
> sidebar kiri bawah TEPAT DI ATAS grup "Security", `is_admin` only —
> **BUKAN** bagian panel Filament `/admin` (jangan bingung dengan
> `admindashboardstandard.md`, itu dokumen lain untuk topik lain: chrome
> navigasi Filament + tema warna dashboard personal). Filament **membaca**
> branding dari sini (lihat §5), tapi **mengedit**-nya tetap di shell lama.
>
> | Submenu sidebar | Kode menu | Route | File |
> |---|---|---|---|
> | Identifikasi | `SET_CONFIG` | `setup.identification` | `setup/identification.blade.php` |
> | Language | `SET_LANG` | `setup.language` | `setup/language.blade.php` |
> | Localization | `SET_LOCALIZATION` | `setup.localization` | `setup/localization.blade.php` |
> | Constants | `SET_CONSTANTS` | `setup.constants` | `setup/constants.blade.php` |

---

## 1. KAPAN PAKAI INI

Baca ini sebelum: menambah konstanta sistem baru (`sys_constant`), menambah
kategori tampilan baru, menambah bahasa baru, atau mengubah identitas
aplikasi (nama/logo/font).

---

## 2. STRUKTUR TABEL

### 2.1 `tm2sys.sys_constant` (pindah dari tm2mst 2026-09-02)

Key-value generik. **Pindah dari `tm2mst.sys_constant`** — lokasi lama cuma
kebetulan historis (tabel ini konfigurasi sistem, bukan master data bisnis).
Tabel lama di tm2mst di-**rename** jadi `sys_constant_migrated_20260902`
(bukan dihapus, jalan mundur kalau perlu).

| Kolom | Arti |
|---|---|
| `constant_key` | ID unik (`APP_NAME`, `PPN`, `BANK_NAME`, dst) |
| `category_code` | FK ke `sys_constant_category` — tab tampilan di Configuration |
| `constant_group` | Label lama (`TAX`/`PAYMENT`/`COSTING`/`PROCUREMENT`/`INV`/`BRANDING`) — tetap dipakai kode existing (`CostingEngine`, dst), JANGAN dihapus |
| `constant_number` | Nilai angka (PPN=11, dst) |
| `constant_text` | Nilai teks (APP_NAME, BANK_NAME, dst) |
| `activate` | Nonaktifkan tanpa hapus |

Model: `App\Models\Sys\SysConstant` (bukan lagi `App\Models\Master\SysConstant`
— kalau nemu referensi lama di kode, itu bug, perbaiki). Helper statis tetap
sama: `SysConstant::text($key, $default)` / `SysConstant::number($key, $default)`.

### 2.2 `tm2sys.sys_constant_category`

Level tampilan DI ATAS `constant_group` (keputusan user 2026-09-02: "tabular
berdasarkan category") — 1 baris = 1 kategori (`BRANDING`/`FINANCE`/
`PRODUCTION`/`PROCUREMENT`/`INVENTORY`). `code` (PK), `label`, `sort_order`,
`activate`.

**Tambah kategori baru** — INSERT baris baru, langsung muncul sebagai opsi di
kolom Kategori grid "Semua Konstanta Sistem" (dropdown, lihat catatan §3.3
soal keterbatasan tampilan opsinya):

```sql
INSERT INTO tm2sys.sys_constant_category (code, label, sort_order, activate)
VALUES ('HRM', 'HR & Payroll', 60, 1);
```

### 2.3 `tm2sys.sys_languages` + `tm2sys.sys_translations` (SUDAH ADA sebelum sesi ini)

Infrastruktur i18n **sudah ada** (bukan baru dibangun 2026-09-02, ditemukan
saat scoping) — `sys_languages` (code PK, name, is_default, +
`decimal_separator`/`thousand_separator` baru) adalah tabel induk,
`sys_translations` (lang_code FK, trans_key, trans_value, unique per
lang_code+trans_key) isi teksnya. Sudah dipakai `login.blade.php`/
`forgot-password.blade.php` (`SysTranslation::forLang($langCode)`).

**"Add new country language"** (keputusan user 2026-09-02) terwujud dari
infrastruktur yang sudah ada ini, BUKAN sistem baru: tambah 1 baris di
`sys_languages` lewat layar Language (`setup.language`) → kolom baru
otomatis muncul di grid Localization (`setup.localization`, dibangun live
dari `SysLanguage::all()`, bukan daftar hardcode) → isi terjemahan per key
manual.

**Kerangka i18n app-wide shell PASCA-LOGIN (BARU 2026-09-07)** — beda dari
`SysTranslation::forLang()` yang dipakai `login.blade.php`/
`forgot-password.blade.php` (guest, lang dari query string `#[Url]`),
`App\Support\Localization\Translator` adalah titik akses utk teks UI shell
SETELAH login (dropdown avatar, sidebar chrome), lang dari preferensi
TERSIMPAN per user (`sec_user.lang_code`, migration
`2026_09_07_000008_add_lang_code_to_sec_user`) — bukan session/query string.
Dipakai: `Translator::t('key', 'fallback')` (cache per-request per-lang),
`Translator::setLang($code)` (validasi thd `sys_languages`, dipanggil
`main-menu.blade.php::setLang()` lalu `location.reload()`, mirror pola
`setTheme()`/`ActiveTheme`). Picker Bahasa di dropdown profil (dulu 2 tombol
bendera dummy, cuma toast) sekarang benar2 ganti `sec_user.lang_code` +
render ulang shell, dan daftar bahasanya `SysLanguage::all()` live (bukan 2
bendera hardcode) — tambah baris bahasa baru otomatis nongol sbg tombol baru.

**SCOPE SAAT INI — BARU KERANGKA, BELUM PENUH** (keputusan user 2026-09-07,
"buat kerangkanya, masukkan yang perlu-perlu dahulu" — bukan proyek 1 sesi):
baru ~8 key `shell_*` utk string chrome PALING dasar (label Home, Logout,
caption Tema/Bahasa/Klasik/section dropdown — lihat pemakaian `Translator::t()`
di `main-menu.blade.php`). **Ratusan file Blade transaksi/master/laporan
lainnya MASIH 100% hardcode Indonesia**, belum ditarik ke `sys_translations`
sama sekali — switch bahasa TIDAK mengubah tampilan modul Sales/Inventory/dst,
cuma chrome shell. Menambah cakupan = tambah baris `sys_translations` key
baru + ganti string literal Blade layar itu jadi `Translator::t(...)`,
diulang per layar. Terkait [[project_english_menu_policy]] (rencana lama yang
belum dieksekusi) — kerangka ini FONDASI-nya, eksekusi penuh masih rencana
terpisah.

### 2.4 `tm2mst.fin_exchange_rate` (BARU — kebutuhan Finance/Accounting)

`fin_currency` **tidak** pernah punya kolom rate (dicek langsung ke DB
2026-09-02, bukan asumsi). Tabel baru, pola **versioned** sama persis dengan
rate coating/film (CLAUDE.md §4): `currency_id` (FK `fin_currency`),
`rate_to_base` (1 unit currency = sekian IDR), `effective_date`, `activate`
(1 baris aktif per currency = kurs berlaku). Model: `App\Models\Master\FinExchangeRate`,
helper `FinExchangeRate::currentRate($currencyId, $default)`.

**Belum ada UI CRUD** untuk tabel ini di sesi ini (di luar cakupan Fase 1
Configuration) — isi manual lewat DB/tinker sampai ada layar khusus.

### 2.5 `tm2mst.geo_country.flag_emoji` (kolom baru)

`geo_country` **sudah ada** (250 baris) + sudah relasi ke `fin_currency`
(`currency_id`) — **TIDAK** dibikin tabel country baru (hindari duplikasi).
Cuma tambah 1 kolom `flag_emoji` (emoji Unicode, bukan file gambar — 0
asset/storage baru). Belum diisi (semua 250 baris NULL) — isi manual per
kebutuhan, bukan seed massal (tidak ada sumber data emoji-per-negara yang
reliable untuk auto-isi 250 baris).

---

## 3. LAYAR SETTINGS (4 route mandiri)

4 route Livewire berdiri sendiri (bukan lagi 1 parent + komponen anak
`@livewire()` embed) — masing-masing sudah 1 concern per layar sejak
dipecah 2026-09-07, jadi tidak lagi kena batasan "1 grid per komponen"
`HasSpreadsheetGrid` (`spreadsheetgridstandard.md`) yang dulu memaksa
Bahasa+Terjemahan+Constants jadi komponen anak terpisah di 1 halaman.

### 3.1 Identifikasi (dulu "Identitas Aplikasi")

Form biasa (bukan spreadsheet) — APP_NAME, APP_TAGLINE, Logo (upload,
`WithFileUploads`, `storage/app/public/branding/`), Font (`<select>` preset
terbatas, lihat §4). Disimpan sbg baris `sys_constant` (`category_code=BRANDING`).

**APP_NAME BUKAN nama company** (keputusan user 2026-09-02, tegas) — nama
company (dipakai kop dokumen cetak & struktur organisasi HR, mendukung
multi-company) tetap di `tm2mst.org_company`, **tidak disentuh** oleh
Configuration. APP_NAME murni identitas produk/aplikasi (contoh: "ERP TM"),
tampil di topbar shell lama, halaman login, dan brand Filament `/admin`.

**Nama perusahaan vs nama terdaftar (keputusan user 2026-09-23, MENGGANTIKAN
aturan lama "kop dokumen selalu nama legal"):** dua konstanta `sys_constant`
kategori BRANDING, grup COMPANY -- `COMPANY_NAME` = "Telasih Makmur" dan
`COMPANY_LEGAL_NAME` = "CV. Langgeng Jaya Makmur" (dibaca lewat
`Brand::companyName()` / `Brand::companyLegalName()`). **Seluruh aplikasi &
semua cetakan memakai COMPANY_NAME.** Dokumen ber-PPN (saat ini: Delivery
Order, Surat Jalan, Nota Inquiry; menyusul SPB, Invoice Penjualan, Pembayaran
Pembelian) punya pilihan di layar preview cetak **Non PPN (default) / PPN**;
PPN = kop COMPANY_LEGAL_NAME + NPWP (`org_company.npwp_number`) a.n. nama
terdaftar. Pilihan hanya untuk cetak/preview, TIDAK disimpan. Mekanisme:
`x-print.document :ppn` + `x-print.header :ppn` + `x-print.company-name`
(`docs/printnotastandard.md`). Flag PPN dokumen: `co_order.is_ppn` (default
dari Inquiry asal / profil pajak customer, boleh diubah saat CO DRAFT) ->
`do_delivery.is_ppn` ikut CO-nya; 1 DO tidak boleh mencampur CO PPN & Non PPN.
`org_company` tetap sumber alamat/telepon/NPWP -- `company_name`-nya tidak
lagi dipakai untuk tampilan.

### 3.2 Language

Lihat §2.3. Grid spreadsheet-editable (code/name/decimal_separator/
thousand_separator/is_default) — `setup.language`, `sys_languages`.

### 3.3 Localization (dulu bagian bawah "Bahasa")

`setup.localization` — grid custom terjemahan (bukan spreadsheet-editable —
bentuknya beda, 1 baris `trans_key` = N kolom 1 per bahasa, di luar kontrak
`HasSpreadsheetGrid`), `sys_translations`. Kolom bahasa dibangun live dari
`SysLanguage::all()` — tambah baris baru di layar Language → kolom baru
otomatis muncul di sini.

### 3.4 Constants (dulu "Semua Konstanta Sistem")

**SEMUA** baris `sys_constant` dalam **1 grid** (bukan 1 tab/komponen per
kategori — `HasSpreadsheetGrid` cuma 1 grid per komponen, lihat pengantar
§3). "Tabular per kategori" diwujudkan lewat kolom **Kategori** yang
diurutkan (`ORDER BY category_code`), BUKAN tab terpisah.

**Keterbatasan diketahui:** kolom Kategori bertipe `select` di
`<x-grid.spreadsheet-table>` (`spreadsheetgridstandard.md`) TIDAK mendukung
opsi `key=>label` beda dari value (`x-for="opt in col.options"`, value & teks
tampil = objek yang sama) — jadi dropdown-nya menampilkan **kode kategori
mentah** (`BRANDING`/`FINANCE`/dst), bukan label ramah ("Identitas
Aplikasi"). Ini keterbatasan komponen shared yang dipakai apa adanya (sama
kontrak dgn Master Currency/UOM) — kalau mau diperbaiki, itu perubahan di
`<x-grid.spreadsheet-table>` sendiri (dampaknya ke SEMUA pemakai select-type,
termasuk 2 pilot lama), bukan sesuatu yang di-workaround lokal di sini.

---

## 4. FONT PRESET

`App\Support\Branding\FontPresets` — daftar **terbatas** (bukan input bebas):
`system-ui` (default, TANPA request eksternal), `inter`/`poppins`/`nunito`/
`roboto` (via Google Fonts, `<link>` cuma dimuat kalau preset ini yang
dipilih). Constant `APP_FONT_FAMILY` simpan KEY preset (bukan CSS mentah) —
resolve ke CSS var `--brand-font` lewat `App\Support\Branding\Brand::fontStack()`,
di-render di `<head>` (`resources/views/partials/brand-font.blade.php`,
di-`@include` di `layouts::shell` & `layouts::guest`). `app.css` `.shell`/
`.authpage` sudah `font-family: var(--brand-font, <fallback lama>)`.

**Tambah preset font baru** — tambah entry di `FontPresets::PRESETS`
(constant class, bukan DB — daftar preset itu sendiri bagian dari kode, cuma
PILIHANNYA yang di-generate ke `<select>`).

---

## 5. SIAPA MEMBACA `Brand::` (konsumen, read-only)

`App\Support\Branding\Brand` = titik akses tunggal (nama/tagline/logo/font),
dibaca dari `sys_constant` lewat `SysConstant::text()`. Dipakai di:

- Shell lama: `main-menu.blade.php` (topbar), `<x-auth.hero-brand>` (login/
  register), `<x-auth.top-brand>` (forgot-password/password-change/security-setup).
- Filament: `App\Providers\Filament\AdminPanelProvider` — `->brandName()`/
  `->brandLogo()`/`->favicon()` dihitung dari `Brand::` per request (lihat
  `admindashboardstandard.md` §11 untuk detail sisi Filament-nya).

**Judul tab browser SUDAH dinamis (2026-09-02), TANPA edit 97 file** — solusi:
`#[Title]` PHP attribute memang wajib *constant expression* (tidak bisa
panggil `Brand::name()` langsung), TAPI string literalnya cuma perlu jadi
1 PLACEHOLDER yang di-cari-ganti sekali di titik render `<title>`.
`layouts::shell`/`guest`/`app` (3 file) sekarang jalankan
`str_replace(['TELASIH MAKMUR ERP', 'TELASIH MAKMUR'], [Brand::name(), Brand::name()], $title)`
sebelum echo — literal "TELASIH MAKMUR"/"TELASIH MAKMUR ERP" di 97
`#[Title(...)]` yang ADA SEKARANG otomatis ikut berubah, **TIDAK PERLU
DIEDIT SATU-SATU**. Kalau bikin layar baru, tulis `#[Title('X — TELASIH
MAKMUR ERP')]` seperti biasa (pola existing) — placeholder-nya otomatis
ke-substitusi, jangan taruh nama app literal lain.

**Sengaja TIDAK diubah jadi dinamis** (di luar cakupan, prioritas rendah):
PDF footer (`exports/generic-grid-pdf.blade.php`) dan email
`verify-email.blade.php` — flag ini kalau diminta eksplisit nanti.
`layouts::portal.blade.php` (portal karir eksternal) **sengaja TIDAK
disentuh** — "Telasih Makmur" di situ representasi NICKNAME company ke
calon karyawan/customer eksternal (nama terdaftar **CV. Langgeng
Jaya Makmur** = konstanta `COMPANY_LEGAL_NAME`, lihat §3.1 — 2026-09-23),
konsepnya beda dari APP_NAME aplikasi internal, jangan digabung.

---

## 6. YANG SENGAJA BELUM DIKERJAKAN (Fase 2, TERPISAH)

Sistem **Report 4 level** (Data Entry Officer/Supervisor/Manager/Top
Management + graphics report) yang diminta user 2026-09-02 **BUKAN** bagian
Fase 1 ini — kerangka standarnya ada di `docs/reportstandard.md` (skeleton
saja, BELUM ada layar report sungguhan) — scoping detail (laporan apa per
modul, level mana lihat data apa) menyusul terpisah kalau diminta.
