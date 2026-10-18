<?php

namespace Tests\Feature;

use App\Models\Sdi\CoOrder;
use App\Models\Sys\SecUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\WoFixture;
use Tests\TestCase;

// Expand-row master-detail grid CO (co/list.blade.php): menampilkan item DAN External Cost.
// CO dan ongkos kirimnya dibangun tes ini sendiri (WoFixture) dan dihapus di tearDown.
class CoListExpandRowTest extends TestCase
{
    use WoFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWoFixture();
    }

    protected function tearDown(): void
    {
        $this->tearDownWoFixture();
        parent::tearDown();
    }

    public function test_expand_row_shows_items_and_external_costs(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail());
        $chain = $this->seedChain('CO1');
        DB::connection('tm2sdi')->table('co_order_external_cost')->insert([
            'order_id' => $chain['order_id'], 'category' => 'Trucking', 'description' => 'Ongkos kirim ke gudang customer',
            'nominal' => 750000, 'tax_treatment' => 'TAXABLE', 'created_by' => 'test',
        ]);
        $order = CoOrder::findOrFail($chain['order_id']);

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
