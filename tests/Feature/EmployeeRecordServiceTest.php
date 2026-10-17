<?php

namespace Tests\Feature;

use App\Models\Master\EmpContract;
use App\Models\Master\EmpDisciplinary;
use App\Models\Master\EmpDocument;
use App\Models\Master\EmpEducation;
use App\Models\Master\EmpEmployee;
use App\Models\Master\EmpFamily;
use App\Models\Sys\SecUser;
use App\Services\Hrm\EmployeeProfileService;
use App\Services\Hrm\EmployeeRecordService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Catatan anak pegawai (kontrak, keluarga, pendidikan, kedisiplinan) dari layar v2. Semua berjalan di transaksi lalu di-rollback.
class EmployeeRecordServiceTest extends TestCase
{
    private function inTransaction(callable $fn): void
    {
        DB::connection('tm2mst')->beginTransaction();
        DB::connection('tm2sys')->beginTransaction();
        try {
            $admin = SecUser::query()->where('is_admin', true)->where('active', true)->firstOrFail();
            [$e] = app(EmployeeProfileService::class)->save(null, ['full_name' => 'Zz Test Record', 'staff_type' => 'NON_STAFF', 'payment_frequency' => 'MONTHLY',
                'position_id' => DB::connection('tm2mst')->table('org_position')->where('activate', true)->value('position_id')], $admin);
            $fn($e, $admin, app(EmployeeRecordService::class));
        } finally {
            DB::connection('tm2sys')->rollBack();
            DB::connection('tm2mst')->rollBack();
        }
    }

    public function test_new_contract_ends_the_active_one(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $u, EmployeeRecordService $svc) {
            $first = $svc->addContract($e, ['employment_type' => 'KONTRAK', 'start_date' => '2026-01-01', 'salary_type' => 'PER_MONTH', 'salary_amount' => '1000'], $u);
            $second = $svc->addContract($e, ['employment_type' => 'TETAP', 'start_date' => '2026-07-01', 'salary_type' => 'PER_MONTH', 'salary_amount' => '2000', 'is_probation' => '1', 'probation_end_date' => '2026-09-30'], $u);
            $this->assertFalse((bool) $first->fresh()->activate);
            $this->assertTrue((bool) $second->fresh()->activate);
            $this->assertSame(1, EmpContract::where('employee_id', $e->employee_id)->where('activate', true)->count());
            $this->assertTrue((bool) $second->fresh()->is_probation);

            $this->expectException(ValidationException::class);
            $svc->addContract($e, ['employment_type' => 'TETAP', 'start_date' => '2026-07-01', 'end_date' => '2026-06-01', 'salary_type' => 'PER_MONTH', 'salary_amount' => '1'], $u);
        });
    }

    public function test_family_rules_and_deceased_mark(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $u, EmployeeRecordService $svc) {
            $svc->addFamily($e, ['relation' => 'AYAH', 'full_name' => 'Zz Ayah'], $u);
            try {
                $svc->addFamily($e, ['relation' => 'AYAH', 'full_name' => 'Zz Ayah Dua'], $u);
                $this->fail('Ayah kedua harus ditolak.');
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey('relation', $ex->errors());
            }
            $svc->addFamily($e, ['relation' => 'ANAK', 'full_name' => 'Zz Anak 1'], $u);
            $svc->addFamily($e, ['relation' => 'ANAK', 'full_name' => 'Zz Anak 2'], $u);
            $this->assertSame(2, EmpFamily::where('employee_id', $e->employee_id)->where('relation', 'ANAK')->count());

            $ayah = EmpFamily::where('employee_id', $e->employee_id)->where('relation', 'AYAH')->first();
            $svc->markDeceased($ayah, $u);
            $this->assertTrue($ayah->fresh()->is_deceased);
            $this->assertNotNull($ayah->fresh()->deceased_date);
            $this->assertSame(3, EmpFamily::where('employee_id', $e->employee_id)->count());
        });
    }

    public function test_education_and_discipline_are_stored(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $u, EmployeeRecordService $svc) {
            $edu = $svc->addEducation($e, ['education_type' => 'PELATIHAN', 'activity_name' => 'Zz Forklift', 'activity_date' => '2026-05-01', 'cost' => '850000', 'funded_by' => 'PERUSAHAAN', 'is_bonding' => '1'], $u);
            $this->assertSame('HR', $edu->fresh()->uploaded_by_role);
            $this->assertTrue((bool) $edu->fresh()->is_bonding);
            $this->assertSame(1, EmpEducation::where('employee_id', $e->employee_id)->count());

            $dis = $svc->addDiscipline($e, ['type' => 'TEGURAN_VERBAL', 'incident_date' => '2026-09-03', 'reason' => 'Zz telat'], $u);
            $this->assertSame($u->login, $dis->fresh()->issued_by);
            $this->assertSame(1, EmpDisciplinary::where('employee_id', $e->employee_id)->count());

            $this->expectException(ValidationException::class);
            $svc->addDiscipline($e, ['type' => 'NGAWUR', 'incident_date' => '2026-09-03', 'reason' => 'x'], $u);
        });
    }

    public function test_document_upload_replaces_the_old_file_and_locks_it_for_hr(): void
    {
        Storage::fake('public');
        $this->inTransaction(function (EmpEmployee $e, SecUser $u, EmployeeRecordService $svc) {
            $first = $svc->addDocument($e, ['document_type' => 'SIM', 'document_no' => 'A-1', 'expiry_date' => '2027-01-31'], UploadedFile::fake()->image('a.jpg'), $u);
            Storage::disk('public')->assertExists($first->file_path);
            $second = $svc->addDocument($e, ['document_type' => 'SIM', 'document_no' => 'A-2'], UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'), $u);

            Storage::disk('public')->assertMissing($first->file_path);
            Storage::disk('public')->assertExists($second->file_path);
            $this->assertSame(1, EmpDocument::where('employee_id', $e->employee_id)->count());
            $this->assertSame('HR', $second->fresh()->uploaded_by_role);
            $this->assertSame('A-2', $second->fresh()->document_no);
            $this->assertNull($second->fresh()->expiry_date);

            $this->expectException(ValidationException::class);
            $svc->addDocument($e, ['document_type' => 'SIM'], null, $u);
        });
    }

    public function test_routes_save_and_return_to_the_same_tab(): void
    {
        $this->inTransaction(function (EmpEmployee $e, SecUser $u) {
            $r = $this->actingAs($u)->post(route('v2.hrm.employee.discipline', $e->employee_id), ['type' => 'SP1', 'incident_date' => '2026-09-03', 'reason' => 'Zz rute']);
            $r->assertRedirect(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id.'&tab=discipline');
            $this->assertSame(1, EmpDisciplinary::where('employee_id', $e->employee_id)->count());

            $this->actingAs($u)->get(route('v2.screen', 'hrm-employee-form').'?id='.$e->employee_id)
                ->assertOk()->assertSee('New Contract')->assertSee('Add Family')->assertSee('Add Activity')->assertSee('Add Record')->assertSee('Zz rute');
        });
    }
}
