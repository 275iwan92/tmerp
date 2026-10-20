<?php

namespace App\Support\V2\Grid\Rcr;

use App\Models\Hrr\RcrRequisition;
use App\Models\Master\OrgDepartment;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Manpower Requisition (permohonan tenaga kerja); Open membuka form v2 hrm-rcr-requisition-form, + New Record membuka form kosong (baris lahir saat Save, bukan saat New).
 * Sumber hulu  : tm2hrr.rcr_requisition + rcr_track + vac_vacancy, tm2mst.org_department, tm2sys.sec_user.
 * Input        : Pencarian, filter Department / Track / Status, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu HRM > Recruitment (v2:hrm-rcr-requisition), layar hrm-rcr-requisition-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::recruitment.requisition.list dan entry sudah dipindah ke tmerp_unused (aturan D.25, T7l HRM).
 */
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
        return route('v2.screen', 'hrm-rcr-requisition-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return route('v2.screen', 'hrm-rcr-requisition-form').'?new=1';
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
            ['key' => 'no', 'label' => 'Requisition No. / Department', 'type' => 'doc', 'party' => 'department'],
            ['key' => 'position', 'label' => 'Position'],
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
