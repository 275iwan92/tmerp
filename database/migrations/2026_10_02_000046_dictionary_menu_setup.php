<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks layar Setup > Menu Setup. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Menu Setup' => 'Pengaturan Menu', 'Under' => 'Di Bawah', 'Short Label' => 'Label Pendek', 'Icon' => 'Ikon', 'Order' => 'Urutan',
        'Change the label, icon, order and active flag of every menu row here. Icon = Font Awesome class (fa-solid fa-truck); each item in one list needs its own icon. Switching a row off hides it and closes its screen. Reload the page to see the menu change.' => 'Ubah label, ikon, urutan, dan aktif / nonaktif setiap baris menu di sini. Ikon = kelas Font Awesome (fa-solid fa-truck); tiap item dalam satu daftar perlu ikon sendiri. Baris yang dimatikan hilang dari menu dan layarnya tertutup. Muat ulang halaman untuk melihat perubahan menu.',
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
