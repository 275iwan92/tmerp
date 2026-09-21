<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Master Mesin (master.machine) sudah live sebelumnya -- test ini cuma
// verifikasi layar existing tetap render normal setelah isinya diganti daftar
// aset mesin nyata (migration 2026_09_21_000007): 46 mesin aktif berkode
// prefix jenis (OFF/DIG/DIE/...) + 29 baris lama dinonaktifkan, dan enum
// machineTypes pindah ke 18 jenis bahasa Inggris (tipe lama "Subkontraktor"
// dicabut -- subkontraktor dipegang sup_supplier.is_subcon, bukan mesin).
class MasterMachineMigratedDataTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_grid_renders_asset_machines_with_new_codes(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::master.machine')
            ->assertSee('OFF-02')
            ->assertSee('Komori GL440')
            ->assertSee('27/09/2015')   // acquired_date, bukan cuma tahun
            ->assertSee('FUJI')         // baris lama nonaktif tetap tampil di grid
            ->assertSee('Total Mesin');
    }

    public function test_new_form_offers_english_machine_types(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::master.machine')
            ->call('openModal', 'new')
            ->assertSee('Offset Printing')
            ->assertSee('Die Cutting')      // dipisah dari Paper Cutting
            ->assertSee('Paper Cutting')
            ->assertSee('Support Equipment')
            ->assertDontSee('Subkontraktor');
    }
}
