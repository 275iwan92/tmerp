<?php

namespace MockupParts\Components\Inv;

use Devlib\Components\WarehouseDesk as BaseDesk;
use MockupParts\Src;

// Form khusus Warehouse Map di aplikasi: seluruh isi = objek DevLib x-dl::warehouse-desk (user 2026-10-07). Turunan ini hanya
// mengganti penerjemah tooltip sumber data dengan Src (Data Dictionary aplikasi) dan mempertahankan tag x-mp::inv.warehouse-desk.
class WarehouseDesk extends BaseDesk
{
    public function tag(): string
    {
        return 'x-mp::inv.warehouse-desk';
    }

    // Peran sumber data pada tooltip (dibaca kelas dasar lewat srcFor()); nilainya tabel tm2mst/tm2inv yang diterjemahkan Src.
    protected function sources(): array
    {
        return [
            'building' => ['tm2mst.wh_warehouse'],
            'map' => ['tm2mst.wh_warehouse', 'tm2mst.wh_zone', 'tm2mst.wh_loc', 'tm2inv.v_rm_onhand', 'tm2inv.v_sfg_onhand', 'tm2inv.v_fg_onhand', 'tm2inv.inv_pallet (stack_no, unit_kind)'],
            'capacity' => ['tm2mst.wh_warehouse (length, width, height per floor)', 'tm2mst.wh_zone (size, stack height)', 'tm2mst.wh_loc.capacity_kg'],
            'zone' => ['tm2mst.wh_zone'],
        ];
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }
}
