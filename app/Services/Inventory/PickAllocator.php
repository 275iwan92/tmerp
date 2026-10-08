<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\WhPickList;
use App\Models\Inventory\WhPickListLine;
use App\Models\Master\WhLoc;
use App\Models\Sys\SysConstant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Mesin alokasi pick list: untuk satu bahan, pilih palet / batch / bin yang boleh diambil dan urutannya. Mesin menyarankan,
// petugas gudang memastikan dengan memindai (PickListService). Aturan:
//   - hanya palet ACTIVE di bin yang bisa dipakai (tidak diblokir, tidak dikunci hitung) pada area penyimpanan atau titipan;
//   - hanya batch yang boleh dialokasikan (BatchService::allocatable); Conditional hanya untuk bahan kelas B dan C: batch food grade
//     atau bersertifikat FSC / PEFC (kelas A) tidak pernah Conditional, dan konstanta INV_PICK_CONDITIONAL_ALLOWED = 0 mematikannya;
//   - batch kedaluwarsa tidak dialokasikan; syarat serat / sertifikasi dari spesifikasi booking harus cocok (PickRequirement);
//   - stok milik bukan COMPANY tidak dialokasikan kecuali milik customer CO itu;
//   - urutan ambil per area: FIFO (paling lama datang) atau FEFO (paling dulu kedaluwarsa), wh_zone.pick_order;
//   - palet yang sudah dialokasikan pick list lain yang masih terbuka dikurangi dari yang tersedia.
// Yang tidak tertutup dikembalikan sebagai kekurangan (SHORT); bahan tidak pernah dikarang.
final class PickAllocator
{
    private const EPS = 0.00005;

    private const ZONE_TYPES = ['STORAGE', 'CONSIGN'];

    /** @var array<string, array{type: string, id: ?int}> */
    private array $owners = [];

    public function __construct(private readonly ?int $customerId = null) {}

    /** @return array{lines: list<array<string, mixed>>, short: float} */
    public function allocate(int $materialId, float $need, PickRequirement $req): array
    {
        $lines = [];
        foreach ($this->candidates($materialId, $req) as $c) {
            if ($need <= self::EPS) {
                break;
            }
            $take = min($need, $c['available']);
            $need -= $take;
            $lines[] = ['pallet_id' => $c['pallet']->id_pallet, 'batch_id' => $c['pallet']->batch_id, 'from_location_id' => $c['pallet']->location_id,
                'qty' => $take, 'uom_code' => $c['pallet']->uom_code, 'note' => $c['note']];
        }

        return ['lines' => $lines, 'short' => max(0.0, round($need, 4))];
    }

    /** @return Collection<int, array{pallet: InvPallet, batch: ?InvBatch, loc: WhLoc, available: float, note: string}> urut ambil */
    public function candidates(int $materialId, PickRequirement $req): Collection
    {
        $pallets = InvPallet::query()->where('stock_class', 'RM')->where('item_id', $materialId)->where('status', 'ACTIVE')->where('qty', '>', 0)->whereNotNull('location_id')->get();
        if ($pallets->isEmpty()) {
            return collect();
        }
        $locs = WhLoc::query()->with('zone')->whereKey($pallets->pluck('location_id')->unique()->all())->get()->keyBy('location_id');
        $batches = InvBatch::query()->whereKey($pallets->pluck('batch_id')->filter()->unique()->all())->get()->keyBy('id_batch');
        $held = $this->heldByOpenLists($pallets->pluck('id_pallet')->all());
        $today = now()->startOfDay();

        return $pallets->map(function (InvPallet $p) use ($locs, $batches, $held, $req, $today) {
            $loc = $locs->get($p->location_id);
            $batch = $p->batch_id ? $batches->get($p->batch_id) : null;
            $available = (float) $p->qty - ($held[$p->id_pallet] ?? 0.0);
            $expiry = $batch?->expiry_date ?? $p->expiry_date;
            if (! $loc || ! $this->binUsable($loc) || $available <= self::EPS || ($expiry && $expiry->lt($today)) || $this->batchRefusal($batch, $req) !== null
                || ! $this->ownerAllowed((int) $p->item_id, (int) $p->location_id, $p->batch_id)) {
                return null;
            }
            $mode = $loc->zone?->pick_order === 'FEFO' ? 'FEFO' : 'FIFO';
            $note = $mode === 'FEFO' ? 'FEFO: earliest expiry first' : 'FIFO: oldest received first';
            if ($batch && $batch->qc_status === 'CONDITIONAL') {
                $note .= '; Conditional batch allowed (not a class A job)';
            }

            return ['pallet' => $p, 'batch' => $batch, 'loc' => $loc, 'available' => $available, 'note' => $note,
                'key' => $mode === 'FEFO' ? ($expiry?->toDateString() ?? '9999-12-31') : ($p->arrived_at?->toDateString() ?? '9999-12-31')];
        })->filter()->sortBy([['key', 'asc'], ['pallet.arrived_at', 'asc'], ['pallet.id_pallet', 'asc']])->values();
    }

    // Alasan batch ditolak untuk alokasi (null = boleh). Satu tempat untuk aturan status, kelas A, dan syarat spesifikasi.
    public function batchRefusal(?InvBatch $batch, PickRequirement $req): ?string
    {
        if (! BatchService::allocatable($batch)) {
            return 'batch status '.$batch?->qc_status;
        }
        if ($batch?->qc_status === 'CONDITIONAL' && ((float) SysConstant::number('INV_PICK_CONDITIONAL_ALLOWED', 1) < 1 || $batch->food_grade || in_array($batch->certification, ['FSC', 'PEFC'], true))) {
            return 'Conditional batch is not allowed for a class A job';
        }

        return $req->refusal($batch);
    }

    private function binUsable(WhLoc $loc): bool
    {
        $zone = $loc->zone;

        return $loc->is_usable && $loc->activate && ! $loc->count_lock && (! $zone || (! $zone->is_virtual && in_array($zone->zone_type, self::ZONE_TYPES, true)));
    }

    // Pemilik stok = baris kartu stok terbaru bahan itu di bin itu (dan batch yang sama bila ada); tanpa kartu = COMPANY.
    private function ownerAllowed(int $materialId, int $locationId, ?int $batchId): bool
    {
        $key = $materialId.':'.$locationId.':'.(int) $batchId;
        $o = $this->owners[$key] ??= self::ownerOf($materialId, $locationId, $batchId);

        return $o['type'] === 'COMPANY' || ($o['type'] === 'CUSTOMER' && $this->customerId !== null && (int) $o['id'] === $this->customerId);
    }

    /** @return array{type: string, id: ?int} pemilik stok bahan di bin itu (baris kartu stok terbaru); tanpa kartu = COMPANY */
    public static function ownerOf(int $materialId, int $locationId, ?int $batchId): array
    {
        $row = DB::connection('tm2inv')->table('rm_stockcard')->where('material_id', $materialId)->where('location_id', $locationId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))->orderByDesc('doc_date')->orderByDesc('doc_id')->first(['owner_type', 'owner_id']);

        return ['type' => $row?->owner_type ?: 'COMPANY', 'id' => $row?->owner_id !== null ? (int) $row->owner_id : null];
    }

    /** @return array<int, float> id palet => qty yang sudah dipegang pick list terbuka */
    private function heldByOpenLists(array $palletIds): array
    {
        return WhPickListLine::query()->join('wh_pick_list as h', 'h.id_pick_list', '=', 'wh_pick_list_line.pick_list_id')->whereIn('h.status', WhPickList::OPEN)
            ->whereIn('wh_pick_list_line.status', ['ALLOCATED', 'PICKED'])->whereIn('wh_pick_list_line.pallet_id', $palletIds)
            ->selectRaw('wh_pick_list_line.pallet_id as pid, SUM(wh_pick_list_line.qty_allocated) as qty')->groupBy('wh_pick_list_line.pallet_id')->pluck('qty', 'pid')
            ->map(fn ($q) => (float) $q)->all();
    }
}
