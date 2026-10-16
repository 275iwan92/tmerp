<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia teks tombol Buy (PR + PO) di Material Planning dan pesan hasilnya.
 * Sumber hulu  : MaterialPlanning::buy, routes/v2_planning.php (rute buy), PurchaseOrder::submit.
 * Input        : -
 * Output       : Pasangan sys_dictionary + sys_dictionary_lang (id); hanya mengisi yang belum punya terjemahan.
 * Dipakai oleh : Translator (t, tf) dan komponen DevLib.
 * Status       : Jalankan di ddt* dan tm2*.
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        'Buy (PR + PO)' => 'Beli (PR + PO)',
        'PR :pr and PO :po were created as Draft. Both still need their normal approval.' => 'PR :pr dan PO :po dibuat sebagai Draft. Keduanya tetap memerlukan approval biasa.',
        'PR :pr was created as Draft. The material has no suggested supplier, so no PO was made.' => 'PR :pr dibuat sebagai Draft. Bahan ini belum punya supplier usulan, jadi PO tidak dibuat.',
        'A PR on this PO is not Approved yet.' => 'Ada PR pada PO ini yang belum Approved.',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    // Isi kamus dibiarkan (bisa sudah diubah user).
    public function down(): void {}
};
