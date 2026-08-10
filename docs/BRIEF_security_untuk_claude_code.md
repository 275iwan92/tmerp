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
