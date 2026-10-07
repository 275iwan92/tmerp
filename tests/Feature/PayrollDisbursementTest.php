<?php

namespace Tests\Feature;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use App\Support\V2\Data\PayrollDisbursement;
use App\Support\V2\Print\PrintDocs;
use Tests\TestCase;

// Payroll Bank Transfer & Cash Roster v2 (C7). Memakai satu periode uji (tahun 2099, status CONFIRMED) dengan dua slip: satu pegawai berekening bank utama aktif,
// satu tanpa. Slip bernilai 1.234.000 dan 987.500; periode + slip dibersihkan lagi. Kolom gaji terenkripsi (PayrollEncrypted) dibaca lewat model.
class PayrollDisbursementTest extends TestCase
{
    private PyrPayrollRun $run;

    private int $bankedId;

    private int $cashId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail()); // payroll hanya terlihat untuk peran yang berhak (PayrollAccess)
        $banked = EmpBankAccount::where('is_primary', true)->where('activate', true)->first() ?? $this->markTestSkipped('No primary bank account in this database.');
        $this->bankedId = (int) $banked->employee_id;
        $cash = EmpEmployee::where('activate', true)->whereNotIn('employee_id', EmpBankAccount::where('is_primary', true)->where('activate', true)->pluck('employee_id'))->first()
            ?? $this->markTestSkipped('Every active employee has a bank account.');
        $this->cashId = (int) $cash->employee_id;

        $this->run = PyrPayrollRun::create(['period_type' => 'WEEKLY', 'period_start' => '2099-01-05', 'period_end' => '2099-01-11', 'run_status' => 'CONFIRMED', 'triggered_by' => 'test', 'triggered_at' => now()]);
        foreach ([[$this->bankedId, 1_234_000, false], [$this->cashId, 987_500, false]] as [$employeeId, $net, $anomaly]) {
            PyrPayslip::create(['id_run' => $this->run->id_run, 'employee_id' => $employeeId, 'total_pokok' => $net, 'total_gaji_bersih' => $net, 'has_anomaly' => $anomaly]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->run)) {
            PyrPayslip::where('id_run', $this->run->id_run)->delete();
            $this->run->delete();
        }
        parent::tearDown();
    }

    public function test_bank_rows_only_include_employees_with_a_primary_account(): void
    {
        $rows = PayrollDisbursement::bankRows($this->run->id_run);
        $this->assertCount(1, $rows);
        $this->assertSame(1_234_000.0, $rows[0]['net']);
        $this->assertNotSame('', $rows[0]['account_no']);
    }

    public function test_cash_rows_are_the_other_employees_with_an_exact_breakdown(): void
    {
        $rows = PayrollDisbursement::cashRows($this->run->id_run);
        $this->assertCount(1, $rows);
        $this->assertSame(987_500.0, $rows[0]['net']);
        // 987.500 = 9x100k + 50k + 20k + 10k + 5k + 2k; sisa Rp 500 tidak bisa dibayar dengan lembaran (pecahan terkecil 1k).
        $this->assertSame([100000 => 9, 50000 => 1, 20000 => 1, 10000 => 1, 5000 => 1, 2000 => 1], $rows[0]['breakdown']);
    }

    public function test_breakdown_uses_largest_notes_first_and_totals_add_up(): void
    {
        $this->assertSame([100000 => 2, 50000 => 1, 20000 => 1, 5000 => 1, 2000 => 1, 1000 => 1], PayrollDisbursement::breakdown(278_000));
        $this->assertSame([], PayrollDisbursement::breakdown(0));
        $totals = PayrollDisbursement::denominationTotals([['breakdown' => [100000 => 2, 50000 => 1]], ['breakdown' => [100000 => 1, 1000 => 4]]]);
        $this->assertSame(3, $totals[100000]);
        $this->assertSame(4, $totals[1000]);
        $this->assertSame(0, $totals[20000]);
        $this->assertSame('2x 100k, 1x 50k', PayrollDisbursement::breakdownText([100000 => 2, 50000 => 1]));
    }

    public function test_anomaly_slips_are_left_out(): void
    {
        PyrPayslip::where('id_run', $this->run->id_run)->where('employee_id', $this->cashId)->update(['has_anomaly' => true]);
        $this->assertSame([], PayrollDisbursement::cashRows($this->run->id_run));
    }

    public function test_only_confirmed_paid_or_closed_runs_are_available(): void
    {
        $this->assertContains($this->run->id_run, PayrollDisbursement::runs()->pluck('id_run')->all());
        $this->run->update(['run_status' => 'CALCULATED']);
        $this->assertNotContains($this->run->id_run, PayrollDisbursement::runs()->pluck('id_run')->all());
        $this->assertNull(PayrollDisbursement::run($this->run->id_run));
    }

    public function test_csv_has_the_v1_header_and_unformatted_amounts(): void
    {
        $csv = PayrollDisbursement::csvRows($this->run->id_run);
        $this->assertSame(['Kode Pegawai', 'Nama', 'Bank', 'No Rekening', 'Atas Nama', 'Nominal'], $csv[0]);
        $this->assertSame('1234000', $csv[1][5]);
    }

    public function test_screens_csv_and_print_render_for_an_hr_user(): void
    {
        $admin = SecUser::where('is_admin', true)->firstOrFail();
        $q = '?run='.$this->run->id_run;
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-bank-transfer').$q)->assertOk()->assertSee('1.234.000');
        $this->actingAs($admin)->get(route('v2.screen', 'hrm-cash-roster').$q)->assertOk()->assertSee('987.500');

        $csv = $this->actingAs($admin)->get(route('v2.payroll.bank-csv', $this->run->id_run));
        $csv->assertOk();
        $this->assertStringContainsString('Kode Pegawai', $csv->streamedContent());
        $this->assertStringContainsString('1234000', $csv->streamedContent());

        $this->actingAs($admin)->get(PrintDocs::url('cash-roster', $this->run->id_run))->assertOk()->assertSee('987.500');
        $this->actingAs($admin)->get(route('v2.payroll.bank-csv', 999999999))->assertNotFound();
    }
}
