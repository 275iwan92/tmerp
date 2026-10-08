<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Inventory\WhTask;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Models\Prd\PrdWo;
use App\Models\Pur\PvLine;
use App\Models\Pur\PvReceipt;
use App\Support\V2\Data\Labels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Tugas gudang v2.01 (tabel wh_task). Saat ini satu jenis yang dibangun: PUTAWAY (Receiving & Putaway, docs/mockups/wh_v201_receiving_putaway.jpg).
//   sync()    membuat tugas putaway untuk palet yang masih di area RECEIVING / QC_HOLD, dan membatalkan tugas yang palet-nya sudah pindah
//   state()   status tugas menurut mutu batch: WAITING (Quarantine) / READY / TO_LINE (Conditional: dock-to-line) / BLOCKED (Rejected, Blocked)
//   confirm() petugas memindai palet lalu bin; stok pindah lewat dokumen Stock Transfer asli (StockTransferService::post), bukan mengubah saldo.
// Bin berbeda dari usulan (PutawayPlanner) wajib alasan. Putaway adalah pindah bin internal: approval Stock Transfer (modul TF) tidak dipakai.
final class WarehouseTaskService
{
    public const WAITING = 'WAITING';

    public const READY = 'READY';

    public const TO_LINE = 'TO_LINE';

    public const BLOCKED = 'BLOCKED';

    private const RECEIVING_ZONES = ['RECEIVING', 'QC_HOLD'];

    // Buat tugas putaway yang belum ada, batalkan yang palet-nya sudah tidak di area terima. Idempoten.
    /** @return array{created: int, cancelled: int} */
    public function sync(string $login): array
    {
        $zones = WhZone::query()->whereIn('zone_type', self::RECEIVING_ZONES)->pluck('id_zone')->all();
        $bins = WhLoc::query()->whereIn('zone_id', $zones ?: [0])->pluck('location_id')->all();
        $pallets = InvPallet::query()->where('status', 'ACTIVE')->where('qty', '>', 0)->whereIn('location_id', $bins ?: [0])->orderBy('arrived_at')->orderBy('id_pallet')->get();
        $open = WhTask::query()->where('task_type', 'PUTAWAY')->where('status', 'OPEN')->get()->keyBy('pallet_id');
        $urgency = $this->urgency($pallets->whereNotIn('id_pallet', $open->keys()->all()));
        $created = 0;
        DB::connection('tm2inv')->transaction(function () use ($pallets, $open, $urgency, $login, &$created) {
            foreach ($pallets->whereNotIn('id_pallet', $open->keys()->all()) as $p) {
                $u = $urgency[$p->id_pallet] ?? ['urgent' => false, 'wo_id' => null];
                WhTask::create(['task_no' => WhTask::nextNo(), 'task_type' => 'PUTAWAY', 'status' => 'OPEN', 'stock_class' => $p->stock_class, 'item_id' => $p->item_id, 'part_id' => $p->part_id,
                    'item_code' => $p->item_code, 'item_name' => $p->item_name, 'pallet_id' => $p->id_pallet, 'batch_id' => $p->batch_id, 'qty' => $p->qty, 'uom_code' => $p->uom_code,
                    'from_location_id' => $p->location_id, 'ref_doc_no' => $p->ref_doc_no, 'is_urgent' => $u['urgent'], 'wo_id' => $u['wo_id'], 'created_by' => $login, 'updated_by' => $login]);
                $created++;
            }
        });
        $stale = $open->filter(fn (WhTask $t) => ! $pallets->contains('id_pallet', $t->pallet_id));
        foreach ($stale as $t) {
            $t->forceFill(['status' => 'CANCELLED', 'cancel_reason' => 'Pallet is no longer in a receiving area.', 'updated_by' => $login])->save();
        }

        return ['created' => $created, 'cancelled' => $stale->count()];
    }

    // Status tugas terbuka menurut mutu batch. Palet tanpa batch (data lama) dianggap Released.
    public static function state(WhTask $task, ?InvBatch $batch): string
    {
        if ($task->status === 'DONE') {
            return 'DONE';
        }
        if ($task->status === 'CANCELLED') {
            return 'CANCELLED';
        }

        return match ($batch?->qc_status) {
            'QUARANTINE' => self::WAITING,
            'CONDITIONAL' => self::TO_LINE,
            'REJECTED', 'BLOCKED' => self::BLOCKED,
            default => self::READY,
        };
    }

    // Bin usulan tugas ini: putaway = urutan PutawayPlanner, dock-to-line = bin WIP / Staging. Kosong = belum ada tempat yang cocok.
    /** @return list<array<string, mixed>> */
    public function suggestions(WhTask $task, ?InvPallet $pallet = null, ?InvBatch $batch = null, ?PutawayPlanner $planner = null, int $limit = 3): array
    {
        $pallet ??= InvPallet::query()->find($task->pallet_id);
        if (! $pallet || ! $pallet->isActive()) {
            return [];
        }
        $batch ??= $task->batch_id ? InvBatch::query()->find($task->batch_id) : null;
        $planner ??= new PutawayPlanner;

        return match (self::state($task, $batch)) {
            self::READY => $planner->suggest($pallet, $batch, $limit),
            self::TO_LINE => array_values(array_filter([$planner->toLine($pallet, $task->wo_id ? PrdWo::query()->whereKey($task->wo_id)->value('wo_no') : null)])),
            default => [],
        };
    }

    // Konfirmasi: pindai palet, lalu bin. Mengembalikan tugas yang sudah DONE; melempar WarehouseTaskRefused bila ditolak (tidak ada yang berubah).
    public function confirm(WhTask $task, string $scanPallet, string $scanBin, ?string $reason, string $login): WhTask
    {
        return DB::connection('tm2inv')->transaction(function () use ($task, $scanPallet, $scanBin, $reason, $login) {
            $t = WhTask::query()->lockForUpdate()->findOrFail($task->id_task);
            if (! $t->isOpen()) {
                throw new WarehouseTaskRefused(t('This task is already closed.'));
            }
            $pallet = InvPallet::query()->lockForUpdate()->find($t->pallet_id);
            if (! $pallet || ! $pallet->isActive() || (int) $pallet->location_id !== (int) $t->from_location_id) {
                throw new WarehouseTaskRefused(t('The pallet is no longer where this task expects it.'));
            }
            $batch = $t->batch_id ? InvBatch::query()->find($t->batch_id) : null;
            $state = self::state($t, $batch);
            if (! in_array($state, [self::READY, self::TO_LINE], true)) {
                throw new WarehouseTaskRefused($state === self::WAITING ? t('The batch is still waiting for the quality decision.') : t('The batch is not available (Rejected or Blocked).'));
            }
            $this->checkScan($pallet, $scanPallet);
            $bin = $this->scannedBin($scanBin);
            $zone = WhZone::query()->find($bin->zone_id);
            $this->checkDestination($pallet, $bin, $zone, $state);

            $top = $this->suggestions($t, $pallet, $batch, null, 1)[0] ?? null;
            $reason = trim((string) $reason);
            if ((! $top || (int) $top['bin']->location_id !== (int) $bin->location_id) && $reason === '') {
                throw new WarehouseTaskRefused(t('This is not the suggested bin: fill in the reason.'));
            }
            $transfer = $this->move($t, $pallet, $bin, $login);
            $t->forceFill(['status' => 'DONE', 'to_location_id' => $bin->location_id, 'scan_pallet' => mb_substr(trim($scanPallet), 0, 60), 'scan_bin' => mb_substr(trim($scanBin), 0, 60),
                'reason' => $reason === '' ? null : mb_substr($reason, 0, 255), 'transfer_id' => $transfer->id_transfer, 'confirmed_by' => $login, 'confirmed_at' => now(), 'updated_by' => $login])->save();

            return $t;
        });
    }

    // Palet yang dipindai harus palet tugas ini (kode label: nomor palet, "PLT:nomor", atau alamat QR).
    private function checkScan(InvPallet $pallet, string $scan): void
    {
        $q = Labels::parse($scan);
        if (! $q || ! in_array($q['kind'], ['PLT', 'ANY'], true) || strcasecmp($q['code'], (string) $pallet->pallet_no) !== 0) {
            throw new WarehouseTaskRefused(tf('Scan pallet :no first. The scanned code does not match.', ['no' => $pallet->pallet_no]));
        }
    }

    private function scannedBin(string $scan): WhLoc
    {
        $q = Labels::parse($scan);
        $bin = $q && in_array($q['kind'], ['LOC', 'ANY'], true) ? WhLoc::query()->where('location_code', $q['code'])->first() : null;

        return $bin ?? throw new WarehouseTaskRefused(t('The scanned code is not a bin.'));
    }

    // Larangan keras pada bin tujuan, dengan alasan atau tanpa: bin tidak bisa dipakai, area virtual / tidak cocok, aturan Mixing.
    private function checkDestination(InvPallet $pallet, WhLoc $bin, ?WhZone $zone, string $state): void
    {
        $code = $bin->location_code;
        if (! $bin->activate || ! $bin->is_usable) {
            throw new WarehouseTaskRefused(tf('The bin :bin is blocked.', ['bin' => $code]));
        }
        if ($bin->count_lock) {
            throw new WarehouseTaskRefused(tf('The bin :bin is locked for counting.', ['bin' => $code]));
        }
        if ((int) $bin->location_id === (int) $pallet->location_id) {
            throw new WarehouseTaskRefused(t('The destination is the bin the pallet is already in.'));
        }
        $types = $state === self::TO_LINE ? ['WIP', 'STAGING'] : ['STORAGE'];
        if (! $zone || $zone->isVirtualType() || ! in_array($zone->zone_type, $types, true)) {
            throw new WarehouseTaskRefused(tf('The bin :bin is not in an area where this pallet can go.', ['bin' => $code]));
        }
        if (! in_array($zone->stock_class, ['ANY', $pallet->stock_class], true)) {
            throw new WarehouseTaskRefused(tf('The area of bin :bin does not take this stock class.', ['bin' => $code]));
        }
        if ($msg = BinRules::mixViolation($bin, $zone, $pallet->stock_class, (int) $pallet->item_id, $pallet->part_id ? (int) $pallet->part_id : null,
            $pallet->batch_id ? (int) $pallet->batch_id : null, (int) $pallet->id_pallet)) {
            throw new WarehouseTaskRefused($msg);
        }
    }

    // Dokumen Stock Transfer asli: satu baris, palet ikut pindah (PalletService::followTransfer).
    private function move(WhTask $t, InvPallet $pallet, WhLoc $to, string $login): InvTransfer
    {
        $from = WhLoc::query()->findOrFail($pallet->location_id);
        $doc = InvTransfer::create(['transfer_date' => now()->toDateString(), 'from_warehouse_id' => $from->warehouse_id, 'to_warehouse_id' => $to->warehouse_id, 'reason' => 'OTHER',
            'notes' => mb_substr('Putaway '.$t->task_no.' / '.$pallet->pallet_no, 0, 255), 'created_by' => $login, 'updated_by' => $login]);
        InvTransferLine::create(['transfer_id' => $doc->id_transfer, 'line_no' => 1, 'stock_class' => $pallet->stock_class, 'item_id' => $pallet->item_id, 'part_id' => $pallet->part_id,
            'item_code' => $pallet->item_code, 'item_name' => $pallet->item_name, 'from_location_id' => $pallet->location_id, 'to_location_id' => $to->location_id, 'lot_no' => $pallet->lot_no,
            'qty' => $pallet->qty, 'uom_code' => $pallet->uom_code, 'pallet_id' => $pallet->id_pallet, 'batch_id' => $pallet->batch_id, 'created_by' => $login, 'updated_by' => $login]);
        if ($error = (new StockTransferService)->post($doc, $login)) {
            throw new WarehouseTaskRefused($error);
        }

        return $doc->refresh();
    }

    // Tanda URGENT dan WO penunggu dari baris penerimaan (pv_line) asal palet. Hanya RM yang punya penerimaan.
    /** @param  Collection<int, InvPallet>  $pallets
     *  @return array<int, array{urgent: bool, wo_id: ?int}> kunci = id palet */
    private function urgency(Collection $pallets): array
    {
        $refs = $pallets->where('stock_class', 'RM')->pluck('ref_doc_no')->filter()->unique()->all();
        if (! $refs) {
            return [];
        }
        $receipts = PvReceipt::query()->whereIn('doc_no', $refs)->pluck('pv_id', 'doc_no');
        $lines = PvLine::query()->whereIn('pv_id', $receipts->values()->all() ?: [0])->where('is_urgent', true)->get(['pv_id', 'item_id', 'urgent_wo_id'])
            ->groupBy(fn (PvLine $l) => $l->pv_id.':'.$l->item_id);
        $out = [];
        foreach ($pallets->where('stock_class', 'RM') as $p) {
            $line = $lines->get(($receipts[$p->ref_doc_no] ?? 0).':'.$p->item_id)?->first();
            if ($line) {
                $out[$p->id_pallet] = ['urgent' => true, 'wo_id' => $line->urgent_wo_id ? (int) $line->urgent_wo_id : null];
            }
        }

        return $out;
    }
}
