---
name: screen_admin_auth_editprofile
description: Halaman Edit Profile bawaan Filament (panel /admin), dioverride untuk tambah preferensi Tampilan Dashboard personal.
---

# Edit Profile (Filament Auth Page)

**File**: `app/Filament/Pages/Auth/EditProfile.php`
**Route/Panel URL**: TBD - perlu verifikasi (halaman profil bawaan Filament, didaftarkan via `->profile(EditProfile::class, isSimple: false)` di `AdminPanelProvider`; biasanya diakses lewat dropdown avatar panel, path umum `/admin/profile`).
**Tipe**: Page (override `Filament\Auth\Pages\EditProfile`)
**Koneksi DB utama**: sys (tm2sys) -- tabel `sec_user` (kolom `admin_theme_mode`/`admin_theme_accent`) via model `App\Models\Sys\SecUser`.
**Modul**: Admin Dashboard (Filament) -- Auth/Profile (lintas-modul, bukan bagian cluster HRM).

## Tujuan
Halaman Edit Profile standar panel `/admin`, ditambah 1 bagian kustom "Tampilan Dashboard" -- preferensi mode & warna aksen dashboard yang personal per-user (`sec_user.admin_theme_mode`/`admin_theme_accent`), berbeda dari `sys_theme` shell lama yang dipakai halaman non-Filament. UI picker-nya mengikuti mockup `docs/mockups/mockup_dashboardcolor.html` (menurut komentar kode) dan `docs/admindashboardstandard.md` §10.

## Field & Kolom (Filament Schema)
`form(Schema $schema): Schema` menggabungkan komponen bawaan `EditProfile` (`parent::form($schema)->getComponents()`, isinya field standar Filament seperti nama/email/password -- tidak didefinisikan ulang di file ini) dengan 1 komponen tambahan dari `getDashboardThemeSectionComponent()`:
- `Section::make('Tampilan Dashboard')` -- deskripsi "Pilih mode & warna aksen dashboard -- cuma berlaku utk akun Anda."
  - `Hidden::make('admin_theme_mode')` default `'light'`.
  - `Hidden::make('admin_theme_accent')` default `'blue'`.
  - `View::make('filament.forms.dashboard-theme-picker')` -- UI picker sesungguhnya (custom Blade+Alpine), field `Hidden` di atas cuma menampung state yang diisi lewat interaksi Alpine di view tersebut.

## Aksi / Method
- `form(Schema $schema): Schema` -- override, gabungkan komponen bawaan + section tema dashboard.
- `getDashboardThemeSectionComponent(): Component` -- method privat/protected penyusun `Section` di atas.
- Tidak ada override `save()`/`handleRecordUpdate()` -- disimpan lewat mekanisme bawaan `EditProfile` (`handleRecordUpdate()` -> `$record->update($data)`), berfungsi karena `admin_theme_mode` dan `admin_theme_accent` sudah ada di `$fillable` model `SecUser`.

## Tabel & Model yang Disentuh
- Model: `App\Models\Sys\SecUser` (`$connection = 'tm2sys'`, `$table = 'sec_user'`, `$primaryKey = 'id_user'`) -- kolom `admin_theme_mode`, `admin_theme_accent` termasuk `$fillable`.
- Tidak ada query lintas-DB di file ini.

## Alur / Business Logic Penting
- View `filament.forms.dashboard-theme-picker` (di luar scope 8 file sesi ini) adalah UI aktual picker mode/warna -- file `EditProfile.php` sendiri cuma menyediakan wadah `Hidden` field untuk menampung hasil pilihan.
- Preferensi ini SEPENUHNYA terpisah dari `sys_theme` (dipakai shell lama, 4 mode x 3 corak, lihat `docs/themestandard.md`) -- `admin_theme_mode`/`admin_theme_accent` khusus personalisasi panel Filament `/admin` saja.
- Berdasarkan komentar `AdminPanelProvider`, render hook `filament.partials.theme-matrix` di `HEAD_END` yang benar-benar menerapkan personalisasi ini per-request (baca `Auth::user()` yang valid saat request, bukan saat `panel()` didaftarkan).

## Catatan
Tidak ada komentar TODO eksplisit di file ini. Field form standar Filament (nama/email/password) yang diwarisi dari `parent::form()` TIDAK didokumentasikan detail di sini karena tidak didefinisikan ulang di file yang dibaca (ada di package Filament sendiri, bukan kode aplikasi).
