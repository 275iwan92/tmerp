<?php

/*
 * REMARK
 * Tujuan       : Menjaga slip gaji v2: kanal Print / WhatsApp hanya untuk slip dalam cakupan user dan bukan anomali (WhatsApp butuh nomor), stempel cetak, cetak massal hanya slip ber-kanal Print tanpa anomali, data lembar slip, layar form, cetakan payslip lewat route v2.print, halaman cetak massal, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Payroll\PayslipService, App\Support\V2\Print\PayslipPrint, layar hrm-payslip-form, routes/v2_hrm.php, PayslipGrid.
 * Input        : User johiwan (admin, Direksi); periode, slip dan akun uji dibuat di dalam transaksi tm2pyr / tm2sys / tm2mst yang di-rollback.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=PayslipServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Pyr\PyrPayrollRun;
use App\Models\Pyr\PyrPayslip;
use App\Models\Sys\SecUser;
use App\Services\Payroll\PayrollAccess;
use App\Services\Payroll\PayslipService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayslipServiceTest extends TestCase
{
    private const CONNS = ['tm2pyr', 'tm2sys', 'tm2mst'];

    private PyrPayrollRun $run;

    private EmpEmployee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        PayrollAccess::forget();
        $this->emp = EmpEmployee::where('activate', true)->whereNotNull('phone')->orderBy('employee_id')->firstOrFail();
        $this->run = PyrPayrollRun::create(['period_type' => 'WEEKLY', 'period_start' => '2032-01-05', 'period_end' => '2032-01-11', 'run_status' => 'CALCULATED', 'triggered_by' => 'tst', 'triggered_at' => now()]);
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

    private function slip(bool $anomaly = false, bool $print = false, ?EmpEmployee $e = null): PyrPayslip
    {
        return PyrPayslip::create(['id_run' => $this->run->id_run, 'employee_id' => ($e ?? $this->emp)->employee_id, 'total_pokok' => 3000000, 'total_tj_keahlian' => 250000, 'total_tj_loyalitas' => 0, 'total_tj_prestasi' => 0,
            'total_bonus_pagi' => 10000, 'total_bonus_hadir' => 15000, 'total_uang_roti' => 0, 'total_overtime_pay' => 100000, 'total_denda' => 20000, 'total_bpjs_employee' => 90000, 'total_pph21' => 0,
            'total_gaji_bersih' => 3265000, 'has_anomaly' => $anomaly, 'print_flag' => $print, 'wa_flag' => false]);
    }

    private function refused(callable $f, string $why): void
    {
        try {
            $f();
            $this->fail('harus ditolak: '.$why);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors(), $why);
        }
    }

    public function test_channels_stamp_and_sheet(): void
    {
        $svc = app(PayslipService::class);
        $admin = $this->admin();
        $p = $this->slip();

        $svc->toggleChannel($p, 'print', $admin);
        $this->assertTrue($p->fresh()->print_flag);
        $svc->toggleChannel($p, 'print', $admin);
        $this->assertFalse($p->fresh()->print_flag, 'toggle kedua mematikan');
        $svc->toggleChannel($p, 'wa', $admin);
        $this->assertTrue($p->fresh()->wa_flag);
        $this->refused(fn () => $svc->toggleChannel($p, 'sms', $admin), 'kanal tidak dikenal');

        $anomaly = $this->slip(true, false, EmpEmployee::where('activate', true)->where('employee_id', '!=', $this->emp->employee_id)->orderBy('employee_id')->firstOrFail());
        $this->refused(fn () => $svc->toggleChannel($anomaly, 'print', $admin), 'slip anomali terkunci');

        $noPhone = EmpEmployee::where('activate', true)->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))->orderBy('employee_id')->first();
        if ($noPhone) {
            $s = $this->slip(false, false, $noPhone);
            $this->refused(fn () => $svc->toggleChannel($s, 'wa', $admin), 'WhatsApp tanpa nomor');
            $svc->toggleChannel($s, 'print', $admin);
            $this->assertTrue($s->fresh()->print_flag, 'Print tidak butuh nomor');
        }

        $this->assertNull($p->fresh()->printed_at);
        $svc->stampPrinted($p->fresh());
        $first = $p->fresh()->printed_at;
        $this->assertNotNull($first);
        $svc->stampPrinted($p->fresh());
        $this->assertTrue($first->equalTo($p->fresh()->printed_at), 'stempel pertama dipertahankan');

        $sheet = PayslipService::sheet($p->fresh()->load('run'));
        $this->assertSame(3000000.0, $sheet['earnings']['Base salary']);
        $this->assertSame(25000.0, $sheet['earnings']['Morning + attendance bonus']);
        $this->assertSame(110000.0, array_sum($sheet['deductions']) + 0.0);
        $this->assertSame(3265000.0, $sheet['net']);
        $this->assertSame(['present', 'late', 'absent'], array_keys($sheet['attendance']));

        // Pengguna tanpa hak payroll ditolak.
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_slip_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 0]);
        PayrollAccess::forget();
        try {
            $svc->toggleChannel($p, 'print', SecUser::findOrFail($id));
            $this->fail('tanpa hak payroll harus ditolak');
        } catch (AuthorizationException) {
            $this->assertFalse(PayslipService::can(SecUser::findOrFail($id), $p));
        }
    }

    public function test_bulk_print_selection_and_mark_printed(): void
    {
        $svc = app(PayslipService::class);
        $admin = $this->admin();
        $other = EmpEmployee::where('activate', true)->where('employee_id', '!=', $this->emp->employee_id)->orderBy('employee_id')->firstOrFail();
        $on = $this->slip(false, true);
        $anomalyOn = $this->slip(true, true, $other);
        $this->assertSame([$on->id_payslip], PayslipService::printable($this->run, $admin)->pluck('id_payslip')->all(), 'hanya print_flag tanpa anomali');
        $this->assertSame(1, $svc->markRunPrinted($this->run, $admin));
        $this->assertNotNull($on->fresh()->printed_at);
        $this->assertNull($anomalyOn->fresh()->printed_at);
    }

    public function test_screen_print_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $p = $this->slip(false, true);
        $form = route('v2.screen', 'hrm-payslip-form');
        $this->get(route('v2.screen', 'hrm-payslip'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$p->id_payslip)->assertOk()->assertSee('Print Payslip')->assertSee('name="channel" value="wa"', false)->assertSee('hrm-payslip-form', false);

        $this->post(route('v2.hrm.payslip.channel'), ['id' => $p->id_payslip, 'channel' => 'wa'])->assertRedirect($form.'?id='.$p->id_payslip);
        $this->assertTrue($p->fresh()->wa_flag);
        $this->post(route('v2.hrm.payslip.channel'), ['id' => $p->id_payslip, 'channel' => 'zz'])->assertSessionHasErrors('document');

        $this->assertNull($p->fresh()->printed_at);
        $this->get(route('v2.print', ['type' => 'payslip', 'id' => $p->id_payslip]))->assertOk()->assertSee('Base salary')->assertSee('3,265,000.00');
        $this->assertNotNull($p->fresh()->printed_at, 'membuka cetakan satu slip menstempel');

        $q = $this->slip(false, true, EmpEmployee::where('activate', true)->where('employee_id', '!=', $this->emp->employee_id)->orderBy('employee_id')->firstOrFail());
        $this->get(route('v2.hrm.payslip.printall', $this->run->id_run))->assertOk()->assertSee('Mark as printed');
        $this->assertNull($q->fresh()->printed_at, 'cetak massal tidak menstempel sebelum Mark as printed');
        $this->post(route('v2.hrm.payslip.printed', $this->run->id_run))->assertRedirect(route('v2.screen', 'hrm-payslip'));
        $this->assertNotNull($q->fresh()->printed_at);
        $this->get(route('v2.hrm.payslip.printall', 999999999))->assertNotFound();

        $grid = Grids::find('hrm-payslip');
        $this->assertStringContainsString('/v2/s/hrm-payslip-form?id={id}', $grid->openUrl());
        foreach (['list', 'print', 'print-all'] as $f) {
            $this->assertFileDoesNotExist(resource_path('views/pages/hrm/payroll-payslip-'.$f.'.blade.php'));
            $this->assertFileExists(base_path('tmerp_unused/payroll_payslip_'.str_replace('-', '_', $f).'_v1_2026-10-20.blade.php'));
        }
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/_payslip-sheet.blade.php'));
        $this->assertArrayNotHasKey('HRM_PAYROLL_PAYSLIP', MenuRoutes::map());
        $this->get('/hrm/payroll/payslip')->assertRedirect('/v2/s/hrm-payslip');
        $this->get('/hrm/payroll/payslip/7/print')->assertRedirect('/v2/print/payslip/7');
        $this->get('/hrm/payroll/payslip/print-all/7')->assertRedirect('/v2/hrm/payslip/print-all/7');
    }
}
