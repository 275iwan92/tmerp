<?php

/*
 * REMARK
 * Tujuan       : Penerimaan Barang (SPB / PV) v2: daftar opsi (supplier, PO terbuka, CO, subkon, gudang), buat SPB, simpan header + baris + QC dari satu form, Post (Confirm), Cancel, hapus DRAFT, status form. Aturan entri header/baris/QC ada di PurchaseReceiveEntryService (dipindah dari komponen v1 pages::pur.pv.entry). Sumber baris = PO SENT / PARTIALLY_RECEIVED.
 * Sumber hulu  : PvReceipt, PvLine, PvBuilderService, PvPostingService, SpecialReceiptService, PurchaseReceiveEntryService.
 * Input        : PvReceipt + isi form (array) dari rute v2.pv.*.
 * Output       : pv_receipt, pv_line (tm2pur); posting stok + jurnal lewat PvPostingService.
 * Dipakai oleh : routes/v2.php (prefix pv), PurchaseReceiveGrid, layar purchase-receive, tests/Feature/PurchaseReceiveEntryTest.
 * Status       : Aktif (T10 tahap 7b, 2026-10-22: tidak lagi membuat komponen Livewire v1).
 */

namespace App\Support\V2\Data;

use App\Models\Pur\PvReceipt;
use App\Services\Pur\PurchaseReceiveEntryService;
use App\Services\Pur\PvBuilderService;
use App\Services\Pur\PvPostingService;
use App\Services\Pur\SpecialReceiptService;
use App\Support\V2\Data\Concerns\FlowDocument;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class PurchaseReceive
{
    use FlowDocument;

    public const HEADER = ['i_doc_date', 'i_supplier_do_no', 'i_vehicle_no', 'i_notes'];

    public const LINE = ['i_qty_purchase', 'i_qty_stock', 'i_lot_no', 'i_location_code', 'i_supplier_do_no'];

    public const STATUS_TONES = ['DRAFT' => 'neutral', 'QC_PENDING' => 'warn', 'POSTED' => 'success', 'CANCELLED' => 'danger'];

    // Jenis penerimaan (user 2026-10-01): dari PO supplier, bahan dari customer (wajib No. CO), barang kembali dari subkon.
    public const TYPES = ['PO' => 'From PO', 'CUSTOMER' => 'From Customer', 'SUBCON' => 'From Subcon'];

    public const TYPE_TONES = ['From PO' => 'neutral', 'From Customer' => 'info', 'From Subcon' => 'warn'];

    // CO yang boleh menerima bahan customer: Confirmed / Submitted, belum batal. Label = No. CO · customer.
    public static function coOptions(): array
    {
        $cos = DB::connection('tm2sdi')->table('co_order')->whereIn('doc_status', ['CONFIRMED', 'SUBMITTED'])->where('stat_cancel', 0)->orderByDesc('id_order')->limit(300)
            ->get(['id_order', 'co_no', 'customer_id']);
        $cust = DB::connection('tm2mst')->table('cust_customer')->whereIn('id_customer', $cos->pluck('customer_id')->unique())->pluck('customer_name', 'id_customer');

        return $cos->mapWithKeys(fn ($c) => [$c->id_order => $c->co_no.' · '.($cust[$c->customer_id] ?? '—')])->all();
    }

    // Bahan customer yang sudah datang utk satu CO (tab Customer Material di layar CO v2). Hanya penerimaan Posted.
    public static function customerMaterialsForCo(int $coId): array
    {
        $pvs = PvReceipt::with(['lines' => fn ($q) => $q->orderBy('line_no')])->where('i_receipt_type', 'CUSTOMER')->where('i_co_id', $coId)
            ->whereIn('status', ['DRAFT', 'POSTED'])->orderBy('pv_id')->get();
        $items = DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $coId)->pluck('line_seq', 'id_item');

        return ['receipts' => $pvs->map(fn ($p) => ['id' => $p->pv_id, 'no' => $p->label(), 'status' => $p->status])->all(),
            'rows' => $pvs->flatMap(fn ($p) => $p->lines->map(fn ($l) => ['doc' => $p->doc_no ?: 'Draft #'.$p->pv_id, 'date' => Locale::d($p->i_doc_date),
                'item' => isset($items[$l->i_co_line_id]) ? '#'.$items[$l->i_co_line_id] : '—', 'material' => $l->i_description, 'qty' => (float) $l->i_qty_purchase,
                'uom' => $l->i_uom_purchase, 'lot' => $l->i_lot_no ?: '—']))->values()->all()];
    }

    public static function coItemOptions(?int $coId): array
    {
        return $coId ? DB::connection('tm2sdi')->table('co_order_item')->where('order_id', $coId)->orderBy('line_seq')
            ->get(['id_item', 'line_seq', 'description'])->mapWithKeys(fn ($i) => [$i->id_item => '#'.$i->line_seq.' '.$i->description])->all() : [];
    }

    public static function subconSupplierOptions(): array
    {
        $ids = SpecialReceiptService::openSubconLines()->pluck('order.i_supplier_id')->unique()->all();

        return PurchaseOrder::suppliers($ids);
    }

    public static function subconLineOptions(int $supplierId): array
    {
        return SpecialReceiptService::openSubconLines($supplierId)->mapWithKeys(fn ($l) => [$l->po_line_id => $l->order->doc_no.' #'.$l->line_no.' · '.$l->i_description
            .' · '.tf('open :qty', ['qty' => Locale::decimal((float) $l->i_qty - (float) $l->c_qty_received, 2).' '.$l->i_uom])])->all();
    }

    // Surat Jalan Subkon (DO jenis SUBCON) ke subkon ini -- rujukan opsional.
    public static function subconDoOptions(int $supplierId): array
    {
        return DB::connection('tm2sdi')->table('do_delivery')->where('do_type', 'SUBCON')->where('supplier_id', $supplierId)->orderByDesc('id_delivery')
            ->limit(100)->pluck('do_no', 'id_delivery')->all();
    }

    // Supplier yang masih punya PO terbuka untuk diterima (Langkah 1 v1).
    public static function supplierOptions(): array
    {
        $w = PvBuilderService::supplierWorklist();
        $names = PurchaseOrder::suppliers($w->pluck('i_supplier_id')->all());

        return $w->mapWithKeys(fn ($r) => [$r->i_supplier_id => ($names[$r->i_supplier_id] ?? '#'.$r->i_supplier_id).' · '.tf(':po PO, :lines open lines', ['po' => $r->open_po_count, 'lines' => $r->open_line_count])])->all();
    }

    public static function openLineOptions(int $supplierId): array
    {
        return PvBuilderService::openLinesForSupplier($supplierId)->mapWithKeys(fn ($l) => [$l->po_line_id => ($l->order->doc_no ?? 'PO').' #'.$l->line_no.' · '.$l->i_description
            .' · '.tf('remaining :qty', ['qty' => Locale::decimal($l->i_qty - (float) $l->c_qty_received, 2).' '.$l->i_uom])])->all();
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
        abort_unless(in_array($pv->status, ['DRAFT', 'QC_PENDING'], true), 422, t('The receipt is already posted and cannot be changed.'));
        foreach (self::HEADER as $f) {
            if (array_key_exists($f, $in) && self::changed(self::str($in[$f]), $pv->{$f})) {
                PurchaseReceiveEntryService::updateHeader($pv, $f, self::str($in[$f]));
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
                        PurchaseReceiveEntryService::updateLine($pv, (int) $id, $f, $v);
                    }
                }
            }
            // QC qty selesai (centang) -> markQtyDone; QC kualitas PASS / FAIL / PARTIAL (+ qty diterima/ditolak bila PARTIAL).
            if (($row['qc_qty'] ?? '') === 'DONE' && $line->qc_qty_status !== 'DONE') {
                PurchaseReceiveEntryService::markQtyDone($pv, (int) $id);
            }
            $q = (string) ($row['qc_quality_status'] ?? '');
            if ($q !== '' && $q !== (string) $line->qc_quality_status) {
                PurchaseReceiveEntryService::setQuality($pv, (int) $id, $q);
            }
            if ($q === 'PARTIAL') {
                foreach (['c_qty_accepted', 'c_qty_rejected'] as $f) {
                    if (array_key_exists($f, (array) $row) && self::changed(self::num($row[$f]), $line->{$f})) {
                        PurchaseReceiveEntryService::updateQcQty($pv, (int) $id, $f, self::num($row[$f]));
                    }
                }
            }
            if (trim((string) ($row['tol_override_reason'] ?? '')) !== '' && $line->is_tol_exceeded && ! $line->tol_override_by) {
                PurchaseReceiveEntryService::overrideTolerance($pv, (int) $id, (string) $row['tol_override_reason']);
            }
        }
    }

    // Confirm = Post v1: stok masuk + jurnal pembelian. Balasan = [ok, pesan].
    public static function post(PvReceipt $pv): array
    {
        $r = PvPostingService::post($pv->refresh()->load('lines'));

        return [$r['ok'], $r['ok'] ? trim(t('Receipt posted — stock is in.').' '.($r['journal_warning'] ?? '').' '.($r['stock_warning'] ?? '')) : $r['message']];
    }

    // Cancel = pembalik SPB POSTED (PvPostingService::cancel): stok keluar lagi, PO dihitung ulang, jurnal dibalik.
    public static function cancel(PvReceipt $pv, string $reason): array
    {
        $r = PvPostingService::cancel($pv->refresh()->load('lines'), $reason);

        return [$r['ok'], $r['ok'] ? trim(t('Receipt cancelled — stock taken out again, PO recalculated.').' '.($r['journal_warning'] ?? '').' '.($r['stock_warning'] ?? '')) : $r['message']];
    }

    public static function delete(PvReceipt $pv): void
    {
        abort_unless($pv->status === 'DRAFT', 422, t('Only a DRAFT receipt can be deleted.'));
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
