<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Provisioning ke ERP (v1: recruitment.hire-provision). Baris = lamaran dengan penawaran ACCEPTED; ?open={id} membuka
// panel provisioning v1 untuk lamaran itu (buat pegawai + akun).
class ProvisionGrid extends RcrGrid
{
    private array $employees = [];

    public function slug(): string
    {
        return 'hrm-rcr-provision';
    }

    public function title(): string
    {
        return 'Hire Provisioning';
    }

    protected function v1Route(): ?string
    {
        return 'hrm.recruitment.hire-provision';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function models(): array
    {
        return [AppApplication::class, CndCandidate::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search candidate, vacancy...';
    }

    protected function query(): Builder
    {
        return AppApplication::query()->with(['candidate', 'vacancy'])
            ->where(fn ($q) => $q->whereHas('offers', fn ($o) => $o->where('status', 'ACCEPTED'))->orWhereNotNull('provisioned_employee_id'))
            ->orderByDesc('id_application');
    }

    protected function prefetch(Collection $items): void
    {
        $this->employees = EmpEmployee::query()->whereKey($items->pluck('provisioned_employee_id')->filter()->unique()->all())
            ->get()->mapWithKeys(fn ($e) => [$e->getKey() => $e->employee_code.' '.$e->full_name])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Candidate', 'type' => 'identity', 'code' => 'phone'],
            ['key' => 'vacancy', 'label' => 'Vacancy'],
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'at', 'label' => 'Provisioned', 'type' => 'date'],
            ['key' => 'state', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Waiting' => 'warn', 'Done' => 'success']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'name' => $m->candidate?->name, 'phone' => $m->candidate?->phone, 'vacancy' => $m->vacancy?->title,
            'employee' => $this->employees[$m->provisioned_employee_id] ?? null, 'at' => $m->provisioned_at?->toDateString(),
            'state' => $m->provisioned_employee_id ? 'Done' : 'Waiting',
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Waiting', 'where' => ['state' => 'Waiting'], 'tone' => 'warn'],
            ['label' => 'Done', 'where' => ['state' => 'Done'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['vacancy' => 'Vacancy', 'state' => 'Status'];
    }
}
