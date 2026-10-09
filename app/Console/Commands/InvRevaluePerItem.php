<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\ItemAverageCost;
use App\Services\Inventory\StockCardChain;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Peralihan ke Average per item (D15), tanpa jurnal. --check hanya melapor (tidak menulis); --apply menulis titik peralihan
// per item (qty / nilai total semua bin, Avg awal = nilai / qty) di inv_item_avg_open. Idempoten: item yang sudah punya titik dilewati.
class InvRevaluePerItem extends Command
{
    protected $signature = 'inv:revalue-per-item {--check : Report only, write nothing} {--apply : Write the cutover point of every item} {--class= : Only RM, SFG or FG} {--item= : Only this item id}';

    protected $description = 'Cutover to Average per item: total value stays the same, only its spread over bins changes (switch INV_AVG_SCOPE to ITEM afterwards)';

    public function handle(): int
    {
        if ($this->option('check') === $this->option('apply')) {
            $this->error('Use exactly one of --check or --apply.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');
        $table = [];
        $totalNow = $totalAfter = 0.0;
        $written = 0;

        foreach (StockCardChain::CARDS as $class => [$card, $itemKey]) {
            if ($this->option('class') && strtoupper((string) $this->option('class')) !== $class) {
                continue;
            }
            $items = $card::query()->select($itemKey.' as item_id', $class === 'FG' ? 'parts_id as part_id' : DB::raw('0 as part_id'))->distinct()
                ->when($this->option('item'), fn ($q, $id) => $q->where($itemKey, (int) $id))->get();
            foreach ($items as $it) {
                $partId = $it->part_id ? (int) $it->part_id : null;
                $s = $this->state($class, (int) $it->item_id, $partId);
                $open = DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where(['stock_class' => $class, 'item_id' => $it->item_id, 'part_id' => (int) $partId])->exists();
                // Sudah beralih: bandingkan nilai sekarang dengan hasil rantai item (tanpa menulis). Belum: titik peralihan = nilai sekarang.
                $after = $open ? ItemAverageCost::recalculate($class, (int) $it->item_id, $partId, null, false)['value'] : $s['value'];
                $totalNow += $s['value'];
                $totalAfter += $after;
                $table[] = [$class, $it->item_id.($partId ? '/'.$partId : ''), round($s['qty'], 4), round($s['value'], 2), $s['qty'] > 0.00005 ? round($s['value'] / $s['qty'], 5) : '-',
                    round($after - $s['value'], 2), $open ? 'done' : ($apply ? 'cutover written' : 'to do')];
                if ($apply && ! $open && (abs($s['qty']) > 0.00005 || abs($s['value']) > 0.005)) {
                    DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->insert(['stock_class' => $class, 'item_id' => $it->item_id, 'part_id' => (int) $partId,
                        'cutover_date' => $s['last_date'], 'cutover_doc_id' => $s['last_id'], 'open_qty' => $s['qty'], 'open_value' => $s['value'],
                        'open_avg' => $s['qty'] > 0.00005 ? $s['value'] / $s['qty'] : null, 'created_at' => now(), 'created_by' => 'inv:revalue-per-item']);
                    $written++;
                }
            }
        }

        $this->table(['Class', 'Item', 'Qty', 'Value now', 'Average', 'Difference', 'Status'], $table);
        $this->line(sprintf('Total value now %s, after %s, difference %s.', number_format($totalNow, 2, '.', ''), number_format($totalAfter, 2, '.', ''), number_format($totalAfter - $totalNow, 2, '.', '')));
        if (abs($totalAfter - $totalNow) > 0.005) {
            $this->error('The total inventory value would change. Do not switch INV_AVG_SCOPE to ITEM.');

            return self::FAILURE;
        }
        $this->info($apply ? "{$written} cutover point(s) written." : 'Check passed: no difference in total value.');

        return self::SUCCESS;
    }

    // Qty milik Company (semua bin), nilai = jumlah value_eb baris terakhir tiap bin, dan baris terakhir item (titik peralihan).
    /** @return array{qty: float, value: float, last_date: string, last_id: int} */
    private function state(string $class, int $itemId, ?int $partId): array
    {
        $qty = $value = 0.0;
        $last = [];
        $lastDate = '1970-01-01 00:00:00';
        $lastId = 0;
        foreach (ItemAverageCost::rows($class, $itemId, $partId)->get() as $r) {
            if (($r->owner_type ?: 'COMPANY') === 'COMPANY') {
                $qty += (float) $r->qty_in - (float) $r->qty_out + (float) $r->qty_adj;
            }
            $last[(int) $r->location_id] = (float) $r->value_eb;
            $lastDate = Carbon::parse($r->doc_date)->format('Y-m-d H:i:s');
            $lastId = (int) $r->getKey();
        }

        return ['qty' => $qty, 'value' => array_sum($last), 'last_date' => $lastDate, 'last_id' => $lastId];
    }
}
