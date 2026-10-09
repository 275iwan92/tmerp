<?php

declare(strict_types=1);

namespace App\Services\Inventory\Valuation;

use App\Models\Inventory\InvPeriod;
use App\Services\Acc\FinPeriodGuard;
use Illuminate\Support\Carbon;

// Tutup / buka periode inventory (inv_period, V-05). CLOSED = StockPostingService menolak dokumen stok bertanggal di dalam atau sebelum periode itu
// (InvPeriod::closedThrough). Aturan:
//   Close : periode sebelumnya harus sudah CLOSED (urut), supaya "closed through" tidak berlubang.
//   Open  : hanya periode CLOSED yang paling akhir, dan hanya bila periode buku besar bulan itu belum CLOSED (FinPeriodGuard).
//   Create: periode bulan berikutnya setelah yang terakhir (OPEN).
final class InvPeriodService
{
    public function __construct(private FinPeriodGuard $gl) {}

    public function close(string $code, string $login): InvPeriod
    {
        $period = $this->find($code);
        if ($period->i_status === 'CLOSED') {
            throw new ValuationRefused(t('The period is already closed.'));
        }
        if (InvPeriod::query()->where('i_period_code', '<', $code)->where('i_status', '<>', 'CLOSED')->exists()) {
            throw new ValuationRefused(t('Close the earlier periods first.'));
        }
        $period->update(['i_status' => 'CLOSED', 'c_closed_by' => $login, 'c_closed_at' => now(), 'updated_by' => $login]);

        return $period;
    }

    public function open(string $code, string $login): InvPeriod
    {
        $period = $this->find($code);
        if ($period->i_status !== 'CLOSED') {
            throw new ValuationRefused(t('Only a closed period can be opened again.'));
        }
        if (InvPeriod::query()->where('i_period_code', '>', $code)->where('i_status', 'CLOSED')->exists()) {
            throw new ValuationRefused(t('Open the later closed periods first.'));
        }
        if ($this->gl->periodFor($period->i_date_start)?->status === 'CLOSED') {
            throw new ValuationRefused(t('The general ledger period of that month is closed; it cannot be opened for inventory.'));
        }
        $period->update(['i_status' => 'OPEN', 'c_closed_by' => null, 'c_closed_at' => null, 'updated_by' => $login]);

        return $period;
    }

    public function createNext(string $login): InvPeriod
    {
        $last = InvPeriod::query()->orderByDesc('i_period_code')->first();
        $start = $last ? Carbon::parse($last->i_date_start)->addMonthNoOverflow()->startOfMonth() : now()->startOfMonth();

        return InvPeriod::create(['i_period_code' => $start->format('Ym'), 'i_date_start' => $start->toDateString(), 'i_date_end' => $start->copy()->endOfMonth()->toDateString(),
            'i_status' => 'OPEN', 'created_by' => $login, 'updated_by' => $login]);
    }

    private function find(string $code): InvPeriod
    {
        return InvPeriod::query()->where('i_period_code', $code)->first() ?? throw new ValuationRefused(t('The period was not found.'));
    }
}
