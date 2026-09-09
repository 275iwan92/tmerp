<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// I18N Malam 1/7 (docs/BACKLOG.md §13) -- string komponen SHARED (grid
// toolbar/pagination/column-modal/sort-modal, date-picker, select placeholder,
// spreadsheet-table) yang dipakai puluhan layar sekaligus. Key prefix "shared_".
return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            // tombol generik dipakai lintas modal
            ['shared_btn_cancel', 'Batal', 'Cancel'],
            ['shared_btn_apply', 'Terapkan', 'Apply'],

            // components/grid/pagination-footer.blade.php (12 layar)
            ['shared_pagination_goto', 'Go to', 'Go to'],
            ['shared_pagination_view', 'View', 'View'],
            ['shared_pagination_first', 'Halaman pertama', 'First page'],
            ['shared_pagination_prev', 'Sebelumnya', 'Previous'],
            ['shared_pagination_next', 'Berikutnya', 'Next'],
            ['shared_pagination_last', 'Halaman terakhir', 'Last page'],
            ['shared_pagination_of', 'dari', 'of'],

            // components/grid/column-modal.blade.php (pilot Kolom/Sort/Export)
            ['shared_colmodal_title', 'Pilih Kolom Tampil', 'Choose Visible Columns'],
            ['shared_colmodal_available', 'Tersedia', 'Available'],
            ['shared_colmodal_search_placeholder', 'Cari kolom…', 'Search columns…'],
            ['shared_colmodal_visible', 'Tampil', 'Visible'],
            ['shared_colmodal_click_remove', 'klik utk hapus →', 'click to remove →'],
            ['shared_colmodal_groupby_label', 'Group By (dari kolom tampil)', 'Group By (from visible columns)'],
            ['shared_colmodal_groupby_none', 'Tidak ada', 'None'],
            ['shared_colmodal_reset', 'Reset ke default', 'Reset to default'],
            ['shared_colmodal_show_all_title', 'Tampilkan semua', 'Show all'],
            ['shared_colmodal_clear_all_title', 'Kosongkan', 'Clear all'],

            // components/grid/sort-modal.blade.php
            ['shared_sortmodal_title', 'Urutkan Data', 'Sort Data'],
            ['shared_sortmodal_column_label', 'Kolom (dari yang tampil)', 'Column (from visible ones)'],
            ['shared_sortmodal_unsorted', 'Tidak diurutkan', 'Not sorted'],

            // components/grid/toolbar-buttons.blade.php
            ['shared_grid_columns_btn', 'Kolom', 'Columns'],
            ['shared_grid_columns_btn_title', 'Pilih kolom tampil', 'Choose visible columns'],
            ['shared_grid_sort_btn_title', 'Urutkan data', 'Sort data'],

            // components/form/date-picker.blade.php (38 layar)
            ['shared_datepicker_placeholder_date', 'Pilih tanggal...', 'Pick a date...'],
            ['shared_datepicker_placeholder_datetime', 'Pilih tanggal & jam...', 'Pick date & time...'],
            ['shared_datepicker_header_datetime', 'Pilih Tanggal & Jam', 'Pick Date & Time'],
            ['shared_datepicker_header_date', 'Pilih Tanggal', 'Pick Date'],
            ['shared_datepicker_hour_label', 'Jam', 'Time'],
            ['shared_datepicker_clear', 'Clear', 'Clear'],
            ['shared_datepicker_today', 'Hari Ini', 'Today'],

            // components/form/select.blade.php (42 layar, default placeholder)
            ['shared_select_placeholder', '— pilih —', '— select —'],

            // components/grid/spreadsheet-table.blade.php (6 layar)
            ['shared_spreadsheet_mode_label', 'Mode Spreadsheet', 'Spreadsheet Mode'],
            ['shared_spreadsheet_hint_body', 'klik sel untuk edit, drag titik biru di pojok sel aktif untuk salin nilai ke bawah. Perubahan baru tersimpan setelah <b>Simpan Semua</b>.', 'click a cell to edit, drag the blue dot at the corner of the active cell to copy the value downward. Changes are only saved after <b>Save All</b>.'],
            ['shared_spreadsheet_add_row', '+ Baris', '+ Row'],
            ['shared_spreadsheet_save_all', 'Simpan Semua', 'Save All'],
            ['shared_spreadsheet_cancel_new_row_title', 'Batalkan baris baru', 'Cancel new row'],
            ['shared_spreadsheet_empty', 'Belum ada data. Klik "+ Baris" untuk mulai.', 'No data yet. Click "+ Row" to start.'],
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
            'shared_btn_cancel', 'shared_btn_apply',
            'shared_pagination_goto', 'shared_pagination_view', 'shared_pagination_first',
            'shared_pagination_prev', 'shared_pagination_next', 'shared_pagination_last', 'shared_pagination_of',
            'shared_colmodal_title', 'shared_colmodal_available', 'shared_colmodal_search_placeholder',
            'shared_colmodal_visible', 'shared_colmodal_click_remove', 'shared_colmodal_groupby_label',
            'shared_colmodal_groupby_none', 'shared_colmodal_reset', 'shared_colmodal_show_all_title', 'shared_colmodal_clear_all_title',
            'shared_sortmodal_title', 'shared_sortmodal_column_label', 'shared_sortmodal_unsorted',
            'shared_grid_columns_btn', 'shared_grid_columns_btn_title', 'shared_grid_sort_btn_title',
            'shared_datepicker_placeholder_date', 'shared_datepicker_placeholder_datetime',
            'shared_datepicker_header_datetime', 'shared_datepicker_header_date', 'shared_datepicker_hour_label',
            'shared_datepicker_clear', 'shared_datepicker_today',
            'shared_select_placeholder',
            'shared_spreadsheet_mode_label', 'shared_spreadsheet_hint_body', 'shared_spreadsheet_add_row',
            'shared_spreadsheet_save_all', 'shared_spreadsheet_cancel_new_row_title', 'shared_spreadsheet_empty',
        ];
        DB::connection('tm2sys')->table('sys_translations')->whereIn('trans_key', $keys)->delete();
    }
};
