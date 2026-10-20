<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > HR & Payroll (v2): headcount, cuti, payroll terakhir (net, hanya yang berhak melihat semua payroll), pegawai per departemen, riwayat payroll run, cuti disetujui per jenis.
 * Sumber hulu  : emp_employee, org_position, org_department, emp_leave_request (tm2mst), pyr_payroll_run, pyr_payslip (tm2pyr, kolom net terenkripsi dijumlah di PHP), PayrollAccess lewat User::canViewPayrollAmounts; aturan sama dengan layar v1 pages::report.hr.
 * Input        : tidak ada (angka berjalan).
 * Output       : angka KPI, baris progress, baris tabel.
 * Dipakai oleh : layar v2 mgmt-hr.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpLeaveRequest;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Support\DataOrigin\Visible;
use Illuminate\Support\Facades\DB;

final class HrReport extends MgmtReport
{
    public function headcount(): array
    {
        return ['active' => EmpEmployee::where('activate', true)->where('work_status', 'WORKING')->count(),
            'cuti' => EmpEmployee::where('activate', true)->where('work_status', 'CUTI')->count(),
            'dirumahkan' => EmpEmployee::where('activate', true)->where('work_status', 'DIRUMAHKAN')->count(),
            'total' => EmpEmployee::where('activate', true)->count()];
    }

    /** @return list<array{name: string, n: int}> */
    public function byDepartment(): array
    {
        return Visible::apply(DB::connection('tm2mst')->table('emp_employee as e')->join('org_position as p', 'p.position_id', '=', 'e.position_id'), 'tm2mst', 'emp_employee', 'e')
            ->join('org_department as d', 'd.department_id', '=', 'p.department_id')->where('e.activate', true)
            ->selectRaw('d.department_name, COUNT(*) as n')->groupBy('d.department_name')->orderByDesc('n')->limit(8)->get()
            ->map(fn ($r) => ['name' => $r->department_name, 'n' => (int) $r->n])->all();
    }

    public function latestRun(): ?PyrPayrollRun
    {
        return PyrPayrollRun::orderByDesc('period_start')->first();
    }

    /** Total gaji seluruh pegawai: hanya yang melihat semua payroll (Direksi/GM, App\Services\Payroll\PayrollAccess). */
    public function canSeePayroll(): bool
    {
        return (bool) auth()->user()?->canViewPayrollAmounts();
    }

    /** total_gaji_bersih terenkripsi: dijumlah di PHP, satu run (ratusan payslip). */
    public function latestRunNet(): float
    {
        $run = $this->latestRun();

        return ! $run || ! $this->canSeePayroll() ? 0 : PyrPayslip::where('id_run', $run->id_run)->get()->sum(fn (PyrPayslip $p) => (float) $p->total_gaji_bersih);
    }

    public function pendingLeave(): int
    {
        return EmpLeaveRequest::where('status', 'PENDING')->count();
    }

    /** @return list<array{type: string, n: int}> */
    public function leaveByType(): array
    {
        return EmpLeaveRequest::where('status', 'APPROVED')->whereYear('date_from', now()->year)->selectRaw('leave_type, COUNT(*) as n')->groupBy('leave_type')->orderByDesc('n')->get()
            ->map(fn ($r) => ['type' => (string) $r->leave_type, 'n' => (int) $r->n])->all();
    }

    /** @return list<array<string, string>> baris riwayat payroll run */
    public function runRows(): array
    {
        return PyrPayrollRun::orderByDesc('period_start')->limit(6)->get()
            ->map(fn ($r) => ['period' => raw($r->period_start->format('M Y').' ('.$r->period_type.')'), 'status' => raw($r->run_status)])->all();
    }
}
