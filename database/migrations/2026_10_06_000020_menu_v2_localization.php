<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Setup > Localization pindah ke layar v2 UI Text Keys (user 2026-10-06: layar v1 dibersihkan; gelombang 1 rencana pengganti v1).
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_LOCALIZATION')->update(['target' => 'v2:ui-text-keys', 'target_kind' => 'app',
            'object_type' => 'grid', 'status_code' => 'READY_FOR_QC', 'status_note' => 'v2 UI Text Keys (sys_translations, App\Support\V2\Grid\Setup\UiTextKeyLookup)',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_LOCALIZATION')->update(['target' => null, 'status_code' => 'NOT_BUILT']);
    }
};
