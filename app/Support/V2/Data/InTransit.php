<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvTransfer;
use App\Models\Inventory\InvTransferLine;
use App\Models\Master\WhLoc;
use Devlib\Locale;

// Data layar Warehouse > In Transit (wh-in-transit): transfer dua langkah (transit_mode TWO_STEP) yang sudah dikirim. Logika Ship / Receive /
// penyelesaian selisih: App\Services\Inventory\StockTransferService; kelas ini hanya menyusun baris tampilan.
final class InTransit
{
    public const STATUS = ['SHIPPED' => 'In transit', 'DISCREPANCY' => 'Discrepancy', 'RECEIVED' => 'Received', 'CLOSED' => 'Closed', 'CANCELLED' => 'Cancelled'];

    public const TONES = ['In transit' => 'info', 'Discrepancy' => 'danger', 'Received' => 'success', 'Closed' => 'neutral', 'Cancelled' => 'neutral'];

    public static function find(int $id): ?InvTransfer
    {
        return InvTransfer::query()->with(['lines', 'fromWarehouse', 'toWarehouse'])->where('transit_mode', 'TWO_STEP')->whereNotNull('transit_status')->find($id);
    }

    // Umur dalam hari: berjalan selama masih di jalan (SHIPPED / DISCREPANCY), jarak kirim ke terima bila sudah diterima.
    public static function ageDays(InvTransfer $t): float
    {
        if (! $t->shipped_at) {
            return 0.0;
        }
        $end = in_array($t->transit_status, ['SHIPPED', 'DISCREPANCY'], true) || ! $t->received_at ? now() : $t->received_at;

        return round($t->shipped_at->diffInMinutes($end, true) / 1440, 1);
    }

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $docs = InvTransfer::query()->with(['lines', 'fromWarehouse', 'toWarehouse'])->where('transit_mode', 'TWO_STEP')->whereNotNull('transit_status')->orderByDesc('shipped_at')->limit(300)->get();
        $bins = WhLoc::query()->whereKey($docs->flatMap(fn (InvTransfer $t) => $t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id')))->filter()->unique()->all())->pluck('location_code', 'location_id');

        return $docs->map(fn (InvTransfer $t) => [
            'id' => $t->id_transfer, 'no' => $t->transfer_no, 'route' => ($t->fromWarehouse?->warehouse_code ?? '?').' → '.($t->toWarehouse?->warehouse_code ?? '?'),
            'shipped' => $t->shipped_at?->toDateTimeString(), 'shipped_by' => $t->shipped_by, 'age' => self::ageDays($t), 'carried_by' => $t->carried_by,
            'from' => $t->fromWarehouse?->warehouse_name, 'to' => $t->toWarehouse?->warehouse_name, 'status' => self::STATUS[$t->transit_status] ?? $t->transit_status,
            'short' => round((float) $t->lines->sum(fn (InvTransferLine $l) => $l->diff_resolution ? 0 : max(0.0, (float) $l->qty_diff)), 4),
            'items' => $t->lines->map(fn (InvTransferLine $l) => ['code' => $l->item_code, 'name' => $l->item_name, 'qty' => $l->qty, 'received' => $l->qty_received, 'unit' => $l->uom_code,
                'from_bin' => $bins[$l->from_location_id] ?? null, 'to_bin' => $bins[$l->to_location_id] ?? null])->values()->all(),
        ])->all();
    }

    // Baris dokumen untuk tabel panel: dikirim, diterima, selisih dan penyelesaiannya.
    /** @return list<array<string, mixed>> */
    public static function lineRows(InvTransfer $t): array
    {
        $bins = WhLoc::query()->whereKey($t->lines->pluck('from_location_id')->merge($t->lines->pluck('to_location_id'))->filter()->unique()->all())->pluck('location_code', 'location_id');

        return $t->lines->map(fn (InvTransferLine $l) => ['id' => $l->id_transfer_line, 'line' => $l->line_no, 'item' => $l->item_name, 'code' => $l->item_code, 'sent' => Locale::decimal((float) $l->qty, 2),
            'received' => $l->qty_received === null ? '—' : Locale::decimal((float) $l->qty_received, 2), 'diff' => $l->qty_diff === null ? '—' : Locale::decimal((float) $l->qty_diff, 2),
            'unit' => $l->uom_code, 'from' => $bins[$l->from_location_id] ?? '—', 'to' => $bins[$l->to_location_id] ?? '—',
            'settled' => $l->diff_resolution ? t($l->diff_resolution === 'LOSS' ? 'Booked as loss' : 'Returned to source') : ((float) $l->qty_diff > 0 ? t('Open') : '—')])->all();
    }
}
