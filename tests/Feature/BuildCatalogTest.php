<?php

namespace Tests\Feature;

use App\Console\Commands\BuildCatalog;
use App\Support\Build\BuildRegistry;
use App\Support\Engines\EngineRegistry;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

// Papan Build Progress (user 2026-10-05): angka dihitung dari menu, daftar grid, daftar engine dan berkas kode.
// Hanya membaca database (sys_app_menu, sys_dictionary, information_schema); tidak menulis apa pun.
class BuildCatalogTest extends TestCase
{
    public function test_snapshot_counts_add_up(): void
    {
        $s = BuildRegistry::snapshot();
        $t = $s['totals'];

        $this->assertNotEmpty($s['modules']);
        $this->assertSame($t['screens'], $t['v2'] + $t['v1'] + $t['not_built'], 'tiap layar = v2, v1 atau belum dibangun');
        foreach ($s['modules'] as $m) {
            $this->assertSame($m['total'], $m['v2'] + $m['v1'] + $m['not_built'], $m['label']);
            $this->assertLessThanOrEqual($m['total'], $m['waiting_qc'] + $m['locked'], $m['label']);
        }
        $this->assertSame(count(EngineRegistry::all()), $t['engines']);
        $this->assertSame($t['engines'], array_sum(array_map(fn ($g) => count($g['items']), $s['engines'])));
        $this->assertGreaterThan(100, $t['objects']);
    }

    // devlib.test membaca resources/data/v2/build.json. Isinya ikut menu ddtsys, jadi hanya bentuknya yang dijaga di sini.
    public function test_file_for_devlib_site_exists_with_the_same_shape(): void
    {
        $this->assertFileExists(BuildCatalog::path(), 'Jalankan: php artisan build:catalog --env=mockup');
        $file = json_decode(file_get_contents(BuildCatalog::path()), true);
        $now = BuildRegistry::snapshot();

        $this->assertSame(array_keys($now), array_keys($file));
        $this->assertSame(array_keys($now['totals']), array_keys($file['totals']), 'build.json tertinggal. Jalankan: php artisan build:catalog --env=mockup');
        $this->assertSame($now['features'], $file['features'], 'build.json tertinggal. Jalankan: php artisan build:catalog --env=mockup');
    }

    public function test_component_shows_modules_engines_and_open_work(): void
    {
        $build = [
            'generated' => '2026-10-05 10:00', 'database' => 'ddtsys',
            'totals' => ['objects' => 139, 'modules' => 2, 'modules_done' => 1, 'screens' => 14, 'v2' => 11, 'v1' => 1, 'not_built' => 2,
                'waiting_qc' => 11, 'locked' => 1, 'forms' => 6, 'grids' => 5, 'reports' => 3, 'grid_classes' => 9, 'lookups' => 2, 'report_grids' => 3,
                'engines' => 1, 'engine_groups' => 1, 'tables' => 20, 'databases' => 2, 'models' => 5, 'migrations' => 6, 'tests' => 7,
                'dictionary' => 1234, 'languages' => 2, 'commits' => '10', 'since' => '2026-08-06'],
            'modules' => [
                ['label' => 'Procurement', 'total' => 11, 'v2' => 11, 'v1' => 0, 'not_built' => 0, 'waiting_qc' => 10, 'locked' => 1, 'form' => 4, 'grid' => 4, 'report' => 3],
                ['label' => 'Management Reports', 'total' => 3, 'v2' => 0, 'v1' => 1, 'not_built' => 2, 'waiting_qc' => 1, 'locked' => 0, 'form' => 2, 'grid' => 1, 'report' => 0],
            ],
            'engines' => [['group' => 'Pro-Cost', 'items' => [['name' => 'Costing Engine', 'purpose' => 'Calculates the cost price.', 'mode' => 'calc']]]],
            'features' => [['fa-comments', 'Chat']],
            'notes' => ['Payroll encryption: decided, not installed'],
        ];
        $html = Blade::render('<x-dl::build-board :build="$build" :library="$library" engines-url="/lib/p9-engines" />',
            ['build' => $build, 'library' => ['groups' => ['Grid', 'Form'], 'pages' => 60, 'approved' => 46, 'demos' => 45, 'packages' => ['chat']]]);

        $this->assertStringContainsString('data-dl-build-board', $html);
        $this->assertStringContainsString('11 / 14', $html);
        $this->assertStringContainsString('Costing Engine', $html);
        $this->assertStringContainsString('Not started', $html);
        $this->assertStringContainsString('Management Reports: 2', $html);
        $this->assertStringContainsString('Payroll encryption: decided, not installed', $html);
        $this->assertStringContainsString('46 / 60', $html);
        $this->assertStringContainsString('/lib/p9-engines', $html);
    }
}
