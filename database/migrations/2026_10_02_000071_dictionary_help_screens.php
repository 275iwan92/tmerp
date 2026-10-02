<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks layar Help (Help Center, Setup > Help Topics / Help Lines). Hanya mengisi yang belum punya
// terjemahan. Isi help-nya sendiri (konteks 'help') dimuat `php artisan help:import`.
return new class extends Migration
{
    private const UI = [
        'Help Center' => 'Pusat Bantuan', 'Help Topics' => 'Topik Bantuan', 'Help Lines' => 'Isi Bantuan',
        'Screen' => 'Layar', 'What it is for' => 'Untuk apa', 'Keywords' => 'Kata cari', 'Kind' => 'Jenis', 'Step / Field' => 'Langkah / Isian',
        'Explanation' => 'Penjelasan', 'How to use and field explanations' => 'Cara pakai dan penjelasan isian', 'How to use' => 'Cara pakai',
        'Field' => 'Isian', 'Input screen' => 'Layar input', 'General guide' => 'Panduan umum', 'Topic' => 'Topik', 'Purpose (English)' => 'Kegunaan (English)',
        'Text (English)' => 'Teks (English)', 'How to use (step)' => 'Cara pakai (langkah)', 'Field explanation' => 'Penjelasan isian',
        'General (everyone)' => 'Umum (semua orang)',
        'Search screen, purpose or keyword...' => 'Cari layar, kegunaan, atau kata cari...', 'Search topic, field or text...' => 'Cari topik, isian, atau teks...',
        'One row = one screen, report or general guide. Open a row to read how to use it and what every field means. Search by screen name or by what you want to do.' => 'Satu baris = satu layar, laporan, atau panduan umum. Buka barisnya untuk membaca cara pakai dan arti tiap isian. Cari dengan nama layar atau dengan apa yang ingin Anda kerjakan.',
        'One row = one screen, report or general guide. Key = the screen address in Menu Setup (guide-... for a general guide); a saved Key cannot be changed. Purpose says what the screen is for. Keywords are the words people type when they look for it. Steps and field explanations are in Help Lines.' => 'Satu baris = satu layar, laporan, atau panduan umum. Key = alamat layar di Menu Setup (guide-... untuk panduan umum); Key yang sudah tersimpan tidak bisa diubah. Purpose = untuk apa layar itu. Keywords = kata yang diketik orang saat mencarinya. Langkah dan penjelasan isian ada di Help Lines.',
        'One row = one short text of a help topic: a step of how to use the screen, or the explanation of one field or report column. Keep each text to one action or one fact, at most 255 characters. Field = the label exactly as written on the screen. Order sets the sequence inside the topic.' => 'Satu baris = satu teks pendek milik sebuah topik: satu langkah cara pakai layar, atau penjelasan satu isian / kolom laporan. Satu teks = satu tindakan atau satu fakta, paling panjang 255 karakter. Field = label persis seperti di layar. Order = urutan di dalam topik.',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            // source_en 255 karakter: panduan yang lebih panjang tetap tampil English (kamus tidak mencatatnya).
            if (mb_strlen($en) > 255 || mb_strlen($id) > 255) {
                continue;
            }
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
