<?php

/*
 * REMARK
 * Tujuan       : Menjaga pencarian wilayah AddressPicker (komponen Livewire yang masih dipakai halaman master v1 lain) terhadap v_geo_full_address. Tes form Customer pindah ke CustomerMasterServiceTest sejak Customer v2.
 * Sumber hulu  : App\Livewire\AddressPicker, tm2mst.v_geo_full_address.
 * Input        : User johiwan (admin); hanya membaca.
 * Output       : Lulus bila pemilihan wilayah mengirim event address-picked dengan id, level dan kode pos.
 * Dipakai oleh : php artisan test --filter=MasterCustomerSmokeTest
 * Status       : Aktif
 */

namespace Tests\Feature;

use App\Livewire\AddressPicker;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

class MasterCustomerSmokeTest extends TestCase
{
    public function test_address_picker_searches_v_geo_full_address(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(AddressPicker::class, ['fieldName' => 'test'])
            ->set('search', 'KLOJEN, KOTA MALANG')
            ->assertSet('open', false)
            ->call('select', 53961, 4161, 152453, 'KASIN, KLOJEN, KOTA MALANG, JAWA TIMUR, Indonesia', null)
            ->assertSet('selectedAreaId', 53961)
            ->assertSet('selectedAreaLevel', 'VILLAGE')
            ->assertDispatched('address-picked');
    }
}
