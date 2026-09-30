<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdCrew;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Crew (v1: pages::production.master-crew).
class CrewGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-crew';
    }

    public function title(): string
    {
        return 'Master Crew';
    }

    protected function v1Route(): ?string
    {
        return 'production.master-crew';
    }

    protected function v1Component(): ?string
    {
        return 'pages::production.master-crew';
    }

    public function search(): string
    {
        return 'Cari crew, proses...';
    }

    protected function query(): Builder
    {
        return ProdCrew::query()->with('process')->orderBy('crew_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Crew', 'type' => 'identity', 'avatar' => false],
            ['key' => 'process', 'label' => 'Default Process'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->crew_name, 'process' => $m->process?->process_name];
    }

    public function filters(): array
    {
        return ['process' => 'Proses Default'];
    }
}
