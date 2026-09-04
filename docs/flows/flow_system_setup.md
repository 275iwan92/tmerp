---
name: flow_system_setup
description: Alur lintas-layar bucket "System & Setup" -- registrasi & login, keamanan akun, administrasi Security/Configuration, Help Center/tiket, notifikasi, dan Portal Karyawan self-service.
---

# Alur System & Setup

**Modul**: System & Setup
**Prefix route**: tidak ada 1 prefix tunggal -- tersebar di root (`/login`, `/register`, dst), `/security`, `/setup/*`, `/bantuan`, `/notifications`, `/self-service`

## Ringkasan
Bucket "System & Setup" bukan 1 modul bisnis tunggal, melainkan kumpulan layar lintas-fungsi yang menopang SELURUH aplikasi: (1) **Auth** -- masuk/daftar/lupa-password/paksa-ganti-password/setup pertanyaan keamanan; (2) **Security** (admin-only) -- kelola akun, grup, hak akses per modul, migrasi data legacy ScriptCase; (3) **Configuration** (admin-only) -- identitas aplikasi, bahasa & terjemahan, semua konstanta sistem; (4) **Help & Support** -- Help Center gabungan (panduan + tiket) untuk semua user, dan grid admin pengelola tiket; (5) **Notification Center** -- notifikasi pribadi tiap user; (6) **Self-Service** -- Portal Karyawan untuk data pribadi & pengajuan HR. Semua layar ini berada di koneksi `sys` (tm2sys) kecuali Self-Service yang sebagian besar membaca/menulis `mst` (tm2mst, tabel `emp_*`).

## Sub-area -> Layar -> Aktor

| Sub-area | Layar | Route | Aktor |
|---|---|---|---|
| Auth | Login | `login` | Publik (belum login) |
| Auth | Registrasi | `register` | Publik (calon customer/supplier/internal) |
| Auth | Lupa Password | `password.request` | Publik (lupa password) |
| Auth | Paksa Ganti Password | `password.change` | User login dengan password default |
| Auth | Setup Pertanyaan Keamanan | `security.setup` | User login (belum setup 4 pertanyaan) |
| Security (admin) | Pengguna / Grup / Hak Akses / Migrasi Legacy | `security.index` | Admin (`is_admin`) |
| Configuration (admin) | Identitas Aplikasi + Bahasa + Konstanta | `setup.configuration` | Admin (`is_admin`) |
| Support (admin) | Support Tickets | `setup.support-tickets` | Admin / support staff |
| Help Center | Bantuan (Tiket/UI/Modul) | `help.index` | Semua user login |
| Notifikasi | Notification Center | `notifications.index` | Semua user login (data milik sendiri) |
| Self-Service | Portal Karyawan | `self-service.index` | Karyawan login yang tertaut `employee_id` |
| App shell | Main Menu | `menu` | Semua user login (titik masuk semua modul) |

## Alur Detail

### 1. Onboarding akun (Auth)
1. **Registrasi** (`register`) -- calon user (customer/supplier/internal) isi form, verifikasi email (demo, cek format) & WhatsApp OTP (demo, terima digit apa saja), submit -> `SecUser` baru dibuat NONAKTIF (`activate=false`) + notifikasi (`SysNotification`) dikirim ke SEMUA admin dengan link ke `security.index?tab=users`.
2. **Approval admin** -- admin buka `security.index` tab Pengguna, cari akun baru (masih NONAKTIF), toggle aktifkan lewat aksi ⏻, tentukan grup lewat form Edit (assign `u_group_id`).
3. **Login pertama** (`login`) -- user pakai username (auto-derive dari email saat registrasi) + password yang dibuat sendiri saat registrasi (BUKAN password default, karena `submit()` di registrasi langsung `Hash::make($this->pass)` milik user sendiri -- beda dari akun hasil migrasi/provisioning HR yang dapat `default_password` acak).
4. **Gate `force.password`** -- jika `sec_user.must_change_password=true` (mis. akun hasil `resetPassword()`/migrasi karyawan yang dapat password acak), user dipaksa lewat `password.change` sebelum bisa akses `menu`.
5. **Setup keamanan** (`security.setup`) -- terpisah dari alur paksa-password, dipicu kondisi lain (belum ada 4 pertanyaan keamanan) -- dibaca sebagai bagian "sudah selesai" batch sebelumnya, lihat `docs/screens/screen_auth_security_setup.md` (CATATAN: file dokumentasi ini tidak ditemukan di direktori saat sesi ini berjalan, walau ditandai selesai -- lihat Gap/TBD).
6. **Lupa password** (`password.request`) -- jalur alternatif kapan saja sebelum login, verifikasi 4 pertanyaan keamanan (bukan email/link), reset ke `default_password` tersimpan.

### 2. Administrasi akses (Security, admin-only)
1. Admin login -> sidebar grup "Security" (hanya tampil untuk `is_admin`) -> `security.index`.
2. Panel **Pengguna**: CRUD akun, reset password (plaintext ditampilkan sekali via session flash), toggle aktif/nonaktif (tidak bisa nonaktifkan diri sendiri).
3. Panel **Grup**: CRUD grup + level manajemen (1-5).
4. Panel **Hak Akses Grup**: pilih grup -> matriks toggle Akses/Input/Approve/Export per modul (`sys_module`), dipakai `SecUser::visibleAppMenuCodes()` di Main Menu untuk filter menu yang tampil.
5. Panel **Migrasi Data Legacy**: tarik data `tm2old` (ScriptCase V1) -> `cust_customer`/`sup_supplier`/`emp_employee`/`org_department`/`org_position` (tm2mst) + provisioning login karyawan aktif -> `sec_user`. Idempoten, dijalankan manual sesuai kebutuhan (bukan terjadwal).

### 3. Administrasi sistem (Configuration, admin-only)
1. Admin buka `setup.configuration` (sidebar tepat di atas grup Security).
2. Section **Identitas Aplikasi**: nama/tagline/font/logo -- dipakai ulang di topbar Main Menu, halaman Login, dan panel Filament `/admin` (lewat `App\Support\Branding\Brand`, baca `sys_constant`).
3. Sub-komponen **Bahasa** (`@livewire`): kelola daftar bahasa aktif (dipakai toggle bendera 🇮🇩/🇬🇧 di Login/Registrasi/Forgot-Password) + grid terjemahan `sys_translations` per key x per bahasa.
4. Sub-komponen **Semua Konstanta Sistem** (`@livewire`): grid spreadsheet-editable SEMUA baris `sys_constant` lintas kategori (termasuk yang ditulis section Identitas Aplikasi di atas -- 1 sumber data yang sama).

### 4. Bantuan & tiket (dua arah)
1. **User** (siapa pun login) tekan F1 atau buka dropdown profil -> `help.index` tab "Tiket Bantuan" -- isi deskripsi + lampiran screenshot, `submitSupportTicket()` bikin `SysSupportTicket` baru bernomor `SPT{YY}{MM}{seq}`.
2. **Admin/support staff** buka `setup.support-tickets` (grid admin, hanya `is_admin`) -- lihat semua tiket, buka detail, balas (`SysSupportReply`) -- balasan staff pada tiket OPEN otomatis majukan status ke IN_PROGRESS.
3. **User** kembali ke `help.index` tab "Tiket Bantuan", buka tiketnya, lihat balasan staff, bisa membalas balik selama status belum DONE/CLOSED -- thread `sys_support_reply` dibaca+ditulis dari KEDUA layar (dua arah, tabel yang sama).
4. Tab "Cara Pakai Aplikasi" & "Modul" di `help.index` murni konten statis (panduan UI/UX umum + penjelasan alur 8 modul bisnis termasuk System & Setup sendiri) -- tidak terhubung ke tiket.

### 5. Notifikasi
`notifications.index` menampilkan SELURUH `SysNotification` milik user login (dari `SysNotification::forUser()`), sumbernya lintas-layar -- mis. registrasi baru (poin 1 di atas mengirim ke admin), atau notifikasi bisnis lain di modul-modul lain. Klik baris menandai terbaca DAN redirect ke `link` terkait (1 aksi gabungan). Ikon lonceng di Main Menu topbar membuka drawer ringkas; layar penuh ini untuk lihat semua + filter.

### 6. Portal Karyawan (Self-Service)
1. Karyawan login (harus punya `sec_user.employee_id` tertaut, dicek di `mount()`) -> `self-service.index`.
2. Tab **Data Saya/Keluarga/Dokumen**: semua read-only, perubahan lewat tombol "Ajukan Koreksi"/"+ Ajukan..." -> tabel `Emp*Request` (tm2mst) berstatus PENDING, menunggu approval HR (panel approval HR sendiri di luar scope layar ini).
3. Tab **Cuti/Sakit/Izin**: engine nyata SOP 002/RP/TM/VII/2026 (H+7 cuti, unpaid izin terkunci selama saldo cuti>0, auto-Alpha ke `EmpDisciplinary` pada percobaan izin ke-4+ sebulan).
4. Tab **Izin Keluar** & **Tukar Shift**: pengajuan dengan approval terpisah (atasan+HRD; rekan+PPIC).
5. Tab **Jadwal Saya** & **Kartu Pengenal**: read-only (jadwal dari batch CONFIRMED) + pengajuan foto ID baru.
6. Tab **Security**: BEDA jalur -- edit langsung `sec_user` (nama/email/telepon/password) tanpa approval, karena ini data akun login bukan data HR. Gabungan dari modal "Edit profil"+"Ubah password" yang dulu ada terpisah di dropdown profil Main Menu (topbar tetap simpan modal itu sebagai fallback khusus akun tanpa `employee_id`, yang tidak bisa akses Portal Karyawan sama sekali).

## Catatan Arsitektur Penting
- Semua layar Security/Configuration/Support Tickets (admin) berada di koneksi `sys` (tm2sys) tanpa query lintas-DB, KECUALI panel Migrasi Legacy yang baca `tm2old` dan tulis `tm2mst` lewat Service Laravel terpisah (bukan JOIN lintas-DB) -- sesuai aturan CLAUDE.md §2.
- Self-Service adalah satu-satunya layar di bucket ini yang koneksi utamanya `mst` (tm2mst, model `App\Models\Master\Emp*`) bukan `sys` -- kecuali tab Security-nya sendiri yang balik ke `sys` (`Auth::user()`/`sec_user`).
- Notifikasi (`SysNotification`) adalah mekanisme lintas-modul generik -- ditulis dari mana saja (contoh nyata: alur Registrasi di atas), dibaca lewat 1 layar terpusat (`notifications.index`) + drawer ringkas di Main Menu.
- Tiket support (`SysSupportTicket`/`SysSupportReply`) adalah pola dua-arah pertama yang memakai `sys_support_replies` secara nyata (sebelumnya ada di skema tapi tidak terpakai) -- 2 layar berbeda (`help.index` sisi user, `setup.support-tickets` sisi admin) membaca+menulis TABEL YANG SAMA, dibedakan hanya oleh scope query (`created_by_user_id` vs semua tiket).
- Grup sidebar "Security" & item "Settings" (Configuration) dipasak permanen di bagian bawah sidebar Main Menu, terpisah dari `sys_app_menu` biasa, hanya tampil untuk `is_admin` (lihat `docs/screens/screen_main_menu.md`).

## Layar terkait (dokumentasi detail per-layar)
- `docs/screens/screen_main_menu.md`
- `docs/screens/screen_auth_login.md`
- `docs/screens/screen_auth_register.md`
- `docs/screens/screen_auth_forgot_password.md`
- `docs/screens/screen_auth_password_change.md`
- `docs/screens/screen_auth_security_setup.md` (lihat Gap/TBD -- tidak ditemukan di direktori saat sesi ini)
- `docs/screens/screen_security_index.md`
- `docs/screens/screen_setup_configuration.md`
- `docs/screens/screen_setup_configuration_languages.md`
- `docs/screens/screen_setup_configuration_constants.md`
- `docs/screens/screen_setup_support_tickets.md`
- `docs/screens/screen_help_index.md`
- `docs/screens/screen_notification_index.md`
- `docs/screens/screen_self_service_index.md`

## Gap / TBD
- `docs/screens/screen_auth_security_setup.md` ditandai "sudah selesai" oleh sesi sebelumnya (konteks tugas), TAPI file ini TIDAK ADA di `docs/screens/` saat pengecekan `ls` pada sesi ini (blade file sumbernya, `resources/views/pages/auth/security-setup.blade.php`, memang ada dan route `security.setup` terdaftar di `routes/web.php`). Kemungkinan file gagal tersimpan di sesi sebelumnya atau nama file berbeda dari konvensi -- perlu ditulis ulang di sesi lain.
- Panel approval HR untuk semua jenis pengajuan Self-Service (koreksi data, keluarga, cuti, izin keluar, tukar shift, resign, foto badge) belum didokumentasikan/dibangun sebagai layar tersendiri -- disebutkan eksplisit di luar scope oleh komentar kode `self-service/index.blade.php`.
- Validasi hari libur nasional/cuti bersama pada engine Cuti/Sakit/Izin belum ada tabel referensi (hanya exclude hari Minggu) -- ditandai "DELTA" di kode.
