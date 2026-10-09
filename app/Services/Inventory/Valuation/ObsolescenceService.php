<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Inventory\InvObsoleteProvision;
use App\Models\Inventory\StockOnhand;
use App\Models\Sys\SysConstant;
use App\Services\Acc\JournalPostingService;
use App\Services\Inventory\StockItemMeta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Provisi stok usang (V-10): umur tanpa pergerakan per item x bin dibandingkan batas INV_AGING_SLOW / NONMOVING / DEAD_DAYS menjadi kelas
// Slow, Non-moving, Dead; usulan provisi = nilai stok x persen kelas (INV_OBSOLETE_PCT_SLOW / _NONMOVING / _DEAD). Pergerakan = pergerakan
// terakhir item selain Stock Transfer (sama dengan laporan Slow Moving); item yang tidak pernah bergerak masuk Dead.
// Jurnal HANYA lahir atas aksi user (post): saldo provisi yang dituju dibandingkan saldo provisi posting sebelumnya, yang dijurnal selisihnya.
//   naik : Dr Beban Penyisihan Persediaan Usang / Cr Penyisihan Persediaan Usang (kontra persediaan)
//   turun: Dr Penyisihan Persediaan Usang / Cr Beban Penyisihan Persediaan Usang
final class ObsolescenceService
{
    public const ACTIVE = 'ACTIVE';

    public const SLOW = 'SLOW';

    public const NON_MOVING = 'NON_MOVING';

    public const DEAD = 'DEAD';

    public const LABELS = [self::ACTIVE => 'Active', self::SLOW => 'Slow', self::NON_MOVING => 'Non-moving', self::DEAD => 'Dead'];

    public const PCT_CONSTANTS = [self::SLOW => 'INV_OBSOLETE_PCT_SLOW', self::NON_MOVING => 'INV_OBSOLETE_PCT_NONMOVING', self::DEAD => 'INV_OBSOLETE_PCT_DEAD'];

    public function __construct(private JournalPostingService $journals) {}

    public static function classify(?int $idleDays): string
    {
        $slow = (int) SysConstant::number('INV_AGING_SLOW_DAYS', 90);
        $nonMoving = (int) SysConstant::number('INV_AGING_NONMOVING_DAYS', 180);
        $dead = (int) SysConstant::number('INV_AGING_DEAD_DAYS', 365);

        return match (true) {
            $idleDays === null || $idleDays >= $dead => self::DEAD,
            $idleDays >= $nonMoving => self::NON_MOVING,
            $idleDays >= $slow => self::SLOW,
            default => self::ACTIVE,
        };
    }

    public static function pct(string $class): float
    {
        return isset(self::PCT_CONSTANTS[$class]) ? SysConstant::number(self::PCT_CONSTANTS[$class], 0.0) : 0.0;
    }

    /** @return list<array<string, mixed>> satu baris per item x bin yang masih bernilai */
    public function rows(?CarbonImmutable $asOf = null): array
    {
        $asOf = ($asOf ?? CarbonImmutable::today())->startOfDay();
        $pct = [];
        foreach (self::PCT_CONSTANTS as $class => $key) {
            $pct[$class] = SysConstant::number($key, 0.0);
        }
        $rows = [];
        foreach (StockOnhand::ITEM_KEYS as $stockClass => $itemKey) {
            $stock = StockOnhand::of($stockClass)->where('qty_onhand', '>', 0)->where('value_onhand', '>', 0)->get();
            $meta = StockItemMeta::load($stockClass, $stock->pluck($itemKey)->all());
            foreach ($stock as $s) {
                $id = (int) $s->getAttribute($itemKey);
                $last = $s->last_item_movement ? CarbonImmutable::parse($s->last_item_movement)->startOfDay() : null;
                $idle = $last ? max(0, (int) $last->diffInDays($asOf, false)) : null;
                $class = self::classify($idle);
                $value = round((float) $s->value_onhand, 2);
                $m = $meta[$id] ?? StockItemMeta::fallback($id);
                $rows[] = ['id' => $stockClass.'-'.$id.'-'.$s->location_id, 'stock_class' => $stockClass, 'item_id' => $id, 'code' => (string) $m['code'], 'name' => (string) $m['name'],
                    'location_id' => (int) $s->location_id, 'location' => trim($s->warehouse_name.' · '.$s->location_name, ' ·'), 'unit' => (string) $s->uom_code,
                    'qty' => (float) $s->qty_onhand, 'value' => $value, 'last_movement' => $last?->toDateString(), 'idle_days' => $idle, 'aging_class' => $class,
                    'pct' => $pct[$class] ?? 0.0, 'provision' => round($value * ($pct[$class] ?? 0.0) / 100, 2)];
            }
        }

        return $rows;
    }

    /** @return array<string, array{items: int, value: float, provision: float}> */
    public function summary(array $rows): array
    {
        $sum = [];
        foreach (array_keys(self::LABELS) as $class) {
            $sum[$class] = ['items' => 0, 'value' => 0.0, 'provision' => 0.0];
        }
        foreach ($rows as $r) {
            $sum[$r['aging_class']]['items']++;
            $sum[$r['aging_class']]['value'] = round($sum[$r['aging_class']]['value'] + $r['value'], 2);
            $sum[$r['aging_class']]['provision'] = round($sum[$r['aging_class']]['provision'] + $r['provision'], 2);
        }

        return $sum;
    }

    public static function previousTotal(): float
    {
        return (float) (InvObsoleteProvision::query()->orderByDesc('id_provision')->value('provision_total') ?? 0.0);
    }

    // Aksi user (tombol Post provision): foto baris + jurnal selisih terhadap provisi sebelumnya.
    public function post(string $login, ?CarbonImmutable $asOf = null): InvObsoleteProvision
    {
        $asOf = ($asOf ?? CarbonImmutable::today())->startOfDay();
        $rows = array_values(array_filter($this->rows($asOf), fn ($r) => $r['provision'] > 0));
        $target = round((float) array_sum(array_column($rows, 'provision')), 2);
        $previous = self::previousTotal();
        $adjustment = round($target - $previous, 2);
        if (abs($adjustment) < 0.005) {
            throw new ValuationRefused(t('The provision is already up to date; there is nothing to post.'));
        }
        $expense = ValuationAccounts::id('INV_ACC_OBS_EXPENSE');
        $provision = ValuationAccounts::id('INV_ACC_OBS_PROVISION');

        return DB::connection('tm2inv')->transaction(function () use ($rows, $target, $previous, $adjustment, $expense, $provision, $login, $asOf) {
            $head = InvObsoleteProvision::create(['provision_date' => $asOf->toDateString(), 'stock_value' => round((float) array_sum(array_column($rows, 'value')), 2),
                'provision_total' => $target, 'previous_total' => $previous, 'adjustment' => $adjustment, 'created_by' => $login, 'created_at' => now()]);
            foreach ($rows as $r) {
                $head->lines()->create(['stock_class' => $r['stock_class'], 'item_id' => $r['item_id'], 'item_code' => $r['code'], 'item_name' => mb_substr($r['name'], 0, 255),
                    'location_id' => $r['location_id'], 'location_name' => mb_substr($r['location'], 0, 100), 'qty' => $r['qty'], 'stock_value' => $r['value'],
                    'idle_days' => $r['idle_days'], 'aging_class' => $r['aging_class'], 'pct' => $r['pct'], 'provision' => $r['provision']]);
            }
            $amount = abs($adjustment);
            $up = $adjustment > 0;
            $voucher = $this->journals->post('GEN', 'OBS_PROVISION', (int) $head->id_provision, 'Obsolete stock provision '.$asOf->toDateString(), [
                ['account_id' => $up ? $expense : $provision, 'debit' => $amount, 'narration' => 'Provisi stok usang '.$asOf->toDateString()],
                ['account_id' => $up ? $provision : $expense, 'credit' => $amount, 'narration' => 'Provisi stok usang '.$asOf->toDateString()],
            ], $login, $asOf->toDateString(), ['reference_no' => 'OBS-'.$head->id_provision]);
            $head->update(['voucher_id' => $voucher->voucher_id]);

            return $head;
        });
    }
}
