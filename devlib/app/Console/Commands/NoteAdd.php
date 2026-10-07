<?php

namespace App\Console\Commands;

use App\Support\AgentHistory;
use Illuminate\Console\Command;

// Untuk AGEN di akhir kerja (user 2026-10-07): simpan ringkasan laporan / keputusan / tautan Artifact ke Agent History
// (menu Executive Summary > Agent History di devlib.test). Jalankan dari folder devlib:
//   php artisan note:add iwan "Judul" --kind=REPORT --body="isi ringkas" [--body-file=berkas.md] [--url=https://...]
class NoteAdd extends Command
{
    protected $signature = 'note:add {author : iwan | jo | user} {title} {--kind=REPORT : REPORT | NOTE | DECISION | ARTIFACT} {--body=} {--body-file=} {--url=}';

    protected $description = 'Simpan satu catatan ke Agent History (pengingat user)';

    public function handle(): int
    {
        if (! AgentHistory::ready()) {
            $this->warn('Tabel dev_note belum ada: jalankan migrasi 2026_10_07_000001_create_dev_note_table.');

            return self::FAILURE;
        }
        $body = $this->option('body-file') ? (string) @file_get_contents($this->option('body-file')) : $this->option('body');
        try {
            $id = AgentHistory::add(strtolower($this->argument('author')), strtoupper($this->option('kind')), $this->argument('title'), $body, $this->option('url'), 'agent:'.$this->argument('author'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Catatan #{$id} disimpan.");

        return self::SUCCESS;
    }
}
