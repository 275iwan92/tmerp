<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Setup > Quotes: layar kelola seluruh kutipan (sys_quotes), di sebelah Daily Quotes.
return new class extends Migration
{
    public function up(): void
    {
        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        $near = (clone $menu)->where('code', 'SET_DAILY_QUOTES')->first();
        if ($near && ! (clone $menu)->where('code', 'SET_QUOTES')->exists()) {
            $menu->insert(['parent_id' => $near->parent_id, 'code' => 'SET_QUOTES', 'label' => 'Quotes', 'icon' => 'fa-solid fa-quote-left', 'menu_type' => 'item',
                'object_type' => 'grid', 'target' => 'v2:setup-quotes', 'sort_order' => max(0, $near->sort_order - 1), 'activate' => 1,
                'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)',
                'status_updated_at' => now(), 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'SET_QUOTES')->delete();
    }
};
