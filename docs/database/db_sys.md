---
name: db_sys
description: Database sistem (tm2sys) — auth/permission (sec_*), menu dinamis, notifikasi, chat, tema, constant, dan tabel framework Laravel bawaan. Koneksi DEFAULT Eloquent.
---

# Database: tm2sys (koneksi Eloquent: `sys`)

**Sumber DDL**: `docs/ddl/tm2sys.sql`
**Jumlah tabel**: 33

## Daftar Tabel

### `cache`
Tabel cache framework Laravel (driver database cache).
- PK: `key`
- Kolom: `key` (varchar 255), `value` (mediumtext), `expiration` (bigint)
- FK internal: tidak ada

### `cache_locks`
Lock cache framework Laravel (atomic lock driver database).
- PK: `key`
- Kolom: `key` (varchar 255), `owner` (varchar 255), `expiration` (bigint)
- FK internal: tidak ada

### `failed_jobs`
Log job queue Laravel yang gagal.
- PK: `id`
- Kolom: `id`, `uuid` (unique), `connection`, `queue`, `payload` (longtext), `exception` (longtext), `failed_at`
- FK internal: tidak ada

### `job_batches`
Batch job queue Laravel (Bus::batch).
- PK: `id` (varchar)
- Kolom: `id`, `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids` (longtext), `options` (mediumtext), `cancelled_at`, `created_at` (int), `finished_at`
- FK internal: tidak ada

### `jobs`
Antrian job queue Laravel (driver database queue).
- PK: `id`
- Kolom: `id`, `queue`, `payload` (longtext), `attempts`, `reserved_at`, `available_at`, `created_at` (int)
- FK internal: tidak ada

### `migrations`
Riwayat migration Laravel yang sudah dijalankan.
- PK: `id`
- Kolom: `id`, `migration`, `batch`
- FK internal: tidak ada

### `password_reset_tokens`
Token reset password Laravel bawaan (jalur lama; app juga punya `sec_recovery_question` 4-pertanyaan sbg jalur utama).
- PK: `email`
- Kolom: `email`, `token`, `created_at`
- FK internal: tidak ada

### `sec_group`
Grup/role pengguna + level manajemen (1=STAFF..5=DIREKSI). Direferensikan `sec_user_group` & `sec_group_permission`.
- PK: `id_group`
- Kolom: `id_group`, `group_code` (unique, mis. SALES_STAFF/DIREKSI/PRODUKSI), `group_name`, `mgmt_level` (tinyint 1-5), `activate`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `sec_group_permission`
Izin per GRUP per MODUL (`sec_module`) — flag can_access/can_input/can_approve/can_export. Default DENY: tak ada baris = grup tak boleh akses modul.
- PK: `id_permission`
- Kolom: `id_permission`, `group_id`, `module_id`, `can_access`, `can_input`, `can_approve`, `can_export`, `created_at/by`, `updated_at/by`
- FK internal: `group_id -> sec_group`, `module_id -> sec_module`
- Unique: (`group_id`, `module_id`)

### `sec_login_attempt`
Audit percobaan login (login, success, ip, user_agent). Tabel KANONIK sejak `sys_login_attempts` dihapus. Diisi server-side tiap percobaan.
- PK: `id_attempt`
- Kolom: `id_attempt`, `login`, `success` (1/0), `ip_address`, `user_agent`, `attempted_at`
- FK internal: tidak ada (index login+time, index success)

### `sec_module`
Modul bisnis = unit pemberian izin. `module_code` DIBUAT SAMA dengan kode modul `sys_app_menu` (SALES/PROC/INV/PROD/MDATA/FINACC/SETUP) agar izin & menu sinkron.
- PK: `id_module`
- Kolom: `id_module`, `parent_id` (self-FK, NULL=modul induk), `module_code` (unique), `module_name`, `icon`, `route_prefix`, `min_mgmt_level`, `is_reportable`, `sort_order`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `parent_id -> sec_module` (self-referencing)

### `sec_recovery_question`
Daftar pertanyaan keamanan untuk recovery password (4-pertanyaan, lihat memory `project_security_question_recovery`).
- PK: `id_question`
- Kolom: `id_question`, `question_text`, `activate`, `sort_order`, `created_at/by`, `updated_at/by`
- FK internal: tidak ada

### `sec_user`
Akun login. `login`=username unik, `pswd`=hash. Kolom pendaftaran: `id_type`/`id_number` (NIK/NRP), `email_verified`, `wa_verified`, `approval_status`, `theme_code`. `linked_type`/`linked_id` menaut ke customer/supplier (lintas-DB, logis). Catatan comment asli: "active vs is_active masih dobel — pilih satu; reg_type overlap dgn linked_type".
- PK: `id_user`
- Kolom: `id_user`, `login` (unique), `pswd`, `default_password` (text, ciphertext Crypt), `must_change_password`, `name`, `email`, `phone`, `birth_date`, `mother_name_hash`, `security_question_id`, `security_answer_hash`, `security_setup_at`, `active` (legacy, terpisah dari `activate`), `is_admin`, `activation_code`, `mfa`, `mfa_last_updated`, `pswd_last_updated`, `employee_id` (FK logis -> tm2mst.emp_employee), `linked_type`, `linked_id`, `id_type` (enum nik/nrp), `activate`, `reg_type` (customer/supplier/internal), `id_number`, `email_verified(+_at)`, `wa_verified(+_at)`, `approval_status` (pending/approved/rejected), `theme_code`, `admin_theme_mode`, `admin_theme_accent`, `created_at/by`, `updated_at/by`
- FK internal: `security_question_id -> sec_recovery_question`
- Kemungkinan FK logis lintas-DB: `employee_id` -> tm2mst.emp_employee; `linked_id`+`linked_type` -> tm2mst.cust_customer/sup_supplier (dijaga app layer)

### `sec_user_group`
Keanggotaan user dalam grup (relasi M:N). Izin efektif user = gabungan izin seluruh grupnya.
- PK: `id_user_group`
- Kolom: `id_user_group`, `user_id`, `group_id`, `created_at`
- FK internal: `user_id -> sec_user`, `group_id -> sec_group`
- Unique: (`user_id`, `group_id`)

### `sessions`
Tabel session Laravel (driver database session).
- PK: `id` (varchar)
- Kolom: `id`, `user_id`, `ip_address`, `user_agent` (text), `payload` (longtext), `last_activity` (int)
- FK internal: tidak ada (index user_id, last_activity)

### `sys_app_menu`
Menu dinamis (mega menu/sidebar/command palette) — layer tampilan. Hierarki module->group->item; `target`+`target_kind`=eksekusi; `required_object` -> `sec_module.module_code` (kaitan hak akses).
- PK: `id` (int, bukan auto — di-assign manual)
- Kolom: `id`, `parent_id` (self-FK), `code` (unique), `label`, `short_label`, `tooltip`, `trans_key`, `icon`, `menu_type` (module/group/item), `object_type` (form/grid/report/dashboard/link), `target`, `target_kind` (app/php/html/url), `sort_order`, `activate`, `required_object`, `created_at`, `status_code` (NOT_BUILT/READY_FOR_QC/LOCKED), `status_note`, `status_updated_by/at`, `doc_slug`
- FK internal: `parent_id -> sys_app_menu` (self-referencing)

### `sys_app_menu_color`
Preset warna badge status menu (`sys_app_menu.status_code`).
- PK: `status_code` (varchar)
- Kolom: `status_code`, `color_hex`, `label`, `is_clickable`, `updated_by`, `updated_at`
- FK internal: tidak ada (referensi logis ke `sys_app_menu.status_code`)

### `sys_chat_conversations`
Header percakapan chat antar pengguna. `is_group`=0 (1:1) / 1 (grup); `title`=nama grup bila grup.
- PK: `id`
- Kolom: `id`, `is_group`, `title`, `created_at`
- FK internal: tidak ada

### `sys_chat_messages`
Pesan chat. `conversation_id` -> `sys_chat_conversations`; `sender` = `sec_user.login`.
- PK: `id`
- Kolom: `id`, `conversation_id`, `sender`, `body` (text), `sent_at`
- FK internal: `conversation_id -> sys_chat_conversations`

### `sys_chat_participants`
Peserta tiap percakapan (PK gabungan conversation_id+user_id); `last_read_at` utk menghitung pesan belum dibaca. `user_id` = `sec_user.login`.
- PK: (`conversation_id`, `user_id`) — composite
- Kolom: `conversation_id`, `user_id` (varchar, bukan FK numerik), `last_read_at`
- FK internal: `conversation_id -> sys_chat_conversations`

### `sys_constant`
Parameter/konstanta aplikasi (key-value dengan number/text), dikelompokkan `category_code`/`constant_group`. Dipakai al. `sys_constant category=PRODUCTION/group=COSTING` di ProCost.
- PK: `id_constant`
- Kolom: `id_constant`, `constant_key` (unique), `category_code`, `constant_group`, `constant_number` (decimal 20,6), `constant_text`, `description`, `activate`, `created_at/by`, `updated_at/by`
- FK internal: `category_code -> sys_constant_category`

### `sys_constant_category`
Kategori/grouping untuk `sys_constant`.
- PK: `code` (varchar)
- Kolom: `code`, `label`, `description`, `sort_order`, `activate`, `created_at`, `updated_at`
- FK internal: tidak ada

### `sys_doc_reference`
Daftar dokumen `/docs` yang bukan milik 1 menu item (aturan global/standar/engine/referensi) — sumber kebenaran nama file dokumen lintas-modul.
- PK: `id`
- Kolom: `id`, `file_name`, `title`, `category` (GLOBAL_RULE/STANDARD/ARCHITECTURE/ENGINE/REFERENCE/SECURITY), `related_menu_code` (referensi lunak, bukan FK), `description`, `sort_order`, `activate`, `created_at`
- FK internal: tidak ada

### `sys_grid_preference`
Preferensi tampilan grid per user (kolom terlihat, sort, group by) — reusable komponen `HasCustomizableGrid`.
- PK: `id_grid_preference`
- Kolom: `id_grid_preference`, `user_login`, `grid_key`, `visible_columns` (json), `sort_column`, `sort_direction` (asc/desc), `group_by_column`, `updated_at`
- FK internal: tidak ada
- Unique: (`user_login`, `grid_key`)

### `sys_languages`
Daftar bahasa aktif untuk i18n. PK=code (id/en); `is_default`=bahasa awal. Tabel induk dari `sys_translations`.
- PK: `code` (varchar)
- Kolom: `code`, `name`, `decimal_separator`, `thousand_separator`, `is_default`
- FK internal: tidak ada

### `sys_login_hero`
Gambar hero/banner halaman login (carousel).
- PK: `id`
- Kolom: `id`, `image_url`, `caption`, `sort_order`, `activate`, `created_at`
- FK internal: tidak ada

### `sys_notifications`
Notifikasi (lonceng/user menu). `user_id`=login penerima, NULL=broadcast semua user; `link`=tujuan saat diklik; `is_read`=status baca.
- PK: `id`
- Kolom: `id`, `user_id` (varchar, bukan FK numerik), `title`, `message`, `type` (info/success/warning/error), `link`, `is_read`, `created_at`
- FK internal: tidak ada (index user_id+is_read+created_at)

### `sys_quotes`
Kata-kata penyemangat di halaman utama & login. Ambil acak: `WHERE is_active=1 ORDER BY RAND() LIMIT 1`; `is_active`=nonaktif tanpa hapus (catatan comment asli pakai istilah `is_active`, kolom fisik `activate`).
- PK: `id`
- Kolom: `id`, `quote_text`, `author`, `activate`, `created_at`
- FK internal: tidak ada

### `sys_setting`
Setting umum aplikasi (key-value): active_theme, default_language, app_version, dll. Fallback level-aplikasi.
- PK: `setting_key` (varchar)
- Kolom: `setting_key`, `setting_value`
- FK internal: tidak ada

### `sys_support_replies`
Balasan pada tiket Help & Support (`sys_support_ticket`).
- PK: `id_reply`
- Kolom: `id_reply`, `ticket_id`, `author` (login/nama pembalas), `body` (text), `created_at`
- FK internal: `ticket_id -> sys_support_ticket`

### `sys_support_ticket`
Tiket Help & Support Center. `status`: OPEN/IN_PROGRESS/DONE/CLOSED — selaras `qc_feedback.md`.
- PK: `id_ticket`
- Kolom: `id_ticket`, `ticket_no` (unique, format SPT{YY}{MM}{NNNN}), `created_by_user_id`, `screen_context`, `description` (text), `status`, `resolution_note` (text), `resolved_by`, `resolved_at`, `created_at`, `updated_at`
- FK internal: `created_by_user_id -> sec_user`

### `sys_support_ticket_attachment`
Lampiran file pada tiket support.
- PK: `id_attachment`
- Kolom: `id_attachment`, `ticket_id`, `file_path`, `uploaded_at`
- FK internal: `ticket_id -> sys_support_ticket`

### `sys_theme`
Preset tema tampilan (matrix 4 mode x 3 corak = 12 kombinasi + tema "Klasik" lama). Tema aktif per-user di `sec_user.theme_code`; default aplikasi di `sys_setting.active_theme`.
- PK: `code` (varchar)
- Kolom: `code`, `name`, `primary_color`, `sidebar_color`, `mode` (light/dark), `family` (pastel/cerah/dark/hitam, NULL=Klasik), `hue` (blue/green/red, NULL=Klasik), `content_bg_color`, `is_default`, `activate`, `sort_order`
- FK internal: tidak ada

### `sys_translations`
Teks UI per bahasa. `lang_code` -> `sys_languages.code`; `trans_key`=kunci teks (unik per bahasa).
- PK: `id`
- Kolom: `id`, `lang_code`, `trans_key`, `trans_value`
- FK internal: `lang_code -> sys_languages`
- Unique: (`lang_code`, `trans_key`)

### `sys_user_menu`
Menu profil (dropdown avatar): chat, notifikasi, AI, edit profil, ubah password, setup, bahasa, keluar. Item & urutan diatur dari tabel ini (data-driven, lihat memory `project_queue_item9_pending`).
- PK: `id_umenu`
- Kolom: `id_umenu`, `section`, `code` (unique), `label`, `icon`, `action_type` (drawer/modal/menu/url/lang/logout), `action_target`, `badge_source` (chat/notif/NULL), `is_danger`, `admin_only`, `visibility` (all/employee_linked/non_employee_linked/setup_non_admin), `sort_order`, `activate`, `created_at`, `updated_at`
- FK internal: tidak ada

## Catatan
- Ini koneksi DEFAULT Laravel (lihat memory `project_default_db_connection`) — semua tabel framework (`cache*`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions`) hidup di sini, bukan di tm2mst.
- Prefix dominan: `sec_` (auth/permission), `sys_` (sistem/UI/config).
- Beberapa tabel pakai kolom varchar sebagai referensi user (bukan FK numerik ke `sec_user.id_user`): `sys_chat_participants.user_id`, `sys_notifications.user_id`, `sys_grid_preference.user_login`, `sys_support_replies.author` — semua menyimpan `sec_user.login` sebagai string, dijaga di level app.
- `sec_user.active` (legacy) vs `sec_user.activate` (hasil rename DEC-FIELDNAME-STATUS) — dua kolom terpisah yang sengaja TIDAK digabung (lihat CLAUDE.md §3).
- Tidak ditemukan tabel `sys_theme` terkait payroll encryption (`tm2pyr`) — modul itu belum dibangun sama sekali, sesuai catatan CLAUDE.md.
