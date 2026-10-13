<?php

/*
 * REMARK
 * Tujuan       : Menyemai data dummy PPIC dari produk yang punya Master Card lengkap (dieline, flat, ukuran, artwork) dan BOP.
 * Sumber hulu  : prod_product, prod_part*, iqp_*, co_*.
 * Input        : Opsi artisan; hanya situs dummy ddt*.
 * Output       : CO, baris kirim, dan WO dummy.
 * Dipakai oleh : Dijalankan manual lewat php artisan.
 * Status       : Dummy; tidak membuat data tanpa Master Card.
 */

namespace App\Console\Commands;

use App\Models\Master\CustCustomer;
use App\Models\Master\ProdMasterCard;
use App\Models\Master\ProdProduct;
use App\Models\Prd\PrdWo;
use App\Models\Sdi\CoOrder;
use App\Models\Sdi\CoOrderDelivery;
use App\Models\Sdi\CoOrderItem;
use App\Models\Sys\SecUser;
use App\Services\Sales\CoOrderBuilder;
use App\Services\Scheduling\Plan\AutoPlanRunner;
use App\Services\Scheduling\Plan\GateService;
use App\Services\Scheduling\Plan\WoPlanningEnricher;
use App\Services\Scheduling\WoGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Dummy PPIC realistis untuk layar Production Scheduling: CO INSTANT Confirmed dengan qty ribuan untuk product yang punya
// Master Card lengkap (dieline + artwork + ukuran) dan data ProDev lengkap (BOP + routing + BOM), beberapa baris kirim per CO. Hanya di database dummy (ddt*), tidak pernah tm2*.
// Baris kirim yang belum dibuat WO-nya sengaja ditinggal agar kartu Create Work Order di layar Work Order berisi.
class PpicSeedDummy extends Command
{
    protected $signature = 'ppic:seed-dummy {--create=6 : jumlah baris kirim yang langsung dibuat WO + Draft} {--purge : hapus dummy PPIC lalu keluar}';

    protected $description = 'Seed (atau purge) dummy PPIC realistis: CO, baris kirim, Work Order, rencana Draft (hanya ddt*)';

    private const USER = 'dummy_ppic';

    public function handle(): int
    {
        if (! str_starts_with((string) config('database.connections.tm2prd.database'), 'ddt')) {
            $this->error('Hanya untuk database dummy ddt* (jalankan dengan --env=mockup).');

            return self::FAILURE;
        }

        return $this->option('purge') ? $this->purge() : $this->seed();
    }

    private function seed(): int
    {
        Auth::login(SecUser::where('login', 'johiwan')->firstOrFail());
        // Offset printing: no dummy order without a complete Master Card (dieline, artwork, size L x W x H and flat size) and a BOP.
        $complete = ProdMasterCard::where('status', 'PRODUCT')->whereNotNull('product_id')->whereNotNull('dieline_svg')
            ->whereNotNull('flat_w')->whereNotNull('flat_h')->whereNotNull('dim_l')->whereNotNull('dim_w')->whereNotNull('dim_h')
            ->whereHas('files', fn ($q) => $q->where('file_kind', 'ARTWORK'))->pluck('product_id')->all();
        $products = ProdProduct::whereIn('id_product', $complete)->where('activate', 1)
            ->whereIn('id_product', DB::connection('tm2mst')->table('prod_part as p')->join('prod_part_process as r', 'r.part_id', '=', 'p.id_part')->pluck('p.product_id')->unique()->all())->get();
        if ($products->isEmpty()) {
            $this->error('No product has a complete Master Card (dieline, artwork, size) and a BOP yet. Complete the card first; nothing was seeded.');

            return self::FAILURE;
        }
        $customers = CustCustomer::where('activate', 1)->orderBy('id_customer')->limit(8)->get();
        $today = CarbonImmutable::today();
        // Qty, hari kirim per baris: sengaja campur agar ada yang mepet, ada yang longgar.
        $plan = [[4000, [7]], [12000, [10, 21]], [2500, [5]], [20000, [14, 28]], [6000, [9]], [9000, [12, 19]], [3000, [6]], [15000, [16, 26]]];
        $made = collect();

        foreach ($plan as $i => [$qty, $days]) {
            $product = $products[$i % $products->count()];
            $order = CoOrderBuilder::createInstant($customers[$i % $customers->count()]);
            $item = CoOrderBuilder::addProductLine($order, $product, $qty);
            $item->forceFill(['due_date' => $today->addDays(max($days))->toDateString(), 'data_origin' => 'DUMMY', 'created_by' => self::USER])->save();
            $order->forceFill(['doc_status' => 'CONFIRMED', 'customer_po_no' => 'PO-PPIC-'.($i + 1), 'notes' => '[PPIC-DUMMY] seed ppic:seed-dummy', 'data_origin' => 'DUMMY', 'created_by' => self::USER])->save();
            $each = round($qty / count($days), 2);
            foreach ($days as $k => $d) {
                $made->push(CoOrderDelivery::create([
                    'order_id' => $order->id_order, 'item_id' => $item->id_item, 'shipment_type' => 'DELIVER', 'ship_date' => $today->addDays($d)->toDateString(),
                    'ship_qty' => $k === count($days) - 1 ? $qty - $each * (count($days) - 1) : $each, 'status' => 'PLANNED', 'data_origin' => 'DUMMY', 'created_by' => self::USER,
                ]));
            }
        }
        $this->info('CO dummy: '.count($plan).', baris kirim: '.$made->count());

        $n = 0;
        // One GateService for the whole run: it keeps the Material Planning result, which is slow to compute.
        $generate = app(WoGenerationService::class);
        $enricher = app(WoPlanningEnricher::class);
        $gates = app(GateService::class);
        foreach ($made->take((int) $this->option('create')) as $line) {
            foreach ($generate->generateFromDelivery($line->fresh(['order', 'item'])) as $wo) {
                $enricher->enrich($wo);
                $gates->apply($wo);
                $n++;
            }
            $line->forceFill(['posted_at' => now(), 'posted_by' => self::USER])->save();
        }
        if ($n) {
            $run = app(AutoPlanRunner::class)->run('EVENT', self::USER);
            $this->info("Work Order dibuat: $n. Draft plan {$run->run_id}: {$run->operations_planned} operasi, {$run->exceptions_found} perlu perhatian.");
        }

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $orders = CoOrder::where('created_by', self::USER)->pluck('id_order');
        $wos = PrdWo::whereIn('source_co_id', $orders)->pluck('wo_id');
        $prd = DB::connection('tm2prd');
        $ops = $prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->pluck('operation_id');
        foreach (['prd_wo_operation_machine' => $ops, 'prd_operation_time_log' => $ops] as $table => $ids) {
            $prd->table($table)->whereIn('operation_id', $ids)->delete();
        }
        $prd->table('prd_wo_operation_output')->whereIn('operation_id', $ops)->delete();
        $prd->table('prd_wo_operation')->whereIn('wo_id', $wos)->delete();
        $prd->table('prd_wo_material')->whereIn('wo_id', $wos)->delete();
        $prd->table('prd_wo')->whereIn('wo_id', $wos)->delete();
        CoOrderDelivery::whereIn('order_id', $orders)->delete();
        CoOrderItem::whereIn('order_id', $orders)->delete();
        CoOrder::whereIn('id_order', $orders)->delete();
        $this->info('Dummy PPIC dihapus: '.count($orders).' CO, '.count($wos).' Work Order.');

        return self::SUCCESS;
    }
}
