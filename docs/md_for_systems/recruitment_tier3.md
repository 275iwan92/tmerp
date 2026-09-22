# TIER 3 — RECRUITMENT · DATABASE & DATA PROCESS FLOW
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## 1. PENEMPATAN DATABASE & ARSITEKTUR 2-HOST

| Bagian | Lokasi | Alasan |
|---|---|---|
| `rcr_*`, `vac_*`, `cnd_*`, `app_*` | `tm2hrr` (internal, jaringan privat) | Domain transaksi rekrutmen inti — sesuai `globalarchitecture_tier0.md` |
| Portal kandidat (submission mentah, sebelum divalidasi) | **Host terpisah, DMZ/publik** — **Plan A: Firebase** (Firestore+Auth+Storage, region `asia-southeast2`), **Plan B (cadangan): `tm2crp`** MariaDB ringan di VPS terpisah | Isolasi total dari jaringan internal — kalau portal diretas, attacker tidak dapat jalan ke `tm2hrr` |

### 1.1 Kenapa arsitektur ini aman untuk 2 rencana backend sekaligus

Internal (`tm2hrr`) **tidak pernah tahu** portal pakai Firebase atau MariaDB — internal hanya bicara ke satu **interface Laravel**:

```php
interface PortalSyncAdapterInterface {
    public function fetchPendingSubmissions(int $limit): array;
    public function markSynced(string $submissionUuid): void;
    public function pushStatusUpdate(string $submissionUuid, string $status): void;
}

class FirebaseSyncAdapter implements PortalSyncAdapterInterface { /* Plan A */ }
class MariaDbPortalSyncAdapter implements PortalSyncAdapterInterface { /* Plan B */ }
```

Pemilihan implementasi lewat `config/recruitment.php` → `PORTAL_BACKEND=firebase|mariadb` (env). **Ini jawaban atas pertanyaan "bisa kah punya cadangan?" — bisa, karena seluruh domain logic (dedupe KTP, insert `cnd_candidate`/`app_application`, validasi) berada di service layer internal yang generic, hanya method `fetchPendingSubmissions()` yang beda implementasi.** Ganti Plan A↔B = ganti 1 baris config + adapter class, tanpa sentuh business logic.

### 1.2 Model sinkronisasi: PULL, bukan PUSH

- Internal (`tm2hrr`) **menginisiasi koneksi keluar** secara berkala (scheduled job, tiap 1–2 menit) untuk menarik submission baru. Portal **tidak pernah** memanggil masuk ke jaringan internal.
- Arah sebaliknya (update status lamaran ke kandidat) juga internal yang push keluar ke portal (`pushStatusUpdate`) — portal cuma tampilkan, tidak memproses bisnis apa pun.
- Idempotency: tiap submission portal punya `submission_uuid` unik. Internal cek `rcr_sync_log` sebelum insert — re-fetch akibat retry job tidak boleh dobel insert `cnd_candidate`/`app_application`.

---

## 2. PLAN A — FIREBASE (skema koleksi, bukan SQL)

Project Firebase terpisah (bukan bagian dari 10 DB `tm2*`), region `asia-southeast2` (Jakarta, kepatuhan data pribadi UU PDP).

```
Firestore collections:
├── candidates/{uid}                      (1 dokumen per akun portal)
│     national_id, name, email, phone, birth_date, address, ...
│     ├── family_members/{auto_id}        (relation_type, name, current_job, current_employer)
│     ├── education/{auto_id}             (level, institution, major, graduation_year)
│     └── work_experience/{auto_id}       (company, position, start_date, end_date, description)
│
├── submissions/{submission_uuid}
│     candidate_uid, vacancy_ref, submitted_at,
│     sync_status: 'pending' | 'synced',
│     synced_at (null sampai ditarik internal)
│
└── vacancy_public/{vacancy_code}         (mirror read-only dari vac_vacancy.is_published=1, untuk ditampilkan di portal)
```

- **Auth**: Firebase Authentication (email/password atau OTP HP), free sampai 50.000 MAU — jauh di atas kebutuhan realistis TMERP.
- **Storage**: Firebase Storage untuk CV/ijazah/sertifikat, security rules dibatasi per-`uid` kandidat sendiri. Saat sync, worker download via Admin SDK lalu simpan ke storage internal (arsip permanen tidak bergantung Firebase jangka panjang).
- **Validasi ulang wajib di sisi internal saat pull**: re-scan file (mime-type by content, bukan ekstensi; blokir `.exe/.php/.js`), sanitasi teks input — jangan percaya mentah data dari DMZ.
- **`FirebaseSyncAdapter::fetchPendingSubmissions()`**: query `submissions` WHERE `sync_status == 'pending'` LIMIT n, pakai `kreait/firebase-php` (Admin SDK untuk PHP/Laravel).

---

## 3. PLAN B — MARIADB `tm2crp` (cadangan, DDL siap pakai)

Aktif hanya jika Firebase tidak disetujui. Host terpisah (VPS kecil, DMZ), **bukan** bagian dari cluster Docker internal.

```sql
-- ========================================================
-- tm2crp.crp_candidate_account — akun portal (analog Firebase Auth)
-- ========================================================
CREATE TABLE crp_candidate_account (
    id_account BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Akun login portal kandidat, Plan B pengganti Firebase Auth';

-- ========================================================
-- tm2crp.crp_submission — draft/final lamaran (analog Firestore submissions)
-- ========================================================
CREATE TABLE crp_submission (
    submission_uuid CHAR(36) PRIMARY KEY,
    id_account BIGINT UNSIGNED NOT NULL,
    vacancy_code VARCHAR(30) NOT NULL COMMENT 'referensi vac_vacancy.vacancy_code, logical FK lintas host',
    national_id VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    submitted_at DATETIME NOT NULL,
    sync_status ENUM('pending','synced') NOT NULL DEFAULT 'pending',
    synced_at DATETIME NULL,
    CONSTRAINT fk_submission_account FOREIGN KEY (id_account) REFERENCES crp_candidate_account(id_account),
    INDEX idx_submission_sync (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Lamaran mentah dari portal, ditarik internal via API read-only';

CREATE TABLE crp_family_member (
    id_family BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL,
    relation_type ENUM('FATHER','MOTHER','FATHER_IN_LAW','MOTHER_IN_LAW','SPOUSE','CHILD') NOT NULL,
    name VARCHAR(150) NOT NULL,
    current_job VARCHAR(150) NULL,
    current_employer VARCHAR(150) NULL,
    CONSTRAINT fk_family_submission FOREIGN KEY (submission_uuid) REFERENCES crp_submission(submission_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crp_education (
    id_edu BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL,
    level VARCHAR(30) NOT NULL COMMENT 'SD/SMP/SMA/D3/S1/S2/dst',
    institution VARCHAR(150) NOT NULL,
    major VARCHAR(100) NULL,
    graduation_year SMALLINT UNSIGNED NULL,
    CONSTRAINT fk_edu_submission FOREIGN KEY (submission_uuid) REFERENCES crp_submission(submission_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crp_work_experience (
    id_work BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL,
    company VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    description TEXT NULL,
    CONSTRAINT fk_work_submission FOREIGN KEY (submission_uuid) REFERENCES crp_submission(submission_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crp_document (
    id_doc BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL,
    doc_type ENUM('CV','IJAZAH','SERTIFIKAT','KTP','FOTO','LAINNYA') NOT NULL,
    file_path VARCHAR(255) NOT NULL COMMENT 'path di object storage portal, bukan BLOB di DB',
    uploaded_at DATETIME NOT NULL,
    CONSTRAINT fk_doc_submission FOREIGN KEY (submission_uuid) REFERENCES crp_submission(submission_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- Exposed hanya via **REST API read-only, token khusus internal, IP-allowlist** ke server `tm2hrr` — bukan koneksi SQL langsung dibuka ke internal.
- `MariaDbPortalSyncAdapter::fetchPendingSubmissions()` panggil endpoint tsb, mapping response ke struktur yang sama dipakai `FirebaseSyncAdapter` (kontrak data identik walau sumber beda — lihat DTO `PortalSubmissionDTO` di §5).

---

## 4. DDL — `tm2hrr` (internal, berlaku terlepas Plan A/B yang dipilih)

```sql
-- ========================================================
-- RCR_TRACK — master, extensible ("dan lain-lain")
-- ========================================================
CREATE TABLE rcr_track (
    id_track TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Klasifikasi rekrutmen (STAFF/NON_STAFF/BORONGAN/dst), Tier 1 SECTION 2.4';

-- ========================================================
-- RCR_REQUISITION — permohonan tenaga kerja
-- ========================================================
CREATE TABLE rcr_requisition (
    id_requisition BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requisition_no VARCHAR(20) NOT NULL UNIQUE COMMENT 'Format RCR{YY}{NNNNN}',
    id_track TINYINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL COMMENT 'FK logical org_department (tm2mst)',
    position_title VARCHAR(150) NOT NULL,
    headcount SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    qualification TEXT NOT NULL,
    reason TEXT NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL COMMENT 'FK logical sec_user (tm2sys)',
    status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','CLOSED') NOT NULL DEFAULT 'DRAFT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requisition_track FOREIGN KEY (id_track) REFERENCES rcr_track(id_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Permohonan tenaga kerja, Tier 1 SECTION 3';

-- ========================================================
-- RCR_APPROVAL_MATRIX — config (Tier 2 SECTION 2)
-- ========================================================
CREATE TABLE rcr_approval_matrix (
    id_matrix BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_track TINYINT UNSIGNED NOT NULL,
    approval_seq TINYINT UNSIGNED NOT NULL,
    approver_role VARCHAR(50) NOT NULL COMMENT 'Dept Head/HR Manager/Direksi — resolusi ke user di 08_security',
    UNIQUE KEY uq_matrix_track_seq (id_track, approval_seq),
    CONSTRAINT fk_matrix_track FOREIGN KEY (id_track) REFERENCES rcr_track(id_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- RCR_REQUISITION_APPROVAL — histori approval berjalan
-- ========================================================
CREATE TABLE rcr_requisition_approval (
    id_approval BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_requisition BIGINT UNSIGNED NOT NULL,
    approval_seq TINYINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL COMMENT 'FK logical sec_user',
    decision ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    decided_at DATETIME NULL,
    note VARCHAR(255) NULL,
    CONSTRAINT fk_reqapproval_requisition FOREIGN KEY (id_requisition) REFERENCES rcr_requisition(id_requisition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- VAC_VACANCY — posting/iklan lowongan
-- ========================================================
CREATE TABLE vac_vacancy (
    id_vacancy BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacancy_code VARCHAR(30) NOT NULL UNIQUE,
    id_requisition BIGINT UNSIGNED NOT NULL,
    channel ENUM('PORTAL_SENDIRI','JOBSTREET','LINKEDIN','REFERRAL','LAINNYA') NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    closed_at DATETIME NULL,
    CONSTRAINT fk_vacancy_requisition FOREIGN KEY (id_requisition) REFERENCES rcr_requisition(id_requisition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='1 requisition bisa banyak vacancy/channel, Tier 1 SECTION 2.1';

-- ========================================================
-- CND_CANDIDATE — talent pool, persisten
-- ========================================================
CREATE TABLE cnd_candidate (
    id_candidate BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    national_id VARCHAR(20) NOT NULL UNIQUE COMMENT 'KTP, dedupe key utama — Tier 2 SECTION 6',
    passport_no VARCHAR(30) NULL COMMENT 'fallback key untuk WNA, Open Item Tier 1 #5',
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    birth_date DATE NULL,
    address TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Talent pool, tidak pernah dihapus saat reject — Tier 2 SECTION 6';

CREATE TABLE cnd_family_member (
    id_family BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    relation_type ENUM('FATHER','MOTHER','FATHER_IN_LAW','MOTHER_IN_LAW','SPOUSE','CHILD') NOT NULL,
    name VARCHAR(150) NOT NULL,
    current_job VARCHAR(150) NULL,
    current_employer VARCHAR(150) NULL,
    CONSTRAINT fk_family_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Current job saja, tanpa histori — keputusan Telma Tier 2 SECTION 6';

CREATE TABLE cnd_education (
    id_edu BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    level VARCHAR(30) NOT NULL,
    institution VARCHAR(150) NOT NULL,
    major VARCHAR(100) NULL,
    graduation_year SMALLINT UNSIGNED NULL,
    CONSTRAINT fk_edu_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cnd_work_experience (
    id_work BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    company VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    description TEXT NULL,
    CONSTRAINT fk_work_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Riwayat pendidikan/karir KANDIDAT sendiri TETAP histori (beda dari cnd_family_member)';

CREATE TABLE cnd_document (
    id_doc BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    doc_type ENUM('CV','IJAZAH','SERTIFIKAT','KTP','FOTO','LAINNYA') NOT NULL,
    file_path VARCHAR(255) NOT NULL COMMENT 'path storage internal, setelah disalin dari portal + re-scan',
    uploaded_at DATETIME NOT NULL,
    CONSTRAINT fk_doc_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- APP_PIPELINE_STAGE_CONFIG — config (Tier 2 SECTION 3)
-- ========================================================
CREATE TABLE app_pipeline_stage_config (
    id_stage_config BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_track TINYINT UNSIGNED NOT NULL,
    stage_seq TINYINT UNSIGNED NOT NULL,
    stage_code VARCHAR(20) NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_stageconfig_track_seq (id_track, stage_seq),
    CONSTRAINT fk_stageconfig_track FOREIGN KEY (id_track) REFERENCES rcr_track(id_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- APP_APPLICATION — lamaran (candidate x vacancy), state machine
-- ========================================================
CREATE TABLE app_application (
    id_application BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    id_vacancy BIGINT UNSIGNED NOT NULL,
    submission_uuid CHAR(36) NULL COMMENT 'jejak asal dari portal (Firebase doc id / crp_submission uuid)',
    current_stage_seq TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('APPLIED','IN_PROGRESS','OFFERING','HIRED','REJECTED','WITHDRAWN') NOT NULL DEFAULT 'APPLIED',
    reject_reason VARCHAR(255) NULL,
    provisioned_employee_id BIGINT UNSIGNED NULL COMMENT 'idempotency guard hire trigger — Tier 2 SECTION 7',
    provisioned_at DATETIME NULL,
    applied_at DATETIME NOT NULL,
    CONSTRAINT fk_app_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate),
    CONSTRAINT fk_app_vacancy FOREIGN KEY (id_vacancy) REFERENCES vac_vacancy(id_vacancy),
    INDEX idx_app_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='State pipeline nempel di sini, BUKAN di cnd_candidate — Tier 1 SECTION 2.1';

CREATE TABLE app_interview_schedule (
    id_schedule BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_application BIGINT UNSIGNED NOT NULL,
    stage_code VARCHAR(20) NOT NULL,
    scheduled_at DATETIME NOT NULL,
    interviewer_id BIGINT UNSIGNED NOT NULL COMMENT 'FK logical sec_user',
    location VARCHAR(150) NULL,
    status ENUM('SCHEDULED','DONE','CANCELLED','RESCHEDULED') NOT NULL DEFAULT 'SCHEDULED',
    CONSTRAINT fk_schedule_application FOREIGN KEY (id_application) REFERENCES app_application(id_application)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_assessment_result (
    id_result BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_application BIGINT UNSIGNED NOT NULL,
    stage_code VARCHAR(20) NOT NULL,
    assessor_id BIGINT UNSIGNED NOT NULL COMMENT 'FK logical sec_user',
    score DECIMAL(5,2) NULL COMMENT 'skala ditentukan per jenis assessment, disimpan sbg angka bebas',
    recommendation ENUM('LANJUT','TIDAK_LANJUT','PERTIMBANGAN') NOT NULL,
    notes TEXT NULL,
    assessed_at DATETIME NOT NULL,
    CONSTRAINT fk_result_application FOREIGN KEY (id_application) REFERENCES app_application(id_application)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Data terstruktur — Tier 2 SECTION 4. Print-out = output, bukan sumber';

-- ========================================================
-- RCR_OFFER_APPROVAL_MATRIX — config (Tier 2 SECTION 5)
-- ========================================================
CREATE TABLE rcr_offer_approval_matrix (
    id_matrix BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_track TINYINT UNSIGNED NOT NULL,
    approval_seq TINYINT UNSIGNED NOT NULL,
    approver_role VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_offermatrix_track_seq (id_track, approval_seq),
    CONSTRAINT fk_offermatrix_track FOREIGN KEY (id_track) REFERENCES rcr_track(id_track)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_offer (
    id_offer BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_application BIGINT UNSIGNED NOT NULL,
    previous_offer_id BIGINT UNSIGNED NULL COMMENT 'revisi/negosiasi = row baru, bukan edit in-place',
    proposed_salary DECIMAL(14,2) NOT NULL,
    employment_type ENUM('TETAP','KONTRAK','HARIAN','BORONGAN') NOT NULL COMMENT 'independen dari rcr_track — Tier 1 SECTION 2.4',
    start_date DATE NOT NULL,
    status ENUM('DRAFT','PENDING_APPROVAL','APPROVED','SENT','ACCEPTED','DECLINED','EXPIRED') NOT NULL DEFAULT 'DRAFT',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offer_application FOREIGN KEY (id_application) REFERENCES app_application(id_application)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_offer_approval (
    id_approval BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_offer BIGINT UNSIGNED NOT NULL,
    approval_seq TINYINT UNSIGNED NOT NULL,
    approver_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    decided_at DATETIME NULL,
    CONSTRAINT fk_offerapproval_offer FOREIGN KEY (id_offer) REFERENCES app_offer(id_offer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- RCR_SYNC_LOG — idempotency & audit sinkronisasi portal
-- ========================================================
CREATE TABLE rcr_sync_log (
    id_log BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_uuid CHAR(36) NOT NULL UNIQUE,
    backend_used ENUM('FIREBASE','MARIADB') NOT NULL,
    fetched_at DATETIME NOT NULL,
    result ENUM('CANDIDATE_CREATED','CANDIDATE_UPDATED','FAILED') NOT NULL,
    error_note VARCHAR(255) NULL,
    id_application BIGINT UNSIGNED NULL,
    CONSTRAINT fk_synclog_application FOREIGN KEY (id_application) REFERENCES app_application(id_application)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Idempotency guard — Tier 3 SECTION 1.2';
```

---

## 5. DATA PROCESS FLOW — SYNC WORKER (berlaku sama untuk Plan A/B)

### 5.1 Kontrak data internal (DTO, tidak berubah walau backend beda)

```php
class PortalSubmissionDTO {
    public string $submissionUuid;
    public string $vacancyCode;
    public string $nationalId;
    public string $name;
    public ?string $birthDate;
    public ?string $address;
    public array $familyMembers;   // [relation_type, name, current_job, current_employer]
    public array $education;
    public array $workExperience;
    public array $documents;       // [doc_type, file_url]
}
```

### 5.2 Job Sync (scheduled, tiap 1–2 menit)

1. `PortalSyncAdapterInterface::fetchPendingSubmissions($limit)` → array `PortalSubmissionDTO`.
2. Untuk tiap DTO:
   a. Validasi ulang (mime-type file, sanitasi teks) — **wajib**, jangan percaya data mentah dari DMZ.
   b. Cari `cnd_candidate` by `national_id`. Ketemu → update; tidak ketemu → insert.
   c. Overwrite `cnd_family_member`/`cnd_education` (current job saja, sesuai Tier 2 §6) — hapus baris lama punya kandidat ini, insert baru dari DTO.
   d. `cnd_work_experience` kandidat sendiri **ditambah** (append), bukan overwrite — ini histori kandidat sendiri, beda dari data keluarga.
   e. Download file dari storage portal → re-scan → simpan ke storage internal → insert `cnd_document`.
   f. Insert `app_application` baru (vacancy dari `vacancyCode`, `status=APPLIED`, `current_stage_seq=1`).
   g. Insert `rcr_sync_log` (idempotency guard, `result=CANDIDATE_CREATED/UPDATED`).
   h. Panggil `markSynced($submissionUuid)` ke adapter — portal update `sync_status='synced'`.
3. Jika langkah manapun gagal → `rcr_sync_log.result=FAILED` + `error_note`, **jangan** panggil `markSynced` (submission tetap `pending`, akan dicoba lagi job berikutnya). Retry aman karena idempotent by `submission_uuid` (cek `rcr_sync_log` dulu sebelum insert ulang).

### 5.3 Push status balik ke kandidat

Setiap `app_application.status` berubah (REJECTED/OFFERING/HIRED/dst) → job terpisah panggil `pushStatusUpdate($submissionUuid, $status)` — portal cukup tampilkan, tidak memproses apa pun.

---

## 6. MOBILE ARCHITECTURE — FLUTTER + PUSH NOTIFICATION (wajib rilis pertama)

### 6.1 Kenapa terpisah dari keputusan Plan A/B

FCM (Firebase Cloud Messaging) dipakai untuk push **terlepas** dari Plan A (Firestore) atau Plan B (`tm2crp` MariaDB) yang jalan untuk penyimpanan data submission. Alasan: FCM hanya butuh **Firebase project untuk messaging** (gratis, unlimited, tidak terikat produk Firebase lain) + device token per kandidat — bukan seluruh data candidate harus di Firestore. Kalau Plan B (MariaDB) yang dipilih untuk data, project Firebase kecil terpisah (hanya modul Messaging aktif) tetap dipakai khusus untuk push, dikelola via `kreait/firebase-php` di sisi internal `tm2hrr` yang sama.

### 6.2 Codebase Flutter — struktur target

```
recruitment_portal_flutter/
├── lib/
│   ├── screens/          (P1-P4, Tier 4 §1.1)
│   ├── services/
│   │   ├── auth_service.dart        (Firebase Auth ATAU REST ke crp_candidate_account, tergantung backend)
│   │   ├── submission_service.dart  (submit form ke Firestore/crp_submission)
│   │   └── push_service.dart        (register FCM token, handle foreground/background message)
│   └── models/            (DTO sama bentuknya dengan PortalSubmissionDTO §5.1, dijaga konsisten manual)
├── android/                (build APK/AAB untuk Play Store)
├── ios/                    (build IPA untuk App Store, butuh akun Apple Developer)
└── web/                    (build web, hosting Firebase Hosting atau server sendiri)
```

- 1 basis kode Dart menghasilkan APK (Android), IPA (iOS), dan bundle web — build terpisah per target tapi source sama.
- `push_service.dart` request permission notifikasi saat login pertama (wajib eksplisit di Android 13+ dan iOS), lalu simpan token via `submission_service` ke `cnd_device_token`.

### 6.3 DDL — `tm2hrr.cnd_device_token`

```sql
CREATE TABLE cnd_device_token (
    id_token BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_candidate BIGINT UNSIGNED NOT NULL,
    fcm_token VARCHAR(255) NOT NULL,
    platform ENUM('ANDROID','IOS','WEB') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    registered_at DATETIME NOT NULL,
    last_error VARCHAR(100) NULL COMMENT 'NotRegistered/InvalidRegistration dst, Tier 2 SECTION 8.2',
    error_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_devicetoken_candidate FOREIGN KEY (id_candidate) REFERENCES cnd_candidate(id_candidate),
    UNIQUE KEY uq_devicetoken_token (fcm_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Device token FCM per kandidat, multi-device (HP+web) — Tier 2 SECTION 8.2';
```

- 1 kandidat bisa punya banyak token aktif (ganti HP, login web+mobile bersamaan) — push dikirim ke semua token `is_active=1`.
- Worker push (Tier 3 §5.3, diperluas): setelah `pushStatusUpdate()` ke portal, panggil FCM Admin SDK kirim message ke semua token kandidat terkait, increment `error_count` bila gagal, set `is_active=0` setelah >5x gagal (Tier 2 §8.2).

### 6.4 Publikasi app store — checklist non-teknis (dicatat, bukan diproses sistem)

- **Google Play**: akun developer $25 (sekali bayar), privacy policy URL wajib (karena app menangani data pribadi termasuk KTP/data keluarga), review biasanya 1-3 hari.
- **Apple App Store**: akun Apple Developer Program $99/tahun, privacy policy + "App Privacy" declaration wajib diisi detail (data KTP/keluarga = kategori sensitif, harus dideklarasikan jujur), review bisa 1-2 minggu (lebih lambat dari Google).
- Ini item operasional Anda (submit ke store), bukan sesuatu yang bisa dikerjakan Claude Code — dicatat di sini supaya tidak lupa saat rilis.

## 7. SEED WAJIB

- `rcr_track`: STAFF, NON_STAFF, BORONGAN (Tier 1 §2.4).
- `rcr_approval_matrix` & `rcr_offer_approval_matrix`: sesuai Tier 2 §2 dan §5 — **jangan seed sebelum nama role/jabatan riil dikonfirmasi Telma** (Tier 1 Open Item #1).
- `app_pipeline_stage_config`: sesuai Tier 2 §3.

## 8. CHECKLIST DBA (urutan deploy)

1. Pastikan `tm2hrm.emp_employee`/`org_department`/`org_position` sudah live (prasyarat).
2. Deploy seluruh tabel `tm2hrr` (§4) — urutan: `rcr_track` → `rcr_requisition`+`rcr_approval_matrix`+`rcr_requisition_approval` → `vac_vacancy` → `cnd_*` → `app_pipeline_stage_config` → `app_application`+turunannya → `rcr_offer_approval_matrix`+`app_offer`+`app_offer_approval` → `rcr_sync_log`.
3. **Pilih & deploy portal**: Plan A (buat Firebase project, region Jakarta, aktifkan Auth+Firestore+Storage, set budget alert Google Cloud) **atau** Plan B (provision VPS kecil, deploy DDL `tm2crp` §3, setup REST API read-only+token+IP-allowlist).
4. Set `PORTAL_BACKEND` env sesuai pilihan langkah 3, implementasikan adapter yang sesuai.
5. Seed §6 — approval matrix **hanya setelah** Open Item Tier 1 #1 dikonfirmasi.
6. Jalankan scheduled job sync (§5.2) — test dengan 1 submission dummy sebelum go-live penuh.
7. Setup project Firebase khusus Messaging (FCM) — terpisah dari keputusan Plan A/B data (§6.1), deploy `cnd_device_token` (§6.3), integrasikan `push_service.dart` di app Flutter (§6.2).
8. Siapkan akun Google Play ($25) & Apple Developer ($99/tahun) + privacy policy sebelum submit ke store (§6.4) — mulai proses ini lebih awal karena review Apple bisa 1-2 minggu.
