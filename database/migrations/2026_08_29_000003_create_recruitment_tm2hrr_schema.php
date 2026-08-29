<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Modul Recruitment (tm2hrr) -- mencerminkan docs/recruitment_tier3.md SECTION 4,
// dengan 3 deviasi sadar (dicatat di docs/recruitment_state.yaml & plan sesi
// 2026-08-29, BUKAN diam-diam):
// 1. cnd_device_token TIDAK dibuat -- FCM push di luar scope iterasi ini.
//    rcr_sync_log TETAP dibuat (skema Tier3 lengkap, murah dipertahankan)
//    TAPI TIDAK DIPAKAI di iterasi ini -- sempat dicoba pindah portal ke DB
//    terpisah tm2crp (Plan B beneran) 2026-08-29, user putuskan BATAL/ditunda
//    ("pakai mysql dulu saja, tm2crp nanti kita pindahkan") -- portal
//    simulasi nulis LANGSUNG ke tabel tm2hrr di bawah (cnd_candidate dkk),
//    TIDAK ada sync worker aktif, L8 Rekap Sync Log ikut dilewati (konsisten
//    keputusan awal simulasi_portal_sementara di recruitment_state.yaml).
// 2. rcr_approver_assignment BARU (tidak ada di Tier3) -- lapisan resolusi
//    approver_role -> sec_user LANGSUNG, pengganti asumsi role->org_position
//    Tier2 SECTION 2 yang belum bisa dipakai (org_position "HR Manager"/
//    "Dept Head" belum ada rapi, emp_employee.position_id org terkait masih
//    legacy-unmapped). Data-driven, gampang diubah tanpa ubah kode begitu
//    org_position dirapikan nanti.
// 3. Kolom status pakai VARCHAR + comment berisi daftar nilai (bukan MySQL
//    ENUM) -- konsisten pola do_delivery/do_line (2026_08_28_000011).
//
// Semua tabel dalam SATU DB tm2hrr -> FK antar rcr_/vac_/cnd_/app_* FISIK
// (aturan globalarchitecture_tier0.md). Referensi ke tm2mst (org_department,
// emp_employee)/tm2sys (sec_user) LOGICAL, kolom unsignedBigInteger polos +
// comment, TANPA FK constraint (lintas-DB).
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2hrr');

        $db->create('rcr_track', function (Blueprint $table) {
            $table->tinyIncrements('id_track');
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
        });

        $db->create('rcr_requisition', function (Blueprint $table) {
            $table->bigIncrements('id_requisition');
            $table->string('requisition_no', 20)->unique()->comment('Format RCR{YY}{NNNNN}, digenerate app');
            $table->unsignedTinyInteger('id_track');
            $table->unsignedBigInteger('department_id')->comment('LOGICAL -> tm2mst.org_department.department_id');
            $table->string('position_title', 150);
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->text('qualification');
            $table->text('reason');
            $table->unsignedBigInteger('requested_by')->comment('LOGICAL -> tm2sys.sec_user.id_user');
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|SUBMITTED|APPROVED|REJECTED|CLOSED');
            $table->timestamps();

            $table->foreign('id_track')->references('id_track')->on('rcr_track');
            $table->index('status', 'idx_requisition_status');
        });

        $db->create('rcr_approval_matrix', function (Blueprint $table) {
            $table->bigIncrements('id_matrix');
            $table->unsignedTinyInteger('id_track');
            $table->unsignedTinyInteger('approval_seq');
            $table->string('approver_role', 50)->comment('Dept Head/HR Manager/Direksi -- diresolusi via rcr_approver_assignment');

            $table->foreign('id_track')->references('id_track')->on('rcr_track');
            $table->unique(['id_track', 'approval_seq'], 'uq_matrix_track_seq');
        });

        $db->create('rcr_requisition_approval', function (Blueprint $table) {
            $table->bigIncrements('id_approval');
            $table->unsignedBigInteger('id_requisition');
            $table->unsignedTinyInteger('approval_seq');
            $table->unsignedBigInteger('approver_id')->comment('LOGICAL -> tm2sys.sec_user.id_user, resolved via rcr_approver_assignment');
            $table->string('decision', 20)->default('PENDING')->comment('PENDING|APPROVED|REJECTED');
            $table->dateTime('decided_at')->nullable();
            $table->string('note', 255)->nullable();

            $table->foreign('id_requisition')->references('id_requisition')->on('rcr_requisition')->onDelete('cascade');
            $table->index(['approver_id', 'decision'], 'idx_reqapproval_approver');
        });

        $db->create('rcr_approver_assignment', function (Blueprint $table) {
            $table->bigIncrements('id_assignment');
            $table->string('approver_role', 50)->comment('Harus match rcr_approval_matrix.approver_role / rcr_offer_approval_matrix.approver_role');
            $table->unsignedBigInteger('sec_user_id')->comment('LOGICAL -> tm2sys.sec_user.id_user');
            $table->unsignedBigInteger('department_id')->nullable()->comment('LOGICAL -> tm2mst.org_department.department_id -- NULL = berlaku semua departemen');
            $table->string('notes', 255)->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->index('approver_role', 'idx_approverassign_role');
        });

        $db->create('vac_vacancy', function (Blueprint $table) {
            $table->bigIncrements('id_vacancy');
            $table->string('vacancy_code', 30)->unique();
            $table->unsignedBigInteger('id_requisition');
            $table->string('channel', 20)->comment('PORTAL_SENDIRI|JOBSTREET|LINKEDIN|REFERRAL|LAINNYA');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('id_requisition')->references('id_requisition')->on('rcr_requisition');
        });

        $db->create('cnd_candidate', function (Blueprint $table) {
            $table->bigIncrements('id_candidate');
            $table->string('national_id', 20)->unique()->comment('KTP, dedupe key utama');
            $table->string('passport_no', 30)->nullable()->comment('fallback key WNA');
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->boolean('activate')->default(true)->comment('talent pool tidak pernah dihapus saat reject, ini toggle non-aktif manual kalau perlu');
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $db->create('cnd_family_member', function (Blueprint $table) {
            $table->bigIncrements('id_family');
            $table->unsignedBigInteger('id_candidate');
            $table->string('relation_type', 20)->comment('FATHER|MOTHER|FATHER_IN_LAW|MOTHER_IN_LAW|SPOUSE|CHILD');
            $table->string('name', 150);
            $table->string('current_job', 150)->nullable();
            $table->string('current_employer', 150)->nullable();

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->create('cnd_education', function (Blueprint $table) {
            $table->bigIncrements('id_edu');
            $table->unsignedBigInteger('id_candidate');
            $table->string('level', 30);
            $table->string('institution', 150);
            $table->string('major', 100)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->create('cnd_work_experience', function (Blueprint $table) {
            $table->bigIncrements('id_work');
            $table->unsignedBigInteger('id_candidate');
            $table->string('company', 150);
            $table->string('position', 100);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->create('cnd_document', function (Blueprint $table) {
            $table->bigIncrements('id_doc');
            $table->unsignedBigInteger('id_candidate');
            $table->string('doc_type', 20)->comment('CV|IJAZAH|SERTIFIKAT|KTP|FOTO|LAINNYA');
            $table->string('file_path', 255);
            $table->dateTime('uploaded_at');

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->create('app_pipeline_stage_config', function (Blueprint $table) {
            $table->bigIncrements('id_stage_config');
            $table->unsignedTinyInteger('id_track');
            $table->unsignedTinyInteger('stage_seq');
            $table->string('stage_code', 20);
            $table->string('stage_name', 100);
            $table->boolean('is_mandatory')->default(true);

            $table->foreign('id_track')->references('id_track')->on('rcr_track');
            $table->unique(['id_track', 'stage_seq'], 'uq_stageconfig_track_seq');
        });

        $db->create('app_application', function (Blueprint $table) {
            $table->bigIncrements('id_application');
            $table->unsignedBigInteger('id_candidate');
            $table->unsignedBigInteger('id_vacancy');
            $table->char('submission_uuid', 36)->nullable()->comment('RESERVED -- jejak sync worker eksternal, tidak dipakai selama portal nulis langsung');
            $table->unsignedTinyInteger('current_stage_seq')->default(1);
            $table->string('status', 20)->default('APPLIED')->comment('APPLIED|IN_PROGRESS|OFFERING|HIRED|REJECTED|WITHDRAWN');
            $table->string('reject_reason', 255)->nullable();
            $table->unsignedBigInteger('provisioned_employee_id')->nullable()->comment('LOGICAL -> tm2mst.emp_employee.employee_id -- idempotency guard hire trigger');
            $table->dateTime('provisioned_at')->nullable();
            $table->dateTime('applied_at');

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate');
            $table->foreign('id_vacancy')->references('id_vacancy')->on('vac_vacancy');
            $table->index('status', 'idx_app_status');
        });

        $db->create('app_interview_schedule', function (Blueprint $table) {
            $table->bigIncrements('id_schedule');
            $table->unsignedBigInteger('id_application');
            $table->string('stage_code', 20);
            $table->dateTime('scheduled_at');
            $table->unsignedBigInteger('interviewer_id')->comment('LOGICAL -> tm2sys.sec_user.id_user');
            $table->string('location', 150)->nullable();
            $table->string('status', 20)->default('SCHEDULED')->comment('SCHEDULED|DONE|CANCELLED|RESCHEDULED');

            $table->foreign('id_application')->references('id_application')->on('app_application')->onDelete('cascade');
        });

        $db->create('app_assessment_result', function (Blueprint $table) {
            $table->bigIncrements('id_result');
            $table->unsignedBigInteger('id_application');
            $table->string('stage_code', 20);
            $table->unsignedBigInteger('assessor_id')->comment('LOGICAL -> tm2sys.sec_user.id_user');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('recommendation', 20)->comment('LANJUT|TIDAK_LANJUT|PERTIMBANGAN');
            $table->text('notes')->nullable();
            $table->dateTime('assessed_at');

            $table->foreign('id_application')->references('id_application')->on('app_application')->onDelete('cascade');
        });

        $db->create('rcr_offer_approval_matrix', function (Blueprint $table) {
            $table->bigIncrements('id_matrix');
            $table->unsignedTinyInteger('id_track');
            $table->unsignedTinyInteger('approval_seq');
            $table->string('approver_role', 50);

            $table->foreign('id_track')->references('id_track')->on('rcr_track');
            $table->unique(['id_track', 'approval_seq'], 'uq_offermatrix_track_seq');
        });

        $db->create('app_offer', function (Blueprint $table) {
            $table->bigIncrements('id_offer');
            $table->unsignedBigInteger('id_application');
            $table->unsignedBigInteger('previous_offer_id')->nullable()->comment('revisi/negosiasi = row baru, bukan edit in-place');
            $table->decimal('proposed_salary', 14, 2);
            $table->string('employment_type', 20)->comment('TETAP|KONTRAK|HARIAN|BORONGAN -- independen dari rcr_track');
            $table->date('start_date');
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|PENDING_APPROVAL|APPROVED|SENT|ACCEPTED|DECLINED|EXPIRED');
            $table->unsignedBigInteger('created_by')->comment('LOGICAL -> tm2sys.sec_user.id_user (bukan string login, ikut DDL asli)');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('id_application')->references('id_application')->on('app_application');
            $table->foreign('previous_offer_id')->references('id_offer')->on('app_offer')->onDelete('set null');
        });

        $db->create('app_offer_approval', function (Blueprint $table) {
            $table->bigIncrements('id_approval');
            $table->unsignedBigInteger('id_offer');
            $table->unsignedTinyInteger('approval_seq');
            $table->unsignedBigInteger('approver_id')->comment('LOGICAL -> tm2sys.sec_user.id_user');
            $table->string('decision', 20)->default('PENDING');
            $table->dateTime('decided_at')->nullable();

            $table->foreign('id_offer')->references('id_offer')->on('app_offer')->onDelete('cascade');
        });

        $db->create('rcr_sync_log', function (Blueprint $table) {
            $table->bigIncrements('id_log');
            $table->char('submission_uuid', 36)->unique();
            $table->string('backend_used', 20)->default('MARIADB')->comment('FIREBASE|MARIADB -- simulasi ini selalu MARIADB (tm2crp)');
            $table->dateTime('fetched_at');
            $table->string('result', 20)->comment('CANDIDATE_CREATED|CANDIDATE_UPDATED|FAILED');
            $table->string('error_note', 255)->nullable();
            $table->unsignedBigInteger('id_application')->nullable();

            $table->foreign('id_application')->references('id_application')->on('app_application');
        });

        // ── SEED: rcr_track (Tier 1 SECTION 2.4) ──
        DB::connection('tm2hrr')->table('rcr_track')->insert([
            ['id_track' => 1, 'code' => 'STAFF', 'name' => 'Staff/Karyawan Tetap-Kontrak kantoran', 'is_active' => true],
            ['id_track' => 2, 'code' => 'NON_STAFF', 'name' => 'Non-Staff (operator, teknisi, dll non-kantoran)', 'is_active' => true],
            ['id_track' => 3, 'code' => 'BORONGAN', 'name' => 'Tenaga Borongan/Harian', 'is_active' => true],
        ]);

        // ── SEED: rcr_approval_matrix (Tier 2 SECTION 2) ──
        DB::connection('tm2hrr')->table('rcr_approval_matrix')->insert([
            ['id_track' => 1, 'approval_seq' => 1, 'approver_role' => 'Dept Head'],
            ['id_track' => 1, 'approval_seq' => 2, 'approver_role' => 'HR Manager'],
            ['id_track' => 1, 'approval_seq' => 3, 'approver_role' => 'Direksi'],
            ['id_track' => 2, 'approval_seq' => 1, 'approver_role' => 'Dept Head'],
            ['id_track' => 2, 'approval_seq' => 2, 'approver_role' => 'HR Manager'],
            ['id_track' => 3, 'approval_seq' => 1, 'approver_role' => 'Dept Head'],
        ]);

        // ── SEED: rcr_offer_approval_matrix (Tier 2 SECTION 5) ──
        DB::connection('tm2hrr')->table('rcr_offer_approval_matrix')->insert([
            ['id_track' => 1, 'approval_seq' => 1, 'approver_role' => 'HR Manager'],
            ['id_track' => 1, 'approval_seq' => 2, 'approver_role' => 'Direksi'],
            ['id_track' => 2, 'approval_seq' => 1, 'approver_role' => 'HR Manager'],
            ['id_track' => 3, 'approval_seq' => 1, 'approver_role' => 'HR Manager'],
        ]);

        // ── SEED: app_pipeline_stage_config (Tier 2 SECTION 3) ──
        DB::connection('tm2hrr')->table('app_pipeline_stage_config')->insert([
            ['id_track' => 1, 'stage_seq' => 1, 'stage_code' => 'SCREENING', 'stage_name' => 'Screening Administrasi', 'is_mandatory' => true],
            ['id_track' => 1, 'stage_seq' => 2, 'stage_code' => 'PSIKOTES', 'stage_name' => 'Psikotes', 'is_mandatory' => true],
            ['id_track' => 1, 'stage_seq' => 3, 'stage_code' => 'INTERVIEW_HR', 'stage_name' => 'Interview HR', 'is_mandatory' => true],
            ['id_track' => 1, 'stage_seq' => 4, 'stage_code' => 'INTERVIEW_USER', 'stage_name' => 'Interview User Dept', 'is_mandatory' => true],
            ['id_track' => 1, 'stage_seq' => 5, 'stage_code' => 'OFFERING', 'stage_name' => 'Offering', 'is_mandatory' => true],
            ['id_track' => 2, 'stage_seq' => 1, 'stage_code' => 'SCREENING', 'stage_name' => 'Screening Administrasi', 'is_mandatory' => true],
            ['id_track' => 2, 'stage_seq' => 2, 'stage_code' => 'INTERVIEW_HR', 'stage_name' => 'Interview HR', 'is_mandatory' => true],
            ['id_track' => 2, 'stage_seq' => 3, 'stage_code' => 'INTERVIEW_USER', 'stage_name' => 'Interview User Dept', 'is_mandatory' => true],
            ['id_track' => 2, 'stage_seq' => 4, 'stage_code' => 'OFFERING', 'stage_name' => 'Offering', 'is_mandatory' => true],
            ['id_track' => 3, 'stage_seq' => 1, 'stage_code' => 'SCREENING', 'stage_name' => 'Screening Administrasi', 'is_mandatory' => true],
            ['id_track' => 3, 'stage_seq' => 2, 'stage_code' => 'INTERVIEW_HR', 'stage_name' => 'Interview HR', 'is_mandatory' => true],
            ['id_track' => 3, 'stage_seq' => 3, 'stage_code' => 'OFFERING', 'stage_name' => 'Offering', 'is_mandatory' => true],
        ]);

        // ── SEED: rcr_approver_assignment -- nama riil dikonfirmasi user 2026-08-29 ──
        // SEMENTARA: org_position "HR Manager"/"Dept Head" belum rapi di tm2mst
        // (lihat recruitment_state.yaml delta_pending #1) -- resolusi langsung
        // by sec_user, bukan by posisi. Reassign begitu org_position dibenahi.
        DB::connection('tm2hrr')->table('rcr_approver_assignment')->insert([
            ['approver_role' => 'Direksi', 'sec_user_id' => 1, 'department_id' => null, 'notes' => 'Johannes Iwan (GM) -- sec_user login johiwan', 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'claude_code'],
            ['approver_role' => 'Dept Head', 'sec_user_id' => 169, 'department_id' => null, 'notes' => 'Annisa Kusumaningtyas (Operational Manager) -- sec_user login annisa, berlaku semua dept selama belum ada kepala per-dept', 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'claude_code'],
            ['approver_role' => 'HR Manager', 'sec_user_id' => 175, 'department_id' => null, 'notes' => 'Sania Maura -- sec_user login sania3', 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'claude_code'],
        ]);
    }

    public function down(): void
    {
        $db = Schema::connection('tm2hrr');

        $db->dropIfExists('rcr_sync_log');
        $db->dropIfExists('app_offer_approval');
        $db->dropIfExists('app_offer');
        $db->dropIfExists('rcr_offer_approval_matrix');
        $db->dropIfExists('app_assessment_result');
        $db->dropIfExists('app_interview_schedule');
        $db->dropIfExists('app_application');
        $db->dropIfExists('app_pipeline_stage_config');
        $db->dropIfExists('cnd_document');
        $db->dropIfExists('cnd_work_experience');
        $db->dropIfExists('cnd_education');
        $db->dropIfExists('cnd_family_member');
        $db->dropIfExists('cnd_candidate');
        $db->dropIfExists('vac_vacancy');
        $db->dropIfExists('rcr_approver_assignment');
        $db->dropIfExists('rcr_requisition_approval');
        $db->dropIfExists('rcr_approval_matrix');
        $db->dropIfExists('rcr_requisition');
        $db->dropIfExists('rcr_track');
    }
};
