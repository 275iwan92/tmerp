<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdProcess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Proses (v1: pages::production.master-process).
class ProcessGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-process';
    }

    public function title(): string
    {
        return 'Master Proses';
    }

    protected function v1Route(): ?string
    {
        return 'production.master-process';
    }

    protected function v1Component(): ?string
    {
        return 'pages::production.master-process';
    }

    public function search(): string
    {
        return 'Cari kode, nama proses...';
    }

    protected function query(): Builder
    {
        return ProdProcess::query()->with('department')->withCount('machines')->orderBy('process_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Proses', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'stage', 'label' => 'Tahap'],
            ['key' => 'dept', 'label' => 'Departemen'],
            ['key' => 'where', 'label' => 'Dikerjakan', 'type' => 'badge', 'tones' => ['In-house' => 'success', 'Subkon' => 'warn']],
            ['key' => 'manual', 'label' => 'Manual', 'type' => 'badge', 'tones' => ['Ya' => 'info']],
            ['key' => 'speed', 'label' => 'Kecepatan Default/Jam', 'type' => 'number'],
            ['key' => 'machines', 'label' => 'Machines', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->process_name, 'code' => $m->process_code, 'stage' => $m->process_stage ?? $m->stage,
            'dept' => $m->department?->department_name, 'where' => $m->inhouse === false || $m->inhouse === 0 ? 'Subkon' : 'In-house',
            'manual' => $m->is_manual ? 'Ya' : null, 'speed' => $m->default_speed_per_hour ? (float) $m->default_speed_per_hour : null,
            'machines' => (int) $m->machines_count,
        ];
    }

    public function filters(): array
    {
        return ['stage' => 'Tahap', 'dept' => 'Departemen', 'where' => 'Dikerjakan'];
    }
}
