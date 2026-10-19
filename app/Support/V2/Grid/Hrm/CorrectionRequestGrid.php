<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpEmployee;
use App\Services\Hrm\CorrectionRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Koreksi Data Karyawan (pengajuan dari Self-Service dan Setup Keamanan Akun: PENDING menunggu HR); Open membuka form v2 hrm-correction-form tempat HR memutuskan. Tanpa New. Nilai pengajuan rekening gaji disamarkan bagi yang tidak berhak payroll atas pegawai itu.
 * Sumber hulu  : tm2mst.emp_correction_request + emp_employee.
 * Input        : Pencarian, filter Field / Status, kotak status.
 * Output       : Baris grid (hanya baca; keputusan lewat form).
 * Dipakai oleh : Menu HRM (v2:hrm-correction), layar hrm-correction-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::hrm.correction-requests sudah dipindah ke tmerp_unused (aturan D.25, T7c HRM).
 */
class CorrectionRequestGrid extends HrmGrid
{
    public function slug(): string
    {
        return 'hrm-correction';
    }

    public function title(): string
    {
        return t('Employee Data Correction');
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-correction-form').'?id={id}';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function models(): array
    {
        return [EmpCorrectionRequest::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search employee, field, value...';
    }

    protected function query(): Builder
    {
        return EmpCorrectionRequest::query()->with('employee')
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")->orderByDesc('requested_at');
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'field', 'label' => 'Field'],
            ['key' => 'old', 'label' => 'Old Value'],
            ['key' => 'new', 'label' => 'New Value'],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'requested', 'label' => 'Submitted', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::REQUEST_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        $v = CorrectionRequestService::values($m, auth()->user());

        return [
            'name' => $m->employee?->full_name, 'code' => $m->employee?->employee_code, 'field' => $m->field_name,
            'old' => $v['old'], 'new' => $v['new'], 'reason' => $m->reason,
            'requested' => $m->requested_at?->toDateString(), 'status' => $m->status,
        ];
    }

    public function stats(): array
    {
        return $this->requestStats();
    }

    public function filters(): array
    {
        return ['field' => 'Field', 'status' => 'Status'];
    }
}
