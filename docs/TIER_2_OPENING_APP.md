# TIER 2 — OPENING APP · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 2 — Opening App · Business Logic & Rules |
| **Versi** | 1.4 (struktur 3 bagian: Alur · Special Case · State/Form/Button) |
| **Riwayat** | v1.0 · v1.1 sinkron `tm2sys` · v1.2 render `sys_user_menu` · **v1.3 `required_object` level-module terisi** → filter modul aktif + aturan *inheritance* item→modul; catatan gerbang login vs seed pending |
| **Riwayat** | v1.0 awal · v1.1 sinkron revisi `tm2sys` (registrasi, 7-modul, tema, dedup audit) · **v1.2 render menu profil dari `sys_user_menu`** (dispatch `action_type`); arsitektur dua tabel menu |
| **Basis** | Tier 1 Opening App v1.2 (fitur F1–F27, dua tabel menu §3.5, gap B-OA2/5/7/8/9/10/11) |
| **Pembaca** | Sistem Analis · Developer (Backend/Frontend) · DBA |
| **Goal** | Menerjemahkan Tier 1 jadi logika presisi. **A** alur normal, **B** kasus khusus, **C** state/form/button admin. |
| **Database** | `tm2sys` |

### Cara membaca
```text
BAGIAN A — ALUR (HAPPY PATH)          → "cara kerja normal masuk & navigasi"
BAGIAN B — SPECIAL CASE (KONDISIONAL) → dibaca saat ketemu kasus tertentu
BAGIAN C — STATE, FORM & BUTTON       → saat membangun layar admin/security
```

---

## SECTION 2 — SCOPE LOGIKA

| Bag | Fokus | Isi |
|---|---|---|
| **A** | Alur | login+sesi, catat attempt, render menu terfilter, resolusi izin, buka tab per `target_kind`, muat tema |
| **B** | Special case | registrasi+verifikasi+approval, reset token, lockout, MFA, notif broadcast, chat/tiket, semantik status |
| **C** | State/Form/Button | CRUD user/grup/modul, matriks izin, i18n, tema, state layar, logging admin |

---

# ══════════════════════════════════════════════════════
# BAGIAN A — LOGIKA ALUR (HAPPY PATH)
# ══════════════════════════════════════════════════════

## SECTION 3 — LOGIN, SESI & TEMA

### 3.1 Validasi & autentikasi
```text
INPUT login, password (client: non-kosong → else alert seragam err_empty)
SERVER:
  u ← SELECT * FROM sec_user WHERE (login=:x OR email=:x) LIMIT 1
  ok ← u AND u.is_active=1 AND u.approval_status='approved' AND bcrypt_verify(password, u.pswd)
  INSERT sec_login_attempt(login=:x, success=ok, ip, user_agent, attempted_at=NOW())
  IF NOT ok → return err_wrong (SERAGAM: tak beda "user tak ada" vs "password salah")
  IF u.mfa IS NOT NULL → challenge MFA (§B S8)
  ELSE → buat sesi, regenerate session id, muat tema, redirect main_menu
```

### 3.2 Muat tema saat sesi
```text
theme ← u.theme_code ?? sys_setting['active_theme'] ?? 'navy'
row   ← sys_theme[theme]  → set var CSS (--navy=primary_color, sidebar=sidebar_color)
lang  ← sesi ?? sys_setting['default_language'] ?? sys_languages(is_default=1)
```

### 3.3 Aturan
- Pesan gagal **seragam** (anti-enumeration).
- `sec_login_attempt` diisi untuk sukses & gagal.
- `is_admin=1` **tidak** melewati autentikasi — hanya **otorisasi** (§5).
- Syarat login = `is_active=1 AND approval_status='approved'` (lihat semantik status §10). ⚠️ **Seed provisioned (`admin`/`rina`) ber-`approval_status='pending'`** → terkunci oleh gerbang ini. Perbaiki seed jadi `approved`, atau longgarkan gerbang: `approved OR is_admin=1` (B-OA12).

## SECTION 4 — RENDER MENU (TERFILTER IZIN)

### 4.1 Pipeline
```text
1. rows ← SELECT ... FROM sys_app_menu WHERE is_active=1 ORDER BY parent_id, sort_order
2. allowed ← resolvePermissions(user)      // §5
3. visible ← rows.filter(r =>
       r.menu_type IN('module','group') ? (punya ≥1 anak lolos) :
       allowed.canOpen( effectiveObject(r) ))     // effectiveObject = r.required_object ?? ancestor_module.required_object
4. Bangun pohon by parent_id → mega(module) / sidebar(group,item) / palette(flatten item)
```
> **Status sekarang:** `required_object` **terisi di 7 modul**; group/item NULL → **mewarisi** modul induk (`effectiveObject`). Filter **aktif di granularitas modul** tanpa perlu isi tiap item. `sec_group_permission` masih kosong → hanya `is_admin` melihat menu; grup biasa ter-DENY sampai grant di-seed (B-OA2). Isi `required_object` item **hanya** untuk pengecualian (item lebih ketat dari modulnya).

### 4.2 Buka item → tab (per `target_kind`)
```text
app  → mount komponen Livewire by code
html → <iframe src=target>   (mockup: inquiry_main_hub.html dll.)
php  → placeholder mock (transisi)
url  → link/iframe eksternal
Tab MDI: satu code = satu tab (re-activate bila ada). Persisten lintas modul.
```

### 4.3 Render menu profil (`sys_user_menu`) — dispatch `action_type`
```text
items ← SELECT * FROM sys_user_menu WHERE is_active=1 ORDER BY sort_order
render: kelompokkan by section (NULL=tanpa header); item admin_only=1 → tampil hanya bila boleh
        badge: badge_source='chat' → COUNT unread sys_chat | 'notif' → COUNT unread sys_notifications
onClick(item): switch item.action_type
   'drawer' → openDrawer(action_target)        # chatDrawer / notifDrawer / aiDrawer
   'modal'  → openModal(action_target)         # mEdit / mPass
   'menu'   → openTab(action_target)           # code sys_app_menu (mis. SET_USER) — lewati filter izin biasa
   'url'    → navigate(action_target)          # aktivitas.php / support.php
   'lang'   → toggleLanguage()
   'logout' → redirect(action_target)          # login.html + hancurkan sesi
```
> **Dua tabel menu, dua render terpisah** namun terhubung: `action_type='menu'` memanggil `openTab()` yang sama dengan navigasi `sys_app_menu`. `admin_only` = filter UI; **server tetap menegakkan izin** saat objek dibuka (mis. Setup → cek modul SETUP).

## SECTION 5 — RESOLUSI IZIN (INTI)

### 5.1 Pseudocode
```text
function resolvePermissions(user):
    if user.is_admin: return ALLOW_ALL                 # superadmin bypass
    groups ← SELECT group_id FROM sec_user_group WHERE user_id=user.id
    if groups empty: return DENY_ALL                   # default DENY
    perms ← SELECT module_id, MAX(can_access) a, MAX(can_input) i,
                   MAX(can_approve) ap, MAX(can_export) e
            FROM sec_group_permission
            WHERE group_id IN (groups) GROUP BY module_id     # UNION = MAX per flag
    maxLevel ← SELECT MAX(mgmt_level) FROM sec_group WHERE id_group IN (groups)
    return { perms, maxLevel }

function canOpen(module_code):
    m ← sec_module[module_code]                         # module_code == kode menu (sinkron)
    return perms[m.id].a=1 AND maxLevel ≥ m.min_mgmt_level
```

### 5.2 Aturan penegakan
| Flag | Efek |
|---|---|
| `can_access=0`/tak ada baris | modul **tak terlihat** (menu & route diblok) |
| `can_input=0` | modul **readonly** (tombol simpan/edit disembunyikan) |
| `can_approve=1` | tombol approve muncul (mis. Direksi approve harga Inquiry) |
| `can_export=1` | tombol export aktif (audit: siapa export apa) |
| `mgmt_level < min_mgmt_level` | blok walau `can_access=1` (data sensitif; FINACC=3, SETUP=5) |

- **Union = MAX per flag** antar grup (paling permisif menang).
- Enforcement **wajib server-side** (middleware per `route_prefix`); UI hanya cermin.

---

# ══════════════════════════════════════════════════════
# BAGIAN B — SPECIAL CASE (KONDISIONAL)
# ══════════════════════════════════════════════════════

## SECTION 6 — REGISTRASI, VERIFIKASI & APPROVAL

### 6.1 State registrasi (`approval_status`)
```text
[client draft] → email_verified=1 → wa_verified=1 → SUBMIT (approval_status='pending', is_active=0)
   → admin: 'approved' + assign grup + is_active=1
   → admin: 'rejected'
```

### 6.2 Verifikasi (dua kanal)
```text
EMAIL: cek MX/deliverability → kirim tautan/kode → set email_verified=1, email_verified_at=NOW()
WA/OTP: kirim OTP via gateway → user input → cocok & belum kadaluarsa → wa_verified=1, wa_verified_at=NOW()
   penyimpanan OTP: bila dipersistkan → tabel sec_registration_otp (kode-hash, expires_at, attempts) [Tier 3]
```

### 6.3 Aturan tipe & identitas
| reg_type | id_type | Format | linked_type (saat approve) |
|---|---|---|---|
| customer | nik | 16 digit | tautkan `cust_customer` |
| supplier | nik | 16 digit | tautkan `sup_*` |
| internal | nrp | bebas | EMPLOYEE (`emp_employee`) |

- Username auto = local-part email; **server jamin unik** (bentrok → +sufiks angka).
- SUBMIT → `INSERT sec_user(..., approval_status='pending', is_active=0)` **tanpa** `sec_user_group` + notifikasi admin (`sys_notifications`).
- **Approve** = isi `sec_user_group` + `approval_status='approved'` + `is_active=1` + set `linked_type/linked_id`.
- `reg_type` **informasional** (jejak asal), jangan dipakai untuk logika izin (B-OA8).

## SECTION 7 — RESET PASSWORD
```text
Request: input email → SELALU respons seragam "jika terdaftar, tautan dikirim"
   bila ada: token←random; INSERT password_reset_tokens(email, token, created_at); kirim tautan
Reset:   validasi token (belum kadaluarsa, mis. 60 mnt) → set password baru
             UPDATE sec_user SET pswd=bcrypt(new), pswd_last_updated=NOW()
             DELETE password_reset_tokens WHERE email=... (single-use)
```

## SECTION 8 — MFA (OPSIONAL) & BRUTE-FORCE
### 8.1 MFA
- Bila `sec_user.mfa` terisi → setelah password benar, minta kode TOTP. `mfa_last_updated` saat set/ubah. Belum ada mockup → fase lanjut.

### 8.2 Lockout (dari `sec_login_attempt`)
```text
fails ← COUNT(*) FROM sec_login_attempt
        WHERE login=:x AND success=0 AND attempted_at > NOW()-INTERVAL 15 MINUTE
IF fails ≥ 5 → tolak sementara (lock 15 mnt) — pesan tetap SERAGAM
Ambang (5/15mnt) = parameter → sys_setting bila perlu dikonfigurasi.
```

## SECTION 9 — NOTIFIKASI, CHAT, TIKET
### 9.1 Notifikasi
```text
per-user: INSERT sys_notifications(user_id=login, title, message, type, link)
broadcast: user_id=NULL → tampil ke semua
   catatan: is_read tunggal tak cukup utk broadcast multi-user →
            Tier 3 pertimbangkan pivot read-state per user
Badge = COUNT unread; "tandai semua terbaca" → is_read=1.
```
### 9.2 Chat
`sys_chat_conversations(is_group)` → `_participants(user_id, last_read_at)` → `_messages(sender, body, sent_at)`. Unread = `sent_at > last_read_at`.
### 9.3 Tiket dukungan
`sys_support_tickets(ticket_no, reporter, type[bug/feature_request/question], priority, subject, description, menu_code, app_version, browser_info, status[new..done/rejected], assigned_to)` → `sys_support_replies(ticket_id, author, body)`. `menu_code`+`app_version` (dari `sys_setting`) menautkan konteks bug.

## SECTION 10 — SEMANTIK STATUS & INTEGRITAS
- **Status user (B-OA9):** `approval_status` = lifecycle pendaftaran (pending/approved/rejected); `is_active` = enable/disable operasional; **`active` di-retire** (B-OA7), selama transisi mirror `is_active`. Login butuh `approval_status='approved' AND is_active=1`.
- **`reg_type` vs `linked_type` (B-OA8):** `reg_type` informasional; `linked_type/linked_id` otoritatif.
- **Audit login kanonik = `sec_login_attempt`** (tunggal).
- **`sys_app_menu` ⟷ `sec_module`:** sinkron by-code; `required_object` **terisi level module** → item/group **mewarisi** modul induk. Filter modul aktif; grant (`sec_group_permission`) belum di-seed → hanya admin berakses (B-OA2).
- **Dua tabel menu:** `sys_app_menu` (navigasi, difilter izin via `required_object`) vs `sys_user_menu` (profil, difilter `admin_only`). Keduanya data-driven; mockup masih hardcode menu profil → migrasi ke tabel (B-OA11).
- **FK lintas-DB** (`sec_user.employee_id/linked_id` → `tm2mst`): validasi di aplikasi (Eloquent), bukan constraint fisik (Tier 0).

---

# ══════════════════════════════════════════════════════
# BAGIAN C — STATE, FORM & BUTTON (UI/UX)
# ══════════════════════════════════════════════════════

## SECTION 11 — AKSI FORM STANDARD & LOGGING
### 11.1 Aksi standar (layar admin)
| Aksi | Efek |
|---|---|
| Tambah | INSERT + `created_by`, `created_at` |
| Simpan | UPDATE + `updated_by`, `updated_at` |
| Nonaktif | set `is_active=0` (bukan DELETE) |
| Batal | buang perubahan, kembali |

### 11.2 Logging universal
Perubahan izin/user/grup wajib tercatat (siapa, kapan, sebelum→sesudah). Minimal `created_by/updated_by`; ideal audit-trail terpisah untuk `sec_group_permission` (kritikal, terutama `can_export`).

## SECTION 12 — LAYAR SECURITY (per tab)
### 12.1 Pengguna (`p-users`)
```text
Form tambah: login/email, nama, Grup(select sec_group), Status(Aktif/Nonaktif)
Grid: login | nama | grup | status(pill) | [Edit][Hapus→nonaktif]
Rule: assign grup = INSERT sec_user_group. Akun approval_status='pending' → badge "menunggu approve".
```
### 12.2 Grup (`p-groups`)
```text
Form: Nama(group_code+group_name), Deskripsi, mgmt_level(1..5)
Grid: grup | level | jml anggota | [Edit]
Rule: group_code unik (uq_group_code); mgmt_level gate report.
```
### 12.3 Objek/Modul (`p-objects`) → `sec_module`
```text
Registry unit izin (bisnis), hierarki via parent_id.
Kolom: module_code, module_name, icon, route_prefix, min_mgmt_level, is_reportable, sort_order.
Rule: module_code = kode modul sys_app_menu (sinkron). INI dasar hak akses (bukan sys_app_menu).
```
### 12.4 Hak Akses Grup (`p-perms`) → `sec_group_permission`
```text
Pilih Grup → matriks per modul: [can_access][can_input][can_approve][can_export]
UPSERT (group_id, module_id). Tak ada baris = DENY.
UI: can_export ditandai warna kritikal + konfirmasi.
```
### 12.5 Bahasa (`p-lang`) → `sys_languages`/`sys_translations`/`sys_setting`
```text
Set default (sys_setting.default_language) + daftar bahasa aktif.
Tambah bahasa → tambah baris sys_translations per trans_key.
```
### 12.6 Tema (`p-theme`) → `sys_theme`/`sys_setting`/`sec_user.theme_code`
```text
Preset dari sys_theme (primary_color+sidebar_color) → var CSS live.
Default aplikasi: sys_setting.active_theme. Pilihan per-user: sec_user.theme_code.
Admin bisa aktif/nonaktif preset (is_active) & set default (is_default).
```

## SECTION 13 — PEMETAAN BUTTON ↔ IZIN
| Layar | Tombol | Muncul bila |
|---|---|---|
| Modul mana pun | Simpan/Edit | `can_input=1` |
| Inquiry/harga | Approve | `can_approve=1` (Direksi) |
| Grid mana pun | Export | `can_export=1` |
| Security | tab admin | `is_admin=1` atau izin modul SETUP (min_mgmt_level=5) |
| User menu | Keluar | selalu |

- UI **menyembunyikan** (bukan sekadar disable) tombol tak berizin.
- **Enforcement ganda:** UI sembunyikan **+** server tolak. UI bukan pengaman.

## SECTION 14 — STATE PENANDA
| Kondisi | Penanda UI |
|---|---|
| Akun pending (`approval_status='pending'`) | badge "menunggu approve" |
| User nonaktif (`is_active=0`) | pill abu/"Nonaktif" |
| Modul readonly (`can_input=0`) | banner "Hanya-baca" |
| Notifikasi belum baca | dot + count badge |
| Login gagal berulang (lock) | pesan seragam (tak ungkap lock) |

---

## SECTION 15 — NOTICE (LANJUTAN)
- **Tier 3 (DDL/seed):** `sys_login_hero`; retire `active` (B-OA7); sub-modul `SALES_HPP` min_mgmt_level=5 bila dipilih (B-OA10); **seed `sys_app_menu.required_object` + `sec_group_permission`** (B-OA2); `sec_registration_otp` bila OTP dipersistkan; pivot read-state notifikasi broadcast.
- **Tier 4:** wiring `target_kind='html'` ke mockup (mis. `SALES_INQ`→`inquiry_main_hub.html`); **render menu profil dari `sys_user_menu`** (ganti `fillUserMenu()` hardcode, B-OA11); state per tab security; penanda readonly/approve/export sesuai izin.
- **Lintas modul:** Direksi `approve` harga Inquiry = instance konkret `can_approve` + `mgmt_level=5` (selaras Tier 1 Inquiry §2.5); rahasia costing Sisi A butuh keputusan gating (B-OA10).

---
> **Dokumen terkait:** Tier 1 (requirement) · **Tier 3** (DDL, relasi, script seed 3B-S1..S6) · **Tier 4** (spec mockup & checklist wiring produksi).
