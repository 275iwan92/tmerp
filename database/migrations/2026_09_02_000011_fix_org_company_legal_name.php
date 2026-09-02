<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Koreksi nama company terdaftar (keputusan user 2026-09-02): "CV. Langgeng
// Jaya Makmur" adalah nama CV yang SUNGGUH terdaftar -- "Telasih Makmur"
// cuma nickname company di mata customer (dipakai di portal karir, kop
// dokumen tetap pakai company_name org_company, TIDAK diubah jadi nickname).
// APP_NAME (sys_constant, identitas aplikasi) TIDAK terpengaruh -- itu
// konsep terpisah (ERP TM), lihat docs/configurationstandard.md.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2mst')->table('org_company')
            ->where('company_code', 'TM')
            ->update(['company_name' => 'CV. Langgeng Jaya Makmur']);
    }

    public function down(): void
    {
        DB::connection('tm2mst')->table('org_company')
            ->where('company_code', 'TM')
            ->update(['company_name' => 'CV Telasih Makmur Group']);
    }
};
