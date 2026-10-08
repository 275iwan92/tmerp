<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Sys\SysConstant;
use App\Services\Planning\MaterialPlanningEngine;
use Devlib\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

// Layar v2 Production > Planning > Pre-Booking (B-01..B-04): kebutuhan kertas sejak ProCost terbit, sebelum ada Customer Order.
// Daftar dan rinci dibaca dari header booking (rm_booking tahap B0 / B1 / B2 tanpa CO) yang ditulis MaterialPlanningEngine::runPre();
// aturan hanya di engine. Pre-booking lunak tidak mengurangi Available; Hold menahan stoknya beberapa hari kerja.
final class PreBookings
{
    public const STAGE_LABELS = ['B0' => 'Not CO yet', 'B1' => 'Quotation', 'B2' => 'Customer Order (draft)'];

    public const STAGE_TONES = ['Not CO yet' => 'info', 'Quotation' => 'info', 'Customer Order (draft)' => 'warn'];

    public const STATUS_TONES = ['Pre-booking' => 'info', 'Held' => 'warn'];

    public static function heads(): Builder
    {
        return RmBooking::query()->where('stat_cancel', 0)->whereNull('co_id')->whereNotNull('chain_key')->orderByDesc('booking_id');
    }

    public static function find(int $bookingId): ?RmBooking
    {
        return self::heads()->where('booking_id', $bookingId)->first();
    }

    public static function rows(): array
    {
        $heads = self::heads()->limit(500)->get();
        $lines = RmBookingD::query()->whereIn('booking_id', $heads->pluck('booking_id'))->where('stat_cancel', 0)->where('qty_need', '>', 0)->orderBy('seq_no')->get()->groupBy('booking_id');
        $inq = DB::connection('tm2sdi')->table('iqp_inquiry')->whereIn('id_inquiry', $heads->pluck('inquiry_id')->filter())->get(['id_inquiry', 'inquiry_no', 'customer_id', 'prospect_name', 'product_draft_name', 'product_id'])->keyBy('id_inquiry');
        $customers = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $inq->pluck('customer_id')->filter())->pluck('customer_name', 'id_customer');
        $products = DB::connection('tm2mst')->table('prod_product')->whereIn('id_product', $inq->pluck('product_id')->filter())->pluck('product_name', 'id_product');

        return $heads->map(function (RmBooking $b) use ($lines, $inq, $customers, $products) {
            $rows = $lines->get($b->booking_id, collect());
            $i = $inq->get($b->inquiry_id);
            $worst = $rows->pluck('line_status')->sortByDesc(fn ($s) => MaterialPlanningEngine::SEVERITY[$s] ?? 0)->first();
            $held = $b->doc_status === 'HELD';

            return ['id' => $b->booking_id, 'inquiry' => $i->inquiry_no ?? ('#'.$b->inquiry_id), 'customer' => $customers[$i->customer_id ?? 0] ?? ($i->prospect_name ?? '—'),
                'product' => $products[$i->product_id ?? 0] ?? ($i->product_draft_name ?? '—'), 'stage' => self::STAGE_LABELS[$b->stage] ?? $b->stage, 'need' => ($min = $rows->min('need_date')) ? substr((string) $min, 0, 10) : null,
                'lines' => $rows->count(), 'short' => $rows->filter(fn ($l) => in_array($l->line_status, ['SHORT', 'NEED_ITEM', 'NEED_LAYOUT'], true))->count(),
                'cover' => MaterialPlanning::COVER[$worst] ?? $worst, 'booking_no' => $b->booking_no, 'status' => $held ? 'Held' : 'Pre-booking', 'hold_until' => $b->hold_until?->toDateString(),
                'items' => $rows->map(fn ($l) => ['material' => $l->spec_text ?: ('#'.$l->material_id), 'qty' => (float) $l->qty_need, 'uom' => $l->uom_code,
                    'need' => $l->need_date?->toDateString(), 'status' => MaterialPlanning::COVER[$l->line_status] ?? $l->line_status])->values()->all()];
        })->all();
    }

    // Baris kebutuhan satu pre-booking untuk grid rinci.
    public static function lineRows(RmBooking $b): array
    {
        $materials = DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', RmBookingD::where('booking_id', $b->booking_id)->pluck('material_id')->filter())->pluck('material_name', 'material_id');

        return RmBookingD::query()->with('allocs')->where('booking_id', $b->booking_id)->where('stat_cancel', 0)->orderBy('seq_no')->get()
            ->map(fn (RmBookingD $d) => ['id' => $d->booking_detail_id, 'material' => $d->material_id ? ($materials[$d->material_id] ?? '#'.$d->material_id) : 'Paper/board (item not chosen)',
                'role' => $d->material_role, 'spec' => $d->spec_text ?: '—', 'need' => $d->need_date?->toDateString(), 'qty' => (float) $d->qty_need, 'uom' => $d->uom_code,
                'covered' => MaterialPlanning::allocText($d->allocs->map(fn ($a) => ['type' => $a->source_type, 'qty' => $a->qty, 'no' => $a->source_no, 'date' => $a->source_date?->toDateString(), 'late' => $a->is_late])->all()),
                'short' => (float) $d->qty_short, 'status' => MaterialPlanning::COVER[$d->line_status] ?? $d->line_status, 'calc' => $d->calc_note])->all();
    }

    // Isi halaman rinci: fakta kepala, pesan keadaan, daftar baris yang itemnya belum dipilih (Pick Item).
    /** @return array{held: bool, row: array<string, mixed>, alert: array{0: string, 1: string}, pick: ?array<int, string>} */
    public static function desk(RmBooking $b): array
    {
        $held = $b->doc_status === 'HELD';
        $pickable = $held ? collect() : RmBookingD::query()->where('booking_id', $b->booking_id)->where('line_status', 'NEED_ITEM')->orderBy('seq_no')->get();

        return ['held' => $held, 'row' => collect(self::rows())->firstWhere('id', $b->booking_id) ?? [],
            'alert' => $held
                ? ['warn', tf('This stock is on hold until :date. After that the hold ends by itself and the stock is available again.', ['date' => Locale::d($b->hold_until) ?? '—'])]
                : ['info', tf('A pre-booking is soft: it does not reduce the available stock and it ends when the quotation expires or the inquiry is cancelled. Use Hold to keep the stock for :n working days.', ['n' => (string) (int) SysConstant::number('INV_BOOKING_HOLD_WORKDAYS', 3)])],
            'pick' => $pickable->isNotEmpty() ? $pickable->mapWithKeys(fn ($d) => [$d->booking_detail_id => $d->seq_no.' · '.($d->spec_text ?: t('no specification')).' · '.Locale::number((float) $d->qty_need, 2).' '.$d->uom_code])->all() : null];
    }
}
