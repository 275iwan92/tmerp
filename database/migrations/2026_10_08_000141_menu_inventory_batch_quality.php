<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu Inventory > Batch & Quality (v2.01 jalur QC, URGENT, kurang kirim) dan jalan pintas Shortage Request di Procurement.
//   Inventory > Batch & Quality: Conditional Stock | First Article | Shortage Request | Setup (Sampling Plan, Sampling Rules, Risk Class) | Report (URGENT Usage, Shortage by Supplier, Supplier Claims)
//   Procurement > Receive: Shortage Request, dan Report: Shortage by Supplier (layar yang sama, baris menu kedua).
// Pola migration menu Inventory & Warehouse (2026_10_03_000010): hanya menambah baris yang belum ada, tidak mengubah yang sudah ada.
return new class extends Migration
{
    private const BY = 'claude (inventory v2.01 qc)';

    // kode => [label, ikon, slug v2, jenis objek, urutan]
    private const MAIN = [
        'INV_BQ_COND' => ['Conditional Stock', 'fa-solid fa-hourglass-half', 'conditional-stock', 'form', 20],
        'INV_BQ_FA' => ['First Article', 'fa-solid fa-clipboard-check', 'qc-first-article', 'form', 30],
        'INV_BQ_SHORT' => ['Shortage Request', 'fa-solid fa-boxes-packing', 'supplier-shortage', 'form', 40],
    ];

    private const SETUP = [
        'INV_BQ_PLAN' => ['Sampling Plan', 'fa-solid fa-vial', 'qc-sampling-plan', 'grid', 10],
        'INV_BQ_RULES' => ['Sampling Rules', 'fa-solid fa-scale-unbalanced-flip', 'qc-sampling-rules', 'grid', 20],
        'INV_BQ_RISK' => ['Risk Class', 'fa-solid fa-triangle-exclamation', 'qc-risk-class', 'grid', 30],
    ];

    private const REPORT = [
        'INV_BQ_URG' => ['URGENT Usage', 'fa-solid fa-bolt', 'qc-urgent-usage', 'report', 10],
        'INV_BQ_SHRT' => ['Shortage by Supplier', 'fa-solid fa-chart-column', 'shortage-by-supplier', 'report', 20],
        'INV_BQ_CLAIM' => ['Supplier Claims', 'fa-solid fa-file-circle-exclamation', 'supplier-claims', 'report', 30],
    ];

    private function row(array $v): array
    {
        return $v + ['activate' => 1, 'status_code' => 'READY_FOR_QC', 'status_updated_by' => self::BY, 'status_updated_at' => now(), 'created_at' => now(), 'target_kind' => 'app'];
    }

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $inv = $menu()->where('code', 'INV')->where('menu_type', 'module')->first();
        if (! $inv) {
            return;
        }
        $group = fn (int $parent, string $code, string $label, string $icon, int $sort) => $menu()->where('code', $code)->value('id')
            ?? $menu()->insertGetId($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'group', 'sort_order' => $sort]));
        $items = function (int $parent, array $list) use ($menu) {
            foreach ($list as $code => [$label, $icon, $slug, $type, $sort]) {
                if (! $menu()->where('code', $code)->exists()) {
                    $menu()->insert($this->row(['parent_id' => $parent, 'code' => $code, 'label' => $label, 'icon' => $icon, 'menu_type' => 'item', 'object_type' => $type, 'target' => 'v2:'.$slug, 'sort_order' => $sort]));
                }
            }
        };

        $bq = $group($inv->id, 'INV_BQ', 'Batch & Quality', 'fa-solid fa-vial-circle-check', 7);
        $items($bq, self::MAIN);
        $items($group($bq, 'INV_BQ_SETUP', 'Setup', 'fa-solid fa-sliders', 800), self::SETUP);
        $items($group($bq, 'INV_BQ_RPT', 'Report', 'fa-solid fa-chart-line', 900), self::REPORT);

        // Jalan pintas di Procurement (menu = data: layar yang sama boleh punya baris di beberapa modul).
        if ($receive = $menu()->where('code', 'PROC_GRP_RECEIVE')->value('id')) {
            $items($receive, ['PROC_SHORT' => ['Shortage Request', 'fa-solid fa-boxes-packing', 'supplier-shortage', 'form', 30]]);
        }
        if ($receiveRpt = $menu()->where('code', 'PROC_GRP_RECEIVE_RPT')->value('id')) {
            $items($receiveRpt, ['PROC_RPT_SHORT' => ['Shortage by Supplier', 'fa-solid fa-chart-column', 'shortage-by-supplier', 'report', 30]]);
        }
    }

    public function down(): void
    {
        // Menu dibiarkan (pola migration menu lain); nonaktifkan lewat Setup > Menu bila perlu.
    }
};
