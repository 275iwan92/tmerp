<?php

namespace App\Console\Commands;

use App\Services\Production\SpeedTarget\SpeedSettings;
use App\Services\Production\SpeedTarget\SpeedSuggestionStore;
use App\Services\Production\SpeedTarget\SpeedTargetEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// Menghitung ulang usulan target kecepatan mesin dari data terbaru (realisasi WO v2 bertambah -> usulan ikut belajar).
// Dijadwalkan harian; hanya membuat usulan PENDING, tidak pernah mengubah master.
class SuggestSpeedTargets extends Command
{
    protected $signature = 'production:suggest-speed-targets';

    protected $description = 'Hitung usulan target kecepatan mesin (engine suggests, PPIC confirms)';

    public function handle(): int
    {
        if (! Schema::connection('tm2mst')->hasTable('prod_speed_suggestion')) {
            $this->warn('Tabel prod_speed_suggestion belum ada di database ini (migration 2026_10_01_000030 belum dijalankan).');

            return self::SUCCESS;
        }

        $r = (new SpeedSuggestionStore(new SpeedTargetEngine(SpeedSettings::load())))->refresh();
        $this->info("{$r['machines']} mesin dihitung: {$r['created']} usulan baru, {$r['kept']} tetap, {$r['superseded']} diganti.");

        return self::SUCCESS;
    }
}
