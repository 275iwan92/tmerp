---
name: db_hrr
description: Recruitment (tm2hrr) — 18+ tabel real (requisition/vacancy/candidate/application/offer), dump lokal STALE, sumber kebenaran migration
---

# Database: tm2hrr (koneksi Eloquent: `tm2hrr` — nama koneksi literal, bukan alias pendek `hrr`)

**Sumber DDL**: `docs/ddl/tm2hrr.sql` **KOSONG (0 tabel)** — dump ini STALE (bertanggal 2026-08-28, sebelum modul Recruitment selesai dibangun). Sumber kebenaran sebenarnya: migration `database/migrations/2026_08_29_000003_create_recruitment_tm2hrr_schema.php` (+ `2026_08_29_000010_expand_candidate_form_fields.php` untuk kolom tambahan).
**Jumlah tabel**: 18 (dari migration utama) + kemungkinan tabel/kolom tambahan dari migration ekspansi (tidak dibaca detail di sesi ini).

## TEMUAN PENTING
Dump DDL lokal (`docs/ddl/tm2hrr.sql`) menampilkan 0 tabel, TAPI 20 file model Eloquent riil ada di `app/Models/Hrr/*.php` semua dgn `protected $connection = 'tm2hrr'`, dan modul Recruitment terverifikasi LIVE & dipakai (lihat `docs/screens/screen_recruitment_*.md`). Ini BUKAN modul kosong — dump-nya saja belum pernah di-refresh setelah migration `2026_08_29_000003` dijalankan. Jangan percaya dump ini untuk status "belum dibangun" — cek migration/kode dulu.

## Daftar Tabel (dari migration, bukan dump)

### `rcr_track`
Kategori jalur rekrutmen (kode+nama, `is_active`).
- PK: `id_track` (tinyIncrements)
- Kolom: `code`, `name`, `is_active`

### `rcr_requisition`
Permintaan rekrutmen dari departemen.
- PK: `id_requisition`
- Kolom: `requisition_no` (format RCR{YY}{NNNNN}), `id_track` (FK->rcr_track), `department_id` (LOGICAL->tm2mst.org_department), `position_title`, `headcount`, `qualification`, `reason`, `requested_by` (LOGICAL->tm2sys.sec_user), `status` (DRAFT/SUBMITTED/APPROVED/REJECTED/CLOSED)

### `rcr_approval_matrix`
Matrix approval per track+seq (role approver).
- PK: `id_matrix`
- Kolom: `id_track` (FK), `approval_seq`, `approver_role`

### `rcr_requisition_approval`
Histori keputusan approval per requisition.
- PK: `id_approval`
- Kolom: `id_requisition` (FK, cascade), `approval_seq`, `approver_id` (LOGICAL->tm2sys.sec_user), `decision` (PENDING/APPROVED/REJECTED), `decided_at`, `note`

### `rcr_approver_assignment`
Assignment role approver ke user sungguhan (workaround krn `org_position` belum lengkap ter-mapping — lihat BACKLOG.md §8).
- PK: `id_assignment`
- Kolom: `approver_role`, `sec_user_id` (LOGICAL->tm2sys.sec_user), `department_id` (LOGICAL->tm2mst.org_department, NULL=semua departemen), `notes`, `activate`

### `vac_vacancy`
Lowongan kerja.
- PK: `id_vacancy`
- Kolom: `vacancy_code`, `id_requisition` (FK), `channel` (PORTAL_SENDIRI/JOBSTREET/LINKEDIN/REFERRAL/LAINNYA), `title`, `description`, `is_published`, `published_at`, `closed_at`

### `cnd_candidate`
Data induk kandidat (talent pool permanen).
- PK: `id_candidate`
- Kolom: `national_id` (KTP, dedupe key utama, unique), `passport_no` (fallback WNA), `name`, `email`, `phone`, `birth_date`, `address`, `activate` (talent pool tidak dihapus saat reject, ini toggle manual)

### `cnd_family_member`
- PK: `id_family`
- Kolom: `id_candidate` (FK cascade), `relation_type` (FATHER/MOTHER/FATHER_IN_LAW/MOTHER_IN_LAW/SPOUSE/CHILD), `name`, `current_job`, `current_employer`

### `cnd_education`
- PK: `id_edu`
- Kolom: `id_candidate` (FK cascade), `level`, `institution`, `major`, `graduation_year`

### `cnd_education_nonformal`
Model `CndEducationNonformal` ada — skema detail TBD (dari migration ekspansi `2026_08_29_000010`, tidak dibaca detail sesi ini).

### `cnd_organization_experience`
Model `CndOrganizationExperience` ada — skema detail TBD (kemungkinan dari migration ekspansi).

### `cnd_work_experience`
- PK: `id_work`
- Kolom: `id_candidate` (FK cascade), `company`, `position`, `start_date`, `end_date`, `description`

### `cnd_document`
Dokumen upload kandidat — skema lengkap TBD (baris terpotong saat ekstraksi di sesi ini).

### `app_pipeline_stage_config`
Konfigurasi tahap pipeline seleksi — skema detail TBD.

### `app_application`
Lamaran kandidat ke 1 vacancy — entitas sentral (dipakai `AppApplicationObserver` push status ke portal publik). Skema detail TBD (baris terpotong).

### `app_interview_schedule`
Jadwal interview — skema detail TBD.

### `app_assessment_result`
Hasil asesmen — skema detail TBD.

### `rcr_offer_approval_matrix`
Matrix approval khusus utk offer (beda dari `rcr_approval_matrix` requisition).

### `app_offer`
Penawaran kerja ke kandidat — skema detail TBD.

### `app_offer_approval`
Histori approval offer.

### `rcr_sync_log`
Log sinkronisasi dari portal eksternal (Firebase/tm2crp) — idempotensi key (lihat `PortalSyncService`, `submission_uuid`).

## Catatan
- **FK lintas-DB ditulis LOGICAL saja** (komentar eksplisit di migration: "LOGICAL -> tm2mst.org_department", "LOGICAL -> tm2sys.sec_user") — konsisten aturan CLAUDE.md §2 (FK MySQL tidak bisa lintas-database).
- Beberapa tabel skemanya belum dibaca lengkap di sesi dokumentasi ini (ekstraksi terhenti sebelum akhir file migration) — kalau butuh detail penuh, baca `database/migrations/2026_08_29_000003_create_recruitment_tm2hrr_schema.php` baris 163 ke bawah + migration `2026_08_29_000010_expand_candidate_form_fields.php`.
- **Rekomendasi**: refresh `docs/ddl/tm2hrr.sql` dgn dump baru dari DB lokal supaya dokumen DDL project sinkron dgn kenyataan.
