<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvPallet;
use App\Models\Inventory\WhTask;
use App\Models\Master\WhLoc;
use App\Services\Inventory\PutawayPlanner;
use App\Services\Inventory\WarehouseTaskService;
use Devlib\Locale;
use Illuminate\Support\Collection;

// Data layar Warehouse > Receiving & Putaway (wh-receiving-putaway): daftar tugas putaway (grid), panel tugas terpilih dengan usulan bin,
// dan aturan usulan. Logika usulan dan konfirmasi ada di App\Services\Inventory\{WarehouseTaskService, PutawayPlanner}; kelas ini hanya
// menyusun baris tampilan (lintas-DB: palet / batch / tugas di tm2inv, bin di tm2mst, dicocokkan di PHP).
final class ReceivingPutaway
{
    // Label mutu batch di layar => tone badge DevLib.
    public const QUALITY_TONES = ['Waiting sampling' => 'warn', 'Released' => 'success', 'Conditional' => 'violet', 'Conditional · URGENT' => 'violet', 'Rejected' => 'danger', 'Blocked' => 'neutral'];

    public const ACTION_TONES = ['Waiting QC' => 'neutral', 'Putaway' => 'info', 'Send to line' => 'violet', 'Not available' => 'danger', 'Done' => 'success', 'Cancelled' => 'neutral'];

    // Aturan usulan bin (kotak 4 gambar), berurutan seperti PutawayPlanner.
    private const RULES = [
        'The batch must be allocatable: Quarantine, Rejected and Blocked batches get no bin.',
        'The area is Storage, active, not virtual, and takes the item class; the bin is usable and not locked for counting.',
        'Mixing rule of the area: one item per bin, mixed items, or one batch per bin.',
        'Capacity: weight (kg), pallet count and the stack limit (max stack) of the area.',
        'Order: same building first, area order, empty bins before used ones, then bin code. Pick order (FIFO / FEFO) is shown with the bin.',
        'The officer scans the pallet, then the bin; a different bin than suggested needs a reason.',
    ];

    public static function find(int $id): ?WhTask
    {
        return WhTask::query()->where('task_type', 'PUTAWAY')->find($id);
    }

    /** @return list<array{n: int, rule: string}> */
    public static function rules(): array
    {
        return array_map(fn (string $r, int $i) => ['n' => $i + 1, 'rule' => t($r)], self::RULES, array_keys(self::RULES));
    }

    // Satu baris per tugas: yang masih terbuka + yang selesai hari ini.
    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $tasks = WhTask::query()->where('task_type', 'PUTAWAY')->where(fn ($q) => $q->where('status', 'OPEN')
            ->orWhere(fn ($d) => $d->where('status', 'DONE')->where('confirmed_at', '>=', now()->startOfDay())))->orderBy('id_task')->get();
        $ctx = self::context($tasks);
        $service = new WarehouseTaskService;
        $planner = new PutawayPlanner;
        $rows = [];
        foreach ($tasks as $t) {
            $pallet = $ctx['pallets']->get($t->pallet_id);
            $batch = $ctx['batches']->get($t->batch_id);
            $state = WarehouseTaskService::state($t, $batch);
            $top = null;
            if ($t->status === 'OPEN' && $pallet) {
                $top = $service->suggestions($t, $pallet, $batch, $planner, 1)[0] ?? null;
                if ($top && $state === WarehouseTaskService::READY) {
                    $planner->reserve($top['bin'], $pallet);
                }
            }
            $dest = $t->status === 'DONE' ? $ctx['bins']->get($t->to_location_id) : ($top['bin'] ?? null);
            $rows[] = ['id' => (string) $t->id_task, 'task' => $t->task_no, 'pallet' => $pallet?->pallet_no ?? '—', 'receipt' => $t->ref_doc_no ?? '—', 'item' => $t->item_name, 'item_code' => $t->item_code,
                'batch' => $batch?->batch_no ?? '—', 'qty' => (float) $t->qty, 'unit' => $t->uom_code, 'arrived' => $pallet?->created_at?->toDateTimeString(),
                'quality' => self::quality($batch, $t), 'state' => $state, 'suggested' => $dest ? $dest->location_code : '—',
                'why' => $t->status === 'DONE' ? ($t->reason ?: t('as suggested')) : ($top['why'] ?? self::noBinText($state)), 'action' => self::actionLabel($state)];
        }

        return $rows;
    }

    // Data panel tugas terpilih: pallet, batch, usulan bin (maks. 5) dan keterangan.
    /** @return array{task: WhTask, pallet: ?InvPallet, batch: ?InvBatch, state: string, from: ?WhLoc, suggestions: list<array<string, mixed>>} */
    public static function panel(WhTask $t): array
    {
        $pallet = InvPallet::query()->find($t->pallet_id);
        $batch = $t->batch_id ? InvBatch::query()->find($t->batch_id) : null;
        $state = WarehouseTaskService::state($t, $batch);
        $found = $t->status === 'OPEN' ? (new WarehouseTaskService)->suggestions($t, $pallet, $batch, null, 5) : [];
        $suggestions = array_map(fn (array $s, int $i) => ['n' => $i + 1, 'bin' => $s['bin']->location_code, 'area' => $s['zone']->zone_code.' · '.$s['zone']->zone_name,
            'free' => array_key_exists('free_kg', $s) && $s['free_kg'] !== null ? Locale::decimal($s['free_kg'], 0) : '—', 'why' => $s['why']], $found, array_keys($found));

        return ['task' => $t, 'pallet' => $pallet, 'batch' => $batch, 'state' => $state, 'from' => WhLoc::query()->find($t->from_location_id), 'suggestions' => $suggestions];
    }

    public static function quality(?InvBatch $batch, WhTask $t): string
    {
        return match ($batch?->qc_status) {
            'QUARANTINE' => 'Waiting sampling',
            'CONDITIONAL' => $t->is_urgent ? 'Conditional · URGENT' : 'Conditional',
            'REJECTED' => 'Rejected',
            'BLOCKED' => 'Blocked',
            default => 'Released',
        };
    }

    private static function actionLabel(string $state): string
    {
        return match ($state) {
            WarehouseTaskService::WAITING => 'Waiting QC',
            WarehouseTaskService::READY => 'Putaway',
            WarehouseTaskService::TO_LINE => 'Send to line',
            WarehouseTaskService::BLOCKED => 'Not available',
            'CANCELLED' => 'Cancelled',
            default => 'Done',
        };
    }

    private static function noBinText(string $state): string
    {
        return match ($state) {
            WarehouseTaskService::WAITING => t('waiting for the quality decision'),
            WarehouseTaskService::BLOCKED => t('batch not available'),
            default => t('no suitable bin: choose one and give a reason'),
        };
    }

    /** @param  Collection<int, WhTask>  $tasks */
    private static function context(Collection $tasks): array
    {
        return ['pallets' => InvPallet::query()->whereKey($tasks->pluck('pallet_id')->filter()->unique()->all())->get()->keyBy('id_pallet'),
            'batches' => InvBatch::query()->whereKey($tasks->pluck('batch_id')->filter()->unique()->all())->get()->keyBy('id_batch'),
            'bins' => WhLoc::query()->whereKey($tasks->pluck('to_location_id')->filter()->unique()->all())->get()->keyBy('location_id')];
    }
}
