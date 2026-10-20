<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Management Reports > Production & Manufacturing (v2): status Work Order, WO selesai per bulan, reject rate output dan WO aktif yang paling dekat jatuh tempo.
 * Sumber hulu  : prd_wo, prd_wo_operation_output, prd_wo_operation_output_defect (tm2prd, disaring Visible::apply); aturan sama dengan layar v1 pages::report.production.
 * Input        : th (tahun; kosong = tahun berjalan).
 * Output       : angka KPI, deret grafik, baris tabel.
 * Dipakai oleh : layar v2 mgmt-production.
 * Status       : Aktif (T9 Management Reports, 2026-10-20).
 */

namespace App\Support\V2\Data\Mgmt;

use App\Support\DataOrigin\Visible;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class ProductionReport extends MgmtReport
{
    public readonly int $year;

    public function __construct(array $q)
    {
        $this->year = ! empty($q['th']) ? (int) $q['th'] : (int) now()->year;
    }

    /** @return array<string, int> jumlah WO per status */
    public function woByStatus(): array
    {
        $rows = Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')->whereYear('created_at', $this->year)
            ->selectRaw('wo_status, COUNT(*) as n')->groupBy('wo_status')->pluck('n', 'wo_status');

        return array_combine($s = ['DRAFT', 'SCHEDULED', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED'], array_map(fn ($k) => (int) ($rows[$k] ?? 0), $s));
    }

    public function totalWo(): int
    {
        return array_sum($this->woByStatus());
    }

    public function overdueOpen(): int
    {
        return Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')->whereIn('wo_status', ['SCHEDULED', 'IN_PROGRESS', 'ON_HOLD'])->where('due_date', '<', now())->count();
    }

    /** Reject qty / (Good + Reject qty), seluruh WO Operation Output tahun ini; null bila belum ada output. */
    public function outputDefectRate(): ?float
    {
        $good = (float) Visible::apply(DB::connection('tm2prd')->table('prd_wo_operation_output'), 'tm2prd', 'prd_wo_operation_output')->whereYear('created_at', $this->year)->sum('qty_good');
        $reject = (float) Visible::apply(DB::connection('tm2prd')->table('prd_wo_operation_output_defect as d')->join('prd_wo_operation_output as o', 'o.output_id', '=', 'd.output_id'), 'tm2prd', 'prd_wo_operation_output', 'o')
            ->whereYear('o.created_at', $this->year)->sum('d.qty');

        return ($good + $reject) <= 0 ? null : round(($reject / ($good + $reject)) * 100, 2);
    }

    /** @return list<int> WO selesai per bulan */
    public function monthlyCompleted(): array
    {
        $rows = Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')->where('wo_status', 'COMPLETED')->whereYear('due_date', $this->year)
            ->selectRaw('MONTH(due_date) as m, COUNT(*) as n')->groupBy('m')->pluck('n', 'm');

        return collect(range(1, 12))->map(fn ($m) => (int) ($rows[$m] ?? 0))->all();
    }

    /** @return list<array<string, string>> 6 WO aktif dengan jatuh tempo terdekat */
    public function longestOpenWoRows(): array
    {
        return Visible::apply(DB::connection('tm2prd')->table('prd_wo'), 'tm2prd', 'prd_wo')->whereIn('wo_status', ['SCHEDULED', 'IN_PROGRESS', 'ON_HOLD'])->orderBy('due_date')->limit(6)
            ->get(['wo_id', 'wo_no', 'wo_status', 'due_date'])
            ->map(function ($r) {
                $late = now()->diffInDays($r->due_date, false) < 0 ? abs((int) now()->diffInDays($r->due_date, false)) : 0;

                return ['no' => raw($r->wo_no), 'status' => raw($r->wo_status), 'due' => Locale::date($r->due_date), 'late' => $late > 0 ? tf(':n days', ['n' => $late]) : '—'];
            })->all();
    }
}
