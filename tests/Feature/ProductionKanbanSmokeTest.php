<?php

namespace Tests\Feature;

use App\Models\Sys\SecUser;
use Tests\TestCase;

// MPS Kanban Board (production.kanban) -- pasangan tampilan MPS Gantt
// (ProductionSchedulingBoardSmokeTest), baca data dummy sama
// (2026_08_28_000002_seed_prd_scheduling_board_dummy.php).
class ProductionKanbanSmokeTest extends TestCase
{
    public function test_kanban_board_renders_operations_by_status(): void
    {
        $admin = SecUser::where('login', 'johiwan')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/production/kanban');

        $response->assertOk();
        $response->assertSee('MPS Kanban Board');
        // WO-0231 dummy (RUNNING, Cetak) harus muncul di kolom Berjalan.
        $response->assertSee('WO2600231');
        $response->assertSee('Lihat di Gantt');
    }
}
