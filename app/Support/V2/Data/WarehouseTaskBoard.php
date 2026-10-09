<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\WhPickList;
use App\Models\Inventory\WhTask;
use App\Services\Inventory\CountTaskService;
use Devlib\Locale;
use Illuminate\Support\Carbon;

// Warehouse > Task Board (docs/mockups/menu_v201_split.jpg, kolom Task Board): papan BACA-SAJA yang menggabungkan pekerjaan gudang dari layar asalnya
// dalam tiga kolom: Open / In progress / Done. Sumber: wh_task (RECEIVE / PUTAWAY / PICK / MOVE / COUNT), pick list terbuka, transfer In Transit yang
// sedang berjalan, dan area yang hitung stoknya jatuh tempo. Tidak ada yang diubah dari sini; tiap kartu membuka layar asalnya (kolom Done = 7 hari terakhir).
final class WarehouseTaskBoard
{
    public const DONE_DAYS = 7;

    /** @return array{columns: list<array<string, mixed>>, cards: list<array<string, mixed>>} */
    public static function board(): array
    {
        $cards = array_merge(self::tasks(), self::pickLists(), self::transfers(), self::dueCounts());

        return ['columns' => [['key' => 'open', 'label' => t('Open'), 'tone' => 'warn'], ['key' => 'progress', 'label' => t('In progress'), 'tone' => 'info'],
            ['key' => 'done', 'label' => t('Done'), 'tone' => 'success', 'done' => true]], 'cards' => $cards];
    }

    private static function since(): Carbon
    {
        return now()->subDays(self::DONE_DAYS);
    }

    private static function screen(string $slug, string $query = ''): string
    {
        return route('v2.screen', $slug).$query;
    }

    /** @return list<array<string, mixed>> */
    private static function tasks(): array
    {
        $tasks = WhTask::query()->where(fn ($q) => $q->where('status', 'OPEN')->orWhere(fn ($d) => $d->where('status', 'DONE')->where('updated_at', '>=', self::since())))->orderByDesc('id_task')->limit(300)->get();

        return $tasks->map(fn (WhTask $t) => ['id' => 'task:'.$t->id_task, 'col' => $t->status === 'OPEN' ? 'open' : 'done', 'title' => ($t->task_no).' · '.($t->item_name ?: '—'),
            'desc' => Locale::decimal((float) ($t->task_type === 'COUNT' ? $t->system_qty : $t->qty), 2).' '.$t->uom_code.($t->ref_doc_no ? ' · '.$t->ref_doc_no : ''),
            'tags' => [['label' => t(WhTask::TYPES[$t->task_type] ?? $t->task_type), 'tone' => 'accent']], 'priority' => $t->is_urgent ? 'High' : null,
            'url' => match ($t->task_type) {
                'COUNT' => self::screen('wh-count-tasks', '?id='.$t->id_task),
                'PICK' => self::screen('wh-pick-list'),
                default => self::screen('wh-receiving-putaway', '?task='.$t->id_task),
            }, 'urlLabel' => t('Open')])->all();
    }

    /** @return list<array<string, mixed>> */
    private static function pickLists(): array
    {
        $lists = WhPickList::query()->where(fn ($q) => $q->whereIn('status', WhPickList::OPEN)->orWhere(fn ($d) => $d->where('status', 'DONE')->where('updated_at', '>=', self::since())))->orderByDesc('id_pick_list')->limit(200)->get();

        return $lists->map(fn (WhPickList $l) => ['id' => 'pick:'.$l->id_pick_list, 'col' => match ($l->status) {
            'DRAFT' => 'open', 'IN_PROGRESS' => 'progress', default => 'done'
        },
            'title' => $l->pick_no.' · '.($l->wo_no ?: '—'), 'desc' => $l->need_date ? t('Needed By').' '.Locale::d($l->need_date) : '', 'tags' => [['label' => t('Pick List'), 'tone' => 'violet']],
            'url' => self::screen('wh-pick-list', '?id='.$l->id_pick_list), 'urlLabel' => t('Open')])->all();
    }

    /** @return list<array<string, mixed>> */
    private static function transfers(): array
    {
        $docs = InvTransfer::query()->with(['fromWarehouse', 'toWarehouse'])->where('transit_mode', 'TWO_STEP')
            ->where(fn ($q) => $q->whereIn('transit_status', ['SHIPPED', 'DISCREPANCY'])->orWhere(fn ($d) => $d->whereIn('transit_status', ['RECEIVED', 'CLOSED'])->where('received_at', '>=', self::since())))
            ->orderByDesc('shipped_at')->limit(200)->get();

        return $docs->map(fn (InvTransfer $t) => ['id' => 'tf:'.$t->id_transfer, 'col' => in_array($t->transit_status, ['SHIPPED', 'DISCREPANCY'], true) ? 'progress' : 'done',
            'title' => $t->transfer_no.' · '.($t->fromWarehouse?->warehouse_code ?? '?').' → '.($t->toWarehouse?->warehouse_code ?? '?'),
            'desc' => t(InTransit::STATUS[$t->transit_status] ?? $t->transit_status), 'tags' => [['label' => t('In Transit'), 'tone' => $t->transit_status === 'DISCREPANCY' ? 'danger' : 'info']],
            'priority' => $t->transit_status === 'DISCREPANCY' ? 'High' : null, 'url' => self::screen('wh-in-transit', '?id='.$t->id_transfer), 'urlLabel' => t('Open')])->all();
    }

    /** @return list<array<string, mixed>> area yang jatuh tempo dihitung (belum ada tugas hitungnya) */
    private static function dueCounts(): array
    {
        return (new CountTaskService)->dueZones()->map(fn ($z) => ['id' => 'due:'.$z->id_zone, 'col' => 'open', 'title' => t('Count due').' · '.$z->zone_code.' · '.$z->zone_name,
            'desc' => t('every').' '.$z->count_every_days.' '.t('days'), 'tags' => [['label' => t('Count'), 'tone' => 'warn']],
            'url' => self::screen('wh-count-tasks'), 'urlLabel' => t('Open')])->values()->all();
    }
}
