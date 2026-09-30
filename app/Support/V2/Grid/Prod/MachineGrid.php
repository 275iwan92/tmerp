<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdMachine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Mesin (v1: pages::master.machine). Spek kosong ditampilkan kosong — jangan dikarang (CLAUDE.md §6B).
class MachineGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-machine';
    }

    public function title(): string
    {
        return 'Master Mesin';
    }

    protected function v1Route(): ?string
    {
        return 'master.machine';
    }

    protected function v1Component(): ?string
    {
        return 'pages::master.machine';
    }

    public function search(): string
    {
        return 'Cari kode, nama, merek/model...';
    }

    protected function query(): Builder
    {
        return ProdMachine::query()->with('machineModel')->withCount('processes')->orderBy('machine_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Mesin', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'nickname', 'label' => 'Nama Panggilan'],
            ['key' => 'model', 'label' => 'Model (katalog)'],
            ['key' => 'role', 'label' => 'Peran', 'type' => 'badge', 'tones' => ['Produksi' => 'info', 'Penunjang' => 'neutral']],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'brand', 'label' => 'Merek / Model'],
            ['key' => 'sheet', 'label' => 'Lembar Maks (mm)'],
            ['key' => 'speed', 'label' => 'Kecepatan Maks/Jam', 'type' => 'number'],
            ['key' => 'year', 'label' => 'Tahun'],
            ['key' => 'processes', 'label' => 'Processes', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->machine_name, 'code' => $m->machine_code, 'nickname' => $m->nickname, 'model' => $m->machineModel?->model_code, 'role' => $m->is_process_machine ? 'Produksi' : 'Penunjang', 'type' => $m->machine_type, 'brand' => $m->brand_model,
            'sheet' => $m->max_sheet_length_mm ? (int) $m->max_sheet_length_mm.' x '.(int) $m->max_sheet_width_mm : null,
            'speed' => $m->max_speed_per_hour ? (float) $m->max_speed_per_hour : null, 'year' => $m->year_acquired,
            'processes' => (int) $m->processes_count,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'Semua'],
            ['label' => 'Aktif', 'where' => ['active' => true], 'tone' => 'success'],
            ['label' => 'Spek belum ada', 'where' => ['sheet' => null], 'tone' => 'warn'],
            ['label' => 'Penunjang (non-produksi)', 'where' => ['role' => 'Penunjang'], 'tone' => 'neutral'],
            ['label' => 'Nonaktif', 'where' => ['active' => false], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['type' => 'Jenis', 'role' => 'Peran', 'brand' => 'Merek / Model'];
    }
}
