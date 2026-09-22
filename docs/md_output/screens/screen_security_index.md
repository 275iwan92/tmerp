---
name: screen_security_index
description: Panel admin gabungan Pengguna, Grup, Hak Akses Grup, dan Migrasi Data Legacy
---

# Setup & Security

**File**: `resources/views/pages/security/index.blade.php`
**Route**: `/security` -> name `security.index` (middleware `admin`, di dalam grup `auth`+`force.password`)
**Koneksi DB utama**: sys (tm2sys) untuk Pengguna/Grup/Hak Akses; old (tm2old) + mst (tm2mst) untuk panel Migrasi Data Legacy
**Modul**: System & Setup

## Tujuan
Layar admin-only (gate `middleware('admin')`) dengan 4 panel tab (`activePanel`, `#[Url(as: 'tab')]`): **Pengguna** (CRUD akun login), **Grup** (CRUD grup hak akses), **Hak Akses Grup** (matriks izin per modul), dan **Migrasi Data Legacy** (tarik data ScriptCase V1 dari `tm2old` ke `tm2mst`). Panel Pengguna & Grup mengikuti pola Grid Master standar (`docs/griduistandard.md`) dengan swap grid<->form di dalam komponen yang sama (bukan modal float).

## Field & Kolom

### Panel 1 — Pengguna (grid + form)
Grid: kolom Pengguna (avatar+nama+login), Grup, Status (AKTIF/NONAKTIF badge), Password (reveal setelah reset), Aksi. Filter: `userSearch` (live-debounce 300ms, cari login/nama/email). Stat box: Total, Aktif, Wajib Ganti Password, Nonaktif.
Form: `u_login` (readonly saat edit, wajib+unik saat new), `u_name` (wajib), `u_email` (opsional), `u_pswd` (wajib min 8, hanya saat new), `u_group_id` (select wajib, dari `groupOptions()`).

### Panel 2 — Grup (grid + form)
Grid: kolom Grup (kode+nama), Level, Jumlah User, Status, Aksi. Filter: `groupSearch`. Stat box: Total Grup, Terisi (≥1 pengguna), Kosong.
Form: `g_code` (readonly saat edit, wajib+unik saat new, auto-uppercase+underscore), `g_name` (wajib), `g_level` (1-5: Staf/Supervisor/Manager/GM/Direksi).

### Panel 3 — Hak Akses Grup
`permGroupId` (select grup, `#[Url]`-independen, live). Tabel matriks modul (induk + sub, dari `SecModule::modulesTree()`) x 4 kolom toggle: `can_access`, `can_input`, `can_approve`, `can_export`.

### Panel 4 — Migrasi Data Legacy
Tidak ada field input — 3 tombol aksi dengan laporan hasil tampil di bawahnya (lihat Aksi).

## Aksi / Method
**Pengguna**: `openUserForm(mode, id)`, `showUserGrid()`, `saveUser()` (create/update + sync grup), `toggleUserActive(id)` (blokir nonaktifkan diri sendiri), `resetPassword(id)` (via `PasswordResetService`, plaintext ditampilkan sekali via session flash `resetPlain_{id}`), `revealPassword(id)` (lihat ulang `default_password` tanpa reset, khusus akun yang masih punya `default_password`).
**Grup**: `openGroupForm(mode, id)`, `showGroupGrid()`, `saveGroup()`, `toggleGroupActive(id)`.
**Hak Akses**: `togglePermission(moduleId, field)` — toggle `can_access` mematikan otomatis 3 field lain (readonly = access=1, input=0).
**Migrasi**: `runVendorMigration()` (via `VendorMigrationService` — `tm2old.mst_vendor*` → `cust_customer`/`sup_supplier`), `runEmployeeMigration()` (via `EmployeeMigrationService` — `tm2old.emp` → `emp_employee`+`org_department`/`org_position`), `runEmployeeLoginProvision()` (via `EmployeeLoginProvisionService` — buat login untuk karyawan aktif hasil migrasi).
Umum: `setPanel(panel)`, `logout()`.

## Tabel & Model yang Disentuh
- `App\Models\Sys\SecUser` — `sec_user` (tm2sys).
- `App\Models\Sys\SecGroup` — `sec_group` (tm2sys).
- `App\Models\Sys\SecGroupPermission` — matriks izin per grup+modul (tm2sys).
- `App\Models\Sys\SecModule` — daftar modul hierarkis (tm2sys).
- `App\Services\Security\PasswordResetService` — reset password.
- `App\Services\Migration\VendorMigrationService` — baca `tm2old.mst_vendor`/`mst_vendor_bank`/`mst_vendor_customer_category`/`mst_vendor_supplier_category`, tulis `cust_customer`/`sup_supplier` (tm2mst) + `geo_city` matching.
- `App\Services\Migration\EmployeeMigrationService` — baca `tm2old.emp`/`emp_position`/`emp_identities`/`org_department` legacy, tulis `emp_employee`/`org_department`/`org_position` (tm2mst).
- `App\Services\Migration\EmployeeLoginProvisionService` — buat `sec_user` dari `emp_employee` aktif.
Panel Migrasi menyentuh 2 database berbeda (old → mst) — dilakukan lewat Service Laravel (baca-tulis terpisah per koneksi), bukan JOIN lintas-DB.

## Alur / Business Logic Penting
- Admin tidak bisa menonaktifkan akun sendiri (`toggleUserActive` cek `Auth::id()`) — mencegah kunci-diri-sendiri keluar.
- Panel Hak Akses: readonly = `can_access=1` DAN `can_input=0` (bukan flag terpisah); mematikan `can_access` otomatis mematikan `can_input`/`can_approve`/`can_export`.
- `groupOptions()` (tanpa filter search) dipisah dari `groups()` (search-filtered) supaya dropdown pemilih grup di form Pengguna/panel Hak Akses tidak ikut kosong saat user sedang mengetik di kotak pencarian grid Grup.
- Password baru hasil reset ditampilkan SEKALI via `session()->flash()` (bukan disimpan di properti Livewire) — supaya tidak ikut ter-dehydrate ke request berikutnya.
- Migrasi karyawan: departemen/jabatan asli nyaris tidak tercatat di data legacy (cuma 1 dari 485 baris) — semua karyawan hasil migrasi masuk bucket sementara "Migrasi Legacy - Belum Dipetakan", wajib di-assign ulang manual.
- Migrasi vendor & karyawan idempoten — aman dijalankan berulang (update, bukan duplikasi).
- Class root `.sp-shell`/`.sp-content` (bukan `.shell`/`.content` polos) — sengaja dihindari karena nama polos itu sudah dipakai class global outer app shell yang punya `overflow-hidden`+`h-screen`, collision ini pernah memotong tabel Pengguna tanpa bisa discroll (ditemukan 2026-08-30).

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode untuk layar ini.
