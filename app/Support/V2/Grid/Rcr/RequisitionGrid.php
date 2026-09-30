<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\RcrRequisition;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Permohonan Tenaga Kerja (v1: recruitment.requisition.list -> entry). New = daftar v1 (tombol Baru di sana langsung
// membuat draft, jadi tidak dipicu lewat alamat GET).
class RequisitionGrid extends RcrGrid
{
    private array $departments = [];

    private array $users = [];

    public function slug(): string
    {
        return 'hrm-rcr-requisition';
    }

    public function title(): string
    {
        return 'Manpower Requisition';
    }

    public function openUrl(): ?string
    {
        return $this->routeUrl('hrm.recruitment.requisition.entry', 'requisition');
    }

    public function newUrl(): ?string
    {
        return route('hrm.recruitment.requisition.list');
    }

    protected function models(): array
    {
        return [RcrRequisition::class, OrgDepartment::class];
    }

    public function search(): string
    {
        return 'Search requisition no, position...';
    }

    protected function query(): Builder
    {
        return RcrRequisition::query()->with('track')->withCount('vacancies')->orderByDesc('id_requisition');
    }

    protected function prefetch(Collection $items): void
    {
        $this->departments = OrgDepartment::query()->whereKey($items->pluck('department_id')->filter()->unique()->all())
            ->pluck('department_name', 'department_id')->all();
        $this->users = SecUser::query()->whereKey($items->pluck('requested_by')->filter()->unique()->all())->pluck('name', 'id_user')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Requisition No'],
            ['key' => 'position', 'label' => 'Position'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'track', 'label' => 'Track'],
            ['key' => 'headcount', 'label' => 'Headcount', 'type' => 'number'],
            ['key' => 'vacancies', 'label' => 'Vacancies', 'type' => 'number'],
            ['key' => 'requested_by', 'label' => 'Requested By'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn',
                'APPROVED' => 'success', 'REJECTED' => 'danger', 'CLOSED' => 'neutral']],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->requisition_no, 'position' => $m->position_title, 'department' => $this->departments[$m->department_id] ?? null,
            'track' => $m->track?->name, 'headcount' => (int) $m->headcount,
            'vacancies' => (int) $m->vacancies_count, 'requested_by' => $this->users[$m->requested_by] ?? $m->requested_by, 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Waiting', 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn'],
            ['label' => 'Approved', 'where' => ['status' => 'APPROVED'], 'tone' => 'success'],
            ['label' => 'Rejected', 'where' => ['status' => 'REJECTED'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['department' => 'Department', 'track' => 'Track', 'status' => 'Status'];
    }
}
