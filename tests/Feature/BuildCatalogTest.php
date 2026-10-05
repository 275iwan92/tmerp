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
        $keys = array_column($s['items'], 'key');
        $this->assertSame($keys, array_unique($keys), 'kunci item harus unik (dipakai devlib.dev_item)');
        $this->assertSame($t['screens'], count(array_filter($keys, fn ($k) => str_starts_with($k, 'menu:'))));
        $this->assertSame($t['engines'], count(array_filter($keys, fn ($k) => str_starts_with($k, 'engine:'))));
        $this->assertSame(['iwan', 'jo'], array_column($s['agents'], 'key'));
        $this->assertCount(14, $s['days']);
        $this->assertSame((int) $t['commits'], $s['untracked'] + array_sum(array_column($s['agents'], 'commits')), 'tiap commit = satu agen atau sebelum pelacakan');
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

    // Daftar hasil kerja + bintang (Executive Summary > By Object di devlib.test).
    public function test_rate_list_shows_item_agent_and_rating(): void
    {
        $item = ['id_item' => 7, 'item_type' => 'FORM', 'name' => 'Customer Order', 'module' => 'Sales', 'qc_status' => 'READY_FOR_QC', 'built_by' => null, 'built_at' => '2026-09-26',
            'changed_by' => 'iwan', 'changed_at' => '2026-10-05', 'rating' => 4, 'note' => 'Cancel flow needs a check', 'rated_by' => 'johiwan', 'rated_at' => '2026-10-05 10:00:00', 'ratings' => 2];
        $html = Blade::render('<x-dl::rate-list :items="$items" :types="$types" :qc="$qc" :agents="$agents" action="/summary/rate" csrf="tok" />', [
            'items' => [$item, ['id_item' => 8, 'item_type' => 'ENGINE', 'name' => 'Costing Engine', 'rating' => null, 'note' => null, 'rated_by' => null, 'rated_at' => null, 'ratings' => 0, 'built_by' => 'jo'] + $item],
            'types' => ['FORM' => 'Form', 'ENGINE' => 'Engine'], 'qc' => ['READY_FOR_QC' => ['Waiting QC', 'warn']], 'agents' => ['iwan' => 'Agent Iwan', 'jo' => 'Agent Jo'],
        ]);

        $this->assertStringContainsString('data-dl-rate-list', $html);
        $this->assertStringContainsString('data-action="/summary/rate"', $html);
        $this->assertStringContainsString('data-id="7" data-type="FORM" data-agent="iwan" data-rating="4"', $html);
        $this->assertStringContainsString('data-id="8" data-type="ENGINE" data-agent="iwan" data-rating="0"', $html);
        $this->assertStringContainsString('Cancel flow needs a check', $html);
        $this->assertStringContainsString('Waiting QC', $html);
        $this->assertStringContainsString('Agent Jo', $html);
        $this->assertSame(2, substr_count($html, 'role="slider"'));
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
            'agents' => [['key' => 'iwan', 'name' => 'Agent Iwan', 'commits' => 7, 'days' => 2, 'last' => '2026-10-05', 'screens' => 3, 'objects' => 1, 'engines' => 0, 'tests' => 2],
                ['key' => 'jo', 'name' => 'Agent Jo', 'commits' => 0, 'days' => 0, 'last' => null, 'screens' => 0, 'objects' => 0, 'engines' => 0, 'tests' => 0]],
            'untracked' => 3,
            'days' => [['date' => '2026-10-04', 'iwan' => 0, 'jo' => 0, 'other' => 3], ['date' => '2026-10-05', 'iwan' => 7, 'jo' => 0, 'other' => 0]],
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
        $this->assertStringContainsString('Agent Iwan', $html);
        $this->assertStringContainsString('no tracked work yet', $html);
        $this->assertStringContainsString('class="is-iwan" style="height:120px"', $html);
    }
}
