<?php

namespace App\Services\Inventory;

use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;

// Identitas item stok dari master (lintas-DB tm2inv -> tm2mst: satu query per kelas, dicocokkan di PHP).
// RM = bahan (mat_raw), FG = product (prod_product), SFG = part (prod_part). Tanpa saringan asal data: stoknya ada, jadi
// namanya harus tampil (sama dengan WarehouseStock).
final class StockItemMeta
{
    public const NOT_FOUND = ['name' => '(item not found in master)', 'group' => 'No category', 'min' => null];

    /** @return array<int, array{code: ?string, name: string, group: string, min: mixed}> id item => identitas */
    public static function load(string $class, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return match ($class) {
            'RM' => MatRaw::withoutGlobalScopes()->whereKey($ids)->get(['material_id', 'material_code', 'material_name', 'category1', 'min_stock'])
                ->mapWithKeys(fn ($m) => [$m->material_id => ['code' => $m->material_code, 'name' => $m->material_name, 'group' => $m->category1 ?: 'No category', 'min' => $m->min_stock]])->all(),
            'FG' => ProdProduct::withoutGlobalScopes()->with('category')->whereKey($ids)->get()
                ->mapWithKeys(fn ($p) => [$p->id_product => ['code' => $p->product_code, 'name' => $p->product_name, 'group' => $p->category?->category_name ?: 'No category', 'min' => $p->min_stock]])->all(),
            default => ProdPart::withoutGlobalScopes()->whereKey($ids)->get(['id_part', 'part_code', 'part_name', 'min_stock'])
                ->mapWithKeys(fn ($p) => [$p->id_part => ['code' => $p->part_code, 'name' => $p->part_name, 'group' => 'Semi Finished Goods', 'min' => $p->min_stock]])->all(),
        };
    }

    public static function fallback(?int $id): array
    {
        return ['code' => $id ? '#'.$id : null] + self::NOT_FOUND;
    }
}
