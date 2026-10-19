<?php

/*
 * REMARK
 * Tujuan       : Menjaga bahwa Master Machine v2 menampilkan daftar aset mesin nyata (kode berprefix jenis OFF / DIG / DIE ...) dan form baru menawarkan 18 jenis mesin English (tipe lama "Subkontraktor" dicabut: subkontraktor dipegang sup_supplier.is_subcon, bukan mesin).
 * Sumber hulu  : Grid MachineGrid (prod-machine), layar prod-machine-form, MachineMasterService::TYPES; data mesin real (hanya dibaca).
 * Input        : User johiwan (admin).
 * Output       : Lulus bila grid dan form baru tampil seperti diharapkan.
 * Dipakai oleh : php artisan test --filter=MasterMachineMigratedDataTest
 * Status       : Aktif; ditulis ulang 2026-10-19 untuk layar v2 (halaman v1 pages::master.machine dipindah ke tmerp_unused, Master T6b5).
 */

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Sys\SecUser;
use Tests\TestCase;

class MasterMachineMigratedDataTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_grid_renders_asset_machines_with_new_codes(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('v2.screen', 'prod-machine'))->assertOk();
        $this->assertTrue(ProdMachine::where('machine_code', 'OFF-02')->exists());
    }

    public function test_new_form_offers_english_machine_types(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('v2.screen', 'prod-machine-form').'?new=1')->assertOk()
            ->assertSee('Offset Printing')->assertSee('Die Cutting')->assertSee('Paper Cutting')->assertSee('Support Equipment')->assertDontSee('Subkontraktor');
    }
}
