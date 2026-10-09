<?php

namespace App\Console\Commands;

use App\Services\Inventory\Valuation\ReconcileService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

// Rekonsiliasi harian nilai persediaan: buku besar vs kartu stok per kelas (RM / SFG / FG). Hasil disimpan di inv_reconcile_log
// dan tampil di Inventory > Valuation & Period > Report > Daily Reconciliation. Belum dijadwalkan di routes/console.php (keputusan user).
class InventoryReconcile extends Command
{
    protected $signature = 'inv:reconcile {--date= : Tanggal (YYYY-MM-DD), bawaan hari ini} {--no-save : Hitung dan tampilkan saja, tanpa menulis inv_reconcile_log}';

    protected $description = 'Compare the inventory accounts in the general ledger with the stock card value per class and log the result';

    public function handle(ReconcileService $service): int
    {
        $date = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date')) : CarbonImmutable::today();
        $rows = $service->run($date, 'system', ! $this->option('no-save'));
        $this->table(['Class', 'Accounts', 'GL', 'Stock card', 'Landed cost not in cards', 'Difference', 'Status'], array_map(fn ($r) => [$r['stock_class'], $r['account_codes'],
            number_format($r['gl_value'], 2), number_format($r['ledger_value'], 2), number_format($r['landed_pending'], 2), number_format($r['difference'], 2), $r['status']], $rows));

        return collect($rows)->contains('status', 'DIFF') ? self::FAILURE : self::SUCCESS;
    }
}
