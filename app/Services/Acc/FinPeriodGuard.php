<?php

namespace App\Services\Acc;

use App\Models\Acc\FinPeriod;
use App\Models\Acc\FinPeriodLog;
use App\Models\Acc\FinVoucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// financeaccounting_delta_period_close.md §4 -- satu-satunya penentu boleh
// tidaknya posting ke suatu tanggal & boleh tidaknya periode ditutup.
// CLOSED ireversibel: changeStatus() menolak apa pun dari CLOSED.
class FinPeriodGuard
{
    public function periodFor(\DateTimeInterface|string $date): ?FinPeriod
    {
        $d = Carbon::parse($date)->toDateString();

        return FinPeriod::withoutGlobalScopes()->where('start_date', '<=', $d)->where('end_date', '>=', $d)
            ->where('is_adjustment', false)->first();
    }

    public function assertOpen(\DateTimeInterface|string $postingDate): void
    {
        $d = Carbon::parse($postingDate)->toDateString();
        $period = $this->periodFor($d);

        if (! $period) {
            throw new \DomainException("Tidak ada periode GL untuk tanggal {$d}. Generate tahun fiskal dulu di Period Closing.");
        }
        if ($period->status !== 'OPEN') {
            throw new \DomainException("Periode {$period->period_name} berstatus {$period->status}. Posting ditolak.");
        }
    }

    /** Alasan periode belum boleh ditutup (kosong = boleh). */
    public function closeBlockers(FinPeriod $period): array
    {
        $blockers = [];
        if ($period->status === 'CLOSED') {
            return ['Periode sudah CLOSED — tidak ada cara mengubahnya lagi.'];
        }

        // Gate lintas-modul (§1.4): inv_period bulan sama wajib CLOSED dulu.
        // inv_period memakai kode YYYYMM & status OPEN/CLOSING/CLOSED; CLOSING
        // dianggap belum tutup (FIN-PERIOD-STATUS-VOCAB, keputusan 2026-09-19).
        if (! $period->is_adjustment) {
            $code = sprintf('%04d%02d', $period->fiscal_year, $period->period_no);
            $inv = DB::connection('tm2inv')->table('inv_period')->where('i_period_code', $code)->value('i_status');
            if ($inv !== 'CLOSED') {
                $blockers[] = "Inventory periode {$period->period_name} belum CLOSED (status: ".($inv ?? 'TIDAK ADA').'). Tutup Inventory dulu sebelum GL.';
            }
        }

        $drafts = FinVoucher::withoutGlobalScopes()->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED'])
            ->whereBetween('entry_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->pluck('voucher_number');
        if ($drafts->isNotEmpty()) {
            $blockers[] = 'Masih ada '.$drafts->count().' voucher belum POSTED: '.$drafts->take(5)->implode(', ').($drafts->count() > 5 ? ', …' : '');
        }

        $open = $period->tasks()->where('status', '<>', 'COMPLETE')->count();
        if ($open > 0) {
            $blockers[] = "{$open} item checklist tutup buku belum Complete.";
        }

        return $blockers;
    }

    public function changeStatus(FinPeriod $period, string $newStatus, string $reason, string $user): void
    {
        if (trim($reason) === '') {
            throw new \DomainException('Alasan perubahan status wajib diisi.');
        }
        if ($period->status === 'CLOSED') {
            throw new \DomainException('Periode sudah CLOSED — tidak ada cara mengubahnya lagi.');
        }
        if ($newStatus === 'CLOSED' && ($blockers = $this->closeBlockers($period))) {
            throw new \DomainException(implode(' ', $blockers));
        }

        DB::connection('tm2acc')->transaction(function () use ($period, $newStatus, $reason, $user) {
            $from = $period->status;
            $period->update([
                'status' => $newStatus,
                'closed_by' => $newStatus === 'CLOSED' ? $user : $period->closed_by,
                'closed_at' => $newStatus === 'CLOSED' ? now() : $period->closed_at,
                'updated_by' => $user,
            ]);
            FinPeriodLog::create([
                'period_id' => $period->period_id, 'from_status' => $from, 'to_status' => $newStatus,
                'reason' => $reason, 'changed_by' => $user, 'changed_at' => now(),
            ]);
        });
    }

    // §6: 12 bulan + periode 13 adjustment (ON_HOLD). Idempoten.
    public function generateYear(int $year, string $user): int
    {
        if (FinPeriod::withoutGlobalScopes()->where('fiscal_year', $year)->exists()) {
            return 0;
        }
        for ($m = 1; $m <= 13; $m++) {
            $start = $m <= 12 ? Carbon::create($year, $m, 1) : Carbon::create($year, 12, 31);
            $p = FinPeriod::create([
                'fiscal_year' => $year, 'period_no' => $m,
                'period_name' => $m <= 12 ? $start->translatedFormat('F').' '.$year : 'Penyesuaian '.$year,
                'start_date' => $start->toDateString(),
                'end_date' => $m <= 12 ? $start->copy()->endOfMonth()->toDateString() : $start->toDateString(),
                'is_adjustment' => $m === 13, 'status' => $m === 13 ? 'ON_HOLD' : 'OPEN', 'created_by' => $user,
            ]);
            FinPeriodLog::create(['period_id' => $p->period_id, 'to_status' => $p->status, 'reason' => 'Periode dibuat (generate tahun fiskal)', 'changed_by' => $user, 'changed_at' => now()]);
        }

        return 13;
    }
}
