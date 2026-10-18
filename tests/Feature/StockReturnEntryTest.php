<?php

/*
 * REMARK
 * Tujuan       : Menjaga Return from Production v2 (RM, SFG): baris dari Stock Out sumber, aturan qty / kondisi / bin / Description, harga dari kartu sumber, qty_returned bertambah di transaksi posting (A), dipulihkan saat Return dibatalkan (B), Stock Out yang sudah punya Return tidak boleh dibatalkan (C), Return melebihi sisa ditolak saat Confirm, kartu awal, route, layar, grid, redirect v1 dan menu.
 * Sumber hulu  : App\Services\Inventory\StockDocEntryService (kind return), StockPostingService::addReturned, StockReversalService (returnedRefusal, restoreReturned, duplicate), layar inv-stock-doc-form, routes/v2_stockdocs.php.
 * Input        : User johiwan (admin); item, bin dan gudang yang sudah ada. Seluruh uji berjalan di transaksi tm2inv / tm2acc / tm2mst yang di-rollback (bin scrap uji dibuat di dalamnya), jadi Kartu Stok dan dokumen uji tidak tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=StockReturnEntryTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Inventory\RmStockD;
use App\Models\Inventory\SfgStock;
use App\Models\Master\OrgDepartment;
use App\Models\Master\WhLoc;
use App\Models\Sys\SecUser;
use App\Services\Inventory\StockDocEntryService;
use App\Services\Inventory\StockPostingService;
use App\Services\Inventory\StockReversalService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\StockEntry;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockReturnEntryTest extends TestCase
{
    private const CONNECTIONS = ['tm2inv', 'tm2acc', 'tm2mst'];

    private ?WhLoc $scrap = null;

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

    private function scrapBin(): WhLoc
    {
        return $this->scrap ??= WhLoc::where('activate', true)->where('is_usable', false)->first()
            ?? WhLoc::create(['warehouse_id' => $this->bin()->warehouse_id, 'location_code' => 'ZZRTN-SCRAP', 'row_code' => 'ZZRTN', 'col_code' => '1', 'stack_code' => '1', 'is_usable' => false, 'activate' => true, 'created_by' => 'test', 'updated_by' => 'test']);
    }

    private function item(string $class): int
    {
        return StockEntry::itemSearch($class, '', 1)[0]['id'];
    }

    private function hdr(array $over = []): array
    {
        return $over + ['doc_date' => now()->toDateString(), 'warehouse_id' => $this->bin()->warehouse_id, 'notes' => 'ZZTEST return'];
    }

    // Stock In 10 lalu Stock Out `$out` (Posted); mengembalikan [id Stock Out, id baris Stock Out].
    private function postedOut(string $class = 'RM', float $out = 10): array
    {
        $svc = app(StockDocEntryService::class);
        $user = $this->admin();
        $line = ['item_id' => $this->item($class), 'location_id' => $this->bin()->location_id, 'uom_code' => '', 'unit_price' => '20', 'batch_id' => '', 'lot_no' => ''];
        $in = $svc->save('in', $class, null, ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->bin()->warehouse_id], [$line + ['qty' => '50']], $user);
        $this->assertTrue($svc->post($class, $in, $user)['posted']);
        $id = $svc->save('out', $class, null, ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->bin()->warehouse_id, 'issued_to_dept' => OrgDepartment::where('activate', true)->value('department_id'), 'process_code' => 'ZZ process'],
            [$line + ['qty' => (string) $out]], $user);
        $res = $svc->post($class, $id, $user);
        $this->assertTrue($res['posted'], (string) ($res['error'] ?? ''));
        $model = ['RM' => RmStock::class, 'SFG' => SfgStock::class][$class];

        return [$id, $model::with('lines')->findOrFail($id)->lines[0]->getKey()];
    }

    private function retLine(int $lineId, array $over = []): array
    {
        return [$lineId => $over + ['qty' => '4', 'return_condition' => 'GOOD', 'location_id' => $this->bin()->location_id, 'line_note' => '']];
    }

    private function saveReturn(string $class, int $outId, array $lines, array $headOver = [], ?int $docId = null): int
    {
        return app(StockDocEntryService::class)->save('return', $class, $docId, $this->hdr(array_merge(['source_doc_id' => $outId], $headOver)), $lines, $this->admin());
    }

    private function qtyReturned(int $lineId): float
    {
        return (float) RmStockD::findOrFail($lineId)->qty_returned;
    }

    public function test_return_draft_is_saved_from_the_stock_out_lines(): void
    {
        [$outId, $lineId] = $this->postedOut();
        $id = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '4', 'line_note' => 'ZZ note']));
        $h = RmStock::with('lines')->findOrFail($id);

        $this->assertMatchesRegularExpression('#^RTN/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame('RETURN', $h->doc_cat);
        $this->assertSame(1, (int) $h->doc_type);
        $this->assertSame('OUT', $h->ref_doc_type);
        $this->assertSame($outId, (int) $h->ref_doc_id);
        $this->assertSame(RmStock::findOrFail($outId)->doc_no, $h->ref_doc_no);
        $this->assertSame('ZZ process', $h->process_code, 'proses ikut dari Stock Out');
        $this->assertSame('DRAFT', $h->flow_status);
        $l = $h->lines[0];
        $this->assertSame('RETURN', $l->action_type);
        $this->assertSame('GOOD', $l->return_condition);
        $this->assertSame($lineId, (int) $l->source_detail_id);
        $this->assertSame(4.0, (float) $l->qty);
        $this->assertSame('ZZ note', $l->line_note, 'Description tersimpan (v1 membuangnya)');
        $card = RmStockcard::where('reference_name', app(StockPostingService::class)->referenceName('RM'))->where('reference_detail_id', $lineId)->value('avg_unitprice');
        $this->assertSame((float) $card, (float) $l->unit_price, 'harga = rata-rata kartu baris sumber');
        $this->assertSame(0.0, $this->qtyReturned($lineId), 'Draft belum mengurangi sisa');

        // Simpan ulang Draft yang sama: nomor tetap, baris diganti.
        $id2 = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '2']), [], $id);
        $this->assertSame($id, $id2);
        $this->assertSame(2.0, (float) RmStock::with('lines')->findOrFail($id)->lines[0]->qty);
        $rows = app(StockDocEntryService::class)->returnRows('RM', RmStock::with('lines')->findOrFail($outId), RmStock::with('lines')->findOrFail($id));
        $this->assertSame(2.0, $rows[0]['qty']);
        $this->assertSame(10.0, $rows[0]['issued']);
    }

    public function test_return_rules_are_refused(): void
    {
        [$outId, $lineId] = $this->postedOut();
        $other = WhLoc::where('activate', true)->where('is_usable', true)->where('warehouse_id', '!=', $this->bin()->warehouse_id)->first();
        $cases = [
            'no source' => [['source_doc_id' => 0], $this->retLine($lineId)],
            'unknown source line only' => [[], [999999999 => ['qty' => '1', 'return_condition' => 'GOOD', 'location_id' => $this->bin()->location_id]]],
            'no qty on any line' => [[], $this->retLine($lineId, ['qty' => '0'])],
            'more than returnable' => [[], $this->retLine($lineId, ['qty' => '10,5'])],
            'negative qty' => [[], $this->retLine($lineId, ['qty' => '-1'])],
            'scrap without description' => [[], $this->retLine($lineId, ['return_condition' => 'SCRAP', 'location_id' => $this->scrapBin()->location_id])],
            'scrap into usable bin' => [[], $this->retLine($lineId, ['return_condition' => 'SCRAP', 'line_note' => 'x'])],
            'good into scrap bin' => [[], $this->retLine($lineId, ['location_id' => $this->scrapBin()->location_id])],
            'no bin' => [[], $this->retLine($lineId, ['location_id' => ''])],
            'no warehouse' => [['warehouse_id' => ''], $this->retLine($lineId)],
            'no date' => [['doc_date' => ''], $this->retLine($lineId)],
        ];
        if ($other) {
            $cases['good into bin of another warehouse'] = [[], $this->retLine($lineId, ['location_id' => $other->location_id])];
        }
        foreach ($cases as $name => [$headOver, $lines]) {
            try {
                $this->saveReturn('RM', $outId, $lines, $headOver);
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame(0, RmStock::where('doc_cat', 'RETURN')->where('notes', 'ZZTEST return')->count(), 'penolakan tidak meninggalkan dokumen');

        // SCRAP dengan Description ke bin non-usable sah.
        $id = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['return_condition' => 'SCRAP', 'location_id' => $this->scrapBin()->location_id, 'line_note' => 'torn']));
        $this->assertSame('SCRAP', RmStock::with('lines')->findOrFail($id)->lines[0]->return_condition);
        $this->assertFalse(StockDocEntryService::supports('return', 'FG'));
    }

    public function test_posting_adds_qty_returned_in_the_same_transaction_and_overdraw_is_refused(): void
    {
        $svc = app(StockDocEntryService::class);
        $user = $this->admin();
        [$outId, $lineId] = $this->postedOut();
        $r1 = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '6']));
        $r2 = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '6']));

        $res = $svc->post('RM', $r1, $user);
        $this->assertTrue($res['posted'], (string) ($res['error'] ?? ''));
        $this->assertSame(6.0, $this->qtyReturned($lineId), 'A: qty_returned naik bersama posting');
        $this->assertSame('POSTED', RmStock::findOrFail($r1)->flow_status);

        $res2 = $svc->post('RM', $r2, $user);
        $this->assertFalse($res2['posted'], 'Return kedua melebihi sisa 4');
        $this->assertStringContainsString('returnable balance', (string) $res2['error']);
        $this->assertSame(6.0, $this->qtyReturned($lineId));
        $this->assertSame('DRAFT', RmStock::findOrFail($r2)->flow_status);

        // Kurangi ke 4 lalu Confirm: pas habis, Stock Out tidak muncul lagi di sumber Return.
        $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '4']), [], $r2);
        $this->assertTrue($svc->post('RM', $r2, $user)['posted']);
        $this->assertSame(10.0, $this->qtyReturned($lineId));
        $this->assertNull($svc->returnableSource('RM', $outId));
        $this->assertNotContains($outId, array_column($svc->returnableSources('RM'), 'id'));
        $this->assertNotNull($svc->returnSource('RM', $outId), 'Return yang tersimpan tetap bisa menampilkan sumbernya');
    }

    public function test_cancelling_a_return_restores_the_balance_and_a_returned_stock_out_cannot_be_cancelled(): void
    {
        $svc = app(StockDocEntryService::class);
        $rv = app(StockReversalService::class);
        $user = $this->admin();
        [$outId, $lineId] = $this->postedOut();
        $r1 = $this->saveReturn('RM', $outId, $this->retLine($lineId, ['qty' => '6']));
        $this->assertTrue($svc->post('RM', $r1, $user)['posted']);
        $this->assertSame(6.0, $this->qtyReturned($lineId));

        // C: Stock Out yang sudah punya Return tidak boleh dibatalkan.
        $state = $rv->state('RM', $outId);
        $this->assertFalse($state['can']);
        $this->assertStringContainsString('has been returned', (string) $state['refusal']);
        $this->assertFalse($rv->reverse('RM', $outId, 'ZZTEST', $user->login)['ok']);
        $this->assertNull(RmStock::findOrFail($outId)->reversed_by_doc_id);

        // B: Return dibatalkan -> sisa pulih; lalu Stock Out boleh dibatalkan.
        $this->assertTrue($rv->reverse('RM', $r1, 'ZZTEST', $user->login)['ok']);
        $this->assertSame(0.0, $this->qtyReturned($lineId));
        $this->assertTrue($rv->state('RM', $outId)['can']);

        // Duplicate Return membawa baris sumber dan Description.
        $copy = $rv->duplicate('RM', $r1, $user->login);
        $line = RmStock::with('lines')->findOrFail($copy)->lines[0];
        $this->assertSame($lineId, (int) $line->source_detail_id);
        $this->assertSame('RETURN', $line->action_type);
    }

    public function test_sfg_return_works(): void
    {
        $svc = app(StockDocEntryService::class);
        [$outId] = $this->postedOut('SFG', 5);
        $line = SfgStock::with('lines')->findOrFail($outId)->lines[0];
        $id = app(StockDocEntryService::class)->save('return', 'SFG', null, $this->hdr(['source_doc_id' => $outId]), [$line->getKey() => ['qty' => '3', 'return_condition' => 'GOOD', 'location_id' => $this->bin()->location_id]], $this->admin());
        $this->assertTrue($svc->post('SFG', $id, $this->admin())['posted']);
        $this->assertSame(3.0, (float) $line->fresh()->qty_returned);
    }

    public function test_screens_routes_grid_and_v1_state(): void
    {
        $this->actingAs($this->admin());
        [$outId, $lineId] = $this->postedOut();
        $form = route('v2.screen', 'inv-stock-doc-form');
        foreach (['RM', 'SFG'] as $class) {
            $this->get(route('v2.screen', 'inv-'.strtolower($class).'-stock-return'))->assertOk();
            $this->get($form.'?kind=return&class='.$class.'&new=1')->assertOk()->assertSee('Start a Return');
        }
        $this->get($form.'?kind=return&class=FG&new=1')->assertNotFound();
        $this->get($form.'?kind=return&class=RM&new=1&source=999999999')->assertNotFound();

        // Kartu awal -> form dengan baris sumber.
        $this->post(route('v2.inv.stockdoc.start', ['return', 'RM']), ['source' => 999999999])->assertSessionHasErrors('source');
        $this->post(route('v2.inv.stockdoc.start', ['return', 'RM']), ['source' => $outId])->assertRedirect($form.'?kind=return&class=RM&new=1&source='.$outId);
        $this->post(route('v2.inv.stockdoc.start', ['out', 'RM']), ['source' => $outId])->assertNotFound();
        $this->get($form.'?kind=return&class=RM&new=1&source='.$outId)->assertOk()->assertSee(RmStock::findOrFail($outId)->doc_no)->assertSee('Return qty')->assertSee('Destination warehouse');

        // Save lewat route -> form -> Confirm -> Cancel.
        $in = $this->hdr(['stock_kind' => 'return', 'stock_class' => 'RM', 'source_doc_id' => $outId, 'lines' => $this->retLine($lineId, ['qty' => '3'])]);
        $this->post(route('v2.inv.stockdoc.save'), $in)->assertRedirect();
        $h = RmStock::where('doc_cat', 'RETURN')->where('notes', 'ZZTEST return')->firstOrFail();
        $open = $form.'?kind=return&class=RM&id='.$h->doc_id;
        $this->get($open)->assertOk()->assertSee($h->doc_no)->assertSee('Confirm');
        $this->get($form.'?kind=in&class=RM&id='.$h->doc_id)->assertNotFound();
        $this->post(route('v2.inv.stockdoc.save'), $in + ['id' => $h->doc_id, 'action' => 'confirm'])->assertRedirect();
        $this->assertSame('POSTED', $h->fresh()->flow_status);
        $this->assertSame(3.0, $this->qtyReturned($lineId));
        $this->get($open)->assertOk()->assertSee('Duplicate');
        $this->get(route('v2.screen', 'inv-stock-doc-form').'?kind=out&class=RM&id='.$outId)->assertOk()->assertSee('has been returned');
        $this->post(route('v2.inv.stockdoc.reverse', ['return', 'RM', $h->doc_id]), ['reason' => 'ZZTEST'])->assertRedirect();
        $this->assertSame(0.0, $this->qtyReturned($lineId));
        $this->post(route('v2.inv.stockdoc.save'), array_merge($in, ['action' => 'save', 'doc_date' => '']))->assertSessionHasErrors('doc_date');

        foreach (['RM', 'SFG'] as $class) {
            $grid = Grids::find('inv-'.strtolower($class).'-stock-return');
            $this->assertStringContainsString('/v2/s/inv-stock-doc-form?kind=return&class='.$class.'&id={id}', $grid->openUrl());
            $this->assertStringContainsString('/v2/s/inv-stock-doc-form?kind=return&class='.$class.'&new=1', $grid->newUrl());
        }
        $this->assertNull(Grids::find('inv-fg-stock-return')->newUrl(), 'FG tidak punya Return');
        $this->assertFileDoesNotExist(resource_path('views/pages/inventory/stock-return.blade.php'));
        $this->assertFileExists(base_path('tmerp_unused/inventory_stock_return_v1_2026-10-19.blade.php'));
        foreach (['INV_RM_ITEM3', 'INV_SFG_ITEM3'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
        }
        $this->get('/inventory/stock-return/RM')->assertRedirect('/v2/s/inv-rm-stock-return');
        $this->get('/inventory/stock-return/SFG?new=1')->assertRedirect('/v2/s/inv-stock-doc-form?kind=return&class=SFG&new=1');
        $this->get('/inventory/stock-return/RM?open=7')->assertRedirect('/v2/s/inv-stock-doc-form?kind=return&class=RM&id=7');
    }
}
