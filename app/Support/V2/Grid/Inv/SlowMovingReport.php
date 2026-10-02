<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\StockOnhand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Slow Moving Item (v1: pages::inventory.print.slow-moving): item yang MASIH ada stoknya di lokasi yang bisa dipakai tetapi
// pergerakan terakhirnya 3 bulan atau lebih (ambang v1: 3 / 6 / 9 / 12 bulan = saringan di atas grid). Item tanpa tanggal
// pergerakan (saldo warisan) selalu ikut: justru itu yang paling perlu diperhatikan.
// Pergerakan = last_item_movement (Stock Transfer antar bin / gudang tidak dihitung sebagai gerak).
abstract class SlowMovingReport extends StockReportGrid
{
    private const MONTHS = 3;

    protected function report(): array
    {
        return ['slow', 'Slow Moving Item'];
    }

    public function icon(): string
    {
        return 'fa-solid fa-hourglass-half';
    }

    public function guide(): ?string
    {
        return 'Items that still have usable stock but have not moved for 3 months or more. '
            .'An item is counted once, by the latest movement in any location. Never moved = old balance without a movement date.';
    }

    protected function query(): Builder
    {
        $key = $this->itemKey();

        return StockOnhand::of($this->category())->where('is_usable', true)->groupBy($key)
            ->selectRaw("{$key} AS row_id, {$key}, SUM(qty_onhand) AS qty_onhand, SUM(value_onhand) AS value_onhand, MAX(last_item_movement) AS last_movement, "
                ."MAX(uom_code) AS uom_code, GROUP_CONCAT(DISTINCT warehouse_name ORDER BY warehouse_name SEPARATOR ', ') AS warehouses")
            ->havingRaw('SUM(qty_onhand) > 0 AND MAX(last_item_movement) < ?', [now()->subMonths(self::MONTHS)->toDateString()])
            ->orderBy('last_movement');
    }

    protected function prefetch(Collection $items): void
    {
        $this->loadItems($items->pluck($this->itemKey())->all());
    }

    public function columns(): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'warehouses', 'label' => 'Warehouse'],
            ['key' => 'onhand', 'label' => 'On Hand', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true],
            ['key' => 'last_movement', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle_days', 'label' => 'Idle (days)', 'type' => 'number'],
            ['key' => 'idle', 'label' => 'Idle', 'type' => 'badge', 'tones' => ['3-6 months' => 'info', '6-9 months' => 'warn', '9-12 months' => 'warn',
                'Over 12 months' => 'danger', 'Never moved' => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        $item = $this->item((int) $m->getAttribute($this->itemKey()));
        $moved = $this->movedAt($m->last_movement);
        $months = $moved ? (int) now()->startOfDay()->diffInMonths($moved, true) : null;

        return [
            'item' => $item['name'], 'item_code' => $item['code'], 'category' => $item['group'], 'warehouses' => $m->warehouses,
            'onhand' => (float) $m->qty_onhand, 'unit' => $m->uom_code, 'value' => (float) $m->value_onhand,
            'last_movement' => $moved, 'idle_days' => $this->idleDays($moved),
            'idle' => match (true) {
                $months === null => 'Never moved',
                $months >= 12 => 'Over 12 months',
                $months >= 9 => '9-12 months',
                $months >= 6 => '6-9 months',
                default => '3-6 months',
            },
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => '3-6 months', 'where' => ['idle' => '3-6 months'], 'tone' => 'info'],
            ['label' => '6-9 months', 'where' => ['idle' => '6-9 months'], 'tone' => 'warn'],
            ['label' => '9-12 months', 'where' => ['idle' => '9-12 months'], 'tone' => 'warn'],
            ['label' => 'Over 12 months', 'where' => ['idle' => 'Over 12 months'], 'tone' => 'danger'],
            ['label' => 'Never moved', 'where' => ['idle' => 'Never moved'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Category', 'idle' => 'Idle'];
    }

    public function groups(): array
    {
        return ['category' => 'Category', 'idle' => 'Idle'];
    }
}
