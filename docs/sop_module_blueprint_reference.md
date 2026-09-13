---
name: sop_module_blueprint_reference
description: Materi mentah (belum diproses) untuk rencana modul SOP baru -- kumpulan blueprint SQL yang diberikan user secara bertahap.
---

# SOP Module — Blueprint Reference (BELUM DIPROSES)

> **Status: MENTAH, MENUNGGU.** User eksplisit 2026-09-13: "read this and make
> it base for SOP module, I will add again. don't process." File ini HANYA
> menyimpan materi yang diberikan supaya tidak hilang dari histori chat —
> **belum ada migration/model/tabel yang dibuat dari isi file ini.** User akan
> menambah lagi sebelum ini benar-benar dirancang & dieksekusi. Jangan mulai
> implementasi dari file ini tanpa instruksi eksplisit baru.

## Catatan arsitektur yang perlu diperhatikan NANTI saat mulai merancang
(Ditulis sebagai pengingat, bukan keputusan — belum dibahas dengan user)

1. **Overlap dengan tabel yang SUDAH ADA & dipakai produksi**: `leave_absence_policy`
   dan `overtime_requests` di blueprint #1 tumpang tindih konsep dengan
   `tm2mst.emp_leave_request`/`emp_leave_balance`/`emp_overtime_request` yang
   sudah live. Perlu diputuskan: SOP module jadi lapisan ATURAN/KEBIJAKAN di atas
   tabel transaksi yang sudah ada (rekomendasi awal, hindari duplikasi), atau
   benar-benar tabel baru terpisah.
2. **Arsitektur database-per-modul (CLAUDE.md §2, LOCKED)**: kedua blueprint
   menulis `CREATE DATABASE` tunggal (`telasih_makmur_sop`) dan skema PostgreSQL
   generik (SERIAL, dst) — proyek ini pakai MySQL 8 + 10 database per-modul
   (`tm2mst`/`tm2hrm`/`tm2sys`/dst), tanpa FK lintas-DB. Kalau nanti benar
   dieksekusi, tabelnya perlu dipecah ke database yang sesuai per domain
   (mis. `company_roles`/`personnel`/`departments` → `tm2mst`, `sop_documents` →
   kemungkinan `tm2sys` mengikuti pola `sys_doc_reference`, dst) dan disesuaikan
   ke prefix/konvensi kolom proyek (`activate` bukan boolean status bebas,
   PK `id_<entitas>`, dst) — bukan dijalankan apa adanya.
3. **Blueprint #2 (Manufacturing Enterprise Schema)** isinya SOP proses produksi
   (Cetak/Plong/Kupas/Laminasi/Potong dst) + personil + rutinitas departemen —
   ini kemungkinan berrelasi ke modul Production (`tm2prd`) yang sudah punya
   `ImpositionEngine`/`CostingEngine`/Production Scheduling sendiri; perlu dicek
   tumpang tindih dengan itu juga saat mulai dirancang.

---

## Blueprint #1 — "SOP Relational Database Blueprint and SQL Script" (2026-09-13)

Cakupan: dokumen SOP (metadata), peran organisasi, alur order Subcon TDP,
log plate cetak (CTP), kebijakan cuti/izin, pengajuan lembur, deposit karyawan
baru, log kunci forklift, kebijakan canvassing sales + uang inap/makan.

```sql
-- =============================================================================
-- TELASIH MAKMUROFFSET PRINTING & PACKAGING
-- Complete Database Schema (DDL) and Data Seed (DML) for Operational SOPs
-- =============================================================================
CREATE DATABASE IF NOT EXISTS telasih_makmur_sop;
USE telasih_makmur_sop;

-- -----------------------------------------------------------------------------
-- 1. SOP DOCUMENT METADATA REGISTER
-- -----------------------------------------------------------------------------
CREATE TABLE sop_documents (
    sop_id INT AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    revision VARCHAR(10) NOT NULL DEFAULT '-',
    effective_date DATE NOT NULL,
    purpose TEXT NOT NULL
);
INSERT INTO sop_documents (sop_id, document_number, title, revision, effective_date, purpose) VALUES
(1, '005/RP/TM/IX/2026', 'Rules & Policy Business Process TDP - Penerimaan Order Subcon TM', '-', '2026-09-05', 'Menetapkan tata cara penerimaan, pemeriksaan, persetujuan, persiapan bahan baku, pelaksanaan produksi, hingga penyelesaian order Subcon TM Non Proof'),
(2, '001/SOP/TM/III/2026', 'Standard Operating Procedure (SOP) - Penggantian Plate Cetak', '-', '2026-02-28', 'Meminimalisir tingkat Waste pada Mesin Cetak, serta meningkatkan efektivitas dan efisiensi keseluruhan proses produksi'),
(3, '001/SOP/TM/V/2026', 'SOP Perizinan & Tidak Masuk Kerja', '-', '2026-05-05', 'Memberikan aturan jelas mengenai izin tidak masuk bekerja, menghindari kesimpangsiuran, dan tertib administrasi'),
(4, '004/SOP/TM/V/2026', 'Standard Operating Procedure (SOP) - Pengajuan dan Pelaksanaan Lembur', '-', '2026-05-16', 'Memastikan seluruh permintaan lembur hari libur/hari Minggu terdokumentasi dengan jelas, disetujui sesuai alur, dan terinput payroll'),
(5, '005/SOP/TM/VI/2026', 'Standard Operating Procedure (SOP) - Deposit Karyawan Baru', '-', '2026-06-06', 'Mengatur mekanisme pengenaan, pemotongan, penyimpanan, dan pengembalian deposit karyawan baru sebagai jaminan kepatuhan'),
(6, '003/SOP/TM/IV/2026', 'Standard Operating Procedure (SOP) - Peminjaman dan Penggunaan Kunci Forklift', '-', '2026-04-24', 'Menjamin penggunaan forklift serta memastikan peminjaman dan penggunaan kunci terkontrol dan dapat dipertanggungjawabkan'),
(7, '001/RP/TM/V/2026', 'Rules & Policy Sales Canvassing', '-', '2026-02-23', 'Menciptakan sistem kerja S.M.A.R.T, efektif, efisien, serta meminimalisir fraud dan ambiguitas di lapangan');

-- -----------------------------------------------------------------------------
-- 2. COMPANY ROLES & ORGANIZATIONAL STRUCTURE
-- -----------------------------------------------------------------------------
CREATE TABLE company_roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    department VARCHAR(100) NOT NULL,
    responsibilities TEXT NOT NULL
);
INSERT INTO company_roles (role_id, role_name, department, responsibilities) VALUES
(1, 'SPV FO', 'Front Office / Marketing', 'Penerimaan order, pemeriksaan kelengkapan data, verifikasi Mastercard, approval Proofing, pemeriksaan akhir Finished Good'),
(2, 'Marketing TM', 'Marketing', 'Menyerahkan order ke SPV FO, komunikasi ACC Proofing ke customer, penerimaan produk akhir'),
(3, 'Kasir / CS', 'Customer Service / Finance', 'Input order ke program, pemeriksaan Finished Good, pengecekan Qty/Kondisi, update status program'),
(4, 'Deskprint', 'Pre Press', 'Pembuatan Mastercard berdasarkan spesifikasi order dan program'),
(5, 'SPV Operasional', 'Operations', 'Approval Mastercard, pembuatan WO/SPK, penjadwalan produksi, penerimaan laporan kendala teknis'),
(6, 'Staff WH / Umum', 'Warehouse', 'Pemeriksaan ketersediaan bahan baku, penyiapan bahan baku, serah terima ke produksi'),
(7, 'Purchasing Staff', 'Procurement', 'Pembelian bahan baku ke supplier jika stok bahan tidak ready'),
(8, 'Staff Produksi', 'Production', 'Pelaksanaan produksi berdasarkan WO/SPK & Mastercard, pembuatan 1-3 lembar cetak proof'),
(9, 'SPV Pre Press', 'Pre Press', 'Memonitor Staff Plate, memvalidasi dan menginstruksikan pembuatan/penggantian plate baru'),
(10, 'Staff CTP', 'Pre Press', 'Penanggung jawab pembuatan plate pada mesin CTP dan pencatatan log plate'),
(11, 'Staff Plate', 'Pre Press', 'Proses Punch Register, pencatatan/penyimpanan plate, serah terima SPK, penandaan fisik kondisi gripper'),
(12, 'SPV Cetak', 'Production', 'Pengguna plate, penyerahan SPK ke Staff Plate, penanggung jawab utama divisi mesin cetak'),
(13, 'Operator & Asisten Cetak', 'Production', 'Pemasangan plate ke unit warna mesin cetak, pelaksanaan cetak'),
(14, 'HRD Staff / Manager', 'Human Resources', 'Pengelolaan perizinan/cuti, pendataan deposit karyawan, verifikasi data lembur payroll, penanganan tindakan disiplin'),
(15, 'PPIC', 'Production Planning', 'Penjadwalan lembur H-3/H-1, penetapan target kerja, pembuatan dan penyerahan slip kerja'),
(16, 'Kepala Produksi', 'Production', 'Cross check slip kerja lembur dan distribusi ke SPV On Duty'),
(17, 'SPV On Duty', 'Production', 'Pengawasan pelaksanaan lembur, kehadiran, hasil kerja, dan pelaporan via WA Group/HRD'),
(18, 'Finance Staff / Manager', 'Finance', 'Pemotongan, penyimpanan, dan pengembalian deposit karyawan, pembayaran klaim reimbursement'),
(19, 'PIC Gudang / Leader', 'Warehouse', 'Pengelolaan, penyimpanan, dan verifikasi peminjaman kunci forklift'),
(20, 'Security', 'General Affair / Security', 'Penyimpanan kunci forklift di pos satpam, verifikasi surat izin keluar jam kerja, operator forklift'),
(21, 'Sales Canvassing B2B', 'Sales', 'Pelaksanaan kunjungan canvassing (in-town/out-of-town), pelaporan harian/mingguan, pencapaian KPI sales'),
(22, 'Operational Manager', 'Management', 'Approval lembur, evaluasi operasional, approval tata kelola SOP'),
(23, 'General Manager', 'Executive Management', 'Approval strategis dan tata kelola SOP'),
(24, 'Direktur Utama', 'Executive Board', 'Approval tingkat akhir perizinan staff, permohonan cuti, dan kebijakan strategis');

-- -----------------------------------------------------------------------------
-- 3. SUBCON TDP ORDER WORKFLOW MANAGEMENT
-- -----------------------------------------------------------------------------
CREATE TABLE order_tdp_subcon (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(100) NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    dimensions VARCHAR(50) NOT NULL,
    material VARCHAR(100) NOT NULL,
    material_spec VARCHAR(150) NOT NULL,
    print_size VARCHAR(50) NOT NULL,
    work_sequence TEXT NOT NULL,
    input_by_cs BOOLEAN DEFAULT FALSE,
    mastercard_created BOOLEAN DEFAULT FALSE,
    spv_fo_approval BOOLEAN DEFAULT FALSE,
    spv_ops_approval BOOLEAN DEFAULT FALSE,
    wo_spk_created BOOLEAN DEFAULT FALSE,
    material_status ENUM('PENDING', 'READY', 'NOT_READY_PURCHASING') DEFAULT 'PENDING',
    proof_acc_customer BOOLEAN DEFAULT FALSE,
    finished_good_status ENUM('IN_PROGRESS', 'PENDING_CS_CHECK', 'REJECTED_RETURNED', 'FINISHED') DEFAULT 'IN_PROGRESS',
    final_fo_approval BOOLEAN DEFAULT FALSE,
    handed_over_to_marketing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO order_tdp_subcon
(order_number, customer_name, item_name, dimensions, material, material_spec, print_size, work_sequence, input_by_cs, mastercard_created, spv_fo_approval, spv_ops_approval, wo_spk_created, material_status, proof_acc_customer, finished_good_status, final_fo_approval, handed_over_to_marketing)
VALUES
('ORD-2026-001', 'PT Food Beverages Indonesia', 'Box Kemasan Premium', '20x10x5 cm', 'Duplex Board', '350 gsm Coating', '40x60 cm', 'Cetak -> LAMINASI -> DIE CUT -> GLUE', TRUE, TRUE, TRUE, TRUE, TRUE, 'READY', TRUE, 'FINISHED', TRUE, TRUE),
('ORD-2026-002', 'CV Malang Jaya Consumer', 'Paper Bag Kraft', '25x30x8 cm', 'Kraft Paper', '150 gsm Brown', '50x70 cm', 'Cetak -> PUNCH -> ROPE ASSEMBLY', TRUE, TRUE, TRUE, TRUE, TRUE, 'NOT_READY_PURCHASING', FALSE, 'IN_PROGRESS', FALSE, FALSE);

-- -----------------------------------------------------------------------------
-- 4. CTP PRINTING PLATE & LIFECYCLE MANAGEMENT
-- -----------------------------------------------------------------------------
CREATE TABLE plate_printing_log (
    plate_id INT AUTO_INCREMENT PRIMARY KEY,
    spk_number VARCHAR(50) NOT NULL,
    plate_code VARCHAR(50) NOT NULL,
    ctp_staff_id INT,
    punch_register_completed BOOLEAN DEFAULT FALSE,
    stored_location VARCHAR(50) DEFAULT 'Rack Plate PrePress',
    color_unit_assigned VARCHAR(20),
    runs_count INT DEFAULT 0 CHECK (runs_count <= 3),
    max_runs_threshold INT DEFAULT 3,
    gripper_marking_status VARCHAR(50) DEFAULT 'MARKED_WITH_CUTTER',
    replacement_status ENUM('ACTIVE', 'REPLACEMENT_REQUESTED', 'APPROVED_NEW_PLATE_MADE', 'RETIRED') DEFAULT 'ACTIVE',
    spv_prepress_approval BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (ctp_staff_id) REFERENCES company_roles(role_id)
);
INSERT INTO plate_printing_log
(spk_number, plate_code, ctp_staff_id, punch_register_completed, stored_location, color_unit_assigned, runs_count, max_runs_threshold, gripper_marking_status, replacement_status, spv_prepress_approval)
VALUES
('SPK-2026-088', 'PLT-CYAN-01', 10, TRUE, 'Rak A-1', 'Unit 1 Cyan', 3, 3, 'Garis Cutter 3x', 'REPLACEMENT_REQUESTED', TRUE),
('SPK-2026-088', 'PLT-MAGENTA-01', 10, TRUE, 'Rak A-1', 'Unit 2 Magenta', 2, 3, 'Garis Cutter 2x', 'ACTIVE', FALSE);

-- -----------------------------------------------------------------------------
-- 5. ATTENDANCE, LEAVE & ABSENCE POLICY RULES
-- -----------------------------------------------------------------------------
CREATE TABLE leave_absence_policy (
    policy_id INT AUTO_INCREMENT PRIMARY KEY,
    leave_type VARCHAR(50) NOT NULL UNIQUE,
    min_tenure_months INT DEFAULT 0,
    max_allowed_days INT,
    notice_days_required INT NOT NULL,
    salary_paid_percentage DECIMAL(5,2) DEFAULT 100.00,
    min_required_documents INT DEFAULT 0,
    policy_details TEXT NOT NULL
);
INSERT INTO leave_absence_policy
(leave_type, min_tenure_months, max_allowed_days, notice_days_required, salary_paid_percentage, min_required_documents, policy_details)
VALUES
('Cuti Tahunan', 12, 12, 7, 100.00, 1, 'Memerlukan Formulir Cuti HRD, approval Kabag/PresDir. Potong 5 hari untuk Lebaran bersama.'),
('Cuti Melahirkan/Keguguran', 0, 60, 7, 100.00, 1, 'Diberikan selama 2 bulan dengan gaji pokok penuh. Melampirkan surat keterangan dokter/bidan/RS.'),
('Cuti Ibadah Keagamaan', 0, NULL, 7, 100.00, 1, 'Memerlukan dokumen resmi keberangkatan/ibadah dan approval Manajemen.'),
('Cuti Nikah Karyawan', 0, 3, 7, 100.00, 0, 'Di luar tanggungan, permohonan H-7.'),
('Cuti Nikah Anak', 0, 2, 7, 100.00, 0, 'Di luar tanggungan, permohonan H-7.'),
('Cuti Khitanan Anak', 0, 2, 7, 100.00, 0, 'Di luar tanggungan, permohonan H-7.'),
('Cuti Istri Melahirkan', 0, 3, 0, 100.00, 0, 'Pemberitahuan hari H pada saat kejadian.'),
('Cuti Duka Anggota Keluarga Inti', 0, 2, 0, 100.00, 0, 'Untuk Suami/Istri, Anak, Orangtua/Mertua, Kakak/Adik. Notification hari H.'),
('Izin Sakit Dengan Dokter', 0, NULL, 0, 100.00, 3, 'Wajib infokan H-0. Menyerahkan min 3 dari: Form Izin, Surat Dokter, Kwitansi Dokter, Resep Obat, Kwitansi Apotek.'),
('Izin Tidak Masuk Terencana', 0, 3, 3, 100.00, 1, 'Batas izin maks 3x/bulan. Jika hari H wajib cari pengganti. Jika Izin+Alpha >= 5 dlm 2 bulan -> SP.'),
('Alpha / Tanpa Keterangan', 0, 0, 0, 0.00, 0, 'Pelanggaran. 3 hari berturut-turut dlm 1 minggu atau 6 hari dlm 1 bulan dianggap mengundurkan diri.');

-- -----------------------------------------------------------------------------
-- 6. OVERTIME REQUEST & PAYROLL VERIFICATION FLOW
-- -----------------------------------------------------------------------------
CREATE TABLE overtime_requests (
    overtime_id INT AUTO_INCREMENT PRIMARY KEY,
    overtime_code VARCHAR(50) NOT NULL UNIQUE,
    scheduled_date DATE NOT NULL,
    is_holiday_or_sunday BOOLEAN DEFAULT TRUE,
    lead_time_days INT DEFAULT 3,
    work_item VARCHAR(150) NOT NULL,
    target_output VARCHAR(100) NOT NULL,
    ppic_role_id INT DEFAULT 15,
    manager_approval BOOLEAN DEFAULT FALSE,
    kp_crosscheck_slip BOOLEAN DEFAULT FALSE,
    spv_on_duty_role_id INT DEFAULT 17,
    employee_type ENUM('REGULAR', 'BORONGAN') NOT NULL,
    hrd_payroll_submitted BOOLEAN DEFAULT FALSE,
    submission_deadline_days INT DEFAULT 1,
    FOREIGN KEY (ppic_role_id) REFERENCES company_roles(role_id),
    FOREIGN KEY (spv_on_duty_role_id) REFERENCES company_roles(role_id)
);
INSERT INTO overtime_requests
(overtime_code, scheduled_date, is_holiday_or_sunday, lead_time_days, work_item, target_output, ppic_role_id, manager_approval, kp_crosscheck_slip, spv_on_duty_role_id, employee_type, hrd_payroll_submitted, submission_deadline_days)
VALUES
('OVT-2026-0501', '2026-05-24', TRUE, 3, 'Pencetakan Box Offsite 10.000 pcs', '10.000 lembar selesai', 15, TRUE, TRUE, 17, 'REGULAR', TRUE, 1),
('OVT-2026-0502', '2026-05-31', TRUE, 1, 'Emergency Sorting & Finishing Borongan', '5.000 box terlipat', 15, TRUE, TRUE, 17, 'BORONGAN', TRUE, 1);

-- -----------------------------------------------------------------------------
-- 7. NEW EMPLOYEE SECURITY DEPOSIT LEDGER
-- -----------------------------------------------------------------------------
CREATE TABLE employee_deposit_ledger (
    policy_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_category ENUM('PRODUKSI', 'STAFF_SECURITY') NOT NULL UNIQUE,
    total_deposit_target DECIMAL(10,2) NOT NULL DEFAULT 1000000.00,
    deduction_per_period DECIMAL(10,2) NOT NULL,
    deduction_frequency ENUM('WEEKLY', 'MONTHLY') NOT NULL,
    total_deduction_periods INT NOT NULL,
    min_daily_wage_threshold DECIMAL(10,2) NOT NULL DEFAULT 90000.00,
    absolute_min_wage_floor DECIMAL(10,2) NOT NULL DEFAULT 80000.00,
    notice_period_days_for_refund INT DEFAULT 30,
    refund_payout_days INT DEFAULT 30,
    forfeiture_conditions TEXT NOT NULL
);
INSERT INTO employee_deposit_ledger
(employee_category, total_deposit_target, deduction_per_period, deduction_frequency, total_deduction_periods, min_daily_wage_threshold, absolute_min_wage_floor, notice_period_days_for_refund, refund_payout_days, forfeiture_conditions)
VALUES
('PRODUKSI', 1000000.00, 62500.00, 'WEEKLY', 16, 90000.00, 80000.00, 30, 30, 'Deposit hangus jika resign tanpa one month notice, mangkir, ada kerugian/kehilangan aset perusahaan yang belum diselesaikan.'),
('STAFF_SECURITY', 1000000.00, 250000.00, 'MONTHLY', 4, 90000.00, 80000.00, 30, 30, 'Deposit hangus jika resign tanpa one month notice, mangkir, ada kerugian/kehilangan aset perusahaan yang belum diselesaikan.');

-- -----------------------------------------------------------------------------
-- 8. FORKLIFT KEY BORROWING & CUSTODY LOG
-- -----------------------------------------------------------------------------
CREATE TABLE forklift_key_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    borrow_date DATE NOT NULL,
    requested_by_spv_role VARCHAR(100) NOT NULL,
    verified_by_pic_gudang BOOLEAN DEFAULT FALSE,
    custodian_location VARCHAR(50) DEFAULT 'Pos Security',
    assigned_operator VARCHAR(100) NOT NULL,
    take_home_allowed BOOLEAN DEFAULT FALSE,
    duplicate_allowed BOOLEAN DEFAULT FALSE,
    key_returned_to_security BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO forklift_key_log
(borrow_date, requested_by_spv_role, verified_by_pic_gudang, custodian_location, assigned_operator, take_home_allowed, duplicate_allowed, key_returned_to_security)
VALUES
('2026-05-10', 'SPV Ekspedisi', TRUE, 'Pos Security', 'Trio Nerma (Ekspedisi)', FALSE, FALSE, TRUE),
('2026-05-11', 'SPV Security', TRUE, 'Pos Security', 'Suhendrik (Security)', FALSE, FALSE, TRUE);

-- -----------------------------------------------------------------------------
-- 9. SALES B2B CANVASSING GOVERNANCE & REIMBURSEMENT
-- -----------------------------------------------------------------------------
CREATE TABLE sales_canvassing_policy (
    canvass_policy_id INT AUTO_INCREMENT PRIMARY KEY,
    location_scope ENUM('IN_TOWN', 'OUT_OF_TOWN') NOT NULL UNIQUE,
    office_attendance_rule VARCHAR(150) NOT NULL,
    presensi_method VARCHAR(100) NOT NULL,
    min_daily_visits INT NOT NULL,
    max_daily_visits INT NOT NULL,
    min_weekly_visits INT NOT NULL DEFAULT 25,
    max_weekly_visits INT NOT NULL DEFAULT 35,
    min_monthly_visits INT NOT NULL DEFAULT 100,
    max_monthly_visits INT NOT NULL DEFAULT 125,
    overtime_eligible BOOLEAN DEFAULT FALSE,
    daily_report_deadline TIME DEFAULT '18:00:00',
    reimburse_motor_daily_max DECIMAL(10,2) NOT NULL,
    reimburse_car_daily_max DECIMAL(10,2) NOT NULL,
    motor_km_ratio DECIMAL(5,2) DEFAULT 40.00,
    car_km_ratio DECIMAL(5,2) DEFAULT 12.00,
    base_commission_pct DECIMAL(5,2) DEFAULT 1.00,
    new_order_bonus_pct DECIMAL(5,2) DEFAULT 0.50
);
INSERT INTO sales_canvassing_policy
(location_scope, office_attendance_rule, presensi_method, min_daily_visits, max_daily_visits, min_weekly_visits, max_weekly_visits, min_monthly_visits, max_monthly_visits, overtime_eligible, daily_report_deadline, reimburse_motor_daily_max, reimburse_car_daily_max, motor_km_ratio, car_km_ratio, base_commission_pct, new_order_bonus_pct)
VALUES
('IN_TOWN', 'Wajib hadir & absen setiap hari di kantor', 'FingerSpot Office / Geotag Photo', 5, 7, 25, 35, 100, 125, FALSE, '18:00:00', 27400.00, 52400.00, 40.00, 12.00, 1.00, 0.50),
('OUT_OF_TOWN', 'Wajib hadir & absen min 2x dalam seminggu di kantor', 'Geotag Photo & Form Report Harian', 3, 5, 25, 35, 100, 125, FALSE, '18:00:00', 27400.00, 52400.00, 40.00, 12.00, 1.00, 0.50);

-- -----------------------------------------------------------------------------
-- 10. SALES ACCOMMODATION & MEAL ALLOWANCE MATRIX
-- -----------------------------------------------------------------------------
CREATE TABLE sales_lodging_meal_allowance (
    allowance_id INT AUTO_INCREMENT PRIMARY KEY,
    region_group VARCHAR(100) NOT NULL,
    role_tier VARCHAR(100) NOT NULL,
    max_lodging_allowance DECIMAL(10,2) NOT NULL,
    max_meal_allowance_per_day DECIMAL(10,2) NOT NULL,
    meal_condition_hours INT DEFAULT 6
);
INSERT INTO sales_lodging_meal_allowance
(region_group, role_tier, max_lodging_allowance, max_meal_allowance_per_day, meal_condition_hours)
VALUES
('Jawa Timur / Jawa Tengah', 'Staff & SPV', 200000.00, 30000.00, 6),
('Jawa Timur / Jawa Tengah', 'Manager Junior - Madya', 300000.00, 50000.00, 6),
('Jawa Timur / Jawa Tengah', 'Manager Senior - BOD', 400000.00, 80000.00, 6),
('Jabodetabek / Jawa Barat / Bali / Lombok', 'Staff & SPV', 250000.00, 40000.00, 6),
('Jabodetabek / Jawa Barat / Bali / Lombok', 'Manager Junior - Madya', 350000.00, 60000.00, 6),
('Jabodetabek / Jawa Barat / Bali / Lombok', 'Manager Senior - BOD', 500000.00, 100000.00, 6);

-- =============================================================================
-- END OF SCRIPT
-- =============================================================================
```

---

## Blueprint #2 — "Offset Printing & Packaging Manufacturing Enterprise Schema" (2026-09-13)

Cakupan: departemen, personil, dokumen SOP proses produksi, langkah kerja SOP
per fase (Cetak/Plong/Kupas/Laminasi/Potong), standar parameter operasional
mesin/bahan, aturan QC & penanganan cacat (Karantina/Hold/NG/Rajang/Kawul),
rutinitas departemen (harian/mingguan/bulanan), prosedur gudang bahan baku.
Ditulis untuk PostgreSQL (`SERIAL`, `REFERENCES` inline) — beda dialek dari
MySQL yang dipakai proyek ini.

```sql
-- SQL Schema Definition for Printing & Packaging Manufacturing Operations
-- Compatible with PostgreSQL 12+ and ANSI SQL Standards
CREATE TABLE departments (
    department_id VARCHAR(10) PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    division_name VARCHAR(100)
);

CREATE TABLE personnel (
    personnel_id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    department_id VARCHAR(10) REFERENCES departments(department_id),
    role_type VARCHAR(50) CHECK (role_type IN ('Executive', 'Manager', 'Supervisor', 'Staff', 'Operator', 'Asisten', 'Helper', 'PIC'))
);

CREATE TABLE sop_documents (
    sop_id SERIAL PRIMARY KEY,
    doc_number VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(150) NOT NULL,
    effective_date DATE,
    revision_number VARCHAR(10) DEFAULT '0',
    page_info VARCHAR(20),
    department_id VARCHAR(10) REFERENCES departments(department_id),
    author_id INT REFERENCES personnel(personnel_id),
    reviewer_id INT REFERENCES personnel(personnel_id),
    approver_id INT REFERENCES personnel(personnel_id)
);

CREATE TABLE sop_workflow_steps (
    step_id SERIAL PRIMARY KEY,
    sop_id INT REFERENCES sop_documents(sop_id) ON DELETE CASCADE,
    phase VARCHAR(50) CHECK (phase IN ('Persiapan Kerja', 'Persiapan Mesin', 'Awal Jalan', 'Proses Massal / Running', 'Finish Order', 'Finish Proses / Cleanup')),
    assigned_role VARCHAR(50) CHECK (assigned_role IN ('Operator', 'Asisten', 'Helper', 'QC Inspector', 'General')),
    step_number INT NOT NULL,
    action_instruction TEXT NOT NULL,
    quality_checkpoint TEXT
);

CREATE TABLE process_operational_standards (
    standard_id SERIAL PRIMARY KEY,
    process_type VARCHAR(50) NOT NULL,
    material_or_machine VARCHAR(100) NOT NULL,
    parameter_name VARCHAR(100) NOT NULL,
    min_value DECIMAL(10,2),
    max_value DECIMAL(10,2),
    unit_of_measure VARCHAR(30),
    operating_condition TEXT
);

CREATE TABLE quality_control_rules (
    rule_id SERIAL PRIMARY KEY,
    sop_id INT REFERENCES sop_documents(sop_id),
    qc_stage VARCHAR(50) CHECK (qc_stage IN ('Incoming Material', 'In-Process Production', 'QC Pack Final Inspection')),
    defect_condition TEXT NOT NULL,
    action_if_ok TEXT NOT NULL,
    action_if_not_ok TEXT NOT NULL,
    form_or_label_used VARCHAR(100)
);

CREATE TABLE departmental_routines (
    routine_id SERIAL PRIMARY KEY,
    department_id VARCHAR(10) REFERENCES departments(department_id),
    personnel_id INT REFERENCES personnel(personnel_id),
    frequency VARCHAR(20) CHECK (frequency IN ('Harian', 'Mingguan', 'Bulanan', 'Seasonal / Incidental')),
    task_category VARCHAR(100) NOT NULL,
    task_description TEXT NOT NULL
);

CREATE TABLE warehouse_material_procedures (
    procedure_id SERIAL PRIMARY KEY,
    operation_type VARCHAR(20) CHECK (operation_type IN ('Penerimaan', 'Pengeluaran', 'Pemeriksaan')),
    step_number INT NOT NULL,
    responsible_party VARCHAR(50) NOT NULL,
    required_document VARCHAR(100),
    action_description TEXT NOT NULL
);

-- ==========================================
-- 1. SEEDING DEPARTMENTS
-- ==========================================
INSERT INTO departments (department_id, department_name, division_name) VALUES
('QA', 'Quality Assurance', 'Quality Control'),
('PPIC', 'Supply Chain', 'PPIC'),
('PURCH', 'Purchasing', 'Purchasing'),
('FIN', 'Finance', 'Accounting & Finance'),
('GA', 'General Affair', 'General Affair'),
('MKT', 'Marketing', 'Sales & Marketing'),
('DIGITAL', 'Digital Marketing', 'Sales & Marketing'),
('PREPRESS', 'Production', 'Pre Press'),
('PROD', 'Production', 'Offset & Digital Printing'),
('EKSP', 'Ekspedisi', 'Logistics & Delivery'),
('WH', 'Warehouse', 'Inventory & Storage');

-- ==========================================
-- 2. SEEDING PERSONNEL
-- ==========================================
INSERT INTO personnel (personnel_id, full_name, job_title, department_id, role_type) VALUES
(1, 'Eka Yeni Rahayu', 'Staff QA', 'QA', 'Staff'),
(2, 'Adi Pratomo', 'General Affair', 'GA', 'Staff'),
(3, 'Umi Nafiah', 'QA SPV', 'QA', 'Supervisor'),
(4, 'Heri Romdani', 'PPIC SPV', 'PPIC', 'Supervisor'),
(5, 'Carrij Soehendra', 'Direktur', 'GA', 'Executive'),
(6, 'Mia Firiyal', 'Accounting & Finance Staff', 'FIN', 'Staff'),
(7, 'Rizta', 'Staff Purchasing', 'PURCH', 'Staff'),
(8, 'Yuna', 'Marketer', 'MKT', 'Staff'),
(9, 'Eni', 'Marketer', 'MKT', 'Staff'),
(10, 'Widita', 'Marketer', 'MKT', 'Staff'),
(11, 'Gunawan', 'Digital Marketer', 'DIGITAL', 'Staff'),
(12, 'Saskia', 'Digital Marketer', 'DIGITAL', 'Staff'),
(13, 'Dedi', 'Digital Marketer', 'DIGITAL', 'Staff'),
(14, 'Suro', 'Staff Planner PPIC', 'PPIC', 'Staff'),
(15, 'Deni Ira Novita', 'Supervisor Digital', 'PROD', 'Supervisor'),
(16, 'Windayanti', 'Staff Ekspedisi', 'EKSP', 'Staff'),
(17, 'Ringga', 'Supervisor Pre Press', 'PREPRESS', 'Supervisor'),
(18, 'Erik Artanto', 'QA Staff', 'QA', 'Staff');

-- ==========================================
-- 3. SEEDING SOP DOCUMENTS
-- ==========================================
INSERT INTO sop_documents (sop_id, doc_number, title, effective_date, revision_number, page_info, department_id, author_id, reviewer_id, approver_id) VALUES
(1, '01/SOP.QA/2022', 'SOP PROSES CETAK', '2022-08-16', '0', '1-3', 'PROD', 1, 2, 5),
(2, '02/SOP.QA/2022', 'SOP PROSES PLONG', '2022-08-16', '0', '1-2', 'PROD', 1, 2, 5),
(3, '0611/SOP.QA/2022', 'SOP PROSES PLONG REV03', '2022-08-27', '3', '1-2', 'PROD', 1, 2, 4),
(4, '/SOP.QA/2022-KUPAS', 'SOP PROSES KUPAS REV02', '2022-09-01', '2', '1', 'PROD', 1, 2, 3),
(5, '/SOP.QA/2022-STITCH', 'SOP PROSES STITCHING REV02', '2022-09-01', '2', '1', 'PROD', 1, 2, 3),
(6, '/SOP.QA/2022-LAM', 'SOP PROSES LAMINASI REV02', '2022-09-01', '2', '1', 'PROD', 1, 2, 3),
(7, '/SOP.QA/2022-FG', 'SOP PROSES FOLDER GLUER BESAR REV02', '2022-09-01', '2', '1', 'PROD', 1, 2, 3),
(8, '/SOP.QA/2022-FGB', 'SOP PROSES FOLDER GLUER BAWAH', '2022-09-01', '0', '1', 'PROD', 1, 2, 3),
(9, '/SOP.QA/2023-POT', 'SOP PROSES POTONG REV02', '2023-01-01', '2', '1', 'PROD', 1, 2, 3),
(10, '0611/SOP.QA/2022-SUM', 'SOP PROSES CUTTING SUMMA', '2022-08-27', '0', '1', 'PROD', 1, 2, 3),
(11, 'QA-QC-PACK-01', 'SOP QC PACK', '2022-01-01', '0', '1', 'QA', 1, 3, 3),
(12, 'TM/SOP-001/Des/2020', 'STANDAR OPERASIONAL PROSEDUR PROSES PRODUKSI', '2020-12-01', '0', '1', 'PROD', 1, 2, 5);

-- ==========================================
-- 4. SEEDING WORKFLOW STEPS
-- ==========================================
INSERT INTO sop_workflow_steps (sop_id, phase, assigned_role, step_number, action_instruction, quality_checkpoint) VALUES
-- SOP Cetak (Offset)
(1, 'Persiapan Kerja', 'Operator', 1, 'Menyalakan mesin cetak dan serah terima pekerjaan dari shift sebelumnya (menulis di buku serah terima)', 'Laporan buku serah terima lengkap'),
(1, 'Persiapan Kerja', 'Operator', 2, 'Memeriksa dan melaporkan kondisi mesin (main panel, unit, CPC)', 'Kondisi mesin optimal'),
(1, 'Persiapan Kerja', 'Asisten', 3, 'Mengambil dan mempersiapkan plat cetak serta tinta sesuai SPK', 'Plat dan tinta sesuai SPK'),
(1, 'Persiapan Kerja', 'Helper', 4, 'Mempersiapkan bahan kertas di area track kertas sesuai SPK', 'Verifikasi gramatur dan ukuran kertas'),
(1, 'Awal Jalan', 'Operator', 5, 'Melakukan cetak awal dan cek layout (kres potong/plong), register, warna, kotor cetak, ghosting, baret, mata ayam, oksidasi', 'Cetak awal presisi sesuai SCR/Proof'),
(1, 'Awal Jalan', 'Operator', 6, 'Pengajuan ACC cetak ke Supervisor, jalan cetak 50 lembar dan 100 lembar untuk cek ulang', 'ACC tertulis SPV sebelum massal'),
(1, 'Proses Massal / Running', 'Operator', 7, 'Melakukan pengecekan berkala (per 150/250/300 lbr reguler; per 100/200/250 lbr order khusus)', 'Kestabilan warna dan kebersihan cetak'),
(1, 'Finish Order', 'Operator', 8, 'Cek jumlah cetakan, save settingan mesin di CPC memory, beri label produksi, dan isi laporan SPK/PPIC', 'Jumlah pas dan setting terarsip'),
-- SOP Plong
(3, 'Persiapan Kerja', 'Operator', 1, 'Memeriksa kondisi mesin (baut handle, baut lengan), baca jadwal, dan pahami SPK/Master Card', 'Kondisi baut dan pisau aman'),
(3, 'Persiapan Kerja', 'Helper', 2, 'Pelumasan mesin, siapkan bahan, alat pendukung (cyto, isolasi, cutter, spon EVA), dan papan legger', 'Alat bantu lengkap'),
(3, 'Awal Jalan', 'Operator', 3, 'Memasang pisau, plat legger, anleg diecut, setting sentrik dan kerataan tekanan', 'Bahan awalan terpotong/ter-creasing sempurna'),
(3, 'Awal Jalan', 'Operator', 4, 'Cek layout plong, hasil potong/creasing, perforasi (tidak tembus/mudah sobek), gelembung laminasi', 'ACC SPV pada bahan bagus max 5 lbr'),
(3, 'Proses Massal / Running', 'Helper', 5, 'Pemeriksaan hasil plong secara berkala ke semua mesin tiap 15 menit, turunkan hasil ke pallet, beri label', 'Tinggi tumpukan pallet 1.5 - 2 m'),
-- SOP Kupas
(4, 'Persiapan Kerja', 'Operator', 1, 'Mempersiapkan produk yang akan dikupas sesuai jadwal, siapkan pallet, baca SPK, dan siapkan kotak sampah kawul', 'Area kerja bersih'),
(4, 'Proses Massal / Running', 'Operator', 2, 'Ambil produk yang akan dikupas (Double whole 3-8 lbr, Sheet 10-15 lbr, Single face 15-30 lbr), tarik ke arah dalam/luar', 'Hasil kupas tidak boleh sobek (cacat)'),
(4, 'Finish Order', 'Operator', 3, 'Beri label hasil kupas, isi laporan SPK/slip kerja, press manual sampah kupas (pisahkan kertas & laminating)', 'Sampah terkelompokkan rapi'),
-- SOP Laminasi
(6, 'Persiapan Kerja', 'Helper', 1, 'Takar lem dan air dengan rasio Lem : Air = 2 : 1, aduk menggunakan mixer hingga rata, tuang ke bak lem', 'Campuran lem homogen'),
(6, 'Awal Jalan', 'Operator', 2, 'Setting feeder cetakan dan corrugated, atur ketebalan lem, speed konveyor, lalu minta ACC SPV', 'Daya rekat lem optimal'),
(6, 'Proses Massal / Running', 'Helper', 3, 'Menata hasil laminasi posisi bolak-balik tiap 25-50 lbr, berikan beban pada pallet agar tidak melengkung', 'Tumpukan rata tanpa lengkungan'),
-- SOP Potong
(9, 'Persiapan Kerja', 'Operator', 1, 'Nyalakan mesin potong, cek pisau, level oli, cutting stick, kebersihan meja, dan kesesuaian material gudang vs SPK', 'Material dan pisau sesuai'),
(9, 'Awal Jalan', 'Operator', 2, 'Setting ukuran potong sesuai Master Card dan gambar potongan bahan, tumpuk kertas sesuai batas kapasitas gramatur', 'Batas lembar pemotongan dipatuhi'),
(9, 'Awal Jalan', 'Operator', 3, 'Lakukan pemotongan dan cek ukuran, kerataan, kesikuan, kebersihan hasil potong, lalu minta ACC SPV', 'ACC SPV sebelum potong massal');

-- ==========================================
-- 5. SEEDING OPERATIONAL STANDARDS
-- ==========================================
INSERT INTO process_operational_standards (standard_id, process_type, material_or_machine, parameter_name, min_value, max_value, unit_of_measure, operating_condition) VALUES
-- Kupas batching rules
(1, 'Kupas', 'Double Whole Corrugated', 'Sheet Batch Size', 3.00, 8.00, 'sheets', 'Stripping capacity per pull'),
(2, 'Kupas', 'Paper Sheet', 'Sheet Batch Size', 10.00, 15.00, 'sheets', 'Stripping capacity per pull'),
(3, 'Kupas', 'Single Face', 'Sheet Batch Size', 15.00, 30.00, 'sheets', 'Stripping capacity per pull'),
-- Potong max paper sheets per cut
(4, 'Potong', 'Art Paper (AP 85-120gr)', 'Max Cutting Stack', NULL, 500.00, 'sheets', 'Standard cutting stack height'),
(5, 'Potong', 'Art Carton (AC 210-260gr)', 'Max Cutting Stack', NULL, 200.00, 'sheets', 'Standard cutting stack height'),
(6, 'Potong', 'Duplex (DC 250-350gr)', 'Max Cutting Stack', NULL, 200.00, 'sheets', 'Standard cutting stack height'),
(7, 'Potong', 'Duplex (DC 400-500gr)', 'Max Cutting Stack', NULL, 150.00, 'sheets', 'Standard cutting stack height'),
(8, 'Potong', 'Ivory (250-350gr)', 'Max Cutting Stack', NULL, 200.00, 'sheets', 'Standard cutting stack height'),
-- Laminasi Glue ratio
(9, 'Laminasi', 'Water Glue Mixture', 'Glue Ratio', 2.00, 2.00, 'parts glue', 'Mix 2 parts glue with 1 part water (2:1)'),
(10, 'Laminasi', 'Water Glue Mixture', 'Water Ratio', 1.00, 1.00, 'parts water', 'Mix 2 parts glue with 1 part water (2:1)'),
-- Folder Gluer Glue Margin
(11, 'Folder Gluer', 'FG Besar Machine', 'Glue Edge Distance', NULL, 2.00, 'mm', 'Distance from inner/outer edge of glue flap'),
(12, 'Folder Gluer', 'FG Bawah Machine', 'Glue Edge Distance', NULL, 1.00, 'mm', 'Distance from inner/outer edge of glue flap'),
-- Stitching Pallet Stacking Rules
(13, 'Stitching', 'Double Wall No Partition', 'Stack Pattern', 10.00, 10.00, 'saf @ pcs', '10 saf @ 10 pcs per pallet'),
(14, 'Stitching', 'Double Wall With Partition', 'Stack Pattern', 8.00, 10.00, 'saf @ pcs', '8 saf @ 10 pcs (susun satu/satu)'),
(15, 'Stitching', 'Sheet Single No Partition', 'Stack Pattern', 12.00, 10.00, 'saf @ pcs', '12 saf @ 10 pcs per pallet'),
(16, 'Stitching', 'Sheet Single With Partition', 'Stack Pattern', 12.00, 10.00, 'saf @ pcs', '12 saf @ 10 pcs (susun satu/satu)'),
-- Printing Machine Speeds
(17, 'Cetak', 'Offset Press < 1000 sheets', 'Machine Speed', 8500.00, 9000.00, 'druck/hour', 'Optimal press speed target'),
(18, 'Cetak', 'Offset Press < 2000 sheets', 'Machine Speed', 8500.00, 10000.00, 'druck/hour', 'Optimal press speed target'),
(19, 'Cetak', 'Offset Press < 5000 sheets', 'Machine Speed', 8500.00, 12000.00, 'druck/hour', 'Optimal press speed target'),
(20, 'Cetak', 'Offset Press > 5000 sheets', 'Machine Speed', 8500.00, 15000.00, 'druck/hour', 'Optimal press speed target');

-- ==========================================
-- 6. SEEDING QUALITY CONTROL RULES
-- ==========================================
INSERT INTO quality_control_rules (rule_id, sop_id, qc_stage, defect_condition, action_if_ok, action_if_not_ok, form_or_label_used) VALUES
(1, 11, 'QC Pack Final Inspection', 'Produk cacat / tidak sesuai spesifikasi SPK', 'Hitung, packing dengan kertas bungkus & lakban, tempel label', 'Disendirikan & beri label KARANTINA. Jika tidak bisa direparasi -> Form Rusakan -> Rajang -> Kawul', 'Buku laporan QC Pack & Form Rusakan'),
(2, 1, 'In-Process Production', 'Cetak baret, ghosting, kotor, warna tidak presisi', 'Lanjut produksi massal setelah ACC SPV', 'Hentikan mesin, jika SPV tidak ada >1 jam / jam >17.00 WIB, pindah ke jadwal order berikutnya', 'Form Hasil Cetak PPIC & Checklist KPI'),
(3, 3, 'In-Process Production', 'Hasil plong tidak putus, creasing pecah, perforasi tembus, gelembung laminasi', 'Lanjut produksi massal 50 lbr pendampingan SPV', 'Kembali ke setting awal, stel tekanan dan leggera plate', 'Checklist Proses & Label Produksi'),
(4, NULL, 'Incoming Material', 'Raw material/bahan baku tidak sesuai spesifikasi Surat Jalan / Standar Quality', 'QC Incoming tempel sticker ACCEPTANCE, disimpan Gudang', 'Tempel Kartu HOLD (jika butuh kepastian) atau Kartu NG (jika Reject)', 'Incoming Inspection Checksheet, Kartu HOLD, Kartu NG');

-- ==========================================
-- 7. SEEDING DEPARTMENTAL ROUTINES
-- ==========================================
INSERT INTO departmental_routines (routine_id, department_id, personnel_id, frequency, task_category, task_description) VALUES
(1, 'PPIC', 1, 'Harian', 'Kalkulasi & WO', 'Kalkulasi bahan baku CO masuk, buat WO/SPK, update data plan produksi, cetak slip kerja, koordinasi gudang, dan buat PR'),
(2, 'PPIC', 1, 'Mingguan', 'Review Meeting', 'Mengikuti Weekly Review Meeting Internal PPIC dan All Department'),
(3, 'PURCH', 7, 'Harian', 'Procurement', 'Identifikasi kebutuhan pembelian, verifikasi spesifikasi, negosiasi vendor, buat PO, pengajuan approval Finance/Dirut, monitoring pengiriman'),
(4, 'PURCH', 7, 'Mingguan', 'Weekly Reporting', 'Menyusun Weekly Report Purchasing (realisasi HPP, evaluasi vendor, prioritas minggu depan)'),
(5, 'FIN', 6, 'Harian', 'Finance & Billing', 'Pencatatan order DP, buat Invoice/Tanda Terima/Faktur Pajak, penagihan piutang, kelola invoice supplier & kas kecil, rekap omset harian'),
(6, 'FIN', 6, 'Mingguan', 'Payroll & Supplier Payment', 'Proses penggajian harian Sabtu, finalisasi rekap/penjadwalan pembayaran supplier, dan pengajuan Giro ke Direktur'),
(7, 'GA', 2, 'Harian', 'GA Operations', 'Penyusunan jadwal mesin TDP, SPK TDP, koordinasi bahan/tinta, input output mesin, pengawasan CS, AC, Security, Perawatan Aset/Taman, Rentokill'),
(8, 'MKT', 8, 'Harian', 'Sales Operations', 'Pengembangan customer, follow up order, konfirmasi spesifikasi, input PO customer, koordinasi PPC & Design, atur ekspedisi'),
(9, 'DIGITAL', 11, 'Harian', 'Meta Management', 'Meta Business Suite administration (1x), Meta Optimization ROAS/CTR (2x), Content editing, reply comments, cross posting'),
(10, 'DIGITAL', 12, 'Harian', 'E-Commerce & Social Live', 'Shopee Seller admin & optimization (3x), Live Shopee (1x), Live TikTok (1x), content editing, reply DM/Comments'),
(11, 'PPIC', 14, 'Harian', 'Production Planning', 'Adjust plan output harian, create plan SPK baru, update manpower plan ke HRD/SPV, daily genba, mendistribusikan slip kerja'),
(12, 'PROD', 15, 'Harian', 'Digital SPV Duties', 'Cek omset harian shift 2, rekap pemakaian bahan mesin Times/Versa, pembagian job 10 karyawan digital, briefing pagi, cek hasil desain'),
(13, 'EKSP', 16, 'Harian', 'Logistics & Delivery', 'Perencanaan rute pengiriman, briefing tim ekspedisi, cek fisik barang vs Surat Jalan, pengiriman barang, kelola e-toll/BBM, perawatan armada'),
(14, 'PREPRESS', 17, 'Harian', 'Pre Press Operations', 'Penyesuaian desain ACC Mastercard, buat Mastercard & input master product, pembuatan plate cetak, koordinasi pisau diecut & SPV QA/Cetak');

-- ==========================================
-- 8. SEEDING WAREHOUSE PROCEDURES
-- ==========================================
INSERT INTO warehouse_material_procedures (procedure_id, operation_type, step_number, responsible_party, required_document, action_description) VALUES
(1, 'Penerimaan', 1, 'Petugas Gudang', 'Surat Jalan Supplier', 'Menerima Surat Jalan dari driver material dan memastikan fisik barang sesuai dengan dokumen'),
(2, 'Penerimaan', 2, 'Petugas Gudang & QC', 'Incoming Inspection Checksheet', 'Membuat permintaan pengecekan ke QC Incoming, mengambil sampling, dan memeriksa kualitas barang'),
(3, 'Penerimaan', 3, 'Kepala Gudang & QC', 'Buku Terima Barang', 'Jika OK, QC menempel sticker acceptance, Kepala Gudang menandatangani Surat Jalan, Staff mencatat di buku terima barang'),
(4, 'Pengeluaran', 1, 'Kepala Gudang', 'SPK, Jadwal Cetak, Jadwal Potong', 'Menerima dokumen jadwal produksi dari PPIC, memeriksa stok, dan membuat daftar pengeluaran barang gudang'),
(5, 'Pengeluaran', 2, 'Petugas Gudang', 'Bukti Terima Barang', 'Mengirimkan bahan baku ke area produksi/meja trek mesin dan meminta tanda tangan penerima di Bukti Terima Barang'),
(6, 'Pengeluaran', 3, 'Staff Gudang & Persediaan', 'Kartu Persediaan Barang', 'Mencatat pengeluaran bahan baku secara akurat ke dalam Kartu Persediaan Barang');
```

### Query verifikasi analitis yang disertakan di blueprint #2

```sql
-- Query 1: Machine Speed and Inspection Rule Verification
SELECT
    pos.material_or_machine AS machine_type,
    pos.parameter_name,
    pos.min_value AS min_speed_druck_hr,
    pos.max_value AS max_speed_druck_hr,
    pos.operating_condition
FROM process_operational_standards pos
WHERE pos.process_type = 'Cetak'
ORDER BY pos.min_value ASC;

-- Query 2: Cross-Departmental Personnel Task Audit
SELECT
    p.full_name,
    p.job_title,
    d.department_name,
    dr.frequency,
    dr.task_category,
    dr.task_description
FROM departmental_routines dr
JOIN personnel p ON dr.personnel_id = p.personnel_id
JOIN departments d ON dr.department_id = d.department_id
WHERE dr.frequency = 'Harian'
ORDER BY d.department_name, p.full_name;
```
