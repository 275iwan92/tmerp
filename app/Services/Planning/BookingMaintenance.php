<?php

declare(strict_types=1);

namespace App\Services\Planning;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Sys\SysConstant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Perawatan booking (B-03, B-06): Hold yang habis dilepas sendiri; booking Confirmed yang tidak pernah menjadi WO diberi peringatan
// setelah INV_BOOKING_STALE_WARN_DAYS hari sejak tanggal butuh, dan dilepas otomatis setelah INV_BOOKING_STALE_RELEASE_DAYS hari
// (stok kembali Available, seperti reservasi lama SAP yang ditandai hapus). Booking yang sudah ada barang keluarnya tidak dilepas.
// Dipanggil perintah `php artisan inventory:booking-maintain` (belum dijadwalkan; jadwal diputuskan bersama koordinator).
final class BookingMaintenance
{
    public function __construct(private readonly ?string $login = 'system') {}

    /** @return array{holds: list<string>, warned: list<string>, released: list<string>} nomor booking per tindakan */
    public function run(bool $dryRun = false, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $engine = new MaterialPlanningEngine($this->login);
        $out = ['holds' => [], 'warned' => [], 'released' => []];

        foreach (RmBooking::query()->where('doc_status', 'HELD')->where('stat_confirm', 1)->where('stat_cancel', 0)->whereNotNull('hold_until')->where('hold_until', '<', $today->toDateString())->get() as $b) {
            $out['holds'][] = $b->booking_no;
            if (! $dryRun) {
                $engine->releaseHold($b);
            }
        }

        $warnAfter = (int) SysConstant::number('INV_BOOKING_STALE_WARN_DAYS', 7);
        $releaseAfter = (int) SysConstant::number('INV_BOOKING_STALE_RELEASE_DAYS', 30);
        $withWo = DB::connection('tm2prd')->table('prd_wo')->whereNotNull('source_co_id')->where('wo_status', '!=', 'CANCELLED')->pluck('source_co_id')->flip();
        foreach (RmBooking::query()->where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)->whereNotNull('co_id')->whereNull('wo_id')->get() as $b) {
            $need = RmBookingD::query()->where('booking_id', $b->booking_id)->where('stat_cancel', 0)->where('qty_need', '>', 0)->min('need_date');
            if (! $need || isset($withWo[$b->co_id]) || (float) RmBookingD::query()->where('booking_id', $b->booking_id)->sum('qty_issued') > 0) {
                continue;
            }
            $late = (int) CarbonImmutable::parse($need)->startOfDay()->diffInDays($today, false);
            if ($late > $releaseAfter) {
                $out['released'][] = $b->booking_no;
                if (! $dryRun) {
                    $engine->release((int) $b->co_id, 'Stale booking: no work order '.$late.' days after the need date');
                }
            } elseif ($late > $warnAfter && ! $b->stale_warned_on) {
                $out['warned'][] = $b->booking_no;
                if (! $dryRun) {
                    $b->update(['stale_warned_on' => $today->toDateString(), 'update_by' => $this->login]);
                }
            }
        }
        MaterialPlanningEngine::forget();

        return $out;
    }
}
