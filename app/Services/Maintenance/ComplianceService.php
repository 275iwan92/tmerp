<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Mnt\MntOrder;
use App\Models\Sys\SysConstant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// PM Compliance: PMO selesai tepat waktu dibagi PMO yang sudah jatuh tempo. Jatuh tempo = tanggal rencana mulai order.
// Toleransi = persen dari interval plan (konstanta MNT_PM_ONTIME_TOLERANCE_PCT), minimal 1 hari; plan hanya-meter memakai 3 hari.
// Order yang belum lewat jatuh tempo dan belum selesai tidak dihitung (belum bisa dinilai). Order Cancelled tidak dihitung.
final class ComplianceService
{
    public const METER_ONLY_TOLERANCE_DAYS = 3;

    /** Penilaian satu order: on_time | late | not_done | pending. */
    public function judge(MntOrder $order, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $due = CarbonImmutable::parse($order->planned_start)->startOfDay();
        $tolerance = $this->toleranceDays($order);
        $limit = $due->addDays($tolerance);
        $doneAt = in_array($order->work_status, ['DONE'], true) || $order->stat_close ? ($order->actual_end ? CarbonImmutable::parse($order->actual_end)->startOfDay() : null) : null;
        if ($doneAt !== null) {
            return $doneAt->lte($limit) ? 'on_time' : 'late';
        }

        return $limit->lt($today) ? 'not_done' : 'pending';
    }

    private function toleranceDays(MntOrder $order): int
    {
        $plan = $order->plan_id ? $order->plan : null;
        $days = $plan?->intervalDays() ?? 0;
        if ($days <= 0) {
            return self::METER_ONLY_TOLERANCE_DAYS;
        }

        return max(1, (int) round($days * SysConstant::number('MNT_PM_ONTIME_TOLERANCE_PCT', 10) / 100));
    }

    /** PMO (Preventive, Inspection) yang Confirmed dan tidak Cancelled, rencana mulai dalam periode. */
    public function orders(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return MntOrder::query()->with('plan')->whereIn('order_type', MntOrder::typesOf('PMO'))->where('stat_confirm', true)->where('stat_cancel', false)
            ->whereBetween('planned_start', [$from->startOfDay(), $to->endOfDay()])->get();
    }

    /** @return array{due:int, on_time:int, late:int, not_done:int, pending:int, pct:?float} */
    public function summarize(Collection $orders, ?CarbonImmutable $today = null): array
    {
        $c = ['on_time' => 0, 'late' => 0, 'not_done' => 0, 'pending' => 0];
        foreach ($orders as $o) {
            $c[$this->judge($o, $today)]++;
        }
        $due = $c['on_time'] + $c['late'] + $c['not_done'];

        return ['due' => $due] + $c + ['pct' => $due > 0 ? round($c['on_time'] / $due * 100, 1) : null];
    }
}
