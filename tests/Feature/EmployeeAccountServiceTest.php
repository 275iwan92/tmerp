<?php

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Master\EmpBpjsKesehatan;
use App\Models\Master\EmpBpjsKetenagakerjaan;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeAccountService;
use App\Services\Hrm\EmployeeProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Tab Account (hanya Admin App) dan tab BPJS layar v2 Master Employee Form. Semua di transaksi lalu di-rollback.
class EmployeeAccountServiceTest extends TestCase
{
    private function inTransaction(callable $fn): void
    {
        DB::connection('tm2mst')->beginTransaction();
        DB::connection('tm2sys')->beginTransaction();
        try {
            $admin = SecUser::query()->where('is_admin', true)->where('active', true)->firstOrFail();
            [$e] = app(EmployeeProfileService::class)->save(null, ['full_name' => 'Zz Test Akun', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY',
                'position_id' => DB::connection('tm2mst')->table('org_position')->where('activate', true)->value('position_id')], $admin);
            $fn($e, $admin);
        } finally {
            DB::connection('tm2sys')->rollBack();
            DB::connection('tm2mst')->rollBack();
        }
    }

    public function test_account_reset_toggle_and_self_lock(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin) {
            $svc = app(EmployeeAccountService::class);
            $user = $svc->userOf($e);
            $this->assertNotNull($user, 'login dibuat otomatis untuk pegawai baru (mode longgar)');
            $this->assertNull($svc->createLogin($e, $admin), 'createLogin idempotent: sudah punya login');

            $old = $user->pswd;
            $card = $svc->resetPassword($user);
            $this->assertNotEmpty($card['password']);
            $this->assertNotSame($old, $user->fresh()->pswd);
            $this->assertTrue((bool) $user->fresh()->must_change_password);

            $svc->setActive($user, false, $admin);
            $this->assertFalse((bool) $user->fresh()->activate);
            $svc->setActive($user, true, $admin);
            $this->assertTrue((bool) $user->fresh()->activate);

            $this->expectException(ValidationException::class);
            $svc->setActive($admin, false, $admin);
        });
    }

    public function test_account_tab_and_routes_are_admin_only(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin) {
            $url = route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id;
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-tab="account"', false)->assertSee('data-tab="documents"', false)->assertSee('data-tab="bpjs"', false);

            $other = SecUser::query()->where('is_admin', false)->where('active', true)->where('activate', true)->get()->first(fn (SecUser $u) => $u->canAccessModule('HRM'));
            if (! $other) {
                $this->markTestSkipped('Tidak ada akun non-admin dengan akses HRM.');
            }
            $this->withoutMiddleware(ForcePasswordChange::class);
            $this->actingAs($other)->get($url)->assertOk()->assertDontSee('data-tab="account"', false);
            $this->actingAs($other)->post(route('v2.hrm.employee.account', [$e->employee_id, 'reset']))->assertForbidden();

            $r = $this->actingAs($admin)->post(route('v2.hrm.employee.account', [$e->employee_id, 'reset']));
            $r->assertRedirect($url.'&tab=account');
            $this->assertNotEmpty(session('v2_login_card')['password'] ?? null);
            $this->assertSame('New password', session('v2_login_card')['title']);
        });
    }

    public function test_bpjs_is_saved_with_the_main_save_and_empty_rows_are_not_created(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $admin) {
            $svc = app(EmployeeProfileService::class);
            $base = ['full_name' => 'Zz Test Akun', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY', 'bpjs_present' => '1'];

            $svc->save($e, $base + ['bpjs_jkk' => '1', 'bpjs_jkm' => '1', 'bpjs_jht' => '1'], $admin);
            $this->assertSame(0, EmpBpjsKesehatan::where('employee_id', $e->employee_id)->count(), 'tanpa isian tidak ada baris');
            $this->assertSame(0, EmpBpjsKetenagakerjaan::where('employee_id', $e->employee_id)->count());

            $svc->save($e, $base + ['bpjs_kes_no' => '0001', 'bpjs_kes_class' => 'II', 'bpjs_kes_dependents' => '3', 'bpjs_ket_no' => '999', 'bpjs_ket_status' => 'TERDAFTAR',
                'bpjs_ket_category' => 'OPERATOR', 'bpjs_jkk' => '1', 'bpjs_jht' => '1'], $admin);
            $kes = EmpBpjsKesehatan::where('employee_id', $e->employee_id)->firstOrFail();
            $this->assertSame('II', $kes->kelas_rawat);
            $this->assertSame(3, $kes->dependents_count);
            $ket = EmpBpjsKetenagakerjaan::where('employee_id', $e->employee_id)->firstOrFail();
            $this->assertSame('TERDAFTAR', $ket->enrollment_status);
            $this->assertTrue($ket->program_jkk && $ket->program_jht);
            $this->assertFalse($ket->program_jkm || $ket->program_jp || $ket->program_jkp, 'program tanpa centang = mati');

            $this->expectException(ValidationException::class);
            $svc->save($e, $base + ['bpjs_kes_class' => 'IV'], $admin);
        });
    }
}
