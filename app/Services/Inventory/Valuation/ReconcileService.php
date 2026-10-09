<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvLandedCostLine;
use App\Models\Inventory\InvReconcileLog;
use App\Models\Sys\SysConstant;
use App\Services\Inventory\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Rekonsiliasi harian (V-06): total nilai persediaan di buku besar (akun persediaan per kelas, konstanta INV_RECON_ACCTS_RM / _SFG / _FG)
// dibanding total nilai kartu stok (StockLedger, posisi akhir hari itu). Selisih di atas INV_RECON_TOLERANCE ditandai DIFF.
//   difference = GL - (kartu stok + landed cost yang sudah dijurnal tetapi belum ada di kartu)
// Landed cost (LandedCostService) menaikkan nilai di buku besar lebih dulu; bagian itu ditampilkan terpisah (landed_pending) supaya tidak
// tampak sebagai selisih sampai rantai Average mendukung baris khusus nilai. Hasil tiap hari + kelas disimpan di inv_reconcile_log.
final class ReconcileService
{
    public const CLASSES = ['RM' => 'INV_RECON_ACCTS_RM', 'SFG' => 'INV_RECON_ACCTS_SFG', 'FG' => 'INV_RECON_ACCTS_FG'];

    /**
     * @return list<array{stock_class: string, account_codes: string, gl_value: float, ledger_value: float, landed_pending: float, difference: float, status: string}>
     */
    public function run(CarbonImmutable $date, string $login, bool $save = true): array
    {
        $date = $date->startOfDay();
        StockLedger::forget();
        $ledger = [];
        foreach (StockLedger::between($date, $date)->items() as $item) {
            $ledger[$item['class']] = ($ledger[$item['class']] ?? 0.0) + $item['closing_value'];
        }
        $tolerance = SysConstant::number('INV_RECON_TOLERANCE', 1.0);

        $out = [];
        foreach (self::CLASSES as $class => $constant) {
            $gl = $this->glValue(ValuationAccounts::ids($constant), $date);
            $cards = round((float) ($ledger[$class] ?? 0.0), 2);
            $pending = $class === 'RM' ? $this->landedPending($date) : 0.0;
            $difference = round($gl - ($cards + $pending), 2);
            $row = ['stock_class' => $class, 'account_codes' => implode(',', ValuationAccounts::codes($constant)), 'gl_value' => $gl, 'ledger_value' => $cards,
                'landed_pending' => $pending, 'difference' => $difference, 'status' => abs($difference) > $tolerance ? 'DIFF' : 'OK'];
            if ($save) {
                InvReconcileLog::updateOrCreate(['recon_date' => $date->toDateString(), 'stock_class' => $class], $row + ['created_by' => $login, 'created_at' => now()]);
            }
            $out[] = $row;
        }

        return $out;
    }

    // Saldo debit - kredit akun-akun itu sampai tanggal posting $date (jurnal POSTED, pembalik ikut menghitung).
    public function glValue(array $accountIds, CarbonImmutable $date): float
    {
        if (! $accountIds) {
            return 0.0;
        }
        $net = DB::connection('tm2acc')->table('fin_voucher_line as l')->join('fin_voucher as v', 'v.voucher_id', '=', 'l.voucher_id')
            ->where('v.status', 'POSTED')->where('v.posting_date', '<=', $date->toDateString())->whereIn('l.account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0) AS net')->value('net');

        return round((float) $net, 2);
    }

    private function landedPending(CarbonImmutable $date): float
    {
        $ids = InvLandedCost::query()->where('stat_confirm', 1)->where('stat_cancel', 0)->where('card_synced', 0)->where('doc_date', '<=', $date->toDateString())->pluck('id_landed_cost');

        return round((float) InvLandedCostLine::query()->whereIn('id_landed_cost', $ids)->sum('to_stock'), 2);
    }
}
