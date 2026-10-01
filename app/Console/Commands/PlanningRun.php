<?php

namespace App\Console\Commands;

use App\Models\Inventory\RmBookingD;
use App\Services\Planning\MaterialPlanningEngine;
use App\Support\DbName;
use App\Support\V2\Data\MaterialPlanning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Material Planning tiap malam (routes/console.php): lepas booking CO yang batal, hitung ulang usulan booking semua CO
// CONFIRMED yang belum dikunci, segarkan popup kekurangan bahan. --demo = isi contoh (forecast + Pick Item), HANYA di ddt*.
class PlanningRun extends Command
{
    protected $signature = 'planning:run {--demo : Seed dummy forecast and item picks (ddt* only)}';

    protected $description = 'Material Planning: recalculate material booking proposals for confirmed Customer Orders';

    public function handle(): int
    {
        $engine = new MaterialPlanningEngine('system');
        $this->info('Recalculated bookings: '.$engine->runAll());
        if ($this->option('demo')) {
            if (! str_starts_with(DB::connection('tm2inv')->getDatabaseName(), 'ddt')) {
                $this->error('--demo only runs on the dummy database (ddt*).');

                return self::FAILURE;
            }
            $this->demo($engine);
        }
        MaterialPlanning::changed();

        return self::SUCCESS;
    }

    // Contoh untuk UAT: forecast 3 bulan dari CO dummy yang ada + Pick Item kertas supaya tampil status stok / kurang / menunggu PR.
    private function demo(MaterialPlanningEngine $engine): void
    {
        $sdi = DB::connection('tm2sdi');
        $now = now();
        $items = $sdi->table('co_order_item as i')->join('co_order as o', 'o.id_order', '=', 'i.order_id')->where('o.stat_cancel', 0)
            ->where('i.line_kind', 'PRODUCT')->whereNotNull('i.product_id')->orderBy('o.id_order')->get(['o.customer_id', 'i.product_id'])->unique('product_id')->take(3)->values();
        DB::connection('tm2prd')->table('prd_demand_forecast')->where('notes', 'Dummy forecast (UAT)')->delete();
        foreach ($items as $i => $it) {
            DB::connection('tm2prd')->table('prd_demand_forecast')->updateOrInsert(
                ['customer_id' => $it->customer_id, 'product_id' => $it->product_id, 'period_start' => $now->copy()->startOfMonth()->addMonths($i)->toDateString()],
                ['qty_forecast' => [8000, 6000, 4000][$i], 'notes' => 'Dummy forecast (UAT)', 'activate' => 1, 'created_by' => 'system', 'created_at' => $now, 'updated_at' => $now]);
        }

        // Kertas berstok (-> Covered / Short) untuk CO confirmed pertama, kertas yang sedang di-PR (-> Waiting / Late) untuk berikutnya.
        $stocked = DB::connection('tm2inv')->table('v_rm_available as a')->join(DbName::table('tm2mst.mat_raw').' as m', 'm.material_id', '=', 'a.material_id')
            ->where('m.category1', 'Paper')->where('a.qty_available', '>', 0)->orderByDesc('a.qty_available')->value('a.material_id');
        $onPr = DB::connection('tm2pur')->table('pr_line as l')->join('pr_requisition as r', 'r.pr_id', '=', 'l.pr_id')->where('r.stat_cancel', 0)
            ->whereNotIn('r.status', ['REJECTED', 'CANCELLED', 'CLOSED', 'FULLY_ORDERED'])->whereNotNull('l.item_id')->where('l.item_id', '!=', $stocked)
            ->orderByDesc('l.pr_line_id')->value('l.item_id');
        $cos = $sdi->table('co_order')->where('doc_status', 'CONFIRMED')->where('stat_cancel', 0)->orderBy('id_order')->pluck('id_order')->values();
        foreach ($cos as $n => $coId) {
            $material = $n === 1 ? ($onPr ?: $stocked) : $stocked;
            for ($i = 0; $material && $i < 20 && ($line = RmBookingD::where('co_id', $coId)->where('material_role', 'SUBSTRATE')
                ->where('line_status', 'NEED_ITEM')->whereNotNull('part_id')->first()); $i++) {
                $engine->pick($line, (int) $material);
            }
        }
        $this->info('Demo: forecast '.$items->count().' rows, picks on '.$cos->count().' confirmed orders.');
    }
}
