<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvSupplierClaim;

// Supplier Claims: klaim yang dibuka otomatis saat batch ditolak (Q-09). Klik baris = buka batch-nya (status klaim diubah di sana).
class SupplierClaimReport extends QualityDocGrid
{
    public const TONES = ['Open' => 'danger', 'Sent' => 'warn', 'Settled' => 'success', 'Dropped' => 'neutral'];

    public const STATUSES = ['OPEN' => 'Open', 'SENT' => 'Sent', 'SETTLED' => 'Settled', 'DROPPED' => 'Dropped'];

    public function slug(): string
    {
        return 'supplier-claims';
    }

    public function title(): string
    {
        return 'Supplier Claims';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-circle-exclamation';
    }

    public function guide(): ?string
    {
        return 'Claims to suppliers for batches that were rejected after receipt. A claim is opened by the rejection; mark it Sent, Settled or Dropped on the batch page.';
    }

    public function openUrl(): ?string
    {
        return route('v2.screen', 'conditional-stock').'?id={id}';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_supplier_claim', 'tm2inv.inv_batch', 'tm2mst.sup_supplier'];
    }

    protected function listRows(): array
    {
        $claims = InvSupplierClaim::query()->orderByDesc('id_claim')->limit($this->limit)->get();
        $suppliers = $this->supplierNames($claims->pluck('supplier_id'));
        $batches = InvBatch::query()->whereKey($claims->pluck('batch_id')->unique()->all())->get()->keyBy('id_batch');

        return $claims->map(fn (InvSupplierClaim $c) => ['id' => $c->batch_id, 'no' => $c->claim_no, 'status' => self::STATUSES[$c->status] ?? $c->status, 'supplier' => $suppliers[$c->supplier_id] ?? '',
            'batch' => $batches[$c->batch_id]->batch_no ?? '', 'item' => (string) ($batches[$c->batch_id]->item_name ?? ''), 'item_code' => (string) ($batches[$c->batch_id]->item_code ?? ''),
            'qty' => (float) $c->qty_claimed, 'uom' => (string) $c->uom_code, 'reason' => $c->reason, 'at' => $this->dt($c->created_at)])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Claim No.', 'width' => '120px'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => self::TONES],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'batch', 'label' => 'Batch', 'width' => '120px'],
            ['key' => 'item', 'label' => 'Item', 'type' => 'identity', 'code' => 'item_code', 'avatar' => false],
            ['key' => 'qty', 'label' => 'Qty Claimed', 'type' => 'number', 'decimals' => 2, 'sum' => true],
            ['key' => 'uom', 'label' => 'UoM', 'width' => '70px'],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'at', 'label' => 'Opened At'],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Open', 'where' => ['status' => 'Open'], 'tone' => 'danger'], ['label' => 'Sent', 'where' => ['status' => 'Sent'], 'tone' => 'warn'],
            ['label' => 'Settled', 'where' => ['status' => 'Settled'], 'tone' => 'success']];
    }
}
