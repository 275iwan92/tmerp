<?php

namespace Tests\Feature;

use App\Support\V2\Data\MigrationDesk;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

// Setup > Migration v2 (C1): daftar runner harus menunjuk service yang ada; runner TIDAK dijalankan di test (mengubah data).
class MigrationDeskTest extends TestCase
{
    public function test_every_runner_points_to_a_service_with_run(): void
    {
        $this->assertCount(17, MigrationDesk::runners());
        foreach (MigrationDesk::runners() as $key => $r) {
            $this->assertTrue(class_exists($r['service']), $key);
            $this->assertTrue(method_exists($r['service'], 'run'), $key);
        }
    }

    public function test_unknown_runner_is_rejected(): void
    {
        $this->expectException(NotFoundHttpException::class);
        MigrationDesk::find('nope');
    }

    public function test_report_rows_flatten_nested_values(): void
    {
        $rows = MigrationDesk::rows(['customers' => ['created' => 3, 'updated' => 1], 'errors' => [], 'codes' => ['A', 'B'], 'n' => 5]);
        $this->assertSame([
            ['part' => 'customers', 'result' => 'created: 3, updated: 1'],
            ['part' => 'errors', 'result' => '—'],
            ['part' => 'codes', 'result' => 'A, B'],
            ['part' => 'n', 'result' => '5'],
        ], $rows);
    }
}
