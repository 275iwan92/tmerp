<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// I18N Malam 1 follow-up (docs/BACKLOG.md §13) -- leftover shared_* string
// yang belum tertarik saat migration 2026_09_09_000001 dibuat: 4 komponen
// x-form.* baru (searchable-select/multi-select/file-dropzone/mini-calendar,
// dibangun sesi paralel 2026-09-09 pagi, belum sempat diaudit malam itu).
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // components/form/searchable-select.blade.php
            ['shared_searchable_select_placeholder', '— pilih atau ketik —', '— select or type —'],
            ['shared_searchable_select_search_placeholder', 'Ketik utk cari...', 'Type to search...'],
            ['shared_searchable_select_no_results', 'Tidak ada hasil.', 'No results.'],

            // components/form/multi-select.blade.php
            ['shared_multi_select_placeholder', 'Pilih...', 'Select...'],

            // components/form/file-dropzone.blade.php
            ['shared_file_dropzone_action', 'Pilih File', 'Choose File'],
            ['shared_file_dropzone_current', 'Saat ini:', 'Current:'],

            // components/form/mini-calendar.blade.php (hari singkat + empty state)
            ['shared_calendar_day_sun', 'Min', 'Sun'],
            ['shared_calendar_day_mon', 'Sen', 'Mon'],
            ['shared_calendar_day_tue', 'Sel', 'Tue'],
            ['shared_calendar_day_wed', 'Rab', 'Wed'],
            ['shared_calendar_day_thu', 'Kam', 'Thu'],
            ['shared_calendar_day_fri', 'Jum', 'Fri'],
            ['shared_calendar_day_sat', 'Sab', 'Sat'],
            ['shared_calendar_no_events', 'Tidak ada event tanggal ini.', 'No events on this date.'],
        ];

        $sys = DB::connection('tm2sys');

        foreach ($rows as [$key, $id, $en]) {
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'id', 'trans_key' => $key], ['trans_value' => $id]);
            $sys->table('sys_translations')->updateOrInsert(['lang_code' => 'en', 'trans_key' => $key], ['trans_value' => $en]);
        }
    }

    public function down(): void
    {
        $keys = [
            'shared_searchable_select_placeholder', 'shared_searchable_select_search_placeholder', 'shared_searchable_select_no_results',
            'shared_multi_select_placeholder',
            'shared_file_dropzone_action', 'shared_file_dropzone_current',
            'shared_calendar_day_sun', 'shared_calendar_day_mon', 'shared_calendar_day_tue', 'shared_calendar_day_wed',
            'shared_calendar_day_thu', 'shared_calendar_day_fri', 'shared_calendar_day_sat', 'shared_calendar_no_events',
        ];
        DB::connection('tm2sys')->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
