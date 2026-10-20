<?php

/* REMARK
 * Tujuan       : Induk dua sheet Target Rate (qty per jam) default per Produk + Part + Proses + Mesin / Pegawai: dipakai WO Generation, selalu bisa ditimpa manual per WO. Turunan hanya menyebut model, kolom sumber daya, dan slug.
 * Sumber hulu  : prod_part_process_machine_rate / prod_part_process_employee_rate (tm2mst), master produk, proses, mesin, pegawai; pindahan layar v1 pages::production.process-rate.
 * Input        : sel yang berubah (produk, part, proses, mesin / pegawai, target, aktif).
 * Output       : baris rate tersimpan; target wajib >= 0,01; baris tidak dihapus, cukup dinonaktifkan.
 * Dipakai oleh : layar v2 production-process-rate (tab Mesin dan Pegawai), WoGenerationService.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdProcess;
use App\Models\Master\ProdProduct;
use App\Support\V2\Grid\DbLookup;

abstract class ProcessRateLookup extends DbLookup
{
    public function module(): string
    {
        return 'PROD';
    }

    public function moduleLabel(): string
    {
        return 'Production';
    }

    public function group(): string
    {
        return 'Process Rate';
    }

    public function icon(): string
    {
        return 'fa-solid fa-gauge-high';
    }

    /** Kolom sumber daya: ['key' => machine_id|employee_id, 'label' => ..., 'options' => [...]]. */
    abstract protected function resourceColumn(): array;

    public function guide(): ?string
    {
        return 'Default target per Product + Part + Process + resource — used by WO Generation; it can always be overridden by hand per WO.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'product_id', 'label' => 'Product', 'type' => 'select', 'options' => ProdProduct::where('activate', true)->orderBy('product_name')->pluck('product_name', 'id_product')->all(), 'required' => true, 'width' => '260px'],
            ['key' => 'part_code', 'label' => 'Part', 'required' => true, 'width' => '100px'],
            ['key' => 'process_id', 'label' => 'Process', 'type' => 'select', 'options' => ProdProcess::where('activate', true)->orderBy('process_name')->pluck('process_name', 'id_process')->all(), 'required' => true, 'width' => '200px'],
            $this->resourceColumn() + ['type' => 'select', 'required' => true, 'width' => '220px'],
            ['key' => 'target_qty_per_hour', 'label' => 'Target/Hour', 'type' => 'number', 'required' => true, 'width' => '110px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function rules(): array
    {
        return ['target_qty_per_hour' => ['min:0.01'], 'part_code' => ['max:50']];
    }
}
