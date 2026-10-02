<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvPallet;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Models\Master\WhZone;
use App\Services\Inventory\PalletService;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Grid\DbGrid;
use App\Support\V2\Src;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Inventory > Warehouse > Stack Monitor (user 2026-10-03: "tumpukan tidak jelas, oleh sebab itu butuh di monitor"): satu
// baris per satuan berlabel (palet / pak sisa) di tiap bin dengan letaknya dalam tumpukan (1 = paling bawah), umur, tinggi
// tumpukan dibanding tinggi tumpuk area, dan tanda sisa / tertimbun. Stok di bin yang belum berlabel tampil sebagai
// "Stack not recorded" (letaknya dalam tumpukan belum diketahui = pekerjaan label). Sumber sama dengan panel bin di
// Warehouse Map (App\Services\Inventory\WarehouseStock::binSummaries), jadi angka di dua layar selalu sama.
class StackMonitorReport extends DbGrid
{
    public function slug(): string
    {
        return 'stack-monitor';
    }

    public function title(): string
    {
        return 'Stack Monitor';
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
        return 'Warehouse';
    }

    public function icon(): string
    {
        return 'fa-solid fa-layer-group';
    }

    public function search(): string
    {
        return 'Search bin, item, pallet...';
    }

    public function guide(): ?string
    {
        return 'One row per labelled pallet or pack, grouped by bin. Stack 1 is the bottom. Buried = the unit to use first has a pallet on top of it. '
            .'Stack not recorded = stock in the bin without a label.';
    }

    public function rowTone(): ?string
    {
        return 'tone';
    }

    public function groupBy(): ?string
    {
        return 'bin';
    }

    // Tidak dipakai (rows() dibaca dari WarehouseStock); hanya memenuhi kontrak induk.
    protected function query(): Builder
    {
        return InvPallet::query();
    }

    protected function sourceText(): string
    {
        return Src::of('tm2inv.inv_pallet (stack_no, unit_kind)', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2mst.wh_loc', 'tm2mst.wh_zone');
    }

    protected function row(Model $m): array
    {
        return [];
    }

    public function actionUrls(): array
    {
        return ['open' => route('v2.screen', 'label-pallet').'?unit={id}'];
    }

    public function rows(): array
    {
        $work = PalletService::worklist();
        // Lintas-DB (tm2inv -> tm2mst): bin, area, gedung dalam tiga query, dicocokkan di PHP.
        $bins = WhLoc::query()->whereKey($work->pluck('location_id')->unique()->all())->get()->keyBy('location_id');
        $zones = WhZone::query()->whereKey($bins->pluck('zone_id')->filter()->unique()->all())->get()->keyBy('id_zone');
        $buildings = WhWarehouse::query()->whereKey($bins->pluck('warehouse_id')->unique()->all())->get()->keyBy('id_warehouse');
        $sum = WarehouseStock::binSummaries($bins->values());
        $full = WarehouseStock::fullPct();
        $rows = [];
        foreach ($bins->sortBy('location_code') as $bin) {
            $s = $sum[$bin->location_id];
            $zone = $zones->get($bin->zone_id);
            $wh = $buildings->get($bin->warehouse_id);
            $where = ['bin' => $bin->location_code, 'building' => $wh ? $wh->warehouse_code.' · '.$wh->warehouse_name : '—', 'area' => $zone ? $zone->zone_code.' · '.$zone->zone_name : 'No area'];
            $height = $s['stack_height_m'] > 0 ? Locale::decimal($s['stack_height_m'], 2).($s['height_m'] ? ' / '.Locale::decimal($s['height_m'], 2) : '').' m' : null;
            $heightState = $s['stack_pct'] === null ? null : ($s['stack_pct'] > 100 ? 'Over height limit' : ($s['stack_pct'] >= $full ? 'Near height limit' : null));
            $dead = collect($s['items'])->where('dead', true)->pluck('key')->flip();
            foreach ($s['stack'] as $u) {
                $rows[] = $where + ['id' => (string) $u['id'], 'stack' => $u['no'].' / '.$u['of'], 'stack_no' => $u['no'], 'kind' => $u['kind'] === 'PACK' ? 'Pack' : 'Pallet', 'unit_no' => $u['pallet_no'],
                    'item' => $u['name'], 'item_code' => $u['code'], 'qty' => $u['qty'], 'unit' => $u['uom'], 'arrived' => $u['arrived'], 'age' => $u['age_days'], 'expiry' => $u['expiry'],
                    'height' => $height, 'height_state' => $heightState ?? ($s['over_stack'] ? 'Over stack limit' : 'OK'),
                    'remnant' => $u['old'] ? 'Old remnant' : ($u['remnant'] ? 'Remnant' : '—'),
                    'buried' => $u['buried'] ? 'Buried' : '—', 'above' => $u['buried'] ?: null, 'record' => 'Recorded',
                    'tone' => $u['old'] || $dead->has($u['key']) ? 'danger' : ($u['buried'] ? 'tangerine' : null)];
            }
            // Stok tanpa label di bin ini: letaknya dalam tumpukan belum diketahui.
            foreach ($work->where('location_id', $bin->location_id)->where('unlabelled', '>', 0.00005) as $p) {
                $rows[] = $where + ['id' => $p['key'], 'stack' => '—', 'stack_no' => null, 'kind' => 'not labelled', 'unit_no' => null, 'item' => $p['name'], 'item_code' => $p['code'],
                    'qty' => $p['unlabelled'], 'unit' => $p['uom'], 'arrived' => $p['arrived'], 'age' => $p['arrived'] ? (int) now()->startOfDay()->diffInDays($p['arrived'], true) : null,
                    'expiry' => null, 'height' => $height, 'height_state' => 'OK', 'remnant' => '—', 'buried' => '—', 'above' => null, 'record' => 'Stack not recorded', 'tone' => 'yellow'];
            }
        }

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'building', 'label' => 'Building', 'visible' => false],
            ['key' => 'area', 'label' => 'Storage Area', 'visible' => false],
            ['key' => 'stack', 'label' => 'Stack'],
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'badge', 'tones' => ['Pallet' => 'info', 'Pack' => 'violet', 'not labelled' => 'neutral']],
            ['key' => 'unit_no', 'label' => 'Pallet / Pack No.'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'arrived', 'label' => 'Arrived', 'type' => 'date'],
            ['key' => 'age', 'label' => 'Age (days)', 'type' => 'number'],
            ['key' => 'expiry', 'label' => 'Expiry Date', 'type' => 'date', 'visible' => false],
            ['key' => 'height', 'label' => 'Height in Bin'],
            ['key' => 'height_state', 'label' => 'Stack Height', 'type' => 'badge', 'tones' => ['OK' => 'success', 'Near height limit' => 'warn', 'Over height limit' => 'danger', 'Over stack limit' => 'danger']],
            ['key' => 'remnant', 'label' => 'Remnant', 'type' => 'badge', 'tones' => ['Remnant' => 'violet', 'Old remnant' => 'danger', '—' => 'neutral']],
            ['key' => 'buried', 'label' => 'Buried', 'type' => 'badge', 'tones' => ['Buried' => 'tangerine', '—' => 'neutral']],
            ['key' => 'above', 'label' => 'Units Above', 'type' => 'number', 'visible' => false],
            ['key' => 'record', 'label' => 'Record', 'type' => 'badge', 'tones' => ['Recorded' => 'success', 'Stack not recorded' => 'warn']],
            ['key' => 'open', 'label' => 'Label', 'type' => 'button', 'text' => 'Open', 'action' => 'open', 'sortable' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Buried', 'where' => ['buried' => 'Buried'], 'tone' => 'tangerine'],
            ['label' => 'Near height limit', 'where' => ['height_state' => 'Near height limit'], 'tone' => 'warn'],
            ['label' => 'Over height limit', 'where' => ['height_state' => 'Over height limit'], 'tone' => 'danger'],
            ['label' => 'Remnant', 'where' => ['remnant' => 'Remnant'], 'tone' => 'violet'],
            ['label' => 'Old remnant', 'where' => ['remnant' => 'Old remnant'], 'tone' => 'danger'],
            ['label' => 'Stack not recorded', 'where' => ['record' => 'Stack not recorded'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['building' => 'Building', 'area' => 'Storage Area', 'kind' => 'Kind', 'remnant' => 'Remnant', 'buried' => 'Buried', 'record' => 'Record'];
    }

    public function groups(): array
    {
        return ['bin' => 'Bin', 'area' => 'Storage Area', 'building' => 'Building', 'item' => 'Item', 'kind' => 'Kind'];
    }
}
