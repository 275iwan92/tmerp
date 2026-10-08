<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Inventory\WhTask;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Hitung stok (opname) v2.01: tugas COUNT di wh_task, satu per posisi (item di satu bin) pada area yang jatuh tempo (wh_zone.count_every_days).
//   dueZones()  area yang belum pernah dihitung atau terakhir dihitung lebih dari count_every_days lalu (dan tidak punya tugas hitung terbuka)
//   start()     membuat tugas COUNT semua posisi ber-stok di area itu dan MENGUNCI bin-nya (wh_loc.count_lock): transfer ke / dari bin terkunci
//               ditolak (BinRules::countLockViolations), putaway dan alokasi pick melewati bin terkunci
//   confirm()   petugas mengisi qty hasil hitung; selisih terhadap saldo kartu stok sekarang menjadi dokumen Stock Opname (doc_cat OP) yang
//               diposting StockPostingService seperti Adjustment lain; bin dibuka lagi bila tidak ada lagi tugas hitung terbuka di bin itu
//   cancel()    tugas dibatalkan tanpa penyesuaian (bin dibuka lagi bila selesai)
// Penyesuaian memakai jalur dokumen yang ada; periode tertutup menolak seperti biasa. Perintah terjadwal: php artisan inv:count-due.
final class CountTaskService
{
    // kelas => [model header, kolom item di baris dokumen]
    private const DOCS = ['RM' => [RmStock::class, 'material_id'], 'SFG' => [SfgStock::class, 'parts_id'], 'FG' => [FgStock::class, 'product_id']];

    /** @return Collection<int, WhZone> */
    public function dueZones(): Collection
    {
        $zones = WhZone::query()->where('activate', true)->where('is_virtual', false)->where('count_every_days', '>', 0)->orderBy('id_zone')->get();
        $open = WhTask::query()->where('task_type', 'COUNT')->where('status', 'OPEN')->pluck('count_zone_id')->flip();
        $last = WhTask::query()->where('task_type', 'COUNT')->where('status', '<>', 'CANCELLED')->groupBy('count_zone_id')->selectRaw('count_zone_id, MAX(created_at) AS at')->pluck('at', 'count_zone_id');

        return $zones->filter(fn (WhZone $z) => ! $open->has($z->id_zone)
            && (! isset($last[$z->id_zone]) || now()->greaterThanOrEqualTo(Carbon::parse($last[$z->id_zone])->addDays((int) $z->count_every_days))))->values();
    }

    // Buat tugas hitung semua area yang jatuh tempo. Kembalian = jumlah tugas baru.
    public function startDue(string $login): int
    {
        return $this->dueZones()->sum(fn (WhZone $z) => $this->start($z, $login));
    }

    // Tugas COUNT untuk setiap posisi ber-stok di area itu + kunci bin. Area yang sedang dihitung tidak dimulai lagi. Kembalian = jumlah tugas.
    public function start(WhZone $zone, string $login): int
    {
        if (WhTask::query()->where('task_type', 'COUNT')->where('status', 'OPEN')->where('count_zone_id', $zone->id_zone)->exists()) {
            throw new WarehouseTaskRefused(tf('Area :zone is already being counted.', ['zone' => $zone->zone_code]));
        }
        $bins = WhLoc::query()->where('zone_id', $zone->id_zone)->where('activate', true)->pluck('location_id')->all();
        $positions = $bins ? WarehouseStock::positions($bins)->values() : collect();
        $made = DB::connection('tm2inv')->transaction(function () use ($positions, $zone, $login) {
            foreach ($positions as $p) {
                WhTask::create(['task_no' => WhTask::nextNo(), 'task_type' => 'COUNT', 'status' => 'OPEN', 'stock_class' => $p['class'], 'item_id' => $p['item_id'], 'part_id' => $p['part_id'],
                    'item_code' => $p['code'] ?? null, 'item_name' => $p['name'] ?? null, 'qty' => $p['qty'], 'system_qty' => $p['qty'], 'uom_code' => $p['uom'] ?: null,
                    'from_location_id' => $p['location_id'], 'count_zone_id' => $zone->id_zone, 'created_by' => $login, 'updated_by' => $login]);
            }

            return $positions->count();
        });
        $lock = $positions->pluck('location_id')->unique()->all();
        if ($lock) {
            WhLoc::query()->whereKey($lock)->update(['count_lock' => true, 'count_lock_by' => $login, 'count_lock_at' => now()]);
        }

        return $made;
    }

    // Hasil hitung satu posisi. Kembalian = tugas DONE (adjustment_doc_no terisi bila ada selisih). Melempar WarehouseTaskRefused bila ditolak.
    public function confirm(WhTask $task, float $counted, string $login): WhTask
    {
        if ($counted < 0) {
            throw new WarehouseTaskRefused(t('The counted quantity cannot be negative.'));
        }
        $done = DB::connection('tm2inv')->transaction(function () use ($task, $counted, $login) {
            $t = WhTask::query()->lockForUpdate()->findOrFail($task->id_task);
            if ($t->task_type !== 'COUNT' || ! $t->isOpen()) {
                throw new WarehouseTaskRefused(t('This task is already closed.'));
            }
            $now = (new StockTransferService)->balance($t->stock_class, (int) $t->item_id, $t->part_id ? (int) $t->part_id : null, (int) $t->from_location_id);
            $diff = round($counted - $now, 4);
            $doc = abs($diff) > 0.00005 ? $this->adjust($t, $now, $counted, $diff, $login) : null;
            $t->forceFill(['status' => 'DONE', 'counted_qty' => $counted, 'adjustment_doc_id' => $doc['id'] ?? null, 'adjustment_doc_no' => $doc['no'] ?? null,
                'confirmed_by' => $login, 'confirmed_at' => now(), 'updated_by' => $login])->save();

            return $t;
        });
        $this->unlockIfFinished((int) $done->from_location_id, $login);

        return $done;
    }

    public function cancel(WhTask $task, string $reason, string $login): WhTask
    {
        $t = WhTask::query()->findOrFail($task->id_task);
        if ($t->task_type !== 'COUNT' || ! $t->isOpen()) {
            throw new WarehouseTaskRefused(t('This task is already closed.'));
        }
        $t->forceFill(['status' => 'CANCELLED', 'cancel_reason' => mb_substr(trim($reason) ?: 'Cancelled', 0, 255), 'updated_by' => $login])->save();
        $this->unlockIfFinished((int) $t->from_location_id, $login);

        return $t;
    }

    // Dokumen Stock Opname untuk selisih satu posisi; ditolak (periode tertutup, dst.) = tugas tetap terbuka.
    /** @return array{id: int, no: string} */
    private function adjust(WhTask $t, float $system, float $counted, float $diff, string $login): array
    {
        [, $itemCol] = self::DOCS[$t->stock_class];
        $bin = WhLoc::query()->findOrFail($t->from_location_id);
        $docs = new StockDocumentService;
        $no = $docs->nextDocNo($t->stock_class, 'OPN');
        $line = [$itemCol => $t->item_id, 'qty' => $diff, 'qty_system' => $system, 'qty_physical' => $counted, 'uom_code' => $t->uom_code, 'location_id' => $t->from_location_id, 'action_type' => 'COUNT']
            + ($t->stock_class === 'FG' ? ['parts_id' => $t->part_id] : []);
        $id = $docs->saveDraft($t->stock_class, null, ['doc_no' => $no, 'doc_date' => now(), 'doc_type' => 0, 'doc_cat' => 'OP', 'ref_doc_type' => 'COUNT_TASK',
            'ref_doc_no' => $t->task_no, 'warehouse_id' => $bin->warehouse_id, 'opn_officer' => $login, 'notes' => mb_substr('Count '.$t->task_no.' '.$bin->location_code, 0, 255)], [$line], $login);
        $res = (new StockPostingService)->postDocument($t->stock_class, $id, $login);
        if (! $res['posted']) {
            $docs->cancel($t->stock_class, $id, $login);
            throw new WarehouseTaskRefused((string) $res['error']);
        }

        return ['id' => $id, 'no' => $no];
    }

    private function unlockIfFinished(int $binId, string $login): void
    {
        if (! WhTask::query()->where('task_type', 'COUNT')->where('status', 'OPEN')->where('from_location_id', $binId)->exists()) {
            WhLoc::query()->whereKey($binId)->update(['count_lock' => false, 'count_lock_by' => $login, 'count_lock_at' => now()]);
        }
    }
}
