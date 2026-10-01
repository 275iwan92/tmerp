<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Acc\FinAccount;
use App\Models\Master\ProdCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

// Master Kategori Produk (tm2mst.prod_category): dulu hanya dropdown baca di Master Product -- tidak ada layar isinya
// (backlog07 Grup A). Akun persediaan & HPP bawaan dipakai rantai resolve akun: product -> kategori -> default sistem
// (ProdProduct::resolveStockAccountId / resolveCogsAccountId). Akun = tm2acc.fin_account (lintas-DB, divalidasi aplikasi).
class ProductCategoryLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-product-category';
    }

    public function title(): string
    {
        return 'Product Category';
    }

    public function icon(): string
    {
        return 'fa-solid fa-boxes-stacked';
    }

    public function guide(): ?string
    {
        return 'Categories chosen in Master Product. Stock Account and COGS Account are the defaults for products of the category; '
            .'a product can override them, and an empty cell falls back to the system default.';
    }

    protected function models(): array
    {
        return [ProdCategory::class, FinAccount::class];
    }

    protected function query(): Builder
    {
        return ProdCategory::query()->orderBy('category_name');
    }

    // Akun postable aktif jenis tertentu + akun yang sudah terpakai di kategori (supaya pilihan lama tetap terbaca).
    private function accounts(array $types, string $column): array
    {
        return FinAccount::query()->where(fn ($q) => $q->where('is_postable', true)->where('activate', true)->whereIn('account_type', $types))
            ->orWhereIn('account_id', ProdCategory::query()->whereNotNull($column)->pluck($column)->all())
            ->orderBy('account_code')->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn (FinAccount $a) => [$a->account_id => $a->account_code.' - '.$a->account_name])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'category_name', 'label' => 'Category Name', 'required' => true, 'unique' => true, 'width' => '260px'],
            ['key' => 'description', 'label' => 'Description', 'width' => '320px'],
            ['key' => 'default_stock_account_id', 'label' => 'Stock Account', 'type' => 'select', 'options' => $this->accounts(['ASSET'], 'default_stock_account_id'), 'width' => '320px'],
            ['key' => 'default_cogs_account_id', 'label' => 'COGS Account', 'type' => 'select', 'options' => $this->accounts(['COGS'], 'default_cogs_account_id'), 'width' => '320px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function rules(): array
    {
        return ['category_name' => ['string', 'max:200'], 'description' => ['string', 'max:2000'],
            'default_stock_account_id' => [Rule::exists(FinAccount::class, 'account_id')], 'default_cogs_account_id' => [Rule::exists(FinAccount::class, 'account_id')]];
    }
}
