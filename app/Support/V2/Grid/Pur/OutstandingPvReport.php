<?php

namespace App\Support\V2\Grid\Pur;

use App\Models\Master\SupSupplier;
use App\Models\Pur\PvReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

// Outstanding PV (v1: pages::pur.report.outstanding-pv): SPB POSTED yang dokumennya belum diserahkan ke Finance.
// Tandai "sudah diserahkan" tetap di layar v1 (aksi, bukan laporan).
class OutstandingPvReport extends PurReportGrid
{
    private array $suppliers = [];

    public function slug(): string
    {
        return 'pur-rpt-outstanding-pv';
    }

    public function title(): string
    {
        return 'Outstanding PV';
    }

    public function group(): string
    {
        return 'Receive › Report';
    }

    protected function models(): array
    {
        return [PvReceipt::class, SupSupplier::class];
    }

    public function search(): string
    {
        return 'Search PV no, supplier, PO...';
    }

    protected function query(): Builder
    {
        return PvReceipt::query()->where('status', 'POSTED')->where('is_handed_over', false)->orderBy('i_doc_date');
    }

    protected function prefetch(Collection $items): void
    {
        $this->suppliers = SupSupplier::query()->whereKey($items->pluck('i_supplier_id')->filter()->unique()->all())
            ->pluck('supplier_name', 'id_supplier')->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'doc_no', 'label' => 'PV No'],
            ['key' => 'doc_date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'type', 'label' => 'Receipt Type'],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'po_list', 'label' => 'PO'],
            ['key' => 'supplier_do', 'label' => 'Supplier DO'],
            ['key' => 'days', 'label' => 'Days Waiting', 'type' => 'number'],
            ['key' => 'age', 'label' => 'Age'],
        ];
    }

    protected function row(Model $m): array
    {
        return [
            'doc_no' => $m->doc_no, 'doc_date' => $m->i_doc_date?->toDateString(), 'type' => $m->i_receipt_type ?: 'PO',
            'supplier' => $this->suppliers[$m->i_supplier_id] ?? null, 'po_list' => $m->c_po_doc_list, 'supplier_do' => $m->i_supplier_do_no,
            'days' => $m->i_doc_date ? (int) $m->i_doc_date->diffInDays(now(), true) : null, 'age' => $this->ageBucket($m->i_doc_date),
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => '0-7 days', 'where' => ['age' => '0-7 days'], 'tone' => 'info'],
            ['label' => '8-30 days', 'where' => ['age' => '8-30 days'], 'tone' => 'warn'],
            ['label' => '> 30 days', 'where' => ['age' => '> 30 days'], 'tone' => 'danger'],
        ];
    }

    public function filters(): array
    {
        return ['supplier' => 'Supplier', 'type' => 'Receipt Type', 'age' => 'Age'];
    }
}
