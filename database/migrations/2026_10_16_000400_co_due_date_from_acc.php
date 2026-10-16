<?php

/*
 * REMARK
 * Tujuan       : Due date Customer Order otomatis dari ACC Digital + CO_LEAD_WORKDAYS hari kerja (Jo 2026-10-16): kolom sumber dan alasan penimpaan, konstanta jumlah hari, dan terjemahan teks layar.
 * Sumber hulu  : App\Services\Sales\CoDueDate, CoOrderItem (event saving).
 * Input        : -
 * Output       : tm2sdi.co_order_item.due_source (ACC | MANUAL | null) dan due_note (alasan Sales menimpa); tm2sys.sys_constant CO_LEAD_WORKDAYS = 14; pasangan kamus.
 * Dipakai oleh : CoDueDate, CoOrderItem, layar Customer Order (v2) dan v1 co/entry.
 * Status       : Jalankan di ddt* dan tm2* (aturan paritas Jo 2026-10-16). Baris CO lama tidak diubah.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UI = [
        'Due Date Note' => 'Catatan Due Date',
    ];

    public function up(): void
    {
        $sdi = Schema::connection('tm2sdi');
        if (! $sdi->hasColumn('co_order_item', 'due_source')) {
            $sdi->table('co_order_item', function (Blueprint $t) {
                $t->string('due_source', 6)->nullable()->after('due_date');
                $t->string('due_note', 200)->nullable()->after('due_source');
            });
        }

        $db = DB::connection('tm2sys');
        if (! $db->table('sys_constant')->where('constant_key', 'CO_LEAD_WORKDAYS')->exists()) {
            $db->table('sys_constant')->insert(['constant_key' => 'CO_LEAD_WORKDAYS', 'category_code' => 'SALES', 'constant_group' => 'Customer Order',
                'constant_number' => 14, 'activate' => true, 'created_at' => now(), 'updated_at' => now(), 'created_by' => 'system',
                'description' => 'Customer Order: the due date of a product line is the latest ACC Digital date of the product plus this many working days (Sunday and Holiday Calendar days are not counted). Sales may overwrite it per line.']);
        }

        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'general', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('tm2sdi')->table('co_order_item', fn (Blueprint $t) => $t->dropColumn(['due_source', 'due_note']));
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'CO_LEAD_WORKDAYS')->delete();
    }
};
