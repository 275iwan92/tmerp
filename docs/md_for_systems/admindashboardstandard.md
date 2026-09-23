# STANDAR — ADMIN DASHBOARD (FILAMENT PANEL, `/admin`)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-30, migrasi FASE 1 (keputusan user: migrasi total shell admin
> lama ke Filament PHP v5, bertahap per-modul, dikonfirmasi lewat AskUserQuestion).
> Dokumen ini **wajib dibaca** sebelum bikin/pindahkan modul apa pun ke
> `/admin` — pola sama semangatnya dengan `griduistandard.md` untuk grid lama.
> **Ini HANYA mengatur navigasi/chrome panel Filament.** Isi Form/Grid/Print/
> Gantt/Kanban di dalam layar Filament tetap ikuti dokumen standarnya
> masing-masing — lihat `uistandardindex.md`.

---

## 1. KENAPA ADA 2 SHELL SEKALIGUS

Panel Filament (`/admin`) jalan **berdampingan** dengan shell custom lama
(`/menu`, `resources/views/pages/main-menu.blade.php`) selama migrasi
bertahap — BUKAN cutover sekaligus. `path('admin')` di
`AdminPanelProvider` **sengaja dipertahankan** (bukan `path('')`) supaya
`/menu` lama tidak mati begitu 1-2 modul pertama dipindah. Ganti ke
`path('')` hanya setelah SEMUA modul selesai dimigrasikan & shell lama
resmi dipensiunkan — jangan lakukan ini sepihak, itu keputusan arsitektural
yang perlu dikonfirmasi user dulu (CLAUDE.md §7 poin 7).

---

## 2. PEMETAAN HIERARKI MENU LAMA → FILAMENT

`sys_app_menu` (tabel lama, DB-driven, difilter per grup user) punya 3
level: **Module → Group → Item**. Filament tidak punya primitif 3-level
langsung — dipetakan begini:

| Level lama (`sys_app_menu`) | Konsep Filament | Contoh nyata |
|---|---|---|
| `menu_type='module'` (mis. `HRM`) | **Cluster** (`app/Filament/Clusters/<Nama>/`) | `App\Filament\Clusters\Hrm\HrmCluster` |
| `menu_type='group'` (mis. `HRM_MASTER` label "Employee") | `protected static string\|UnitEnum\|null $navigationGroup` di tiap Resource/Page milik cluster itu | `$navigationGroup = 'Employee';` |
| `menu_type='item'` (mis. `HRM_EMPLOYEE` label "Master Employee") | Resource atau Page itu sendiri | `EmpEmployeeResource` |

**Urutan (`sort_order`) HARUS disalin persis dari DB**, jangan ditebak/dibiarkan
default — lihat §4.

---

## 3. TOP BAR = PEMILIH MODUL, SIDEBAR = ISI MODUL — `->topNavigation()`

Shell lama itu hybrid: topbar mega-nav untuk pilih **Module**, sidebar kiri
untuk isi **Group→Item** milik Module yang aktif. Filament punya fitur
**bawaan** yang sudah persis pola ini — `->topNavigation()` di
`AdminPanelProvider` — **JANGAN bikin render hook/Blade override custom
untuk ini**, sudah dicoba & terbukti tidak perlu (diverifikasi 2026-08-30
lewat request HTTP kernel asli ke 5 route: topbar selalu tampil "Dashboard |
<nama Cluster>" di SEMUA halaman, sidebar kiri otomatis scoped ke
Group+Item milik Cluster yang sedang dibuka).

```php
$panel->topNavigation(); // itu saja, tidak ada konfigurasi tambahan yang dibutuhkan
```

**Keterbatasan kosmetik yang diketahui, belum diperbaiki:** route index
sebuah Cluster (mis. `/admin/hrm`) redirect ke Resource pertama yang
DITEMUKAN saat discovery (urutan alfabetis folder), bukan ke Resource
dengan `navigationSort`/group `sort_order` terkecil. Dampaknya kecil (cuma
soal Resource mana yang tampil duluan saat masuk ke Cluster tanpa memilih
item spesifik) — perbaiki hanya kalau user secara eksplisit minta.

---

## 4. URUTAN GRUP — DAFTARKAN EKSPLISIT DI PANEL

Filament **tidak** otomatis mengurutkan `navigationGroup` sesuai
`sort_order` DB — defaultnya ikut urutan DITEMUKAN saat discovery. Daftarkan
manual di `AdminPanelProvider::panel()`, urutannya HARUS dicek dulu ke
`sys_app_menu` (jangan asumsi/hafalan):

```php
use Filament\Navigation\NavigationGroup;

$panel->navigationGroups([
    NavigationGroup::make('Organization'),   // HRM_ORG, sort_order=5
    NavigationGroup::make('Employee'),       // HRM_MASTER, sort_order=10
    NavigationGroup::make('Payroll'),        // HRM_PAYROLL, sort_order=15
    NavigationGroup::make('Time Attendance'),// HRM_SCHEDULE, sort_order=20
    NavigationGroup::make('Recruitment'),    // HRM_RECRUITMENT, sort_order=50
]);
```

Tambahkan grup modul lain ke array yang SAMA saat modul itu menyusul
dimigrasikan — jangan bikin panggilan `->navigationGroups()` terpisah per
modul (yang terakhir dipanggil akan menimpa, bukan menggabung).

---

## 5. AUTH — JANGAN PAKAI FORM LOGIN BAWAAN FILAMENT

`SecUser` (tm2sys.sec_user) pakai kolom **`login`+`pswd`**, bukan
`email`+`password` bawaan Filament. Form login default Filament HARDCODE
field `email`, jadi kalau dipakai apa adanya, SEMUA percobaan login akan
gagal (kolom `email` bukan identifier akun) — ini bukan tebakan, sudah
dicek langsung ke source `Filament\Auth\Pages\Login`.

**Solusi yang sudah diimplementasi & terbukti jalan** (`app/Filament/Pages/Auth/Login.php`):

```php
class Login extends \Filament\Auth\Pages\Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')->label('Username')->required()->autocomplete()->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return ['login' => $data['login'], 'password' => $data['password']];
    }

    // WAJIB juga override ini -- base class hardcode key 'data.email' utk
    // pesan gagal, kalau tidak error akan "hilang" diam2 (attach ke field yg
    // sudah tak ada di form baru).
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages(['data.login' => __('filament-panels::auth/pages/login.messages.failed')]);
    }
}
```

Panel dikonfigurasi `->login(Login::class)` (BUKAN `->login()` polos) +
`->authGuard('web')` — guard `web` itu PERSIS guard yang sudah dipakai
`/login` custom lama (`config/auth.php` provider `users` → `SecUser`), jadi
sesi yang sama otomatis dikenali di `/admin`, user **tidak perlu login dua
kali**.

`SecUser` juga wajib `implements Filament\Models\Contracts\FilamentUser`
dengan `canAccessPanel(Panel $panel): bool { return (bool) $this->activate; }`
— tanpa ini SEMUA user (termasuk yang `activate=0`) bisa masuk panel.

---

## 6. CARA BIKIN RESOURCE BARU DI DALAM CLUSTER — HINDARI CRASH

**JANGAN pakai `make:filament-resource --cluster=...` langsung** — di
environment ini (Windows/Herd) itu memicu PHP Fatal `Cannot redeclare
class` yang reproducible (root cause belum 100% terpetakan, kemungkinan
interaksi discovery Filament dgn path Windows; sudah diselidiki cukup
dalam, bukan sekadar cache basi). **Workaround yang terbukti aman, hasil
akhirnya identik:**

```bash
# 1. Generate TANPA --cluster (ini SELALU jalan)
php artisan make:filament-resource Master/NamaModel --simple --no-interaction

# 2. Pindahkan manual folder hasil generate ke dalam cluster
mv app/Filament/Resources/Master/NamaModels app/Filament/Clusters/<Cluster>/Resources/NamaModels

# 3. Perbaiki `namespace`/`use` di kedua file (Resource + Pages/Manage*.php)
#    dari App\Filament\Resources\Master\... jadi App\Filament\Clusters\<Cluster>\Resources\...

# 4. Tambahkan manual ke class Resource:
#    protected static ?string $cluster = <Cluster>Cluster::class;
#    protected static string|\UnitEnum|null $navigationGroup = '<Group sesuai sys_app_menu>';
#    protected static ?string $navigationLabel = '<Label sesuai sys_app_menu>';
#    protected static ?int $navigationSort = <sort_order sesuai sys_app_menu>;
```

Pola sama berlaku untuk `make:filament-page` di dalam cluster.

**Halaman Resource WAJIB turunan induk `App\Filament\Base\*` (UI-10, 2026-09-23).** Jangan pakai `--simple`/`ManageRecords` (form modal menelan klik sidebar). Daftarkan 3 halaman `index`/`create`/`edit`; tiap halaman cukup `class ListX extends TmListRecords { protected static string $resource = XResource::class; }` (juga `TmCreateRecord`, `TmEditRecord`). Label Simpan/Batal, tanpa 'create another', redirect ke grid, dan peringatan perubahan belum tersimpan (`unsavedChangesAlerts()` di panel) sudah diurus induk. Contoh: `app/Filament/Clusters/Hrm/Resources/*/Pages`.

**Gotcha kedua yang WAJIB diperhatikan tiap bikin Resource/Page baru:**
override properti `$navigationGroup` **HARUS** persis tipe union induknya
(`string|UnitEnum|null`) — PHP menuntut override properti INVARIANT (beda
dari override method yang boleh covariant). Menulis `protected static
?string $navigationGroup` (tanpa `UnitEnum`) = PHP Fatal Error saat class
di-load. Properti nav lain (`$navigationLabel` = `?string`,
`$navigationSort` = `?int`, `$cluster` = `?string`, `$navigationIcon` =
`string|BackedEnum|null`) SUDAH benar di scaffold generator, cuma
`$navigationGroup` yang perlu union type tambahan.

---

## 7. KONVERSI LAYAR NON-CRUD (KANBAN/KALENDER/DLL) — JANGAN TULIS ULANG

Kalau layar lama BUKAN grid/form standar (mis. Pipeline Board, Interview
Schedule kalender, Org Chart) — class Filament Page-nya **HANYA jadi pintu
navigasi+routing**, isi Blade-nya tetap panggil komponen Livewire v4 ASLI
apa adanya lewat `@livewire('pages::<nama.komponen.lama>')`. **Jangan
tulis ulang logikanya ke dalam Filament Page/Schema.**

Contoh nyata (`app/Filament/Clusters/Hrm/Pages/PipelineBoard.php` +
`resources/views/filament/clusters/hrm/pages/pipeline-board.blade.php`):

```php
class PipelineBoard extends Page
{
    protected string $view = 'filament.clusters.hrm.pages.pipeline-board';
    protected static ?string $cluster = HrmCluster::class;
    protected static string|\UnitEnum|null $navigationGroup = 'Recruitment';
    protected static ?string $navigationLabel = 'Pipeline Board';
    protected static ?int $navigationSort = 40;
}
```

```blade
{{-- resources/views/filament/clusters/hrm/pages/pipeline-board.blade.php --}}
<x-filament-panels::page>
    @livewire('pages::recruitment.pipeline-board')
</x-filament-panels::page>
```

Komponen Livewire lama (`resources/views/pages/recruitment/pipeline-board.blade.php`)
TETAP ikut `kanbanboardstandard.md` seperti biasa — dokumen ini tidak
menggantikannya, cuma menambahkan pintu masuk baru.

---

## 8. VERIFIKASI — TIDAK BISA LOGIN BROWSER (LIHAT MEMORY SESI)

Panel Filament TIDAK bisa diverifikasi lewat login browser otomatis
(aturan proyek: jangan pernah mengetik password ke field login lewat
automation, bahkan akun dev/test). Cara verifikasi yang benar:

1. `Livewire::test(SomeFilamentPage::class)` — Filament Page/Resource Page
   ADALAH komponen Livewire, jadi bisa langsung ditest sama seperti layar
   lama, tapi **`.html()` HANYA berisi markup komponen itu sendiri, TIDAK
   termasuk layout topbar/sidebar** (beda dari `pages::main-menu` lama yang
   memang 1 komponen = 1 halaman penuh). Cukup untuk cek isi Form/Table di
   dalam Resource, TIDAK cukup untuk cek navigasi/topbar.
2. Untuk cek navigasi/topbar/sidebar (termasuk urutan grup, apakah
   `->topNavigation()` benar-benar merender seperti diharapkan): dispatch
   request ASLI lewat HTTP kernel, bukan cuma `route:list`:
   ```php
   Auth::login($johannes); // is_admin, biar canAccessPanel() lolos
   $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
   $resp = $kernel->handle(\Illuminate\Http\Request::create('/admin/hrm/emp-employees', 'GET'));
   // cek $resp->getStatusCode() === 200, dan substr_count($resp->getContent(), '<label grup/nama>')
   ```
   Ini menangkap FULL HTML (topbar+sidebar+content), sesuatu yang
   `Livewire::test()` tidak tangkap untuk halaman Filament.

---

## 10. TEMA WARNA DASHBOARD (PERSONAL, USER PROFILE)

> Dibuat 2026-09-02, ikut mockup `docs/mockups/mockup_dashboardcolor.html`.
> **Beda dari `themestandard.md`** (itu punya SHELL LAMA, matrix 4 mode x 3
> corak, `tm2sys.sys_theme`, berlaku SAMA ke semua device user) — ini
> **PERSONAL per-user**, cuma berlaku di panel Filament, disimpan 2 kolom
> `sec_user.admin_theme_mode`/`admin_theme_accent` (bukan tabel lookup
> terpisah — 5 mode x 7 aksen sepenuhnya ortogonal di mockup, tidak butuh
> kombinasi ter-precompute spt `sys_theme`).

### 10.1 5 Mode x 7 Aksen

Mode: `light`/`pastel`/`bright`/`dark`/`black`. Aksen: `blue`/`green`/`red`/
`purple`/`amber`/`cyan`/`mono`. Diedit di **User Profile** Filament
(`/admin/profile`, `App\Filament\Pages\Auth\EditProfile extends
Filament\Auth\Pages\EditProfile`), section "Tampilan Dashboard" — UI-nya
persis mockup: `<select>` mode + 7 swatch bulat aksen
(`resources/views/filament/forms/dashboard-theme-picker.blade.php`), ganti
warna **instan** di client (Alpine set atribut `data-fi-theme`/`data-fi-accent`
di `<html>`) sebelum tombol Save bawaan Filament diklik (baru situ tersimpan
permanen ke DB) — persis cara mockup ("ikutin mockup... begitu pula cara
user menggantinya", instruksi user 2026-09-02).

### 10.2 BUG ARSITEKTURAL nyata ditemukan & diperbaiki 2026-09-02 — `panel()` TIDAK jalan per-request

**Versi awal (SALAH, sempat live beberapa jam)**: personalisasi aksen/mode
dihitung DI DALAM `AdminPanelProvider::panel()` (`Auth::user()->admin_theme_*`,
lalu `->colors()`/`->defaultThemeMode()`). Ini SALAH — **`panel()` cuma
dieksekusi SEKALI**, saat `PanelRegistry` PERTAMA KALI di-resolve dari
container (`Filament::registerPanel()` → `app()->resolving(PanelRegistry::class, ...)`,
lihat `vendor/filament/filament/src/Facades/Filament.php`), **jauh sebelum**
middleware auth/session jalan untuk request itu. `Auth::user()` di dalam
`panel()` **SELALU null** — akibatnya SEMUA user melihat aksen/mode yang
SAMA (default statis), bukan preferensi masing-masing, meski tersimpan
benar di DB dan form Save tidak error. Ketahuan lewat verifikasi silang 5
mode × 4 halaman (bukan cuma 1 mode), bukan laporan user.

**Perbaikan**: personalisasi dipindah SEPENUHNYA ke render hook HEAD_END
(`filament.partials.theme-matrix.blade.php`) — render hook TERBUKTI jalan
per-request (Blade di-render sesudah auth mapan). Mekanismenya:
- **Aksen** → `Filament\Support\Colors\Color::hex($hex)` (fungsi PALET
  YANG SAMA dipakai `Panel::colors()` internal, `App\Support\Branding\DashboardAccent::hex()`
  utk lookup key→hex) dipanggil ULANG di render hook, hasil 11-shade
  `--primary-50`..`--primary-950` di-echo sbg `<style>:root{...}</style>`
  di HEAD_END — cascade CSS menang krn dirender PALING AKHIR (sama
  selector `:root`, urutan sumber menentukan). `panel()` sendiri cuma set
  default statis (blue) utk guest/fallback.
- **Dark/Light** → `Panel::darkMode()` (toggle-able) TETAP dipanggil di
  `panel()` (WAJIB, kalau `darkMode(false)` Filament maksa
  `localStorage.theme='light'` tiap load, lihat `layout/base.blade.php`) —
  TAPI keputusan mode aktual (`.dark` class + `localStorage.theme`)
  di-set oleh script inline di render hook yang sama, dihitung dari user
  yang benar-benar login. `->defaultThemeMode()` di `panel()` **dihapus**
  (percuma, sama masalahnya dgn `->colors()`).

**Diverifikasi ulang** (bukan cuma 1x): `Livewire::test()`/HTTP-kernel
lintas 5 mode x 4 halaman (`/admin`, 2 Resource HRM, `/admin/profile`),
cek isi script (`isDark` flag benar) + `--primary-500` OKLCH hue beda
antar 2 aksen berbeda pada baris TERAKHIR (bukan cuma ada, tapi ada di
posisi yang MENANG cascade). 0 gagal dari 20 kombinasi.

**Pelajaran umum** (berlaku ke Resource/Page Filament lain juga): **jangan
pernah panggil `Auth::user()`/logic per-user di dalam `PanelProvider::panel()`**
— method ini SETARA konfigurasi statis boot-time, BUKAN lifecycle
per-request. Personalisasi apa pun yang butuh user login HARUS lewat
render hook, middleware, atau titik lain yang benar-benar jalan sesudah
auth resolve.

**Lapis 2 (kosmetik, best-effort, custom CSS):** variasi background per mode
yang Filament sendiri TIDAK punya (pastel tint, glass blur ala mockup, OLED
hitam pekat) — SAMA render hook di atas. Target CSS cuma container besar
yang nama class-nya dipastikan stabil (`body.fi-body`, `.fi-sidebar`,
`.fi-topbar`, `.fi-main`) — **BUKAN klaim override semua komponen
Filament**, kalau ada komponen dalam yang belum ikut ganti background saat
mode pastel/bright/black, itu memang belum tercakup lapis ini (bukan bug
tersembunyi, keputusan sadar batasi scope).

### 10.3 Kenapa BUKAN tabel lookup spt `sys_theme`

`sys_theme` (shell lama) butuh tabel karena kombinasi mode+hue-nya
PRECOMPUTED (tiap baris py warna sidebar/aksen sendiri yg tidak bisa
diturunkan formula sederhana). Mockup dashboard ini SEBALIQNYA — mode &
aksen benar-benar ortogonal (35 kombinasi = 5x7 murni CSS var override,
tanpa pengecualian per kombinasi) — 2 kolom enum `sec_user` sudah cukup,
tabel lookup baru cuma nambah kompleksitas tanpa manfaat nyata.

---

## 11. BRAND FILAMENT (NAMA/LOGO/FAVICON) — SUMBER DI SHELL LAMA

`->brandName()`/`->brandLogo()`/`->favicon()` di `AdminPanelProvider` **TIDAK
lagi hardcode** ("TELASIH MAKMUR ERP" string literal) sejak 2026-09-02 —
dihitung dari `App\Support\Branding\Brand::` (baca `tm2sys.sys_constant`,
key `APP_NAME`/`APP_LOGO_PATH`). **Diedit di layar Configuration, shell
LAMA** (`/setup/configuration`, is_admin), BUKAN di panel Filament — dokumen
ini cuma mendokumentasikan sisi KONSUMEN (Filament baca), detail penuh
(struktur tabel, cara tambah konstanta, cara kelola bahasa) ada di
`docs/configurationstandard.md`. Kalau brand tidak berubah setelah admin
edit di Configuration, cek `Brand::` dulu (bukan cache Filament — tidak ada
caching di titik ini, query langsung tiap request).

---

## 12. YANG SENGAJA BELUM DIKERJAKAN

- Modul lain (Sales/Procurement/Inventory/Production/Finance/9 layar
  Recruitment lain/Setup) masih 100% di shell lama — migrasi baru
  menyentuh HRM (2 Resource + 1 Page) sebagai bukti pola, BUKAN rollout
  penuh. Jangan lanjutkan migrasi modul lain tanpa arahan eksplisit user
  soal modul mana & kapan.
- `composer.json` mengunci `"filament/filament": "^5.7"` — jangan upgrade
  major version tanpa sepengetahuan user (breaking changes API besar antar
  major Filament).
- Lapis 2 tema (§10.2, background per mode) & mekanisme aksen/dark-mode
  **sudah diverifikasi lebih dalam** 2026-09-02 (20 kombinasi 5 mode x 4
  halaman, cek isi script + posisi cascade CSS var yang menang) — TAPI ini
  masih verifikasi MEKANISME (HTTP kernel, bukan browser sungguhan), BUKAN
  screenshot visual asli. Kalau ada komponen Filament dalam yang kelihatan
  pecah/kurang kontras di mode tertentu pas dilihat user, itu perluasan
  selector CSS §10.2, bukan rombak arsitektur (arsitektur inti — akar
  masalah "panel() bukan per-request" — sudah dipastikan benar).
