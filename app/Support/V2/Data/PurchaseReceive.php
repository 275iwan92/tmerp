<?php

namespace App\Support\V2\Data;

use App\Models\Pur\PvReceipt;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

// Penerimaan Barang (SPB / PV) v2 = aturan & tulis data komponen v1 pages::pur.pv.entry (updateHeader / updateLine / markQtyDone /
// setQuality / updateQcQty / overrideTolerance / addSelectedLines / post). Sumber baris = PO SENT / PARTIALLY_RECEIVED.
final class PurchaseReceive
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_supplier_do_no', 'i_vehicle_no', 'i_notes'];

    public const LINE = ['i_qty_purchase', 'i_qty_stock', 'i_lot_no', 'i_location_code', 'i_supplier_do_no'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'QC_PENDING' => 'warn', 'POSTED' => 'success', 'CANCELLED' => 'danger'];

    public static function component(int $id): Component
    {
        $c = app('livewire')->new('pages::pur.pv.entry');
        $c->pvId = $id;

        return $c;
    }

    public static function rows(): array
    {
        $pvs = PvReceipt::withCount('lines')->orderByDesc('pv_id')->limit(500)->get();
        $sup = PurchaseOrder::suppliers($pvs->pluck('i_supplier_id')->unique()->all());

        return $pvs->map(fn ($p) => ['id' => $p->pv_id, 'no' => $p->doc_no, 'supplier' => $sup[$p->i_supplier_id] ?? '—', 'date' => $p->i_doc_date?->toDateString(),
            'warehouse' => $p->i_warehouse_code, 'po' => $p->c_po_doc_list, 'lines' => $p->lines_count, 'status' => $p->status])->all();
    }

    // Supplier yang masih punya PO terbuka untuk diterima (Langkah 1 v1).
    public static function supplierOptions(): array
    {
        $w = PvBuilderService::supplierWorklist();
        $names = PurchaseOrder::suppliers($w->pluck('i_supplier_id')->all());

        return $w->mapWithKeys(fn ($r) => [$r->i_supplier_id => ($names[$r->i_supplier_id] ?? '#'.$r->i_supplier_id).' · '.$r->open_po_count.' PO, '.$r->open_line_count.' baris terbuka'])->all();
    }

    public static function openLineOptions(int $supplierId): array
    {
        return PvBuilderService::openLinesForSupplier($supplierId)->mapWithKeys(fn ($l) => [$l->po_line_id => ($l->order->doc_no ?? 'PO').' #'.$l->line_no.' · '.$l->i_description
            .' · sisa '.rtrim(rtrim(number_format((float) $l->i_qty - (float) $l->c_qty_received, 2, '.', ''), '0'), '.').' '.$l->i_uom])->all();
    }

    public static function warehouses(): array
    {
        return DB::connection('tm2mst')->table('wh_warehouse')->where('activate', 1)->orderBy('warehouse_code')
            ->get(['warehouse_code', 'warehouse_name'])->mapWithKeys(fn ($w) => [$w->warehouse_code => $w->warehouse_code.' · '.$w->warehouse_name])->all();
    }

    public static function create(int $supplierId, string $warehouse, array $poLineIds): PvReceipt
    {
        return PvBuilderService::createFromLines($supplierId, $warehouse, array_map('intval', $poLineIds));
    }

    public static function addLines(PvReceipt $pv, array $poLineIds): void
    {
        PvBuilderService::addLines($pv, array_map('intval', $poLineIds));
    }

    // Header + baris (qty, lot, lokasi) + QC per baris dari satu form.
    public static function save(PvReceipt $pv, array $in): void
    {
        abort_unless(in_array($pv->status, ['DRAFT', 'QC_PENDING'], true), 422, 'Penerimaan sudah diposting, tidak bisa diubah.');
        $c = self::component($pv->pv_id);
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::changed(self::str($in[$f]), $pv->{$f})) {
                $c->updateHeader($f, self::str($in[$f]));
            }
        }
        $lines = $pv->lines()->get()->keyBy('pv_line_id');
        foreach ((array) ($in['lines'] ?? []) as $id => $row) {
            $line = $lines->get((int) $id);
            if (! $line) {
                continue;
            }
            foreach (self::LINE as $f) {
                if (array_key_exists($f, (array) $row)) {
                    $v = in_array($f, ['i_qty_purchase', 'i_qty_stock'], true) ? self::num($row[$f]) : self::str($row[$f]);
                    if (self::changed($v, $line->{$f})) {
                        $c->updateLine((int) $id, $f, $v);
                    }
                }
            }
            // QC qty selesai (centang) -> markQtyDone; QC kualitas PASS / FAIL / PARTIAL (+ qty diterima/ditolak bila PARTIAL).
            if (($row['qc_qty'] ?? '') === 'DONE' && $line->qc_qty_status !== 'DONE') {
                $c->markQtyDone((int) $id);
            }
            $q = (string) ($row['qc_quality_status'] ?? '');
            if ($q !== '' && $q !== (string) $line->qc_quality_status) {
                $c->setQuality((int) $id, $q);
            }
            if ($q === 'PARTIAL') {
                foreach (['c_qty_accepted', 'c_qty_rejected'] as $f) {
                    if (array_key_exists($f, (array) $row) && self::changed(self::num($row[$f]), $line->{$f})) {
                        $c->updateQcQty((int) $id, $f, self::num($row[$f]));
                    }
                }
            }
            if (trim((string) ($row['tol_override_reason'] ?? '')) !== '' && $line->is_tol_exceeded && ! $line->tol_override_by) {
                $c->overrideTolerance((int) $id, (string) $row['tol_override_reason']);
            }
        }
    }

    // Confirm = Post v1: stok masuk + jurnal pembelian. Balasan = [ok, pesan].
    public static function post(PvReceipt $pv): array
    {
        $r = PvPostingService::post($pv->refresh()->load('lines'));

        return [$r['ok'], $r['ok'] ? trim('Penerimaan diposting — stok masuk. '.($r['journal_warning'] ?? '')) : $r['message']];
    }

    public static function delete(PvReceipt $pv): void
    {
        abort_unless($pv->status === 'DRAFT', 422, 'Hanya penerimaan DRAFT yang bisa dihapus.');
        $pv->delete();
    }

    // Draft (DRAFT / QC_PENDING) > Confirmed (POSTED) > Closed (sudah diserahkan / ditagih penuh).
    public static function formStatus(PvReceipt $pv): string
    {
        return match ($pv->status) {
            'DRAFT', 'QC_PENDING' => 'draft', 'POSTED' => $pv->is_handed_over ? 'closed' : 'confirmed', 'CANCELLED' => 'cancelled', default => 'draft',
        };
    }
}
