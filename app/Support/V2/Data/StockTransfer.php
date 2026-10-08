<?php

namespace App\Support\V2\Data;

use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Master\MatRaw;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Services\Flow\FlowEngineService;
use App\Services\Inventory\StockTransferService;
use App\Services\Inventory\WarehouseStock;
use App\Support\V2\Data\Concerns\FlowDocument;
use Devlib\Locale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Stock Transfer v2 (user 2026-10-02: "input moving item antar warehouse"): GRID -> + New Record = pilih gudang asal dan
// tujuan -> DRAFT: baris = posisi stok asal (item di satu bin) + bin tujuan + qty -> Confirm (stok pindah, nomor lahir,
// siap dicetak) -> Cancel wajib alasan (stok kembali). Posting kartu stok = App\Services\Inventory\StockTransferService.
// Tiap baris menampilkan label kapasitas bin tujuan dan tanda DEAD STOCK; lebih dari kapasitas = peringatan, bukan larangan
// (engine menyarankan, petugas memutuskan).
// Approval = modul flow 'TF' (Setup > Setting Approval), bawaan TIDAK aktif: Confirm langsung memindahkan stok. Aktif:
// Confirm = diajukan (SUBMITTED), stok pindah saat level terakhir Approve; Reject = berhenti (Duplicate untuk mengulang).
final class StockTransfer
{
    use FlowDocument;

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'REJECTED' => 'danger', 'CONFIRMED' => 'success', 'CANCELLED' => 'danger'];

    public static function find(int $id): ?InvTransfer
    {
        return InvTransfer::query()->with(['lines', 'fromWarehouse', 'toWarehouse'])->find($id);
    }

    public static function rows(): array
    {
        $docs = InvTransfer::query()->with(['lines', 'fromWarehouse', 'toWarehouse'])->orderByDesc('id_transfer')->limit(500)->get();
        $bins = WhLoc::query()->whereKey($docs->flatMap(fn (InvTransfer $t) => $t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id')))->filter()->unique()->all())
            ->pluck('location_code', 'location_id');

        return $docs->map(fn (InvTransfer $t) => [
            'id' => $t->id_transfer, 'no' => $t->transfer_no ?: 'DRAFT-'.$t->id_transfer,
            'route' => ($t->fromWarehouse?->warehouse_code ?? '?').' → '.($t->toWarehouse?->warehouse_code ?? '?'),
            'date' => $t->transfer_date?->toDateString(), 'from' => $t->fromWarehouse?->warehouse_name, 'to' => $t->toWarehouse?->warehouse_name,
            'reason' => t(InvTransfer::REASONS[$t->reason] ?? '—'), 'carried_by' => $t->carried_by, 'status' => $t->status(),
            'dead' => $t->lines->contains('was_dead_stock', true) ? 'Dead stock' : null, 'tone' => $t->lines->contains('was_dead_stock', true) ? 'danger' : null,
            'items' => $t->lines->map(fn (InvTransferLine $l) => ['code' => $l->item_code, 'name' => $l->item_name, 'qty' => $l->qty, 'unit' => $l->uom_code,
                'from_bin' => $bins[$l->from_location_id] ?? null, 'to_bin' => $bins[$l->to_location_id] ?? null, 'class' => $l->stock_class])->values()->all(),
        ])->all();
    }

    public static function create(int $fromWarehouseId, int $toWarehouseId, ?string $reason = null): InvTransfer
    {
        $login = Auth::user()->login;
        WhWarehouse::query()->findOrFail($fromWarehouseId);
        WhWarehouse::query()->findOrFail($toWarehouseId);

        return InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $fromWarehouseId, 'to_warehouse_id' => $toWarehouseId,
            'reason' => isset(InvTransfer::REASONS[$reason ?? '']) ? $reason : null, 'created_by' => $login, 'updated_by' => $login]);
    }

    // Dari peta: draft berisi SEMUA isi satu bin (qty penuh); bin tujuan dipilih di form.
    public static function createFromBin(WhLoc $bin): InvTransfer
    {
        $login = Auth::user()->login;

        return DB::connection('tm2inv')->transaction(function () use ($bin, $login) {
            $t = self::create((int) $bin->warehouse_id, (int) $bin->warehouse_id);
            foreach (WarehouseStock::positions([$bin->location_id])->values() as $i => $p) {
                InvTransferLine::create(self::lineData($p) + ['transfer_id' => $t->id_transfer, 'line_no' => $i + 1, 'qty' => $p['qty'], 'created_by' => $login, 'updated_by' => $login]);
            }

            return $t;
        });
    }

    // Dari label palet (lacak): draft berisi satu palet utuh; bin tujuan dipilih di form.
    public static function createFromPallet(InvPallet $pallet): InvTransfer
    {
        $login = Auth::user()->login;
        $bin = WhLoc::query()->findOrFail($pallet->location_id);

        return DB::connection('tm2inv')->transaction(function () use ($pallet, $bin, $login) {
            $t = self::create((int) $bin->warehouse_id, (int) $bin->warehouse_id);
            InvTransferLine::create(['transfer_id' => $t->id_transfer, 'line_no' => 1, 'stock_class' => $pallet->stock_class, 'item_id' => $pallet->item_id, 'part_id' => $pallet->part_id,
                'item_code' => $pallet->item_code, 'item_name' => $pallet->item_name, 'from_location_id' => $pallet->location_id, 'uom_code' => $pallet->uom_code,
                'lot_no' => $pallet->lot_no, 'qty' => $pallet->qty, 'pallet_id' => $pallet->id_pallet, 'created_by' => $login, 'updated_by' => $login]);

            return $t;
        });
    }

    // Duplicate (dokumen Rejected / Cancelled): Draft baru berisi salinan baris, tanpa nomor.
    public static function duplicate(InvTransfer $t): InvTransfer
    {
        $login = Auth::user()->login;

        return DB::connection('tm2inv')->transaction(function () use ($t, $login) {
            $copy = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $t->from_warehouse_id, 'to_warehouse_id' => $t->to_warehouse_id,
                'reason' => $t->reason, 'carried_by' => $t->carried_by, 'notes' => $t->notes, 'created_by' => $login, 'updated_by' => $login]);
            foreach ($t->lines as $l) {
                InvTransferLine::create(['transfer_id' => $copy->id_transfer, 'created_by' => $login, 'updated_by' => $login]
                    + $l->only(['line_no', 'stock_class', 'item_id', 'part_id', 'item_code', 'item_name', 'from_location_id', 'to_location_id', 'lot_no', 'qty', 'uom_code', 'pallet_id']));
            }

            return $copy;
        });
    }

    private static function lineData(array $p): array
    {
        return ['stock_class' => $p['class'], 'item_id' => $p['item_id'], 'part_id' => $p['part_id'], 'item_code' => mb_substr((string) $p['code'], 0, 60),
            'item_name' => mb_substr((string) $p['name'], 0, 255), 'from_location_id' => $p['location_id'], 'uom_code' => $p['uom'] ?: null];
    }

    // Posisi stok gudang asal (saldo > 0), kunci = WarehouseStock::key.
    public static function positions(InvTransfer $t): Collection
    {
        return WarehouseStock::positions(WhLoc::query()->where('warehouse_id', $t->from_warehouse_id)->pluck('location_id')->all())->keyBy('key');
    }

    /** @return array<string, string> kunci posisi => "PYJ31 · IVORY 300 79 X 109 | A5-PPR-L01-01 | 6,000 LBR" (+ DEAD STOCK) */
    public static function positionOptions(InvTransfer $t, ?Collection $positions = null): array
    {
        $positions ??= self::positions($t);
        $opts = $positions->sortBy(fn (array $p) => $p['location_code'].' '.$p['code'])
            ->map(fn (array $p) => $p['code'].' · '.$p['name'].' | '.$p['location_code'].' | '.Locale::decimal($p['qty'], 2).' '.$p['uom'].($p['dead'] ? ' | '.t('DEAD STOCK') : ''))->all();
        // Baris yang posisinya sudah habis (dokumen Confirmed / dibatalkan) tetap terbaca.
        $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->unique()->all())->pluck('location_code', 'location_id');
        foreach ($t->lines as $l) {
            $opts[$l->positionKey()] ??= $l->item_code.' · '.$l->item_name.' | '.($bins[$l->from_location_id] ?? '?');
        }

        return $opts;
    }

    // Baris x-dl::lines.
    public static function lineRows(InvTransfer $t, ?Collection $positions = null): array
    {
        $positions ??= self::positions($t);

        $pallets = InvPallet::query()->whereKey($t->lines->pluck('pallet_id')->filter()->all())->pluck('pallet_no', 'id_pallet');

        return $t->lines->map(function (InvTransferLine $l) use ($positions, $pallets) {
            $p = $positions->get($l->positionKey());

            return ['id_transfer_line' => $l->id_transfer_line, 'position' => $l->positionKey(), 'to_location_id' => $l->to_location_id, 'qty' => $l->qty,
                'unit' => $l->uom_code, 'onhand' => $p ? Locale::decimal($p['qty'], 2) : '—', 'lot_no' => $l->lot_no, 'pallet' => $pallets[$l->pallet_id] ?? '—'];
        })->values()->all();
    }

    // Header + baris (tambah / ubah / hapus). Baris baru tanpa posisi dilewati.
    public static function save(InvTransfer $t, array $in): void
    {
        abort_unless($t->status() === 'DRAFT', 422, 'This transfer is already Confirmed and cannot be changed.');
        $login = Auth::user()->login;
        $text = fn (string $k, int $max) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $max) ?: null;
        $to = (int) ($in['to_warehouse_id'] ?? $t->to_warehouse_id);
        $t->fill([
            'transfer_date' => trim((string) ($in['transfer_date'] ?? '')) ?: $t->transfer_date,
            'to_warehouse_id' => WhWarehouse::query()->whereKey($to)->exists() ? $to : $t->to_warehouse_id,
            'reason' => isset(InvTransfer::REASONS[$in['reason'] ?? '']) ? $in['reason'] : null,
            'carried_by' => $text('carried_by', 80), 'notes' => $text('notes', 255), 'updated_by' => $login,
            // Dua langkah (Ship lalu Receive lewat In Transit) atau langsung (bawaan).
            'transit_mode' => ($in['transit_mode'] ?? '') === StockTransferService::TWO_STEP ? StockTransferService::TWO_STEP : 'DIRECT',
        ]);
        $positions = self::positions($t);
        $toBins = WhLoc::query()->where('warehouse_id', $t->to_warehouse_id)->pluck('location_id')->flip();

        DB::connection('tm2inv')->transaction(function () use ($t, $in, $login, $positions, $toBins) {
            $t->save();
            $lines = $t->lines()->get()->keyBy('id_transfer_line');
            foreach (array_keys((array) ($in['remove'] ?? [])) as $id) {
                $lines->get((int) $id)?->delete();
            }
            $next = (int) $lines->max('line_no');
            foreach ((array) ($in['lines'] ?? []) as $id => $row) {
                $row = (array) $row;
                if (isset($in['remove'][$id])) {
                    continue;
                }
                $line = is_numeric($id) ? $lines->get((int) $id) : null;
                $p = $positions->get((string) ($row['position'] ?? ''));
                if (! $line) {
                    if (! $p) {
                        continue;
                    }
                    $line = new InvTransferLine(['transfer_id' => $t->id_transfer, 'line_no' => ++$next, 'created_by' => $login]);
                }
                if ($p) {
                    $line->fill(self::lineData($p));
                }
                $dest = (int) ($row['to_location_id'] ?? 0);
                $line->fill(['to_location_id' => $toBins->has($dest) ? $dest : null, 'qty' => max(0, (float) (Locale::input($row['qty'] ?? 0) ?? 0)),
                    'lot_no' => mb_substr(trim((string) ($row['lot_no'] ?? '')), 0, 100) ?: null, 'updated_by' => $login]);
                // Baris yang lahir dari satu palet tetap menunjuk palet itu selama posisi dan qty-nya tidak diubah.
                if ($line->pallet_id && ($line->isDirty('qty') || $line->isDirty('from_location_id') || $line->isDirty('item_id'))) {
                    $line->pallet_id = null;
                }
                $line->save();
            }
        });
    }

    // Delete = hapus permanen, hanya Draft (rulebook C.6); baris ikut terhapus (FK cascade).
    public static function delete(InvTransfer $t): void
    {
        abort_unless($t->status() === 'DRAFT', 422, 'Only a Draft can be deleted.');
        $t->delete();
    }

    // Pemeriksaan per baris untuk tabel "Bins & capacity": label bin asal / tujuan, kapasitas tujuan sesudah pindah, tanda.
    /** @return list<array<string, mixed>> */
    public static function checks(InvTransfer $t, ?Collection $positions = null): array
    {
        $positions ??= self::positions($t);
        $bins = WhLoc::query()->with('zone')->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->filter()->unique()->all())->get()->keyBy('location_id');
        $sum = WarehouseStock::binSummaries($bins->values());
        // "Belum pindah" (Draft, menunggu approval, ditolak): tujuan dihitung dengan baris ini; sesudah Confirm: isi sekarang.
        $draft = ! $t->stat_confirm;
        $materials = MatRaw::withoutGlobalScopes()->whereKey($t->lines->where('stock_class', 'RM')->pluck('item_id')->unique()->all())
            ->get(['material_id', 'category1', 'gram_weight', 'cm_length', 'cm_width', 'mm_thickness'])->keyBy('material_id');
        $incoming = [];
        $incomingM3 = [];
        $out = [];
        foreach ($t->lines as $l) {
            $p = $positions->get($l->positionKey());
            $to = $bins->get($l->to_location_id);
            // Perkiraan berat baris (null = tidak diketahui); dihitung dari master supaya tetap ada sesudah posisi asal habis.
            $material = $l->stock_class === 'RM' ? $materials->get($l->item_id) : null;
            $kg = WarehouseStock::weightKg($l->qty, (string) $l->uom_code, $material, (string) $l->item_name);
            $m3 = WarehouseStock::volumeM3($l->qty, (string) $l->uom_code, $material, (string) $l->item_name);
            $after = null;
            $afterM3 = null;
            $over = false;
            if ($to) {
                $s = $sum[$to->location_id];
                // Draft: isi tujuan sekarang + baris-baris sebelumnya ke bin yang sama + baris ini. Sesudah Confirm: isi sekarang.
                $incoming[$to->location_id] = ($incoming[$to->location_id] ?? 0) + ($draft ? (float) $kg : 0);
                $incomingM3[$to->location_id] = ($incomingM3[$to->location_id] ?? 0) + ($draft ? (float) $m3 : 0);
                $used = $s['used_kg'] + $incoming[$to->location_id];
                $usedM3 = $s['used_m3'] + $incomingM3[$to->location_id];
                $after = Warehouse::capacityText($used, $s['capacity_kg']);
                $afterM3 = Warehouse::volumeText($usedM3, $s['capacity_m3']);
                $over = ($s['capacity_kg'] !== null && $used > $s['capacity_kg']) || ($s['capacity_m3'] !== null && $usedM3 > $s['capacity_m3']);
            }
            $out[] = ['line' => $l->line_no, 'item' => $l->item_name, 'code' => $l->item_code, 'qty' => $l->qty, 'unit' => $l->uom_code,
                'weight' => $kg, 'volume' => $m3, 'from' => $bins->get($l->from_location_id)?->location_code, 'to' => $to?->location_code, 'after' => $after, 'after_m3' => $afterM3,
                'flag' => match (true) {
                    ($draft ? ($p['dead'] ?? false) : $l->was_dead_stock) => 'Dead stock',
                    $over => 'Over capacity',
                    ! $to => 'No destination',
                    default => 'OK',
                }, 'over' => $over, 'dead' => $draft ? ($p['dead'] ?? false) : $l->was_dead_stock];
        }

        return $out;
    }

    // Bin yang terlibat (asal + tujuan), untuk kartu label di form: kode, area, kapasitas, QR lacak, pita merah bila dead stock.
    /** @return list<array<string, mixed>> */
    public static function binLabels(InvTransfer $t): array
    {
        $ids = $t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->filter()->unique()->all();
        $bins = WhLoc::query()->with('zone')->whereKey($ids)->orderBy('location_code')->get();
        $sum = WarehouseStock::binSummaries($bins);
        $to = $t->lines->pluck('to_location_id')->filter()->flip();

        return $bins->map(fn (WhLoc $b) => Labels::location($b, $sum[$b->location_id]) + ['role' => $to->has($b->location_id) ? 'Destination' : 'Source'])->all();
    }

    // confirm | cancel -> pesan salah (sudah diterjemahkan), null = berhasil.
    public static function act(InvTransfer $t, string $action, string $reason = ''): ?string
    {
        $svc = new StockTransferService;

        if ($action === 'confirm') {
            // Dua langkah: Confirm = Ship (stok ke bin In Transit); diterima di Warehouse > In Transit.
            return $t->transit_mode === StockTransferService::TWO_STEP ? $svc->ship($t, Auth::user()->login) : $svc->confirm($t, Auth::user()->login);
        }

        return $svc->cancel($t, $reason, Auth::user()->login);
    }

    // ---------------------------------------------------------------- approval (modul flow TF)

    public static function approvalActive(): bool
    {
        return (bool) app(FlowEngineService::class)->activeConfig(StockTransferService::FLOW);
    }

    // Keadaan approval satu dokumen untuk form: level, level yang menunggu, apakah user ini pemeriksanya, log.
    // 'at' = level yang sedang menunggu; sesudah selesai = jumlah persetujuan + 1 (untuk bar tahapan form).
    /** @return array{used: bool, levels: int, at: int, held: bool, pending: ?object, log: array} */
    public static function approval(InvTransfer $t): array
    {
        $used = $t->flow_status !== null;
        $state = $used ? self::approvalState(StockTransferService::FLOW, (int) $t->id_transfer, 0) : ['levels' => 0, 'at' => 0, 'held' => false];

        $log = $used ? self::flowLog(StockTransferService::FLOW, (int) $t->id_transfer) : [];

        return ['used' => $used, 'levels' => (int) $state['levels'], 'at' => (int) $state['at'] ?: collect($log)->where('action', 'Approved')->count() + 1, 'held' => (bool) $state['held'],
            'pending' => $t->status() === 'SUBMITTED' ? self::pendingFor(StockTransferService::FLOW, (int) $t->id_transfer) : null, 'log' => $log];
    }

    // Keputusan pemeriksa level berjalan: approve | reject | hold. Kembalian = pesan salah ('' = berhasil).
    // Approve terakhir = stok pindah; stok diperiksa DULU supaya persetujuan tidak tercatat untuk dokumen yang tidak bisa dipindah.
    public static function decide(InvTransfer $t, string $action, string $note): string
    {
        $pending = $t->status() === 'SUBMITTED' ? self::pendingFor(StockTransferService::FLOW, (int) $t->id_transfer) : null;
        if (! $pending) {
            return t('This document is not waiting for your approval.');
        }
        $flow = app(FlowEngineService::class);
        $login = Auth::user()->login;
        $amount = (float) $pending->amount_snapshot;
        $svc = new StockTransferService;
        if ($action === 'hold') {
            $flow->hold(StockTransferService::FLOW, (int) $t->id_transfer, (int) $pending->flow_step_id, $note, $amount);

            return '';
        }
        if ($action === 'reject') {
            $flow->reject(StockTransferService::FLOW, (int) $t->id_transfer, (int) $pending->flow_step_id, $note, $amount);
            $t->forceFill(['flow_status' => 'REJECTED', 'updated_by' => $login])->save();

            return '';
        }
        if ($errors = $svc->confirmErrors($t)) {
            return implode(' ', $errors);
        }
        $res = $flow->approve(StockTransferService::FLOW, (int) $t->id_transfer, (int) $pending->flow_step_id, $amount);

        return $res['final'] ? (string) $svc->post($t, $login, true) : '';
    }

    public static function formStatus(InvTransfer $t, bool $held = false): string
    {
        return match ($t->status()) {
            'SUBMITTED' => $held ? 'hold' : 'approval', 'REJECTED' => 'rejected',
            'CONFIRMED' => $t->flow_status === 'APPROVED' ? 'approved' : 'confirmed', 'CANCELLED' => 'cancelled', default => 'draft',
        };
    }
}
