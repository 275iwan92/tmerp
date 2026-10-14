<?php

/*
 * REMARK
 * Tujuan       : Menu Setup > Others > Old Docs Library (perpustakaan dokumen usang, dibaca seperti artikel blog) dan terjemahan teksnya.
 * Sumber hulu  : App\Support\V2\Grid\Setup\DocsLibraryLookup; tabel tm2sys.sys_md_library dari migration 2026_10_14_000400.
 * Input        : -
 * Output       : Satu baris sys_app_menu (SET_DOCS_LIBRARY, v2:setup-docs-library) di grup SET_GRP_OTHERS; pasangan kamus English -> Indonesia.
 * Dipakai oleh : AppMenu (menu v2).
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis atas izin Jo.
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'SET_DOCS_LIBRARY';

    private const UI = [
        'Old Docs Library' => 'Perpustakaan Dokumen Usang',
        'Archived documents, read like articles. Search inside the text, then open one to read it in full. Nothing can be changed here.' => 'Dokumen yang diarsipkan, dibaca seperti artikel. Cari di dalam teks, lalu buka satu untuk dibaca penuh. Tidak ada yang bisa diubah di sini.',
        'Read article' => 'Baca artikel',
        'All articles' => 'Semua artikel',
        'Search inside documents' => 'Cari di dalam dokumen',
        'No documents found.' => 'Tidak ada dokumen yang ditemukan.',
        'This library is read-only.' => 'Perpustakaan ini hanya untuk dibaca.',
        'archived :date' => 'diarsipkan :date',
        'Flows v1' => 'Alur v1',
        'Obsolete' => 'Usang',
        'UI standards v1' => 'Standar UI v1',
        'PPIC archive' => 'Arsip PPIC',
        'Rules' => 'Aturan',
        'Other' => 'Lainnya',
    ];

    public function up(): void
    {
        $db = DB::connection('tm2sys');
        $menu = $db->table('sys_app_menu');
        $others = (clone $menu)->where('code', 'SET_GRP_OTHERS')->first();
        if ($others && ! (clone $menu)->where('code', self::CODE)->exists()) {
            $menu->insert(['parent_id' => $others->id, 'code' => self::CODE, 'label' => 'Old Docs Library', 'icon' => 'fa-solid fa-box-archive',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:setup-docs-library', 'sort_order' => 30, 'activate' => 1,
                'required_object' => null, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(),
                'created_at' => now()]);
        }

        if (Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
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
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', self::CODE)->delete();
    }
};
