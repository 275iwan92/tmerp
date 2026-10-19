<?php

namespace App\Support\V2\Grid\Prod;

use App\Models\Master\ProdProcess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Master Process (proses produksi dan parameter bawaannya); Open dan + New Record membuka form v2 prod-process-form.
 * Sumber hulu  : tm2mst.prod_process + org_department + hitungan prod_process_machine.
 * Input        : Pencarian, filter Stage / Department / Performed By.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu Production (v2:prod-process), layar prod-process-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::production.master-process sudah dipindah ke tmerp_unused (aturan D.25, Master T6b).
 */
class ProcessGrid extends ProdGrid
{
    public function slug(): string
    {
        return 'prod-process';
    }

    public function title(): string
    {
        return t('Master Process');
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'prod-process-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'prod-process-form').'?new=1';
    }

    public function search(): string
    {
        return 'Search code, process name...';
    }

    protected function query(): Builder
    {
        return ProdProcess::query()->with('department')->withCount('machines')->orderBy('process_name');
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Process', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'stage', 'label' => 'Stage'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'where', 'label' => 'Performed By', 'type' => 'badge', 'tones' => [t('In-house') => 'success', t('Subcontract') => 'warn']],
            ['key' => 'manual', 'label' => 'Manual', 'type' => 'badge', 'tones' => [t('Yes') => 'info']],
            ['key' => 'speed', 'label' => 'Default Speed/Hour', 'type' => 'number'],
            ['key' => 'machines', 'label' => 'Machines', 'type' => 'link', 'format' => 'number'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'photo' => null, 'name' => $m->process_name, 'code' => $m->process_code, 'stage' => $m->process_stage ?? $m->stage,
            'dept' => $m->department?->department_name, 'where' => $m->inhouse === false || $m->inhouse === 0 ? t('Subcontract') : t('In-house'),
            'manual' => $m->is_manual ? t('Yes') : null, 'speed' => $m->default_speed_per_hour ? (float) $m->default_speed_per_hour : null,
            'machines' => (int) $m->machines_count,
        ];
    }

    public function filters(): array
    {
        return ['stage' => 'Stage', 'dept' => 'Department', 'where' => 'Performed By'];
    }
}
