<?php

namespace App\Support\V2\Grid\Hrm;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Services\Payroll\PayrollAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Grid Print & Send Payslips: semua slip periode yang sudah dihitung (dalam cakupan payroll user) dengan kanal Print dan WhatsApp serta waktu cetak / kirim; Open membuka form v2 hrm-payslip-form (cetak satu slip dan toggle kanal). Slip dibuat oleh perhitungan periode, tanpa + New Record.
 * Sumber hulu  : tm2pyr.pyr_payslip + pyr_payroll_run (nominal terenkripsi), tm2mst.emp_employee.
 * Input        : Pencarian, filter Period / Department, kotak status.
 * Output       : Baris grid (hanya baca; perubahan lewat form).
 * Dipakai oleh : Menu HRM > Payroll (v2:hrm-payslip), layar hrm-payslip-form (kembali ke daftar).
 * Status       : Aktif. Halaman v1 pages::hrm.payroll-payslip-list, print dan print-all sudah dipindah ke tmerp_unused (aturan D.25, T8 Payroll).
 */
class PayslipGrid extends HrmGrid
{
    private array $employees = [];

    public function slug(): string
    {
        return 'hrm-payslip';
    }

    public function title(): string
    {
        return t('Print & Send Payslips');
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Payroll';
    }

    protected function canCreate(): bool
    {
        return false;
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'hrm-payslip-form').'?id={id}';
    }

    protected function models(): array
    {
        return [PyrPayslip::class, PyrPayrollRun::class, EmpEmployee::class];
    }

    public function search(): string
    {
        return 'Search employee, period...';
    }

    protected function query(): Builder
    {
        return PyrPayslip::query()->with('run')
            ->whereHas('run', fn ($q) => $q->whereIn('run_status', ['CALCULATED', 'VERIFIED', 'CONFIRMED', 'PAID', 'CLOSED']))
            ->tap(fn ($q) => PayrollAccess::restrict($q, auth()->user()))
            ->orderByDesc('id_run')->orderBy('employee_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->employees = EmpEmployee::query()->with('position.department')->whereIn('employee_id', $items->pluck('employee_id')->unique())
            ->get()->keyBy('employee_id')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Employee No. / Name', 'type' => 'doc', 'party' => 'name'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'net', 'label' => 'Net Salary', 'type' => 'money'],
            ['key' => 'printed', 'label' => 'Print channel', 'type' => 'badge', 'tones' => [t('Printed') => 'success', t('Waiting') => 'warn', t('Off') => 'neutral']],
            ['key' => 'wa', 'label' => 'WhatsApp channel', 'type' => 'badge', 'tones' => [t('Sent') => 'success', t('Waiting') => 'warn', t('Off') => 'neutral']],
            ['key' => 'anomaly', 'label' => 'Anomaly', 'type' => 'badge', 'tones' => [t('Yes') => 'danger']],
        ];
    }

    protected function row(Model $m): array
    {
        $e = $this->employees[$m->employee_id] ?? null;
        $run = $m->run;

        return [
            'period' => $run ? $run->period_type.' '.tf(':from to :to', ['from' => substr((string) $run->period_start, 0, 10), 'to' => substr((string) $run->period_end, 0, 10)]) : null,
            'name' => $e?->full_name, 'code' => $e?->employee_code, 'dept' => $e?->position?->department?->department_name,
            'net' => (float) $m->total_gaji_bersih,
            // Kanal: Off = tidak dipilih, Waiting = dipilih tapi belum dicetak / dikirim, Printed / Sent = sudah (ada stempel waktunya).
            'printed' => t($m->print_flag ? ($m->printed_at ? 'Printed' : 'Waiting') : 'Off'), 'wa' => t($m->wa_flag ? ($m->wa_sent_at ? 'Sent' : 'Waiting') : 'Off'),
            'anomaly' => $m->has_anomaly ? t('Yes') : null,
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All slips'],
            ['label' => 'Waiting to print', 'where' => ['printed' => t('Waiting')], 'tone' => 'warn'],
            ['label' => 'WhatsApp waiting', 'where' => ['wa' => t('Waiting')], 'tone' => 'info'],
            ['label' => 'Anomaly', 'where' => ['anomaly' => t('Yes')], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['period' => 'Period', 'dept' => 'Department'];
    }
}
