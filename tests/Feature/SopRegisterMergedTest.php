<?php

/*
 * REMARK
 * Tujuan       : Jaga register SOP / WI yang sudah satu (tm2mnt.mnt_document): HR Officer menulis dan meng-Approve lewat form v2 hrm-sop-form (MntDocumentService, aturan sama dengan v1), yang lain hanya melihat dokumen Approved, Maintenance tidak bisa mengubah dokumen, pegawai hanya melihat dokumen Approved milik departemennya atau semua departemen, halaman v1 sudah dipindah (redirect, menu, grid).
 * Sumber hulu  : routes/v2_hrm.php (v2.hrm.sop.save), layar hrm-sop-form, MntDocumentService, MntDocument, SelfService::sopDocuments, MaintenanceAccess::canManageDocuments, SopGrid.
 * Input        : Dokumen uji bernomor ZZ-SOP-* dan akun uji sementara tst_sop_* di tm2sys.
 * Output       : Lulus atau gagal; dokumen dan akun uji dihapus di tearDown.
 * Dipakai oleh : php artisan test --env=mockup.
 * Status       : Aktif; ditulis ulang untuk v2 2026-10-19 (T7f HRM).
 */

namespace Tests\Feature;

use App\Http\Middleware\ForcePasswordChange;
use App\Models\Master\EmpEmployee;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntDocumentLine;
use App\Models\Mnt\MntLog;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\MaintenanceAccess;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\SelfService;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopRegisterMergedTest extends TestCase
{
    private function cleanup(): void
    {
        $ids = MntDocument::where('doc_no', 'like', 'ZZ-SOP-%')->pluck('id_document');
        MntDocumentLine::whereIn('document_id', $ids)->delete();
        MntLog::where('doc_type', 'DOCUMENT')->whereIn('doc_id', $ids)->delete();
        MntDocument::whereIn('id_document', $ids)->delete();
    }

    private function dropUsers(): void
    {
        $c = DB::connection('tm2sys');
        $ids = $c->table('sec_user')->where('login', 'like', 'tst_sop_%')->pluck('id_user');
        $c->table('sec_user_group')->whereIn('user_id', $ids)->delete();
        $c->table('sec_user')->whereIn('id_user', $ids)->delete();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
        $this->withoutMiddleware(ForcePasswordChange::class);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $this->dropUsers();
        $this->assertSame(0, MntDocument::where('doc_no', 'like', 'ZZ-SOP-%')->count());
        parent::tearDown();
    }

    private function user(string $key, array $groups = []): SecUser
    {
        $c = DB::connection('tm2sys');
        $id = $c->table('sec_user')->insertGetId(['login' => 'tst_sop_'.$key, 'name' => 'Test '.$key, 'active' => 1, 'activate' => 1, 'is_admin' => 0, 'employee_id' => null]);
        foreach ($groups as $code) {
            $c->table('sec_user_group')->insert(['user_id' => $id, 'group_id' => $c->table('sec_group')->where('group_code', $code)->value('id_group')]);
        }

        return SecUser::findOrFail($id);
    }

    public function test_hr_officer_writes_steps_approves_and_only_approved_documents_reach_employees(): void
    {
        $this->actingAs($this->user('hr', ['HR_OFFICER']));
        $form = route('v2.screen', 'hrm-sop-form');
        $this->get($form.'?new=1')->assertOk()->assertSee('name="doc_no"', false)->assertSee('Purpose');

        $in = ['doc_no' => 'ZZ-SOP-1', 'kind' => 'WI', 'title' => 'Uji langkah', 'revision' => '00', 'purpose' => 'Uji', 'effective_date' => '2026-11-01',
            'lines' => [['section' => 'Steps', 'text' => 'Langkah satu'], ['section' => 'Check', 'text' => 'Langkah dua'], ['section' => 'Steps', 'text' => '']]];
        $this->post(route('v2.hrm.sop.save'), ['kind' => 'MANUAL'] + $in)->assertSessionHasErrors('kind');
        $this->post(route('v2.hrm.sop.save'), ['title' => ''] + $in)->assertSessionHasErrors('title');
        $this->post(route('v2.hrm.sop.save'), $in)->assertRedirect();

        $doc = MntDocument::with('lines')->where('doc_no', 'ZZ-SOP-1')->firstOrFail();
        $this->assertSame('DRAFT', $doc->status);
        $this->assertSame("Langkah satu\nLangkah dua", $doc->contentText());
        $this->assertNull($doc->doc_set);
        $this->assertSame('2026-11-01', $doc->effective_date->toDateString());
        $this->assertCount(0, MntDocument::company()->inForce()->where('doc_no', 'ZZ-SOP-1')->get());
        $this->post(route('v2.hrm.sop.save'), ['doc_no' => 'ZZ-SOP-1'] + $in)->assertSessionHasErrors('doc_no');
        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'title' => 'Uji langkah 2'] + $in)->assertRedirect();
        $this->assertSame('Uji langkah 2', $doc->refresh()->title);

        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'approve'])->assertRedirect();
        $this->assertTrue($doc->refresh()->isApproved());
        $this->assertCount(1, MntDocument::company()->inForce()->where('doc_no', 'ZZ-SOP-1')->get());
        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'approve'])->assertSessionHasErrors('document');
        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'title' => 'Changed'] + $in)->assertSessionHasErrors('document');
        $this->assertSame('Uji langkah 2', $doc->refresh()->title, 'dokumen Approved terkunci');

        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'to_draft'])->assertRedirect();
        $this->assertSame('DRAFT', $doc->refresh()->status);
        $this->assertSame('01', $doc->revision);
        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse((bool) $doc->refresh()->activate);
        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'activate'])->assertRedirect();
        $this->assertTrue((bool) $doc->refresh()->activate);
        $this->assertGreaterThanOrEqual(5, MntLog::where('doc_type', 'DOCUMENT')->where('doc_id', $doc->id_document)->count());
        $this->get($form.'?id='.$doc->id_document)->assertOk()->assertSee('Uji langkah 2');
    }

    public function test_others_cannot_change_documents_and_maintenance_head_is_view_only(): void
    {
        $doc = MntDocument::create(['doc_no' => 'ZZ-SOP-2', 'kind' => 'SOP', 'title' => 'Uji gerbang', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
        $approved = MntDocument::create(['doc_no' => 'ZZ-SOP-6', 'kind' => 'SOP', 'title' => 'Sudah berlaku', 'revision' => '00', 'status' => 'APPROVED', 'activate' => true]);
        $other = $this->user('plain', ['DIREKSI']);
        $this->assertFalse($other->isHrOfficer());
        $this->actingAs($other);
        $form = route('v2.screen', 'hrm-sop-form');

        $this->post(route('v2.hrm.sop.save'), ['id' => $doc->id_document, 'action' => 'approve'])->assertForbidden();
        $this->assertSame('DRAFT', $doc->refresh()->status);
        $this->get($form.'?new=1')->assertNotFound();
        $this->get($form.'?id='.$doc->id_document)->assertNotFound();
        $this->get($form.'?id='.$approved->id_document)->assertOk()->assertSee('Sudah berlaku')->assertDontSee('value="approve"', false);
        $this->assertFalse(MaintenanceAccess::canManageDocuments($other));
        $this->assertNull(Grids::find('hrm-sop')->newUrl());
    }

    public function test_grid_redirect_and_v1_state(): void
    {
        $this->actingAs($this->user('hr2', ['HR_OFFICER']));
        $grid = Grids::find('hrm-sop');
        $this->assertStringContainsString('/v2/s/hrm-sop-form?id={id}', $grid->openUrl());
        $this->assertStringContainsString('/v2/s/hrm-sop-form?new=1', $grid->newUrl());
        $this->get(route('v2.screen', 'hrm-sop'))->assertOk();
        $this->assertFileDoesNotExist(resource_path('views/pages/hrm/sop-documents.blade.php'));
        $this->assertArchived('hrm_sop_documents_v1_2026-10-19.blade.php');
        $this->assertArrayNotHasKey('HRM_SOP', MenuRoutes::map());
        $this->get('/hrm/sop')->assertRedirect('/v2/s/hrm-sop');
        $this->get('/hrm/sop?open=111')->assertRedirect('/v2/s/hrm-sop-form?id=111');
    }

    public function test_self_service_shows_company_documents_of_the_department_or_all_departments(): void
    {
        MntDocument::create(['doc_no' => 'ZZ-SOP-3', 'kind' => 'SOP', 'title' => 'Semua departemen', 'revision' => '00', 'status' => 'APPROVED', 'activate' => true]);
        MntDocument::create(['doc_no' => 'ZZ-SOP-4', 'kind' => 'SOP', 'title' => 'Konsep', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
        MntDocument::create(['doc_no' => 'ZZ-SOP-5', 'kind' => 'WI', 'doc_set' => 'DIE', 'title' => 'Set alat', 'revision' => '00', 'status' => 'APPROVED', 'activate' => true]);

        $codes = SelfService::sopDocuments(EmpEmployee::where('activate', true)->firstOrFail())->pluck('doc_no');
        $this->assertTrue($codes->contains('ZZ-SOP-3'));
        $this->assertFalse($codes->contains('ZZ-SOP-4'), 'a Draft is not shown');
        $this->assertFalse($codes->contains('ZZ-SOP-5'), 'an equipment-set document is not a company document');
    }
}
