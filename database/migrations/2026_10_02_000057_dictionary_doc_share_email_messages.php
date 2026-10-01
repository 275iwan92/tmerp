<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pesan server dialog Email x-dl::doc-share (routes/v2.php docshare.email) kini ditulis English; terjemahan Indonesianya di sini.
// Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Your e-mail is not verified yet. Open Profile > E-mail to verify it first:' => 'E-mail Anda belum terverifikasi. Buka Profil > E-mail untuk verifikasi dulu:',
        'Enter at least one To address.' => 'Isi minimal satu alamat To.',
        'Invalid To address: :input' => 'Alamat To tidak valid: :input',
        'Invalid CC address: :input' => 'Alamat CC tidak valid: :input',
        'Invalid BCC address: :input' => 'Alamat BCC tidak valid: :input',
        'Subject' => 'Subjek',
        'E-mail sent to' => 'E-mail terkirim ke',
        'E-mail could not be sent:' => 'E-mail gagal dikirim:',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
