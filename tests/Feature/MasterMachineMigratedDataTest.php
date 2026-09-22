<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Master Mesin (master.machine) sudah live sebelumnya -- test ini cuma
// verifikasi layar existing tetap render normal setelah isinya diganti daftar
// aset mesin nyata (migration 2026_09_21_000007): mesin berkode prefix jenis
// (OFF/DIG/DIE/...), dan enum machineTypes pindah ke 18 jenis bahasa Inggris
// (tipe lama "Subkontraktor" dicabut -- subkontraktor dipegang
// sup_supplier.is_subcon, bukan mesin).
//
// 2026-09-22: tiga harapan basi dicabut, semuanya akibat perubahan yang
// memang disengaja sesudah test ini ditulis -- bukan layarnya yang rusak:
//   'FUJI'       -> 29 baris legacy awalnya cuma dinonaktifkan, lalu DIHAPUS
//                   migration 2026_09_22_000008 ("data legacy salah HAPUS
//                   bukan dinonaktifkan").
//   '27/09/2015' -> grid cuma menampilkan year_acquired dalam kurung di baris
//                   Merk; acquired_date lengkap hanya ada di form.
//   'Total Mesin'-> kotak statistik berganti label jadi 'Total Unit Mesin'.
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
            ->assertSee('(2015)')       // year_acquired tampil di baris Merk
            ->assertSee('Total Unit Mesin');
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
