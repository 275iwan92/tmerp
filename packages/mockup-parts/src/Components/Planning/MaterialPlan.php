<?php

namespace MockupParts\Components\Planning;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Form khusus Material Planning (MRP baru, docs/materialbooking_tier1.md). Dua mode:
//   list   = tombol Recalculate All + pintasan ke layar terkait, peringatan bahan tanpa item, GRID bahan (slot), lalu kartu
//            Create Purchase Requisition (centang bahan yang kurang; satu PR per supplier usulan).
//   detail = kepala satu bahan (kode, status penutup, butuh / kurang / pertama dibutuhkan / pesan paling lambat) + grid proyeksi (slot).
// Layar hanya menyerahkan $plan (App\Support\V2\Data\MaterialPlanning::planDesk()) dan grid-nya lewat slot. Hitungan = engine.
class MaterialPlan extends DevlibComponent
{
    public function __construct(
        public array $plan = [],
    ) {}

    public function tag(): string
    {
        return 'x-mp::planning.material-plan';
    }

    public function detail(): bool
    {
        return ($this->plan['mode'] ?? 'list') === 'detail';
    }

    public function src(string ...$tables): ?string
    {
        return $tables ? Src::of(...$tables) : null;
    }

    public function render()
    {
        return view('mp::planning.material-plan');
    }
}
