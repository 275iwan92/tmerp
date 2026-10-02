<?php

namespace App\Support\V2\Data;

use App\Models\Inventory\RmBooking;
use App\Models\Pur\PrLine;
use App\Services\Planning\MaterialPlanningEngine;
use Carbon\CarbonImmutable;
use Devlib\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Layar v2 Material Planning (Material Booking, Material Planning/MRP, popup kekurangan bahan): membaca hasil
// App\Services\Planning\MaterialPlanningEngine dan menyiapkan baris grid. Aturan & rumus hanya di engine.
final class MaterialPlanning
{
    public const BOOKING_TONES = ['NOT BOOKED' => 'neutral', 'DRAFT' => 'warn', 'CONFIRMED' => 'success', 'FORECAST' => 'info'];

    public const CO_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'CONFIRMED' => 'success', 'FORECAST' => 'info'];

    // Kode status engine -> label layar (English; kamus sys_dictionary menerjemahkan).
    public const COVER = ['NEED_LAYOUT' => 'Need layout', 'NEED_ITEM' => 'Need item', 'SHORT' => 'Short', 'LATE_PR' => 'Late', 'LATE_PO' => 'Late',
        'PEGGED_PR' => 'Waiting PO/PR', 'PEGGED_PO' => 'Waiting PO/PR', 'STOCK' => 'Covered by stock', 'FG_STOCK' => 'Covered by stock', 'BOOKED' => 'Booked'];

    public const COVER_TONES = ['Need layout' => 'danger', 'Need item' => 'danger', 'Short' => 'danger', 'Late' => 'warn', 'Waiting PO/PR' => 'info',
        'Covered by stock' => 'success', 'Booked' => 'success'];

    public static function engine(): MaterialPlanningEngine
    {
        return new MaterialPlanningEngine(Auth::user()?->login);
    }

    // Satu baris per CO aktif (+ forecast) dengan ringkasan penutup bahan.
    public static function coRows(): array
    {
        $engine = self::engine();
        $bookings = RmBooking::where('stat_cancel', 0)->whereNotNull('co_id')->get()->keyBy('co_id');
        $out = [];
        foreach (collect($engine->plan()['lines'])->groupBy(fn ($l) => $l['co_id'] ?: 'F'.$l['co_no']) as $rows) {
            $f = $rows->first();
            $b = $f['co_id'] ? ($bookings[$f['co_id']] ?? null) : null;
            $worst = $rows->pluck('status')->sortByDesc(fn ($s) => MaterialPlanningEngine::SEVERITY[$s] ?? 0)->first();
            $out[] = ['id' => $f['co_id'] ?: 0, 'co' => $f['co_no'], 'customer' => $f['customer'], 'co_status' => $f['co_status'],
                'booking_no' => $b->booking_no ?? '—', 'booking' => $f['type'] === 'FORECAST' ? 'FORECAST' : ($b ? $b->doc_status : 'NOT BOOKED'),
                'need' => $rows->min('need_date'), 'lines' => $rows->count(),
                // Baris anak grid (Expand): product + bahan yang dibutuhkan CO ini.
                'items' => $rows->map(fn ($l) => ['product' => $l['product'], 'part' => $l['part'] ?? '—', 'material' => $l['material'], 'qty' => (float) $l['qty'],
                    'uom' => $l['uom'], 'need' => $l['need_date'], 'status' => self::COVER[$l['status']] ?? $l['status']])->values()->all(),
                'short' => $rows->filter(fn ($l) => in_array($l['status'], ['SHORT', 'NEED_ITEM', 'NEED_LAYOUT'], true))->count(),
                'cover' => self::COVER[$worst] ?? $worst,
                'changed' => $b && $b->stat_confirm && $b->co_fingerprint !== $engine->fingerprint((int) $f['co_id']) ? 'CO changed' : ''];
        }

        return collect($out)->sortBy('need')->values()->all();
    }

    public static function lineRows(int $coId): array
    {
        return collect(self::engine()->linesForCo($coId))->values()->map(fn ($l, $i) => ['id' => $i + 1, 'product' => $l['product'], 'part' => $l['part'] ?? '—',
            'role' => $l['role'], 'material' => $l['material'].(! empty($l['picked']) ? ' (picked)' : ''), 'need' => $l['need_date'], 'qty' => (float) $l['qty'],
            'uom' => $l['uom'], 'covered' => self::allocText($l['alloc']), 'short' => (float) ($l['short'] ?? 0), 'status' => self::COVER[$l['status']] ?? $l['status'],
            'calc' => $l['note']])->all();
    }

    public static function allocText(array $alloc): string
    {
        return collect($alloc)->map(fn ($a) => Locale::number((float) $a['qty'], 0).' '.match ($a['type']) {
            'STOCK' => 'stock', 'FG' => 'FG stock', default => $a['no'].($a['date'] ? ' ('.Locale::d($a['date']).($a['late'] ? ', late' : '').')' : ''),
        })->implode(' + ') ?: '—';
    }

    // Satu baris per bahan (Shortage Planner / MRP).
    public static function materialRows(): array
    {
        $suppliers = DB::connection('tm2mst')->table('sup_supplier')->pluck('supplier_name', 'id_supplier');

        return collect(self::engine()->plan()['materials'])->values()->map(fn ($m) => ['id' => $m['material_id'] ?: 0, 'material' => $m['material'],
            'code' => $m['code'] ?? 'no item yet', 'role' => $m['role'], 'uom' => $m['uom'], 'need' => $m['need'], 'stock' => $m['stock'],
            'incoming' => $m['incoming'], 'short' => $m['short'], 'first_need' => $m['first_need'], 'order_by' => $m['order_by'], 'lead' => $m['lead_days'],
            'status' => self::COVER[$m['status']] ?? $m['status'], 'orders' => implode(', ', $m['orders']), 'supplier' => $suppliers[$m['supplier_id']] ?? '—'])->all();
    }

    // Proyeksi saldo satu bahan per tanggal: stok Available sekarang, + PO/PR datang, - kebutuhan.
    public static function timeline(int $materialId): array
    {
        $events = [];
        foreach (collect(self::engine()->plan()['lines'])->where('material_id', $materialId) as $l) {
            $events[] = ['date' => $l['need_date'], 'what' => $l['co_no'].' · '.$l['product'], 'in' => 0.0, 'out' => (float) $l['qty']];
            foreach ($l['alloc'] as $a) {
                if (in_array($a['type'], ['PO', 'PR'], true)) {
                    $events[] = ['date' => $a['date'] ?? $l['need_date'], 'what' => $a['type'].' '.$a['no'], 'in' => (float) $a['qty'], 'out' => 0.0];
                }
            }
        }
        $bal = (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $materialId)->where('is_usable', 1)->sum('qty_available');
        $rows = [['id' => 0, 'date' => CarbonImmutable::today()->toDateString(), 'what' => 'Available stock today', 'in' => $bal, 'out' => 0.0, 'balance' => $bal]];
        foreach (collect($events)->sortBy([['date', 'asc'], ['out', 'asc']])->values() as $i => $e) {
            $bal += $e['in'] - $e['out'];
            $rows[] = ['id' => $i + 1] + $e + ['balance' => $bal];
        }

        return $rows;
    }

    // PR dari kekurangan: satu PR per supplier usulan, qty dibulatkan ke atas ke satuan beli, tanggal butuh = butuh pertama.
    public static function createPr(array $materialIds): array
    {
        $engine = self::engine();
        $plan = $engine->plan();
        $mats = collect($plan['materials'])->whereNotNull('material_id')->whereIn('material_id', array_map('intval', $materialIds))->where('short', '>', 0);
        abort_if($mats->isEmpty(), 422, t('No short material with an item was selected.'));
        $dept = DB::connection('tm2mst')->table('org_department')->where('department_name', 'PPIC')->value('department_id')
            ?? DB::connection('tm2mst')->table('org_department')->where('activate', 1)->value('department_id');
        $lines = collect($plan['lines']);
        $made = [];
        foreach ($mats->groupBy(fn ($m) => $m['supplier_id'] ?: 0) as $supplierId => $group) {
            $pr = PurchaseRequisition::create((int) $dept);
            $supplier = $supplierId ? DB::connection('tm2mst')->table('sup_supplier')->where('id_supplier', $supplierId)->value('supplier_name') : null;
            $pr->update(['i_source' => 'MRP', 'i_need_date' => $group->min('first_need'),
                'i_notes' => mb_substr('Material Planning '.now()->toDateString().($supplier ? ' · suggested supplier: '.$supplier : ''), 0, 255)]);
            $c = PurchaseRequisition::component($pr->pr_id);
            foreach ($group as $m) {
                $item = $engine->material((int) $m['material_id']);
                $c->addMasterItem((int) $m['material_id']);
                $line = PrLine::where('pr_id', $pr->pr_id)->orderByDesc('pr_line_id')->first();
                $c->updateLine($line->pr_line_id, 'i_qty', (string) ceil($m['short'] / ((float) $item->conversion_to_stock ?: 1)));
                $c->updateLine($line->pr_line_id, 'i_need_date', $m['first_need']);
                $c->updateLine($line->pr_line_id, 'i_notes', mb_substr('For '.implode(', ', $m['orders']), 0, 255));
                PrLine::whereKey($line->pr_line_id)->update(['i_co_line_id' => $lines->where('material_id', $m['material_id'])->where('short', '>', 0)->pluck('co_item_id')->filter()->first()]);
            }
            $made[] = $pr->fresh();
        }
        self::changed();

        return $made;
    }

    public static function changed(): void
    {
        MaterialPlanningEngine::forget();
        Cache::forget('material_planning.alerts');
    }

    // Popup layar pembuka: kekurangan bahan untuk Direksi / Ops Manager / PPIC / Warehouse / Finance (B5). Dihitung ulang tiap 10 menit.
    public static function alerts(): array
    {
        $u = Auth::user();
        if (! $u || ! collect(['PROD', 'INV', 'FINACC', 'MGMT', 'PROC'])->contains(fn ($m) => $u->canAccessModule($m))) {
            return [];
        }

        // Situs yang belum menjalankan migration Material Planning (tm2* sebelum disetujui user): tanpa peringatan.
        if (! Schema::connection('tm2inv')->hasColumn('rm_booking', 'co_id')) {
            return [];
        }

        return Cache::remember('material_planning.alerts', 600, function () {
            $bad = collect(self::engine()->plan()['materials'])->filter(fn ($m) => in_array($m['status'], ['SHORT', 'NEED_ITEM', 'NEED_LAYOUT', 'LATE_PO', 'LATE_PR'], true));

            return ['count' => $bad->count(), 'short' => $bad->where('status', 'SHORT')->count(),
                'need_item' => $bad->whereIn('status', ['NEED_ITEM', 'NEED_LAYOUT'])->count(), 'late' => $bad->whereIn('status', ['LATE_PO', 'LATE_PR'])->count(),
                'top' => $bad->take(6)->map(fn ($m) => ['material' => $m['material'], 'short' => $m['short'], 'uom' => $m['uom'], 'first_need' => $m['first_need'],
                    'order_by' => $m['order_by'], 'orders' => implode(', ', array_slice($m['orders'], 0, 3)), 'status' => self::COVER[$m['status']] ?? $m['status']])->values()->all()];
        });
    }
}
