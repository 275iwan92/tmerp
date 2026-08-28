<?php

namespace Tests\Feature;

use App\Models\Master\UomConversion;
use App\Models\Master\UomUnit;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

class MasterUomSmokeTest extends TestCase
{
    protected ?UomUnit $unit = null;

    protected ?UomConversion $conversion = null;

    protected function tearDown(): void
    {
        if ($this->conversion) {
            UomConversion::whereKey($this->conversion->id_conversion)->delete();
        }
        if ($this->unit) {
            UomUnit::whereKey($this->unit->id_uom)->delete();
        }

        parent::tearDown();
    }

    public function test_selecting_unit_computes_correct_derived_conversion_factors(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $component = Livewire::test('pages::master.uom')
            ->call('selectUnit', 'KG')
            ->call('openConversionPanel')
            ->assertSet('conversionPanelOpen', true);

        $derived = $component->instance()->derivedConversions();

        $gram = $derived->firstWhere('uom_code', 'GR');
        $ton = $derived->firstWhere('uom_code', 'TON');
        $mg = $derived->firstWhere('uom_code', 'MG');
        $kw = $derived->firstWhere('uom_code', 'KW');

        // Independent expected values (not derived from the same graph-walk code under test):
        // 1 KG = 1000 GR, 1,000,000 MG, 0.01 KW, 0.001 TON.
        $this->assertEqualsWithDelta(1000.0, $gram->value, 0.000001);
        $this->assertEqualsWithDelta(1000000.0, $mg->value, 0.000001);
        $this->assertEqualsWithDelta(0.01, $kw->value, 0.000001);
        $this->assertEqualsWithDelta(0.001, $ton->value, 0.000001);
    }

    public function test_spec_dimension_unit_cannot_open_conversion_panel(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::master.uom')
            ->call('selectUnit', 'GSM')
            ->call('openConversionPanel')
            ->assertSet('conversionPanelOpen', false);
    }

    public function test_new_unit_can_be_created_via_modal(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::master.uom')
            ->call('openUnitModal', 'new')
            ->set('uom_code', 'ik16')
            ->set('dimension', 'JUMLAH')
            ->set('uom_name', 'Ikat isi 16 pcs')
            ->call('saveUnit')
            ->assertHasNoErrors()
            ->assertSet('showUnitModal', false);

        $this->unit = UomUnit::where('uom_code', 'IK16')->firstOrFail();
        $this->assertSame('JUMLAH', $this->unit->dimension);
        $this->assertSame('Ikat isi 16 pcs', $this->unit->uom_name);
    }

    public function test_conversion_between_mismatched_dimensions_is_rejected(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('pages::master.uom')
            ->call('selectUnit', 'KG')
            ->call('openConvModal', 'new')
            ->set('uom_src', 'KG')
            ->set('uom_dst', 'CM') // beda dimensi: MASSA vs PANJANG
            ->set('conversion_value', '5')
            ->call('saveConv')
            ->assertHasErrors('uom_dst');

        $this->assertDatabaseMissing('uom_conversion', ['uom_src' => 'KG', 'uom_dst' => 'CM'], 'tm2mst');
    }

    public function test_new_conversion_saves_and_appears_in_direct_list(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $component = Livewire::test('pages::master.uom')
            ->call('selectUnit', 'INCH')
            ->call('openConvModal', 'new')
            ->set('uom_src', 'INCH')
            ->set('uom_dst', 'CM')
            ->set('conversion_value', '2.54')
            ->call('saveConv')
            ->assertHasNoErrors()
            ->assertSet('showConvModal', false);

        $this->conversion = UomConversion::where('uom_src', 'INCH')->where('uom_dst', 'CM')->firstOrFail();
        $this->assertEqualsWithDelta(2.54, (float) $this->conversion->conversion_value, 0.000001);

        $direct = $component->instance()->directConversions();
        $this->assertTrue($direct->contains(fn ($c) => $c->uom_src === 'INCH' && $c->uom_dst === 'CM'));
    }
}
