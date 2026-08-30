<?php

namespace Tests\Feature;

use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// MPS - Cards (production.monitoring) dirombak 2026-08-28 jadi Kanban ala
// Trello, direview via mockup HTML (Claude Artifact) sebelum dibangun --
// kolom = mesin/proses, card = 1 prd_wo_operation (bukan 1 WO). Drag antar
// kolom = App\Livewire::moveOperation(). Semua mutasi memulihkan state
// dummy di akhir test (dipakai ulang test lain, pola sama
// ProductionSchedulingBoardSmokeTest).
class ProductionMpsCardsKanbanTest extends TestCase
{
    private function admin(): SecUser
    {
        return SecUser::where('login', 'johiwan')->firstOrFail();
    }

    private function queueOperation(): PrdWoOperation
    {
        return PrdWoOperation::where('resource_type', 'MACHINE')
            ->whereNull('target_start')
            ->where('task_status', 'PENDING')
            ->firstOrFail();
    }

    public function test_board_renders_queue_and_machine_columns(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.monitoring-dashboard')
            ->assertSee('Antrean')
            ->assertSee('Manual')
            ->assertSee('Mesin kosong');
    }

    public function test_dragging_queue_card_onto_machine_assigns_and_schedules_it(): void
    {
        $this->actingAs($this->admin());
        $op = $this->queueOperation();
        $originalMachineId = $op->machine_id; // boleh sudah py saran mesin dari WoGenerationService, boleh null
        $machine = ProdMachine::where('activate', true)->firstOrFail();

        Livewire::test('pages::production.monitoring-dashboard')
            ->call('moveOperation', $op->operation_id, $machine->machine_id);

        $op->refresh();
        $this->assertSame('ASSIGNED', $op->task_status);
        $this->assertSame($machine->machine_id, $op->machine_id);
        $this->assertNotNull($op->target_start);
        $this->assertNotNull($op->target_finish);

        // pulihkan PERSIS ke state semula (termasuk machine_id ASLI-nya --
        // JANGAN dipaksa null, lihat komentar moveOperation() soal kenapa).
        $op->update(['task_status' => 'PENDING', 'machine_id' => $originalMachineId, 'target_start' => null, 'target_finish' => null]);
    }

    // Drag balik ke Antrean TIDAK boleh menghapus machine_id yang sudah
    // ter-pre-fill (saran mesin dari WoGenerationService) -- kalau dihapus,
    // RateBandResolver salah kira task itu "manual" & PdrEngine gagal
    // (bug nyata yang sempat kejadian sesi ini, ketahuan dari
    // ProductionSchedulingBoardSmokeTest::test_hot_job_dialog_... gagal).
    public function test_dragging_card_back_to_queue_preserves_prefilled_machine_suggestion(): void
    {
        $this->actingAs($this->admin());
        $op = $this->queueOperation();
        $originalMachineId = $op->machine_id;
        $machine = ProdMachine::where('activate', true)->firstOrFail();

        $test = Livewire::test('pages::production.monitoring-dashboard');
        $test->call('moveOperation', $op->operation_id, $machine->machine_id);
        $op->refresh();
        $this->assertSame('ASSIGNED', $op->task_status);

        $test->call('moveOperation', $op->operation_id, null);
        $op->refresh();
        $this->assertSame('PENDING', $op->task_status);
        $this->assertNull($op->target_start);
        $this->assertNull($op->target_finish);
        // machine_id TIDAK dihapus oleh drag-ke-Antrean -- tetap machine
        // terakhir yang di-assign (di atas), bukan null.
        $this->assertSame($machine->machine_id, $op->machine_id);

        // pulihkan ke machine_id asli (test ini sendiri mengubahnya ke $machine di atas)
        $op->update(['machine_id' => $originalMachineId]);
    }

    public function test_running_task_cannot_be_dragged(): void
    {
        $this->actingAs($this->admin());
        $op = PrdWoOperation::where('task_status', 'RUNNING')->where('resource_type', 'MACHINE')->firstOrFail();
        $originalMachineId = $op->machine_id;
        $otherMachine = ProdMachine::where('machine_id', '!=', $originalMachineId)->where('activate', true)->firstOrFail();

        Livewire::test('pages::production.monitoring-dashboard')
            ->call('moveOperation', $op->operation_id, $otherMachine->machine_id);

        $op->refresh();
        $this->assertSame($originalMachineId, $op->machine_id);
        $this->assertSame('RUNNING', $op->task_status);
    }

    public function test_manual_task_cannot_be_moved_via_board(): void
    {
        $this->actingAs($this->admin());
        $op = PrdWoOperation::where('resource_type', 'MANUAL')->firstOrFail();
        $machine = ProdMachine::where('activate', true)->firstOrFail();

        Livewire::test('pages::production.monitoring-dashboard')
            ->call('moveOperation', $op->operation_id, $machine->machine_id);

        $op->refresh();
        $this->assertNull($op->machine_id);
    }

    public function test_idle_machine_flag_reflects_no_running_or_assigned_task(): void
    {
        $this->actingAs($this->admin());

        // machineOperations() filter by whereDate('target_start', $this->date) --
        // data dummy dijangkarkan ke 2026-08-28 (lihat seed_prd_scheduling_board_dummy),
        // bukan "hari ini" yang terus berjalan. Wajib di-pin, sama pola
        // ProductionSchedulingBoardSmokeTest -- kalau tidak, tes ini diam-diam
        // gagal begitu tanggal kalender lewat dari 2026-08-28.
        $instance = Livewire::test('pages::production.monitoring-dashboard')
            ->set('date', '2026-08-28')
            ->instance();

        $idleMachine = ProdMachine::where('machine_code', 'COAT-01')->first();
        if ($idleMachine) {
            $this->assertTrue($instance->machineIsIdle($idleMachine->machine_id));
        }

        $busyMachine = ProdMachine::where('machine_code', 'CETAK-01')->firstOrFail();
        $this->assertFalse($instance->machineIsIdle($busyMachine->machine_id));
    }
}
