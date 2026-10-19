<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Master\EmpEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Hire Provisioning: lamaran dengan penawaran ACCEPTED (Waiting) dan yang sudah jadi pegawai (Done); Open membuka form v2 hrm-rcr-provision-form (Provision to ERP). Tanpa + New Record: baris muncul dari penawaran yang diterima.
 * Sumber hulu  : tm2hrr.app_application + app_offer + cnd_candidate + vac_vacancy, tm2mst.emp_employee.
 * Input        : Pencarian, filter Vacancy / Status, kotak status.
 * Output       : Baris grid (hanya baca; provisioning lewat form).
 * Dipakai oleh : Menu HRM > Recruitment (v2:hrm-rcr-provision), layar hrm-rcr-provision-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::recruitment.hire-provision sudah dipindah ke tmerp_unused (aturan D.25, T7j HRM).
 */
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

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-provision-form').'?id={id}';
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
            ['key' => 'name', 'label' => 'Candidate / Phone', 'type' => 'doc', 'party' => 'phone'],
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
