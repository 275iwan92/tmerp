---
name: flow_admin_dashboard_filament
description: Filosofi migrasi bertahap shell lama -> panel Filament v5 /admin, auth bridge login/pswd, pola Cluster->Resource->Page, status modul per-modul.
---

# Alur Migrasi Panel Admin Filament (`/admin`)

**Modul**: Admin Dashboard (Filament v5) -- migrasi bertahap dari shell custom lama
**Panel provider**: `app/Providers/Filament/AdminPanelProvider.php`
**Panel id/path**: `admin` -> `/admin`

## Ringkasan

Sejak 2026-08-30 (keputusan user "migrasi total" via AskUserQuestion), aplikasi memindahkan modul-modul dari shell custom lama (`/menu`, `main-menu.blade.php`, layar Livewire diakses lewat route `pages::*`) ke panel Filament v5 (`/admin`) SATU MODUL PADA SATU WAKTU. Kedua sistem hidup **berdampingan** selama migrasi berlangsung -- path panel Filament sengaja dipertahankan `admin` (bukan `/`) supaya `/menu` lama tidak mati begitu 1-2 modul pertama dipindah. Baru diganti ke `path('')` setelah SEMUA modul selesai dimigrasikan dan shell lama resmi dipensiunkan (belum terjadi).

Modul HRM adalah **modul percobaan pertama** (proof-of-concept) migrasi ini -- 3 layar (Master Employee, Master Posisi, Pipeline Board) sudah punya versi Filament, dijadikan pola acuan untuk modul-modul berikutnya.

## Auth Bridge: kolom `login`/`pswd`, BUKAN email/password

Model autentikasi aplikasi, `App\Models\Sys\SecUser` (tm2sys, tabel `sec_user`), memakai kolom `login` (username) dan `pswd` (password, bcrypt) -- BUKAN `email`/`password` bawaan Laravel/Filament. `SecUser::getAuthPassword()` mengembalikan `$this->pswd`.

Kalau panel Filament dipaksa memakai form login bawaan apa adanya, `Auth::attempt(['email' => ..., 'password' => ...])` akan **selalu gagal** karena kolom `email` bukan identifier login yang valid di skema ini. Solusi yang dipakai (bukan dugaan -- ditemukan & dicegah eksplisit lewat komentar kode):

1. **Guard `web` dipakai apa adanya** -- provider `SecUser` sudah didaftarkan di `config/auth.php`, jadi SESSION LOGIN yang sama dengan `/login` custom otomatis dikenali di `/admin`. Tidak perlu login 2 kali.
2. **Custom Login page** (`App\Filament\Pages\Auth\Login`, didaftarkan via `->login(Login::class)` di `AdminPanelProvider`) HANYA dipakai kalau Filament perlu redirect guest (belum ada sesi) ke halaman login. 3 method di-override:
   - `getEmailFormComponent()` -- ganti field `email` jadi `TextInput::make('login')` (label "Username").
   - `getCredentialsFromFormData()` -- susun credentials array `['login' => ..., 'password' => ...]`, bukan `['email' => ...]`.
   - `throwFailureValidationException()` -- lempar error ke key `'data.login'` (bukan `'data.email'` bawaan yang sudah tidak ada di form), supaya pesan gagal login tidak "hilang diam-diam".
3. **Gate masuk panel**: `SecUser::canAccessPanel(Panel $panel)` (interface `FilamentUser`) mengembalikan `(bool) $this->activate` -- akun nonaktif diblokir total, konsisten dengan shell lama.

File terkait: `app/Filament/Pages/Auth/Login.php` (lihat `docs/screens/screen_admin_auth_login.md`).

## Pola Cluster -> Resource -> Page (contoh: modul HRM)

Migrasi Filament mencerminkan hierarki menu lama **Module -> Group -> Item** TANPA merombaknya (syarat mutlak user 2026-08-30), lewat 3 lapis struktur Filament:

| Level lama (`sys_app_menu`) | Konstruksi Filament | Contoh (HRM) |
|---|---|---|
| Module (`menu_type='module'`) | **Cluster** (`Filament\Clusters\Cluster`) | `App\Filament\Clusters\Hrm\HrmCluster` (code `HRM`, label "Human Resources", `sort_order=45`) |
| Group (`sys_app_menu` parent group) | Properti `$navigationGroup` di tiap Resource/Page anggota cluster | `'Organization'`, `'Employee'`, `'Payroll'`, `'Time Attendance'`, `'Recruitment'` |
| Item (`sys_app_menu` item) | **Resource** (CRUD) atau **Page** (non-CRUD) | `EmpEmployeeResource` (Master Employee), `OrgPositionResource` (Master Posisi), `PipelineBoard` (Custom Page) |

**Urutan grup navigasi** TIDAK otomatis mengikuti `sort_order` di DB -- Filament secara default mengurutkan grup berdasarkan urutan ditemukan saat *discovery*. Karena itu urutan grup HRM didaftarkan **eksplisit** di `AdminPanelProvider::panel()->navigationGroups([...])`: `Organization(5) < Employee(10) < Payroll(15) < Time Attendance(20) < Recruitment(50)`. Modul lain yang menyusul migrasinya WAJIB menambahkan grupnya sendiri di sini juga.

### 2 pola konversi layar yang ditemukan

1. **Resource CRUD standar** (`EmpEmployeeResource`, `OrgPositionResource`) -- `form()`/`table()` Filament Schema Builder ditulis ulang dari nol berdasarkan layar shell lama (`employee.blade.php`, `position.blade.php`), TAPI hanya mencakup subset field inti dari model yang sebenarnya (lihat catatan gap di masing-masing `docs/screens/screen_admin_hrm_empemployee_resource.md` dan `screen_admin_hrm_orgposition_resource.md`). Halaman `index` memakai pola `ManageRecords` (1 halaman, form create/edit sebagai modal) -- BUKAN `ListRecords`+`CreateRecords`+`EditRecords` terpisah.
2. **Custom Page reuse Livewire lama** (`PipelineBoard`) -- untuk layar non-CRUD (kanban drag-drop), Filament Page HANYA jadi pintu navigasi+routing; isi sesungguhnya tetap komponen Livewire v4 SFC asli (`pages::recruitment.pipeline-board`) dipanggil apa adanya lewat `@livewire(...)` di view wrapper (`resources/views/filament/clusters/hrm/pages/pipeline-board.blade.php`) -- logic drag-drop/state TIDAK ditulis ulang.

### Profil pengguna: preferensi tampilan personal

`App\Filament\Pages\Auth\EditProfile` (didaftarkan via `->profile(EditProfile::class, isSimple: false)`) menambah 1 section "Tampilan Dashboard" di atas form profil bawaan -- menyimpan `sec_user.admin_theme_mode`/`admin_theme_accent` (personalisasi KHUSUS panel `/admin`, terpisah total dari `sys_theme` yang dipakai shell lama, lihat `docs/themestandard.md`). Diterapkan per-request lewat render hook `PanelsRenderHook::HEAD_END` (view `filament.partials.theme-matrix`) -- BUKAN di `AdminPanelProvider::panel()` itu sendiri, karena method itu cuma dieksekusi SEKALI saat `PanelRegistry` pertama kali di-resolve (jauh sebelum middleware auth/session jalan), sehingga `Auth::user()` di sana selalu `null`.

## Gotcha arsitektural (dari komentar `AdminPanelProvider`)

- `PanelProvider::panel()` **tidak** jalan per-request meski terlihat begitu -- personalisasi per-user tidak boleh dihitung di method itu (lihat bagian EditProfile di atas).
- `->darkMode()` (bukan `->darkMode(false)`) WAJIB tetap aktif, meski mode final ditentukan per-user lewat render hook -- kalau `false`, Filament memaksa `localStorage.theme='light'` tiap load dan menimpa override tersebut.
- `->topNavigation()` (fitur bawaan Filament) dikonfirmasi sudah persis meniru pola shell lama (top mega-nav pilih modul + sub-sidebar kiri isi grup/item) tanpa perlu render hook/Blade override custom -- topbar selalu menampilkan `Dashboard | <Nama Cluster>` di semua halaman dalam cluster tsb.

## Status modul lain (belum dimigrasikan, masih shell lama)

Berdasarkan `AdminPanelProvider::navigationGroups()` yang baru mendaftarkan grup-grup modul HRM, dan `discoverResources()`/`discoverPages()`/`discoverClusters()` yang men-scan `app/Filament/{Resources,Pages,Clusters}` -- modul selain HRM **belum** punya representasi di `app/Filament/*` per sesi dokumentasi ini (TBD - perlu verifikasi menyeluruh lewat `Glob app/Filament/Clusters/*` untuk daftar final, tapi struktur yang terbaca di sesi ini hanya berisi cluster `Hrm`). Modul-modul berikut (per CLAUDE.md §2, daftar database-per-modul) diasumsikan MASIH di shell lama (`/menu`, layar Livewire `pages::*`) sampai ada bukti migrasi lanjutan:

| Modul | Database | Status Filament |
|---|---|---|
| Sales (Inquiry/Quotation/CO/Delivery) | tm2sdi | Shell lama (belum dimigrasikan) |
| Procurement | tm2pur | Shell lama (belum dimigrasikan) |
| Inventory | tm2inv | Shell lama (belum dimigrasikan) |
| Production | tm2prd | Shell lama (belum dimigrasikan) |
| Finance & Accounting | tm2acc | Shell lama (belum dimigrasikan) |
| HR Organization/Employee/Recruitment | tm2hrm/tm2hrr | **Sebagian**: cluster HRM Filament sudah ada (`EmpEmployeeResource`, `OrgPositionResource`, `PipelineBoard`) -- POC migrasi, kemungkinan besar BELUM mencakup semua layar HRM shell lama (Roster/Self-Service/Correction Request/dll. -- lihat memory `project_hrm_module_status.md`). Recruitment Portal publik (`/hrm/recruitment/portal`) TIDAK termasuk migrasi ini -- itu aplikasi terpisah dengan guard sesi sendiri (`portal.candidate`), lihat `docs/flows/flow_recruitment_portal_public.md`.
| Payroll | tm2pyr | Belum ada modul (requirement, 0% dibangun sesuai memory `project_payroll_encryption_requirement.md`) |
| Sistem (auth/role/notifikasi) | tm2sys | Auth bridge (`login`/`pswd`) & Edit Profile sudah tersentuh Filament (lihat bagian Auth Bridge di atas); layar admin sistem lain (Master User/Role/dll.) TBD - perlu verifikasi. |

## Tabel Cluster/Resource -> Tabel DB -> Status

| Cluster/Resource/Page | Tabel DB (koneksi) | Status |
|---|---|---|
| `HrmCluster` | -- (navigasi saja) | Live |
| `EmpEmployeeResource` (+ `ManageEmpEmployees`) | `emp_employee` (tm2mst) | Live, subset field (lihat catatan gap di `screen_admin_hrm_empemployee_resource.md`) |
| `OrgPositionResource` (+ `ManageOrgPositions`) | `org_position` (tm2mst) | Live, subset field (lihat catatan gap di `screen_admin_hrm_orgposition_resource.md`) |
| `PipelineBoard` | Delegasi ke komponen Livewire lama (tabel di luar scope dokumentasi ini) | Live (reuse, bukan tulis ulang) |
| `EditProfile` (Auth) | `sec_user` (tm2sys) -- kolom `admin_theme_mode`/`admin_theme_accent` | Live |
| `Login` (Auth) | `sec_user` (tm2sys) -- lewat guard `web`, tanpa query langsung di file | Live |

## Catatan

- Semua kesimpulan "belum dimigrasikan" untuk modul selain HRM adalah **inferensi** dari struktur yang terbaca di sesi ini (`app/Filament/Clusters/Hrm/*` + `app/Filament/Pages/Auth/*`), bukan hasil audit `Glob` menyeluruh atas `app/Filament/**` -- kalau perlu kepastian penuh, jalankan `Glob app/Filament/**/*.php` untuk daftar final semua Resource/Page/Cluster yang sudah ada.
- Tidak ada dokumen `admindashboardstandard.md` yang dibaca ulang secara detail dalam sesi ini (di luar cakupan 8 file kode yang diminta) -- untuk aturan chrome/navigasi Filament lengkap, rujuk langsung ke dokumen itu (CLAUDE.md §6 menandainya wajib dibaca sebelum memindahkan modul apa pun ke panel Filament).
