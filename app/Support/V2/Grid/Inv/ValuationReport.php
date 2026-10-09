<?php

namespace App\Support\V2\Grid\Inv;

use App\Support\V2\Grid\DocListGrid;
use Carbon\CarbonImmutable;

// Induk daftar / laporan Inventory > Valuation & Period: baris disiapkan turunan (listRows) dari kelas layanan App\Services\Inventory\Valuation\*;
// laporan hanya membaca. Tanggal akhir pilihan periode (?to=) = tanggal per saat laporan (as of) untuk laporan saldo.
abstract class ValuationReport extends DocListGrid
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
        return 'Valuation & Period';
    }

    public function family(): string
    {
        return self::REPORT;
    }

    public function printable(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return null;
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function defaultPeriod(): ?array
    {
        return [now()->toImmutable()->startOfMonth(), now()->toImmutable()->startOfDay()];
    }

    protected function asOf(): CarbonImmutable
    {
        return $this->period()[1];
    }
}
