<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fitur "Lupa Password" berbasis 4 pertanyaan (bukan email, sesuai
// security_defaultpassword_spec.md §0 -- gateway email/WA belum tentu jalan
// di server ini). Keputusan user 2026-08-27:
// 1. tanggal_lahir -- utk akun tertaut emp_employee, diisi otomatis dari
//    emp_employee.birth_date saat setup (disalin sekali, bukan live-query
//    tiap verifikasi -- birth_date praktis tak pernah berubah).
// 2. nama_ibu_kandung -- di-HASH (bcrypt, spt password) krn sensitif &
//    cuma perlu dicocokkan, tak pernah perlu ditampilkan balik ke siapa pun.
// 3. no_hp_terdaftar -- pakai kolom sec_user.phone yang SUDAH ADA, apa adanya.
// 4. pertanyaan_keamanan -- dipilih dari daftar tetap (App\Support\SecurityQuestions),
//    jawabannya di-HASH juga (alasan sama dgn nama ibu).
// Setelah 4-4nya benar -> reset ke default_password yang SUDAH ADA (Crypt,
// bisa dibaca admin) + must_change_password=1, infrastruktur lama dipakai
// ulang, TIDAK bikin mekanisme reset baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone')
                ->comment('Verifikasi lupa password poin 1. Tertaut employee_id -> disalin dari emp_employee.birth_date saat setup; akun tak tertaut -> isi manual.');
            $table->string('mother_name_hash', 255)->nullable()->after('birth_date')
                ->comment('Verifikasi lupa password poin 2 (nama ibu kandung) -- HASH bcrypt, bukan plaintext/Crypt (tak pernah perlu ditampilkan balik).');
            $table->string('security_question_code', 30)->nullable()->after('mother_name_hash')
                ->comment('Kode pertanyaan keamanan terpilih -- lihat App\\Support\\SecurityQuestions::LIST.');
            $table->string('security_answer_hash', 255)->nullable()->after('security_question_code')
                ->comment('Verifikasi lupa password poin 4 -- HASH bcrypt.');
            $table->timestamp('security_setup_at')->nullable()->after('security_answer_hash')
                ->comment('Kapan user menyelesaikan setup 4 pertanyaan keamanan. NULL = belum setup, dipaksa lewat /security/setup.');
        });
    }

    public function down(): void
    {
        Schema::connection('tm2sys')->table('sec_user', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'mother_name_hash', 'security_question_code', 'security_answer_hash', 'security_setup_at']);
        });
    }
};
