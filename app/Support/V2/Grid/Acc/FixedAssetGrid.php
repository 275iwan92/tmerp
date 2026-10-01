<?php

namespace App\Support\V2\Grid\Acc;

// Fixed Assets (v1: pages::finance.assets): daftar aset tetap + penyusutannya. Buka = tampilan aset v1 (jadwal penyusutan,
// kapitalisasi, pelepasan); New = form aset baru v1. Jalankan penyusutan bulanan tetap di layar v1.
class FixedAssetGrid extends AssetGrid
{
    public function slug(): string
    {
        return 'acc-fixed-asset';
    }

    public function title(): string
    {
        return 'Fixed Assets';
    }

    public function icon(): string
    {
        return 'fa-solid fa-building';
    }

    protected function v1Route(): ?string
    {
        return 'finance.assets';
    }

    protected function types(): array
    {
        return ['FIXED'];
    }

    protected function placeColumn(): array
    {
        return ['location', 'Location'];
    }
}
