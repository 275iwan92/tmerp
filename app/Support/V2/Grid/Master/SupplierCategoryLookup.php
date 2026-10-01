<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SupCategory;
use Illuminate\Database\Eloquent\Builder;

// Master Kategori Supplier (v1: pages::master.supplier-category). Supplier = semua vendor, dibedakan kategori
// (BANK = lookup rekening gaji pegawai, EKSPEDISI = vendor angkut, dst); sup_supplier.category menyimpan kodenya.
class SupplierCategoryLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-category';
    }

    public function title(): string
    {
        return 'Supplier Category';
    }

    public function icon(): string
    {
        return 'fa-solid fa-tags';
    }

    public function guide(): ?string
    {
        return 'Every vendor is a supplier, told apart by category (BANK = banks for payroll accounts, EKSPEDISI = forwarders). '
            .'A saved code cannot be changed because suppliers refer to it; deactivate it instead.';
    }

    protected function query(): Builder
    {
        return SupCategory::query()->orderBy('category_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'category_code', 'label' => 'Category Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '220px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '420px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        return $this->upper($in, 'category_code');
    }

    protected function rules(): array
    {
        return ['category_code' => ['string', 'max:50'], 'description' => ['string', 'max:150']];
    }
}
