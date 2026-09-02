<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftarkan configurationstandard.md + reportstandard.md (docs/uistandardindex.md)
// -- CLAUDE.md §6: sys_doc_reference sumber kebenarannya migration, jangan
// ditulis manual.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $now = now();

        $docs = [
            [
                'file_name' => 'configurationstandard.md',
                'title' => 'Standar Configuration (Konstanta Sistem, Branding, Bahasa)',
                'category' => 'STANDARD',
                'related_menu_code' => 'SET_CONFIG',
                'description' => 'Layar Configuration shell lama (di atas grup Security) -- struktur sys_constant/sys_constant_category, identitas aplikasi (APP_NAME/logo/font), bahasa & terjemahan (sys_languages/sys_translations), fin_exchange_rate, geo_country.flag_emoji.',
                'sort_order' => 52,
            ],
            [
                'file_name' => 'reportstandard.md',
                'title' => 'Standar Report 4 Level (Kerangka, Belum Diimplementasi)',
                'category' => 'STANDARD',
                'related_menu_code' => null,
                'description' => 'Kerangka konsep report Data Entry/Supervisor/Manager/Top Management + graphics report -- BELUM ada layar report sungguhan dibangun, scoping menyusul terpisah.',
                'sort_order' => 53,
            ],
        ];

        foreach ($docs as $doc) {
            if ($sys->table('sys_doc_reference')->where('file_name', $doc['file_name'])->exists()) {
                continue;
            }

            $sys->table('sys_doc_reference')->insert($doc + ['activate' => true, 'created_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')
            ->whereIn('file_name', ['configurationstandard.md', 'reportstandard.md'])
            ->delete();
    }
};
