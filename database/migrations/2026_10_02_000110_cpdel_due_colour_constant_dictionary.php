<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CPDEL: baris berwarna menurut jatuh tempo slot (user 2026-10-02, docs/globaluserrules.md C.32). Batas "kuning" (H-1 .. H-n,
// hari kerja) = konstanta CPDEL_DUE_SOON_DAYS, diubah user di Settings > Constants. + terjemahan Indonesia teks barunya
// (hanya mengisi yang belum punya terjemahan).
return new class extends Migration
{
    private const UI = [
        'Due' => 'Jatuh tempo', 'Overdue' => 'Lewat tanggal', 'Due soon' => 'Segera jatuh tempo', 'Next 7 days' => '7 hari ke depan',
        'days' => 'hari', 'days left' => 'hari lagi', 'D-' => 'H-', 'Today' => 'Hari ini', 'Late' => 'Terlambat', 'Shipped' => 'Terkirim',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        if (! $db->table('sys_constant')->where('constant_key', 'CPDEL_DUE_SOON_DAYS')->exists()) {
            $db->table('sys_constant')->insert(['constant_key' => 'CPDEL_DUE_SOON_DAYS', 'category_code' => 'PRODUCTION', 'constant_group' => 'CPDEL',
                'constant_number' => 2, 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system',
                'description' => 'Customer Plan Delivery: a delivery line turns yellow when its customer slot is this many working days away or less (Sunday and Holiday Calendar days are not counted)']);
        }

        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'CPDEL_DUE_SOON_DAYS')->delete();
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
