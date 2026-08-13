<?php

namespace Tests\Feature;

use App\Models\Master\CustCustomer;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

class MasterCustomerSmokeTest extends TestCase
{
    protected ?CustCustomer $customer = null;

    protected function tearDown(): void
    {
        if ($this->customer) {
            CustCustomer::whereKey($this->customer->id_customer)->delete();
        }

        parent::tearDown();
    }

    public function test_new_customer_panel_opens(): void
    {
        $admin = SecUser::where('login', 'admin')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::master.customer')
            ->call('newCustomer')
            ->assertSet('panel', 'form');
    }

    public function test_address_picker_searches_v_geo_full_address(): void
    {
        $admin = SecUser::where('login', 'admin')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\AddressPicker::class, ['fieldName' => 'test'])
            ->set('search', 'KLOJEN, KOTA MALANG')
            ->assertSet('open', false)
            ->call('select', 53961, 4161, 152453, 'KASIN, KLOJEN, KOTA MALANG, JAWA TIMUR, Indonesia', null)
            ->assertSet('selectedAreaId', 53961)
            ->assertSet('selectedAreaLevel', 'VILLAGE')
            ->assertDispatched('address-picked');
    }

    public function test_form_saves_address_from_picker_event(): void
    {
        $admin = SecUser::where('login', 'admin')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::master.customer')
            ->call('newCustomer')
            ->set('customer_code', 'CU-TEST-ADDR')
            ->set('customer_name', 'Customer Test Address')
            ->dispatch('address-picked',
                fieldName: 'customer-address',
                areaId: 53961,
                areaLevel: 'VILLAGE',
                label: 'KASIN, KLOJEN, KOTA MALANG, JAWA TIMUR, Indonesia',
                postalCode: '65117'
            )
            ->assertSet('default_area_id', 53961)
            ->assertSet('default_area_level', 'VILLAGE')
            ->assertSet('postal_code', '65117')
            ->call('save')
            ->assertHasNoErrors();

        $this->customer = CustCustomer::where('customer_code', 'CU-TEST-ADDR')->firstOrFail();

        $this->assertSame(53961, $this->customer->default_area_id);
        $this->assertSame('VILLAGE', $this->customer->default_area_level);
        $this->assertSame('65117', $this->customer->postal_code);
        $this->assertStringContainsString('KLOJEN, KOTA MALANG', $this->customer->addressLabel());
    }
}
