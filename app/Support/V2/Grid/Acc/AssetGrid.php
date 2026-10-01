<?php

namespace App\Support\V2\Grid\Acc;

use App\Models\Acc\FinAsset;
use App\Models\Acc\FinAssetCategory;
use App\Models\Acc\FinAssetDepr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Induk daftar aset (tm2acc.fin_asset): aset tetap (penyusutan) dan beban dibayar di muka / tak berwujud (amortisasi).
// Satu turunan = satu kelompok asset_type kategori. Nilai buku = harga perolehan - akumulasi (fin_asset_depr), sama dengan v1.
abstract class AssetGrid extends AccGrid
{
    private const STATUSES = ['DRAFT' => 'Draft', 'ACTIVE' => 'Active', 'FULLY_DEPRECIATED' => 'Fully depreciated', 'DISPOSED' => 'Disposed'];

    private const STATUS_TONES = ['Draft' => 'warn', 'Active' => 'success', 'Fully depreciated' => 'info', 'Disposed' => 'neutral'];

    // id aset => akumulasi penyusutan / amortisasi.
    private array $accumulated = [];

    // asset_type kategori yang ditampilkan (FIXED, atau PREPAID / INTANGIBLE / LEASEHOLD).
    abstract protected function types(): array;

    // Kolom keterangan kedua: lokasi (aset tetap) atau dokumen sumber (amortisasi) => [kolom tabel, label].
    abstract protected function placeColumn(): array;

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Accounting';
    }

    protected function models(): array
    {
        return [FinAsset::class, FinAssetCategory::class, FinAssetDepr::class];
    }

    public function search(): string
    {
        return 'Search asset code, asset name...';
    }

    protected function query(): Builder
    {
        return FinAsset::query()->with('category')
            ->whereIn('category_id', FinAssetCategory::query()->whereIn('asset_type', $this->types())->select('category_id'))
            ->orderByDesc('acquisition_date')->orderByDesc('asset_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->accumulated = FinAssetDepr::query()->whereIn('asset_id', $items->pluck('asset_id')->all())
            ->selectRaw('asset_id, SUM(amount) total')->groupBy('asset_id')->pluck('total', 'asset_id')->all();
    }

    public function columns(): array
    {
        [, $placeLabel] = $this->placeColumn();

        return [
            ['key' => 'asset_code', 'label' => 'Asset Code / Category', 'type' => 'doc', 'party' => 'category'],
            ['key' => 'asset', 'label' => 'Asset Name'],
            ['key' => 'place', 'label' => $placeLabel],
            ['key' => 'acquired', 'label' => 'Acquired', 'type' => 'date'],
            ['key' => 'start', 'label' => 'Start', 'type' => 'date', 'visible' => false],
            ['key' => 'life', 'label' => 'Useful Life (months)', 'type' => 'number', 'visible' => false],
            ['key' => 'cost', 'label' => 'Cost', 'type' => 'money'],
            ['key' => 'accumulated', 'label' => 'Accumulated', 'type' => 'money'],
            ['key' => 'book_value', 'label' => 'Net Book Value', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $cost = (float) $m->acquisition_cost;
        $acc = (float) ($this->accumulated[$m->asset_id] ?? 0);

        return [
            'asset_code' => $m->asset_code ?: 'AST-'.$m->asset_id, 'category' => $m->category?->category_name, 'asset' => $m->asset_name,
            'place' => $m->getAttribute($this->placeColumn()[0]), 'acquired' => $m->acquisition_date?->toDateString(), 'start' => $m->depr_start_date?->toDateString(),
            'life' => $m->useful_life_months === null ? null : (int) $m->useful_life_months, 'cost' => $cost, 'accumulated' => $acc,
            'book_value' => round($cost - $acc, 2), 'status' => self::STATUSES[$m->status] ?? $m->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'warn'],
            ['label' => 'Active', 'where' => ['status' => 'Active'], 'tone' => 'success'],
            ['label' => 'Fully depreciated', 'where' => ['status' => 'Fully depreciated'], 'tone' => 'info'],
            ['label' => 'Disposed', 'where' => ['status' => 'Disposed'], 'tone' => 'neutral'],
        ];
    }

    public function filters(): array
    {
        return ['category' => 'Category', 'status' => 'Status'];
    }
}
