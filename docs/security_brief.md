# BRIEF untuk Claude Code — Modul Security: Login → Main Menu

> Taruh di `docs/`. Ini konteks agar Claude Code membangun halaman login,
> forgot-password, dan main menu (general + admin) dengan benar sesuai
> struktur tabel `sec_*` di database `tm2sys` dan mockup HTML yang ada.

---

## 1. ACUAN VISUAL (mockup HTML yang sudah ada)

Bangun tampilan mengikuti mockup di `docs/mockups/`:
- `login.html` — halaman login (panel brand navy + jam berdetak, form login, i18n ID/EN, toggle password, "ingat saya", link lupa password).
- `forgot-password.html` — form email reset (pesan sukses selalu sama demi keamanan).
- `main_menu_admin.html` — menu utama versi ADMIN (ada link "⚙️ Setup", quote motivasi, grid card modul, modal edit profil & ubah password).
- `main_menu_general.html` — menu utama versi USER BIASA (sama seperti admin TAPI tanpa link Setup).
- `security.html` — halaman kelola user/group/hak akses (khusus admin).

**Aturan penting soal mockup:**
- PERTAHANKAN CSS/tema mockup (variabel `--navy` dll) — tampilan harus mirip.
- Tema warna, layout header, dropdown profil, card modul = tiru.
- JS demo di mockup (mis. `setRole()`, toggle Admin/User, hardcode nama)
  JANGAN ditiru — ganti dengan logika Laravel nyata (lihat §4).

---

## 2. STRUKTUR DATA (database tm2sys, connection 'sys')

Autentikasi & izin memakai tabel `sec_*`:

| Tabel | Isi |
|---|---|
| `sec_user` | User login. Kolom kunci: `login` (username), `pswd` (bcrypt), `name`, `email`, `active`, `is_admin` (1=superadmin bypass izin), `employee_id`/`linked_id` (tautan ke master emp_employee, lintas-DB). |
| `sec_group` | Grup/role + `mgmt_level` (1=STAFF..5=DIREKSI). |
| `sec_user_group` | User ↔ group (M:N). |
| `sec_module` | Hierarki modul (2 level: `parent_id`). Tiap modul punya `route_prefix`, `min_mgmt_level`, `icon`, `module_name`. |
| `sec_group_permission` | Izin per group per modul: `can_access`, `can_input`, `can_approve`, `can_export`. |
| `sec_login_attempt` | Audit tiap percobaan login (login, success, ip, user_agent, waktu). |

Sesi pakai `sessions` bawaan Laravel. Lupa password pakai `password_reset_tokens` bawaan Laravel.

> CATATAN: tabel `users` & `sec_session` masih ada di DB tapi AKAN dibuang
> (lihat `docs/CLEANUP_tm2sys.sql`) — JANGAN pakai keduanya. Auth = `sec_user`.

---

## 3. ALUR YANG HARUS DIBANGUN

### 3.1 Login (`login.html`)
1. User isi `login` + `pswd`, klik Masuk.
2. Server autentikasi terhadap `sec_user` (cek `active=1`, cocokkan bcrypt `pswd`).
3. Catat ke `sec_login_attempt` (berhasil ATAU gagal) dengan IP + user_agent.
4. Berhasil → redirect ke main menu. Gagal → pesan "Username atau password salah".
5. i18n ID/EN dari tabel translations (bila dibuat) atau file lang Laravel.

### 3.2 Forgot password (`forgot-password.html`)
- Kirim link reset via mekanisme Laravel (`password_reset_tokens`).
- Pesan sukses SELALU sama ("Jika email terdaftar, tautan dikirim") — jangan
  bocorkan apakah email terdaftar (keamanan).

### 3.3 Main menu (satu halaman, tampil beda per hak akses)
JANGAN buat 2 file terpisah admin vs general. Buat SATU halaman menu yang
menyaring isi berdasarkan izin user login:
- **Card modul** ditampilkan HANYA modul yang user-nya `can_access=1`
  DAN `group.mgmt_level >= module.min_mgmt_level`. (default DENY — modul tanpa
  izin tak muncul sama sekali; inilah kenapa Produksi tak lihat Inquiry).
- **Link "⚙️ Setup"** hanya muncul bila user `is_admin=1` (atau punya akses
  modul security). Ini yang membedakan tampilan admin vs general — BUKAN file
  terpisah, BUKAN toggle JS.
- Nama & role di header/dropdown diambil dari user login (jangan hardcode
  "Budi Santoso").
- Quote motivasi: ambil 1 acak dari sumber quote (tabel quotes bila dibuat).

### 3.4 Halaman Setup/Security (`security.html`) — admin only
Kelola user, group, dan izin. Izin diberikan **per modul** (bukan per form):
untuk tiap group × modul, set can_access/input/approve/export. Hierarki modul
(induk → sub, mis. Inquiry → HPP) tampil bertingkat.

---

## 4. LOGIKA IZIN (WAJIB dipahami sebelum coding)

- **Sumber izin:** `sec_group_permission` (per group per modul). User dapat izin
  lewat group-nya (`sec_user_group`). Multi-group = izin paling permisif (OR),
  `mgmt_level` = MAX dari group-group user.
- **Cek akses modul X:** ada baris permission (group user, X) `can_access=1`
  DAN `mgmt_level >= X.min_mgmt_level`. `is_admin=1` bypass semua.
- **Readonly** = `can_access=1` & `can_input=0` (bukan flag terpisah).
- **Route → modul:** petakan route Laravel ke modul via `sec_module.route_prefix`
  + middleware. Contoh: semua route `inquiry/*` dicek terhadap modul INQUIRY.
- **Menu:** render hanya modul yang lolos cek (default DENY).
- **Tombol aksi:** tampilkan tombol Approve hanya bila `can_approve=1`;
  tombol Export hanya bila `can_export=1` (export = kritikal, bawa data keluar).

---

## 5. URUTAN KERJA YANG DISARANKAN

1. Model Eloquent sec_* (connection 'sys', $table, $primaryKey, relasi).
2. Konfig auth Laravel ke `sec_user` (model User → sec_user, kolom login/pswd),
   config/auth.php. SETELAH ini jalan, baru jalankan CLEANUP_tm2sys.sql (drop
   users & sec_session).
3. Halaman login + pencatatan sec_login_attempt.
4. Middleware cek izin per modul (§4).
5. Main menu dinamis (card & Setup link tergantung izin).
6. Forgot password.
7. Halaman Setup/Security (kelola user/group/izin) — admin.

Kerjakan bertahap, tunjukkan rencana tiap tahap sebelum menulis. Jawab dalam
Bahasa Indonesia, ringkas.

---

## 6. MODEL ROLE LEVEL x BIDANG (ditambah 2026-09-02, dasar Sistem Report 4-Level)

> Detail lengkap & status implementasi ada di `docs/reportstandard.md`
> (dokumen INDUK utk topik ini) — bagian ini cuma ringkas sisi SECURITY-nya
> (bukan duplikat) supaya siapa pun baca `security_brief.md` juga tahu
> model ini ada, karena dia genuinely bagian dari desain akses/menu, bukan
> cuma fitur report berdiri sendiri.

### 6.1 2 sumbu akses, BUKAN 1

Sistem akses aplikasi ini punya **2 sumbu independen**, bukan cuma "modul
mana yang boleh diakses" (§4 di atas):

1. **Sumbu BIDANG (domain/modul)** — Sales/Purchasing/Inventory/Production/
   Finance & Accounting/HR, dst. Ini sumbu YANG SUDAH ADA (`sec_module`
   + `sec_group_permission.can_access`, §2-§4 di atas) — menentukan modul
   APA yang boleh dibuka user.
2. **Sumbu LEVEL (`sec_group.mgmt_level`, skala 1-5)** — 1=Staff/2=Supervisor/
   3=Manager/4=GM/5=Direksi (kolom sudah ada sejak awal, komentar DB-nya
   sendiri sudah define skala ini). Sumbu ini menentukan **seberapa DALAM**
   & **seberapa LUAS** (cakupan tim/departemen/perusahaan) data yang
   kelihatan di DALAM bidang yang sudah diizinkan sumbu #1, DAN — poin baru
   2026-09-02 — **MENU/item mana yang muncul**, bukan cuma data.

Skema akses = **matriks 2 dimensi (bidang x level)**, bukan garis lurus.
Contoh: Manager Purchasing (bidang=Purchasing, level=3) lihat ringkasan
SELURUH transaksi Purchasing timnya, TAPI TIDAK lihat apa pun di bidang
Sales — sementara Direksi (level=5) lintasi SEMUA bidang sekaligus. Detail
per-bidang: `docs/reportstandard.md` §2.3 (tabel matrix Sales/Purchasing/
Inventory/Production/Finance/HR x Level 1-4).

### 6.2 Menu BEDA per level (keputusan user 2026-09-02) — PERLUASAN dari yang sudah ada

`sec_module.min_mgmt_level` **SUDAH ADA & SUDAH DIPAKAI** (§4: "Cek akses
modul X: ... DAN mgmt_level >= X.min_mgmt_level", diimplementasi di
`SecUser::accessibleModules()`) — TAPI ini gating di level **MODUL**
(seluruh Sales nongol/hilang sekaligus), bukan per-ITEM di dalam modul yang
sudah bisa diakses.

**Kebutuhan baru**: DI DALAM 1 modul yang sudah bisa diakses (mis. Sales),
item menu yang muncul harus BEDA per level — Data Entry cuma lihat
"Input CO"/"Input Quotation", Manager tambahan lihat "Laporan Sales"
(ringkasan departemen), Top Management tambahan lihat entry ke laporan
eksekutif lintas-bidang. **Ini BELUM ada mekanismenya** — `sys_app_menu`
(tabel menu AKTUAL yang dipakai `main-menu.blade.php`, beda dari
`sec_module` yang lebih tua) **belum punya kolom `min_mgmt_level` sendiri**
per item, cuma warisan gate modul induknya. **Saat mulai implementasi
report level**: kemungkinan besar butuh tambah kolom serupa
(`sys_app_menu.min_mgmt_level`, nullable = ikut modul induk apa adanya) +
sesuaikan `SecUser::visibleAppMenuCodes()`/`menuTree()` di
`main-menu.blade.php` supaya filter item, bukan cuma modul — TAPI ini
keputusan skema yang perlu dikonfirmasi user dulu (perubahan ke tabel
menu inti, dampaknya ke SEMUA menu existing, bukan cuma report), jangan
diimplementasi sepihak.

### 6.3 Kode otorisasi Supervisor — juga bagian model security ini

Level 2 (Supervisor) di §6.1 punya kemampuan KHUSUS yang levelnya lain
tidak punya: buka layar data-entry milik bawahan pakai kode sementara
sekali-pakai (bukan cuma "lihat data lebih luas" spt level 3-4). Detail
mekanisme (masih desain awal, banyak keputusan detail belum final):
`docs/reportstandard.md` §3.
