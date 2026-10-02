<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvMovement;
use App\Services\Inventory\StockItemMeta;
use App\Services\Inventory\StockLedger;
use App\Support\V2\Grid\DbGrid;
use App\Support\V2\Src;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Induk laporan stok SEMUA kelas (menu Inventory > Warehouse > Report, user 2026-10-02: "stock card, summary all stocks,
// recapitulation of all items, inspection stocks, items by location / warehouse, loss and gain all items monthly").
// Satu laporan memuat RM / SFG / FG sekaligus dengan kolom Class; laporan per kelas tetap ada di menu kelasnya.
// Angka periode dihitung App\Services\Inventory\StockLedger, bukan di sini.
abstract class AllStockReport extends DbGrid
{
    public const CLASSES = ['RM' => 'Raw Material', 'SFG' => 'Semi Finished Goods', 'FG' => 'Finished Goods'];

    // "KELAS|item" => ['code', 'name', 'group', 'min']
    private array $meta = [];

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function group(): string
    {
        return 'Warehouse › Report';
    }

    public function search(): string
    {
        return 'Search item code, item name...';
    }

    public function printable(): bool
    {
        return true;
    }

    // Bawaan laporan berperiode: awal bulan berjalan sampai hari ini.
    protected function defaultPeriod(): ?array
    {
        return [now()->toImmutable()->startOfMonth(), now()->toImmutable()->startOfDay()];
    }

    protected function ledger(): StockLedger
    {
        [$from, $to] = $this->period();

        return StockLedger::between($from, $to);
    }

    // Tidak dipakai (rows() disusun turunan dari StockLedger / dokumen); hanya memenuhi kontrak induk.
    protected function query(): Builder
    {
        return InvMovement::query();
    }

    protected function row(Model $m): array
    {
        return [];
    }

    protected function sourceText(): string
    {
        return Src::of('tm2inv.v_inv_movement', 'tm2inv.rm_stockcard', 'tm2inv.sfg_stockcard', 'tm2inv.fg_stockcard', 'tm2mst.mat_raw', 'tm2mst.prod_part', 'tm2mst.prod_product');
    }

    // Lintas-DB (tm2inv -> tm2mst): satu query master per kelas untuk semua item laporan ini.
    /** @param iterable<array{class: string, item_id: int}> $items */
    protected function loadMeta(iterable $items): void
    {
        $ids = [];
        foreach ($items as $i) {
            $ids[$i['class']][] = $i['item_id'];
        }
        foreach ($ids as $class => $list) {
            foreach (StockItemMeta::load($class, $list) as $id => $meta) {
                $this->meta[StockLedger::itemKey($class, (int) $id)] = $meta;
            }
        }
    }

    protected function meta(string $class, int $itemId): array
    {
        return $this->meta[StockLedger::itemKey($class, $itemId)] ?? StockItemMeta::fallback($itemId);
    }
}
