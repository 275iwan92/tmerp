<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout DbGrid (backlog01) + Stock In v2 (backlog04): menu Inventory RM/FG/SFG x Stock In/Out/Return/Adjustment
// -> grid DB v2 (App\Support\V2\Grid\Inv\*). Form tetap v1 lewat ?open={id} / ?new=1. down() = target lama (null -> MenuRoutes).
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = [
        'INV_RM_ITEM1' => 'inv-rm-stock-in', 'INV_RM_ITEM2' => 'inv-rm-stock-out', 'INV_RM_ITEM3' => 'inv-rm-stock-return', 'INV_RM_ITEM4' => 'inv-rm-stock-adj',
        'INV_FG_ITEM1' => 'inv-fg-stock-in', 'INV_FG_ITEM2' => 'inv-fg-stock-out', 'INV_FG_ITEM3' => 'inv-fg-stock-return', 'INV_FG_ITEM4' => 'inv-fg-stock-adj',
        'INV_SFG_ITEM1' => 'inv-sfg-stock-in', 'INV_SFG_ITEM2' => 'inv-sfg-stock-out', 'INV_SFG_ITEM3' => 'inv-sfg-stock-return', 'INV_SFG_ITEM4' => 'inv-sfg-stock-adj',
    ];

    public function up(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->whereNull('target')->update([
                'target' => 'v2:'.$slug, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $code => $slug) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->where('target', 'v2:'.$slug)
                ->update(['target' => null, 'status_code' => 'NOT_BUILT']);
        }
    }
};
