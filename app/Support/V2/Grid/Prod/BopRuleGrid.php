<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdBopRule;
use App\Models\Master\ProdProcess;
use App\Models\Master\RefFinishingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid BOP Rule Setting: urutan langkah Bill of Process dan bahan yang ikut tiap langkah (dibaca engine ProDev); Open dan + New Record membuka form v2 prod-bop-form (hanya admin yang boleh mengubah).
 * Sumber hulu  : tm2mst.prod_bop_rule + prod_process + ref_finishing_type.
 * Input        : Pencarian, filter Scope / Finishing / Process / Material Role.
 * Output       : Baris grid; tombol aktif / nonaktif membalik prod_bop_rule.activate (hanya admin).
 * Dipakai oleh : Menu Production (v2:prod-bop-rule), layar prod-bop-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::production.bop-rule sudah dipindah ke tmerp_unused (aturan D.25, Master T6b).
 */
class BopRuleGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-bop-rule';
    }

    public function title(): string
    {
        return 'BOP Rule Setting';
    }

    public function icon(): string
    {
        return 'fa-solid fa-list-check';
    }

    protected function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'prod-bop-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->canCreate() ? route('v2.screen', 'prod-bop-form').'?new=1' : null;
    }

    protected function models(): array
    {
        return [ProdBopRule::class, ProdProcess::class, RefFinishingType::class];
    }

    public function search(): string
    {
        return 'Search step, process, material...';
    }

    protected function query(): Builder
    {
        return ProdBopRule::query()->with(['process', 'finishing'])->orderBy('step_seq')->orderBy('id_bop_rule');
    }

    public function columns(): array
    {
        return [
            ['key' => 'step', 'label' => 'Step', 'type' => 'identity', 'code' => 'seq', 'avatar' => false],
            ['key' => 'scope', 'label' => 'Scope'],
            ['key' => 'finishing', 'label' => 'Finishing'],
            ['key' => 'part_type', 'label' => 'Part Type', 'visible' => false],
            ['key' => 'process', 'label' => 'Process'],
            ['key' => 'merge', 'label' => 'Merged As', 'visible' => false],
            ['key' => 'machine_role', 'label' => 'Machine Role', 'visible' => false],
            ['key' => 'condition', 'label' => 'Condition', 'visible' => false],
            ['key' => 'material_role', 'label' => 'Material Role'],
            ['key' => 'usage_basis', 'label' => 'Usage Basis'],
            ['key' => 'usage_rate', 'label' => 'Usage Rate', 'type' => 'number'],
            ['key' => 'waste', 'label' => 'Waste %', 'type' => 'number'],
            ['key' => 'tolerance', 'label' => 'Tolerance %', 'type' => 'number', 'visible' => false],
            ['key' => 'source', 'label' => 'Rate Source', 'type' => 'badge', 'tones' => ['MANUAL' => 'neutral', 'AUTO' => 'info']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'step' => $m->step_label ?: $m->process?->process_name, 'seq' => (string) $m->step_seq, 'scope' => $m->rule_scope, 'finishing' => $m->finishing?->finishing_name,
            'part_type' => $m->part_type, 'process' => $m->process?->process_name, 'merge' => $m->merge_label ?: $m->merge_group, 'machine_role' => $m->machine_role,
            'condition' => $m->condition_code, 'material_role' => $m->material_role, 'usage_basis' => $m->usage_basis,
            'usage_rate' => $m->usage_rate === null ? null : (float) $m->usage_rate, 'waste' => $m->waste_pct === null ? null : (float) $m->waste_pct,
            'tolerance' => $m->tolerance_pct === null ? null : (float) $m->tolerance_pct, 'source' => $m->rate_source,
        ];
    }

    public function filters(): array
    {
        return ['scope' => 'Scope', 'finishing' => 'Finishing', 'process' => 'Process', 'material_role' => 'Material Role'];
    }
}
