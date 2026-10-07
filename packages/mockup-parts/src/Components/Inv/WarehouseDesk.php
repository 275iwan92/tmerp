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

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }
}
