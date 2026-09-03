<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Keputusan desain user 2026-09-03 -- lihat docs/payroll_encryption_decision.md
// utk detail lengkap (scope kolom, algoritma, trade-off). Migration ini CUMA
// menaruh kunci (permintaan eksplisit user "taruh key di tm2sys constants
// table") + daftar dokumennya -- TIDAK membangun modul Payroll itu sendiri
// (tm2pyr belum ada sama sekali, ini murni prasyarat).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_constant_category')->insert([
            'code' => 'PAYROLL',
            'label' => 'Payroll & Enkripsi',
            'description' => 'Parameter modul Payroll (belum dibangun) -- termasuk kunci enkripsi at-rest',
            'sort_order' => 60,
            'activate' => 1,
        ]);

        DB::connection('tm2sys')->table('sys_constant')->insert([
            'constant_key' => 'VERBA_SANCTA',
            'category_code' => 'PAYROLL',
            'constant_group' => 'ENCRYPTION',
            'constant_number' => 0,
            'constant_text' => 'telasihmakmur',
            'description' => 'Passphrase sumber kunci enkripsi at-rest basic_salary/net_salary (tm2pyr, BELUM dibangun) -- JANGAN diubah/dihapus sembarangan setelah modul Payroll live (butuh migrasi dekrip-enkrip ulang). Detail lengkap: docs/payroll_encryption_decision.md',
            'activate' => 1,
        ]);

        DB::connection('tm2sys')->table('sys_doc_reference')->insert([
            'file_name' => 'payroll_encryption_decision.md',
            'title' => 'Payroll -- Keputusan Desain Enkripsi',
            'category' => 'SECURITY',
            'related_menu_code' => null,
            'description' => 'Scope kolom, lokasi kunci (VERBA_SANCTA), algoritma, dampak ke laporan -- dibaca sebelum mulai bangun modul Payroll',
            'sort_order' => 999,
            'activate' => 1,
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_doc_reference')->where('file_name', 'payroll_encryption_decision.md')->delete();
        DB::connection('tm2sys')->table('sys_constant')->where('constant_key', 'VERBA_SANCTA')->delete();
        DB::connection('tm2sys')->table('sys_constant_category')->where('code', 'PAYROLL')->delete();
    }
};
