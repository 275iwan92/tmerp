<?php

namespace Tests\Feature;

use App\Models\Master\ProdProcess;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// Master Proses (PROD_MST_PROCESS) -- grid+form baru, data riil dimigrasi dari
// tm2old.m_process (2026_08_28_000027_migrate_process_master_from_tm2old.php).
class ProductionMasterProcessTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    public function test_grid_renders_migrated_processes(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.master-process')
            ->assertSee('CETAK')
            ->assertSee('LAMINASI')
            ->assertSee('Total Proses');
    }

    public function test_new_form_requires_process_name(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.master-process')
            ->call('openModal', 'new')
            ->assertSet('panel', 'form')
            ->set('process_name', '')
            ->call('save')
            ->assertHasErrors(['process_name']);
    }

    public function test_edit_form_rejects_duplicate_name(): void
    {
        $this->actingAs($this->admin());
        $target = ProdProcess::where('process_name', 'LAMINASI')->firstOrFail();

        Livewire::test('pages::production.master-process')
            ->call('openModal', 'edit', $target->id_process)
            ->set('process_name', 'CETAK')
            ->call('save')
            ->assertHasErrors(['process_name']);
    }
}
