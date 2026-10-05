<?php

namespace App\Support\V2\Grid\Sales;

use App\Support\V2\Data\ProductMaster;
use App\Support\V2\Grid\DocListGrid;

// Daftar Master Product (layar v2 master-product): turunan DocListGrid, kolom dan saringannya sama dengan sebelumnya.
class ProductMasterListGrid extends DocListGrid
{
    public function slug(): string
    {
        return 'master-product';
    }

    public function title(): string
    {
        return 'Master Product';
    }

    public function module(): string
    {
        return 'SALES';
    }

    protected function listId(): string
    {
        return 'master-product';
    }

    protected function listRows(): array
    {
        return ProductMaster::rows();
    }

    protected function sources(): array
    {
        return ['tm2mst.prod_product', 'tm2mst.prod_part', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc', 'tm2mst.cust_customer'];
    }

    public function search(): string
    {
        return 'Search product name, design no., customer...';
    }

    public function newUrl(): ?string
    {
        return null;
    }

    public function columns(): array
    {
        return [
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer (owner)'],
            ['key' => 'parts', 'label' => 'Part', 'type' => 'number'],
            ['key' => 'join', 'label' => 'Joint'],
            ['key' => 'revision', 'label' => 'Branch'],
            ['key' => 'acc_digital', 'label' => 'Digital ACC', 'type' => 'badge', 'tones' => [t('AVAILABLE') => 'success', t('NOT YET') => 'warn']],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ['key' => 'updated', 'label' => t('Changed'), 'type' => 'date', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [
            ['label' => t('All Products')],
            ['label' => 'Official (Quotation WIN)', 'where' => ['status' => 'ACTIVE'], 'tone' => 'success'],
            ['label' => 'Draft (ProDev)', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'],
            ['label' => t('No Digital ACC yet'), 'where' => ['acc_digital' => t('NOT YET'), 'status' => 'ACTIVE'], 'tone' => 'warn'],
        ];
    }

    public function filters(): array
    {
        return ['customer' => 'Customer', 'join' => 'Joint', 'status' => 'Status'];
    }
}
