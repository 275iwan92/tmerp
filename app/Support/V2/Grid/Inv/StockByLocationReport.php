<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\StockOnhand;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Data\Labels;
use App\Support\V2\Grid\DbGrid;
use App\Support\V2\Src;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Inventory > Warehouse > Report > Stock by Location: semua stok (RM / SFG / FG) per gedung > area > bin, satu baris per
// item per bin. Baris merah = DEAD STOCK (item tidak bergerak lebih dari ASSISTANT_DEADSTOCK_MONTHS bulan; transfer tidak
// dihitung sebagai gerak). Sumber sama dengan Warehouse Map (App\Services\Inventory\WarehouseStock).
// Tambahan 2026-10-02: volume perkiraan (m3), tanggal datang, palet berlabel, dan tanda kedaluwarsa (dari label palet).
class StockByLocationReport extends DbGrid
{
    public function slug(): string
    {
        return 'inv-rpt-location';
    }

    public function title(): string
    {
        return 'Stock by Location';
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
        return 'Warehouse › Report';
    }

    public function icon(): string
    {
        return 'fa-solid fa-map-location-dot';
    }

    public function search(): string
    {
        return 'Search item, bin, area...';
    }

    public function guide(): ?string
    {
        return 'Stock on hand now, one row per item and bin, in every building. Red rows are dead stock: the item has not moved for longer than '
            .'the dead stock limit (Settings, Constants), and moving it to another bin does not count as a movement.';
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    // Dibuka langsung terkelompok per gedung (user 2026-10-02: "report items group by location warehouse"); Group mengganti ke area / bin / item.
    public function groupBy(): ?string
    {
        return 'building';
    }

    // Tidak dipakai (rows() dibaca dari WarehouseStock); hanya memenuhi kontrak induk.
    protected function query(): Builder
    {
        return StockOnhand::of('RM');
    }

    protected function sourceText(): string
    {
        return Src::of('tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc', 'tm2mst.wh_zone', 'tm2mst.wh_warehouse');
    }

    protected function row(Model $m): array
    {
        return [];
    }

    public function rows(): array
    {
        $positions = PalletService::worklist()->filter(fn (array $p) => $p['qty'] > 0);
        // Lintas-DB (tm2inv -> tm2mst): bin, area, gedung dalam tiga query, dicocokkan di PHP.
        $bins = WhLoc::query()->whereKey($positions->pluck('location_id')->unique()->all())->get()->keyBy('location_id');
        $zones = WhZone::query()->whereKey($bins->pluck('zone_id')->filter()->unique()->all())->get()->keyBy('id_zone');
        $buildings = WhWarehouse::query()->whereKey($bins->pluck('warehouse_id')->unique()->all())->get()->keyBy('id_warehouse');

        return $positions->sortBy(fn (array $p) => $p['location_code'].' '.$p['code'])->map(function (array $p) use ($bins, $zones, $buildings) {
            $bin = $bins->get($p['location_id']);
            $zone = $bin ? $zones->get($bin->zone_id) : null;
            $wh = $bin ? $buildings->get($bin->warehouse_id) : null;
            $expiry = Labels::worstExpiry($p['pallets']);

            return ['id' => $p['key'], 'item' => $p['name'], 'item_code' => $p['code'], 'class' => $p['class'], 'building' => $wh ? $wh->warehouse_code.' · '.$wh->warehouse_name : '—',
                'area' => $zone ? $zone->zone_code.' · '.$zone->zone_name : 'No area', 'floor' => $zone?->floor_no, 'bin' => $p['location_code'],
                'onhand' => $p['qty'], 'unit' => $p['uom'], 'weight' => $p['weight_kg'], 'volume' => $p['volume_m3'], 'value' => $p['value'], 'arrived' => $p['arrived'],
                'pallets' => $p['pallets']->count(), 'expiry' => $p['pallets']->pluck('expiry_date')->filter()->min()?->toDateString(),
                'last_movement' => $p['last_movement'], 'idle_days' => $p['idle_days'],
                'stock' => $p['dead'] ? 'Dead stock' : ($expiry === 'EXPIRED' ? 'Expired' : ($expiry === 'SOON' ? 'Expires soon' : (! $bin?->is_usable ? 'Blocked bin' : 'OK'))),
                'tone' => $p['dead'] || $expiry === 'EXPIRED' ? 'danger' : ($expiry === 'SOON' ? 'yellow' : null)];
        })->values()->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'class', 'label' => 'Class'],
            ['key' => 'building', 'label' => 'Building'],
            ['key' => 'area', 'label' => 'Storage Area'],
            ['key' => 'floor', 'label' => 'Floor', 'type' => 'number', 'visible' => false],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'onhand', 'label' => 'On Hand', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'weight', 'label' => 'Weight (kg)', 'type' => 'number', 'sum' => true],
            ['key' => 'volume', 'label' => 'Volume (m³)', 'type' => 'number', 'sum' => true],
            ['key' => 'value', 'label' => 'Stock Value', 'type' => 'money', 'sum' => true],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'pallets', 'label' => 'Pallets', 'type' => 'number', 'visible' => false],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date'],
            ['key' => 'last_movement', 'label' => 'Last Movement', 'type' => 'date'],
            ['key' => 'idle_days', 'label' => 'Idle (days)', 'type' => 'number'],
            ['key' => 'stock', 'label' => 'Stock', 'type' => 'badge', 'tones' => ['OK' => 'success', 'Dead stock' => 'danger', 'Expired' => 'danger', 'Expires soon' => 'warn', 'Blocked bin' => 'warn']],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Dead stock', 'where' => ['stock' => 'Dead stock'], 'tone' => 'danger'],
            ['label' => 'Expired', 'where' => ['stock' => 'Expired'], 'tone' => 'danger'],
            ['label' => 'Expires soon', 'where' => ['stock' => 'Expires soon'], 'tone' => 'warn'],
            ['label' => 'Blocked bin', 'where' => ['stock' => 'Blocked bin'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['class' => 'Class', 'building' => 'Building', 'area' => 'Storage Area', 'stock' => 'Stock'];
    }

    public function groups(): array
    {
        return ['building' => 'Building', 'area' => 'Storage Area', 'bin' => 'Bin', 'item' => 'Item'];
    }
}
