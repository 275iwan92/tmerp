<?php

namespace App\Support\V2\Grid\Acc;

/*
 * REMARK
 * Tujuan       : Grid Fixed Assets: daftar aset tetap dengan akumulasi dan nilai buku; Open dan + New Record membuka form v2 acc-fixed-asset-form (New = kartu Start pilih kategori).
 * Sumber hulu  : tm2acc.fin_asset + fin_asset_category + fin_asset_depr (induk AssetGrid).
 * Input        : Pencarian, statistik status, filter Category / Status.
 * Output       : Baris grid.
 * Dipakai oleh : Menu ACC_FIXEDASSET (v2:acc-fixed-asset), layar acc-fixed-asset-form (kembali ke daftar); penyusutan bulanan di layar acc-depreciation-run.
 * Status       : Aktif. Halaman v1 pages::finance.assets sudah dipindah ke tmerp_unused (aturan D.25).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'acc-fixed-asset-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'acc-fixed-asset-form').'?new=1';
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
