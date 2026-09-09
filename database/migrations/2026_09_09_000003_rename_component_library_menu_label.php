<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rename label menu SET_UILIB (permintaan user 2026-09-09) -- label tab MDI
// shell (resources/js/app-shell.js openTab()) diambil dari sys_app_menu.label
// DB, BUKAN dari #[Title(...)] Livewire halamannya. User koreksi: "harusnya
// ubah data bukan ubah program karena semua menu di table" -- semua
// menu/label MDI tab memang 100% data-driven (docs/settings_menu_datadriven,
// migration 2026_09_07_000004 dst), jadi rename tab = UPDATE baris ini,
// bukan edit Blade/PHP.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SET_UILIB')
            ->update(['label' => 'DevLib UI UX']);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')
            ->where('code', 'SET_UILIB')
            ->update(['label' => 'Library Component for UI/UX']);
    }
};
