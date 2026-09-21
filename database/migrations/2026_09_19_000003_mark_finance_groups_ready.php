<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Dua grup lama (FIN "Transaction Flows" & ACC "Accounting") masih NOT_BUILT
// padahal seluruh itemnya sudah READY_FOR_QC setelah Accounting Process Engine
// dibangun. Status LOCKED tetap hanya boleh diset user (globalrules.yaml).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['FIN', 'ACC'])->update(['status_code' => 'READY_FOR_QC']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->whereIn('code', ['FIN', 'ACC'])->update(['status_code' => 'NOT_BUILT']);
    }
};
