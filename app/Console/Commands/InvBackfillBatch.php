<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\BatchBackfillService;
use Illuminate\Console\Command;

// Inventory & Warehouse v2.01: lot lama -> batch (BatchBackfillService). Aman diulang; --check tidak menulis apa pun.
//   php artisan inv:backfill-batch --check --env=mockup
//   php artisan inv:backfill-batch --env=mockup
class InvBackfillBatch extends Command
{
    protected $signature = 'inv:backfill-batch {--check : hanya hitung, tidak menulis} {--class= : RM, SFG, atau FG (bawaan semua)} {--item=* : hanya item ini (id master)}';

    protected $description = 'Buat batch dari lot_no kartu stok dan palet yang sudah ada, lalu isi batch_id (idempoten)';

    public function handle(): int
    {
        $class = strtoupper((string) $this->option('class'));
        if ($class !== '' && ! in_array($class, ['RM', 'SFG', 'FG'], true)) {
            $this->error('Kelas harus RM, SFG, atau FG.');

            return self::FAILURE;
        }
        $write = ! $this->option('check');
        $items = array_map('intval', (array) $this->option('item')) ?: null;
        $r = BatchBackfillService::run($write, 'inv:backfill-batch', $class === '' ? null : [$class], $items);

        $this->info(($write ? 'Backfill selesai.' : 'Pemeriksaan saja, tidak ada yang ditulis.').' DB inv: '.config('database.connections.tm2inv.database'));
        $this->table(['Kelas', 'Kelompok lot', $write ? 'Batch dibuat' : 'Batch akan dibuat', 'Batch sudah ada', 'Digabung', 'Kartu', 'Baris dok', 'Palet'],
            array_map(fn (string $c, array $m) => [$c, $m['groups'], $m['created'], $m['existing'], $m['merged'], $m['cards'], $m['lines'], $m['pallets']], array_keys($r['by_class']), $r['by_class']));
        $this->line(sprintf('Batch %s: %d | sudah ada: %d | kelompok lot digabung (beda huruf besar-kecil/spasi): %d | baris %s: kartu %d, dokumen %d, palet %d',
            $write ? 'dibuat' : 'akan dibuat', $r['batches_created'], $r['batches_existing'], $r['groups_merged'], $write ? 'tertaut' : 'akan tertaut', $r['linked']['cards'], $r['linked']['lines'], $r['linked']['pallets']));

        return self::SUCCESS;
    }
}
