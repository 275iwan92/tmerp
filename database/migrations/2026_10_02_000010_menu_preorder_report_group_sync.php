<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu PREORDER di tm2sys disamakan dengan ddtsys (user 2026-10-02: "ubah di mockup juga ubah di tmerp.test"): grup Report
// (Quotation Terbuka, Follow-up Quotation, Win-Rate Sales) dan izin COSTING di baris Pro-Cost. Di ddtsys baris-baris ini sudah
// ada (dibuat lewat layar/seed v2), jadi di sana migrasi ini tidak mengubah apa pun.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const REPORTS = [
        'SALES_RPT_QUOOPEN' => ['Quotation Terbuka', 'fa-solid fa-hourglass-half', 'v2:quotation-outstanding', 10],
        'SALES_RPT_QUOFU' => ['Follow-up Quotation', 'fa-solid fa-phone', 'v2:quotation-followup', 30],
    ];

    public function up(): void
    {
        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        $module = (clone $menu)->where('code', 'PREORDER')->value('id');
        if (! $module) {
            return;
        }
        $group = (clone $menu)->where('code', 'SALES_INQGRP_RPT')->value('id') ?? (clone $menu)->insertGetId([
            'parent_id' => $module, 'code' => 'SALES_INQGRP_RPT', 'label' => 'Report', 'icon' => 'fa-solid fa-chart-line', 'menu_type' => 'group',
            'target_kind' => 'app', 'sort_order' => 900, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'created_at' => now(),
        ]);
        foreach (self::REPORTS as $code => [$label, $icon, $target, $sort]) {
            if (! (clone $menu)->where('code', $code)->exists()) {
                (clone $menu)->insert(['parent_id' => $group, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => 'report',
                    'target' => $target, 'target_kind' => 'app', 'sort_order' => $sort, 'activate' => 1, 'status_code' => 'READY_FOR_QC', 'created_at' => now()]);
            }
        }
        (clone $menu)->where('code', 'SALES_WINRATE')->update(['parent_id' => $group, 'sort_order' => 40]);
        (clone $menu)->where('code', 'SALES_COST')->whereNull('required_object')->update(['required_object' => 'COSTING']);
    }

    public function down(): void
    {
        $menu = DB::connection('tm2sys')->table('sys_app_menu');
        (clone $menu)->where('code', 'SALES_WINRATE')->update(['parent_id' => (clone $menu)->where('code', 'PREORDER')->value('id')]);
        (clone $menu)->whereIn('code', [...array_keys(self::REPORTS), 'SALES_INQGRP_RPT'])->delete();
    }
};
