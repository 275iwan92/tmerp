<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Master\SupSupplier;
use App\Support\V2\Grid\DocListGrid;
use Devlib\Locale;

// Induk daftar / laporan Inventory > Batch & Quality: baris disiapkan turunan (listRows), hanya dibaca kecuali turunan memberi
// alamat Open (?id=) atau New (?new=1) ke layar formnya. Perilaku grid diwarisi dari DocListGrid.
abstract class QualityDocGrid extends DocListGrid
{
    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return 'Batch & Quality';
    }

    public function openUrl(): ?string
    {
        return null;
    }

    public function newUrl(): ?string
    {
        return null;
    }

    /** @return array<int, string> id_supplier => nama, satu query untuk seluruh halaman (lintas-DB: pola 1 CLAUDE.md) */
    protected function supplierNames(iterable $ids): array
    {
        return SupSupplier::query()->whereKey(collect($ids)->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->all();
    }

    protected function dt(mixed $value): string
    {
        return $value ? Locale::dt($value) : '';
    }
}
