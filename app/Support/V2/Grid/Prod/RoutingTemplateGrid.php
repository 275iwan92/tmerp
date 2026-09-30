<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdRoutingTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Master Routing Template (v1: pages::production.routing-template).
class RoutingTemplateGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-routing';
    }

    public function title(): string
    {
        return 'Master Routing Template';
    }

    protected function v1Route(): ?string
    {
        return 'production.routing-template';
    }

    protected function v1Component(): ?string
    {
        return 'pages::production.routing-template';
    }

    public function search(): string
    {
        return 'Cari kode, nama template...';
    }

    protected function query(): Builder
    {
        return ProdRoutingTemplate::query()->withCount('steps')->orderBy('template_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Template', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'type', 'label' => 'Tipe Produk'],
            ['key' => 'substrate', 'label' => 'Material'],
            ['key' => 'default', 'label' => 'Default', 'type' => 'badge', 'tones' => ['Ya' => 'success']],
            ['key' => 'steps', 'label' => 'Steps', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->template_name, 'code' => $m->template_code, 'type' => $m->prod_type_code,
            'substrate' => $m->substrate_hint, 'default' => $m->is_default ? 'Ya' : null, 'steps' => (int) $m->steps_count];
    }

    public function filters(): array
    {
        return ['type' => 'Tipe Produk'];
    }
}
