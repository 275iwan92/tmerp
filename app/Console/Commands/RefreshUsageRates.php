<?php

namespace App\Console\Commands;

use App\Services\ProDev\UsageRateEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

// Menyegarkan usulan takaran pemakaian bahan & persen waste dari data produksi
// nyata (BACKLOG §19.2). Dijadwalkan awal bulan; boleh juga dijalankan manual.
class RefreshUsageRates extends Command
{
    protected $signature = 'prodev:usage-rates
        {--months=3 : Rentang data produksi yang dipakai (bulan ke belakang)}
        {--dry : Hanya hitung usulan, jangan terapkan walau aturan ber-auto_apply}';

    protected $description = 'Hitung usulan takaran pemakaian & waste dari WO selesai (Setting Aturan BOP)';

    public function handle(UsageRateEngine $engine): int
    {
        $from = Carbon::now()->subMonths(max(1, (int) $this->option('months')))->startOfDay();
        $r = $engine->refresh($from, ! $this->option('dry'));

        $this->info("Data WO selesai sejak {$r['from']}: {$r['sample_total']} WO.");

        if ($r['note']) {
            $this->warn($r['note']);

            return self::SUCCESS;
        }

        $this->table(
            ['Aturan', 'Takaran kini', 'Usulan', 'Waste kini', 'Usulan waste', 'Sampel', 'Diterapkan'],
            collect($r['rules'])->map(fn ($x) => [
                $x['role'], $x['rate_now'], $x['rate_suggested'], $x['waste_now'],
                $x['waste_suggested'], $x['sample'], $x['applied'] ? 'ya' : 'tunggu admin',
            ])->all(),
        );

        $this->info("Diterapkan otomatis: {$r['applied']} aturan (minimal sampel {$engine->minSample()} WO).");

        return self::SUCCESS;
    }
}
