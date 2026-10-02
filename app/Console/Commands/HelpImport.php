<?php

namespace App\Console\Commands;

use App\Services\Help\HelpCenter;
use Illuminate\Console\Command;

// Isi awal Help dari resources/data/help/*.json ke sys_help_topic / sys_help_line + kamus (konteks 'help').
// Topik yang sudah ada dilewati supaya koreksi user di Setup > Help Topics / Help Lines tidak tertimpa; --force menimpa.
class HelpImport extends Command
{
    protected $signature = 'help:import {--file= : Only this file name, e.g. sales.json} {--force : Replace topics that already exist}';

    protected $description = 'Load help topics (purpose, how-to steps, field explanations) from resources/data/help';

    public function handle(): int
    {
        $dir = resource_path('data/help');
        $files = $this->option('file') ? [$dir.'/'.$this->option('file')] : (glob($dir.'/*.json') ?: []);
        $topics = [];
        foreach ($files as $file) {
            $rows = json_decode((string) @file_get_contents($file), true);
            if (! is_array($rows)) {
                $this->error(basename($file).': not valid JSON.');

                return self::FAILURE;
            }
            array_push($topics, ...$rows);
        }
        // Aturan isi (255 karakter per teks, kunci unik, dua bahasa) dijaga pemeriksa yang sama dengan yang dipakai penulis isi.
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($dir.'/_check.php').' '.implode(' ', array_map('escapeshellarg', $files)), $bad);
        if ($bad) {
            $this->error('Fix the content files first; nothing was imported.');

            return self::FAILURE;
        }

        $r = HelpCenter::import($topics, 'help:import', (bool) $this->option('force'));
        $this->info("{$r['topics']} topics loaded ({$r['lines']} lines), {$r['skipped']} already there, {$r['translations']} translations added.");
        if ($missing = HelpCenter::missing()) {
            $this->warn(count($missing).' menu screens have no help topic yet:');
            foreach ($missing as $slug => $label) {
                $this->line("  $slug  ($label)");
            }
        }

        return self::SUCCESS;
    }
}
