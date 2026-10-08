<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use Devlib\Locale;

// Usulan bin untuk putaway (engine suggests, officer confirms; gambar docs/mockups/wh_v201_receiving_putaway.jpg, kotak 4).
// Urutan aturan:
//   1. batch boleh dialokasikan (BatchService::allocatable: Rejected / Blocked / Quarantine tidak diusulkan)
//   2. area Storage yang aktif, bukan virtual, kelas stok cocok (ANY atau sama dengan palet)
//   3. bin aktif, bisa dipakai, tidak terkunci hitung (count_lock); aturan Mixing area (BinRules)
//   4. kapasitas: berat (capacity_kg), jumlah palet (capacity_pallet), tumpukan (max_stack area); berat yang tidak bisa diperkirakan tidak menolak
//   5. urutan: gedung yang sama dengan palet dulu, urutan area (sort_order), bin kosong lebih dulu, kode bin
// Satu instance = satu rangkaian usulan: bin yang sudah diusulkan untuk palet lain (reserve) dihitung terisi pada usulan berikutnya.
// Dock-to-line (toLine) tidak melewati putaway: tujuannya bin area WIP, lalu Staging.
final class PutawayPlanner
{
    private const SCAN_LIMIT = 30;

    /** @var array<int, array{kg: float, count: int, items: array<string, true>, batches: array<string, true>}> */
    private array $reserved = [];

    /** @return list<array{bin: WhLoc, zone: WhZone, empty: bool, free_kg: ?float, why: string}> */
    public function suggest(InvPallet $pallet, ?InvBatch $batch, int $limit = 5): array
    {
        if (! BatchService::allocatable($batch)) {
            return [];
        }
        $source = WhLoc::query()->find($pallet->location_id);
        $zones = WhZone::query()->where('activate', true)->where('is_virtual', false)->where('zone_type', 'STORAGE')->whereIn('stock_class', ['ANY', $pallet->stock_class])->get()
            ->sortBy(fn (WhZone $z) => [(int) $z->warehouse_id === (int) $source?->warehouse_id ? 0 : 1, (int) $z->sort_order, $z->zone_code])->values();
        if ($zones->isEmpty()) {
            return [];
        }
        $zoneRank = $zones->pluck('id_zone')->flip();
        $bins = WhLoc::query()->whereIn('zone_id', $zones->pluck('id_zone')->all())->where('activate', true)->where('is_usable', true)->where('count_lock', false)
            ->whereKeyNot($pallet->location_id)->get();
        $occupied = WarehouseStock::positions($bins->pluck('location_id')->all())->pluck('location_id')->flip();
        $zoneById = $zones->keyBy('id_zone');
        $ordered = $bins->sortBy(fn (WhLoc $b) => [$zoneRank[$b->zone_id], $occupied->has($b->location_id) ? 1 : 0, $b->location_code])->values();

        $item = WarehouseStock::key($pallet->stock_class, (int) $pallet->item_id, $pallet->part_id ? (int) $pallet->part_id : null, 0);
        $batchKey = (string) ($pallet->batch_id ?: 'none');
        $scan = [];
        foreach ($ordered as $bin) {
            if (count($scan) >= self::SCAN_LIMIT) {
                break;
            }
            $zone = $zoneById[$bin->zone_id];
            if ($this->conflictsWithReserved($bin, $zone, $item, $batchKey)) {
                continue;
            }
            if ($occupied->has($bin->location_id) && BinRules::mixViolation($bin, $zone, $pallet->stock_class, (int) $pallet->item_id, $pallet->part_id ? (int) $pallet->part_id : null,
                $pallet->batch_id ? (int) $pallet->batch_id : null, (int) $pallet->id_pallet)) {
                continue;
            }
            $scan[] = $bin;
        }

        $summary = WarehouseStock::binSummaries(collect($scan));
        $weight = $this->weightKg($pallet);
        $out = [];
        foreach ($scan as $bin) {
            $zone = $zoneById[$bin->zone_id];
            $s = $summary[$bin->location_id];
            $res = $this->reserved[$bin->location_id] ?? ['kg' => 0.0, 'count' => 0];
            $isPallet = ! $pallet->isPack();
            $count = $s['pallet_count'] + $res['count'] + ($isPallet ? 1 : 0);
            $used = (float) $s['used_kg'] + $res['kg'];
            $capKg = $s['capacity_kg'];
            if (($capKg !== null && $weight !== null && $used + $weight > $capKg + 0.005)
                || ($bin->capacity_pallet && $count > (int) $bin->capacity_pallet)
                || ($s['max_stack'] !== null && $count > (int) $s['max_stack'])) {
                continue;
            }
            $free = $capKg === null ? null : round($capKg - $used, 2);
            $out[] = ['bin' => $bin, 'zone' => $zone, 'empty' => ! $occupied->has($bin->location_id) && $res['count'] === 0, 'free_kg' => $free, 'why' => $this->why($zone, ! $occupied->has($bin->location_id), $free)];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    // Tandai bin usulan terpakai oleh palet ini, supaya usulan palet berikutnya tidak menumpuk melebihi kapasitas / aturan Mixing.
    public function reserve(WhLoc $bin, InvPallet $pallet): void
    {
        $r = &$this->reserved[$bin->location_id];
        $r ??= ['kg' => 0.0, 'count' => 0, 'items' => [], 'batches' => []];
        $r['kg'] += (float) $this->weightKg($pallet);
        $r['count'] += $pallet->isPack() ? 0 : 1;
        $r['items'][WarehouseStock::key($pallet->stock_class, (int) $pallet->item_id, $pallet->part_id ? (int) $pallet->part_id : null, 0)] = true;
        $r['batches'][(string) ($pallet->batch_id ?: 'none')] = true;
    }

    // Dock-to-line: bin tujuan di area WIP (lalu Staging), gedung yang sama dengan palet lebih dulu. null = belum ada area / bin untuk itu.
    /** @return array{bin: WhLoc, zone: WhZone, why: string}|null */
    public function toLine(InvPallet $pallet, ?string $woNo = null): ?array
    {
        $source = WhLoc::query()->find($pallet->location_id);
        foreach (['WIP', 'STAGING'] as $type) {
            $zones = WhZone::query()->where('activate', true)->where('is_virtual', false)->where('zone_type', $type)->get()
                ->sortBy(fn (WhZone $z) => [(int) $z->warehouse_id === (int) $source?->warehouse_id ? 0 : 1, (int) $z->sort_order, $z->zone_code]);
            foreach ($zones as $zone) {
                $bin = WhLoc::query()->where('zone_id', $zone->id_zone)->where('activate', true)->where('is_usable', true)->where('count_lock', false)->orderBy('location_code')->first();
                if ($bin) {
                    return ['bin' => $bin, 'zone' => $zone, 'why' => $woNo ? tf('dock to line for :wo', ['wo' => $woNo]) : t('dock to line')];
                }
            }
        }

        return null;
    }

    private function conflictsWithReserved(WhLoc $bin, WhZone $zone, string $item, string $batchKey): bool
    {
        $r = $this->reserved[$bin->location_id] ?? null;
        if (! $r) {
            return false;
        }

        return match ($zone->mix_rule) {
            'ONE_ITEM' => array_keys($r['items']) !== [$item],
            'ONE_BATCH' => array_keys($r['batches']) !== [$batchKey],
            default => false,
        };
    }

    // Perkiraan berat palet (kg); null = tidak bisa diperkirakan (kapasitas berat tidak menolak).
    private function weightKg(InvPallet $pallet): ?float
    {
        $material = $pallet->stock_class === 'RM' ? MatRaw::withoutGlobalScopes()->find($pallet->item_id) : null;

        return WarehouseStock::weightKg((float) $pallet->qty, (string) $pallet->uom_code, $material, (string) $pallet->item_name);
    }

    private function why(WhZone $zone, bool $empty, ?float $freeKg): string
    {
        return collect([$empty ? t('empty bin') : t('has room'), match ($zone->mix_rule) {
            'ONE_ITEM' => t('1 item/bin'), 'ONE_BATCH' => t('1 batch/bin'), default => t('mixed items'),
        }, $zone->pick_order, $freeKg === null ? null : tf('capacity :kg kg ok', ['kg' => Locale::decimal($freeKg, 0)])])->filter()->implode(' · ');
    }
}
