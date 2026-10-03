<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PqLine;
use App\Models\Pur\PqQuotation;
use App\Models\Pur\PrRequisition;
use App\Support\V2\Data\PurchaseQuotation;
use App\Support\V2\Grid\DbGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Daftar Purchase Quotation (Grid Transactional) di layar v2 purchase-quotation: Open / + New Record = form di layar yang sama.
class PurchaseQuotationGrid extends DbGrid
{
    private array $suppliers = [];

    private array $requisitions = [];

    public function slug(): string
    {
        return 'purchase-quotation';
    }

    public function title(): string
    {
        return 'Purchase Quotation (PQ)';
    }

    public function module(): string
    {
        return 'PROC';
    }

    public function moduleLabel(): string
    {
        return 'Procurement';
    }

    public function family(): string
    {
        return self::TRANSACTIONAL;
    }

    public function group(): string
    {
        return 'Order';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-import';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function models(): array
    {
        return [PqQuotation::class, PqLine::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search PQ no., supplier, PR, file name...';
    }

    protected function query(): Builder
    {
        return PqQuotation::query()->with('lines')->orderByDesc('pq_id');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('i_supplier_id')->filter()->unique()->all())->pluck('supplier_name', 'id_supplier')->all();
        $this->requisitions = PrRequisition::query()->whereKey($items->pluck('i_pr_id')->filter()->unique()->all())->pluck('doc_no', 'pr_id')->all();
    }

    public function columns(): array
    {
        // Nilai 'read' sudah diterjemahkan di row(), jadi kunci tone-nya ikut diterjemahkan.
        $readTones = collect(PurchaseQuotation::READ_TONES)->mapWithKeys(fn ($tone, $label) => [t($label) => $tone])->all();

        return [
            ['key' => 'no', 'label' => 'PQ No. / Supplier', 'type' => 'doc', 'party' => 'supplier'],
            ['key' => 'pr', 'label' => 'Reference PR'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
            ['key' => 'file', 'label' => 'File'],
            ['key' => 'read', 'label' => 'Reading Result', 'type' => 'badge', 'tones' => $readTones],
            ['key' => 'total', 'label' => 'Total (IDR)', 'type' => 'money', 'sum' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => PurchaseQuotation::STATUS_TONES],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'no' => $m->doc_no ?: '(draft #'.$m->pq_id.')',
            'supplier' => $this->suppliers[$m->i_supplier_id] ?? (($m->i_supplier_text ?: '—').' '.t('(not matched yet)')),
            'pr' => $this->requisitions[$m->i_pr_id] ?? '—', 'date' => $m->i_doc_date?->toDateString(), 'valid' => $m->i_quotation_valid_until?->toDateString(),
            'file' => $m->source_name ?? '—', 'read' => isset(PurchaseQuotation::READ_LABELS[$m->extract_status]) ? t(PurchaseQuotation::READ_LABELS[$m->extract_status]) : '—',
            'total' => (float) $m->c_total_idr, 'status' => $m->status,
            'items' => $m->lines->map(fn (PqLine $l) => ['name' => $l->i_description ?: $l->i_source_text, 'qty' => (float) $l->i_qty, 'unit' => $l->i_uom,
                'lead' => $l->i_lead_time_day, 'note' => $l->i_notes])->all(),
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Item', 'columns' => [
            ['key' => 'name', 'label' => 'Item Name', 'strong' => true],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'lead', 'label' => 'Lead Time (days)', 'type' => 'number'],
            ['key' => 'note', 'label' => 'Notes'],
        ]];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => 'Needs check', 'where' => ['status' => 'DRAFT', 'read' => [t('Needs check'), t('Fill in by hand'), t('Could not be read')]], 'tone' => 'warn'],
            ['label' => 'Confirmed', 'where' => ['status' => 'CONFIRMED'], 'tone' => 'success'],
            ['label' => 'Closed (ordered)', 'where' => ['status' => 'CLOSED'], 'tone' => 'info'],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'pr' => 'Reference PR', 'status' => 'Status'];
    }
}
