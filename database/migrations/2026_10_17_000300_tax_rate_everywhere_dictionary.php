<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk teks layar Tax Register yang kini membaca tarif PPN dari Tax Rate (bukan 12% x DPP tetap).
 * Sumber hulu  : resources/views/v2/screens/acc-tax.blade.php.
 * Input        : -
 * Output       : Satu baris sys_dictionary (ui) dengan terjemahan Indonesia.
 * Dipakai oleh : Layar Finance > Tax (v2).
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_17_000300_tax_rate_everywhere_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        'Active rate: PPN :rate% (Master Data > Tax Rate)' => 'Tarif aktif: PPN :rate% (Master Data > Tarif Pajak)',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
