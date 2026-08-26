# TIER 1 — OPENING APP · SYSTEM FLOW & PROCESS MATRIX
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 1 — Opening App · System Flow & Process |
| **Versi** | 1.4 |
| **Riwayat** | v1.0 awal · v1.1 sinkron `tm2sys` (registrasi/7-modul/tema/dedup) · v1.2 `sys_user_menu` (dua tabel menu) · **v1.3 `required_object` terisi di level MODULE** (7 modul → kode sendiri) + ikon emoji → filter izin aktif granularitas modul (inheritance item→modul) · **v1.4 Tier 3 (DDL&seed) & Tier 4 (spec 5 mockup) dibuat** |
| **Riwayat** | v1.0 cakupan awal · v1.1 sinkron revisi `tm2sys` (registrasi, `sec_module` 7-modul, tema, dedup audit) · **v1.2 tabel `sys_user_menu`** — menu profil (dropdown avatar) kini data-driven, memperjelas arsitektur **dua tabel sumber menu** |
| **Status** | AKTIF — acuan alur untuk Tier 2/3/4 |
| **Pembaca** | Sistem Analis · Developer (Backend/Frontend) · DBA · Admin Aplikasi |
| **Aktor** | Prospek (Customer/Supplier) · Internal User · Admin Aplikasi · Direksi · Sistem (server) |
| **Goal** | Menetapkan alur & aturan "pintu masuk" aplikasi: identitas (login/daftar/reset), rangka navigasi (mega menu, sidebar, tab MDI, command palette, user menu, i18n, tema), dan administrasi keamanan (user, grup, modul, hak akses, bahasa, tema) — fondasi seluruh modul bisnis. |
| **Database** | **`tm2sys`** (Security & Notification) — 27 tabel; inti: `sec_*` (izin) + `sys_*` (tampilan/utilitas). **Dua tabel menu:** `sys_app_menu` (navigasi) + `sys_user_menu` (menu profil) |
| **Output** | Sesi terautentikasi + menu terfilter hak akses → titik masuk ke seluruh modul (Sales/Inquiry dst.) |

---

## SECTION 2 — SCOPE & RUANG LINGKUP

### 2.1 Modul yang dicakup
Modul **Opening App** = lapisan yang dilewati **setiap** pengguna sebelum & selama memakai aplikasi. Bukan modul bisnis, tapi **prasyarat** semua modul (Tier 0: "Master & Security dulu"). Selaras Modul **09 — Security & Notification**.

### 2.2 Fitur di dalam scope

> **Status:** ✅ konsep matang + skema siap · 🆕 rancangan baru/perlu pelengkap.
> **Mockup:** ✔ ada di HTML · ◑ sebagian · ✗ belum.

| # | Fitur | Status | Mockup | Ringkas | Tabel utama |
|---|---|:--:|:--:|---|---|
| F1 | Login (username/email + password) | ✅ | ✔ | validasi, show/hide, alert, catat percobaan | `sec_user`, `sec_login_attempt` |
| F2 | Audit percobaan login | ✅ | ✔ | tiap attempt (sukses/gagal) server-side | `sec_login_attempt` |
| F3 | Registrasi mandiri (self-register) | ✅ | ✔ | 3 tipe Customer/Supplier/Internal; **pending** | `sec_user` (kolom registrasi) |
| F4 | Verifikasi email | ✅ | ✔ | cek keabsahan + tautan/kode | `sec_user.email_verified*` |
| F5 | Verifikasi WhatsApp (OTP) | ✅ | ✔ | OTP ke WA aktif = bukti nomor valid | `sec_user.wa_verified*` |
| F6 | Approval akun oleh admin | ✅ | ◑ | `approval_status`; admin set grup saat approve | `sec_user`, `sec_user_group` |
| F7 | Lupa & reset password | ✅ | ✔ | pesan seragam (anti user-enumeration) | `password_reset_tokens` |
| F8 | Mega menu modul (top bar) | ✅ | ✔ | ikon+nama modul (`menu_type='module'`) | `sys_app_menu` |
| F9 | Sidebar kontekstual | ✅ | ✔ | group→item modul terpilih | `sys_app_menu` |
| F10 | Tab MDI (multi-dokumen) | ✅ | ✔ | tiap item buka tab; persisten lintas modul | — (state UI) |
| F11 | Command palette (Ctrl+K) | ✅ | ✔ | cari cepat item menu (flatten) | `sys_app_menu` |
| F12 | Target eksekusi menu | ✅ | ✔ | `target_kind` app/php/html/url → cara render | `sys_app_menu` |
| F13 | i18n (ID/EN) | ✅ | ✔ | switch bahasa; teks UI per kunci | `sys_languages`, `sys_translations` |
| F14 | Hero image + quote acak | 🆕 | ✔ | quote ✅; **hero belum bertabel** (§6 B-OA5) | `sys_quotes` (+`sys_login_hero`*) |
| F15 | User menu (avatar) | ✅ | ✔ | **data-driven**; profil/password/setup/aktivitas/bahasa/tema/logout via `action_type` | `sys_user_menu` |
| F16 | Notifikasi (lonceng) | ✅ | ✔ | per-user / broadcast; tipe info/success/warning/error | `sys_notifications` |
| F17 | Chat internal | ✅ | ◑ | percakapan 1:1 & grup | `sys_chat_*` |
| F18 | Bantuan & dukungan (tiket) | ✅ | ◑ | tiket bug/feature/question + balasan | `sys_support_tickets/_replies` |
| F19 | Asisten AI (buka menu by intent) | 🆕 | ◑ | ketik maksud → petakan ke item menu | `sys_app_menu` |
| F20 | Admin: kelola User | ✅ | ✔ | tambah/edit/nonaktif; assign grup & status | `sec_user`, `sec_user_group` |
| F21 | Admin: kelola Grup | ✅ | ✔ | grup = wadah izin; `mgmt_level` 1–5 | `sec_group` |
| F22 | Admin: Registry Modul | ✅ | ✔ | unit izin (bisnis), dasar hak akses | `sec_module` |
| F23 | Admin: Hak Akses Grup | ✅ | ✔ | per grup × modul: access/input/approve/export | `sec_group_permission` |
| F24 | Admin: Bahasa | ✅ | ✔ | default + daftar bahasa aktif | `sys_languages`, `sys_setting` |
| F25 | Admin: Tema | ✅ | ✔ | preset warna; aktif per-user & default app | `sys_theme`, `sys_setting`, `sec_user.theme_code` |
| F26 | Collapse sidebar / topbar / mini | ✅ | ✔ | mode tampilan hemat ruang | — (state UI) |
| F27 | MFA (opsional) | 🆕 | ✗ | secret MFA sudah bertempat di skema | `sec_user.mfa` |

*`sys_login_hero` dirujuk mockup tapi **belum ada** di `tm2sys` → gap B-OA5.

### 2.3 Di luar scope (dokumen lain)
Isi modul bisnis — hanya jadi **tujuan navigasi**. Notifikasi/chat/tiket sebagai *fitur* dicakup; *konten*-nya milik modul asal. Data orang (`emp_*`) ada di `tm2mst` — `sec_user` menaut via FK logis.

### 2.4 Klasifikasi Data (visibilitas)
- **Publik/pra-login:** hero, quote, form login/daftar/reset. Tak ada data sensitif.
- **Anti-enumeration:** pesan lupa-password & login gagal **seragam**.
- **Rahasia admin:** matriks hak akses, `mgmt_level`, `is_admin`, hash password.
- **`can_export` = kritikal:** menandai grup yang boleh membawa data keluar (audit ketat).

---

## SECTION 3 — ALUR PROSES & ALUR INFORMASI

### 3.1 Peta besar (3 sub-alur)
```text
A. IDENTITAS  : Daftar → (verifikasi email+WA) → Approval admin → Login → Sesi
B. SHELL      : Sesi → Render menu (terfilter izin) → Buka item jadi tab → Kerja
C. ADMIN      : Admin kelola User/Grup/Modul/Izin/Bahasa/Tema (memberi makan A & B)
```

### 3.2 Alur A — Identitas

**A1. Registrasi mandiri (`register.html`)**
```text
Pilih tipe: Customer / Supplier / Internal → set reg_type
  → Nama, Email (VERIFIKASI keabsahan + tautan) → email_verified=1 → Username auto dari email (server jamin unik)
  → No. WhatsApp (KIRIM OTP → verifikasi) → wa_verified=1
  → id_type: NIK 16 digit (customer/supplier) | NRP bebas (internal) → id_number
  → Password (≥8, huruf+angka) + konfirmasi
  → SUBMIT → INSERT sec_user(approval_status='pending', TANPA grup) + notifikasi admin
  → Layar "menunggu persetujuan admin"
```

**A2. Approval admin** — admin buka akun `approval_status='pending'` → tetapkan **grup** (`sec_user_group`) → set `approval_status='approved'` + `is_active=1`. Tolak = `approval_status='rejected'`. Tanpa grup = tak ada izin (default DENY).

**A3. Login (`login.html`)**
```text
Isi login + password → validasi non-kosong (else alert seragam)
  → server: cek sec_user (is_active=1, approval_status='approved') + verify bcrypt(pswd)
  → CATAT sec_login_attempt(login, success, ip, user_agent, attempted_at)
  → sukses → sesi → muat theme_code user → redirect main_menu
  → gagal → pesan seragam · (opsional) MFA bila sec_user.mfa terisi
```

**A4. Lupa/Reset (`forgot-password.html`)**
```text
Input email → SELALU "jika terdaftar, tautan dikirim" (anti-enumeration)
  → bila terdaftar: token (password_reset_tokens) + kirim tautan
  → reset: set password baru → update pswd + pswd_last_updated → hapus token (single-use)
```

### 3.3 Alur B — App Shell (`main_menu_v2.html`)

**B1. Sumber menu** = `sys_app_menu`:
```text
SELECT id,parent_id,code,label,trans_key,icon,menu_type,object_type,target,target_kind,sort_order,required_object
FROM sys_app_menu WHERE is_active=1 ORDER BY parent_id, sort_order;
  → DIFILTER hak akses (required_object → sec_module → sec_group_permission)
```

**B2. Render 3 permukaan dari 1 sumber:**
| Permukaan | Ambil | Perilaku |
|---|---|---|
| Mega menu (top) | `menu_type='module'` | pilih modul → set sidebar |
| Sidebar | `group`+`item` anak modul aktif | leaf = buka tab |
| Command palette | semua `item` (flatten) | Ctrl+K, cari label+modul |

**B3. Buka item → tab (MDI)** sesuai `target_kind`:
```text
app  → komponen Livewire internal (produksi)
html → <iframe src="target">   (mis. mockup inquiry_main_hub.html)
php  → placeholder mock (transisi)
url  → link eksternal
```
Tab persisten lintas modul; tutup → aktifkan tab terakhir / Home.

**B4. User menu (avatar)** = **data-driven dari `sys_user_menu`** (bukan hardcode JS). Tiap item: `section` (header kelompok) · `action_type` menentukan perilaku klik · `action_target` sasaran · `badge_source` angka badge · `is_danger`/`admin_only`. Seed: Chat, Notifikasi, Asisten AI, Edit profil, Ubah password, Setup, Aktivitas saya, Bantuan, Bahasa, Keluar.

**B5. Utilitas shell:** clock, hero+quote, i18n toggle, tema (var CSS dari `sys_theme`), collapse sidebar/topbar/mini.

### 3.5 Arsitektur Menu — DUA tabel sumber (fokus)
Semua menu **data-driven**; dua tabel dengan peran berbeda:

| Tabel | Peran | Permukaan | Hierarki | Eksekusi | Filter izin |
|---|---|---|---|---|---|
| **`sys_app_menu`** | Navigasi modul bisnis | mega menu · sidebar · command palette | `module→group→item` | `target`+`target_kind` (app/php/html/url) | via `required_object`→`sec_module` |
| **`sys_user_menu`** | Menu profil/akun | dropdown avatar | `section→item` (flat) | `action_type`+`action_target` | `admin_only` (tetap dicek server) |

**`sys_user_menu.action_type`** (perilaku klik):
| action_type | Arti | action_target contoh |
|---|---|---|
| `drawer` | buka panel geser | `chatDrawer`, `notifDrawer`, `aiDrawer` |
| `modal` | buka pop-up | `mEdit`, `mPass` |
| `menu` | buka objek di `sys_app_menu` | code mis. `SET_USER` |
| `url` | arahkan ke halaman/route | `aktivitas.php`, `support.php` |
| `lang` | ganti bahasa | (NULL) |
| `logout` | keluar | `login.html` |

- `badge_source`: `chat` (dari `sys_chat_*`) · `notif` (dari `sys_notifications`) · NULL.
- `is_danger=1` → gaya menonjol (mis. Keluar). `admin_only=1` → hanya tampil untuk admin (mis. Setup), **wajib tetap dicek izin di server** (UI bukan pengaman).
- **Konsistensi:** `action_type='menu'` menautkan menu profil ke item navigasi (mis. Setup → objek security) → dua tabel tetap terhubung.

### 3.4 Alur C — Administrasi Keamanan (`security.html`)
Tab: **Pengguna · Grup · Objek/Modul · Hak Akses Grup · Bahasa · Tema**. Otorisasi di §4.

---

## SECTION 4 — ARSITEKTUR OTORISASI (DUA LAYER)

**Prinsip:** *tampilan* dan *izin* dipisah. Sejak revisi ini, `sec_module.module_code` **dibuat identik** dengan kode modul `sys_app_menu` (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) → sinkron by-code.

```text
LAYER TAMPILAN                         LAYER IZIN (penegakan)
sys_app_menu                           sec_module (unit izin, min_mgmt_level)
  code/label/icon/target/target_kind      └─ sec_group_permission (group × module)
  menu_type: module/group/item                 can_access | can_input | can_approve | can_export
  required_object ───────────────►  sec_module.module_code   (DEFAULT DENY: tak ada baris = tak boleh)
                                          ▲
                                     sec_group (mgmt_level 1..5, is_active)
                                          ▲  M:N
                                     sec_user_group ◄── sec_user (is_admin bypass)
```

**Resolusi izin efektif (server, tiap request):**
```text
1. is_admin=1 → BYPASS semua (superadmin)
2. Kumpulkan grup user (sec_user_group)
3. Izin efektif = UNION (MAX per flag) izin semua grup
4. Item menu X: required_object → sec_module → cek can_access
     + mgmt_level user ≥ sec_module.min_mgmt_level
5. can_input=readwrite|0=readonly · can_approve=tombol approve · can_export=izin export
```

**Aturan kunci:**
- **Default DENY** — modul tak muncul bila tak ada baris izin.
- **Union antar-grup** — izin paling permisif menang.
- **`min_mgmt_level`** — gerbang kedua data sensitif (mis. FINACC=3, SETUP=5).
- **`required_object`** menunjuk `module_code`. **Kini terisi di 7 baris `module`** (SALES→`SALES`, dst.); `group`/`item` masih NULL → dipakai **inheritance**: item/group mewarisi `required_object` modul induknya. Filter izin **sudah aktif di granularitas modul**. Sisa: seed `sec_group_permission` masih kosong → non-admin belum punya grant (gap B-OA2).

---

## SECTION 5 — ATURAN BISNIS PER AREA

### 5.1 User & identitas
- Username **immutable** & unik (`uq_user_login`), auto dari local-part email saat daftar.
- Password: bcrypt; `pswd_last_updated` dicatat; min ≥8 huruf+angka.
- **Lifecycle pendaftaran** dikendalikan `approval_status` (pending→approved/rejected); **enable/disable** operasional oleh flag aktif (lihat B-OA7).
- `reg_type` = jejak asal self-registration; `linked_type`/`linked_id` = tautan entitas master aktual (Internal→`emp_employee`, Customer→`cust_customer`, Supplier→`sup_*`). FK **logis lintas-DB**, dijaga aplikasi (Tier 0).
- `id_type`(nik/nrp)+`id_number` menyimpan identitas dari registrasi.
- Nonaktif = set flag aktif=0, **tidak dihapus** (jejak audit).

### 5.2 Grup & level manajemen
- `mgmt_level`: 1 STAFF · 2 SUPERVISOR · 3 MANAGER · 4 GM · 5 DIREKSI. Seed: SALES_STAFF(1), SALES_SPV(2), DIREKSI(5), PRODUKSI(1).
- Grup = satu-satunya unit pemberian izin. User tak pernah diberi izin langsung.

### 5.3 Menu dinamis
- Menu **data-driven** → admin ubah susunan via `sys_app_menu` tanpa deploy.
- `target_kind='html'` menjembatani **mockup HTML** ke shell nyata (migrasi bertahap html→app).

### 5.4 i18n & Tema
- `sys_languages` (PK `code`, `is_default`) induk `sys_translations` (`lang_code`+`trans_key` unik). `sys_app_menu.trans_key` → label menu ikut diterjemahkan.
- **Tema:** preset `sys_theme` (navy default, emerald, indigo, plum, slate, teal — `primary_color`+`sidebar_color`). Aktif per-user = `sec_user.theme_code`; default aplikasi = `sys_setting.active_theme`. Fallback: user→app default.

### 5.5 Notifikasi
- `user_id=NULL` → **broadcast**; berisi → per-user. `link`=tujuan klik. `type` mewarnai badge.

### 5.6 Audit login & anti-abuse
- Tiap percobaan → 1 baris `sec_login_attempt` (kanonik tunggal). Indeks `(login, attempted_at)` untuk deteksi brute-force (lockout di Tier 2).

### 5.7 Pengaturan aplikasi
- `sys_setting` key-value: `active_theme`, `default_language`, `app_version`. Fallback level-aplikasi.

### 5.8 Menu profil (`sys_user_menu`)
- **Data-driven** — admin ubah item/urutan/aktif tanpa deploy (paralel dengan `sys_app_menu`).
- `code` stabil dipakai kode aplikasi (mis. `UM_CHAT`); `uq_umenu_code` menjamin unik.
- `admin_only` **hanya** menyembunyikan di UI; otorisasi tetap ditegakkan server (mis. Setup → cek izin modul SETUP `min_mgmt_level=5`).
- Badge (chat/notif) dihitung real-time dari tabel sumber, bukan disimpan di sini.

---

## SECTION 6 — NOTICE: GAP, DUPLIKASI & KEPUTUSAN

### 6.1 Sudah ditetapkan / DIRESOLUSI di revisi ini
- ✅ **Registrasi** — kolom lengkap di `sec_user` (`reg_type`, `id_type`, `id_number`, `email_verified*`, `wa_verified*`, `approval_status`). *(dulu B-OA1)*
- ✅ **`sec_module` selaras 7-modul** — `module_code` identik kode `sys_app_menu`. *(dulu B-OA2 bagian taksonomi)*
- ✅ **Tema bertabel** — `sys_theme` + `sys_setting` + `sec_user.theme_code`. *(dulu B-OA4)*
- ✅ **Audit login tunggal** — `sys_login_attempts` di-drop; `sec_login_attempt` kanonik. *(dulu B-OA3)*
- ✅ Dua-layer menu/izin, default DENY + union, anti-enumeration.

### 6.2 Blocker / keputusan menggantung (+saran)
- **B-OA2 (sisa) — Grant izin belum di-seed.** ✅ `required_object` **terisi di level module** (filter modul aktif via inheritance item→modul). ❌ `sec_group_permission` **masih kosong (0 baris)** → dengan default DENY, **hanya `is_admin` yang bisa akses**; grup biasa belum melihat modul apa pun. **Saran sisa:** (a) seed `sec_group_permission` per grup (mis. SALES_STAFF: `can_access` SALES/INV, `can_input` SALES; DIREKSI: akses luas + `can_approve`); (b) isi `required_object` level item **hanya** untuk pengecualian (item lebih ketat dari modulnya); (c) wiring "inquiry ke main menu" tinggal ubah `target_kind` item inquiry → `html` + arahkan ke mockup (`inquiry_main_hub.html`).
- **B-OA12 — Seed user provisioned ber-`approval_status='pending'`.** Demo `admin` (Budi) & `rina` di-seed `pending`, padahal gerbang login butuh `approved` (§Tier 2 3.1). User yang dibuat admin (bukan self-register) mestinya langsung `approved`. **Saran:** set `approval_status='approved'` untuk akun provisioned, ATAU gerbang login = `approval_status='approved' OR is_admin=1`. Kalau tidak, admin terkunci.
- **B-OA5 — `sys_login_hero` absen.** Hero masih dirujuk mockup. **Saran:** tabel (`image_url`, `caption`, `sort_order`, `is_active`). Kecil, aman.
- **B-OA7 — `active` vs `is_active` dobel** (skema sendiri menandai). **Saran:** pakai **`is_active`** sebagai kanonik enable/disable; pensiunkan `active` (isi mengikuti `is_active` selama transisi, lalu drop di Tier 3).
- **B-OA8 — `reg_type` overlap `linked_type`.** **Saran:** `reg_type` = **informasional/immutable** (asal pendaftaran); `linked_type`+`linked_id` = **otoritatif** (tautan master). Boleh koeksis dengan definisi ini; jangan pakai `reg_type` untuk logika izin.
- **B-OA9 — Tiga konsep status** (`approval_status`, `active`, `is_active`). **Saran (gabung B-OA7):** `approval_status` = lifecycle pendaftaran; `is_active` = enable/disable operasional; retire `active`. Login butuh `approval_status='approved' AND is_active=1`.
- **B-OA11 — Mockup masih hardcode menu profil.** `main_menu_v2.html` `fillUserMenu()` menanam item secara statis; skema kini punya `sys_user_menu`. **Saran:** ganti render dari `SELECT * FROM sys_user_menu WHERE is_active=1 ORDER BY sort_order` (difilter `admin_only`), analog dengan `sys_app_menu`. Kecil, sejalan prinsip data-driven.
- **B-OA10 — Rahasia costing (Sisi A) tak ter-gate di level modul.** `sec_module` tak lagi punya sub-modul HPP/PROOF; `SALES.min_mgmt_level=1`. Padahal HPP/margin = Direksi-only (Tier 1 Inquiry §2.5). **Saran:** tambah sub-modul `SALES_HPP` (`min_mgmt_level=5`) sebagai unit izin costing, ATAU tegakkan redaksi harga di level field/komponen (Sisi A/B) — putuskan di Tier 2/3.

### 6.3 Akan dibahas di tier lain
- **Tier 2:** state machine registrasi (`approval_status`), pseudocode resolusi izin, lockout brute-force, alur reset token, lifecycle tiket, logging admin.
- **Tier 3:** DDL pelengkap (`sys_login_hero`; retire `active`; sub-modul `SALES_HPP` bila dipilih), **seed `required_object` + `sec_group_permission`**, tabel OTP registrasi bila OTP dipersistkan.
- **Tier 4:** state UI tiap tab security, penanda readonly (`can_input=0`), sembunyikan tombol approve/export sesuai izin, wiring `target_kind='html'`→mockup.

---

## SECTION 7 — ROLE MATRIX

| Aksi | Prospek | Internal User | Admin App | Direksi | Sistem |
|---|:--:|:--:|:--:|:--:|:--:|
| Registrasi mandiri | **C** | C | – | – | – |
| Verifikasi email/WA (OTP) | **C** | C | – | – | R |
| Approve akun + assign grup | – | – | **A** | R | – |
| Login | **C** | **C** | C | C | R (catat attempt) |
| Reset password sendiri | C | C | C | C | R (token) |
| Buka modul (sesuai izin) | – | **C** | C | C | R (filter) |
| Kelola User | – | – | **C** | R | – |
| Kelola Grup + mgmt_level | – | – | **C** | A | – |
| Registry Modul (`sec_module`) | – | – | **C** | R | – |
| Set Hak Akses Grup | – | – | **C** | **A** | – |
| Set `can_export` (kritikal) | – | – | C | **A** | – |
| Kelola Bahasa/Terjemahan | – | – | **C** | – | – |
| Kelola Tema | – | – | **C** | – | – |
| Pilih tema sendiri | C | **C** | C | C | – |
| Kirim notifikasi broadcast | – | – | **C** | C | R |
| Buat tiket dukungan | C | **C** | R | – | – |

C=Create · A=Approve · R=Review/Support · –=tidak berlaku

---
> **Dokumen terkait:** Tier 2 (Logika) · **Tier 3** (DDL & script seed penutup gap B-OA2/5/7/10/12) · **Tier 4** (spec mockup: login/forgot/register/main_menu/security).
