<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keputusan user 2026-09-23: selama aplikasi BELUM dinyatakan selesai, field wajib
// biodata pegawai (NIK/KK/tgl lahir/HP/alamat/email/data keluarga) tidak boleh
// menghalangi simpan. Saklar di konstanta supaya bisa dinyalakan sendiri lewat
// Settings > Constants saat go-live (CLAUDE.md §7.12), bukan dengan ubah kode.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        if (! $sys->table('sys_constant_category')->where('code', 'HRM')->exists()) {
            $cols = DB::connection('tm2sys')->getSchemaBuilder()->getColumnListing('sys_constant_category');
            $row = ['code' => 'HRM'];
            foreach (['label' => 'HRM', 'name' => 'HRM', 'description' => 'Human Resource', 'sort_order' => 60, 'activate' => 1] as $k => $v) {
                if (in_array($k, $cols, true)) {
                    $row[$k] = $v;
                }
            }
            $sys->table('sys_constant_category')->insert($row);
        }

        $sys->table('sys_constant')->updateOrInsert(
            ['constant_key' => 'HRM_EMPLOYEE_MANDATORY_STRICT'],
            ['category_code' => 'HRM', 'constant_group' => 'MASTER_EMPLOYEE', 'constant_number' => 0,
                'description' => '1 = biodata wajib (NIK, KK, tgl lahir, HP, alamat, email, keluarga) ditegakkan saat simpan Master Employee; 0 = longgar selama aplikasi belum go-live',
                'activate' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'HRM_EMPLOYEE_MANDATORY_STRICT')->delete();
    }
};
