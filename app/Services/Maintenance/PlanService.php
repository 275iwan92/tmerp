<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Master\MatRaw;
use App\Models\Mnt\MntLog;
use App\Models\Mnt\MntMeterReading;
use App\Models\Mnt\MntOrder;
use App\Models\Mnt\MntPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Aturan Preventive Plan: jatuh tempo berikutnya, kapan plan jatuh tempo, dan pembuatan Preventive Maintenance Order (Draft).
// Jatuh tempo berikutnya selalu dihitung sistem, kecuali tanggal pertama plan FIXED yang diisi user.
final class PlanService
{
    public function __construct(private ?string $login = null)
    {
        $this->login ??= auth()->user()?->login ?? 'system';
    }

    // FLOATING dihitung dari tanggal selesai terakhir; FIXED dari jadwal sebelumnya (tanggal pasti, mis. inspeksi wajib).
    public function nextDue(MntPlan $plan, ?CarbonImmutable $doneAt = null, ?float $meter = null): array
    {
        $out = ['next_due_date' => null, 'next_due_meter' => null];
        if ($plan->usesTime()) {
            $base = $plan->schedule_mode === 'FIXED' && $plan->next_due_date
                ? CarbonImmutable::parse($plan->next_due_date)
                : ($doneAt ?? ($plan->last_done_date ? CarbonImmutable::parse($plan->last_done_date) : CarbonImmutable::today()));
            $next = $this->addInterval($base, (int) $plan->interval_value, (string) $plan->interval_unit);
            // FIXED yang tertinggal jauh tidak menumpuk: lompat ke periode yang belum lewat.
            while ($plan->schedule_mode === 'FIXED' && $next->lt(CarbonImmutable::today())) {
                $next = $this->addInterval($next, (int) $plan->interval_value, (string) $plan->interval_unit);
            }
            $out['next_due_date'] = $next->toDateString();
        }
        if ($plan->usesMeter()) {
            $out['next_due_meter'] = ($meter ?? (float) $plan->last_done_meter) + (float) $plan->meter_interval;
        }

        return $out;
    }

    private function addInterval(CarbonImmutable $d, int $n, string $unit): CarbonImmutable
    {
        return match ($unit) {
            'DAY' => $d->addDays($n), 'WEEK' => $d->addWeeks($n), 'YEAR' => $d->addYearsNoOverflow($n), default => $d->addMonthsNoOverflow($n),
        };
    }

    // Jatuh tempo bila tanggal <= hari ini + lead_days, atau meter sudah melewati batas.
    public function isDue(MntPlan $plan, ?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::today();
        $byTime = $plan->usesTime() && $plan->next_due_date && CarbonImmutable::parse($plan->next_due_date)->subDays((int) $plan->lead_days)->lte($today);
        $cur = $plan->usesMeter() ? MntMeterReading::latestValue($plan->asset_id, (string) $plan->meter_type) : null;
        $byMeter = $plan->usesMeter() && $cur !== null && $plan->next_due_meter !== null && $cur >= (float) $plan->next_due_meter;

        return $byTime || $byMeter;
    }

    // Satu plan -> satu PMO Draft (tidak dobel selama masih ada order terbuka dari plan itu). null = tidak dibuat.
    public function generateFromPlan(MntPlan $plan, bool $force = false): ?MntOrder
    {
        $open = MntOrder::where('plan_id', $plan->id_plan)->where('stat_cancel', false)->where('stat_close', false)->exists();
        if ($open || ! $plan->activate || (! $force && ! $this->isDue($plan))) {
            return null;
        }

        return DB::connection('tm2mnt')->transaction(function () use ($plan) {
            $start = CarbonImmutable::parse($plan->next_due_date ?? now()->addDay())->setTime(7, 0);
            if ($start->lt(now())) {
                $start = CarbonImmutable::tomorrow()->setTime(7, 0);
            }
            $order = MntOrder::create([
                'order_type' => $plan->order_type, 'source' => 'PLAN', 'plan_id' => $plan->id_plan, 'asset_id' => $plan->asset_id, 'priority' => 'P3',
                'description' => mb_substr($plan->plan_name, 0, 255), 'needs_stop' => $plan->needs_stop, 'planned_start' => $start,
                'planned_end' => $plan->est_minutes ? $start->addMinutes((int) $plan->est_minutes) : null, 'est_minutes' => $plan->est_minutes,
                'work_status' => 'DRAFT', 'created_by' => $this->login, 'updated_by' => $this->login,
            ]);
            foreach ($plan->tasks as $t) {
                $order->tasks()->create($t->only(['seq_no', 'task_text', 'task_kind', 'measure_uom', 'min_value', 'max_value', 'is_required']) + ['created_by' => $this->login, 'updated_by' => $this->login]);
            }
            foreach ($plan->parts as $p) {
                $mat = MatRaw::find($p->material_id);
                $order->parts()->create(['material_id' => $p->material_id, 'qty_plan' => $p->qty, 'uom_code' => $p->uom_code ?: $mat?->uom_stock,
                    'unit_cost' => (float) ($mat?->avg_unitprice ?: $mat?->last_unitprice ?: 0), 'created_by' => $this->login, 'updated_by' => $this->login]);
            }
            (new OrderService($this->login))->recost($order);
            MntLog::add('ORDER', $order->id_order, 'Created', 'Generated from '.$plan->plan_code);

            return $order;
        });
    }

    /** Semua plan aktif yang jatuh tempo -> PMO Draft. Kepala maintenance mengatur jadwal lalu Confirm. @return list<MntOrder> */
    public function generateDue(): array
    {
        $made = [];
        foreach (MntPlan::where('activate', true)->with(['tasks', 'parts'])->get() as $plan) {
            if ($order = $this->generateFromPlan($plan)) {
                $made[] = $order;
            }
        }

        return $made;
    }

    // Dipanggil saat PMO Closed: tanggal dan meter selesai terakhir dicatat, jatuh tempo berikutnya dihitung ulang.
    public function afterClose(MntOrder $order): void
    {
        $plan = $order->plan_id ? MntPlan::find($order->plan_id) : null;
        if (! $plan) {
            return;
        }
        $doneAt = CarbonImmutable::parse($order->actual_end ?? now());
        $meter = $order->meter_at_done ?? ($plan->meter_type ? MntMeterReading::latestValue($order->asset_id, (string) $plan->meter_type) : null);
        $plan->update(['last_done_date' => $doneAt->toDateString(), 'last_done_meter' => $meter, 'updated_by' => $this->login] + $this->nextDue($plan, $doneAt, $meter));
    }
}
