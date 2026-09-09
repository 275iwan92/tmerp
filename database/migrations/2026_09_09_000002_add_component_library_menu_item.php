<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Layar "Library Component for UI/UX" (permintaan user 2026-09-09) --
// showcase resources/views/components/form/*.blade.php baru (password,
// switch, radio-group, color-picker, range-slider, number-stepper,
// otp-input, star-rating, masked-input, searchable-select, multi-select,
// phone-input, currency-input, file-dropzone, split-button, mini-calendar).
// Ditaruh di grup SET_GRP_SETTINGS (mirror pola migration
// 2026_09_07_000004_group_setup_menu_settings_security), sort_order 50
// (setelah Constants=40).
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        $settingsGroupId = $sys->table('sys_app_menu')->where('code', 'SET_GRP_SETTINGS')->value('id');
        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $sys->table('sys_app_menu')->updateOrInsert(
            ['code' => 'SET_UILIB'],
            [
                'id' => $nextId,
                'parent_id' => $settingsGroupId,
                'label' => 'Library Component for UI/UX',
                'icon' => 'fa-solid fa-shapes',
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => 50,
                'activate' => 1,
                'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'doc_slug' => null,
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_UILIB')->delete();
    }
};
