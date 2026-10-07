<?php

namespace App\Support\V2\Data;

use App\Models\Sdi\DoDelivery;
use App\Models\Sdi\DoDeliveryPod;
use Devlib\Locale;

// Pemetaan status Delivery Order -> tahap objek DevLib x-dl::delivery-track (user 2026-10-07: versi skematis, status ikut DO).
// DRAFT -> draft, CONFIRMED -> confirmed, SHIPPED -> shipped, SHIPPED + bukti terima (POD) RECEIVED / PARTIAL -> delivered,
// CANCELLED -> cancelled. Tidak ada GPS: posisi truk ditentukan tahap, bukan lokasi nyata.
final class DeliveryTrack
{
    public const RECEIVED = ['RECEIVED', 'PARTIAL'];

    public static function stage(string $status, ?string $podResult = null): string
    {
        return match ($status) {
            'CANCELLED' => 'cancelled',
            'CONFIRMED' => $podResult === 'ACCIDENT' ? 'incident' : 'confirmed',
            'SHIPPED' => match (true) {
                in_array($podResult, self::RECEIVED, true) => 'delivered',
                $podResult === 'ACCIDENT' => 'incident',
                default => 'shipped',
            },
            default => 'draft',
        };
    }

    // Props siap pakai untuk <x-dl::delivery-track v-bind>; nilai data dibungkus raw() supaya tidak masuk kamus.
    public static function props(DoDelivery $do): array
    {
        // Keputusan tahap: sudah diterima (RECEIVED / PARTIAL) menang atas semuanya; selain itu hasil percobaan terakhir (ACCIDENT = kecelakaan).
        $pods = DoDeliveryPod::where('delivery_id', $do->id_delivery)->orderBy('attempt_no')->get();
        $pod = $pods->first(fn ($p) => in_array($p->result, self::RECEIVED, true));
        $decisive = $pod?->result ?? $pods->last()?->result;

        return [
            'stage' => self::stage((string) $do->status, $decisive),
            'from' => t('Warehouse'),
            'to' => raw($do->customer?->customer_name ?? t('Customer')),
            'times' => array_filter([
                'draft' => $do->do_date ? raw(Locale::d($do->do_date)) : '',
                'shipped' => $do->actual_ship_date ? raw(Locale::d($do->actual_ship_date)) : '',
                'delivered' => $pod?->arrived_at ? raw(Locale::d($pod->arrived_at)) : '',
            ]),
        ];
    }
}
