<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Expand-row master-detail grid CO (co/list.blade.php, dibangun 2026-08-24)
// diperluas 2026-08-29 (permintaan user) nampilin External Cost, bukan cuma
// Product/Service items -- dummy data di migration
// 2026_08_29_000001_seed_co_external_cost_dummy.php (CO2600001/order_id=2).
class CoListExpandRowTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_expand_row_shows_items_and_external_costs(): void
    {
        $this->actingAs($this->admin());
        $order = CoOrder::where('co_no', 'CO2600001')->firstOrFail();

        $test = Livewire::test('pages::co.list');

        // Tertutup by default -- detail item/external cost belum kelihatan.
        $test->assertDontSee('Ongkos kirim ke gudang customer');

        $test->call('toggleExpand', $order->id_order)
            ->assertSee('Ongkos kirim ke gudang customer')
            ->assertSee('Trucking')
            ->assertSee('750.000');

        // Toggle lagi -- tertutup balik.
        $test->call('toggleExpand', $order->id_order)
            ->assertDontSee('Ongkos kirim ke gudang customer');
    }
}
