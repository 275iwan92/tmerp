<?php

namespace Tests\Feature;

use App\Models\Master\EmpEmployee;
use App\Models\Master\ProdMachine;
use App\Models\Prd\PrdCrewAssignment;
use App\Models\Prd\PrdWoOperation;
use App\Models\Sys\SecUser;
use Livewire\Livewire;
use Tests\TestCase;

// MPS - Spreadsheet (permintaan user 2026-08-28): UI kompatibel Google
// Sheets di atas data prd_wo_operation yang SAMA dgn WO Detail/Gantt/Kanban.
// Fokus test: engine (PdrEngine) tetap ditegakkan persis spt Gantt/Kanban --
// bukan cuma cek tampilan tabel. Semua mutasi memulihkan state di akhir
// test, pola sama ProductionMpsCardsKanbanTest.
class ProductionMpsSpreadsheetTest extends TestCase
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

    private function scheduledOperation(): PrdWoOperation
    {
        return PrdWoOperation::where('resource_type', 'MACHINE')
            ->whereNotNull('target_start')
            ->whereIn('task_status', ['PENDING', 'ASSIGNED'])
            ->firstOrFail();
    }

    public function test_sheet_renders_active_tasks(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::production.mps-spreadsheet')
            ->assertSee('MPS - Spreadsheet')
            ->assertSee('WO No');
    }

    public function test_assigning_machine_to_unscheduled_task_runs_engine_and_schedules_it(): void
    {
        $this->actingAs($this->admin());
        $op = $this->queueOperation();
        $originalMachineId = $op->machine_id;
        $machine = ProdMachine::where('activate', true)->firstOrFail();

        Livewire::test('pages::production.mps-spreadsheet')
            ->call('updateMachine', $op->operation_id, $machine->machine_id);

        $op->refresh();
        $this->assertSame('ASSIGNED', $op->task_status);
        $this->assertSame($machine->machine_id, $op->machine_id);
        $this->assertNotNull($op->target_start);
        $this->assertNotNull($op->target_finish);

        $op->update(['task_status' => 'PENDING', 'machine_id' => $originalMachineId, 'target_start' => null, 'target_finish' => null]);
    }

    public function test_editing_est_mulai_on_scheduled_task_validates_via_engine(): void
    {
        $this->actingAs($this->admin());
        $op = $this->scheduledOperation();
        $originalStart = $op->target_start->copy();
        $originalFinish = $op->target_finish->copy();

        $newStart = $originalStart->copy()->addHours(2);

        Livewire::test('pages::production.mps-spreadsheet')
            ->call('updateStart', $op->operation_id, $newStart->format('Y-m-d\TH:i'));

        $op->refresh();
        $this->assertTrue($op->target_start->equalTo($newStart));

        $op->update(['target_start' => $originalStart, 'target_finish' => $originalFinish]);
    }

    public function test_operator_conflict_is_rejected_by_engine(): void
    {
        $this->actingAs($this->admin());

        $op = PrdWoOperation::where('resource_type', 'MACHINE')
            ->whereNotNull('target_start')->whereNotNull('target_finish')
            ->where('task_status', 'ASSIGNED')
            ->firstOrFail();

        $busyOp = PrdWoOperation::where('operation_id', '!=', $op->operation_id)
            ->whereIn('task_status', ['ASSIGNED', 'RUNNING'])
            ->whereNotNull('target_start')->whereNotNull('target_finish')
            ->where('target_start', '<', $op->target_finish)
            ->where('target_finish', '>', $op->target_start)
            ->firstOrFail();

        $employee = EmpEmployee::where('activate', true)->firstOrFail();

        $conflict = PrdCrewAssignment::updateOrCreate(
            ['operation_id' => $busyOp->operation_id, 'crew_role' => 'OPERATOR_UTAMA'],
            ['employee_id' => $employee->employee_id, 'created_by' => 'test']
        );
        $existingOnOp = PrdCrewAssignment::where('operation_id', $op->operation_id)
            ->where('crew_role', 'OPERATOR_UTAMA')->first();

        Livewire::test('pages::production.mps-spreadsheet')
            ->call('updateOperator', $op->operation_id, $employee->employee_id);

        $stillAssigned = PrdCrewAssignment::where('operation_id', $op->operation_id)
            ->where('crew_role', 'OPERATOR_UTAMA')->first();
        $this->assertNull($stillAssigned, 'Operator seharusnya TIDAK ter-assign karena konflik jadwal.');

        $conflict->delete();
        if ($existingOnOp) {
            $existingOnOp->save();
        }
    }

    public function test_running_task_cannot_be_edited(): void
    {
        $this->actingAs($this->admin());
        $op = PrdWoOperation::where('task_status', 'RUNNING')->where('resource_type', 'MACHINE')->firstOrFail();
        $originalMachineId = $op->machine_id;
        $otherMachine = ProdMachine::where('machine_id', '!=', $originalMachineId)->where('activate', true)->firstOrFail();

        Livewire::test('pages::production.mps-spreadsheet')
            ->call('updateMachine', $op->operation_id, $otherMachine->machine_id);

        $op->refresh();
        $this->assertSame($originalMachineId, $op->machine_id);
        $this->assertSame('RUNNING', $op->task_status);
    }
}
