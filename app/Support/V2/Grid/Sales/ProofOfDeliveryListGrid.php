<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\ProofOfDelivery;
use App\Support\V2\Grid\DocListGrid;

// Daftar Proof of Delivery (Grid Transactional) di layar v2 proof-of-delivery: Open / + New Record = form di layar yang sama.
class ProofOfDeliveryListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'proof-of-delivery';
    }

    public function title(): string
    {
        return 'Proof of Delivery';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'pod-list';
    }

    protected function listRows(): array
    {
        return ProofOfDelivery::rows();
    }

    protected function sources(): array
    {
        return ['tm2sdi.do_delivery (CONFIRMED / SHIPPED)', 'tm2sdi.do_delivery_pod'];
    }

    public function search(): string
    {
        return 'Search delivery note no., customer...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'Delivery Note No. / Customer', 'type' => 'doc', 'party' => 'customer'],
            ['key' => 'promise', 'label' => 'Promised date', 'type' => 'date'],
            ['key' => 'address', 'label' => 'Destination'],
            ['key' => 'arrived', 'label' => 'Arrived'],
            ['key' => 'result', 'label' => 'Result'],
            ['key' => 'state', 'label' => 'Promise', 'type' => 'badge', 'tones' => ProofOfDelivery::STATE_TONES],
        ];
    }

    public function detail(): array
    {
        return ['key' => 'items', 'title' => 'Product', 'columns' => [
            ['key' => 'product', 'label' => 'Product', 'strong' => true],
            ['key' => 'co', 'label' => 'CO No.'],
            ['key' => 'qty', 'label' => 'Qty', 'type' => 'number'],
            ['key' => 'unit', 'label' => 'Unit'],
        ]];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Waiting', 'where' => ['state' => 'Waiting'], 'tone' => 'warn'], ['label' => 'Kept', 'where' => ['state' => 'Kept'], 'tone' => 'success'],
            ['label' => 'Missed', 'where' => ['state' => 'Missed'], 'tone' => 'danger'], ['label' => 'Retry', 'where' => ['state' => 'Retry'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'state' => 'Promise'];
    }
}
