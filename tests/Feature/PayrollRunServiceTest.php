<?php

/*
 * REMARK
 * Tujuan       : Menjaga siklus periode payroll v2: hanya user berhak payroll, periode divalidasi, langkah salah urutan ditolak sebagai pesan form (bukan error), Mark Payslips as Sent hanya sekali setelah PAID, Close membuat periode berikutnya, ringkasan Bank vs Cash, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Payroll\PayrollRunService, PayrollEngineService, layar hrm-payroll-run-form, routes/v2_hrm.php, PayrollRunGrid.
 * Input        : User johiwan (admin, Direksi); periode WEEKLY di tahun 2031 dan akun uji dibuat di dalam transaksi tm2pyr / tm2sys yang di-rollback, jadi data payroll nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=PayrollRunServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use App\Services\Payroll\PayrollAccess;
use App\Services\Payroll\PayrollRunService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollRunServiceTest extends TestCase
{
    private const CONNS = ['tm2pyr', 'tm2sys'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        PayrollAccess::forget();
    }

    protected function tearDown(): void
    {
        foreach (self::CONNS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
        PayrollAccess::forget();
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function in(array $over = []): array
    {
        return array_merge(['period_type' => 'WEEKLY', 'period_start' => '2031-01-06', 'period_end' => '2031-01-12'], $over);
    }

    private function refused(callable $f, string $key, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), $why);
        }
    }

    public function test_access_and_period_validation(): void
    {
        $svc = app(PayrollRunService::class);
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_run_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        PayrollAccess::forget();
        $before = PyrPayrollRun::count();

        try {
            $svc->calculate($this->in(), SecUser::findOrFail($id));
            $this->fail('tanpa hak payroll harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        foreach (['bad type' => ['period_type' => 'DAILY'], 'bad start' => ['period_start' => '06-01-2031'], 'end before start' => ['period_end' => '2031-01-01'], 'too long' => ['period_end' => '2031-04-01']] as $name => $over) {
            try {
                $svc->calculate($this->in($over), $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame($before, PyrPayrollRun::count(), 'penolakan tidak membuat periode');
    }

    public function test_cycle_order_summary_and_close(): void
    {
        $svc = app(PayrollRunService::class);
        $admin = $this->admin();

        $run = $svc->calculate($this->in(), $admin);
        $this->assertSame('CALCULATED', $run->fresh()->run_status);
        $this->assertSame('CALCULATED', $svc->calculate($this->in(), $admin)->fresh()->run_status, 'hitung ulang selama CALCULATED');
        $this->assertSame(1, PyrPayrollRun::where('period_type', 'WEEKLY')->where('period_start', '2031-01-06')->count());

        // Langkah salah urutan menjadi pesan form, bukan error.
        $this->refused(fn () => $svc->confirm($run, $admin), 'document', 'confirm sebelum VERIFIED');
        $this->refused(fn () => $svc->paid($run, $admin), 'document', 'paid sebelum CONFIRMED');
        $this->refused(fn () => $svc->sent($run, $admin), 'document', 'sent sebelum PAID');
        $this->refused(fn () => $svc->close($run, $admin), 'document', 'close sebelum PAID');
        $this->refused(fn () => $svc->verify($run, 'XX', $admin), 'method', 'metode tidak dikenal');

        $summary = PayrollRunService::summary($run->fresh(), $admin);
        $this->assertSame(PyrPayslip::where('id_run', $run->id_run)->count(), $summary['employees']);
        $this->assertSame($summary['bank_count'] + $summary['cash_count'] + $summary['anomalies'], $summary['employees']);

        $svc->verify($run, 'FULL', $admin);
        $this->assertSame('VERIFIED', $run->fresh()->run_status);
        $this->refused(fn () => $svc->calculate($this->in(), $admin), 'document', 'hitung ulang sesudah VERIFIED');

        // Confirm ditolak selama ada slip anomali; setelah anomali diselesaikan baru lolos.
        PyrPayslip::where('id_run', $run->id_run)->update(['has_anomaly' => true]);
        if (PyrPayslip::where('id_run', $run->id_run)->exists()) {
            $this->refused(fn () => $svc->confirm($run, $admin), 'document', 'masih ada anomali');
        }
        PyrPayslip::where('id_run', $run->id_run)->update(['has_anomaly' => false]);
        $svc->confirm($run, $admin);
        $svc->paid($run, $admin);
        $this->assertSame('PAID', $run->fresh()->run_status);
        $svc->sent($run, $admin);
        $this->assertNotNull($run->fresh()->payslip_sent_at);
        $this->refused(fn () => $svc->sent($run, $admin), 'document', 'sent dua kali');

        $next = $svc->close($run, $admin);
        $this->assertSame('CLOSED', $run->fresh()->run_status);
        $this->assertSame('DRAFT', $next->run_status);
        $this->assertSame('2031-01-13', $next->period_start->toDateString());
        $this->assertSame(['Closed', 'Payslips sent', 'Paid'], array_slice(array_column($svc->history($run->fresh()), 'action'), 0, 3));
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $form = route('v2.screen', 'hrm-payroll-run-form');
        $run = app(PayrollRunService::class)->calculate($this->in(['period_start' => '2031-02-03', 'period_end' => '2031-02-09']), $this->admin());
        $this->get(route('v2.screen', 'hrm-payroll-run'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?new=1')->assertOk()->assertSee('name="period_start"', false)->assertSee('name="action" value="calculate"', false);
        $open = $form.'?id='.$run->id_run;
        $this->get($open)->assertOk()->assertSee('name="action" value="verify"', false)->assertSee('name="method"', false);

        $save = route('v2.hrm.payrollrun.save');
        $this->post($save, ['action' => 'calculate'] + $this->in(['period_end' => '2020-01-01']))->assertSessionHasErrors('period_end');
        $this->post($save, ['action' => 'verify'])->assertStatus(422);
        $this->post($save, ['id' => $run->id_run, 'action' => 'bogus'])->assertStatus(422);
        $this->post($save, ['id' => $run->id_run, 'action' => 'confirm'])->assertSessionHasErrors('document');
        $this->post($save, ['id' => $run->id_run, 'action' => 'verify', 'method' => 'FULL'])->assertRedirect($open);
        $this->assertSame('VERIFIED', $run->fresh()->run_status);
        $this->get($open)->assertOk()->assertSee('name="action" value="confirm"', false);

        $grid = Grids::find('hrm-payroll-run');
        $this->assertStringContainsString('/v2/s/hrm-payroll-run-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-payroll-run-form?new=1', $grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/payroll-batch-run.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/payroll_batch_run_v1_2026-10-20.blade.php'));
        $this->assertArrayNotHasKey('HRM_PAYROLL_CALC', MenuRoutes::map());
        $this->assertFalse(PayrollAccess::screenAllowed('hrm-payroll-run-form', null));
        $this->get('/hrm/payroll/batch-run')->assertRedirect('/v2/s/hrm-payroll-run');
        $this->get('/hrm/payroll/batch-run?new=1')->assertRedirect('/v2/s/hrm-payroll-run-form?new=1');
        $this->get('/hrm/payroll/batch-run?open=7')->assertRedirect('/v2/s/hrm-payroll-run-form?id=7');
    }
}
