<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvShortageRequest;
use App\Models\Master\CustCustomer;
use App\Services\Pur\ShortageRequestService;

// Supplier Shortage Request: bahan yang kurang sejak awal diminta dikirim (supplier / subkon / customer). Klik baris = buka permintaan.
class ShortageListGrid extends QualityDocGrid
{
    public const TONES = ['Draft' => 'neutral', 'Confirmed' => 'info', 'Sent' => 'warn', 'Received' => 'success', 'Refused' => 'danger', 'Closed' => 'neutral'];

    public function slug(): string
    {
        return 'supplier-shortage';
    }

    public function title(): string
    {
        return 'Shortage Request';
    }

    public function icon(): string
    {
        return 'fa-solid fa-boxes-packing';
    }

    public function guide(): ?string
    {
        return 'Material that was short from the start: after production, output plus standard waste is less than the material issued. The head of the warehouse recounts, PPIC confirms, Purchasing asks the supplier to send the shortfall. Waste above the standard is a usage variance, not a shortage.';
    }

    public function openUrl(): ?string
    {
        return $this->url().'?id={id}';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_shortage_request', 'tm2mst.sup_supplier', 'tm2mst.cust_customer'];
    }

    protected function listRows(): array
    {
        $rows = InvShortageRequest::query()->orderByDesc('id_shortage')->limit($this->limit)->get();
        $suppliers = $this->supplierNames($rows->pluck('supplier_id'));
        $customers = CustCustomer::query()->whereKey($rows->pluck('customer_id')->filter()->unique()->all())->pluck('customer_name', 'id_customer');

        return $rows->map(fn (InvShortageRequest $r) => ['id' => $r->id_shortage, 'no' => $r->request_no, 'status' => ShortageRequestService::STATUSES[$r->status] ?? $r->status,
            'source' => ShortageRequestService::SOURCES[$r->source_type] ?? $r->source_type, 'party' => $r->source_type === 'CUSTOMER' ? ($customers[$r->customer_id] ?? '') : ($suppliers[$r->supplier_id] ?? ''),
            'wo' => (string) $r->wo_no, 'material' => (string) $r->material_name, 'code' => (string) $r->material_code, 'candidate' => (float) $r->qty_candidate, 'short' => (float) $r->qty_short,
            'uom' => (string) $r->uom_code, 'need' => $r->need_date?->toDateString(), 'window' => $r->window_until?->toDateString(), 'debit' => $r->debit_note_required ? 'Debit note' : ''])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Request No.', 'width' => '120px'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::TONES],
            ['key' => 'source', 'label' => 'Source', 'width' => '110px'],
            ['key' => 'party', 'label' => 'Supplier / Customer'],
            ['key' => 'wo', 'label' => 'Work Order', 'width' => '120px'],
            ['key' => 'material', 'label' => 'Material', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'candidate', 'label' => 'Suspected', 'type' => 'number', 'decimals' => 2],
            ['key' => 'short', 'label' => 'Proven Short', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'uom', 'label' => 'UoM', 'width' => '70px'],
            ['key' => 'need', 'label' => 'Needed By', 'type' => 'date'],
            ['key' => 'window', 'label' => 'Send Until', 'type' => 'date'],
            ['key' => 'debit', 'label' => 'Debit Note', 'type' => 'badge', 'tones' => ['Debit note' => 'danger']],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Draft', 'where' => ['status' => 'Draft'], 'tone' => 'neutral'], ['label' => 'Confirmed', 'where' => ['status' => 'Confirmed'], 'tone' => 'info'],
            ['label' => 'Sent', 'where' => ['status' => 'Sent'], 'tone' => 'warn'], ['label' => 'Refused', 'where' => ['status' => 'Refused'], 'tone' => 'danger']];
    }
}
