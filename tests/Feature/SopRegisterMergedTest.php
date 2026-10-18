<?php

/*
 * REMARK
 * Tujuan       : Jaga register SOP / WI yang sudah satu (tm2mnt.mnt_document): HR Officer menulis dan meng-Approve, yang lain hanya melihat,
 *                Maintenance tidak bisa mengubah dokumen, pegawai hanya melihat dokumen Approved milik departemennya atau semua departemen.
 * Sumber hulu  : pages::hrm.sop-documents, MntDocument, SelfService::sopDocuments, MaintenanceAccess::canManageDocuments.
 * Input        : Dokumen uji bernomor ZZ-SOP-*.
 * Output       : Lulus atau gagal; dokumen uji dihapus di tearDown.
 * Dipakai oleh : php artisan test --env=mockup.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Mnt\MntDocument;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\MaintenanceAccess;
use App\Support\V2\Data\SelfService;
use Livewire\Livewire;
use Tests\TestCase;

class SopRegisterMergedTest extends TestCase
{
    private function cleanup(): void
    {
        $ids = MntDocument::where('doc_no', 'like', 'ZZ-SOP-%')->pluck('id_document');
        \App\Models\Mnt\MntDocumentLine::whereIn('document_id', $ids)->delete();
        MntDocument::whereIn('id_document', $ids)->delete();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $this->assertSame(0, MntDocument::where('doc_no', 'like', 'ZZ-SOP-%')->count());
        parent::tearDown();
    }

    public function test_hr_officer_writes_steps_approves_and_only_approved_documents_reach_employees(): void
    {
        $admin = SecUser::where('is_admin', 1)->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::hrm.sop-documents')
            ->call('openNew')
            ->set('doc_no', 'ZZ-SOP-1')->set('kind', 'WI')->set('title', 'Uji langkah')->set('purpose', 'Uji')
            ->set('lines', [['section' => 'Steps', 'text' => 'Langkah satu'], ['section' => 'Check', 'text' => 'Langkah dua'], ['section' => 'Steps', 'text' => '']])
            ->call('save')->assertHasNoErrors();

        $doc = MntDocument::with('lines')->where('doc_no', 'ZZ-SOP-1')->firstOrFail();
        $this->assertSame('DRAFT', $doc->status);
        $this->assertSame("Langkah satu\nLangkah dua", $doc->contentText());
        $this->assertNull($doc->doc_set);
        $this->assertCount(0, MntDocument::company()->inForce()->where('doc_no', 'ZZ-SOP-1')->get());

        Livewire::test('pages::hrm.sop-documents')->call('approve', $doc->id_document);
        $this->assertTrue($doc->refresh()->isApproved());
        $this->assertCount(1, MntDocument::company()->inForce()->where('doc_no', 'ZZ-SOP-1')->get());

        Livewire::test('pages::hrm.sop-documents')->call('backToDraft', $doc->id_document);
        $this->assertSame('DRAFT', $doc->refresh()->status);
        $this->assertSame('01', $doc->revision);
    }

    public function test_others_cannot_change_documents_and_maintenance_head_is_view_only(): void
    {
        $doc = MntDocument::create(['doc_no' => 'ZZ-SOP-2', 'kind' => 'SOP', 'title' => 'Uji gerbang', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
        $other = SecUser::where('is_admin', 0)->get()->first(fn ($u) => ! $u->isHrOfficer());
        $this->assertNotNull($other, 'a non HR Officer user exists');
        $this->actingAs($other);

        Livewire::test('pages::hrm.sop-documents')->call('approve', $doc->id_document);
        $this->assertSame('DRAFT', $doc->refresh()->status);
        $this->assertFalse(MaintenanceAccess::canManageDocuments($other));
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
