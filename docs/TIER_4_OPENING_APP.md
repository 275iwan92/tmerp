# TIER 4 — OPENING APP · MOCKUP & UI/UX SPEC
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 4 — Opening App · Mockup & UI/UX Spec |
| **Versi** | 1.0 |
| **Basis** | Tier 1 v1.3 · Tier 2 v1.3 · Tier 3 v1.0 (DDL/seed) |
| **Sifat** | Spec **replikasi** struktur layar yang sudah ada (dummy data), bukan desain baru |
| **Berkas mockup** | `login.html` · `forgot-password.html` · `register.html` · `main_menu_v2.html` · `security.html` |
| **Tujuan** | Kunci komponen, state, i18n, dan **binding ke tabel `tm2sys`** tiap layar + checklist wiring produksi |

---

## SECTION 2 — BAHASA DESAIN (konsisten semua layar)
- Palet blueprint-ink **navy** (`--navy` #1f2742/#34406b), latar `#eef1f6`, aksen biru `#2f6fed`, danger `#d0453f`, ok `#2e9e5b`.
- Kartu putih radius 12–16, shadow lembut; input `.ig` dengan focus-ring biru; tombol utama biru penuh.
- i18n **ID/EN** via `data-i18n` + kamus `T`; `?lang=` dipertahankan antar-halaman.
- Utilitas login/daftar: **hero** (gambar), **quote**, **clock** live, **grip** pelebar panel (15–40%).
- Tema di-drive var CSS (`--navy`) → produksi diisi dari `sys_theme` sesuai `sec_user.theme_code`.

---

## SECTION 2B — KONVENSI LAYOUT (WAJIB, anti-regresi)
Layar opening_app **full-screen**, tak boleh dibungkus layout ber-container.

| Layout | Untuk | Aturan |
|---|---|---|
| **`layouts.guest`** (full-bleed) | login · register · forgot-password | `<body>` → langsung `.wrap`; tanpa `.container`/`max-width`/`mx-auto`/`padding` |
| **`layouts.shell`** (full-viewport) | main_menu | top bar + sidebar + area tab mengisi layar; `overflow:hidden` di body |

**Reset wajib (khusus opening_app):** `html,body{margin:0;padding:0;height:100%;width:100%}` · `*{box-sizing:border-box}`.
**Struktur:** `.wrap{display:flex;height:100vh;width:100%}` · `.hero{flex:1}` (hero `position:absolute;inset:0;object-fit:cover`).
**Larangan:** tak ada `<div class="container">`, `<main class="max-w-…">`, atau padding pembungkus antara `<body>` dan `.wrap`. Utility Tailwind tidak boleh menimpa CSS mockup.
**Verifikasi:** DevTools → `<body>` lebar = viewport penuh; tak ada elemen `margin-auto`/`max-width` di jalur `<body>`→`.wrap`.
> Gejala regresi: kartu/hero mengambang di tengah dengan ruang putih di kiri/sekeliling = view masih memakai layout app default.

## SECTION 3 — SPEC PER LAYAR

### 3.1 `login.html` — Masuk
**Komponen:** hero+veil, brand, clock (`ck/cd`), quote (`qText/qAuthor`), grip resize, panel: langbar (🇮🇩/🇬🇧), form (`user`, `pass`+eye toggle), link Lupa/ Daftar, tombol Masuk, toast, alert.
**State:** kosong→`err_empty` + border danger · salah (`pass='salah'`)→`err_wrong` (seragam) · sukses→toast + redirect `main_menu_v2.html`.
**Binding produksi:**
| Elemen | Sumber |
|---|---|
| hero | `sys_login_hero` (RAND, is_active=1) — *tabel belum ada, Tier 3B-S3* |
| quote | `sys_quotes` (RAND, is_active=1) |
| submit | verify `sec_user` (is_active=1 AND approval_status='approved') |
| catat attempt | **`sec_login_attempt`** — ⚠️ mockup `logAttempt()` masih tulis `sys_login_attempts` (nama lama) → perbaiki |
| clock/locale | `lang` |

### 3.2 `forgot-password.html` — Lupa Password
**Komponen:** kartu, input `email`, tombol Kirim, blok `done`, link kembali.
**State:** klik Kirim → sembunyikan form, tampilkan `fp_done` **seragam** ("jika terdaftar, tautan dikirim") — anti-enumeration.
**Binding:** buat token → `password_reset_tokens`; kirim email hanya jika `sec_user.email` terdaftar (logika server, tak dibocorkan ke UI).

### 3.3 `register.html` — Registrasi Mandiri
**Komponen:** pilih tipe `ptype` (opt_cust/supp/int), field `name`, `email`(+note verifikasi), `user`(auto), `wa`(+`send_otp`→`otpRow`/`otp`/`verify`), `idno`(+label dinamis `lblId`: NIK/NRP), `pass`+`conf`(+eye), tombol `submit`, layar `done` ("menunggu approval").
**Logika tipe:** customer/supplier → `lblId`=NIK 16 digit · internal → `lblId`=NRP bebas.
**State verifikasi:** email → `email_verified` · WA OTP kirim→input→cocok → `wa_verified`. Submit → akun `approval_status='pending'`.
**Binding `sec_user`:** `reg_type`←ptype · `id_type`+`id_number`←idno · `email_verified*` · `wa_verified*` · `approval_status='pending'` · `login`←local-part email (server jamin unik). Notifikasi admin → `sys_notifications`.
**Gap:** penyimpanan OTP belum bertabel (opsional `sec_registration_otp`, Tier 3).

### 3.4 `main_menu_v2.html` — App Shell
**Struktur:**
| Zona | Isi | Sumber |
|---|---|---|
| Mega menu (top) | modul (ikon+nama) | `sys_app_menu` `menu_type='module'` + filter izin (`required_object`→`sec_module`→`sec_group_permission`, inheritance) |
| Sidebar | group→item modul aktif | `sys_app_menu` group/item |
| Tab MDI | tiap item = 1 tab | render per `target_kind` (app/php/html/url) |
| Command palette (Ctrl+K) | semua item (flatten) | `sys_app_menu` |
| User menu (`userMenu`) | dropdown avatar | **`sys_user_menu`** (dispatch `action_type`) — *mockup masih hardcode `fillUserMenu()`* |
| Drawers | `chatDrawer`/`notifDrawer`/`aiDrawer` | `sys_chat_*` / `sys_notifications` |
| Modals | edit profil / ubah password | `sec_user` |

**Badge:** unread chat (`sys_chat_*`) & notif (`sys_notifications`). **Admin flag:** `sec_user.is_admin` (sesi).
**Collapse:** sidebar/topbar/mini (state UI).
**PRODUKSI (dari komentar mockup):** ganti array menu statis → query `sys_app_menu`; user menu → `sys_user_menu`; is_admin dari sesi; unread realtime.

### 3.5 `security.html` — Administrasi Keamanan
Sidebar 6 panel (`data-p` / `id=p-*`):
| Panel | Fungsi | Aksi | Tabel |
|---|---|---|---|
| `users` (Pengguna) | daftar + tambah user | `addUser()`, assign grup, status | `sec_user`, `sec_user_group` |
| `groups` (Grup) | daftar + tambah grup, `mgmt_level` | `addGroup()` | `sec_group` |
| `objects` (Objek Form & Grid) | registry unit izin | — | `sec_module` |
| `perms` (Hak Akses Grup) | matriks grup×objek: access/input/approve/export | `bulkAll()`, toggle | `sec_group_permission` |
| `lang` (Bahasa) | default + daftar bahasa | `addLang()` | `sys_languages`, `sys_setting` |
| `theme` (Tema) | preset warna | `pickTheme()` | `sys_theme`, `sys_setting`, `sec_user.theme_code` |

**Penanda:** akun `pending`→badge "menunggu approve"; user nonaktif→pill; `can_export`→warna kritikal + konfirmasi.

---

## SECTION 4 — i18n (cakupan kunci)
- Setiap layar punya kamus `T.id`/`T.en` + `data-i18n`. Produksi: pindah ke `sys_translations` (`trans_key`), `sys_app_menu.trans_key` & `sys_user_menu.code` sebagai kunci.
- `?lang=` konsisten dibawa antar-navigasi (login↔forgot↔register).

## SECTION 5 — STATE & BUTTON ↔ IZIN (ref Tier 2 §13)
- Tombol Simpan/Edit → `can_input=1`; Approve → `can_approve=1`; Export → `can_export=1`; tab admin/Setup → `is_admin` atau modul SETUP (lvl5).
- UI **menyembunyikan** (bukan disable) yang tak berizin; **server tetap menegakkan**.

## SECTION 6 — CHECKLIST WIRING PRODUKSI
1. **login:** hero←`sys_login_hero` (buat tabel, 3B-S3) · quote←`sys_quotes` · attempt→**`sec_login_attempt`** (ganti nama lama di JS) · gerbang login cek `approved` (3B-S2).
2. **register:** simpan ke `sec_user` (kolom registrasi) · notif admin.
3. **shell:** menu←`sys_app_menu` **terfilter izin** (inheritance) · user menu←`sys_user_menu` (ganti hardcode, B-OA11) · badge realtime.
4. **inquiry masuk menu (hub+sidebar):** `SALES_INQ`→`inquiry_hub.html` (pane); leaf `SALES_INQ_NEW/RECALC/REVISE` di sidebar; hub delegasi ke `openTab()` & warisi tema shell (3B-S5).
5. **security:** CRUD ke `sec_*` · matriks izin ke `sec_group_permission` (seed awal 3B-S1) · tema ke `sys_theme`/`sec_user.theme_code`.

## SECTION 7 — NOTICE (gap khusus mockup)
- **login.js** mencatat ke `sys_login_attempts` (nama lama) → harus `sec_login_attempt` (B-OA3).
- **main_menu.js** `fillUserMenu()` hardcode → baca `sys_user_menu` (B-OA11).
- **hero** merujuk `sys_login_hero` yang belum ada (B-OA5 / 3B-S3).
- **register** OTP belum bertabel (opsional Tier 3).
- Semua mockup = **dummy data**; angka/label bukan final.

## SECTION 8 — KONVENSI LAYOUT & SERVING (untuk Claude Code)
Semua layar opening_app **full-bleed** (isi 100% viewport). Gejala "kolom putih di kiri" = view dibungkus layout ber-container → perbaiki di **layout**, bukan CSS mockup.

**Dua layout khusus, TANPA container:**
- `layouts.guest` → login, register, forgot-password, reset-password
- `layouts.shell` → main_menu (app shell)

Isi layout hanya `<body>{{ $slot }}</body>`. **DILARANG** di pembungkus: `.container`, `max-width`, `mx-auto`, `p-*`, `m-*`, `<main class="max-w-…">`.

**Global reset (khusus 2 layout ini):** `html,body{margin:0;padding:0;height:100%;width:100%}` · `*{box-sizing:border-box}`.

**Struktur wajib:** `<body>` → langsung `.wrap` (`display:flex;height:100vh;width:100%`) → `.hero{flex:1}` (img `position:absolute;inset:0;object-fit:cover`). Tak boleh ada elemen perantara.

**Hero:** `img.src` dari `sys_login_hero` (is_active=1, RAND); fallback dev `/img/hero/offset_1.jpg`; jangan biarkan `src` kosong (gap B-OA5 / 3B-S3).

**Serving (Herd):** project di `…/Herd/tmerp` → `http://tmerp.test`. Route Livewire: `/login`, `/` (shell). Mockup statis: taruh di `public/mock/…` → `http://tmerp.test/mock/<file>.html`. HTTPS: `herd secure`.

**Kontrak shell `openTab(code, params)`:** lookup `sys_app_menu` by `code` → buka `target` sebagai tab MDI → append `params` sebagai query ke iframe. Dipakai hub inquiry (`window.parent.openTab('SALES_INQ_NEW',{doc_no,mode})`); kirim tema ke iframe via `postMessage({type:'theme',primary,accent})`.

**Verifikasi (DevTools F12):** klik `<body>` → lebar = viewport penuh; telusuri anak sampai `.wrap`; elemen lebar <100vw / `margin-auto` = wrapper yang harus dihapus.

---
> **Terkait:** Tier 1 (requirement) · Tier 2 (logika) · Tier 3 (DDL & seed penutup gap).
