<?php

namespace Tests\Feature;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntDocument;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntWatchPoint;
use App\Models\Sys\SecUser;
use App\Services\Maintenance\AssetProfileService;
use App\Services\Maintenance\MaintenanceAccess;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Maintenance Fase 1 (Master Data): kelengkapan profil, salin watch point, tarik mesin, simpan profil lewat route, layar tampil.
// Database tm2mnt belum dibuat di live sampai rilis: jalankan terhadap dummy dengan  DB_MNT=ddtmnt php artisan test --filter=MaintenanceAssetTest
// (dilewati bila tabel mnt_* belum ada). Semua baris buatan test (kode TEST-*) dihapus di tearDown.
class MaintenanceAssetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2mnt')->hasTable('mnt_asset')) {
            $this->markTestSkipped('Tabel Maintenance (mnt_*) belum ada di database ini; jalankan dengan DB_MNT=ddtmnt.');
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tm2mnt')->hasTable('mnt_asset')) {
            $this->cleanup();
        }
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = MntAsset::where('asset_code', 'like', 'TEST-%')->pluck('id_asset');
        MntLog::where('doc_type', 'ASSET')->whereIn('doc_id', $ids)->delete();
        MntAsset::whereIn('id_asset', $ids)->delete();   // watch point, part, tautan dokumen ikut (cascade)
        $docs = MntDocument::where('doc_no', 'like', 'TEST-%')->pluck('id_document');
        MntLog::where('doc_type', 'DOCUMENT')->whereIn('doc_id', $docs)->delete();
        MntDocument::whereIn('id_document', $docs)->delete();
    }

    private function asset(string $code, array $extra = []): MntAsset
    {
        return MntAsset::create(['asset_code' => $code, 'asset_name' => 'Test '.$code, 'asset_class' => 'FACILITY', 'meter_type' => 'NONE', 'activate' => true] + $extra);
    }

    public function test_completeness_counts_four_items_and_is_calculated(): void
    {
        $this->assertSame(['done' => 0, 'total' => 4, 'pct' => 0], array_intersect_key(AssetProfileService::completeness([], 'NONE'), array_flip(['done', 'total', 'pct'])));
        $c = AssetProfileService::completeness(['watch' => 3, 'parts' => 0, 'docs' => 1], 'RUN_HOURS');
        $this->assertSame(3, $c['done']);
        $this->assertSame(75, $c['pct']);
        $this->assertFalse($c['items']['parts']);
    }

    public function test_sync_machines_is_idempotent_and_keeps_maintenance_fields(): void
    {
        $svc = new AssetProfileService;
        $svc->syncMachines();
        $one = MntAsset::whereNotNull('machine_id')->orderBy('asset_code')->firstOrFail();
        $one->update(['criticality' => 'A', 'meter_type' => 'RUN_HOURS']);
        $second = $svc->syncMachines();
        $this->assertSame(0, $second['created']);
        $this->assertSame('RUN_HOURS', $one->refresh()->meter_type, 'sync must not touch maintenance-owned fields');
        $one->update(['criticality' => null, 'meter_type' => 'NONE']);
    }

    public function test_copy_watch_points_never_overwrites_existing_rows(): void
    {
        $from = $this->asset('TEST-A');
        $to = $this->asset('TEST-B');
        foreach (['Oil level', 'Noise'] as $i => $text) {
            MntWatchPoint::create(['asset_id' => $from->id_asset, 'check_text' => $text, 'frequency' => 'DAILY', 'checked_by_role' => 'OPERATOR', 'sort_order' => $i, 'activate' => true]);
        }
        MntWatchPoint::create(['asset_id' => $to->id_asset, 'check_text' => 'Oil level', 'method_text' => 'kept', 'frequency' => 'WEEKLY', 'checked_by_role' => 'TECHNICIAN', 'activate' => true]);
        $svc = new AssetProfileService;
        $this->assertSame(1, $svc->copyWatchPoints($from, $to));
        $this->assertSame(0, $svc->copyWatchPoints($from, $to), 'second copy adds nothing');
        $this->assertSame('kept', $to->watchPoints()->where('check_text', 'Oil level')->firstOrFail()->method_text);
        $this->assertSame(2, $to->watchPoints()->count());
        $this->assertSame(0, $svc->copyWatchPoints($from, $from));
    }

    public function test_only_head_or_admin_can_manage(): void
    {
        $this->assertFalse(MaintenanceAccess::canManage(null));
        $this->assertTrue(MaintenanceAccess::canManage(SecUser::where('login', 'johiwan')->firstOrFail()));
    }

    public function test_profile_saves_through_the_route_and_screens_render(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $this->post(route('v2.mnt.asset.save'), ['action' => 'save', 'asset_code' => 'TEST-AC', 'asset_name' => 'Test air conditioner', 'asset_class' => 'FACILITY',
            'criticality' => 'C', 'meter_type' => 'NONE', 'location' => 'Test room'])->assertRedirect();
        $a = MntAsset::where('asset_code', 'TEST-AC')->firstOrFail();
        $this->assertSame('C', $a->criticality);

        $this->post(route('v2.mnt.asset.save'), ['action' => 'add_watch', 'id' => $a->id_asset, 'asset_code' => 'TEST-AC', 'asset_name' => 'Test air conditioner',
            'asset_class' => 'FACILITY', 'meter_type' => 'NONE', 'new_watch' => 'Filter clean', 'new_watch_frequency' => 'MONTHLY', 'new_watch_role' => 'TECHNICIAN'])->assertRedirect();
        $this->assertSame('MONTHLY', $a->watchPoints()->firstOrFail()->frequency);

        $this->post(route('v2.mnt.asset.save'), ['action' => 'save', 'asset_code' => 'TEST-AC', 'asset_name' => 'Dup code', 'asset_class' => 'FACILITY', 'meter_type' => 'NONE'])
            ->assertSessionHasErrors('asset_code');

        foreach (['maintenance-profile', 'maintenance-documents', 'maintenance-codes', 'maintenance-readiness'] as $slug) {
            $this->get(route('v2.screen', $slug))->assertOk();
        }
        $this->get(route('v2.screen', 'maintenance-profile').'?id='.$a->id_asset)->assertOk()->assertSee('TEST-AC');
        $doc = MntDocument::firstOrFail();
        $this->get(route('v2.screen', 'maintenance-documents').'?id='.$doc->id_document)->assertOk()->assertSee($doc->doc_no);
    }

    public function test_approved_document_is_locked_until_back_to_draft(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $d = MntDocument::create(['doc_no' => 'TEST-WI-01', 'kind' => 'WI', 'title' => 'Test WI', 'revision' => '00', 'status' => 'DRAFT', 'activate' => true]);
        $this->post(route('v2.mnt.document.save'), ['action' => 'approve', 'id' => $d->id_document])->assertRedirect();
        $this->assertSame('APPROVED', $d->refresh()->status);
        $this->post(route('v2.mnt.document.save'), ['action' => 'save', 'id' => $d->id_document, 'doc_no' => 'TEST-WI-01', 'kind' => 'WI', 'title' => 'Changed'])->assertStatus(422);
        $this->assertSame('Test WI', $d->refresh()->title);
        $this->post(route('v2.mnt.document.save'), ['action' => 'to_draft', 'id' => $d->id_document])->assertRedirect();
        $this->assertSame(['DRAFT', '01'], [$d->refresh()->status, $d->revision]);
    }
}
