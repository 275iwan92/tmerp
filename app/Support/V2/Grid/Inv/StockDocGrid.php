<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\MatRaw;
use App\Models\Master\ProdPart;
use App\Models\Master\ProdProduct;
use App\Models\Master\WhWarehouse;
use App\Services\Inventory\StockDocEntryService;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/*
 * REMARK
 * Tujuan       : Induk grid dokumen stok (Stock In / Out / Return / Adjustment per kelas RM, FG, SFG): daftar dokumen dengan gudang, kategori, sumber, referensi dan status; Open dan + New Record membuka form v2 inv-stock-doc-form bila jenis dan kelasnya sudah punya (Stock In, Stock Out RM / SFG, Return RM / SFG), selain itu halaman v1.
 * Sumber hulu  : tm2inv.rm_stock / fg_stock / sfg_stock + baris, tm2mst.wh_warehouse dan item master (query terpisah).
 * Input        : Pencarian, statistik, filter Category / Source / Warehouse / Status.
 * Output       : Baris grid.
 * Dipakai oleh : Menu Inventory > Stock (v2:inv-*-stock-in / -out / -return / -adj), layar inv-stock-doc-form (kembali ke daftar).
 * Status       : Aktif. Stock In, Stock Out dan Return sudah v2 (halaman v1 dipindah ke tmerp_unused 2026-10-19); Adjustment masih membuka halaman v1 (Master T5 berlanjut).
 */

// Dokumen stok (v1: pages::inventory.stock-in/out/return/adjustment {category}). Satu turunan = satu kelas
// stok (RM/FG/SFG) x satu jenis dokumen. Stock In juga menampilkan dokumen otomatis dari SPB (ref PV), bukan
// hanya MANUAL seperti daftar v1 (backlog04: "layar Stock In v2 untuk melihat dokumen Stock In hasil SPB").
abstract class StockDocGrid extends DbGrid
{
    protected const MODELS = ['RM' => RmStock::class, 'FG' => FgStock::class, 'SFG' => SfgStock::class];

    protected const CATEGORY_LABELS = ['RM' => 'Raw Material', 'FG' => 'Finished Goods', 'SFG' => 'Semi Finished Goods'];

    // kind => [slug part, title, v1 route]
    protected const KINDS = [
        'in' => ['stock-in', 'Stock In', 'inv.stock-in.index'],
        'out' => ['stock-out', 'Stock Out', 'inv.stock-out.index'],
        'return' => ['stock-return', 'Return from Production', 'inv.stock-return.index'],
        'adj' => ['stock-adj', 'Stock Adjustment & Physical Count', 'inv.stock-adj.index'],
    ];

    // Jenis dokumen yang sudah punya form v2 (StockDocEntryService::KINDS menentukan kelas yang boleh; FG tidak punya Stock Out dan Return): Stock In, Stock Out, Return. Jenis lain masih ke halaman v1 sampai dipindah.
    protected const V2_FORM = 'inv-stock-doc-form';

    protected const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'info', 'CONFIRMED' => 'info',
        'POSTED' => 'success', 'REJECTED' => 'danger', 'CANCELLED' => 'danger'];

    private array $warehouses = [];

    // Nama item per baris dokumen (RM = bahan, FG = product, SFG = part), kunci = id item.
    private array $itemNames = [];

    abstract protected function category(): string;

    abstract protected function kind(): string;

    public function slug(): string
    {
        return 'inv-'.strtolower($this->category()).'-'.self::KINDS[$this->kind()][0];
    }

    public function title(): string
    {
        return self::KINDS[$this->kind()][1].' '.$this->category();
    }

    public function module(): string
    {
        return 'INV';
    }

    public function moduleLabel(): string
    {
        return 'Inventory';
    }

    public function group(): string
    {
        return self::CATEGORY_LABELS[$this->category()];
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function icon(): string
    {
        return match ($this->kind()) {
            'in' => 'fa-solid fa-arrow-right-to-bracket',
            'out' => 'fa-solid fa-arrow-right-from-bracket',
            'return' => 'fa-solid fa-rotate-left',
            default => 'fa-solid fa-scale-balanced',
        };
    }

    // v1 menolak FG di Stock Out/Return (barang jadi keluar lewat DO): grid FG itu hanya-baca.
    protected function v1Route(): ?string
    {
        return $this->category() === 'FG' && in_array($this->kind(), ['out', 'return'], true) ? null : self::KINDS[$this->kind()][2];
    }

    private function hasV2Form(): bool
    {
        return StockDocEntryService::supports($this->kind(), $this->category());
    }

    protected function canCreate(): bool
    {
        return $this->hasV2Form() || parent::canCreate();
    }

    public function openUrl(): ?string
    {
        if ($this->hasV2Form()) {
            return route('v2.screen', self::V2_FORM).'?kind='.$this->kind().'&class='.$this->category().'&id={id}';
        }

        return $this->v1Route() ? route($this->v1Route(), ['category' => $this->category()]).'?open={id}' : null;
    }

    public function newUrl(): ?string
    {
        if ($this->hasV2Form()) {
            return route('v2.screen', self::V2_FORM).'?kind='.$this->kind().'&class='.$this->category().'&new=1';
        }

        return $this->v1Route() ? route($this->v1Route(), ['category' => $this->category()]).'?new=1' : null;
    }

    protected function models(): array
    {
        return [self::MODELS[$this->category()], WhWarehouse::class];
    }

    public function search(): string
    {
        return 'Search doc no, reference, notes...';
    }

    protected function query(): Builder
    {
        $q = (self::MODELS[$this->category()])::query()->with('lines')->orderByDesc('doc_date')->orderByDesc('doc_id');

        return match ($this->kind()) {
            'in' => $q->where('doc_type', 1)->where('doc_cat', '<>', 'RETURN'),
            'out' => $q->where('doc_type', -1)->whereNotIn('doc_cat', ['AD', 'OP']),
            'return' => $q->where('doc_cat', 'RETURN'),
            default => $q->whereIn('doc_cat', ['AD', 'OP']),
        };
    }

    protected function prefetch(Collection $items): void
    {
        $this->warehouses = WhWarehouse::query()->whereKey($items->pluck('warehouse_id')->filter()->unique()->all())
            ->get()->pluck('warehouse_name', (new WhWarehouse)->getKeyName())->all();
        // Lintas-DB (tm2inv -> tm2mst): satu query nama item untuk semua baris halaman ini.
        $ids = $items->flatMap(fn ($d) => $d->lines->pluck($this->itemColumn()))->filter()->unique()->all();
        $this->itemNames = match ($this->category()) {
            'RM' => MatRaw::query()->whereKey($ids)->get(['material_id', 'material_code', 'material_name'])
                ->mapWithKeys(fn ($m) => [$m->material_id => [$m->material_code, $m->material_name]])->all(),
            'FG' => ProdProduct::query()->whereKey($ids)->get(['id_product', 'product_code', 'product_name'])
                ->mapWithKeys(fn ($m) => [$m->id_product => [$m->product_code, $m->product_name]])->all(),
            default => ProdPart::query()->whereKey($ids)->get(['id_part', 'part_code', 'part_name'])
                ->mapWithKeys(fn ($m) => [$m->id_part => [$m->part_code, $m->part_name]])->all(),
        };
    }

    private function itemColumn(): string
    {
        return ['RM' => 'material_id', 'FG' => 'product_id', 'SFG' => 'parts_id'][$this->category()];
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'Doc No. / Warehouse', 'type' => 'doc', 'party' => 'warehouse'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_cat', 'label' => 'Category'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'ref_no', 'label' => 'Reference'],
            ['key' => 'notes', 'label' => 'Notes'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'doc_no' => $m->doc_no, 'doc_date' => $m->doc_date?->toDateString(), 'doc_cat' => $m->doc_cat,
            'source' => $m->ref_doc_type ?: 'MANUAL', 'ref_no' => $m->ref_doc_no, 'warehouse' => $this->warehouses[$m->warehouse_id] ?? null,
            'lines' => $m->lines->count(), 'notes' => $m->notes, 'status' => $m->flow_status ?: 'DRAFT',
            'items' => $m->lines->map(fn ($l) => ['code' => $this->itemNames[$l->{$this->itemColumn()}][0] ?? null, 'name' => $this->itemNames[$l->{$this->itemColumn()}][1] ?? null,
                'qty' => (float) $l->qty, 'unit' => $l->uom_code, 'lot' => $l->lot_no])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'lot', 'label' => 'Lot No.'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Waiting', 'where' => ['status' => ['SUBMITTED', 'CONFIRMED', 'APPROVED']], 'tone' => 'warn'],
            ['label' => 'Posted', 'where' => ['status' => 'POSTED'], 'tone' => 'success'],
        ];
    }

    public function filters(): array
    {
        return ['doc_cat' => 'Category', 'source' => 'Source', 'warehouse' => 'Warehouse', 'status' => 'Status'];
    }
}
