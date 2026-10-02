<?php

namespace Tests\Feature;

use App\Console\Commands\EngineCatalog as EngineCatalogCommand;
use App\Models\Sys\SecUser;
use App\Support\Engines\EngineRegistry;
use Devlib\Components\EngineCatalog;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Daftar engine (user 2026-10-02): setiap engine terdaftar dengan input, output dan cara kerjanya; tanda tangan method
// dibaca dari kode; berkas untuk devlib.test tidak boleh tertinggal dari kode. Tidak membaca / menulis database bisnis.
class EngineCatalogTest extends TestCase
{
    // Kelas *Engine / *EngineService baru di app/Services wajib masuk App\Support\Engines\EngineRegistry.
    public function test_every_engine_class_is_registered(): void
    {
        $missing = [];
        foreach ((new Finder)->files()->in(app_path('Services'))->name(['*Engine.php', '*EngineService.php']) as $f) {
            $class = 'App\\Services\\'.str_replace(['/', '.php'], ['\\', ''], str_replace('\\', '/', $f->getRelativePathname()));
            if (! in_array($class, EngineRegistry::classes(), true)) {
                $missing[] = $class;
            }
        }
        $this->assertSame([], $missing, 'Engine belum didaftarkan di App\Support\Engines\EngineRegistry (kegunaan, input, output, cara kerja).');
    }

    public function test_every_entry_explains_input_output_and_how_it_works(): void
    {
        $catalog = EngineRegistry::catalog();   // melempar ReflectionException bila kelas / method yang didaftarkan tidak ada
        $keys = array_column($catalog, 'key');
        $this->assertSame($keys, array_unique($keys), 'key engine harus unik');

        foreach ($catalog as $e) {
            $this->assertArrayHasKey($e['mode'], EngineCatalog::MODES, $e['key'].': mode tidak dikenal');
            foreach (['name', 'group', 'purpose'] as $k) {
                $this->assertNotSame('', trim((string) ($e[$k] ?? '')), "{$e['key']}: {$k} kosong");
            }
            foreach (['inputs', 'outputs', 'steps', 'entries'] as $k) {
                $this->assertNotEmpty($e[$k] ?? [], "{$e['key']}: {$k} kosong");
            }
            $this->assertFileExists(base_path($e['file']), $e['key'].': berkas sumber tidak ada');
            foreach (array_merge($e['tests'] ?? [], array_filter($e['docs'] ?? [], fn ($d) => str_starts_with($d, 'docs/'))) as $path) {
                $file = preg_replace('/ (§|\().*$/u', '', $path);
                if (str_contains($file, '/')) {
                    $this->assertFileExists(base_path($file), $e['key'].': rujukan tidak ada');
                }
            }
        }
    }

    // devlib.test membaca resources/data/v2/engines.json. Gagal di sini = jalankan `php artisan engine:catalog`.
    public function test_catalog_file_for_devlib_site_is_current(): void
    {
        $strip = fn (array $list) => array_map(fn ($e) => array_diff_key($e, ['lines' => 1]), $list);   // jumlah baris boleh bergeser
        $this->assertFileExists(EngineCatalogCommand::path());
        $this->assertSame(
            $strip(json_decode(EngineCatalogCommand::json(), true)),
            $strip(json_decode(file_get_contents(EngineCatalogCommand::path()), true)),
            'resources/data/v2/engines.json tertinggal dari kode. Jalankan: php artisan engine:catalog'
        );
    }

    public function test_component_shows_signature_from_code_and_the_explanation(): void
    {
        $engine = collect(EngineRegistry::catalog())->firstWhere('key', 'imposition');
        $html = Blade::render('<x-dl::engine-catalog :engines="$engines" open />', ['engines' => [$engine]]);

        $this->assertStringContainsString('Imposition Engine', $html);
        $this->assertStringContainsString('run(float $planoW, float $planoH, float $flatW, float $flatH, float $gap, float $gripper, float $margin): array', $html);
        $this->assertStringContainsString('Pieces per sheet.', $html);
        $this->assertStringContainsString('Usable area = plano minus margins and gripper.', $html);
        $this->assertStringContainsString('app/Services/Imposition/ImpositionEngine.php', $html);
    }

    // Rumus harga ProCost rahasia: daftar engine hanya menjelaskan alurnya.
    public function test_costing_entry_is_marked_confidential(): void
    {
        $costing = collect(EngineRegistry::catalog())->firstWhere('key', 'costing');
        $this->assertNotEmpty($costing['restricted']);
    }

    public function test_engine_page_is_for_developers_only(): void
    {
        $this->actingAs(SecUser::where('login', 'johiwan')->firstOrFail())->get('/v2/engines')
            ->assertOk()->assertSee('data-dl-engine-catalog', false)->assertSee('Costing Engine (Pro-Cost)');
        $this->actingAs(SecUser::where('is_admin', false)->where('activate', true)->firstOrFail())->get('/v2/engines')->assertForbidden();
    }
}
