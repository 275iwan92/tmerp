<?php

namespace App\Services\Payroll;

use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

// REMARK Tujuan: satu pintu "siapa boleh melihat payroll siapa" (user 2026-10-07). Default tolak; is_admin TIDAK otomatis lolos (developer tidak menyentuh payroll, hanya Direksi/GM). Sumber hulu: grup sec_user_group + emp_employee.staff_type.
//   ALL = grup DIREKSI (semua pegawai dan layar payroll); NON_STAFF = HR_OFFICER (hanya pegawai NON_STAFF); SUBORDINATES = OPS_MGR (hanya slip bawahan); NONE = selain itu. Input: user, id pegawai, slug layar / kode menu.
// Output: scope, canProcess / canViewPayslips / canSeeEmployee, restrict() untuk query, screenAllowed() / menuAllowed(). Dipakai oleh: middleware payroll:*, layar dan layanan Payroll (Salary, Run, Payslip), AppMenu, grid payroll. Status: aktif; slug form baru (hrm-salary-form, hrm-payroll-run-form, hrm-payslip-form) masuk daftar layar di bawah.
final class PayrollAccess
{
    public const ALL = 'ALL';

    public const NON_STAFF = 'NON_STAFF';

    public const SUBORDINATES = 'SUBORDINATES';

    public const NONE = 'NONE';

    private static array $scopeCache = [];

    public static function scope(?SecUser $user): string
    {
        if (! $user) {
            return self::NONE;
        }

        return self::$scopeCache[$user->getKey()] ??= self::resolveScope($user);
    }

    public static function forget(): void
    {
        self::$scopeCache = [];
    }

    private static function resolveScope(SecUser $user): string
    {
        $groups = $user->groups()->pluck('group_code')->all();

        return match (true) {
            in_array('DIREKSI', $groups, true) => self::ALL,
            in_array('HR_OFFICER', $groups, true) => self::NON_STAFF,
            in_array('OPS_MGR', $groups, true) && $user->employee_id => self::SUBORDINATES,
            default => self::NONE,
        };
    }

    // Slip gaji (daftar, cetak): semua peran yang punya cakupan.
    public static function canViewPayslips(?SecUser $user): bool
    {
        return self::scope($user) !== self::NONE;
    }

    // Gaji pokok, batch run, rekening, transfer bank, cash roster: memproses payroll, bukan Op Manager.
    public static function canProcess(?SecUser $user): bool
    {
        return in_array(self::scope($user), [self::ALL, self::NON_STAFF], true);
    }

    // Layar v2 (slug) dan item menu yang masuk payroll. Layar lain selalu lolos.
    private const PROCESS_SCREENS = ['hrm-salary', 'hrm-salary-form', 'hrm-payroll-run', 'hrm-payroll-run-form', 'hrm-bank', 'hrm-bank-transfer', 'hrm-cash-roster'];

    private const PAYSLIP_SCREENS = ['hrm-payslip', 'hrm-payslip-form'];

    private const PROCESS_MENU = ['HRM_PAYROLL_CALC', 'HRM_PAYROLL_BANKXFER', 'HRM_PAYROLL_CASHROSTER', 'HRM_PAYROLL_SALARY', 'HRM_PAYROLL_BANK'];

    private const PAYSLIP_MENU = ['HRM_PAYROLL_PAYSLIP'];

    public static function screenAllowed(string $slug, ?SecUser $user): bool
    {
        return match (true) {
            in_array($slug, self::PROCESS_SCREENS, true) => self::canProcess($user),
            in_array($slug, self::PAYSLIP_SCREENS, true) => self::canViewPayslips($user),
            default => true,
        };
    }

    public static function menuAllowed(string $code, ?SecUser $user): bool
    {
        return match (true) {
            in_array($code, self::PROCESS_MENU, true) => self::canProcess($user),
            in_array($code, self::PAYSLIP_MENU, true) => self::canViewPayslips($user),
            default => true,
        };
    }

    // Ubah tipe pegawai: hanya yang berhak atas semua payroll, supaya HR tidak bisa memindahkan staf ke non-staf untuk melihatnya.
    public static function canEditStaffType(?SecUser $user): bool
    {
        return self::scope($user) === self::ALL;
    }

    // null = tanpa batas; array = hanya employee_id ini.
    public static function employeeIds(?SecUser $user): ?array
    {
        return match (self::scope($user)) {
            self::ALL => null,
            self::NON_STAFF => EmpEmployee::query()->where('staff_type', 'NON_STAFF')->pluck('employee_id')->all(),
            self::SUBORDINATES => self::subordinateIds($user),
            default => [],
        };
    }

    public static function canSeeEmployee(?SecUser $user, int $employeeId): bool
    {
        $ids = self::employeeIds($user);

        return $ids === null || in_array($employeeId, $ids, true);
    }

    public static function restrict(EloquentBuilder|Builder $query, ?SecUser $user, string $column = 'employee_id'): EloquentBuilder|Builder
    {
        $ids = self::employeeIds($user);

        return $ids === null ? $query : $query->whereIn($column, $ids === [] ? [0] : $ids);
    }

    // Bawahan = pegawai pada posisi turunan (reports_to_position_id) dari posisi pegawai ini, tanpa dirinya sendiri.
    private static function subordinateIds(SecUser $user): array
    {
        $me = EmpEmployee::query()->find($user->employee_id);
        if (! $me?->position_id) {
            return [];
        }

        $byParent = OrgPosition::query()->whereNotNull('reports_to_position_id')->get(['position_id', 'reports_to_position_id'])
            ->groupBy('reports_to_position_id');

        $positions = [];
        $queue = [(int) $me->position_id];
        while ($queue) {
            $parent = array_shift($queue);
            foreach ($byParent->get($parent, []) as $child) {
                if (! isset($positions[$child->position_id])) {
                    $positions[$child->position_id] = true;
                    $queue[] = (int) $child->position_id;
                }
            }
        }

        return EmpEmployee::query()->whereIn('position_id', array_keys($positions))
            ->where('employee_id', '!=', $me->employee_id)->pluck('employee_id')->all();
    }
}
