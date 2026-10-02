<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use App\Support\V2\Data\MaterialPlanning;
use Devlib\Locale;

// Bahan yang kurang / belum punya item / terlambat datang menurut Material Planning (angka yang sama dengan popup layar pembuka).
final class MaterialShortage extends Widget
{
    public function key(): string
    {
        return 'material_shortage';
    }

    public function title(): string
    {
        return 'Material Shortage';
    }

    public function description(): string
    {
        return 'Materials that are short, with the first date they are needed and the orders waiting for them.';
    }

    public function type(): string
    {
        return 'list';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function icon(): string
    {
        return 'fa-triangle-exclamation';
    }

    public function module(): ?string
    {
        return 'INV';
    }

    public function phrases(): array
    {
        return ['kekurangan bahan', 'bahan kurang', 'material kurang', 'material shortage', 'short material'];
    }

    public function dashboard(): ?string
    {
        return 'PURCHASING';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $alert = MaterialPlanning::alerts();
        $rows = collect($alert['top'] ?? [])->map(fn (array $m) => ['title' => $m['material'],
            'sub' => trim((Locale::d($m['first_need']) ?? '—').' · '.$m['orders'], ' ·'), 'value' => Locale::number((float) $m['short'], 0).' '.$m['uom'], 'tone' => 'danger'])->all();
        $count = (int) ($alert['count'] ?? 0);

        return ['caption' => $count ? raw(str_replace(':n', (string) $count, t(':n materials need attention'))) : null, 'source' => 'tm2inv.rm_booking_d · v_rm_available · tm2pur.po_line',
            'data' => ['rows' => $rows, 'empty' => 'No material is short'], 'open' => $this->open('PROD_MRP', 'Material Planning (MRP)', 'fa-solid fa-chart-gantt')];
    }
}
