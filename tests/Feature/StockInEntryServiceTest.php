<?php

/*
 * REMARK
 * Tujuan       : Menjaga Stock In v2: simpan Draft (nomor, baris, batch baru, aturan header dan baris), Posted tidak bisa diubah, Confirm memposting ke Kartu Stok, Cancel dengan pembalik, Cancel Draft, Duplicate, kelas FG dan SFG, route dan layar, dan tidak ada jalur ke halaman v1 inventory.stock-in.
 * Sumber hulu  : App\Services\Inventory\StockInEntryService, StockPostingService, StockReversalService, layar inv-stock-in-form, grid inv-*-stock-in, routes/v2_stockdocs.php.
 * Input        : User johiwan (admin); item, bin dan gudang yang sudah ada di database. Seluruh uji berjalan di transaksi tm2inv / tm2acc / tm2mst yang di-rollback, jadi posting ke Kartu Stok dan batch uji tidak tersisa.
 * Output       : Lulus bila aturan terpenuhi dan database kembali seperti semula.
 * Dipakai oleh : php artisan test --filter=StockInEntryServiceTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\RmStockcard;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Sys\SecUser;
use App\Services\Inventory\StockInEntryService;
use App\Services\Inventory\StockReversalService;
use App\Support\Menu\MenuRoutes;
use App\Support\V2\Data\StockEntry;
use App\Support\V2\Grid\Grids;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockInEntryServiceTest extends TestCase
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
        return $over + ['doc_date' => now()->toDateString(), 'doc_cat' => 'STOCK', 'warehouse_id' => $this->bin()->warehouse_id, 'notes' => 'ZZTEST stock in'];
    }

    private function line(string $class, array $over = []): array
    {
        return $over + ['item_id' => $this->item($class), 'location_id' => $this->bin()->location_id, 'qty' => '5', 'unit_price' => '12,5', 'uom_code' => '', 'batch_id' => '', 'new_lot' => '', 'new_production' => '', 'new_expiry' => ''];
    }

    public function test_draft_is_saved_with_number_lines_and_new_batch(): void
    {
        $svc = app(StockInEntryService::class);
        $id = $svc->save('RM', null, $this->docHeader(), [$this->line('RM', ['new_lot' => 'ZZLOT-1', 'new_production' => '2026-10-01', 'new_expiry' => '2027-10-01']), [], $this->line('RM', ['qty' => '2'])], $this->admin());

        $h = RmStock::with('lines')->findOrFail($id);
        $this->assertMatchesRegularExpression('#^IN/\d{2}/\d{5}$#', $h->doc_no);
        $this->assertSame('DRAFT', $h->flow_status);
        $this->assertSame('MANUAL', $h->ref_doc_type);
        $this->assertSame(1, (int) $h->doc_type);
        $this->assertSame('ZZTEST stock in', $h->notes);
        $this->assertCount(2, $h->lines, 'baris kosong dibuang');
        $first = $h->lines[0];
        $this->assertSame(5.0, (float) $first->qty);
        $this->assertSame(12.5, (float) $first->unit_price);
        $this->assertSame('ZZLOT-1', $first->lot_no);
        $batch = InvBatch::findOrFail($first->batch_id);
        $this->assertSame('2027-10-01', $batch->expiry_date->toDateString());
        $this->assertNotSame('', (string) $first->uom_code, 'satuan ikut item bila kosong');

        // Simpan ulang Draft yang sama: nomor tetap, baris diganti, lot yang sama memakai batch yang sama.
        $id2 = $svc->save('RM', $id, $this->docHeader(['notes' => 'changed']), [$this->line('RM', ['new_lot' => 'ZZLOT-1', 'new_expiry' => '2027-10-01'])], $this->admin());
        $this->assertSame($id, $id2);
        $h2 = RmStock::with('lines')->findOrFail($id);
        $this->assertSame($h->doc_no, $h2->doc_no);
        $this->assertCount(1, $h2->lines);
        $this->assertSame($batch->id_batch, (int) $h2->lines[0]->batch_id, 'lot sama = batch sama');
        $this->assertSame('changed', $h2->notes);
    }

    public function test_rules_are_refused(): void
    {
        $svc = app(StockInEntryService::class);
        $other = WhLoc::where('activate', true)->where('is_usable', true)->where('warehouse_id', '!=', $this->bin()->warehouse_id)->first();
        $cases = [
            'no lines' => [$this->docHeader(), []],
            'blank date' => [$this->docHeader(['doc_date' => '']), [$this->line('RM')]],
            'bad category' => [$this->docHeader(['doc_cat' => 'OP']), [$this->line('RM')]],
            'no warehouse' => [$this->docHeader(['warehouse_id' => '']), [$this->line('RM')]],
            'no item' => [$this->docHeader(), [$this->line('RM', ['item_id' => '', 'qty' => '3'])]],
            'zero qty' => [$this->docHeader(), [$this->line('RM', ['qty' => '0'])]],
            'no bin' => [$this->docHeader(), [$this->line('RM', ['location_id' => ''])]],
        ];
        if ($other) {
            $cases['bin of another warehouse'] = [$this->docHeader(), [$this->line('RM', ['location_id' => $other->location_id])]];
        }
        $shelf = MatRaw::where('activate', true)->where('shelf_life_days', '>', 0)->value('material_id');
        if ($shelf) {
            $cases['shelf life without expiry'] = [$this->docHeader(), [$this->line('RM', ['item_id' => $shelf, 'new_lot' => 'ZZ'])]];
        }
        foreach ($cases as $name => [$head, $lines]) {
            try {
                $svc->save('RM', null, $head, $lines, $this->admin());
                $this->fail('harus ditolak: '.$name);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors(), $name);
            }
        }
        $this->assertSame(0, RmStock::where('notes', 'ZZTEST stock in')->count(), 'penolakan tidak boleh meninggalkan dokumen');
    }

    public function test_confirm_posts_to_the_stock_card_then_cancel_reverses_it(): void
    {
        $svc = app(StockInEntryService::class);
        $user = $this->admin();
        $id = $svc->save('RM', null, $this->docHeader(), [$this->line('RM', ['qty' => '7'])], $user);
        $before = RmStockcard::count();

        $res = $svc->post('RM', $id, $user);
        $this->assertTrue($res['posted'], (string) ($res['error'] ?? ''));
        $h = RmStock::with('lines')->findOrFail($id);
        $this->assertSame('POSTED', $h->flow_status);
        $this->assertTrue((bool) $h->stat_confirm);
        $this->assertSame($before + 1, RmStockcard::count());
        $this->assertSame(['confirmed', 'Posted'], $svc->formState($h));
        $this->assertFalse($svc->editable($h));

        try {
            $svc->save('RM', $id, $this->docHeader(), [$this->line('RM')], $user);
            $this->fail('dokumen Posted tidak boleh diubah');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }

        $rv = app(StockReversalService::class);
        $this->assertTrue($rv->state('RM', $id)['can']);
        $r = $rv->reverse('RM', $id, 'ZZTEST reason', $user->login);
        $this->assertTrue($r['ok'], (string) $r['error']);
        $h = RmStock::findOrFail($id);
        $this->assertSame(['cancelled', 'Reversed'], $svc->formState($h));
        $copy = $rv->duplicate('RM', $id, $user->login);
        $this->assertNotNull($copy);
        $this->assertSame('DRAFT', RmStock::findOrFail($copy)->flow_status);
    }

    public function test_draft_can_be_cancelled_and_other_classes_work(): void
    {
        $svc = app(StockInEntryService::class);
        $user = $this->admin();
        $id = $svc->save('RM', null, $this->docHeader(), [$this->line('RM')], $user);
        $svc->cancelDraft('RM', $id, $user);
        $h = RmStock::findOrFail($id);
        $this->assertSame(['cancelled', null], $svc->formState($h));

        foreach (['FG', 'SFG'] as $class) {
            $did = $svc->save($class, null, $this->docHeader(), [$this->line($class, ['qty' => '3'])], $user);
            $model = ['FG' => \App\Models\Inventory\FgStock::class, 'SFG' => \App\Models\Inventory\SfgStock::class][$class];
            $doc = $model::with('lines')->findOrFail($did);
            $this->assertSame('DRAFT', $doc->flow_status, $class);
            $this->assertCount(1, $doc->lines, $class);
            $this->assertSame(3.0, (float) $doc->lines[0]->qty, $class);
        }
    }

    public function test_forms_and_routes_work_for_admin(): void
    {
        $this->actingAs($this->admin());
        foreach (['RM', 'FG', 'SFG'] as $class) {
            $this->get(route('v2.screen', 'inv-'.strtolower($class).'-stock-in'))->assertOk();
            $this->get(route('v2.screen', 'inv-stock-in-form').'?class='.$class.'&new=1')->assertOk()->assertSee('New Stock In');
        }

        $in = $this->docHeader() + ['stock_class' => 'RM', 'lines' => [$this->line('RM', ['qty' => '4'])]];
        $this->post(route('v2.inv.stockin.save'), $in)->assertRedirect();
        $h = RmStock::where('notes', 'ZZTEST stock in')->firstOrFail();
        $this->get(route('v2.screen', 'inv-stock-in-form').'?class=RM&id='.$h->doc_id)->assertOk()->assertSee($h->doc_no)->assertSee('Confirm');

        $this->post(route('v2.inv.stockin.save'), $in + ['id' => $h->doc_id, 'action' => 'confirm'])->assertRedirect();
        $this->assertSame('POSTED', $h->fresh()->flow_status);
        $page = $this->get(route('v2.screen', 'inv-stock-in-form').'?class=RM&id='.$h->doc_id)->assertOk();
        $page->assertSee('Cancel')->assertSee('Duplicate');

        $this->post(route('v2.inv.stockin.reverse', ['RM', $h->doc_id]), ['reason' => ''])->assertSessionHasErrors('document');
        $this->post(route('v2.inv.stockin.reverse', ['RM', $h->doc_id]), ['reason' => 'ZZTEST'])->assertRedirect();
        $this->assertNotNull($h->fresh()->reversed_by_doc_id);

        $this->post(route('v2.inv.stockin.save'), array_merge($in, ['action' => 'save', 'doc_date' => '']))->assertSessionHasErrors('doc_date');
        $this->post(route('v2.inv.stockin.save'), ['stock_class' => 'XX'])->assertNotFound();
    }

    public function test_grid_and_v1_state(): void
    {
        foreach (['RM', 'FG', 'SFG'] as $class) {
            $grid = Grids::find('inv-'.strtolower($class).'-stock-in');
            $this->assertStringContainsString('/v2/s/inv-stock-in-form?class='.$class.'&id={id}', $grid->openUrl());
            $this->assertStringContainsString('/v2/s/inv-stock-in-form?class='.$class.'&new=1', $grid->newUrl());
        }
        // Jenis lain belum dipindah: tetap membuka halaman v1.
        $this->assertStringContainsString('/inventory/stock-out/RM', Grids::find('inv-rm-stock-out')->newUrl());

        $this->assertFileDoesNotExist(resource_path('views/pages/inventory/stock-in.blade.php'));
        foreach (['INV_RM_ITEM1', 'INV_FG_ITEM1', 'INV_SFG_ITEM1'] as $code) {
            $this->assertArrayNotHasKey($code, MenuRoutes::map());
        }
        $this->actingAs($this->admin());
        $this->get('/inventory/stock-in/RM')->assertRedirect('/v2/s/inv-rm-stock-in');
        $this->get('/inventory/stock-in/FG?new=1')->assertRedirect('/v2/s/inv-stock-in-form?class=FG&new=1');
        $this->get('/inventory/stock-in/SFG?open=12')->assertRedirect('/v2/s/inv-stock-in-form?class=SFG&id=12');
    }
}
