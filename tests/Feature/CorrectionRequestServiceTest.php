<?php

/*
 * REMARK
 * Tujuan       : Menjaga keputusan HR atas pengajuan koreksi data pegawai v2: Approve menerapkan kolom terpetakan atau hanya menutup field lain, Reject wajib alasan (maks 255) dan mengirim notifikasi, hanya PENDING yang bisa diputuskan, pengajuan rekening gaji disamarkan dan hanya diputuskan yang berhak payroll atas pegawai itu, layar form, route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Hrm\CorrectionRequestService, layar hrm-correction-form, routes/v2_hrm.php, CorrectionRequestGrid, PayrollAccess.
 * Input        : User johiwan (Direksi) dan akun uji sementara (HR Officer, admin tanpa payroll) di tm2sys yang dihapus di tearDown; pegawai uji dan pengajuan dibuat di dalam transaksi tm2mst yang di-rollback; satu notifikasi tm2sys yang dihapus per id. Dua pengajuan nyata (employee_id 1) tidak disentuh.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=CorrectionRequestServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Master\EmpBankAccount;
use App\Models\Master\EmpCorrectionRequest;
use App\Models\Master\EmpEmployee;
use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;
use App\Services\Hrm\CorrectionRequestService;
use App\Services\Payroll\PayrollAccess;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CorrectionRequestServiceTest extends TestCase
{
    private const PREFIX = 'tst_corr_';

    private int $company;

    private array $notifications = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('tm2mst')->beginTransaction();
        $this->company = (int) DB::connection('tm2mst')->table('org_company')->value('company_id');
    }

    protected function tearDown(): void
    {
        while (DB::connection('tm2mst')->transactionLevel() > 0) {
            DB::connection('tm2mst')->rollBack();
        }
        SysNotification::whereIn('id', $this->notifications)->delete();
        $ids = DB::connection('tm2sys')->table('sec_user')->where('login', 'like', self::PREFIX.'%')->pluck('id_user');
        DB::connection('tm2sys')->table('sec_user_group')->whereIn('user_id', $ids)->delete();
        DB::connection('tm2sys')->table('sec_user')->whereIn('id_user', $ids)->delete();
        PayrollAccess::forget();
        parent::tearDown();
    }

    private function direksi(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function user(string $key, array $groups = [], bool $admin = false): SecUser
    {
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => self::PREFIX.$key, 'name' => 'Test '.$key, 'active' => 1, 'activate' => 1, 'is_admin' => $admin ? 1 : 0, 'employee_id' => null]);
        foreach ($groups as $code) {
            DB::connection('tm2sys')->table('sec_user_group')->insert(['user_id' => $id, 'group_id' => DB::connection('tm2sys')->table('sec_group')->where('group_code', $code)->value('id_group')]);
        }
        PayrollAccess::forget();

        return SecUser::findOrFail($id);
    }

    private function employee(string $type = 'NON_STAFF'): EmpEmployee
    {
        return EmpEmployee::create(['employee_code' => 'ZZ-C'.substr(md5(microtime().$type), 0, 8), 'company_id' => $this->company, 'full_name' => 'ZZ Correction', 'staff_type' => $type, 'payment_frequency' => 'MONTHLY', 'phone' => '0800-OLD', 'created_by' => 'test', 'updated_by' => 'test']);
    }

    private function request(EmpEmployee $e, string $field = 'Telepon / WA', string $new = '0811-NEW', ?string $old = '0800-OLD'): EmpCorrectionRequest
    {
        return EmpCorrectionRequest::create(['employee_id' => $e->employee_id, 'field_name' => $field, 'old_value' => $old, 'new_value' => $new, 'reason' => 'Nomor lama tidak aktif', 'requires_hr_approval' => true, 'status' => 'PENDING', 'requested_at' => now()]);
    }

    private function bankRequest(EmpEmployee $e): EmpCorrectionRequest
    {
        $bank = (int) DB::connection('tm2mst')->table('sup_supplier')->where('category', 'BANK')->where('activate', 1)->orderBy('id_supplier')->value('id_supplier');

        return $this->request($e, EmpCorrectionRequest::FIELD_BANK, json_encode(['bank_supplier_id' => $bank, 'account_no' => '999000', 'account_holder_name' => 'ZZ BARU']), 'BCA · 111');
    }

    public function test_approve_applies_mapped_columns_and_only_closes_other_fields(): void
    {
        $svc = app(CorrectionRequestService::class);
        $e = $this->employee();
        $r = $this->request($e);
        $svc->approve($r, $this->direksi());
        $this->assertSame('0811-NEW', $e->fresh()->phone);
        $r->refresh();
        $this->assertSame('APPROVED', $r->status);
        $this->assertSame('johiwan', $r->reviewed_by);
        $this->assertNotNull($r->reviewed_at);
        $this->assertSame('Approved', $svc->history($r)[0]['action']);

        $other = $this->request($e, 'BPJS Kesehatan', '0001234567', null);
        $svc->approve($other, $this->direksi());
        $this->assertSame('APPROVED', $other->fresh()->status);
        $this->assertSame('0811-NEW', $e->fresh()->phone, 'field tanpa kolom otomatis tidak mengubah pegawai');

        foreach ([fn () => $svc->approve($r, $this->direksi()), fn () => $svc->reject($r, 'late', $this->direksi())] as $i => $again) {
            try {
                $again();
                $this->fail('sudah diputuskan harus ditolak: '.$i);
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('document', $ex->errors());
            }
        }
    }

    public function test_reject_needs_a_reason_and_notifies_the_employee(): void
    {
        $svc = app(CorrectionRequestService::class);
        $owner = SecUser::whereNotNull('employee_id')->where('employee_id', '>', 1)->firstOrFail();
        $e = EmpEmployee::findOrFail($owner->employee_id);
        $r = $this->request($e, 'Alamat Domisili', 'Jl. Baru 2', 'Jl. Lama 1');
        foreach (['' => 'blank', '   ' => 'spaces', str_repeat('r', 256) => 'too long'] as $reason => $name) {
            try {
                $svc->reject($r, (string) $reason, $this->direksi());
                $this->fail('alasan salah harus ditolak: '.$name);
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('document', $ex->errors(), $name);
            }
        }
        $this->assertSame('PENDING', $r->fresh()->status);
        $before = SysNotification::where('user_id', $owner->login)->count();
        $svc->reject($r, '  Bukti alamat belum ada  ', $this->direksi());
        $r->refresh();
        $this->assertSame('REJECTED', $r->status);
        $this->assertSame('Bukti alamat belum ada', $r->review_note);
        $this->assertSame($before + 1, SysNotification::where('user_id', $owner->login)->count());
        $n = SysNotification::where('user_id', $owner->login)->orderByDesc('id')->first();
        $this->notifications[] = $n->id;
        $this->assertStringContainsString('Bukti alamat belum ada', $n->message);
        $this->assertNotSame('Jl. Baru 2', $e->fresh()->address_detail_domisili);
    }

    public function test_bank_account_requests_follow_the_payroll_scope(): void
    {
        $svc = app(CorrectionRequestService::class);
        $hr = $this->user('hr', ['HR_OFFICER']);
        $admin = $this->user('admin', [], true);
        $staff = $this->employee('STAFF');
        $non = $this->employee('NON_STAFF');

        $this->assertSame(['old' => 'Payroll bank account (confidential)', 'new' => 'Payroll bank account (confidential)'], CorrectionRequestService::values($this->bankRequest($staff), $hr));
        $this->assertStringContainsString('999000', CorrectionRequestService::values($this->bankRequest($staff), $this->direksi())['new']);
        $this->assertSame('Payroll bank account (confidential)', CorrectionRequestService::values($this->bankRequest($non), $admin)['new']);
        $this->assertSame('0811-NEW', CorrectionRequestService::values($this->request($staff), $admin)['new'], 'field biasa tidak disamarkan');

        foreach ([[$hr, $staff], [$admin, $non]] as $i => [$user, $emp]) {
            $r = $this->bankRequest($emp);
            foreach ([fn () => $svc->approve($r, $user), fn () => $svc->reject($r, 'no', $user)] as $j => $try) {
                try {
                    $try();
                    $this->fail("tanpa hak payroll harus ditolak: $i/$j");
                } catch (ValidationException $ex) {
                    $this->assertArrayHasKey('document', $ex->errors());
                }
            }
            $this->assertSame('PENDING', $r->fresh()->status);
            $this->assertSame(0, EmpBankAccount::where('employee_id', $emp->employee_id)->count());
        }

        $ok = $this->bankRequest($non);
        $svc->approve($ok, $hr);
        $this->assertSame('999000', EmpBankAccount::where('employee_id', $non->employee_id)->where('activate', true)->value('account_no'));
        $svc->approve($r = $this->bankRequest($staff), $this->direksi());
        $this->assertSame('APPROVED', $r->fresh()->status);
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->direksi());
        $e = $this->employee();
        $r = $this->request($e);
        $form = route('v2.screen', 'hrm-correction-form');
        $this->get(route('v2.screen', 'hrm-correction'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$r->request_id)->assertOk()->assertSee('Employee Data Correction')->assertSee('0811-NEW')->assertSee('Approve')->assertSee('Reject');

        $this->post(route('v2.hrm.correction.decide'), ['id' => $r->request_id, 'action' => 'reject', '_reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.correction.decide'), ['id' => $r->request_id, 'action' => 'hold'])->assertStatus(422);
        $this->post(route('v2.hrm.correction.decide'), ['id' => $r->request_id, 'action' => 'approve'])->assertRedirect($form.'?id='.$r->request_id);
        $this->assertSame('0811-NEW', $e->fresh()->phone);
        $this->post(route('v2.hrm.correction.decide'), ['id' => $r->request_id, 'action' => 'approve'])->assertSessionHasErrors('document');

        $bank = $this->bankRequest($e);
        $this->actingAs($this->user('adm', [], true));
        $this->get($form.'?id='.$bank->request_id)->assertOk()->assertSee('Payroll bank account (confidential)')->assertDontSee('999000');
        $this->post(route('v2.hrm.correction.decide'), ['id' => $bank->request_id, 'action' => 'approve'])->assertSessionHasErrors('document');

        $grid = Grids::find('hrm-correction');
        $this->assertStringContainsString('/v2/s/hrm-correction-form?id={id}', $grid->openUrl());
        $this->assertNull($grid->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/correction-requests.blade.php'));
        $this->assertArchived('hrm_correction_requests_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_CORRECTION', MenuRoutes::map());
        $this->get('/hrm/correction-requests')->assertRedirect('/v2/s/hrm-correction');
        $this->get('/hrm/correction-requests?open=5')->assertRedirect('/v2/s/hrm-correction-form?id=5');
    }
}
