<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Modul Job Description + SOP (keputusan user 2026-09-13): "tambahkan job
// description module dimana di setiap user terdapat job description yang
// muncul di user profile dalam bentuk readonly. Yang input adalah HR
// Officer. Begitu pula dengan SOP" -- input HR Officer, tampil read-only di
// profil Self-Service. Menyusul diskusi sesi ini soal blueprint SOP mentah
// yang diberikan user (docs/sop_module_blueprint_reference.md) -- versi
// pertama SOP di sini SENGAJA baru berupa REGISTER DOKUMEN (metadata +
// konten), belum mengambil seluruh isi blueprint (workflow_steps/QC
// rules/dst) yang masih menunggu arahan lebih lanjut.
//
// Job Description ditaruh per POSISI (org_position), bukan per pegawai --
// dalam praktik HR nyata, deskripsi kerja melekat ke jabatan (dipakai
// bersama oleh semua pemegang posisi itu), bukan ditulis ulang per orang.
// Employee mewarisi lewat position_id yang sudah ada.
return new class extends Migration
{
    public function up(): void
    {
        $mst = Schema::connection('tm2mst');

        $mst->table('org_position', function (Blueprint $table) {
            $table->text('job_description')->nullable()->after('position_name');
            $table->string('job_description_updated_by', 100)->nullable();
            $table->timestamp('job_description_updated_at')->nullable();
        });

        // Register dokumen SOP -- prefix baru `sop_` (domain baru, belum ada
        // di CLAUDE.md §3 sebelum sesi ini). department_id NULL = berlaku
        // semua departemen. Isi (`content`) polos dulu (textarea panjang),
        // belum dipecah jadi langkah-kerja terstruktur seperti
        // sop_workflow_steps di blueprint mentah.
        $mst->create('sop_document', function (Blueprint $table) {
            $table->bigIncrements('sop_id');
            $table->string('sop_code', 50)->unique();
            $table->string('title', 200);
            $table->string('revision', 10)->default('0');
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->text('purpose')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();

            $table->index('department_id');
        });

        // Role gate baru (pola sama dgn QA/PROD_MGR/OPS_MGR, SecUser::hasGroup()):
        // belum ada satu pun sec_user yang dipasangkan grup ini -- posisi
        // "Personalia" (org_position id 89) ada di master tapi belum ada
        // pegawai/akun yang menjabatnya (dicek live 2026-09-13), jadi TIDAK
        // di-assign otomatis ke akun mana pun. Assign manual lewat layar
        // Security setelah user menentukan siapa HR Officer sesungguhnya.
        DB::connection('tm2sys')->table('sec_group')->updateOrInsert(
            ['group_code' => 'HR_OFFICER'],
            [
                'group_name' => 'HR Officer',
                'mgmt_level' => 2,
                'activate' => 1,
                'created_by' => 'claude_code',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sop_document');

        Schema::connection('tm2mst')->table('org_position', function (Blueprint $table) {
            $table->dropColumn(['job_description', 'job_description_updated_by', 'job_description_updated_at']);
        });

        DB::connection('tm2sys')->table('sec_group')->where('group_code', 'HR_OFFICER')->delete();
    }
};
