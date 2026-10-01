<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\StockOnhand;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Support\V2\Grid\DbGrid;
use App\Support\V2\Src;

// Induk laporan stok (menu Raw Material / Finished Goods / Semi Finished Goods > Report). Satu turunan = satu kelas stok
// (RM / FG / SFG) x satu laporan; sumbernya view stok yang sama dengan laporan v1 (pages::inventory.print.*).
abstract class StockReportGrid extends DbGrid
{
    protected const CATEGORY_LABELS = ['RM' => 'Raw Material', 'FG' => 'Finished Goods', 'SFG' => 'Semi Finished Goods'];

    private const MASTERS = ['RM' => MatRaw::class, 'FG' => ProdProduct::class, 'SFG' => ProdPart::class];

    // id item => ['code', 'name', 'group', 'min' (stok minimum, null = tidak diatur)]
    protected array $items = [];

    abstract protected function category(): string;

    // Potongan slug + judul laporan, mis. ['balance', 'Stock Balance'].
    abstract protected function report(): array;

    public function slug(): string
    {
        return 'inv-'.strtolower($this->category()).'-rpt-'.$this->report()[0];
    }

    public function title(): string
    {
        return $this->report()[1].' '.$this->category();
    }

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
        return self::CATEGORY_LABELS[$this->category()].' › Report';
    }

    public function search(): string
    {
        return 'Search item code, item name...';
    }

    protected function itemKey(): string
    {
        return StockOnhand::ITEM_KEYS[$this->category()];
    }

    // Lintas-DB (tm2inv -> tm2mst): satu query master untuk semua item halaman ini.
    protected function loadItems(array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids)));
        $this->items = match ($this->category()) {
            'RM' => MatRaw::query()->whereKey($ids)->get(['material_id', 'material_code', 'material_name', 'category1', 'min_stock'])
                ->mapWithKeys(fn ($m) => [$m->material_id => ['code' => $m->material_code, 'name' => $m->material_name, 'group' => $m->category1 ?: 'No category', 'min' => $m->min_stock]])->all(),
            'FG' => ProdProduct::query()->with('category')->whereKey($ids)->get()
                ->mapWithKeys(fn ($p) => [$p->id_product => ['code' => $p->product_code, 'name' => $p->product_name, 'group' => $p->category?->category_name ?: 'No category', 'min' => $p->min_stock]])->all(),
            default => ProdPart::query()->whereKey($ids)->get(['id_part', 'part_code', 'part_name', 'min_stock'])
                ->mapWithKeys(fn ($p) => [$p->id_part => ['code' => $p->part_code, 'name' => $p->part_name, 'group' => 'Semi Finished Goods', 'min' => $p->min_stock]])->all(),
        };
    }

    protected function item(?int $id): array
    {
        return $this->items[$id] ?? ['code' => $id ? '#'.$id : null, 'name' => '(item not found in master)', 'group' => 'No category', 'min' => null];
    }

    // View lama bisa memuat tanggal kosong (0000-00-00) untuk saldo warisan: dianggap belum pernah bergerak.
    protected function movedAt(mixed $value): ?string
    {
        $date = substr((string) $value, 0, 10);

        return $date === '' || str_starts_with($date, '0000') ? null : $date;
    }

    protected function idleDays(?string $date): ?int
    {
        return $date ? (int) now()->startOfDay()->diffInDays($date, true) : null;
    }

    protected function sourceTables(): array
    {
        return [StockOnhand::VIEWS[$this->category()]];
    }

    protected function sourceText(): string
    {
        $master = new (self::MASTERS[$this->category()]);

        return Src::of(...array_map(fn (string $t) => 'tm2inv.'.$t, $this->sourceTables()), ...[$master->getConnectionName().'.'.$master->getTable()]);
    }
}
