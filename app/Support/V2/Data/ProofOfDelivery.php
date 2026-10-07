<?php

namespace App\Support\V2\Data;

use App\Models\Master\CustCustomer;
use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use App\Services\Sales\DeliveryPromiseService;
use Carbon\CarbonImmutable;
use Devlib\Locale;

// Proof of Delivery v2: Surat Jalan yang sudah dikirim (SHIPPED) atau siap kirim (CONFIRMED) -> officer pengiriman mencatat
// jam tiba + hasil terima (satu baris per kedatangan). Aturan tepat/meleset = App\Services\Sales\DeliveryPromiseService.
final class ProofOfDelivery
{
    public const STATE_TONES = ['Waiting' => 'warn', 'Kept' => 'success', 'Missed' => 'danger', 'Retry' => 'info'];

    public static function rows(): array
    {
        $dos = DoDelivery::query()->with(['lines', 'lines.order:id_order,co_no'])->whereIn('status', ['CONFIRMED', 'SHIPPED'])->orderByDesc('scheduled_ship_date')->limit(500)->get();
        $cust = CustCustomer::whereIn('id_customer', $dos->pluck('customer_id')->filter()->unique())->pluck('customer_name', 'id_customer');
        $pods = DoDeliveryPod::whereIn('delivery_id', $dos->modelKeys())->orderBy('attempt_no')->get()->groupBy('delivery_id');

        return $dos->map(function (DoDelivery $d) use ($cust, $pods) {
            $last = $pods->get($d->id_delivery)?->last();

            return ['id' => $d->id_delivery, 'no' => $d->do_no, 'customer' => $cust[$d->customer_id] ?? '—',
                'promise' => ($d->scheduled_ship_date ?? $d->do_date)?->toDateString(), 'arrived' => Locale::dt($last?->arrived_at),
                'result' => $last ? t(DeliveryPromiseService::RESULTS[$last->result] ?? $last->result) : null,
                'address' => $d->ship_address_label, 'items' => DeliveryOrder::lineRows($d), 'state' => self::state($last)];
        })->all();
    }

    // Kept = diterima tepat janji; Retry = belum diterima, akan dikirim ulang; Missed = diterima tapi meleset / tidak jadi.
    public static function state(?DoDeliveryPod $last): string
    {
        return match (true) {
            ! $last => 'Waiting',
            $last->promise_kept => 'Kept',
            in_array($last->result, ['CUSTOMER_CLOSED', 'REJECTED', 'ACCIDENT'], true) => 'Retry',
            default => 'Missed',
        };
    }

    // KPI satu bulan: tiap Surat Jalan dihitung SEKALI menurut kedatangan pertamanya (janji pertama yang menentukan).
    public static function kpi(CarbonImmutable $month): array
    {
        $first = DoDeliveryPod::query()->where('attempt_no', 1)
            ->whereBetween('promise_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])->get();
        $missed = $first->where('promise_kept', false);

        return ['total' => $first->count(), 'kept' => $first->where('promise_kept', true)->count(),
            'pct' => $first->count() ? round($first->where('promise_kept', true)->count() * 100 / $first->count()) : null,
            'by_dept' => $missed->groupBy(fn ($p) => DeliveryPromiseService::CAUSES[$p->miss_cause][1] ?? 'Other')->map->count()->sortDesc()->all(),
            'by_result' => $missed->groupBy('result')->map->count()->all()];
    }
}
