<?php

namespace App\Http\Middleware;

use App\Services\Payroll\PayrollAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gerbang layar payroll di atas module:HRM (user 2026-10-07). `payroll:payslip` = slip gaji (semua peran yang punya cakupan),
// `payroll:process` = gaji pokok, batch run, rekening, transfer bank, cash roster (Direksi/GM dan HR Officer).
class EnsurePayrollAccess
{
    public function handle(Request $request, Closure $next, string $level = 'process'): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $ok = $level === 'payslip' ? PayrollAccess::canViewPayslips($user) : PayrollAccess::canProcess($user);
        abort_unless($ok, 403, 'Anda tidak punya akses ke payroll.');

        return $next($request);
    }
}
