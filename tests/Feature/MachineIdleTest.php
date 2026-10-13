<?php

/*
 * REMARK
 * Tujuan       : Menjaga penjelasan jeda mesin (MachineIdle): jeda karena proses sebelumnya + waktu tunggu, karena jam kerja, karena job dikonfirmasi lebih dulu, dan jeda tanpa alasan ditandai.
 * Sumber hulu  : App\Support\V2\Data\MachineIdle, App\Services\Scheduling\WorkingCalendar.
 * Input        : Operasi buatan (tanpa database), kalender 1 shift 07:00-15:00.
 * Output       : Lulus/gagal.
 * Dipakai oleh : php artisan test.
 * Status       : Aktif.
 */

namespace Tests\Feature;

use App\Services\Scheduling\WorkingCalendar;
use App\Support\V2\Data\MachineIdle;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MachineIdleTest extends TestCase
{
    private function op(int $id, int $wo, int $seq, string $process, string $start, string $finish, array $extra = []): array
    {
        return $extra + ['op_id' => $id, 'wo_id' => $wo, 'seq' => $seq, 'process' => $process, 'machine_id' => 1, 'start' => CarbonImmutable::parse($start),
            'finish' => CarbonImmutable::parse($finish), 'task_status' => 'PENDING', 'plan_status' => 'DRAFT', 'locked' => false, 'gates' => ['READY', 'READY', 'READY'],
            'gate_note' => null, 'material_ready_at' => null];
    }

    private function explain(array $ops): array
    {
        $cal = new WorkingCalendar(CarbonImmutable::parse('2026-10-12 00:00'), [], [], '07:00', 1, 8.0);

        return MachineIdle::explain($ops, $cal, ['CETAK' => 120], CarbonImmutable::parse('2026-10-12 06:00'));
    }

    public function test_back_to_back_jobs_have_no_idle(): void
    {
        $this->assertSame([], $this->explain([$this->op(1, 1, 1, 'CETAK', '2026-10-12 07:00', '2026-10-12 08:00'), $this->op(2, 2, 1, 'CETAK', '2026-10-12 08:00', '2026-10-12 09:00')]));
    }

    public function test_gap_after_drying_of_the_previous_process_is_explained(): void
    {
        $ops = [
            $this->op(1, 1, 1, 'CETAK', '2026-10-12 07:00', '2026-10-12 08:00'),
            $this->op(2, 1, 2, 'LAMINASI', '2026-10-12 10:00', '2026-10-12 11:00', ['machine_id' => 2]),
            $this->op(3, 2, 1, 'POTONG', '2026-10-12 07:00', '2026-10-12 07:30', ['machine_id' => 3]),
            $this->op(4, 2, 2, 'LAMINASI', '2026-10-12 08:00', '2026-10-12 09:00', ['machine_id' => 2]),
        ];
        $ex = $this->explain($ops);
        $this->assertArrayNotHasKey(4, $ex);
        $this->assertSame(60, $ex[2]['minutes']);
        $this->assertTrue($ex[2]['explained']);
        $this->assertStringContainsString('CETAK', $ex[2]['reason']);
    }

    public function test_gap_without_a_cause_is_flagged(): void
    {
        $ex = $this->explain([$this->op(1, 1, 1, 'CETAK', '2026-10-12 07:00', '2026-10-12 08:00'), $this->op(2, 2, 1, 'CETAK', '2026-10-12 11:00', '2026-10-12 12:00')]);
        $this->assertSame(180, $ex[2]['minutes']);
        $this->assertFalse($ex[2]['explained']);
    }

    public function test_duration_text(): void
    {
        $this->assertSame('1 h 40 min', MachineIdle::duration(100));
        $this->assertSame('2 h', MachineIdle::duration(120));
        $this->assertSame('45 min', MachineIdle::duration(45));
    }
}
