<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvTransferLine;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use Illuminate\Support\Collection;

// Aturan area yang menjadi larangan keras saat stok MASUK ke sebuah bin (v2.01): Mixing area (WhZone::MIX_RULES).
//   ONE_ITEM  = satu bin hanya berisi satu item (kelas + item + part yang sama)
//   ONE_BATCH = satu bin hanya berisi satu batch
// Dipakai putaway (WarehouseTaskService, PutawayPlanner) dan Stock Transfer (StockTransferService::confirmErrors).
// Kapasitas bin (berat, jumlah palet, tumpukan) BUKAN larangan: itu usulan / peringatan (PutawayPlanner, transfer).
final class BinRules
{
    private const NO_BATCH = 'none';

    // Pesan salah bila item ini tidak boleh masuk bin itu; null = boleh. $ignorePalletId = palet yang sedang dipindah (isinya tidak dihitung).
    public static function mixViolation(WhLoc $bin, ?WhZone $zone, string $class, int $itemId, ?int $partId, ?int $batchId, ?int $ignorePalletId = null): ?string
    {
        $rule = $zone->mix_rule ?? 'ANY';
        if ($rule === 'ANY') {
            return null;
        }
        $positions = WarehouseStock::positions([(int) $bin->location_id]);
        if ($positions->isEmpty()) {
            return null;
        }
        $mine = WarehouseStock::key($class, $itemId, $partId, 0);
        if ($rule === 'ONE_ITEM') {
            $other = $positions->first(fn (array $p) => WarehouseStock::key($p['class'], $p['item_id'], $p['part_id'], 0) !== $mine);

            return $other ? tf('The bin :bin allows one item only and already holds :item.', ['bin' => $bin->location_code, 'item' => $other['code'] ?: $other['name']]) : null;
        }
        // ONE_BATCH: batch yang sudah ada di bin dibaca dari palet aktifnya; stok tanpa palet dihitung "tanpa batch".
        $pallets = InvPallet::query()->where('status', 'ACTIVE')->where('location_id', $bin->location_id)->where('qty', '>', 0)
            ->when($ignorePalletId, fn ($q) => $q->whereKeyNot($ignorePalletId))->get();
        $existing = $pallets->map(fn (InvPallet $p) => $p->batch_id ? (string) $p->batch_id : self::NO_BATCH);
        if ($existing->isEmpty()) {
            $existing = collect([self::NO_BATCH]);
        }
        $incoming = $batchId ? (string) $batchId : self::NO_BATCH;

        return $existing->contains(fn (string $b) => $b !== $incoming)
            ? tf('The bin :bin allows one batch only and already holds another batch.', ['bin' => $bin->location_code]) : null;
    }

    // Bin yang dikunci hitung (wh_loc.count_lock) tidak boleh menjadi asal maupun tujuan transfer. Kunci dipasang dan dilepas CountTaskService.
    /** @param  Collection<int, InvTransferLine>  $lines
     *  @return list<string> */
    public static function countLockViolations(Collection $lines): array
    {
        $ids = $lines->pluck('from_location_id')->merge($lines->pluck('to_location_id'))->filter()->unique()->all();
        $locked = WhLoc::query()->whereKey($ids ?: [0])->where('count_lock', true)->pluck('location_code', 'location_id');
        $errors = [];
        foreach ($lines as $l) {
            foreach ([$l->from_location_id, $l->to_location_id] as $binId) {
                if ($binId && isset($locked[$binId])) {
                    $errors[] = tf('Line :n: the bin :bin is locked for counting.', ['n' => $l->line_no, 'bin' => $locked[$binId]]);
                }
            }
        }

        return array_values(array_unique($errors));
    }

    // Pemeriksaan untuk satu dokumen Stock Transfer: tiap baris terhadap bin tujuannya, dan baris-baris di dokumen yang sama.
    /** @param  Collection<int, InvTransferLine>  $lines
     *  @return list<string> */
    public static function transferViolations(Collection $lines): array
    {
        $bins = WhLoc::query()->whereKey($lines->pluck('to_location_id')->filter()->unique()->all())->get()->keyBy('location_id');
        $zones = WhZone::query()->whereKey($bins->pluck('zone_id')->filter()->unique()->all())->get()->keyBy('id_zone');
        $pallets = InvPallet::query()->whereKey($lines->pluck('pallet_id')->filter()->unique()->all())->get()->keyBy('id_pallet');
        $errors = [];
        $batches = [];
        foreach ($lines as $l) {
            $bin = $bins->get($l->to_location_id);
            $zone = $bin ? $zones->get($bin->zone_id) : null;
            if (! $bin || ! $zone || $zone->mix_rule === 'ANY') {
                continue;
            }
            $batchId = $l->batch_id ?: ($l->pallet_id ? $pallets->get($l->pallet_id)?->batch_id : null);
            if ($msg = self::mixViolation($bin, $zone, $l->stock_class, (int) $l->item_id, $l->part_id ? (int) $l->part_id : null, $batchId ? (int) $batchId : null, $l->pallet_id ? (int) $l->pallet_id : null)) {
                $errors[] = tf('Line :n: :msg', ['n' => $l->line_no, 'msg' => $msg]);
            }
            // Baris lain di dokumen yang sama menuju bin yang sama harus memenuhi aturan yang sama.
            $key = $zone->mix_rule === 'ONE_ITEM' ? WarehouseStock::key($l->stock_class, (int) $l->item_id, $l->part_id ? (int) $l->part_id : null, 0) : (string) ($batchId ?: self::NO_BATCH);
            $seen = $batches[$l->to_location_id] ?? null;
            if ($seen !== null && $seen !== $key) {
                $errors[] = tf('Line :n: the bin :bin allows one :what only, but this transfer sends more than one to it.', ['n' => $l->line_no, 'bin' => $bin->location_code,
                    'what' => $zone->mix_rule === 'ONE_ITEM' ? t('item') : t('batch')]);
            }
            $batches[$l->to_location_id] ??= $key;
        }

        return $errors;
    }
}
