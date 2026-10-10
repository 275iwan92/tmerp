<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-10-10: modul Warehouse masuk ke Inventory (Inventory = Stock + Warehouse); modul Production diganti nama Manufacture dan
// dibagi PPIC, Production, Maintenance. Hanya memindahkan baris (parent_id / menu_type / label); kode item dan target layar TIDAK
// berubah. WH dan MAINT menjadi grup di bawah modul (kode & required_object tetap, jadi hak akses sama). Idempoten.
// Jalankan hanya dengan --env=mockup sampai rilis ke tm2*.
return new class extends Migration
{
    private const BY = 'claude (menu regroup 2026-10-10)';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $id = fn (string $code) => $menu()->where('code', $code)->value('id');
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $id($code)
            ?? $menu()->insertGetId(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort,
                'activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(),
                'target_kind' => 'app', 'doc_slug' => 'warehouselayout']);

        $inv = $id('INV');
        $prod = $id('PROD');
        if (! $inv || ! $prod) {
            return;
        }

        // INVENTORY = Stock + Warehouse
        if ($wh = $id('WH')) {
            $menu()->where('id', $wh)->update(['parent_id' => $inv, 'menu_type' => 'group', 'label' => 'Warehouse', 'sort_order' => 20, 'short_label' => null, 'tooltip' => null]);
        }
        $stock = $group($inv, 'INV_STOCK', 'Stock', 'fa-solid fa-boxes-stacked', 10);
        $menu()->where('parent_id', $inv)->whereNotIn('id', array_filter([$stock, $wh ?? null]))->update(['parent_id' => $stock]);

        // MANUFACTURE = PPIC + Production + Maintenance
        $menu()->where('id', $prod)->update(['label' => 'Manufacture', 'tooltip' => 'Manufacture (PPIC, production, maintenance)']);
        $ppic = $group($prod, 'PROD_PPIC', 'PPIC', 'fa-solid fa-chart-gantt', 10);
        $production = $group($prod, 'PROD_PRODUCTION', 'Production', 'fa-solid fa-industry', 20);
        // Shift Schedule (crew roster) is planning work, so it sits with PPIC; Production = Process Entry, QC, Reports.
        $menu()->whereIn('code', ['PROD_MASTER', 'PROD_PLAN', 'PROD_PPIC_SET', 'PROD_SUPPORT'])->update(['parent_id' => $ppic]);
        $menu()->whereIn('code', ['PROD_OUT', 'PROD_QC', 'PROD_GRP_RPT'])->update(['parent_id' => $production]);
        $menu()->where('code', 'PROD_OUT')->update(['label' => 'Process Entry', 'sort_order' => 10]);
        $menu()->where('code', 'PROD_QC')->update(['label' => 'QC', 'sort_order' => 20]);
        $menu()->where('code', 'PROD_GRP_RPT')->update(['label' => 'Reports', 'sort_order' => 30]);
        // QA = fourth group (user 2026-10-10); the complaint review is QA's answer to a complaint, so it moves here. QC stays under Production.
        $qa = $group($prod, 'PROD_QA', 'QA', 'fa-solid fa-clipboard-check', 40);
        $menu()->where('code', 'PROD_QC_COMPLAINT')->update(['parent_id' => $qa, 'sort_order' => 10]);
        if ($maint = $id('MAINT')) {
            $menu()->where('id', $maint)->update(['parent_id' => $prod, 'menu_type' => 'group', 'label' => 'Maintenance', 'sort_order' => 30, 'short_label' => null, 'tooltip' => null]);
        }
    }

    public function down(): void
    {
        // Pemindahan menu tidak dibalik.
    }
};
