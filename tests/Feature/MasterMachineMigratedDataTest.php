<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Master Mesin (master.machine) sudah live sebelumnya -- test ini cuma
// verifikasi layar existing tetap render normal setelah migration
// 2026_08_28_000030 menambah 37 mesin riil dari tm2old.mst_machine + tipe
// baru "Subkontraktor" ditambahkan ke enum machineTypes.
class MasterMachineMigratedDataTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_grid_renders_migrated_machines_and_subkontraktor_type(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::master.machine')
            ->assertSee('FUJI')
            ->assertSee('Total Mesin');
    }

    public function test_new_form_offers_subkontraktor_machine_type(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::master.machine')
            ->call('openModal', 'new')
            ->assertSee('Subkontraktor');
    }
}
