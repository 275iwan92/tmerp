<?php

/*
 * REMARK
 * Tujuan       : Layar input harga kertas per supplier (pola Exchange Rate): supplier, jenis kertas, gramasi opsional, harga roll per kg/ton, biaya potong, harga ream per kg, dasar PPN, tanggal berlaku.
 * Sumber hulu  : Petugas Purchasing mengisi harga dari supplier; sup_supplier, mat_grammage.
 * Input        : Baris sup_paper_price.
 * Output       : Konstanta harga yang dibaca PaperPriceEngine; baris lama tetap sebagai riwayat.
 * Dipakai oleh : Menu Master Data > Supplier Paper Price (v2:md-supplier-paper-price).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatGrammage;
use App\Models\Master\SupPaperPrice;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SupplierPaperPriceLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-paper-price';
    }

    public function title(): string
    {
        return 'Supplier Paper Price';
    }

    public function icon(): string
    {
        return 'fa-solid fa-scroll';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Paper price per supplier, versioned by effective date like Exchange Rate. Roll price per kg or ton; ream price for standard sheet sizes. '
            .'An empty grammage applies to all grammages. Price basis: whether the price already includes PPN.';
    }

    protected function models(): array
    {
        return [SupPaperPrice::class, SupSupplier::class, MatGrammage::class];
    }

    protected function query(): Builder
    {
        return SupPaperPrice::query()->orderBy('supplier_id')->orderBy('paper_kind')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        $suppliers = SupSupplier::query()->where('activate', true)->orWhereIn('id_supplier', SupPaperPrice::query()->select('supplier_id'))
            ->orderBy('supplier_name')->get(['id_supplier', 'supplier_name'])->mapWithKeys(fn (SupSupplier $s) => [$s->id_supplier => $s->supplier_name])->all();
        $grammages = MatGrammage::query()->orderBy('grammage_value')->get(['id_grammage', 'grammage_value', 'paper_kind'])
            ->mapWithKeys(fn (MatGrammage $g) => [$g->id_grammage => rtrim(rtrim(number_format((float) $g->grammage_value, 2, '.', ''), '0'), '.').' gsm '.$g->paper_kind])->all();

        return [
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'required' => true, 'width' => '240px'],
            ['key' => 'paper_kind', 'label' => 'Paper kind', 'required' => true, 'width' => '110px'],
            ['key' => 'grammage_id', 'label' => 'Grammage', 'type' => 'select', 'options' => $grammages, 'width' => '150px'],
            ['key' => 'roll_price', 'label' => 'Roll price', 'type' => 'number', 'decimals' => 2, 'width' => '130px'],
            ['key' => 'roll_price_unit', 'label' => 'Per', 'type' => 'select', 'options' => ['KG' => 'kg', 'TON' => 'ton'], 'required' => true, 'width' => '80px'],
            ['key' => 'cut_cost_per_kg', 'label' => 'Cut cost / kg', 'type' => 'number', 'decimals' => 2, 'width' => '120px'],
            ['key' => 'sheet_price_per_kg', 'label' => 'Ream price / kg', 'type' => 'number', 'decimals' => 2, 'width' => '130px'],
            ['key' => 'price_basis', 'label' => 'Price basis', 'type' => 'select', 'options' => ['INCL' => 'Incl PPN', 'EXCL' => 'Excl PPN'], 'required' => true, 'width' => '110px'],
            ['key' => 'effective_date', 'label' => 'Effective', 'type' => 'date', 'required' => true, 'width' => '140px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['effective_date' => $m->effective_date?->toDateString()] + parent::row($m);
    }

    protected function prepare(array $in): array
    {
        return $this->upper($in, 'paper_kind');
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => [Rule::exists(SupSupplier::class, 'id_supplier')],
            'grammage_id' => ['nullable', Rule::exists(MatGrammage::class, 'id_grammage')],
            'roll_price' => ['nullable', 'min:0'],
            'cut_cost_per_kg' => ['nullable', 'min:0'],
            'sheet_price_per_kg' => ['nullable', 'min:0'],
            'effective_date' => ['date'],
        ];
    }

    protected function check(array $in, ?int $id): ?string
    {
        return empty($in['roll_price']) && empty($in['sheet_price_per_kg']) ? t('Fill the roll price or the ream price.') : null;
    }
}
