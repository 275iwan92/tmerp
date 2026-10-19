<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Machine (aset mesin dan register kapabilitas); Open dan + New Record membuka form v2 prod-machine-form. Spek kosong ditampilkan kosong, tidak dikarang.
 * Sumber hulu  : tm2mst.prod_machine + prod_machine_model + hitungan prod_process_machine.
 * Input        : Pencarian, filter Type / Role / Brand, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu Production (v2:prod-machine), layar prod-machine-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::master.machine sudah dipindah ke tmerp_unused (aturan D.25, Master T6b).
 */
class MachineGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-machine';
    }

    public function title(): string
    {
        return t('Master Machine');
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'prod-machine-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'prod-machine-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search code, name, brand/model...';
    }

    protected function query(): Builder
    {
        return ProdMachine::query()->with('machineModel')->withCount('processes')->orderBy('machine_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Machine', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'nickname', 'label' => 'Nickname'],
            ['key' => 'model', 'label' => 'Model (catalogue)'],
            ['key' => 'role', 'label' => 'Role', 'type' => 'badge', 'tones' => [t('Production') => 'info', t('Supporting') => 'neutral']],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'brand', 'label' => 'Brand / Model'],
            ['key' => 'sheet', 'label' => 'Max Sheet (mm)'],
            ['key' => 'speed', 'label' => 'Max Speed/Hour', 'type' => 'number'],
            ['key' => 'year', 'label' => 'Year'],
            ['key' => 'processes', 'label' => 'Processes', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->machine_name, 'code' => $m->machine_code, 'nickname' => $m->nickname, 'model' => $m->machineModel?->model_code, 'role' => t($m->is_process_machine ? 'Production' : 'Supporting'), 'type' => $m->machine_type, 'brand' => $m->brand_model,
            'sheet' => $m->max_sheet_length_mm ? (int) $m->max_sheet_length_mm.' x '.(int) $m->max_sheet_width_mm : null,
            'speed' => $m->max_speed_per_hour ? (float) $m->max_speed_per_hour : null, 'year' => $m->year_acquired,
            'processes' => (int) $m->processes_count,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Active', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'No spec yet', 'where' => ['sheet' => null], 'tone' => 'warn'],
            ['label' => 'Supporting (non-production)', 'where' => ['role' => t('Supporting')], 'tone' => 'neutral'],
            ['label' => 'Inactive', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Type', 'role' => 'Role', 'brand' => 'Brand / Model'];
    }
}
