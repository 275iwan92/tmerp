<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\WhLoc;
use App\Models\Master\WhWarehouse;
use App\Support\V2\Src;

// Stock Opname: hasil hitung fisik dan penyesuaian stok (dokumen Stock Adjustment & Physical Count, doc_cat OP / AD) satu
// periode, satu baris per item per dokumen -- qty sistem, qty fisik, selisih (Gain / Loss / Match) dan nilainya. Semua
// status dokumen tampil (Draft sampai Posted) supaya hitungan yang belum disetujui kelihatan; yang sudah mengubah stok =
// Posted. Rekap per bulan dari yang sudah Posted = Stock Loss & Gain.
class StockOpnameReport extends AllStockReport
{
    // kelas => [kepala dokumen, kolom item di baris]
    private const DOCS = ['RM' => [RmStock::class, 'material_id'], 'SFG' => [SfgStock::class, 'parts_id'], 'FG' => [FgStock::class, 'product_id']];

    // doc_cat kepala dokumen (data lama memakai beberapa ejaan) => jenis.
    public const KINDS = ['OP' => 'Physical count', 'OPN' => 'Physical count', 'OPNAME' => 'Physical count', 'AD' => 'Adjustment', 'ADJ' => 'Adjustment', 'ADJUST' => 'Adjustment'];

    private const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'info', 'CONFIRMED' => 'info', 'POSTED' => 'success', 'REJECTED' => 'danger', 'CANCELLED' => 'danger'];

    public function slug(): string
    {
        return 'inv-rpt-opname';
    }

    public function title(): string
    {
        return 'Stock Opname';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-check';
    }

    public function search(): string
    {
        return 'Search doc no, item, bin, reason...';
    }

    public function guide(): ?string
    {
        return 'Physical count and adjustment documents of the period, one row per item: system quantity, counted quantity and the difference. Gain = more than the system, Loss = less. Only Posted documents have changed the stock; monthly totals are in Stock Loss & Gain.';
    }

    public function groupBy(): ?string
    {
        return 'doc_no';
    }

    protected function sourceText(): string
    {
        return Src::of('tm2inv.rm_stock', 'tm2inv.rm_stock_d', 'tm2inv.sfg_stock', 'tm2inv.sfg_stock_d', 'tm2inv.fg_stock', 'tm2inv.fg_stock_d', 'tm2mst.wh_warehouse', 'tm2mst.wh_loc');
    }

    public function rows(): array
    {
        [$from, $to] = $this->period();
        $docs = [];
        foreach (self::DOCS as $class => [$model, $itemColumn]) {
            foreach ($model::query()->with('lines')->whereIn('doc_cat', array_keys(self::KINDS))->whereBetween('doc_date', [$from->startOfDay(), $to->endOfDay()])
                ->orderBy('doc_date')->orderBy('doc_id')->get() as $doc) {
                $docs[] = [$class, $itemColumn, $doc];
            }
        }
        $lines = fn () => collect($docs)->flatMap(fn (array $d) => $d[2]->lines->map(fn ($l) => ['class' => $d[0], 'item_id' => (int) $l->{$d[1]}, 'location_id' => (int) $l->location_id]));
        $this->loadMeta($lines());
        // Lintas-DB (tm2inv -> tm2mst): gudang dan bin dalam dua query, dicocokkan di PHP.
        $warehouses = WhWarehouse::query()->whereKey(collect($docs)->map(fn (array $d) => $d[2]->warehouse_id)->filter()->unique()->all())->get()->keyBy('id_warehouse');
        $bins = WhLoc::query()->whereKey($lines()->pluck('location_id')->filter()->unique()->all())->get()->keyBy('location_id');

        $rows = [];
        foreach ($docs as [$class, $itemColumn, $doc]) {
            $wh = $warehouses->get($doc->warehouse_id);
            foreach ($doc->lines as $l) {
                $meta = $this->meta($class, (int) $l->{$itemColumn});
                $diff = (float) $l->qty;
                $rows[] = ['id' => $class.'-'.$l->getKey(), 'doc_date' => $doc->doc_date?->toDateString(), 'doc_no' => $doc->doc_no ?: 'Draft #'.$doc->getKey(),
                    'kind' => self::KINDS[$doc->doc_cat], 'status' => $doc->stat_cancel ? 'CANCELLED' : ($doc->flow_status ?: 'DRAFT'), 'class' => self::CLASSES[$class],
                    'item' => $meta['name'], 'item_code' => $meta['code'], 'warehouse' => $wh ? $wh->warehouse_code.' · '.$wh->warehouse_name : null,
                    'bin' => $bins->get($l->location_id)?->location_code, 'system' => $l->qty_system === null ? null : (float) $l->qty_system,
                    'physical' => $l->qty_physical === null ? null : (float) $l->qty_physical, 'diff' => $diff, 'unit' => $l->uom_code,
                    'unit_price' => (float) $l->unit_price, 'value' => $diff * (float) $l->unit_price, 'result' => $diff > 0 ? 'Gain' : ($diff < 0 ? 'Loss' : 'Match'),
                    'reason' => $doc->reason_code, 'officer' => $doc->opn_officer, 'witness' => $doc->opn_witness, 'notes' => $doc->notes];
            }
        }

        return $rows;
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_no', 'label' => 'Doc No.'],
            ['key' => 'kind', 'label' => 'Type', 'type' => 'badge', 'tones' => ['Physical count' => 'info', 'Adjustment' => 'warn']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
            ['key' => 'class', 'label' => 'Class', 'visible' => false],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'bin', 'label' => 'Bin'],
            ['key' => 'system', 'label' => 'System Qty', 'type' => 'number'],
            ['key' => 'physical', 'label' => 'Counted Qty', 'type' => 'number'],
            ['key' => 'diff', 'label' => 'Difference', 'type' => 'number', 'sum' => true],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'unit_price', 'label' => 'Unit Price', 'type' => 'money', 'visible' => false],
            ['key' => 'value', 'label' => 'Difference Value', 'type' => 'money', 'sum' => true],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['Gain' => 'success', 'Loss' => 'danger', 'Match' => 'neutral']],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'officer', 'label' => 'Counted By', 'visible' => false],
            ['key' => 'witness', 'label' => 'Witness', 'visible' => false],
            ['key' => 'notes', 'label' => 'Notes', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Loss', 'where' => ['result' => 'Loss'], 'tone' => 'danger'],
            ['label' => 'Gain', 'where' => ['result' => 'Gain'], 'tone' => 'success'],
            ['label' => 'Match', 'where' => ['result' => 'Match'], 'tone' => 'neutral'],
            ['label' => 'Posted', 'where' => ['status' => 'POSTED'], 'tone' => 'success'],
            ['label' => 'Not posted yet', 'where' => ['status' => ['DRAFT', 'SUBMITTED', 'APPROVED', 'CONFIRMED']], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['kind' => 'Type', 'status' => 'Status', 'class' => 'Class', 'warehouse' => 'Warehouse', 'result' => 'Result', 'reason' => 'Reason'];
    }

    public function groups(): array
    {
        return ['doc_no' => 'Document', 'warehouse' => 'Warehouse', 'item' => 'Item', 'result' => 'Result'];
    }

    public function printSummary(): array
    {
        return [['label' => t('Difference Value'), 'sum' => 'value', 'signed' => true]];
    }

    public function printSignatures(): array
    {
        return [['Counted By', 'Warehouse'], ['Witness', 'Accounting'], ['Approved By', 'Head of Warehouse']];
    }
}
