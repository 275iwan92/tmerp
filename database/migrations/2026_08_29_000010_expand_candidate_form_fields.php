<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Perluas skema kandidat supaya persis mengikuti formulir resmi HR
// "DATA CALON KARYAWAN" (No. Dokumen LJM/HRD/P01/F02, contoh terisi
// diberikan user 2026-08-29 -- form.pdf). Field yang jumlahnya sedikit &
// bernilai tunggal jadi kolom langsung di cnd_candidate (tabel lebar,
// konsisten pola emp_employee yg juga lebar). Field repeatable pendek
// (referensi, kontak darurat, bahasa, komputer, kesediaan) disimpan JSON --
// menghindari sprawl 5+ tabel kecil utk data yg jarang di-query per-baris.
// Field repeatable yg lebih "tabular"/mungkin dilaporkan terpisah (pendidikan
// non-formal, pengalaman organisasi) tetap jadi tabel fisik sendiri.
return new class extends Migration
{
    public function up(): void
    {
        $db = Schema::connection('tm2hrr');

        $db->table('cnd_candidate', function (Blueprint $table) {
            // Data pribadi
            $table->string('nickname', 100)->nullable()->after('name');
            $table->string('gender', 10)->nullable()->after('nickname')->comment('LAKI_LAKI|PEREMPUAN');
            $table->string('religion', 30)->nullable()->after('gender');
            $table->text('address_domisili')->nullable()->after('address')->comment('alamat tinggal, beda dari address=alamat KTP kalau beda');
            $table->string('vehicle_type', 20)->nullable()->comment('MOTOR|MOBIL|TIDAK_ADA');
            $table->json('sim_types')->nullable()->comment('array: C,A,B,B1');

            // Data keluarga (status pernikahan -- susunan keluarga tetap di cnd_family_member)
            $table->string('marital_status', 20)->nullable()->comment('LAJANG|MENIKAH|DUDA|JANDA');
            $table->date('marital_date')->nullable()->comment('tanggal menikah/bercerai');

            // Aktivitas sosial & kegiatan lain
            $table->string('company_acquaintance', 255)->nullable()->comment('kenalan di perusahaan: nama+jabatan');
            $table->string('hobby', 150)->nullable();
            $table->string('leisure_activity', 255)->nullable();
            $table->string('reading_topics', 255)->nullable();
            $table->text('went_abroad')->nullable()->comment('kapan & untuk keperluan apa');

            // Minat & konsep pribadi
            $table->text('why_join_us')->nullable();
            $table->text('what_you_know_about_us')->nullable();
            $table->text('contribution_plan')->nullable();
            $table->text('role_plan_if_hired')->nullable();
            $table->text('job_type_preference')->nullable()->comment('Perencana/Pelaksana + alasan');
            $table->text('job_nature_preference')->nullable()->comment('Penelitian-Teknis/Non Teknis + alasan');
            $table->text('work_environment_preference')->nullable()->comment('Pabrik/Kantor/Lapangan/Laboratorium + alasan');
            $table->text('hindering_environment')->nullable();
            $table->text('preferred_colleague_type')->nullable();
            $table->text('difficult_decision_topic')->nullable();
            $table->text('strengths_weaknesses')->nullable();
            $table->text('who_you_ask_when_stuck')->nullable();

            // Riwayat kesehatan
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->text('illness_history')->nullable();
            $table->text('hospitalized_history')->nullable();
            $table->boolean('is_smoker')->nullable();
            $table->text('physical_disability')->nullable();
            $table->boolean('family_health_ok')->nullable();

            // Daftar pertanyaan
            $table->text('still_receiving_aid')->nullable();
            $table->text('other_dependents')->nullable();
            $table->string('source_of_info', 150)->nullable()->comment('dari mana tahu lowongan');
            $table->decimal('expected_salary', 14, 2)->nullable();
            $table->text('expected_facility')->nullable();
            $table->date('availability_date')->nullable();
            $table->json('willingness_checklist')->nullable()->comment('8 item kesediaan -> bool');
            $table->json('emergency_contacts')->nullable()->comment('array {name,phone,address,relation}, min 3');
            $table->json('professional_references')->nullable()->comment('array {name,phone}, min 2, bukan keluarga');
            $table->json('language_skills')->nullable()->comment('array {language,written,oral,notes}');
            $table->json('computer_skills')->nullable()->comment('array {skill,level,notes}');

            // Pernyataan & e-signature (typed name, bukan gambar tanda tangan)
            $table->boolean('agreement_signed')->default(false);
            $table->string('signature_name', 150)->nullable();
            $table->dateTime('agreement_signed_at')->nullable();
        });

        $db->table('cnd_family_member', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name')->comment('L|P');
            $table->unsignedTinyInteger('age')->nullable()->after('gender');
            $table->string('last_education', 50)->nullable()->after('age');
        });

        $db->table('cnd_education', function (Blueprint $table) {
            $table->unsignedSmallInteger('period_start_year')->nullable()->after('institution');
            $table->string('final_score', 20)->nullable()->comment('nilai akhir, varchar krn format bebas (mis. "35,1")');
        });

        $db->create('cnd_education_nonformal', function (Blueprint $table) {
            $table->bigIncrements('id_nonformal');
            $table->unsignedBigInteger('id_candidate');
            $table->string('course_type', 150)->comment('jenis kursus/pelatihan');
            $table->string('institution', 150)->nullable();
            $table->unsignedSmallInteger('period_start_year')->nullable();
            $table->unsignedSmallInteger('period_end_year')->nullable();
            $table->string('certificate_no', 100)->nullable();

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->create('cnd_organization_experience', function (Blueprint $table) {
            $table->bigIncrements('id_org_exp');
            $table->unsignedBigInteger('id_candidate');
            $table->string('organization_name', 150);
            $table->string('position', 100)->nullable();
            $table->string('period', 50)->nullable();
            $table->text('task_description')->nullable();

            $table->foreign('id_candidate')->references('id_candidate')->on('cnd_candidate')->onDelete('cascade');
        });

        $db->table('cnd_work_experience', function (Blueprint $table) {
            $table->string('location', 150)->nullable()->after('company');
            $table->string('business_type', 150)->nullable();
            $table->string('employee_count', 20)->nullable();
            $table->string('company_phone', 30)->nullable();
            $table->string('position_end', 100)->nullable()->comment('jabatan terakhir, "position" existing = jabatan awal');
            $table->date('position_end_start_date')->nullable();
            $table->date('position_end_end_date')->nullable();
            $table->string('subordinate_count_start', 10)->nullable();
            $table->string('subordinate_count_end', 10)->nullable();
            $table->decimal('salary_start', 14, 2)->nullable();
            $table->decimal('salary_end', 14, 2)->nullable();
            $table->text('benefits')->nullable();
            $table->string('supervisor_name', 150)->nullable();
            $table->string('supervisor_phone', 30)->nullable();
            $table->text('achievement')->nullable();
            $table->text('org_structure_note')->nullable()->comment('gambaran struktur organisasi teks bebas');
            $table->text('key_challenge')->nullable();
            $table->text('improvement_made')->nullable();
            $table->text('leave_reason')->nullable();
        });
    }

    public function down(): void
    {
        $db = Schema::connection('tm2hrr');

        $db->table('cnd_work_experience', function (Blueprint $table) {
            $table->dropColumn([
                'location', 'business_type', 'employee_count', 'company_phone', 'position_end',
                'position_end_start_date', 'position_end_end_date', 'subordinate_count_start',
                'subordinate_count_end', 'salary_start', 'salary_end', 'benefits', 'supervisor_name',
                'supervisor_phone', 'achievement', 'org_structure_note', 'key_challenge', 'improvement_made', 'leave_reason',
            ]);
        });

        $db->dropIfExists('cnd_organization_experience');
        $db->dropIfExists('cnd_education_nonformal');

        $db->table('cnd_education', function (Blueprint $table) {
            $table->dropColumn(['period_start_year', 'final_score']);
        });

        $db->table('cnd_family_member', function (Blueprint $table) {
            $table->dropColumn(['gender', 'age', 'last_education']);
        });

        $db->table('cnd_candidate', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'gender', 'religion', 'address_domisili', 'vehicle_type', 'sim_types',
                'marital_status', 'marital_date', 'company_acquaintance', 'hobby', 'leisure_activity',
                'reading_topics', 'went_abroad', 'why_join_us', 'what_you_know_about_us', 'contribution_plan',
                'role_plan_if_hired', 'job_type_preference', 'job_nature_preference', 'work_environment_preference',
                'hindering_environment', 'preferred_colleague_type', 'difficult_decision_topic', 'strengths_weaknesses',
                'who_you_ask_when_stuck', 'height_cm', 'weight_kg', 'blood_type', 'illness_history',
                'hospitalized_history', 'is_smoker', 'physical_disability', 'family_health_ok', 'still_receiving_aid',
                'other_dependents', 'source_of_info', 'expected_salary', 'expected_facility', 'availability_date',
                'willingness_checklist', 'emergency_contacts', 'professional_references', 'language_skills',
                'computer_skills', 'agreement_signed', 'signature_name', 'agreement_signed_at',
            ]);
        });
    }
};
