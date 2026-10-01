<?php

namespace App\Support\V2\Grid\Acc;

// Amortization (v1: pages::finance.amortization): beban dibayar di muka, aset tak berwujud, dan leasehold yang diamortisasi.
// Buka = jadwal amortisasi v1; New = jadwal baru v1. Jalankan amortisasi bulanan tetap di layar v1.
class AmortizationGrid extends AssetGrid
{
    public function slug(): string
    {
        return 'acc-amortization';
    }

    public function title(): string
    {
        return 'Amortization';
    }

    public function icon(): string
    {
        return 'fa-solid fa-arrow-trend-down';
    }

    protected function v1Route(): ?string
    {
        return 'finance.amortization';
    }

    protected function types(): array
    {
        return ['PREPAID', 'INTANGIBLE', 'LEASEHOLD'];
    }

    protected function placeColumn(): array
    {
        return ['source_ref', 'Source Document'];
    }
}
