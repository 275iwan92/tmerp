<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Urutan unit kerja mengikuti sheet formasi HR (tampilan Formasi Harian & cetaknya
// dibaca HR baris demi baris, permintaan user 2026-09-19). Unit lain digeser +1000
// supaya tetap di belakang dengan urutan relatif yang sama.
return new class extends Migration
{
    // code => sort_order lama
    private const HR_ORDER = [
        'WC-UV' => 410, 'WC-LILIN' => 230, 'WC-LAMINASI_MANUAL' => 210, 'WC-LAMINATING' => 220,
        'WC-LAM-01' => 40, 'WC-DIECUT' => 641, 'WC-PLONG-01' => 50, 'WC-PLONG_2' => 260,
        'WC-PLONG_3' => 270, 'WC-FG4' => 643, 'WC-FG3' => 642, 'WC-SHRINK' => 590,
        'WC-BLANKING' => 644, 'WC-KUPAS' => 580, 'WC-DELIVERY' => 610, 'WC-MANUAL' => 570, 'WC-QC-2' => 600,
    ];

    public function up(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('emp_work_center')->whereNotIn('work_center_code', array_keys(self::HR_ORDER))->increment('sort_order', 1000);
        foreach (array_keys(self::HR_ORDER) as $i => $code) {
            $db->table('emp_work_center')->where('work_center_code', $code)->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        $db = DB::connection('tm2mst');
        $db->table('emp_work_center')->whereNotIn('work_center_code', array_keys(self::HR_ORDER))->decrement('sort_order', 1000);
        foreach (self::HR_ORDER as $code => $old) {
            $db->table('emp_work_center')->where('work_center_code', $code)->update(['sort_order' => $old]);
        }
    }
};
