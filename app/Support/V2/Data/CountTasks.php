<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\WhTask;
use App\Models\Master\WhLoc;
use App\Models\Master\WhZone;
use App\Services\Inventory\CountTaskService;

// Data layar Warehouse > Count Tasks (wh-count-tasks): tugas hitung (COUNT) per posisi stok, terbuka + yang selesai 7 hari terakhir, dan pilihan
// area untuk memulai hitung. Logika: App\Services\Inventory\CountTaskService.
final class CountTasks
{
    public const TONES = ['Open' => 'warn', 'Done' => 'success', 'Cancelled' => 'neutral'];

    public static function find(int $id): ?WhTask
    {
        return WhTask::query()->where('task_type', 'COUNT')->find($id);
    }

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $tasks = WhTask::query()->where('task_type', 'COUNT')->where(fn ($q) => $q->where('status', 'OPEN')->orWhere('updated_at', '>=', now()->subDays(7)))->orderByDesc('id_task')->limit(500)->get();
        $bins = WhLoc::query()->whereKey($tasks->pluck('from_location_id')->unique()->all())->get()->keyBy('location_id');
        $zones = WhZone::query()->whereKey($tasks->pluck('count_zone_id')->filter()->unique()->all())->pluck('zone_code', 'id_zone');

        return $tasks->map(fn (WhTask $t) => ['id' => $t->id_task, 'task' => $t->task_no, 'zone' => $zones[$t->count_zone_id] ?? '—', 'bin' => $bins[$t->from_location_id]->location_code ?? '—',
            'item' => $t->item_name, 'item_code' => $t->item_code, 'system' => (float) $t->system_qty, 'counted' => $t->counted_qty === null ? null : (float) $t->counted_qty,
            'diff' => $t->counted_qty === null ? null : round((float) $t->counted_qty - (float) $t->system_qty, 4), 'unit' => $t->uom_code, 'adjustment' => $t->adjustment_doc_no ?: '—',
            'status' => WhTask::STATUSES[$t->status] ?? $t->status, 'by' => $t->confirmed_by])->all();
    }

    // Area yang bisa dihitung: non-virtual, aktif. Yang jatuh tempo diberi tanda.
    /** @return array<int, string> id area => label */
    public static function zoneOptions(): array
    {
        $due = (new CountTaskService)->dueZones()->pluck('id_zone')->flip();

        return WhZone::query()->where('activate', true)->where('is_virtual', false)->orderBy('warehouse_id')->orderBy('zone_code')->get()
            ->mapWithKeys(fn (WhZone $z) => [$z->id_zone => $z->zone_code.' · '.$z->zone_name.($z->count_every_days ? ' ('.t('every').' '.$z->count_every_days.' '.t('days').')' : '').($due->has($z->id_zone) ? ' — '.t('due') : '')])->all();
    }
}
