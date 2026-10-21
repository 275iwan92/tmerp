<?php

/*
 * REMARK
 * Tujuan       : Menjaga provisioning pegawai baru v2: lamaran ACCEPTED menjadi pegawai + kontrak + salinan dokumen + login dalam satu langkah (lamaran HIRED), hanya user berhak payroll, posisi harus aktif, tidak bisa ganda, ditolak bila NIK dipakai pegawai WORKING, layar form (tanpa nominal gaji), route, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Recruitment\HireProvisionService, layar hrm-rcr-provision-form, routes/v2_hrm.php, ProvisionGrid.
 * Input        : User johiwan (admin); lamaran, penawaran, pegawai, kontrak, dokumen dan akun uji dibuat di dalam transaksi tm2hrr / tm2mst / tm2sys yang di-rollback, jadi data nyata tidak berubah.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=HireProvisionServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppOffer;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\CndDocument;
use App\Models\Hrr\VacVacancy;
use App\Models\Master\EmpContract;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEmployee;
use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use App\Services\Payroll\PayrollAccess;
use App\Services\Recruitment\HireProvisionService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Grid\Grids;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HireProvisionServiceTest extends TestCase
{
    private const CONNS = ['tm2hrr', 'tm2mst', 'tm2sys'];

    private int $position;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNS as $c) {
            DB::connection($c)->beginTransaction();
        }
        PayrollAccess::forget();
        $this->position = (int) OrgPosition::where('activate', true)->orderBy('position_id')->value('position_id');
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

    private function plain(): SecUser
    {
        $id = DB::connection('tm2sys')->table('sec_user')->insertGetId(['login' => 'tst_prov_plain', 'name' => 'Test plain', 'active' => 1, 'activate' => 1, 'is_admin' => 1]);
        PayrollAccess::forget();

        return SecUser::findOrFail($id);
    }

    /** Lamaran uji dengan kandidat baru ber-NIK unik dan penawaran ACCEPTED. */
    private function ready(string $nik = '9990000000000001', string $offerStatus = 'ACCEPTED'): AppApplication
    {
        $cand = CndCandidate::create(['name' => 'Zz Prov Candidate', 'national_id' => $nik, 'phone' => '0800000000', 'email' => 'zz.prov@example.test', 'birth_date' => '1995-05-05', 'address' => 'Jl Uji 1']);
        CndDocument::create(['id_candidate' => $cand->id_candidate, 'doc_type' => 'KTP', 'file_path' => 'zz/ktp.pdf', 'uploaded_at' => now()]);
        CndDocument::create(['id_candidate' => $cand->id_candidate, 'doc_type' => 'CV', 'file_path' => 'zz/cv.pdf', 'uploaded_at' => now()]);
        $app = AppApplication::create(['id_candidate' => $cand->id_candidate, 'id_vacancy' => VacVacancy::orderBy('id_vacancy')->value('id_vacancy'), 'current_stage_seq' => 5, 'status' => 'OFFERING', 'applied_at' => now()]);
        AppOffer::create(['id_application' => $app->id_application, 'proposed_salary' => 4500000, 'employment_type' => 'KONTRAK', 'start_date' => '2026-11-02', 'status' => $offerStatus, 'created_by' => 1]);

        return $app;
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

    public function test_provision_creates_employee_contract_documents_login_and_hires(): void
    {
        $svc = app(HireProvisionService::class);
        $admin = $this->admin();
        $this->assertTrue(PayrollAccess::canProcess($admin), 'admin uji berhak payroll (Direksi)');
        $app = $this->ready();
        $this->assertTrue(HireProvisionService::ready($app));

        [$e, $cred] = $svc->provision($app, $this->position, $admin);

        $this->assertMatchesRegularExpression('/^TM-\d{5}$/', $e->employee_code);
        $this->assertSame('Zz Prov Candidate', $e->full_name);
        $this->assertSame('9990000000000001', $e->nik_ktp);
        $this->assertSame($this->position, (int) $e->position_id);
        $this->assertSame('WORKING', $e->work_status);
        $this->assertSame('2026-11-02', $e->join_date->toDateString());
        $c = EmpContract::where('employee_id', $e->employee_id)->firstOrFail();
        $this->assertSame('CTR-'.$e->employee_code, $c->contract_no);
        $this->assertSame('KONTRAK', $c->employment_type);
        $this->assertSame('PER_MONTH', $c->salary_type);
        $this->assertSame(4500000.0, (float) $c->salary_amount);
        $docs = EmpDocument::where('employee_id', $e->employee_id)->orderBy('document_type')->get();
        $this->assertSame([['KTP', 'HR'], ['LAINNYA', 'HR']], $docs->map(fn ($d) => [$d->document_type, $d->uploaded_by_role])->all(), 'KTP tetap, CV dipetakan ke LAINNYA');
        $this->assertNotNull($cred);
        $this->assertTrue(SecUser::where('employee_id', $e->employee_id)->where('login', $cred['login'])->exists());
        $app->refresh();
        $this->assertSame('HIRED', $app->status);
        $this->assertSame((int) $e->employee_id, (int) $app->provisioned_employee_id);
        $this->assertNotNull($app->provisioned_at);
        $this->assertFalse(HireProvisionService::ready($app));
        $this->assertSame('Provisioned', $svc->history($app)[0]['action']);

        $this->refused(fn () => $svc->provision($app, $this->position, $admin), 'document', 'sudah diprovision');
        $this->assertSame(1, EmpEmployee::where('nik_ktp', '9990000000000001')->count());
    }

    public function test_refusals_write_nothing(): void
    {
        $svc = app(HireProvisionService::class);
        $admin = $this->admin();
        $employees = EmpEmployee::count();
        $users = SecUser::count();

        $app = $this->ready();
        try {
            $svc->provision($app, $this->position, $this->plain());
            $this->fail('user tanpa hak payroll harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->refused(fn () => $svc->provision($app, 999999999, $admin), 'position_id', 'posisi tidak ada');
        $inactive = OrgPosition::where('activate', false)->value('position_id');
        if ($inactive) {
            $this->refused(fn () => $svc->provision($app, (int) $inactive, $admin), 'position_id', 'posisi nonaktif');
        }
        $noOffer = $this->ready('9990000000000002', 'REJECTED');
        $this->assertFalse(HireProvisionService::ready($noOffer));
        $this->refused(fn () => $svc->provision($noOffer, $this->position, $admin), 'document', 'tanpa penawaran ACCEPTED');

        $existing = EmpEmployee::where('work_status', 'WORKING')->whereNotNull('nik_ktp')->firstOrFail();
        $dup = $this->ready($existing->nik_ktp);
        $this->refused(fn () => $svc->provision($dup, $this->position, $admin), 'document', 'NIK sudah dipakai pegawai WORKING');

        $this->assertSame($employees, EmpEmployee::count());
        $this->assertSame($users + 1, SecUser::count(), 'hanya akun uji non-payroll yang dibuat tes');
        $this->assertNull($app->fresh()->provisioned_employee_id);
    }

    public function test_screen_route_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        $app = $this->ready();
        $form = route('v2.screen', 'hrm-rcr-provision-form');
        $this->get(route('v2.screen', 'hrm-rcr-provision'))->assertOk();
        $this->get($form)->assertNotFound();
        $this->get($form.'?id=999999999')->assertNotFound();
        $this->get($form.'?id='.$app->id_application)->assertOk()->assertSee('name="position_id"', false)->assertSee('Provision to ERP')->assertDontSee('4500000')->assertDontSee('4.500.000')->assertDontSee('4,500,000');

        $this->post(route('v2.hrm.provision.save'), ['id' => $app->id_application, 'position_id' => ''])->assertSessionHasErrors('position_id');
        $this->assertNull($app->fresh()->provisioned_employee_id);
        $resp = $this->post(route('v2.hrm.provision.save'), ['id' => $app->id_application, 'position_id' => $this->position]);
        $emp = EmpEmployee::where('nik_ktp', '9990000000000001')->firstOrFail();
        $resp->assertRedirect(route('v2.screen', 'hrm-employee-form').'?id='.$emp->employee_id)->assertSessionHas('v2_login_card');
        $this->get($form.'?id='.$app->id_application)->assertOk()->assertDontSee('name="position_id"', false)->assertSee($emp->employee_code);
        $this->post(route('v2.hrm.provision.save'), ['id' => $app->id_application, 'position_id' => $this->position])->assertSessionHasErrors('document');

        $this->actingAs($this->plain());
        $other = $this->ready('9990000000000003');
        $this->get($form.'?id='.$other->id_application)->assertOk()->assertDontSee('name="position_id"', false);
        $this->post(route('v2.hrm.provision.save'), ['id' => $other->id_application, 'position_id' => $this->position])->assertForbidden();
        $this->assertNull($other->fresh()->provisioned_employee_id);

        $this->assertStringContainsString('/v2/s/hrm-rcr-provision-form?id={id}', Grids::find('hrm-rcr-provision')->openUrl());
        $this->assertNull(Grids::find('hrm-rcr-provision')->newUrl());
        $this->assertFileDoesNotExist(resource_path('views/pages/recruitment/hire-provision.blade.php'));
        $this->assertArchived('recruitment_hire_provision_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_RCR_PROVISION', MenuRoutes::map());
        $this->get('/hrm/recruitment/hire-provision')->assertRedirect('/v2/s/hrm-rcr-provision');
        $this->get('/hrm/recruitment/hire-provision?open=5')->assertRedirect('/v2/s/hrm-rcr-provision-form?id=5');
    }
}
