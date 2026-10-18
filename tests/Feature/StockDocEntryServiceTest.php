<?php

/*
 * REMARK
 * Tujuan       : Menjaga dokumen stok manual v2 (Stock In RM / FG / SFG dan Stock Out RM / SFG): simpan Draft (nomor, baris, batch, aturan header dan baris), Posted tidak bisa diubah, Confirm memposting ke Kartu Stok (Stock Out ditolak bila saldo minus), Cancel dengan pembalik, Cancel Draft, Duplicate, route dan layar, FG tanpa Stock Out, dan tidak ada jalur ke halaman v1 inventory.stock-in / stock-out.
 * Sumber hulu  : App\Services\Inventory\StockDocEntryService, StockPostingService, StockReversalService, layar inv-stock-doc-form, grid inv-*-stock-in / -stock-out, routes/v2_stockdocs.php.
 * Input        : User johiwan (admin); item, bin dan gudang yang sudah ada di database. Seluruh uji berjalan di transaksi tm2inv / tm2acc / tm2mst yang di-rollback, jadi posting ke Kartu Stok dan batch uji tidak tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=StockDocEntryServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\InvBatch;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\SfgStock;
use App\Models\Master\MatRaw;
use App\Models\Master\OrgDepartment;
use App\Models\Master\WhLoc;
use App\Models\Sys\SecUser;
use App\Services\Inventory\BatchService;
use App\Services\Inventory\StockDocEntryService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockReversalService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\StockEntry;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockDocEntryServiceTest extends TestCase
{
    private const CONNECTIONS = ['tm2inv', 'tm2acc', 'tm2mst'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::CONNECTIONS as $c) {
            DB::connection($c)->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        foreach (self::CONNECTIONS as $c) {
            while (DB::connection($c)->transactionLevel() > 0) {
                DB::connection($c)->rollBack();
            }
        }
        parent::tearDown();
    }

    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function bin(): WhLoc
    {
        return WhLoc::where('activate', true)->where('is_usable', true)->orderBy('location_id')->firstOrFail();
    }

    private function item(string $class): int
    {
        $row = StockEntry::itemSearch($class, '', 1)[0] ?? null;
        $this->assertNotNull($row, "butuh item aktif kelas {$class}");

        return $row['id'];
    }

    private function docHeader(array $over = []): array
    {
        return $over + ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->bin()->warehouse_id, 'notes' => 'ZZTEST stock doc'];
    }

    private function outHeader(array $over = []): array
    {
        return $over + $this->docHeader() + ['issued_to_dept' => OrgDepartment::where('activate', true)->value('department_id'), 'process_code' => 'ZZ process'];
    }

    private function line(string $class, array $over = []): array
    {
        return $over + ['item_id' => $this->item($class), 'location_id' => $this->bin()->location_id, 'qty' => '5', 'unit_price' => '12,5', 'uom_code' => '', 'batch_id' => '', 'lot_no' => '', 'new_lot' => '', 'new_production' => '', 'new_expiry' => ''];
    }

    public function test_stock_in_draft_is_saved_with_number_lines_and_new_batch(): void
    {
        $svc = app(StockDocEntryService::class);
        $id = $svc->save('in', 'RM', null, $this->docHeader(), [$this->line('RM', ['new_lot' => 'ZZLOT-1', 'new_production' => '2026-10-01', 'new_expiry' => '2027-10-01']), [], $this->line('RM', ['qty' => '2'])], $this->admin());

        $h = RmStock::with('lines')->findOrFail($id);
        $this->assertMatchesRegularExpression('#^IN/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame('DRAFT', $h->flow_status);
        $this->assertSame('MANUAL', $h->ref_doc_type);
        $this->assertSame(1, (int) $h->doc_type);
        $this->assertCount(2, $h->lines, 'baris kosong dibuang');
        $first = $h->lines[0];
        $this->assertSame(5.0, (float) $first->qty);
        $this->assertSame(12.5, (float) $first->unit_price);
        $this->assertSame('ZZLOT-1', $first->lot_no);
        $this->assertSame('2027-10-01', InvBatch::findOrFail($first->batch_id)->expiry_date->toDateString());
        $this->assertNotSame('', (string) $first->uom_code, 'satuan ikut item bila kosong');

        $id2 = $svc->save('in', 'RM', $id, $this->docHeader(['notes' => 'changed']), [$this->line('RM', ['new_lot' => 'ZZLOT-1', 'new_expiry' => '2027-10-01'])], $this->admin());
        $this->assertSame($id, $id2);
        $h2 = RmStock::with('lines')->findOrFail($id);
        $this->assertSame($h->doc_no, $h2->doc_no);
        $this->assertCount(1, $h2->lines);
        $this->assertSame((int) $first->batch_id, (int) $h2->lines[0]->batch_id, 'lot sama = batch sama');
    }

    public function test_rules_are_refused_for_both_kinds(): void
    {
        $svc = app(StockDocEntryService::class);
        $other = WhLoc::where('activate', true)->where('is_usable', true)->where('warehouse_id', '!=', $this->bin()->warehouse_id)->first();
        $cases = [
            'in: no lines' => ['in', $this->docHeader(), []],
            'in: blank date' => ['in', $this->docHeader(['doc_date' => '']), [$this->line('RM')]],
            'in: bad category' => ['in', $this->docHeader(['doc_cat' => 'OP']), [$this->line('RM')]],
            'in: no warehouse' => ['in', $this->docHeader(['warehouse_id' => '']), [$this->line('RM')]],
            'in: no item' => ['in', $this->docHeader(), [$this->line('RM', ['item_id' => '', 'qty' => '3'])]],
            'in: zero qty' => ['in', $this->docHeader(), [$this->line('RM', ['qty' => '0'])]],
            'in: no bin' => ['in', $this->docHeader(), [$this->line('RM', ['location_id' => ''])]],
            'out: no department' => ['out', $this->outHeader(['issued_to_dept' => '']), [$this->line('RM')]],
            'out: bad department' => ['out', $this->outHeader(['issued_to_dept' => 999999999]), [$this->line('RM')]],
            'out: no lines' => ['out', $this->outHeader(), []],
            'out: zero qty' => ['out', $this->outHeader(), [$this->line('RM', ['qty' => '0'])]],
        ];
        if ($other) {
            $cases['in: bin of another warehouse'] = ['in', $this->docHeader(), [$this->line('RM', ['location_id' => $other->location_id])]];
            $cases['out: bin of another warehouse'] = ['out', $this->outHeader(), [$this->line('RM', ['location_id' => $other->location_id])]];
        }
        $shelf = MatRaw::where('activate', true)->where('shelf_life_days', '>', 0)->value('material_id');
        if ($shelf) {
            $cases['in: shelf life without expiry'] = ['in', $this->docHeader(), [$this->line('RM', ['item_id' => $shelf, 'new_lot' => 'ZZ'])]];
        }
        foreach ($cases as $name => [$kind, $head, $lines]) {
            try {
                $svc->save($kind, 'RM', null, $head, $lines, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame(0, RmStock::where('notes', 'ZZTEST stock doc')->count(), 'penolakan tidak boleh meninggalkan dokumen');
        $this->assertFalse(StockDocEntryService::supports('out', 'FG'), 'barang jadi keluar lewat Delivery Order');
        $this->assertFalse(StockDocEntryService::supports('adj', 'RM'));
    }

    public function test_stock_in_confirm_posts_then_cancel_reverses_it(): void
    {
        $svc = app(StockDocEntryService::class);
        $user = $this->admin();
        $id = $svc->save('in', 'RM', null, $this->docHeader(), [$this->line('RM', ['qty' => '7'])], $user);
        $before = RmStockcard::count();

        $res = $svc->post('RM', $id, $user);
        $this->assertTrue($res['posted'], (string) ($res['error'] ?? ''));
        $h = RmStock::with('lines')->findOrFail($id);
        $this->assertSame('POSTED', $h->flow_status);
        $this->assertSame($before + 1, RmStockcard::count());
        $this->assertSame(['confirmed', 'Posted'], $svc->formState($h));
        $this->assertFalse($svc->editable($h));

        try {
            $svc->save('in', 'RM', $id, $this->docHeader(), [$this->line('RM')], $user);
            $this->fail('dokumen Posted tidak boleh diubah');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $rv = app(StockReversalService::class);
        $this->assertTrue($rv->state('RM', $id)['can']);
        $r = $rv->reverse('RM', $id, 'ZZTEST reason', $user->login);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $this->assertSame(['cancelled', 'Reversed'], $svc->formState(RmStock::findOrFail($id)));
        $copy = $rv->duplicate('RM', $id, $user->login);
        $this->assertSame('DRAFT', RmStock::findOrFail($copy)->flow_status);
    }

    public function test_stock_out_posts_against_stock_and_is_refused_when_the_balance_would_go_negative(): void
    {
        $svc = app(StockDocEntryService::class);
        $user = $this->admin();
        $item = $this->item('RM');
        $batch = BatchService::create('RM', $item, 'ZZ-OUT-1', [], $user->login);

        // Stok masuk dulu (satu batch), lalu keluar sebagian dengan batch itu.
        $inId = $svc->save('in', 'RM', null, $this->docHeader(), [$this->line('RM', ['item_id' => $item, 'qty' => '10', 'batch_id' => (string) $batch->id_batch])], $user);
        $this->assertTrue($svc->post('RM', $inId, $user)['posted']);

        $outId = $svc->save('out', 'RM', null, $this->outHeader(), [$this->line('RM', ['item_id' => $item, 'qty' => '4', 'batch_id' => (string) $batch->id_batch])], $user);
        $h = RmStock::with('lines')->findOrFail($outId);
        $this->assertMatchesRegularExpression('#^OUT/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame(-1, (int) $h->doc_type);
        $this->assertSame('STOCK', $h->doc_cat);
        $this->assertSame('ZZ process', $h->process_code);
        $this->assertNotNull($h->issued_to_dept);
        $line = $h->lines[0];
        $this->assertSame('ISSUE', $line->action_type);
        $this->assertSame((int) $batch->id_batch, (int) $line->batch_id, 'batch ikut ke baris dan Kartu Stok');
        $this->assertSame(0.0, (float) $line->unit_price);

        $res = $svc->post('RM', $outId, $user);
        $this->assertTrue($res['posted'], (string) ($res['error'] ?? ''));
        $card = RmStockcard::query()->where('reference_name', app(StockPostingService::class)->referenceName('RM'))->where('reference_detail_id', $line->getKey())->first();
        $this->assertNotNull($card, 'baris Kartu Stok untuk Stock Out');

        // Keluar melebihi saldo item: ditolak, Draft tetap tersimpan.
        $big = $svc->save('out', 'RM', null, $this->outHeader(), [$this->line('RM', ['item_id' => $item, 'qty' => '99999999'])], $user);
        $refused = $svc->post('RM', $big, $user);
        $this->assertFalse($refused['posted']);
        $this->assertSame('DRAFT', RmStock::findOrFail($big)->flow_status);

        // Stock Out Posted bisa dibatalkan dengan pembalik.
        $this->assertTrue(app(StockReversalService::class)->reverse('RM', $outId, 'ZZTEST', $user->login)['ok']);
    }

    public function test_draft_can_be_cancelled_and_other_classes_work(): void
    {
        $svc = app(StockDocEntryService::class);
        $user = $this->admin();
        $id = $svc->save('in', 'RM', null, $this->docHeader(), [$this->line('RM')], $user);
        $svc->cancelDraft('RM', $id, $user);
        $this->assertSame(['cancelled', null], $svc->formState(RmStock::findOrFail($id)));

        foreach (['FG' => FgStock::class, 'SFG' => SfgStock::class] as $class => $model) {
            $did = $svc->save('in', $class, null, $this->docHeader(), [$this->line($class, ['qty' => '3'])], $user);
            $doc = $model::with('lines')->findOrFail($did);
            $this->assertSame('DRAFT', $doc->flow_status, $class);
            $this->assertSame(3.0, (float) $doc->lines[0]->qty, $class);
        }
        $sid = $svc->save('out', 'SFG', null, $this->outHeader(), [$this->line('SFG', ['qty' => '2'])], $user);
        $sdoc = SfgStock::with('lines')->findOrFail($sid);
        $this->assertSame(-1, (int) $sdoc->doc_type);
        $this->assertSame('ISSUE', $sdoc->lines[0]->action_type);
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        foreach (['in' => ['RM', 'FG', 'SFG'], 'out' => ['RM', 'SFG']] as $kind => $classes) {
            foreach ($classes as $class) {
                $this->get(route('v2.screen', 'inv-'.strtolower($class).'-stock-'.$kind))->assertOk();
                $this->get(route('v2.screen', 'inv-stock-doc-form').'?kind='.$kind.'&class='.$class.'&new=1')->assertOk()->assertSee('New Stock '.ucfirst($kind));
            }
        }
        $this->get(route('v2.screen', 'inv-stock-doc-form').'?kind=out&class=FG&new=1')->assertNotFound();

        // Stock In lewat route.
        $in = $this->docHeader() + ['stock_kind' => 'in', 'stock_class' => 'RM', 'lines' => [$this->line('RM', ['qty' => '4'])]];
        $this->post(route('v2.inv.stockdoc.save'), $in)->assertRedirect();
        $h = RmStock::where('notes', 'ZZTEST stock doc')->firstOrFail();
        $form = route('v2.screen', 'inv-stock-doc-form').'?kind=in&class=RM&id='.$h->doc_id;
        $this->get($form)->assertOk()->assertSee($h->doc_no)->assertSee('Confirm');
        $this->get(route('v2.screen', 'inv-stock-doc-form').'?kind=out&class=RM&id='.$h->doc_id)->assertNotFound();

        $this->post(route('v2.inv.stockdoc.save'), $in + ['id' => $h->doc_id, 'action' => 'confirm'])->assertRedirect();
        $this->assertSame('POSTED', $h->fresh()->flow_status);
        $this->get($form)->assertOk()->assertSee('Cancel')->assertSee('Duplicate');
        $this->post(route('v2.inv.stockdoc.reverse', ['in', 'RM', $h->doc_id]), ['reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.inv.stockdoc.reverse', ['in', 'RM', $h->doc_id]), ['reason' => 'ZZTEST'])->assertRedirect();
        $this->assertNotNull($h->fresh()->reversed_by_doc_id);
        $this->post(route('v2.inv.stockdoc.save'), array_merge($in, ['action' => 'save', 'doc_date' => '']))->assertSessionHasErrors('doc_date');

        // Stock Out lewat route: Draft, lalu Confirm yang ditolak (saldo kurang) menyisakan Draft dan pesan di form.
        $out = array_merge($this->outHeader(), ['stock_kind' => 'out', 'stock_class' => 'RM', 'notes' => 'ZZTEST out route', 'lines' => [$this->line('RM', ['qty' => '99999999'])]]);
        $this->post(route('v2.inv.stockdoc.save'), $out)->assertRedirect();
        $o = RmStock::where('notes', 'ZZTEST out route')->firstOrFail();
        $this->post(route('v2.inv.stockdoc.save'), $out + ['id' => $o->doc_id, 'action' => 'confirm'])->assertSessionHasErrors('document');
        $this->assertSame('DRAFT', $o->fresh()->flow_status);
        $this->get(route('v2.screen', 'inv-stock-doc-form').'?kind=out&class=RM&id='.$o->doc_id)->assertOk()->assertSee($o->doc_no)->assertSee('Issued to');
        $this->post(route('v2.inv.stockdoc.save'), ['id' => $o->doc_id, 'stock_kind' => 'out', 'stock_class' => 'RM', 'action' => 'cancel'])->assertRedirect();
        $this->assertSame('CANCELLED', $o->fresh()->flow_status);

        $this->post(route('v2.inv.stockdoc.save'), ['stock_kind' => 'out', 'stock_class' => 'FG'])->assertNotFound();
        $this->post(route('v2.inv.stockdoc.save'), ['stock_kind' => 'in', 'stock_class' => 'XX'])->assertNotFound();
    }

    public function test_grid_and_v1_state(): void
    {
        foreach (['in' => ['RM', 'FG', 'SFG'], 'out' => ['RM', 'SFG']] as $kind => $classes) {
            foreach ($classes as $class) {
                $grid = Grids::find('inv-'.strtolower($class).'-stock-'.$kind);
                $this->assertStringContainsString('/v2/s/inv-stock-doc-form?kind='.$kind.'&class='.$class.'&id={id}', $grid->openUrl());
                $this->assertStringContainsString('/v2/s/inv-stock-doc-form?kind='.$kind.'&class='.$class.'&new=1', $grid->newUrl());
            }
        }
        $this->assertNull(Grids::find('inv-fg-stock-out')->newUrl(), 'FG tidak punya Stock Out');
        // Return dan Adjustment belum dipindah: tetap halaman v1.
        $this->assertStringContainsString('/inventory/stock-return/RM', Grids::find('inv-rm-stock-return')->newUrl());

        foreach (['stock-in', 'stock-out'] as $v1) {
            $this->assertFileDoesNotExist(resource_path('views/pages/inventory/'.$v1.'.blade.php'));
        }
        foreach (['INV_RM_ITEM1', 'INV_FG_ITEM1', 'INV_SFG_ITEM1', 'INV_RM_ITEM2', 'INV_SFG_ITEM2'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
        }
        $this->actingAs($this->admin());
        $this->get('/inventory/stock-in/RM')->assertRedirect('/v2/s/inv-rm-stock-in');
        $this->get('/inventory/stock-in/FG?new=1')->assertRedirect('/v2/s/inv-stock-doc-form?kind=in&class=FG&new=1');
        $this->get('/inventory/stock-in/SFG?open=12')->assertRedirect('/v2/s/inv-stock-doc-form?kind=in&class=SFG&id=12');
        $this->get('/inventory/stock-out/RM')->assertRedirect('/v2/s/inv-rm-stock-out');
        $this->get('/inventory/stock-out/SFG?new=1')->assertRedirect('/v2/s/inv-stock-doc-form?kind=out&class=SFG&new=1');
        $this->get('/inventory/stock-out/RM?open=7')->assertRedirect('/v2/s/inv-stock-doc-form?kind=out&class=RM&id=7');
    }
}
