<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English tumpukan gudang (Stack Monitor, panel tumpukan di Warehouse Map, sisa rim) yang
// belum ikut migration 000020. Nama menu / dokumen tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Over height limit' => 'Lebih dari batas tinggi', 'Recorded' => 'Tercatat', 'Units Above' => 'Satuan di Atasnya', 'Record' => 'Catatan', 'Stack Height' => 'Tinggi Tumpukan',
        'Buried' => 'Tertimbun', 'pallet' => 'palet', 'pack' => 'pak', 'not labelled' => 'belum berlabel', 'Pallet / Pack No.' => 'No. Palet / Pak',
        'Remnant limit: :limit sheets (Settings, Constants).' => 'Batas sisa: :limit lembar (Settings, Constants).',
        'Relabelled. The move to :bin waits for approval (Stock Transfer :no).' => 'Sudah dilabel ulang. Pemindahan ke :bin menunggu approval (Stock Transfer :no).',
        'One row per labelled pallet or pack, grouped by bin. Stack 1 is the bottom. Buried = the unit to use first has a pallet on top of it. Stack not recorded = stock in the bin without a label.' => 'Satu baris per palet atau pak berlabel, terkelompok per bin. Tumpukan 1 = paling bawah. Tertimbun = satuan yang harus dipakai dulu ditindih palet. Tumpukan belum dicatat = stok di bin tanpa label.',
        'Max. Stacked Pallets' => 'Maks. Palet Ditumpuk',
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
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
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
