<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrderItem;
use App\Models\Sdi\DoLine;
use Tests\TestCase;

// Qty Minim = harga borongan (user 2026-10-02, sama dengan sistem lama): nilai baris = harga, bukan qty x harga.
// Tanpa menulis ke database: baris disusun di memori.
class QtyMinimLumpSumTest extends TestCase
{
    public function test_order_line_with_qty_minim_is_worth_its_price(): void
    {
        $item = new CoOrderItem(['qty' => 573, 'unit_price' => 798142, 'discount_pct' => 0, 'qty_minim' => true]);
        $item->recalcSubtotal();

        $this->assertSame(798142.0, (float) $item->subtotal);
    }

    public function test_order_line_without_qty_minim_is_qty_times_price(): void
    {
        $item = new CoOrderItem(['qty' => 508, 'unit_price' => 7200, 'discount_pct' => 0, 'qty_minim' => false]);
        $item->recalcSubtotal();

        $this->assertSame(3657600.0, (float) $item->subtotal);
    }

    public function test_line_discount_still_applies_to_a_lump_sum_line(): void
    {
        $item = new CoOrderItem(['qty' => 573, 'unit_price' => 800000, 'discount_pct' => 10, 'qty_minim' => true]);
        $item->recalcSubtotal();

        $this->assertSame(720000.0, (float) $item->subtotal);
    }

    public function test_delivery_line_of_a_lump_sum_order_line_is_worth_its_price(): void
    {
        $line = new DoLine(['co_order_item_id' => 1, 'qty_do' => 300, 'unit_price' => 798142]);
        $line->setRelation('orderItem', new CoOrderItem(['qty_minim' => true]));
        $line->recalcSubtotal();

        $this->assertSame(798142.0, (float) $line->subtotal);
    }

    public function test_delivery_line_of_a_normal_order_line_is_qty_times_price(): void
    {
        $line = new DoLine(['co_order_item_id' => 1, 'qty_do' => 508, 'unit_price' => 7200]);
        $line->setRelation('orderItem', new CoOrderItem(['qty_minim' => false]));
        $line->recalcSubtotal();

        $this->assertSame(3657600.0, (float) $line->subtotal);
    }
}
