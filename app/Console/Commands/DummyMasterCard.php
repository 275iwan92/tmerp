<?php

/*
 * REMARK
 * Tujuan       : Menjalankan MasterCardDummyEngine: PDF Master Card baru di docs/attachments/mastercard -> data dummy berlevel (ddt*).
 * Sumber hulu  : Folder PDF + extracted/ (gambar kartu), master customer / bahan / proses / mesin.
 * Input        : --path, --levels=inquiry:2,prodev:2,procost:2,co:2 (default: semua produk baru ke level inquiry), --qty, --due.
 * Output       : Tabel laporan per produk (level, nomor inquiry atau alasan dilewati).
 * Dipakai oleh : Dijalankan manual setelah ada PDF baru: php artisan dummy:mastercard --env=mockup
 * Status       : Aktif; hanya situs dummy ddt*.
 */

namespace App\Console\Commands;

use App\Services\MasterCard\MasterCardDummyEngine;
use Illuminate\Console\Command;

class DummyMasterCard extends Command
{
    protected $signature = 'dummy:mastercard {--path=docs/attachments/mastercard} {--levels= : inquiry:2,prodev:2,procost:2,co:2} {--qty=5000} {--due=21}';

    protected $description = 'PDF Master Card baru -> data dummy berlevel (Inquiry, ProDev, ProCost, CO), hanya ddt*';

    public function handle(MasterCardDummyEngine $engine): int
    {
        $quota = array_fill_keys(MasterCardDummyEngine::LEVELS, 0);
        if (! $this->option('levels')) {
            $quota['inquiry'] = PHP_INT_MAX;
        }
        foreach (array_filter(explode(',', (string) $this->option('levels'))) as $pair) {
            [$level, $n] = array_pad(explode(':', $pair), 2, 0);
            if (! array_key_exists($level, $quota)) {
                $this->error("Level tidak dikenal: {$level} (inquiry, prodev, procost, co).");

                return self::FAILURE;
            }
            $quota[$level] = (int) $n;
        }

        $report = $engine->run(base_path($this->option('path')), $quota, (int) $this->option('qty'), (int) $this->option('due'));
        $this->table(['Design', 'Product', 'Level', 'Note'], array_map(fn ($r) => [$r['design'] ?? '-', $r['name'], $r['level'] ?? '-', $r['note']], $report));

        return self::SUCCESS;
    }
}
