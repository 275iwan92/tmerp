# TIER 3 — OPENING APP · DATABASE DESIGN (DDL, RELASI & SEED)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 3 — Opening App · Database Design |
| **Versi** | 1.0 |
| **Basis** | Tier 1 v1.3 (fitur & gap) · Tier 2 v1.3 (logika) · dump `tm2sys.sql` aktual |
| **Sifat** | 3A = struktur aktual `tm2sys` (sudah ter-deploy) · **3B = script migrasi/seed penutup gap** (belum jalan) |
| **Database** | `tm2sys` (Security & Notification) — 27 tabel |
| **Prefix** | `sec_*` (identitas & izin) · `sys_*` (tampilan & utilitas) · sisanya infra Laravel |

> Struktur penuh sudah ada di `tm2sys.sql`. Dokumen ini = **peta otoritatif + relasi + skrip penutup gap** (bukan menyalin DDL mentah).

---

## SECTION 2 — PETA TABEL (27)

| Kategori | Tabel | Peran |
|---|---|---|
| **Identitas & Izin** | `sec_user` | akun login (+kolom registrasi) |
| | `sec_group` | grup = unit izin, `mgmt_level` 1–5 |
| | `sec_user_group` | M:N user↔grup (izin efektif = union) |
| | `sec_module` | unit izin bisnis (7 modul), `min_mgmt_level` |
| | `sec_group_permission` | grup×modul: access/input/approve/export |
| | `sec_login_attempt` | audit percobaan login (kanonik) |
| **Menu & Tampilan** | `sys_app_menu` | navigasi (mega/sidebar/palette) |
| | `sys_user_menu` | menu profil (dropdown avatar) |
| | `sys_theme` | preset tema (warna) |
| | `sys_setting` | key-value app (active_theme, dll.) |
| | `sys_languages`, `sys_translations` | i18n |
| | `sys_quotes` | kutipan layar login |
| **Notif/Chat/Support** | `sys_notifications` | notifikasi per-user/broadcast |
| | `sys_chat_conversations/_participants/_messages` | chat internal |
| | `sys_support_tickets/_replies` | tiket dukungan |
| **Infra Laravel** | `migrations`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | framework |

---

## SECTION 3 — TABEL INTI (kolom otoritatif)

### 3.1 `sec_user`
PK `id_user`. Unik `login`. Kunci: `pswd`(bcrypt), `email`, `phone`, `is_admin`(bypass izin), `mfa`.
**Registrasi:** `reg_type`(customer/supplier/internal, informasional) · `id_type`(nik/nrp)+`id_number` · `email_verified`+`_at` · `wa_verified`+`_at` · `approval_status`(pending/approved/rejected) · `theme_code`(→`sys_theme`).
**Tautan master (FK logis lintas-DB):** `employee_id`→`emp_employee`(tm2mst) · `linked_type`+`linked_id`(otoritatif).
**⚠️ Redundansi (lihat 3B):** `active` vs `is_active`; `reg_type` vs `linked_type`.

### 3.2 `sec_module`
PK `id_module`, `parent_id`(hierarki). Unik `module_code`(=kode `sys_app_menu` module). `min_mgmt_level`(gate sensitif), `route_prefix`(middleware), `is_reportable`, `sort_order`, `is_active`.
Seed 7: SALES(1)·PROC(2)·INV(3)·PROD(4)·MDATA(5)·FINACC(6·lvl3)·SETUP(9·lvl5).

### 3.3 `sec_group` / `sec_user_group` / `sec_group_permission`
- `sec_group`: unik `group_code`, `mgmt_level`(1STAFF..5DIREKSI). Seed: SALES_STAFF(1)·SALES_SPV(2)·DIREKSI(5)·PRODUKSI(1).
- `sec_user_group`: (user_id, group_id).
- `sec_group_permission`: (group_id, module_id) unik + `can_access|can_input|can_approve|can_export`. **Default DENY** (tak ada baris = tak boleh). **Seed = KOSONG** → hanya `is_admin` berakses (gap, 3B-S1).

### 3.4 `sys_app_menu`
PK `id`, `parent_id`. `menu_type`(module/group/item), `object_type`(form/grid/report/dashboard/link), `target`+`target_kind`(app/php/html/url), `trans_key`(i18n), `required_object`→`sec_module.module_code`.
**Status wiring:** `required_object` terisi di **7 module**; group/item NULL → **inheritance** ke modul induk. Item inquiry masih `target_kind='app'`→`.php` placeholder (3B-S5).

### 3.5 `sys_user_menu`
PK `id_umenu`, unik `code`(UM_*). `section`(header), `action_type`(drawer/modal/menu/url/lang/logout)+`action_target`, `badge_source`(chat/notif/NULL), `is_danger`, `admin_only`, `sort_order`. Seed 10 item.

### 3.6 `sys_theme` / `sys_setting`
- `sys_theme`: PK `code`, `primary_color`, `sidebar_color`, `is_default`, `is_active`. Seed 6 (navy default, emerald, indigo, plum, slate, teal).
- `sys_setting`: PK `setting_key`. Seed: active_theme=navy, default_language=id, app_version=1.0.

### 3.7 `sec_login_attempt`
PK `id_attempt`. `login`, `success`, `ip_address`, `user_agent`, `attempted_at`. Indeks `(login, attempted_at)` untuk lockout.

---

## SECTION 4 — RELASI (FK logis; lintas-DB dijaga aplikasi)

```text
sec_user ──< sec_user_group >── sec_group ──< sec_group_permission >── sec_module
   │                                                                        ▲
   │ theme_code ─► sys_theme                        sys_app_menu.required_object ┘ (by module_code)
   │ employee_id ─► emp_employee (tm2mst)           sys_app_menu.parent_id ─► self (module→group→item)
   │ linked_type/linked_id ─► cust_customer / sup_* (tm2mst)
   │ login ─► sec_login_attempt.login (soft)        sys_user_menu.action_target ─► sys_app_menu.code (bila action_type='menu')
   └ id_user ─► sys_notifications.user_id / sys_chat_participants.user_id / sys_support_tickets.reporter
sys_languages ──< sys_translations   ·   sys_app_menu.trans_key / sys_user_menu.code ─► sys_translations.trans_key
```
**Prinsip Tier 0:** cross-DB = **logical FK** via Eloquent, **tanpa** constraint fisik. Cross-module read via VIEW.

---

## SECTION 5 — TIER 3B: SCRIPT PENUTUP GAP (belum dijalankan)

> Urutan aman: S2 → S1 → S3 → S4 → S5 → S6. Semua idempotent bila memungkinkan. Sesuaikan `id`/`code` dengan data aktual.

### 3B-S1 — Seed `sec_group_permission` (gap B-OA2) — WAJIB sebelum non-admin bisa akses
```sql
-- module_id: 1SALES 2PROC 3INV 4PROD 5MDATA 6FINACC 9SETUP
INSERT INTO sec_group_permission (group_id, module_id, can_access, can_input, can_approve, can_export) VALUES
-- SALES_STAFF(1): akses Sales+Inventory, input Sales
 (1,1,1,1,0,0),(1,3,1,0,0,0),
-- SALES_SPV(2): + approve Sales, export Sales, lihat FINACC readonly
 (2,1,1,1,1,1),(2,3,1,0,0,0),(2,6,1,0,0,0),
-- DIREKSI(3): akses luas + approve + export (HPP/margin lolos via mgmt_level=5)
 (3,1,1,1,1,1),(3,2,1,1,1,1),(3,3,1,1,1,1),(3,4,1,1,1,1),(3,5,1,1,0,1),(3,6,1,1,1,1),
-- PRODUKSI(4): akses Production+Inventory
 (4,4,1,1,0,0),(4,3,1,0,0,0)
ON DUPLICATE KEY UPDATE can_access=VALUES(can_access), can_input=VALUES(can_input),
  can_approve=VALUES(can_approve), can_export=VALUES(can_export);
-- Admin app: cukup sec_user.is_admin=1 (bypass), tak perlu baris ini.
```

### 3B-S2 — Fix akun provisioned (gap B-OA12)
```sql
UPDATE sec_user SET approval_status='approved'
WHERE reg_type IS NULL AND approval_status='pending';  -- akun buatan-admin, bukan self-register
-- ATAU longgarkan gerbang login di aplikasi: approval_status='approved' OR is_admin=1
```

### 3B-S3 — Tabel `sys_login_hero` (gap B-OA5; dirujuk login.html/register.html)
```sql
CREATE TABLE sys_login_hero (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_url VARCHAR(500) NOT NULL,
  caption   VARCHAR(200) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO sys_login_hero (image_url, caption, sort_order) VALUES
 ('/img/hero/offset_1.jpg','Mesin cetak offset 4 warna',10),
 ('/img/hero/offset_2.jpg','Line finishing packaging',20);
-- Query render: SELECT image_url,caption FROM sys_login_hero WHERE is_active=1 ORDER BY RAND() LIMIT 1;
```

### 3B-S4 — Pensiunkan `active` → `is_active` (gap B-OA7/B-OA9)
```sql
UPDATE sec_user SET is_active = active WHERE is_active <> active;  -- selaraskan dulu
-- Setelah kode tak lagi baca `active`:
-- ALTER TABLE sec_user DROP COLUMN active;
-- Semantik final: approval_status=lifecycle · is_active=enable/disable.
```

### 3B-S5 — Wiring "Inquiry → main menu" (pola **hub + sidebar**, Opsi C)
Hub = landing pane; leaf New/Recalc/Revise juga tampil di sidebar. **Satu sumber navigasi**: kartu hub memanggil `openTab()` shell dengan kode leaf yang sama.
```sql
-- Hub sebagai landing "Inquiry Product"
UPDATE sys_app_menu SET target_kind='html', target='inquiry_hub.html' WHERE code='SALES_INQ';
-- Leaf di sidebar (grup 110 SALES_TRX). NEW = form langsung; RECALC/REVISE = hub + picker.
INSERT INTO sys_app_menu (id, parent_id, code, label, menu_type, object_type, target, target_kind, sort_order, is_active, required_object) VALUES
 (117,110,'SALES_INQ_NEW',   'Inkuiri Baru',        'item','form','inquiry_new.html',              'html',11,1,NULL),
 (118,110,'SALES_INQ_RECALC','Hitung Ulang (Recalc)','item','form','inquiry_hub.html?picker=RECALC','html',12,1,NULL),
 (119,110,'SALES_INQ_REVISE','Ubah Spesifikasi (Revise)','item','form','inquiry_hub.html?picker=REVISE','html',13,1,NULL);
-- Lain (transisi html→app saat komponen Livewire siap):
UPDATE sys_app_menu SET target_kind='html', target='inquiry_costing_hub.html' WHERE code='SALES_COST';
UPDATE sys_app_menu SET target_kind='html', target='inquiry_quotation.html'  WHERE code='SALES_QUOT';
-- required_object leaf tetap NULL → mewarisi modul SALES. Isi HANYA bila perlu lebih ketat (mis. costing→SALES_HPP, S6).
```
> **Kontrak shell:** `openTab(code, params)` mencari `sys_app_menu` by `code`, membuka `target` (append `params` sebagai query bila ada). Hub yang diadaptasi memanggil `window.parent.openTab('SALES_INQ_NEW',{doc_no,mode})`; bila dibuka standalone, fallback ke URL. Warna hub mewarisi `--navy/--accent` (token shell) dan bisa di-override via `postMessage({type:'theme',primary,accent})`.

### 3B-S6 — (Opsional) Sub-modul rahasia costing (gap B-OA10)
```sql
INSERT INTO sec_module (parent_id, module_code, module_name, route_prefix, min_mgmt_level, sort_order)
 VALUES ((SELECT id_module FROM sec_module WHERE module_code='SALES'),
         'SALES_HPP','HPP & Margin (Sisi A)','inquiry/costing',5,15);
-- Lalu set sys_app_menu.required_object='SALES_HPP' pada item costing yang menampilkan HPP/margin.
-- Efek: hanya grup mgmt_level>=5 (Direksi) yang lolos. Alternatif = redaksi per-field (Sisi B).
```

---

## SECTION 6 — INDEX & INTEGRITAS
- Unik: `sec_user.login`, `sec_group.group_code`, `sec_module.module_code`, `sys_user_menu.code`, `sec_group_permission(group_id,module_id)`, `sys_translations(lang_code,trans_key)`.
- Indeks kinerja: `sec_login_attempt(login,attempted_at)` (lockout) · `sys_app_menu(parent_id,sort_order)` (render) · `sys_notifications(user_id,is_read)` (badge).
- Snapshot/audit kolom: `created_by/updated_by/created_at/updated_at` di tabel admin (sec_*, sys_user_menu, sec_module).

---

## SECTION 7 — NOTICE
- **Wajib sebelum go-live:** 3B-S1 (grant) + 3B-S2 (admin approved). Tanpa keduanya, non-admin buta / admin terkunci.
- **Direkomendasikan:** 3B-S3 (hero), 3B-S4 (retire `active`).
- **Saat mulai modul Inquiry:** 3B-S5 (wiring menu→mockup).
- **Keputusan terbuka:** 3B-S6 gating costing (sub-modul vs redaksi per-field) — tunggu arahan.
- **Broadcast notif:** `is_read` tunggal belum mendukung status-baca per-user untuk `user_id=NULL`; pertimbangkan pivot bila diperlukan.
- **OTP registrasi:** bila OTP WA dipersistkan, tambах `sec_registration_otp(code_hash, expires_at, attempts)` (belum ada).

---
> **Terkait:** Tier 1/2 (requirement & logika) · **Tier 4** (spec mockup & wiring UI).
