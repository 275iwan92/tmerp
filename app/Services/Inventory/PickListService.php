<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\RmBooking;
use App\Models\Inventory\RmBookingD;
use App\Models\Inventory\WhPickList;
use App\Models\Inventory\WhPickListLine;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Pick list per Work Order (W-05, B-12; docs/md_for_systems/inventorywarehouse_v201_scope.md, gambar docs/mockups/wh_v201_pick_allocation.jpg).
//   create()        booking Confirmed untuk WO itu -> kebutuhan per bahan -> alokasi palet / batch / bin (PickAllocator); kekurangan = baris SHORT.
//   reallocate()    hitung ulang baris yang belum dipindai (stok datang / batch berubah).
//   scanPallet()    konfirmasi palet benar; scanBin() konfirmasi bin benar -> baris PICKED.
//   confirm()       semua baris PICKED -> Stock Out RM ke WO lewat StockDocumentService + StockPostingService (satu dokumen per gudang),
//                   palet dikurangi (label ulang), rm_booking_d.qty_issued naik, pick list DONE.
//   cancel()        batalkan sebelum DONE (belum ada stok bergerak, jadi cukup melepas alokasi).
// Pemilik angka stok tetap Inventory; service ini tidak menulis kartu stok sendiri.
final class PickListService
{
    private const EPS = 0.00005;

    // ------------------------------------------------------------------ buat

    public static function create(int $woId, string $login, ?int $deliverToLocationId = null): WhPickList
    {
        $wo = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $woId)->first() ?? throw new PickListRefused(t('Work order not found.'));
        $open = WhPickList::query()->where('wo_id', $woId)->whereIn('status', WhPickList::OPEN)->first();
        if ($open) {
            throw new PickListRefused(tf('Pick list :no is still open for this work order.', ['no' => $open->pick_no]));
        }

        return DB::connection('tm2inv')->transaction(function () use ($wo, $login, $deliverToLocationId) {
            $booking = self::bookingFor($wo) ?? throw new PickListRefused(t('There is no confirmed booking for this work order. Confirm the material booking first.'));
            // Dua petugas yang membuat pick list dari booking yang sama bergantian (B-14): header booking dikunci sampai selesai.
            RmBooking::query()->whereKey($booking->booking_id)->lockForUpdate()->first();
            $customerId = $booking->co_id ? DB::connection('tm2sdi')->table('co_order')->where('id_order', $booking->co_id)->value('customer_id') : null;
            $list = WhPickList::create(['pick_no' => WhPickList::nextNo(), 'pick_date' => now()->toDateString(), 'wo_id' => $wo->wo_id, 'wo_no' => $wo->wo_no,
                'booking_id' => $booking->booking_id, 'co_id' => $booking->co_id, 'customer_id' => $customerId, 'deliver_to_location_id' => $deliverToLocationId,
                'status' => 'DRAFT', 'created_by' => $login, 'updated_by' => $login]);
            $needs = self::needLines($booking, $wo);
            if ($needs->isEmpty()) {
                throw new PickListRefused(t('The booking has no material left to pick for this work order.'));
            }
            self::fill($list, $needs, $customerId ? (int) $customerId : null);
            if (! $list->lines()->exists()) {
                throw new PickListRefused(t('The booking has no material left to pick for this work order.'));
            }
            $list->forceFill(['need_date' => $needs->min('need_date')])->save();
            $booking->forceFill(['stage' => 'B4', 'update_by' => $login])->save(); // tangga booking: B4 = pick list dibuat
            if (! $deliverToLocationId) {
                $list->forceFill(['deliver_to_location_id' => self::defaultStaging($list)])->save();
            }

            return $list->refresh();
        });
    }

    // Booking Confirmed untuk WO: yang menunjuk WO itu, atau booking Customer Order sumber WO.
    private static function bookingFor(object $wo): ?RmBooking
    {
        return RmBooking::query()->where('stat_confirm', 1)->where('stat_cancel', 0)->where('stat_closed', 0)
            ->where(fn ($q) => $q->where('wo_id', $wo->wo_id)->when($wo->source_co_id, fn ($q2) => $q2->orWhere('co_id', $wo->source_co_id)))
            ->orderByRaw('wo_id IS NULL')->orderBy('booking_id')->first();
    }

    // Baris booking milik WO ini (produk, jadwal kirim, part) yang masih punya kebutuhan: bahan berkode, bukan FG.
    /** @return Collection<int, RmBookingD> */
    private static function needLines(RmBooking $booking, object $wo): Collection
    {
        return RmBookingD::query()->where('booking_id', $booking->booking_id)->where('stat_cancel', 0)->whereNotNull('material_id')->where('material_role', '!=', 'FG')
            ->where('qty_need', '>', 0)->where('product_id', $wo->product_id)
            ->when($wo->source_delivery_id, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('co_delivery_id')->orWhere('co_delivery_id', $wo->source_delivery_id)))
            ->when($wo->parts_id, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('part_id')->orWhere('part_id', $wo->parts_id)))
            ->orderBy('seq_no')->get();
    }

    // Alokasikan sisa kebutuhan tiap baris booking ke palet; yang tidak tertutup jadi satu baris SHORT.
    private static function fill(WhPickList $list, Collection $needs, ?int $customerId): void
    {
        $allocator = new PickAllocator($customerId);
        $materials = DB::connection('tm2mst')->table('mat_raw')->whereIn('material_id', $needs->pluck('material_id')->unique()->all())->get(['material_id', 'material_code', 'material_name', 'uom_stock'])->keyBy('material_id');
        $lineNo = (int) WhPickListLine::query()->where('pick_list_id', $list->id_pick_list)->max('line_no');
        foreach ($needs as $d) {
            $remaining = (float) $d->qty_need - (float) $d->qty_issued - self::heldForDetail((int) $d->booking_detail_id);
            if ($remaining <= self::EPS) {
                continue;
            }
            $m = $materials->get($d->material_id);
            $req = PickRequirement::fromSpec($d->spec_text);
            $base = ['pick_list_id' => $list->id_pick_list, 'booking_detail_id' => $d->booking_detail_id, 'material_id' => $d->material_id, 'item_code' => $m?->material_code,
                'item_name' => $m?->material_name, 'requirement' => $req->label(), 'qty_need' => $remaining, 'uom_code' => $d->uom_code ?: $m?->uom_stock];
            $res = $allocator->allocate((int) $d->material_id, $remaining, $req);
            foreach ($res['lines'] as $a) {
                WhPickListLine::create($base + ['line_no' => ++$lineNo, 'qty_allocated' => $a['qty'], 'pallet_id' => $a['pallet_id'], 'batch_id' => $a['batch_id'],
                    'from_location_id' => $a['from_location_id'], 'note' => $a['note'], 'uom_code' => $a['uom_code'] ?: $base['uom_code'], 'status' => 'ALLOCATED']);
            }
            if ($res['short'] > self::EPS) {
                WhPickListLine::create($base + ['line_no' => ++$lineNo, 'qty_allocated' => 0, 'status' => 'SHORT',
                    'note' => 'Short '.rtrim(rtrim(number_format($res['short'], 4, '.', ''), '0'), '.').': no allowed stock left. Open a Supplier Shortage Request or a substitute proposal.']);
            }
        }
    }

    // Qty baris booking yang sudah dipegang pick list terbuka (alokasi + sudah dipindai), supaya tidak dihitung dua kali.
    private static function heldForDetail(int $detailId): float
    {
        return (float) WhPickListLine::query()->join('wh_pick_list as h', 'h.id_pick_list', '=', 'wh_pick_list_line.pick_list_id')->whereIn('h.status', WhPickList::OPEN)
            ->whereIn('wh_pick_list_line.status', ['ALLOCATED', 'PICKED'])->where('wh_pick_list_line.booking_detail_id', $detailId)->sum('wh_pick_list_line.qty_allocated');
    }

    // Bin staging produksi di gudang bin pertama yang dialokasikan (titik antar bahan ke produksi).
    private static function defaultStaging(WhPickList $list): ?int
    {
        $from = $list->lines()->whereNotNull('from_location_id')->value('from_location_id');
        $warehouse = $from ? WhLoc::query()->whereKey($from)->value('warehouse_id') : null;
        $zones = WhZone::query()->where('zone_type', 'STAGING')->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse))->pluck('id_zone');

        return $zones->isEmpty() ? null : WhLoc::query()->whereIn('zone_id', $zones)->where('activate', true)->where('is_usable', true)->orderBy('location_code')->value('location_id');
    }

    // ------------------------------------------------------------------ hitung ulang

    public static function reallocate(WhPickList $list, string $login): WhPickList
    {
        self::requireOpen($list);

        return DB::connection('tm2inv')->transaction(function () use ($list, $login) {
            $list = WhPickList::query()->lockForUpdate()->findOrFail($list->id_pick_list);
            self::requireOpen($list);
            $list->lines()->whereIn('status', ['ALLOCATED', 'SHORT'])->where(fn ($q) => $q->whereNull('scan_pallet_at'))->delete();
            // Baris yang sudah dipindai palet (belum bin) dan yang sudah PICKED tetap; sisanya dialokasikan ulang.
            $booking = RmBooking::query()->findOrFail($list->booking_id);
            $wo = DB::connection('tm2prd')->table('prd_wo')->where('wo_id', $list->wo_id)->first() ?? throw new PickListRefused(t('Work order not found.'));
            self::fill($list, self::needLines($booking, $wo), $list->customer_id ? (int) $list->customer_id : null);
            $list->forceFill(['updated_by' => $login])->save();

            return $list->refresh();
        });
    }

    // ------------------------------------------------------------------ scan

    public static function scanPallet(WhPickListLine $line, string $scanned, string $login): WhPickListLine
    {
        $list = self::openListOf($line);
        self::requireAllocated($line);
        $pallet = InvPallet::query()->find($line->pallet_id);
        if (! $pallet || $pallet->status !== 'ACTIVE') {
            throw new PickListRefused(t('The allocated pallet is no longer active. Reallocate the pick list.'));
        }
        if (self::code($scanned) !== strtoupper($pallet->pallet_no)) {
            $bin = WhLoc::query()->whereKey($line->from_location_id)->value('location_code');
            throw new PickListRefused(tf('Wrong pallet scanned (:scanned). Take the allocated pallet from bin :bin.', ['scanned' => self::code($scanned), 'bin' => (string) $bin]));
        }
        $line->forceFill(['scan_pallet_at' => now(), 'scan_pallet_by' => $login])->save();
        self::started($list, $login);

        return $line;
    }

    public static function scanBin(WhPickListLine $line, string $scanned, string $login): WhPickListLine
    {
        $list = self::openListOf($line);
        self::requireAllocated($line);
        if (! $line->scan_pallet_at) {
            throw new PickListRefused(t('Scan the pallet first, then the bin.'));
        }
        $pallet = InvPallet::query()->find($line->pallet_id);
        if (! $pallet || $pallet->status !== 'ACTIVE' || (int) $pallet->location_id !== (int) $line->from_location_id || (float) $pallet->qty + self::EPS < (float) $line->qty_allocated) {
            throw new PickListRefused(t('The pallet moved or has less stock than allocated. Reallocate the pick list.'));
        }
        $code = (string) WhLoc::query()->whereKey($line->from_location_id)->value('location_code');
        if (self::code($scanned) !== strtoupper($code)) {
            throw new PickListRefused(tf('Wrong bin scanned (:scanned). The pallet is in bin :bin.', ['scanned' => self::code($scanned), 'bin' => $code]));
        }
        $line->forceFill(['scan_bin_at' => now(), 'scan_bin_by' => $login, 'qty_picked' => $line->qty_allocated, 'status' => 'PICKED'])->save();
        self::started($list, $login);

        return $line;
    }

    // Satu kotak pindai untuk alat pemindai: kode yang cocok dengan bin baris yang palet-nya sudah dipindai = pindai bin; kode yang
    // cocok dengan palet baris yang belum dipindai = pindai palet. Selain itu ditolak (petugas tidak diberi tahu kode yang benar).
    /** @return array{kind: string, line: WhPickListLine} */
    public static function scan(WhPickList $list, string $scanned, string $login): array
    {
        self::requireOpen($list);
        $code = self::code($scanned);
        $lines = $list->lines()->where('status', 'ALLOCATED')->get();
        $bins = WhLoc::query()->whereKey($lines->pluck('from_location_id')->unique()->all())->pluck('location_code', 'location_id');
        $pallets = InvPallet::query()->whereKey($lines->pluck('pallet_id')->unique()->all())->pluck('pallet_no', 'id_pallet');
        $atBin = $lines->first(fn (WhPickListLine $l) => $l->scan_pallet_at && strtoupper((string) ($bins[$l->from_location_id] ?? '')) === $code);
        if ($atBin) {
            return ['kind' => 'bin', 'line' => self::scanBin($atBin, $code, $login)];
        }
        $atPallet = $lines->first(fn (WhPickListLine $l) => ! $l->scan_pallet_at && strtoupper((string) ($pallets[$l->pallet_id] ?? '')) === $code);
        if ($atPallet) {
            return ['kind' => 'pallet', 'line' => self::scanPallet($atPallet, $code, $login)];
        }
        throw new PickListRefused(tf('Scanned code :code is not expected now. Scan the pallet of an open line, then its bin.', ['code' => $code]));
    }

    private static function started(WhPickList $list, string $login): void
    {
        if ($list->status === 'DRAFT') {
            $list->forceFill(['status' => 'IN_PROGRESS', 'updated_by' => $login])->save();
        }
    }

    // Isi pindaian: alamat QR (...?code=PLT:xxx), "PLT:nomor" / "LOC:kode", atau kode polos; huruf besar-kecil tidak dibedakan.
    private static function code(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/[?&]code=([^&#]+)/', $raw, $m)) {
            $raw = rawurldecode($m[1]);
        }

        return strtoupper(trim((string) preg_replace('/^(LOC|PLT|ITM):/i', '', $raw)));
    }

    private static function openListOf(WhPickListLine $line): WhPickList
    {
        $list = WhPickList::query()->findOrFail($line->pick_list_id);
        self::requireOpen($list);

        return $list;
    }

    private static function requireOpen(WhPickList $list): void
    {
        if (! $list->isOpen()) {
            throw new PickListRefused(tf('Pick list :no is already :status.', ['no' => $list->pick_no, 'status' => $list->status]));
        }
    }

    private static function requireAllocated(WhPickListLine $line): void
    {
        if ($line->status !== 'ALLOCATED') {
            throw new PickListRefused(t('Only an allocated line can be scanned.'));
        }
    }

    // ------------------------------------------------------------------ confirm pick

    /** @return array{docs: list<string>, warning: ?string} nomor Stock Out yang lahir, dan peringatan jurnal bila ada */
    public static function confirm(WhPickList $list, string $login): array
    {
        $list = WhPickList::query()->with('lines')->findOrFail($list->id_pick_list);
        self::requireOpen($list);
        if ($list->lines->contains('status', 'SHORT')) {
            throw new PickListRefused(t('Some lines are short. Reallocate when stock arrives, or cancel this pick list.'));
        }
        if ($list->lines->contains('status', 'ALLOCATED')) {
            throw new PickListRefused(t('Scan the pallet and the bin of every line first.'));
        }
        $picked = $list->lines->where('status', 'PICKED');
        if ($picked->isEmpty()) {
            throw new PickListRefused(t('There is nothing picked yet.'));
        }
        // Bin yang dikunci hitung sesudah alokasi: pick ditahan sampai hitungnya selesai (CountTaskService).
        if ($locked = WhLoc::query()->whereKey($picked->pluck('from_location_id')->unique()->all())->where('count_lock', true)->pluck('location_code')->implode(', ')) {
            throw new PickListRefused(tf('The bin :bin is locked for counting.', ['bin' => $locked]));
        }
        $warning = null;
        if (! $list->stock_out_doc_id) {
            [$ids, $nos, $warning] = self::issueStock($list, $picked, $login);
            $list->forceFill(['stock_out_doc_id' => $ids[0], 'stock_out_doc_no' => implode(', ', $nos)])->save();
        }
        self::finish($list, $picked, $login);

        return ['docs' => explode(', ', (string) $list->stock_out_doc_no), 'warning' => $warning];
    }

    // Stock Out RM ke WO, satu dokumen per gudang. Ditolak = pesan posting (mis. saldo kurang); dokumen draft yang sudah dibuat dibatalkan.
    /** @return array{0: list<int>, 1: list<string>, 2: ?string} */
    private static function issueStock(WhPickList $list, Collection $picked, string $login): array
    {
        $docs = new StockDocumentService;
        $posting = new StockPostingService;
        $bins = WhLoc::query()->whereKey($picked->pluck('from_location_id')->unique()->all())->get()->keyBy('location_id');
        $batches = InvBatch::query()->whereKey($picked->pluck('batch_id')->filter()->all())->get()->keyBy('id_batch');
        $dept = DB::connection('tm2mst')->table('org_department')->where('activate', 1)->where('department_name', 'like', 'Production%')->orderBy('department_id')->value('department_id');
        $ids = [];
        $nos = [];
        $warnings = [];
        foreach ($picked->groupBy(fn (WhPickListLine $l) => $bins[$l->from_location_id]->warehouse_id) as $warehouseId => $group) {
            $docNo = $docs->nextDocNo('RM', 'OUT');
            $lines = $group->map(fn (WhPickListLine $l) => ['material_id' => $l->material_id, 'qty' => $l->qty_picked, 'uom_code' => $l->uom_code, 'location_id' => $l->from_location_id,
                'lot_no' => ($b = $batches->get($l->batch_id)) ? ($b->supplier_lot ?: $b->batch_no) : null, 'action_type' => 'ISSUE'])->values()->all();
            $id = $docs->saveDraft('RM', null, ['doc_no' => $docNo, 'doc_date' => now()->toDateString(), 'doc_type' => -1, 'doc_cat' => 'STOCK', 'ref_doc_type' => 'PICK_LIST',
                'ref_doc_id' => $list->id_pick_list, 'ref_doc_no' => $list->pick_no, 'wo_id' => $list->wo_id, 'wo_no' => $list->wo_no, 'warehouse_id' => (int) $warehouseId,
                'issued_to_dept' => $dept, 'notes' => mb_substr('Pick list '.$list->pick_no.' for '.$list->wo_no, 0, 255)], $lines, $login);
            // Baris dokumen menyimpan batch dan booking yang dipenuhinya (kolom ini belum ada di $fillable model baris).
            foreach (DB::connection('tm2inv')->table('rm_stock_d')->where('doc_id', $id)->orderBy('seq_no')->get(['doc_detail_id', 'seq_no']) as $row) {
                $src = $group->values()->get($row->seq_no - 1);
                $owner = PickAllocator::ownerOf((int) $src->material_id, (int) $src->from_location_id, $src->batch_id ? (int) $src->batch_id : null);
                DB::connection('tm2inv')->table('rm_stock_d')->where('doc_detail_id', $row->doc_detail_id)
                    ->update(['batch_id' => $src->batch_id, 'qty_booked' => $src->qty_picked, 'owner_type' => $owner['type'], 'owner_id' => $owner['id']]);
            }
            $res = $posting->postDocument('RM', $id, $login);
            if (! $res['posted']) {
                $docs->cancel('RM', $id, $login);
                throw new PickListRefused((string) $res['error']);
            }
            $ids[] = $id;
            $nos[] = $docNo;
            $warnings[] = $res['journal_warning'] ?? null;
        }

        return [$ids, $nos, implode(' ', array_filter($warnings)) ?: null];
    }

    // Sesudah stok keluar: palet dikurangi, qty_issued booking naik, pick list DONE. Boleh diulang bila terputus (idempoten lewat status).
    private static function finish(WhPickList $list, Collection $picked, string $login): void
    {
        DB::connection('tm2inv')->transaction(function () use ($list, $picked, $login) {
            foreach ($picked->groupBy('pallet_id') as $palletId => $group) {
                $pallet = InvPallet::query()->lockForUpdate()->find($palletId);
                if ($pallet && $pallet->status === 'ACTIVE') {
                    PalletService::relabel($pallet, max(0.0, (float) $pallet->qty - (float) $group->sum('qty_picked')), $login, 'Picked for '.$list->wo_no.' ('.$list->pick_no.')');
                }
            }
            foreach ($picked->groupBy('booking_detail_id') as $detailId => $group) {
                if ($detailId) {
                    RmBookingD::query()->whereKey($detailId)->update(['qty_issued' => DB::raw('qty_issued + '.(float) $group->sum('qty_picked'))]);
                }
            }
            $list->forceFill(['status' => 'DONE', 'confirmed_by' => $login, 'confirmed_at' => now(), 'updated_by' => $login])->save();
        });
    }

    // ------------------------------------------------------------------ batal

    public static function cancel(WhPickList $list, string $reason, string $login): void
    {
        self::requireOpen($list);
        if (trim($reason) === '') {
            throw new PickListRefused(t('Fill in the reason for cancelling.'));
        }
        if ($list->stock_out_doc_id) {
            throw new PickListRefused(t('The stock has already been issued for this pick list.'));
        }
        DB::connection('tm2inv')->transaction(function () use ($list, $reason, $login) {
            $list->lines()->update(['status' => 'CANCELLED']);
            $list->forceFill(['status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason), 0, 255), 'updated_by' => $login])->save();
        });
    }
}
