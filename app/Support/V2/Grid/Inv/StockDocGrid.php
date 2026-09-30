<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\FgStock;
use App\Models\Inventory\RmStock;
use App\Models\Inventory\SfgStock;
use App\Models\Master\WhWarehouse;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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

    protected const STATUS_TONES = ['DRAFT' => 'neutral', 'SUBMITTED' => 'warn', 'APPROVED' => 'info', 'CONFIRMED' => 'info',
        'POSTED' => 'success', 'REJECTED' => 'danger', 'CANCELLED' => 'danger'];

    private array $warehouses = [];

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

    public function openUrl(): ?string
    {
        return $this->v1Route() ? route($this->v1Route(), ['category' => $this->category()]).'?open={id}' : null;
    }

    public function newUrl(): ?string
    {
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
        $q = (self::MODELS[$this->category()])::query()->withCount('lines')->orderByDesc('doc_date')->orderByDesc('doc_id');

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
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'Doc No'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'doc_cat', 'label' => 'Category'],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'ref_no', 'label' => 'Reference'],
            ['key' => 'warehouse', 'label' => 'Warehouse'],
            ['key' => 'lines', 'label' => 'Lines', 'type' => 'number'],
            ['key' => 'notes', 'label' => 'Notes'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'doc_no' => $m->doc_no, 'doc_date' => $m->doc_date?->toDateString(), 'doc_cat' => $m->doc_cat,
            'source' => $m->ref_doc_type ?: 'MANUAL', 'ref_no' => $m->ref_doc_no, 'warehouse' => $this->warehouses[$m->warehouse_id] ?? null,
            'lines' => (int) $m->lines_count, 'notes' => $m->notes, 'status' => $m->flow_status ?: 'DRAFT',
        ];
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
