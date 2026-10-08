<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Mnt\MntAsset;
use App\Models\Mnt\MntOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

// Jendela mesin yang dipakai Maintenance: order (PMO, BMO) yang Confirmed, belum Done / Close, dan butuh mesin berhenti, pada aset yang tertaut ke Master Mesin.
// Satu sumber untuk penjadwalan mundur, validasi geser manual, dan gambar Scheduling Board, jadi ketiganya tidak pernah berbeda pendapat.
// Rentang = mulai aktual (atau rencana) sampai rencana selesai, atau mulai + estimasi menit, atau 60 menit bila tidak ada keduanya.
final class MaintenanceBlockProvider
{
    private const DEFAULT_MINUTES = 60;

    // Penjadwalan mundur memanggil per mesin per hari berulang-ulang; hasilnya disimpan sebentar saja supaya proses panjang (queue worker) tidak memegang data basi.
    private const CACHE_SECONDS = 30;

    /** @var array<string, list<array{machine_id:int, start:Carbon, end:Carbon, order_id:int, label:string}>> cache per mesin + hari */
    private array $cache = [];

    private int $cachedAt = 0;

    private ?bool $hasTable = null;

    /**
     * @param  list<int>|null  $machineIds
     * @return list<array{machine_id:int, start:Carbon, end:Carbon, order_id:int, label:string}> urut dari yang paling akhir selesai
     */
    public function blocksFor(Carbon $from, Carbon $to, ?array $machineIds = null): array
    {
        if (! ($this->hasTable ??= $this->maintenanceExists())) {
            return [];
        }
        $assets = MntAsset::whereNotNull('machine_id')->where('activate', true)->when($machineIds, fn ($q) => $q->whereIn('machine_id', $machineIds))->pluck('machine_id', 'id_asset');
        if ($assets->isEmpty()) {
            return [];
        }
        $blocks = [];
        $orders = MntOrder::whereIn('asset_id', $assets->keys())->where('stat_confirm', true)->where('stat_cancel', false)->where('stat_close', false)
            ->where('needs_stop', true)->where('work_status', '!=', 'DONE')->whereNotNull('planned_start')
            ->where('planned_start', '<=', $to)->get();
        foreach ($orders as $o) {
            $start = ($o->actual_start ?? $o->planned_start)->copy();
            $end = $o->planned_end?->copy() ?? $start->copy()->addMinutes($o->est_minutes ?: self::DEFAULT_MINUTES);
            if ($end->lte($start)) {
                $end = $start->copy()->addMinutes(self::DEFAULT_MINUTES);
            }
            if ($end->gt($from)) {
                $blocks[] = ['machine_id' => (int) $assets[$o->asset_id], 'start' => $start, 'end' => $end, 'order_id' => $o->id_order, 'label' => ($o->doc_no ?: '#'.$o->id_order).' · '.$o->description];
            }
        }
        usort($blocks, fn ($a, $b) => $b['end']->getTimestamp() <=> $a['end']->getTimestamp());

        return $blocks;
    }

    // Belum dirilis ke database ini (tabel atau database Maintenance belum ada) = tidak ada penahanan; penjadwalan produksi tidak boleh ikut gagal.
    private function maintenanceExists(): bool
    {
        try {
            return Schema::connection('tm2mnt')->hasTable('mnt_order');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array{machine_id:int, start:Carbon, end:Carbon, order_id:int, label:string}> blok mesin ini yang menyentuh hari $day */
    public function blocksOn(Carbon $day, int $machineId): array
    {
        if (time() - $this->cachedAt > self::CACHE_SECONDS) {
            $this->cache = [];
            $this->hasTable = null;
            $this->cachedAt = time();
        }
        $key = $machineId.'|'.$day->toDateString();

        return $this->cache[$key] ??= $this->blocksFor($day->copy()->startOfDay(), $day->copy()->endOfDay(), [$machineId]);
    }

    /** Blok pertama yang bertabrakan dengan [$start, $end] pada mesin ini, atau null. */
    public function overlapping(int $machineId, Carbon $start, Carbon $end): ?array
    {
        foreach ($this->blocksFor($start, $end, [$machineId]) as $b) {
            if ($b['start']->lt($end) && $b['end']->gt($start)) {
                return $b;
            }
        }

        return null;
    }
}
