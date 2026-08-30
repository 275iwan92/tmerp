-- tm2crp — Plan B portal database (MariaDB, host terpisah/VPS di desain final,
-- lokal dulu di mesin dev per instruksi user 2026-08-30).
-- Sumber dasar: docs/recruitment_tier3.md §3 — DIPERLUAS 2026-08-30 (lihat
-- docs/recruitment_delta_plan_ab_local.md) karena Tier 3 ditulis SEBELUM form
-- kandidat diperluas jadi ~45 kolom (migration 2026_08_29_000010_expand_
-- candidate_form_fields.php). crp_submission sekarang mirror 1:1 kolom skalar
-- cnd_candidate supaya sync worker bisa map langsung tanpa transformasi rumit;
-- seksi berulang (keluarga/pendidikan/pengalaman kerja/dst) disimpan JSON di
-- crp_submission (bukan tabel relasional terpisah seperti cnd_*) karena ini
-- layer staging mentah yang di-unpack ulang oleh worker ke tabel cnd_* asli --
-- analog subcollection/array Firestore di Plan A, supaya struktur Plan A/B
-- sefaham (adapter tinggal decode array yang sama).
--
-- tm2crp BUKAN bagian dari 10 DB tm2* yang di-manage Laravel migrate (§1: host
-- terpisah dari internal) — DDL dijalankan manual/deploy script, disimpan di
-- sini sebagai referensi version-controlled.

DROP TABLE IF EXISTS crp_document;
DROP TABLE IF EXISTS crp_submission;
DROP TABLE IF EXISTS crp_candidate_account;

-- ========================================================
-- crp_candidate_account — akun portal (analog Firebase Auth)
-- ========================================================
CREATE TABLE crp_candidate_account (
    id_account BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    national_id VARCHAR(20) NOT NULL UNIQUE COMMENT 'identitas login simulasi portal (KTP-only, lihat recruitment_state.yaml) -- email/password menyusul saat portal real dibangun',
    email VARCHAR(150) NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Akun login portal kandidat, Plan B pengganti Firebase Auth';

-- ========================================================
-- crp_submission — draft/final lamaran, DIPERLUAS mirror cnd_candidate.
-- ========================================================
CREATE TABLE crp_submission (
    submission_uuid CHAR(36) PRIMARY KEY,
    id_account BIGINT UNSIGNED NOT NULL,
    vacancy_code VARCHAR(30) NOT NULL COMMENT 'referensi vac_vacancy.vacancy_code, logical FK lintas host',

    -- data pribadi (mirror cnd_candidate scalar)
    national_id VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    nickname VARCHAR(100) NULL,
    gender VARCHAR(10) NULL,
    religion VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    address_domisili TEXT NULL,
    vehicle_type VARCHAR(20) NULL,
    sim_types JSON NULL,
    marital_status VARCHAR(20) NULL,
    marital_date DATE NULL,

    -- sosial & referensi
    company_acquaintance VARCHAR(255) NULL,
    hobby VARCHAR(150) NULL,
    leisure_activity VARCHAR(255) NULL,
    reading_topics VARCHAR(255) NULL,
    went_abroad TEXT NULL,
    emergency_contacts JSON NULL,
    professional_references JSON NULL,

    -- minat & konsep pribadi
    why_join_us TEXT NULL,
    what_you_know_about_us TEXT NULL,
    contribution_plan TEXT NULL,
    role_plan_if_hired TEXT NULL,
    job_type_preference TEXT NULL,
    job_nature_preference TEXT NULL,
    work_environment_preference TEXT NULL,
    hindering_environment TEXT NULL,
    preferred_colleague_type TEXT NULL,
    difficult_decision_topic TEXT NULL,
    strengths_weaknesses TEXT NULL,
    who_you_ask_when_stuck TEXT NULL,

    -- kesehatan
    height_cm SMALLINT UNSIGNED NULL,
    weight_kg SMALLINT UNSIGNED NULL,
    blood_type VARCHAR(5) NULL,
    illness_history TEXT NULL,
    hospitalized_history TEXT NULL,
    is_smoker TINYINT(1) NULL,
    physical_disability TEXT NULL,
    family_health_ok TINYINT(1) NULL,

    -- kemampuan & info lowongan
    language_skills JSON NULL,
    computer_skills JSON NULL,
    still_receiving_aid TEXT NULL,
    other_dependents TEXT NULL,
    source_of_info VARCHAR(150) NULL,
    expected_salary DECIMAL(14,2) NULL,
    expected_facility TEXT NULL,
    availability_date DATE NULL,

    -- kesediaan & pernyataan
    willingness_checklist JSON NULL,
    agreement_signed TINYINT(1) NOT NULL DEFAULT 0,
    signature_name VARCHAR(150) NULL,
    agreement_signed_at DATETIME NULL,

    -- seksi berulang, staged JSON (worker unpack -> cnd_family_member/
    -- cnd_education/cnd_education_nonformal/cnd_organization_experience/
    -- cnd_work_experience). Bentuk array tiap elemen SAMA PERSIS nama kolom
    -- tabel cnd_* tujuan supaya worker tinggal spread, bukan re-mapping.
    family_members JSON NULL,
    education JSON NULL,
    education_nonformal JSON NULL,
    org_experience JSON NULL,
    work_experience JSON NULL,

    submitted_at DATETIME NOT NULL,
    sync_status ENUM('pending','synced') NOT NULL DEFAULT 'pending',
    app_status VARCHAR(30) NULL COMMENT 'mirror app_application.status, ditulis balik internal via pushStatusUpdate() -- recruitment_tier3.md §1.2',
    status_pushed_at DATETIME NULL,
    synced_at DATETIME NULL,

    CONSTRAINT fk_submission_account FOREIGN KEY (id_account) REFERENCES crp_candidate_account(id_account),
    INDEX idx_submission_sync (sync_status),
    INDEX idx_submission_national_id (national_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Lamaran mentah dari portal, ditarik internal via API read-only';

-- ========================================================
-- crp_document — file upload per submission (CV/ijazah/dst), tetap tabel
-- relasional (bukan JSON) karena representasi "1 baris = 1 file" alami.
-- ========================================================
CREATE TABLE crp_document (
    id_doc BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL,
    doc_type ENUM('CV','IJAZAH','SERTIFIKAT','KTP','FOTO','LAINNYA') NOT NULL,
    file_path VARCHAR(255) NOT NULL COMMENT 'path di object storage portal, bukan BLOB di DB',
    uploaded_at DATETIME NOT NULL,
    CONSTRAINT fk_doc_submission FOREIGN KEY (submission_uuid) REFERENCES crp_submission(submission_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
