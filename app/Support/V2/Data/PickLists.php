<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\RmBooking;
use App\Models\Inventory\WhPickList;
use App\Models\Inventory\WhPickListLine;
use App\Models\Master\WhLoc;
use App\Models\Sdi\CoOrder;
use Illuminate\Support\Facades\DB;

// Layar v2 Warehouse > Pick List (docs/mockups/wh_v201_pick_allocation.jpg): menyiapkan baris daftar, kartu per bahan, dan data lapisan
// alokasi di Warehouse Map. Aturan dan alur hanya di App\Services\Inventory\PickListService / PickAllocator.
final class PickLists
{
    public const STATUS_TONES = ['Draft' => 'neutral', 'In progress' => 'warn', 'Done' => 'success', 'Cancelled' => 'danger'];

    public const STATUS_LABELS = ['DRAFT' => 'Draft', 'IN_PROGRESS' => 'In progress', 'DONE' => 'Done', 'CANCELLED' => 'Cancelled'];

    public static function find(int $id): ?WhPickList
    {
        return WhPickList::query()->with('lines')->find($id);
    }

    // Status badge form: pick list lahir dari booking yang sudah Confirm, jadi yang terbuka = Confirmed + tahap kerja.
    public static function formStatus(WhPickList $list): string
    {
        return match ($list->status) {
            'DONE' => 'closed', 'CANCELLED' => 'cancelled', default => 'working',
        };
    }

    /** Work order yang boleh dibuatkan pick list: ada booking Confirmed dan belum ada pick list terbuka. @return array<int, string> */
    public static function workOrderOptions(): array
    {
        $open = WhPickList::query()->whereIn('status', WhPickList::OPEN)->pluck('wo_id')->all();
        $withBooking = RmBooking::query()->where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)->get(['wo_id', 'co_id']);
        $wos = DB::connection('tm2prd')->table('prd_wo')->whereNotIn('wo_status', ['DONE', 'COMPLETED', 'CANCELLED', 'CLOSED'])
            ->where(fn ($q) => $q->whereIn('wo_id', $withBooking->pluck('wo_id')->filter()->all())->orWhereIn('source_co_id', $withBooking->pluck('co_id')->filter()->all()))
            ->whereNotIn('wo_id', $open ?: [0])->orderByDesc('wo_id')->limit(200)->get(['wo_id', 'wo_no', 'wo_status', 'qty', 'due_date']);

        return $wos->mapWithKeys(fn ($w) => [$w->wo_id => $w->wo_no.' · '.$w->wo_status])->all();
    }

    public static function rows(): array
    {
        $lists = WhPickList::query()->with('lines')->orderByDesc('id_pick_list')->limit(500)->get();
        $bookings = RmBooking::query()->whereKey($lists->pluck('booking_id')->filter()->all())->pluck('booking_no', 'booking_id');
        $cos = CoOrder::query()->whereKey($lists->pluck('co_id')->filter()->all())->pluck('co_no', 'id_order');
        $bins = WhLoc::query()->whereKey($lists->flatMap(fn (WhPickList $l) => $l->lines->pluck('from_location_id')->push($l->deliver_to_location_id))->filter()->unique()->all())->pluck('location_code', 'location_id');
        $pallets = InvPallet::query()->whereKey($lists->flatMap(fn (WhPickList $l) => $l->lines->pluck('pallet_id'))->filter()->unique()->all())->pluck('pallet_no', 'id_pallet');

        return $lists->map(fn (WhPickList $l) => [
            'id' => $l->id_pick_list, 'no' => $l->pick_no, 'wo' => $l->wo_no, 'booking' => $bookings[$l->booking_id] ?? null, 'co' => $cos[$l->co_id] ?? null,
            'date' => $l->pick_date?->toDateString(), 'need' => $l->need_date?->toDateString(), 'deliver_to' => $bins[$l->deliver_to_location_id] ?? null,
            'status' => self::STATUS_LABELS[$l->status] ?? $l->status,
            'short' => $l->lines->contains('status', 'SHORT') ? 'Short' : null, 'tone' => $l->lines->contains('status', 'SHORT') && $l->isOpen() ? 'danger' : null,
            'items' => $l->lines->where('status', '!=', 'CANCELLED')->map(fn (WhPickListLine $x) => ['code' => $x->item_code, 'name' => $x->item_name, 'qty' => $x->qty_allocated ?: $x->qty_need,
                'unit' => $x->uom_code, 'pallet' => $pallets[$x->pallet_id] ?? '—', 'bin' => $bins[$x->from_location_id] ?? '—'])->values()->all(),
        ])->all();
    }

    // Rujukan kepala pick list yang disimpan sebagai id: nomor booking, nomor CO, kode bin tujuan antar.
    /** @return array{booking: ?string, co: ?string, deliver_to: ?string} */
    public static function header(WhPickList $list): array
    {
        return ['booking' => RmBooking::query()->whereKey($list->booking_id)->value('booking_no'), 'co' => CoOrder::query()->whereKey($list->co_id)->value('co_no'),
            'deliver_to' => $list->deliver_to_location_id ? WhLoc::query()->whereKey($list->deliver_to_location_id)->value('location_code') : null];
    }

    // Kartu per bahan: judul + kebutuhan, baris palet, ringkasan alokasi, dan catatan (Conditional / urutan area).
    /** @return list<array<string, mixed>> */
    public static function materialCards(WhPickList $list): array
    {
        $lines = $list->lines->where('status', '!=', 'CANCELLED')->values();
        $pallets = InvPallet::query()->whereKey($lines->pluck('pallet_id')->filter()->all())->get()->keyBy('id_pallet');
        $batches = InvBatch::query()->whereKey($lines->pluck('batch_id')->filter()->all())->get()->keyBy('id_batch');
        $bins = WhLoc::query()->whereKey($lines->pluck('from_location_id')->filter()->all())->pluck('location_code', 'location_id');

        return $lines->groupBy(fn (WhPickListLine $l) => $l->booking_detail_id.':'.$l->material_id)->map(function ($group) use ($pallets, $batches, $bins) {
            $first = $group->first();
            $alloc = (float) $group->sum('qty_allocated');
            $need = (float) $first->qty_need;
            $short = max(0.0, round($need - $alloc, 4));
            $conditional = $group->first(fn (WhPickListLine $l) => $batches->get($l->batch_id)?->qc_status === 'CONDITIONAL');

            return ['title' => trim($first->item_code.' · '.$first->item_name, ' ·'), 'need' => $need, 'unit' => $first->uom_code, 'allocated' => $alloc, 'short' => $short,
                'requirement' => $first->requirement, 'note' => $group->where('status', '!=', 'SHORT')->pluck('note')->filter()->unique()->implode('; '),
                'warning' => $conditional ? 'Conditional batch allowed: this is not a class A job. The pick order still decides which pallet is taken first.' : null,
                'rows' => $group->where('status', '!=', 'SHORT')->map(function (WhPickListLine $l) use ($pallets, $batches, $bins) {
                    $p = $pallets->get($l->pallet_id);
                    $b = $batches->get($l->batch_id);

                    return ['id' => $l->id_pick_list_line, 'no' => $l->line_no, 'pallet' => $p?->pallet_no ?? '—', 'batch' => $b?->batch_no ?? '—', 'bin' => $bins[$l->from_location_id] ?? '—',
                        'received' => $p?->arrived_at?->toDateString(), 'expiry' => ($b?->expiry_date ?? $p?->expiry_date)?->toDateString(), 'pick' => $l->qty_allocated,
                        'quality' => $b ? ($b->qc_status ? InvBatch::STATUSES[$b->qc_status]['label'] : '—') : 'No batch', 'grain' => $b?->grain_direction ?? '—',
                        'cert' => $b && $b->certification !== 'NONE' ? $b->certification : '—', 'scan' => match (true) {
                            $l->status === 'PICKED' => 'Scanned', $l->scan_pallet_at !== null => 'Pallet scanned, scan the bin', default => 'Scan pallet, then bin',
                        }];
                })->values()->all()];
        })->values()->all();
    }

    // Bagian yang tidak tertutup: satu baris per bahan yang SHORT.
    /** @return list<array<string, mixed>> */
    public static function shortRows(WhPickList $list): array
    {
        return $list->lines->where('status', 'SHORT')->map(fn (WhPickListLine $l) => ['id' => $l->id_pick_list_line, 'item' => $l->item_name, 'code' => $l->item_code, 'need' => $l->qty_need,
            'allocated' => $list->lines->where('booking_detail_id', $l->booking_detail_id)->sum('qty_allocated'), 'unit' => $l->uom_code, 'requirement' => $l->requirement ?? '—'])
            ->map(fn (array $r) => $r + ['short' => max(0.0, round((float) $r['need'] - (float) $r['allocated'], 4))])->values()->all();
    }

    // Lapisan alokasi di Warehouse Map untuk satu pick list: bin yang dituju berurutan (1, 2, ...) dengan qty dan palet, plus staging.
    // Peta memakai ini untuk menggambar nomor urutan ambil dan rute ke staging; tanpa pick list yang dipilih, peta tidak berubah.
    /** @return array{pick: ?string, wo: ?string, bins: list<array<string, mixed>>, deliver_to: ?array<string, mixed>} */
    public static function mapLayer(int $pickListId): array
    {
        $list = self::find($pickListId);
        if (! $list) {
            return ['pick' => null, 'wo' => null, 'bins' => [], 'deliver_to' => null];
        }
        $lines = $list->lines->where('status', 'ALLOCATED')->merge($list->lines->where('status', 'PICKED'))->sortBy('line_no')->values();
        $locs = WhLoc::query()->with('zone')->whereKey($lines->pluck('from_location_id')->push($list->deliver_to_location_id)->filter()->unique()->all())->get()->keyBy('location_id');
        $pallets = InvPallet::query()->whereKey($lines->pluck('pallet_id')->filter()->all())->pluck('pallet_no', 'id_pallet');
        $bins = [];
        foreach ($lines->groupBy('from_location_id') as $locationId => $group) {
            $loc = $locs->get($locationId);
            $bins[] = ['order' => count($bins) + 1, 'location_id' => (int) $locationId, 'code' => $loc?->location_code, 'zone' => $loc?->zone?->zone_code, 'map_x' => $loc?->map_x, 'map_y' => $loc?->map_y,
                'qty' => (float) $group->sum('qty_allocated'), 'unit' => $group->first()->uom_code, 'item' => $group->first()->item_name,
                'pallets' => $group->map(fn (WhPickListLine $l) => $pallets[$l->pallet_id] ?? null)->filter()->values()->all(), 'picked' => $group->every(fn (WhPickListLine $l) => $l->status === 'PICKED')];
        }
        $to = $list->deliver_to_location_id ? $locs->get($list->deliver_to_location_id) : null;

        return ['pick' => $list->pick_no, 'wo' => $list->wo_no, 'bins' => $bins,
            'deliver_to' => $to ? ['location_id' => (int) $to->location_id, 'code' => $to->location_code, 'zone' => $to->zone?->zone_code, 'map_x' => $to->map_x, 'map_y' => $to->map_y] : null];
    }

    /** Bin staging produksi yang bisa dipilih sebagai tujuan antar. @return array<int, string> */
    public static function stagingOptions(): array
    {
        $zones = DB::connection('tm2mst')->table('wh_zone')->where('zone_type', 'STAGING')->pluck('id_zone');

        return WhLoc::query()->whereIn('zone_id', $zones)->where('activate', true)->orderBy('location_code')->pluck('location_code', 'location_id')->all();
    }
}
