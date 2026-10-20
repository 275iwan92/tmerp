<?php

/* REMARK
 * Tujuan       : Sheet Target Rate per Mesin (tab Mesin): default qty per jam untuk Produk + Part + Proses + Mesin.
 * Sumber hulu  : prod_part_process_machine_rate (tm2mst) lewat ProdPartProcessMachineRate; mesin dari ProdMachine aktif.
 * Input        : sel yang berubah; aturan umum di ProcessRateLookup.
 * Output       : baris rate mesin.
 * Dipakai oleh : layar v2 production-process-rate (tab Mesin).
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use App\Models\Master\ProdPartProcessMachineRate;
use Illuminate\Database\Eloquent\Builder;

class MachineRateLookup extends ProcessRateLookup
{
    public function slug(): string
    {
        return 'prod-rate-machine';
    }

    public function title(): string
    {
        return 'Machine Rates';
    }

    protected function query(): Builder
    {
        return ProdPartProcessMachineRate::query()->orderByDesc('rate_id');
    }

    protected function resourceColumn(): array
    {
        return ['key' => 'machine_id', 'label' => 'Machine', 'options' => ProdMachine::where('activate', true)->orderBy('machine_name')->pluck('machine_name', 'machine_id')->all()];
    }
}
