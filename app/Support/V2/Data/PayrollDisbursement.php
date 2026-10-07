<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use Devlib\Locale;
use Illuminate\Support\Collection;

// HRM > Payroll > Bank Transfer Generator dan Cash Roster & Denominations (v2): dua cara membayar slip gaji satu periode payroll.
//  - Transfer bank = pegawai dengan rekening bank utama aktif (emp_bank_account); batch dapat diunduh sebagai CSV generik
//    (kolom sama dengan layar v1; format kliring resmi tiap bank = butir terbuka).
//  - Tunai = pegawai TANPA rekening utama aktif; setiap amplop dirinci per pecahan uang, dan jumlah lembar per pecahan dijumlahkan.
// Hanya slip periode CONFIRMED / PAID / CLOSED dan tanpa anomali. Nilai gaji dibaca lewat model (kolom terenkripsi didekripsi PayrollEncrypted).
final class PayrollDisbursement
{
    public const DENOMINATIONS = [100000, 50000, 20000, 10000, 5000, 2000, 1000];

    public const RUN_STATUSES = ['CONFIRMED', 'PAID', 'CLOSED'];

    /** @return Collection<int, PyrPayrollRun> */
    public static function runs(): Collection
    {
        return PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->orderByDesc('period_start')->get();
    }

    public static function defaultRun(): ?int
    {
        return PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->orderByDesc('period_start')->value('id_run');
    }

    public static function run(?int $id): ?PyrPayrollRun
    {
        return $id ? PyrPayrollRun::whereIn('run_status', self::RUN_STATUSES)->find($id) : null;
    }

    /** @return array<int, string> id periode => "dd-Mmm-yyyy — dd-Mmm-yyyy" */
    public static function runOptions(): array
    {
        return self::runs()->mapWithKeys(fn (PyrPayrollRun $r) => [$r->id_run => Locale::d($r->period_start).' — '.Locale::d($r->period_end)])->all();
    }

    /** @return list<array{code: string, name: string, bank: string, account_no: string, holder: string, net: float}> */
    public static function bankRows(int $runId): array
    {
        $payslips = self::payslips($runId);
        $employees = EmpEmployee::whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');
        $banks = self::primaryAccounts($payslips->pluck('employee_id')->all())->keyBy('employee_id');

        return $payslips->filter(fn (PyrPayslip $p) => $banks->has($p->employee_id))->map(fn (PyrPayslip $p) => [
            'code' => (string) $employees->get($p->employee_id)?->employee_code, 'name' => (string) $employees->get($p->employee_id)?->full_name,
            'bank' => (string) $banks->get($p->employee_id)->bank_name, 'account_no' => (string) $banks->get($p->employee_id)->account_no,
            'holder' => (string) $banks->get($p->employee_id)->account_holder_name, 'net' => (float) $p->total_gaji_bersih,
        ])->sortBy('name')->values()->all();
    }

    /** @return list<array{code: string, name: string, dept: string, net: float, breakdown: array<int, int>}> */
    public static function cashRows(int $runId): array
    {
        $payslips = self::payslips($runId);
        $employees = EmpEmployee::with('position.department')->whereIn('employee_id', $payslips->pluck('employee_id'))->get()->keyBy('employee_id');
        $banked = self::primaryAccounts($payslips->pluck('employee_id')->all())->pluck('employee_id')->all();

        return $payslips->reject(fn (PyrPayslip $p) => in_array($p->employee_id, $banked, true))->map(fn (PyrPayslip $p) => [
            'code' => (string) $employees->get($p->employee_id)?->employee_code, 'name' => (string) $employees->get($p->employee_id)?->full_name,
            'dept' => (string) ($employees->get($p->employee_id)?->position?->department?->department_name ?? ''),
            'net' => (float) $p->total_gaji_bersih, 'breakdown' => self::breakdown((int) $p->total_gaji_bersih),
        ])->sortBy('name')->values()->all();
    }

    /** @return array<int, int> pecahan => jumlah lembar, pecahan terbesar dulu, hanya yang terpakai */
    public static function breakdown(int $amount): array
    {
        $out = [];
        foreach (self::DENOMINATIONS as $denom) {
            if (($count = intdiv($amount, $denom)) > 0) {
                $out[$denom] = $count;
                $amount -= $count * $denom;
            }
        }

        return $out;
    }

    /** @param list<array{breakdown: array<int, int>}> $rows @return array<int, int> pecahan => total lembar (semua pecahan, 0 bila tak terpakai) */
    public static function denominationTotals(array $rows): array
    {
        $totals = array_fill_keys(self::DENOMINATIONS, 0);
        foreach ($rows as $r) {
            foreach ($r['breakdown'] as $denom => $count) {
                $totals[$denom] += $count;
            }
        }

        return $totals;
    }

    /** "2x 100k, 1x 50k": teks rincian amplop. @param array<int, int> $breakdown */
    public static function breakdownText(array $breakdown): string
    {
        return collect($breakdown)->map(fn ($count, $denom) => $count.'x '.($denom / 1000).'k')->implode(', ');
    }

    /** Isi CSV batch transfer (sama dengan v1: header baku untuk bank / keuangan). @return list<list<string|float>> */
    public static function csvRows(int $runId): array
    {
        $rows = [['Kode Pegawai', 'Nama', 'Bank', 'No Rekening', 'Atas Nama', 'Nominal']];
        foreach (self::bankRows($runId) as $r) {
            $rows[] = [$r['code'], $r['name'], $r['bank'], $r['account_no'], $r['holder'], number_format($r['net'], 0, '', '')];
        }

        return $rows;
    }

    /** @return Collection<int, PyrPayslip> */
    private static function payslips(int $runId): Collection
    {
        abort_unless(self::run($runId), 404);

        return PyrPayslip::where('id_run', $runId)->where('has_anomaly', false)->get();
    }

    /** @param list<int> $employeeIds @return Collection<int, EmpBankAccount> */
    private static function primaryAccounts(array $employeeIds): Collection
    {
        return EmpBankAccount::whereIn('employee_id', $employeeIds)->where('is_primary', true)->where('activate', true)->get();
    }
}
