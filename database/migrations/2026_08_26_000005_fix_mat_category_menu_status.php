<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration 2026_08_26_000003 lupa isi status_code -- default NOT_BUILT
// (is_clickable=0, abu-abu) padahal layarnya (mat-category.blade.php) sudah
// live & teruji. Samakan dengan MD_RAW/MD_WH: READY_FOR_QC (siap dicek user,
// bukan LOCKED krn belum divalidasi tampilan browser).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_MATCAT')->update([
            'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude_code',
            'status_updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_MATCAT')->update([
            'status_code' => 'NOT_BUILT',
            'status_updated_by' => null,
            'status_updated_at' => null,
        ]);
    }
};
