<?php

namespace Tests\Feature;

use App\Services\Inventory\ItemAverageCost;
use App\Services\Inventory\StockReversalService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Average per item (INV_AVG_SCOPE = ITEM, D13 / D15): satu Avg per item lintas bin; BIN = perilaku lama.
class AvgPerItemTest extends AvgPerItemBase
{
    public function test_t1_second_receipt_at_a_higher_price_raises_the_item_average_across_two_bins(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->assertEqualsWithDelta(15000, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->in($this->b, 500, 16500, 4);

        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
    }

    public function test_t2_issue_uses_the_item_average_whichever_bin_is_taken(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->a, 800, 3);
        $this->assertEqualsWithDelta(12400000, $this->lastOutValue(), 0.01);
        $this->out($this->b, 100, 2);
        $this->assertEqualsWithDelta(1550000, $this->lastOutValue(), 0.01);
        $this->assertEqualsWithDelta(23250000 - 12400000 - 1550000, $this->itemValue(), 0.01);
        $this->assertChained($this->a);
        $this->assertChained($this->b);
    }

    public function test_t3_transfer_between_bins_keeps_item_value_and_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->transfer($this->a, $this->b, 300);

        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
        $this->assertEqualsWithDelta(700, (float) $this->cards($this->a)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(800, (float) $this->cards($this->b)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(300 * 15500, (float) $this->cards($this->b)->last()->value_in, 0.01);
        $this->assertChained($this->a);
        $this->assertChained($this->b);
    }

    public function test_t5_customer_owned_stock_is_not_valued_and_does_not_change_the_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->postDoc(1, $this->b, 200, 4, 99999, 'CUSTOMER');

        $last = $this->cards($this->b)->last();
        $this->assertEqualsWithDelta(15000, (float) $last->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(0, (float) $last->value_eb, 0.01);
        $this->assertEqualsWithDelta(200, (float) $last->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(15000000, $this->itemValue(), 0.01);
    }

    public function test_t6_reversal_of_an_issue_keeps_the_card_chained_and_the_average(): void
    {
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $out = $this->out($this->b, 400, 3);

        $r = app(StockReversalService::class)->reverse('RM', $out['doc_id'], 'test', self::BY);
        $this->assertTrue($r['ok'], (string) $r['error']);

        $this->assertChained($this->b);
        $this->assertEqualsWithDelta(500, (float) $this->cards($this->b)->last()->qty_eb, 0.0001);
        $this->assertEqualsWithDelta(15500, (float) $this->cards()->last()->avg_unitprice, 0.0001);
        $this->assertEqualsWithDelta(23250000, $this->itemValue(), 0.01);
    }

    public function test_t7_scope_bin_keeps_the_old_per_bin_average(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->b, 100, 3);

        $this->assertEqualsWithDelta(1650000, $this->lastOutValue(), 0.01, 'Per bin: bin B average stays 16.500.');
    }

    public function test_t8_t9_revalue_command_is_idempotent_and_keeps_the_total_value(): void
    {
        $this->scope('BIN');
        $this->in($this->a, 1000, 15000, 5);
        $this->in($this->b, 500, 16500, 4);
        $this->out($this->b, 100, 3);
        $before = $this->itemValue();
        $snapshot = fn () => $this->cards()->map(fn ($c) => [$c->doc_id, (string) $c->value_eb, (string) $c->avg_unitprice])->all();
        $rows = $snapshot();
        $count = fn () => DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('stock_class', 'RM')->where('item_id', $this->material->material_id)->count();

        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--check' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(0, $count(), '--check writes nothing.');

        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--apply' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
        $this->assertSame(1, $count());
        $open = DB::connection('tm2inv')->table(ItemAverageCost::OPEN_TABLE)->where('stock_class', 'RM')->where('item_id', $this->material->material_id)->first();
        $this->assertEqualsWithDelta(1400, (float) $open->open_qty, 0.0001);
        $this->assertEqualsWithDelta($before, (float) $open->open_value, 0.01);
        $this->assertEqualsWithDelta($before / 1400, (float) $open->open_avg, 0.0001);
        $this->assertSame($rows, $snapshot(), 'History is not recalculated.');

        // Sesudah peralihan: keluar dari bin mana pun memakai Avg item dan total nilai konsisten.
        $this->scope('ITEM');
        $this->out($this->a, 140, 1);
        $this->assertEqualsWithDelta(140 * $before / 1400, $this->lastOutValue(), 0.01);
        $this->assertEqualsWithDelta($before * 0.9, $this->itemValue(), 0.01);
        $this->assertSame(0, Artisan::call('inv:revalue-per-item', ['--check' => true, '--class' => 'RM', '--item' => $this->material->material_id]));
    }

    public function test_backdated_receipt_reprices_the_later_issue_and_reports_it(): void
    {
        $this->in($this->a, 1000, 15000, 6);
        $this->in($this->b, 500, 16500, 5);
        $this->out($this->a, 800, 3);
        $r = $this->in($this->b, 600, 18000, 4);

        $this->assertEqualsWithDelta(800 * 34050000 / 2100, $this->lastOutValue(), 0.01);
        $this->assertSame(1, $r['revaluation']['rows']);
        $this->assertEqualsWithDelta(800 * 34050000 / 2100 - 12400000, $r['revaluation']['value'], 0.01);
        $this->assertEqualsWithDelta(34050000 - 800 * 34050000 / 2100, $this->itemValue(), 0.01);
    }
}
