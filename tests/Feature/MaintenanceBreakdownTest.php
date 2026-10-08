<?php

namespace Tests\Feature;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntRequest;
use App\Models\Pur\PrLine;
use App\Models\Pur\PrRequisition;
use App\Services\Maintenance\KpiService;
use App\Services\Maintenance\OrderService;
use App\Services\Maintenance\PartSupplyService;
use App\Services\Maintenance\RequestService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// Maintenance Fase 3 (Breakdown): request -> Accept / Reject, P1 otomatis jadi BMO Confirmed, laporan mesin rusak dari produksi, PR untuk part yang kurang, angka KPI.
// Jalankan terhadap database dummy (semua koneksi ddt*, DB_MNT=ddtmnt) seperti MaintenancePreventiveTest. Baris buatan test (kode TEST-*, event 999999xxx) dihapus di tearDown.
class MaintenanceBreakdownTest extends TestCase
{
    private const EVENT = 999999901;

    private array $prIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::connection('tm2mnt')->hasTable('mnt_order_part') || ! Schema::connection('tm2mnt')->hasColumn('mnt_order_part', 'pr_id')) {
            $this->markTestSkipped('Tabel Maintenance Fase 3 belum ada di database ini; jalankan dengan DB_MNT=ddtmnt.');
        }
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tm2mnt')->hasTable('mnt_order_part')) {
            $this->cleanup();
        }
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $assets = MntAsset::where('asset_code', 'like', 'TEST-%')->pluck('id_asset');
        $requests = MntRequest::whereIn('asset_id', $assets)->orWhere('breakdown_event_id', self::EVENT)->pluck('id_request');
        $orders = MntOrder::whereIn('asset_id', $assets)->orWhereIn('request_id', $requests)->pluck('id_order');
        MntLog::whereIn('doc_type', ['ORDER', 'REQUEST', 'ASSET'])->where(fn ($q) => $q->whereIn('doc_id', $orders)->orWhereIn('doc_id', $requests)->orWhereIn('doc_id', $assets))->delete();
        MntOrder::whereIn('id_order', $orders)->delete();
        MntRequest::whereIn('id_request', $requests)->delete();
        MntAsset::whereIn('id_asset', $assets)->delete();
        PrLine::whereIn('pr_id', $this->prIds)->delete();
        PrRequisition::whereIn('pr_id', $this->prIds)->delete();
        $this->prIds = [];
    }

    private function asset(string $code, array $extra = []): MntAsset
    {
        return MntAsset::create(array_merge(['asset_code' => $code, 'asset_name' => 'Test '.$code, 'asset_class' => 'FACILITY', 'meter_type' => 'NONE', 'activate' => true], $extra));
    }

    public function test_priority_is_suggested_from_kind_stop_and_criticality(): void
    {
        $a = $this->asset('TEST-P');
        $this->assertSame('P1', RequestService::suggestPriority($a, 'SAFETY', false));
        $this->assertSame('P2', RequestService::suggestPriority($a, 'BREAKDOWN', true));
        $this->assertSame('P3', RequestService::suggestPriority($a, 'CORRECTIVE', false));
    }

    public function test_accept_makes_a_draft_bmo_and_reject_needs_a_reason(): void
    {
        $a = $this->asset('TEST-A');
        $svc = new RequestService('tester');
        [$req, $auto] = $svc->open(['asset_id' => $a->id_asset, 'request_kind' => 'CORRECTIVE', 'symptom' => 'TEST noise from the gearbox']);
        $this->assertNull($auto);
        $this->assertSame('OPEN', $req->status);
        $this->assertMatchesRegularExpression('/^MR-\d{4}-\d{4}$/', $req->doc_no);

        $order = $svc->accept($req);
        $this->assertSame('DRAFT', $order->docStatus());
        $this->assertSame('CORRECTIVE', $order->order_type);
        $this->assertSame('REQUEST', $order->source);
        $this->assertSame($req->id_request, $order->request_id);
        $this->assertSame('ACCEPTED', $req->refresh()->status);
        $this->assertSame($order->id_order, $req->order_id);

        [$second] = $svc->open(['asset_id' => $a->id_asset, 'request_kind' => 'CORRECTIVE', 'symptom' => 'TEST duplicate report']);
        try {
            $svc->reject($second, ' ');
            $this->fail('A reject without a reason must be refused.');
        } catch (ValidationException) {
        }
        $svc->reject($second, 'Same as the open request');
        $this->assertSame('REJECTED', $second->refresh()->status);
        $this->expectException(ValidationException::class);
        $svc->accept($second);
    }

    public function test_a_p1_request_becomes_a_confirmed_bmo_at_once(): void
    {
        $a = $this->asset('TEST-S');
        [$req, $order] = (new RequestService('tester'))->open(['asset_id' => $a->id_asset, 'request_kind' => 'SAFETY', 'symptom' => 'TEST guard missing', 'asset_stopped' => true]);
        $this->assertSame('P1', $req->priority);
        $this->assertSame('ACCEPTED', $req->status);
        $this->assertNotNull($order);
        $this->assertSame('OPEN', $order->docStatus());
        $this->assertSame('SCHEDULED', $order->work_status);
        $this->assertMatchesRegularExpression('/^BMO-\d{4}-\d{4}$/', $order->doc_no);
    }

    public function test_a_shop_floor_breakdown_becomes_one_request_and_unknown_machines_are_skipped(): void
    {
        $asset = MntAsset::whereNotNull('machine_id')->where('activate', true)->first();
        if (! $asset) {
            $this->markTestSkipped('No asset linked to a machine in this database.');
        }
        $svc = new RequestService('tester');
        $this->assertNull($svc->fromBreakdown(self::EVENT, 999999999));
        [$req] = $svc->fromBreakdown(self::EVENT, (int) $asset->machine_id, 'TEST operation');
        $this->assertSame('BREAKDOWN', $req->request_kind);
        $this->assertTrue($req->asset_stopped);
        $this->assertSame(self::EVENT, (int) $req->breakdown_event_id);
        $this->assertNull($svc->fromBreakdown(self::EVENT, (int) $asset->machine_id), 'one event must not open a second request');
        $this->assertSame(1, MntRequest::where('breakdown_event_id', self::EVENT)->count());
    }

    public function test_parts_cannot_be_issued_from_a_draft_and_a_pr_is_made_for_short_stock_once(): void
    {
        $mat = MatRaw::where('activate', true)->get()->first(fn ($m) => $m->cogs_account_id || MatCategory::level(1)->where('category_name', $m->category1)->value('default_coa_account_id'));
        if (! $mat) {
            $this->markTestSkipped('No material with a COA account in this database.');
        }
        $a = $this->asset('TEST-PART');
        $orders = new OrderService('tester');
        $order = $orders->create(['order_type' => 'CORRECTIVE', 'asset_id' => $a->id_asset, 'description' => 'TEST parts']);
        $orders->addPart($order, (int) $mat->material_id, 9999999);

        $svc = new PartSupplyService('tester');
        try {
            $svc->issue($order->refresh());
            $this->fail('Issuing parts from a Draft order must be refused.');
        } catch (ValidationException) {
        }

        $pr = $svc->createPr($order->refresh());
        $this->prIds[] = (int) $pr;
        $this->assertNotNull($pr);
        $this->assertSame('DRAFT', PrRequisition::find($pr)->status);
        $this->assertSame(1, PrLine::where('pr_id', $pr)->count());
        $this->assertNull($svc->createPr($order->refresh()), 'a part already on a PR must not be requested twice');
    }

    public function test_kpi_figures_are_computed_without_errors(): void
    {
        $k = new KpiService;
        $to = CarbonImmutable::now()->startOfDay();
        $i = $k->indicators($to->subDays(90), $to);
        foreach (['availability', 'mtbf', 'mttr', 'pm_compliance', 'planned_work', 'backlog_weeks', 'oee'] as $key) {
            $this->assertArrayHasKey($key, $i);
        }
        $this->assertSame(['down', 'requests', 'overdue_pm', 'in_progress', 'waiting', 'parts_low'], array_keys($k->actions()));
        $this->assertCount(12, $k->complianceTrend()['labels']);
        $this->assertArrayHasKey('total', $k->cost($to->subDays(90), $to));
    }
}
