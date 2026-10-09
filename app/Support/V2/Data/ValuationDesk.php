<?php

declare(strict_types=1);

namespace App\Support\V2\Data;

use App\Models\Inventory\InvLandedCost;
use App\Models\Inventory\InvLandedCostLine;
use App\Models\Inventory\InvObsoleteProvision;
use App\Models\Inventory\InvPeriod;
use App\Models\Inventory\InvReconcileLog;
use App\Models\Inventory\StockOnhand;
use App\Models\Master\SupSupplier;
use App\Models\Pur\PvReceipt;
use App\Services\Inventory\Valuation\LandedCostService;
use App\Services\Inventory\Valuation\ObsolescenceService;
use App\Support\V2\Grid\Inv\AllStockReport;
use Devlib\Locale;

// Data layar Inventory > Valuation & Period (Landed Cost, Obsolete Stock, Valuation & Period): pilihan form, baris tabel kecil, ringkasan.
// Aturan dan jurnalnya di App\Services\Inventory\Valuation\*; kelas ini hanya menyiapkan tampilan.
final class ValuationDesk
{
    public static function landed(int $id): ?InvLandedCost
    {
        return InvLandedCost::query()->with('lines')->find($id);
    }

    /** @return array<int, string> supplier aktif (penagih biaya) */
    public static function supplierOptions(): array
    {
        return PurchaseOrder::suppliers();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return array_map('t', InvLandedCost::TYPES);
    }

    /** @return array<string, string> */
    public static function basisOptions(): array
    {
        return array_map('t', InvLandedCost::BASES);
    }

    /** @return array<int, string> penerimaan PO yang sudah POSTED: nomor · supplier · tanggal */
    public static function receiptOptions(): array
    {
        $receipts = PvReceipt::query()->where('status', 'POSTED')->where('i_receipt_type', 'PO')->orderByDesc('pv_id')->limit(300)->get();
        $names = SupSupplier::query()->whereKey($receipts->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier');

        return $receipts->mapWithKeys(fn (PvReceipt $r) => [$r->pv_id => implode(' · ', array_filter([$r->doc_no, $names[$r->i_supplier_id] ?? null, Locale::d($r->i_doc_date)]))])->all();
    }

    /**
     * Baris tabel dokumen: nilai terima, bagian biaya, bagian ke persediaan / selisih. Draft memakai perkiraan (allocate), Confirmed memakai angka yang tersimpan.
     *
     * @return list<array<string, string>>
     */
    public static function lineRows(InvLandedCost $doc): array
    {
        $plan = [];
        if ($doc->status() === 'DRAFT' && (float) $doc->amount > 0 && $doc->lines->isNotEmpty()) {
            try {
                $plan = collect(app(LandedCostService::class)->allocate($doc))->get('lines');
                $plan = collect($plan)->keyBy('id')->all();
            } catch (\RuntimeException) {
                $plan = [];
            }
        }

        return $doc->lines->map(function (InvLandedCostLine $l) use ($plan) {
            $p = $plan[$l->id_landed_cost_line] ?? null;

            return ['line' => (string) $l->line_no, 'item' => $l->item_code.' '.$l->item_name, 'qty' => Locale::decimal((float) $l->qty_received, 2).' '.$l->uom_code, 'value' => Locale::money($l->base_value),
                'allocated' => Locale::money($p['allocated'] ?? $l->allocated), 'to_stock' => Locale::money($p['to_stock'] ?? $l->to_stock), 'to_variance' => Locale::money($p['to_variance'] ?? $l->to_variance)];
        })->all();
    }

    /** @return list<array<string, string>> periode inventory terbaru */
    public static function periodRows(): array
    {
        return InvPeriod::query()->orderByDesc('i_period_code')->limit(24)->get()->map(fn (InvPeriod $p) => ['period' => $p->i_period_code,
            'range' => Locale::d($p->i_date_start).' – '.Locale::d($p->i_date_end), 'status' => t(ucfirst(strtolower($p->i_status))),
            'closed' => $p->c_closed_at ? $p->c_closed_by.' · '.Locale::stamp($p->c_closed_at) : '—'])->all();
    }

    /** @return array<string, string> kode periode => teks, untuk pilihan Close / Open */
    public static function periodOptions(): array
    {
        return InvPeriod::query()->orderByDesc('i_period_code')->limit(24)->get()->mapWithKeys(fn (InvPeriod $p) => [$p->i_period_code => $p->i_period_code.' · '.t(ucfirst(strtolower($p->i_status)))])->all();
    }

    /** @return list<array<string, string>> nilai stok sekarang per kelas dan status rekonsiliasi terakhir */
    public static function classRows(): array
    {
        $rows = [];
        foreach (AllStockReport::CLASSES as $class => $label) {
            $value = (float) StockOnhand::of($class)->sum('value_onhand');
            $last = InvReconcileLog::query()->where('stock_class', $class)->orderByDesc('recon_date')->first();
            $rows[] = ['class' => $label, 'value' => Locale::money($value), 'recon' => $last ? Locale::d($last->recon_date).' · '.Locale::money($last->difference) : '—',
                'status' => $last ? t($last->status === 'OK' ? 'Balanced' : 'Difference') : '—'];
        }

        return $rows;
    }

    /** @return list<array<string, string>> ringkasan Obsolete Stock per kelas aging */
    public static function agingRows(array $summary): array
    {
        $rows = [];
        foreach (ObsolescenceService::LABELS as $class => $label) {
            $s = $summary[$class];
            $rows[] = ['aging' => t($label), 'pct' => $class === ObsolescenceService::ACTIVE ? '—' : Locale::decimal(ObsolescenceService::pct($class), 2).'%', 'items' => Locale::number($s['items']),
                'value' => Locale::money($s['value']), 'provision' => Locale::money($s['provision'])];
        }

        return $rows;
    }

    /** @return list<array<string, string>> posting provisi sebelumnya */
    public static function provisionHistory(): array
    {
        return InvObsoleteProvision::query()->orderByDesc('id_provision')->limit(12)->get()->map(fn (InvObsoleteProvision $p) => ['date' => Locale::d($p->provision_date),
            'value' => Locale::money($p->stock_value), 'total' => Locale::money($p->provision_total), 'previous' => Locale::money($p->previous_total), 'adjustment' => Locale::money($p->adjustment),
            'by' => (string) $p->created_by])->all();
    }
}
