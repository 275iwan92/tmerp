<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan user 2026-08-27: daftar pertanyaan keamanan (fitur Lupa Password
// 4-pertanyaan) TIDAK boleh hardcode array PHP (App\Support\SecurityQuestions,
// dihapus migration berikutnya) -- pindah ke tabel, di tm2sys (bukan tm2mst)
// krn ini murni urusan sistem/security/login, sejalur dgn sec_user/sec_group/
// sec_module yang sudah ada di database yang sama. Prefix `sec_` (bukan
// `sys_`) supaya konsisten dgn tabel security lain di tm2sys, bukan tabel
// UI/config spt sys_app_menu/sys_theme.
//
// Aman langsung redesain kolom sec_user (bukan migrasi data pelan-pelan)
// krn dicek dulu: 0 baris sec_user yang sudah isi security_question_code --
// fitur baru dibangun sesi ini, belum ada user nyata yang pakai.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->create('sec_recovery_question', function (Blueprint $table) {
            $table->increments('id_question');
            $table->string('question_text', 255);
            $table->boolean('activate')->default(true);
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        $now = now();
        $questions = [
            ['question_text' => 'Di kota mana Anda lahir?', 'sort_order' => 10],
            ['question_text' => 'Siapa nama hewan peliharaan pertama Anda?', 'sort_order' => 20],
            ['question_text' => 'Apa nama SD (Sekolah Dasar) Anda?', 'sort_order' => 30],
            ['question_text' => 'Apa makanan favorit Anda saat kecil?', 'sort_order' => 40],
            ['question_text' => 'Apa nama jalan tempat Anda tinggal saat kecil?', 'sort_order' => 50],
            ['question_text' => 'Apa nama panggilan Anda saat kecil?', 'sort_order' => 60],
        ];

        foreach ($questions as $q) {
            DB::connection('tm2sys')->table('sec_recovery_question')->insert($q + [
                'activate' => true,
                'created_at' => $now,
                'created_by' => 'claude_code',
            ]);
        }

        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->dropColumn('security_question_code');
        });

        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->unsignedInteger('security_question_id')->nullable()->after('mother_name_hash')
                ->comment('FK -> sec_recovery_question.id_question. NULL = belum setup.');
            $table->foreign('security_question_id')->references('id_question')->on('sec_recovery_question')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->dropForeign(['security_question_id']);
            $table->dropColumn('security_question_id');
        });

        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->string('security_question_code', 30)->nullable()->after('mother_name_hash');
        });

        Schema::connection('tm2sys')->dropIfExists('sec_recovery_question');
    }
};
