/*
 DDL — flow_* : Unified Document Flow & Approval Engine
 Konteks   : TMERP — CV Telasih Makmur Group
 Menggantikan sepenuhnya:
   - m_authorize / m_authorize_detail (skema lama, approval-only, username hardcode)
   - doc_state_log (infra lama, state-only, terpisah dari approval)
 Konsep    : satu engine untuk SEMUA modul (PR, PO, CO, DO, DN, dst) — approval
             adalah salah satu JENIS step dalam alur dokumen, bukan sistem terpisah.

 Lokasi DB : tmmst (master netral, dipakai lintas modul via VIEW read-only
             sesuai pola TIER_0_GLOBAL_ARCHITECTURE.md §3)

 [DELTA] Prasyarat: tabel org_position (HRM) & tabel user/akun (mis. sys_user)
         harus sudah ada — FK position_id / user_id menunjuk ke sana.
         Sesuaikan nama tabel/kolom referensi sebelum eksekusi (FK di-comment).
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. flow_config — konfigurasi alur per modul (+ opsional per divisi)
-- ============================================================
DROP TABLE IF EXISTS `flow_config`;
CREATE TABLE `flow_config` (
  `flow_config_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_code`    VARCHAR(50)  NOT NULL COMMENT 'Kode modul: PR, PO, CO, DO, DN, PV, HR, OP, AD, dst',
  `division_id`    BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '[DELTA] FK org_division. NULL = company-wide (fallback jika tidak ada config khusus SBU)',
  `total_step`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total step dalam alur (termasuk step non-approval)',
  `notes`          VARCHAR(200) NULL DEFAULT NULL COMMENT 'Keterangan konfigurasi',
  `is_active`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=nonaktif (versi lama config, tidak dihapus)',
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     VARCHAR(200) NULL DEFAULT NULL,
  `updated_by`     VARCHAR(200) NULL DEFAULT NULL,
  PRIMARY KEY (`flow_config_id`) USING BTREE,
  UNIQUE INDEX `uq_flowconfig_module_division_active` (`module_code` ASC, `division_id` ASC, `is_active` ASC) USING BTREE,
  INDEX `idx_flowconfig_division` (`division_id` ASC) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Konfigurasi alur dokumen per modul, opsional per divisi (SBU). Induk dari flow_config_step'
  ROW_FORMAT = Dynamic;

-- ============================================================
-- 2. flow_config_step — tiap step alur: transisi status + (opsional) approval
-- ============================================================
DROP TABLE IF EXISTS `flow_config_step`;
CREATE TABLE `flow_config_step` (
  `flow_step_id`     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `flow_config_id`   BIGINT UNSIGNED NOT NULL COMMENT 'FK flow_config',
  `step_no`          INT UNSIGNED NOT NULL COMMENT 'Urutan step (1, 2, 3, ...)',
  `step_type`        VARCHAR(20) NOT NULL DEFAULT 'APPROVAL' COMMENT 'APPROVAL = butuh persetujuan manusia | NOTIFY_ONLY = info saja, auto-lanjut | AUTO = sistem yang trigger (event modul lain), tanpa orang',
  `status_from`      VARCHAR(30) NOT NULL COMMENT 'Status dokumen sebelum step ini dieksekusi',
  `status_to`        VARCHAR(30) NOT NULL COMMENT 'Status dokumen sesudah step ini disetujui/dijalankan',
  `status_to_reject` VARCHAR(30) NULL DEFAULT NULL COMMENT 'Status dokumen jika step_type=APPROVAL dan ditolak. NULL bila tidak relevan (mis. step non-approval)',
  `position_id`      BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '[DELTA] FK org_position — penanggung jawab step ini. NULL jika step_type=AUTO',
  `min_amount`       DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Batas bawah nilai dokumen (Rp) step ini aktif. NULL = tanpa batas bawah (0)',
  `max_amount`       DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Batas atas nilai dokumen (Rp) step ini aktif. NULL = tanpa batas atas (step tertinggi/unlimited)',
  `label`            VARCHAR(100) NULL DEFAULT NULL COMMENT 'Label tercetak di dokumen: Membuat, Mengetahui, Menyetujui, dst',
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`       VARCHAR(200) NULL DEFAULT NULL,
  `updated_by`       VARCHAR(200) NULL DEFAULT NULL,
  PRIMARY KEY (`flow_step_id`) USING BTREE,
  INDEX `idx_flowstep_config` (`flow_config_id` ASC) USING BTREE,
  INDEX `idx_flowstep_position` (`position_id` ASC) USING BTREE,
  INDEX `idx_flowstep_status_from` (`status_from` ASC) USING BTREE,
  CONSTRAINT `fk_flowstep_config` FOREIGN KEY (`flow_config_id`) REFERENCES `flow_config` (`flow_config_id`) ON DELETE CASCADE ON UPDATE CASCADE
  -- , CONSTRAINT `fk_flowstep_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`position_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Step alur dokumen: definisi transisi status + (jika APPROVAL) jabatan penanggung jawab & threshold nilai'
  ROW_FORMAT = Dynamic;

-- ============================================================
-- 3. flow_position_acting — PLT/Pejabat Sementara per jabatan
--    Berlaku ke SEMUA step di semua modul yang memakai position_id tsb.
-- ============================================================
DROP TABLE IF EXISTS `flow_position_acting`;
CREATE TABLE `flow_position_acting` (
  `acting_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `position_id`    BIGINT UNSIGNED NOT NULL COMMENT '[DELTA] FK org_position — jabatan yang digantikan sementara',
  `acting_user_id` BIGINT UNSIGNED NOT NULL COMMENT '[DELTA] FK user/akun — pejabat sementara (PLT)',
  `start_date`     DATE NOT NULL COMMENT 'Mulai berlaku PLT',
  `end_date`       DATE NOT NULL COMMENT 'Berakhir PLT (inklusif)',
  `reason`         VARCHAR(200) NULL DEFAULT NULL COMMENT 'Alasan: sakit, cuti, dinas luar, dll',
  `is_active`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=aktif, 0=dibatalkan sebelum end_date (pejabat tetap kembali lebih awal)',
  `created_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     VARCHAR(200) NULL DEFAULT NULL COMMENT 'User pembuat (Admin/HR yang menetapkan PLT)',
  `updated_by`     VARCHAR(200) NULL DEFAULT NULL,
  PRIMARY KEY (`acting_id`) USING BTREE,
  INDEX `idx_acting_position_date` (`position_id` ASC, `start_date` ASC, `end_date` ASC) USING BTREE,
  INDEX `idx_acting_user` (`acting_user_id` ASC) USING BTREE
  -- , CONSTRAINT `fk_acting_position` FOREIGN KEY (`position_id`) REFERENCES `org_position` (`position_id`) ON DELETE CASCADE ON UPDATE CASCADE
  -- , CONSTRAINT `fk_acting_user` FOREIGN KEY (`acting_user_id`) REFERENCES `sys_user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Penunjukan pejabat sementara (PLT) per jabatan — lintas semua modul yang memakai jabatan tsb dalam flow_config_step'
  ROW_FORMAT = Dynamic;

-- ============================================================
-- 4. flow_log — SATU log terpadu untuk seluruh pergerakan dokumen
--    Menggantikan doc_state_log lama. Polymorphic: module_code + doc_id.
-- ============================================================
DROP TABLE IF EXISTS `flow_log`;
CREATE TABLE `flow_log` (
  `flow_log_id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_code`             VARCHAR(50) NOT NULL COMMENT 'Kode modul dokumen sumber: PR, PO, CO, dst',
  `doc_id`                  BIGINT UNSIGNED NOT NULL COMMENT 'ID dokumen sumber (polymorphic — bukan FK fisik, karena dokumen tersebar di beberapa database/modul)',
  `flow_config_id`          BIGINT UNSIGNED NOT NULL COMMENT 'FK flow_config — konfigurasi alur yang dipakai saat itu',
  `flow_step_id`            BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'FK flow_config_step — step yang dieksekusi. NULL bila transisi di luar step terdefinisi (mis. cancel manual)',
  `status_from`             VARCHAR(30) NOT NULL COMMENT 'Status dokumen sebelum aksi ini',
  `status_to`                VARCHAR(30) NOT NULL COMMENT 'Status dokumen sesudah aksi ini',
  `actor_position_id`        BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Snapshot jabatan penanggung jawab step ini (dari flow_config_step.position_id saat itu)',
  `actual_user_id`           BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '[DELTA] User yang benar-benar melakukan aksi (pejabat tetap ATAU PLT, ATAU pembuat dokumen untuk step non-approval)',
  `is_acting`                TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=aksi dilakukan oleh PLT, 0=pejabat tetap / bukan approval',
  `action`                   VARCHAR(20) NOT NULL COMMENT 'SUBMITTED | APPROVED | REJECTED | AUTO_ADVANCED | CANCELLED',
  `amount_snapshot`          DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Nilai dokumen saat aksi terjadi (snapshot, untuk audit threshold approval)',
  `notes`                    VARCHAR(200) NULL DEFAULT NULL COMMENT 'Catatan/alasan (wajib diisi bila action=REJECTED)',
  `acted_at`                 TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu aksi terjadi',
  `created_by`                VARCHAR(200) NULL DEFAULT NULL COMMENT 'User/system pembuat baris log',
  PRIMARY KEY (`flow_log_id`) USING BTREE,
  INDEX `idx_flowlog_doc` (`module_code` ASC, `doc_id` ASC) USING BTREE,
  INDEX `idx_flowlog_config_step` (`flow_config_id` ASC, `flow_step_id` ASC) USING BTREE,
  INDEX `idx_flowlog_acted_at` (`acted_at` ASC) USING BTREE
  -- , CONSTRAINT `fk_flowlog_config` FOREIGN KEY (`flow_config_id`) REFERENCES `flow_config` (`flow_config_id`) ON DELETE RESTRICT ON UPDATE CASCADE
  -- , CONSTRAINT `fk_flowlog_step` FOREIGN KEY (`flow_step_id`) REFERENCES `flow_config_step` (`flow_step_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci
  COMMENT = 'Log terpadu seluruh pergerakan status dokumen lintas modul (state transition + approval jadi satu baris). Menggantikan doc_state_log lama'
  ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Contoh seed data (ilustrasi, SESUAIKAN position_id/division_id riil)
-- Kasus: PR company-wide, 2 step (Kepala PPIC selalu, Direksi jika >50jt)
-- ============================================================
-- INSERT INTO `flow_config` (`module_code`,`division_id`,`total_step`,`notes`,`is_active`,`created_by`)
--   VALUES ('PR', NULL, 2, 'Approval PR company-wide default', 1, '_system');
-- SET @flow_pr := LAST_INSERT_ID();
--
-- INSERT INTO `flow_config_step`
--   (`flow_config_id`,`step_no`,`step_type`,`status_from`,`status_to`,`status_to_reject`,`position_id`,`min_amount`,`max_amount`,`label`)
--   VALUES
--   (@flow_pr, 1, 'APPROVAL', 'SUBMITTED',        'PENDING_L2', 'DRAFT', /*position_id Kepala PPIC*/ 1, 0,          NULL, 'Mengetahui'),
--   (@flow_pr, 2, 'APPROVAL', 'PENDING_L2',        'APPROVED',  'DRAFT', /*position_id Direksi*/    2, 50000000,  NULL, 'Menyetujui');
--
-- Contoh kasus DO (tanpa approval sama sekali, step_type NOTIFY_ONLY/AUTO):
-- INSERT INTO `flow_config` (`module_code`,`division_id`,`total_step`,`notes`,`is_active`,`created_by`)
--   VALUES ('DO', NULL, 1, 'DO tanpa approval — auto confirm', 1, '_system');
-- SET @flow_do := LAST_INSERT_ID();
-- INSERT INTO `flow_config_step`
--   (`flow_config_id`,`step_no`,`step_type`,`status_from`,`status_to`,`position_id`,`label`)
--   VALUES (@flow_do, 1, 'AUTO', 'DRAFT', 'CONFIRMED', NULL, NULL);
