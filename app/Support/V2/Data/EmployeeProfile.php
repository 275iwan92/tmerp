<?php

/*
 * REMARK
 * Tujuan       : Sisi baca layar v2 Master Employee Form (hrm-employee-form): angka KPI banner, daftar "To complete" (kelengkapan data) dan opsi pilihan form.
 * Sumber hulu  : tm2mst.emp_employee, emp_leave_balance, att_daily_status, emp_disciplinary, emp_family, org_position, prod_machine, emp_shift; aturan wajib = EmployeeProfileService.
 * Input        : Model EmpEmployee (boleh yang belum tersimpan untuk form New).
 * Output       : Array angka / daftar untuk x-dl::kpi dan x-dl::progress-ring. Tidak menyimpan apa pun (semua dihitung saat layar dibuka).
 * Dipakai oleh : resources/views/v2/screens/hrm-employee-form.blade.php.
 * Status       : Aktif.
 */

namespace App\Support\V2\Data;

use App\Models\Master\AttDailyStatus;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Master\EmpLeaveBalance;
use App\Models\Master\EmpShift;
use App\Models\Master\OrgPosition;
use App\Models\Master\ProdMachine;
use App\Services\Hrm\EmployeeProfileService;

final class EmployeeProfile
{
    // Tab -> bagian isian wajib yang dicek untuk "To complete".
    private const FIELD_TAB = ['full_name' => 'profile', 'gender' => 'profile', 'birth_date' => 'profile', 'nik_ktp' => 'profile', 'no_kk' => 'profile',
        'phone' => 'profile', 'address_detail_domisili' => 'profile', 'join_date' => 'job', 'position_id' => 'job'];

    private const FIELD_LABEL = ['full_name' => 'Full name', 'gender' => 'Gender', 'birth_date' => 'Birth date', 'nik_ktp' => 'NIK KTP (16 digits)',
        'no_kk' => 'Family card (KK)', 'phone' => 'Mobile / WhatsApp', 'address_detail_domisili' => 'Residence address', 'join_date' => 'Join date', 'position_id' => 'Position'];

    /** @return list<array{label:string, value:string, tone:string, note:?string, icon:string}> */
    public static function kpis(EmpEmployee $e): array
    {
        $left = self::leaveLeft($e);
        $sp = $e->activeSpType();
        $verbal = $e->verbalWarningCount();

        return [
            ['label' => 'Tenure', 'value' => self::tenure($e), 'tone' => 'success', 'note' => $e->join_date ? null : 'No join date', 'icon' => 'fa-hourglass-half'],
            ['label' => 'Leave days left', 'value' => $left === null ? '—' : (string) $left, 'tone' => $left !== null && $left <= 3 ? 'warn' : 'accent', 'note' => (string) now()->year, 'icon' => 'fa-umbrella-beach'],
            ['label' => 'Attendance', 'value' => (string) self::attendanceYtd($e), 'tone' => 'accent', 'note' => 'days in '.now()->year, 'icon' => 'fa-fingerprint'],
            ['label' => 'Discipline', 'value' => $sp ?: ($verbal ? "Verbal $verbal/6" : 'Clean'), 'tone' => $sp ? 'danger' : ($verbal ? 'warn' : 'success'), 'note' => null, 'icon' => 'fa-gavel'],
        ];
    }

    public static function tenure(EmpEmployee $e): string
    {
        if (! $e->join_date) {
            return '—';
        }
        $d = $e->join_date->diff($e->resign_date ?? now());

        return $d->y.'y '.$d->m.'m';
    }

    // Sisa cuti tahun ini = jatah + bawaan − terpakai; null = belum ada baris saldo.
    public static function leaveLeft(EmpEmployee $e): ?int
    {
        $b = $e->exists ? EmpLeaveBalance::where('employee_id', $e->employee_id)->where('year', now()->year)->first() : null;

        return $b ? $b->entitlement_days + $b->carried_over_days - $b->used_days : null;
    }

    public static function attendanceYtd(EmpEmployee $e): int
    {
        return $e->exists ? AttDailyStatus::where('employee_id', $e->employee_id)->whereYear('attendance_date', now()->year)->count() : 0;
    }

    /**
     * Isian wajib yang masih kosong: [['label', 'tab'], ...]. Dihitung dari isi tersimpan (bukan dari isian yang sedang diketik).
     * Rekening bank tidak ikut: "Cash / Tunai" = tanpa baris rekening, jadi kosong tidak berarti belum diisi.
     *
     * @return list<array{label:string, tab:string}>
     */
    public static function missing(EmpEmployee $e): array
    {
        $out = [];
        foreach (self::FIELD_TAB as $field => $tab) {
            $v = $e->{$field};
            if ($v === null || $v === '' || ($field === 'nik_ktp' && ! preg_match('/^\d{16}$/', (string) $v))) {
                $out[] = ['label' => self::FIELD_LABEL[$field], 'tab' => $tab];
            }
        }
        $have = $e->exists ? EmpFamily::where('employee_id', $e->employee_id)->pluck('relation')->all() : [];
        foreach (EmployeeProfileService::MANDATORY_FAMILY as $code => $label) {
            if (! in_array($code, $have, true)) {
                $out[] = ['label' => $label, 'tab' => 'family'];
            }
        }

        return $out;
    }

    public static function total(): int
    {
        return count(self::FIELD_TAB) + count(EmployeeProfileService::MANDATORY_FAMILY);
    }

    public static function percent(EmpEmployee $e): int
    {
        return (int) round(100 * (self::total() - count(self::missing($e))) / self::total());
    }

    /** @return array{0: string, 1: string} [label, tone] status masa berlaku dokumen: tanpa tanggal = tidak ada batas, lewat = Expired, <= 30 hari = peringatan */
    public static function documentStatus(EmpDocument $d): array
    {
        if (! $d->expiry_date) {
            return ['No expiry date', 'neutral'];
        }
        $days = (int) now()->startOfDay()->diffInDays($d->expiry_date, false);

        return match (true) {
            $days < 0 => ['Expired', 'danger'],
            $days <= 30 => [tf('Expires in :n days', ['n' => $days]), 'warn'],
            default => ['Valid', 'success'],
        };
    }

    public static function positionOptions(): array
    {
        return OrgPosition::query()->with('department')->where('activate', true)->get()
            ->sortBy(fn ($p) => $p->department?->department_name.$p->position_name)
            ->mapWithKeys(fn ($p) => [$p->position_id => $p->department?->department_name.' — '.$p->position_name])->all();
    }

    public static function machineOptions(): array
    {
        return ProdMachine::query()->where('activate', true)->orderBy('machine_code')->get()
            ->mapWithKeys(fn ($m) => [$m->machine_id => $m->machine_code.' — '.$m->machine_name])->all();
    }

    public static function shiftOptions(): array
    {
        return EmpShift::query()->where('activate', true)->orderBy('shift_name')->pluck('shift_name', 'shift_id')->all();
    }
}
