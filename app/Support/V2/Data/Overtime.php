<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpOvertimeRequest;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// HRM > Time Attendance > Overtime (v2): pengajuan lembur per pegawai per tanggal (emp_overtime_request). Hanya yang APPROVED yang dibaca
// AttendanceCalculationService (menit lembur + tipe tarif) saat presensi hari itu dihitung; layar ini hanya mengelola pengajuannya.
// Alur: DRAFT -> Submit -> SUBMITTED -> Approve (APPROVED) / Reject (REJECTED); REJECTED boleh Reopen ke DRAFT. Ubah isian hanya saat DRAFT.
// Persetujuan oleh pemilik modul HRM dan tidak untuk pengajuan atas dirinya sendiri (kecuali Administrator).
final class Overtime
{
    public const TYPES = ['AWAL' => 'Before shift', 'AKHIR' => 'After shift', 'KEDUANYA' => 'Before and after shift'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'success', 'REJECTED' => 'danger'];

    private const MAX_MINUTES = 960;

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        return EmpOvertimeRequest::query()->with('employee')->orderByDesc('overtime_date')->orderByDesc('id_overtime')->limit(1000)->get()
            ->map(fn (EmpOvertimeRequest $o) => [
                'id' => $o->id_overtime, 'employee' => $o->employee?->full_name ?? '—', 'code' => $o->employee?->employee_code ?? '', 'date' => $o->overtime_date?->toDateString(),
                'type' => t(self::TYPES[$o->ot_type] ?? $o->ot_type), 'minutes' => (int) $o->duration_minutes, 'hours' => round($o->duration_minutes / 60, 2),
                'status' => $o->status, 'by' => $o->approved_by ?? '', 'at' => $o->approved_at?->toDateString(),
            ])->all();
    }

    public static function find(int $id): ?EmpOvertimeRequest
    {
        return EmpOvertimeRequest::with('employee')->find($id);
    }

    public static function blank(): EmpOvertimeRequest
    {
        return new EmpOvertimeRequest(['overtime_date' => now()->toDateString(), 'ot_type' => 'AKHIR', 'duration_minutes' => 60, 'status' => 'DRAFT']);
    }

    /** @return array<int, string> id pegawai => "kode · nama" (aktif) */
    public static function employeeOptions(): array
    {
        return EmpEmployee::query()->where('activate', true)->orderBy('full_name')->get(['employee_id', 'employee_code', 'full_name'])
            ->mapWithKeys(fn ($e) => [$e->employee_id => ($e->employee_code ? $e->employee_code.' · ' : '').$e->full_name])->all();
    }

    public static function canEdit(EmpOvertimeRequest $o): bool
    {
        return $o->status === 'DRAFT';
    }

    /** Pemeriksa: pemilik modul HRM; bukan atas pengajuannya sendiri kecuali Administrator. */
    public static function canDecide(EmpOvertimeRequest $o, SecUser $user): bool
    {
        return $o->status === 'SUBMITTED' && $user->canAccessModule('HRM') && ($user->is_admin || (int) $user->employee_id !== (int) $o->employee_id);
    }

    /** @param array<string, mixed> $in */
    public static function save(?EmpOvertimeRequest $o, array $in, string $by): EmpOvertimeRequest
    {
        $d = Validator::make($in, [
            'employee_id' => ['required', 'integer', 'exists:tm2mst.emp_employee,employee_id'],
            'overtime_date' => ['required', 'date'],
            'ot_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MINUTES],
        ], [], ['employee_id' => t('Employee'), 'overtime_date' => t('Date'), 'ot_type' => t('Overtime Type'), 'duration_minutes' => t('Duration (minutes)')])->validate();

        $dup = EmpOvertimeRequest::query()->where('employee_id', $d['employee_id'])->whereDate('overtime_date', $d['overtime_date'])
            ->when($o, fn ($q) => $q->where('id_overtime', '!=', $o->id_overtime))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['overtime_date' => t('This employee already has an overtime request for that date.')]);
        }

        if ($o) {
            abort_unless(self::canEdit($o), 422, t('Only a draft can be changed.'));
            $o->update($d + ['updated_by' => $by]);

            return $o;
        }

        return EmpOvertimeRequest::create($d + ['status' => 'DRAFT', 'created_by' => $by, 'updated_by' => $by]);
    }

    /** submit | approve | reject | reopen. @return string pesan sukses */
    public static function transition(EmpOvertimeRequest $o, string $action, SecUser $user): string
    {
        $by = $user->login;
        $fail = fn (string $m) => throw ValidationException::withMessages(['status' => t($m)]);
        match ($action) {
            'submit' => $o->status === 'DRAFT' ? $o->update(['status' => 'SUBMITTED', 'updated_by' => $by]) : $fail('Only a draft can be submitted.'),
            'approve', 'reject' => self::canDecide($o, $user)
                ? $o->update(['status' => $action === 'approve' ? 'APPROVED' : 'REJECTED', 'approved_by' => $by, 'approved_at' => now(), 'updated_by' => $by])
                : $fail('You cannot decide this request.'),
            'reopen' => $o->status === 'REJECTED' ? $o->update(['status' => 'DRAFT', 'approved_by' => null, 'approved_at' => null, 'updated_by' => $by]) : $fail('Only a rejected request can be reopened.'),
            default => $fail('Unknown action.'),
        };

        return ['submit' => t('Submitted for approval.'), 'approve' => t('Approved — it counts when attendance of that day is calculated.'), 'reject' => t('Rejected.'), 'reopen' => t('Reopened as a draft.')][$action];
    }

    public static function delete(EmpOvertimeRequest $o): void
    {
        abort_unless(self::canEdit($o), 422, t('Only a draft can be deleted.'));
        $o->delete();
    }
}
