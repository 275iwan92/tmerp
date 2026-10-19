<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdRoutingTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Routing Template (urutan operasi standar per tipe produk) dengan tipe produk, bahan, template bawaan dan jumlah langkah; Open dan + New Record membuka form v2 prod-routing-form.
 * Sumber hulu  : tm2mst.prod_routing_template + prod_routing_step (jumlah langkah).
 * Input        : Pencarian, filter Product Type.
 * Output       : Baris grid; tombol aktif / nonaktif membalik prod_routing_template.activate.
 * Dipakai oleh : Menu Production (v2:prod-routing), layar prod-routing-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::production.routing-template sudah dipindah ke tmerp_unused (aturan D.25, Master T6b).
 */
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

    protected function canCreate(): bool
    {
        return true;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'prod-routing-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'prod-routing-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search code, template name...';
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
            ['key' => 'type', 'label' => 'Product Type'],
            ['key' => 'substrate', 'label' => 'Material'],
            ['key' => 'default', 'label' => 'Default', 'type' => 'badge', 'tones' => [t('Yes') => 'success']],
            ['key' => 'steps', 'label' => 'Steps', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['photo' => null, 'name' => $m->template_name, 'code' => $m->template_code, 'type' => $m->prod_type_code,
            'substrate' => $m->substrate_hint, 'default' => $m->is_default ? t('Yes') : null, 'steps' => (int) $m->steps_count];
    }

    public function filters(): array
    {
        return ['type' => 'Product Type'];
    }
}
