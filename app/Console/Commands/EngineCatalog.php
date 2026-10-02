<?php

namespace App\Console\Commands;

use App\Support\Engines\EngineRegistry;
use Illuminate\Console\Command;

// Daftar engine tmerp untuk situs DevLib (devlib.test tidak memuat kode tmerp): EngineRegistry + tanda tangan method dari
// kode -> resources/data/v2/engines.json. Jalankan setiap engine ditambah / tanda tangannya berubah; EngineCatalogTest
// gagal bila berkas ini tertinggal dari kode.
class EngineCatalog extends Command
{
    protected $signature = 'engine:catalog';

    protected $description = 'Ekspor daftar engine (input, output, cara kerja) ke resources/data/v2/engines.json untuk katalog DevLib';

    public static function path(): string
    {
        return resource_path('data/v2/engines.json');
    }

    public static function json(): string
    {
        return json_encode(EngineRegistry::catalog(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
    }

    public function handle(): int
    {
        @mkdir(dirname(self::path()), 0777, true);
        file_put_contents(self::path(), self::json());
        $this->info('Engine catalog: '.count(EngineRegistry::all()).' engine -> resources/data/v2/engines.json');

        return self::SUCCESS;
    }
}
