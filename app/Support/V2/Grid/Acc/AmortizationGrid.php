<?php

namespace App\Support\V2\Grid\Acc;

/*
 * REMARK
 * Tujuan       : Grid Amortization: beban dibayar di muka, aset tak berwujud, dan leasehold yang diamortisasi; Open dan + New Record membuka form v2 acc-amortization-form (New = kartu Start pilih kategori).
 * Sumber hulu  : tm2acc.fin_asset + fin_asset_category + fin_asset_depr (induk AssetGrid).
 * Input        : Pencarian, statistik status, filter Category / Status.
 * Output       : Baris grid.
 * Dipakai oleh : Menu ACC_AMORT (v2:acc-amortization), layar acc-amortization-form (kembali ke daftar); amortisasi bulanan di layar acc-depreciation-run.
 * Status       : Aktif. Halaman v1 pages::finance.amortization sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'acc-amortization-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'acc-amortization-form').'?new=1';
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
