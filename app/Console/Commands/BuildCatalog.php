<?php

namespace App\Console\Commands;

use App\Support\Build\BuildRegistry;
use Illuminate\Console\Command;

// Papan Build Progress untuk halaman depan devlib.test (devlib.test tidak memuat kode / database tmerp):
// BuildRegistry -> resources/data/v2/build.json. Menu yang benar ada di ddtsys, jadi jalankan dengan --env=mockup.
class BuildCatalog extends Command
{
    protected $signature = 'build:catalog';

    protected $description = 'Ekspor angka Build Progress (modul, layar, form, grid, engine, fitur) ke resources/data/v2/build.json untuk devlib.test';

    public static function path(): string
    {
        return resource_path('data/v2/build.json');
    }

    public function handle(): int
    {
        $data = BuildRegistry::snapshot();
        file_put_contents(self::path(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");
        $t = $data['totals'];
        $this->info("Build catalog ({$data['database']}): {$t['modules']} modules, {$t['v2']} / {$t['screens']} screens on v2, {$t['engines']} engines -> resources/data/v2/build.json");

        return self::SUCCESS;
    }
}
