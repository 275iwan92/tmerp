<?php

namespace Tests\Feature;

use App\Models\Master\ProdCrew;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Master Crew (PROD_MST_CREW) -- grid+form baru, tabel prod_crew baru, data
// riil dimigrasi dari tm2old.mst_machine (machine_type=CREW).
class ProductionMasterCrewTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_grid_renders_migrated_crew(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.master-crew')
            ->assertSee('ADE FIRDIAN')
            ->assertSee('Total Crew');
    }

    public function test_new_form_requires_crew_name(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.master-crew')
            ->call('openModal', 'new')
            ->assertSet('panel', 'form')
            ->set('crew_name', '')
            ->call('save')
            ->assertHasErrors(['crew_name']);
    }

    public function test_process_link_is_optional_and_saves(): void
    {
        $this->actingAs($this->admin());
        $crew = ProdCrew::whereNull('process_id')->firstOrFail();
        $originalName = $crew->crew_name;

        Livewire::test('pages::production.master-crew')
            ->call('openModal', 'edit', $crew->id_crew)
            ->assertSet('process_id', null)
            ->call('save')
            ->assertHasNoErrors();

        $crew->refresh();
        $this->assertSame($originalName, $crew->crew_name);
    }
}
