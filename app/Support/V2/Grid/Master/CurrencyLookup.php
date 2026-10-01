<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinCurrency;
use Illuminate\Database\Eloquent\Builder;

// Master Currency (v1: pages::master.currency). Kurs per mata uang = ExchangeRateLookup.
class CurrencyLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-currency';
    }

    public function title(): string
    {
        return 'Currency';
    }

    public function icon(): string
    {
        return 'fa-solid fa-coins';
    }

    public function guide(): ?string
    {
        return 'Counted Word is the currency name written in the amount in words on printed documents (Rupiah, Dollar). '
            .'A saved code cannot be changed, only deactivated. Rates are entered in Exchange Rate.';
    }

    protected function query(): Builder
    {
        return FinCurrency::query()->orderByDesc('activate')->orderBy('currency_code');
    }

    public function columns(): array
    {
        return [
            ['key' => 'currency_code', 'label' => 'Code', 'required' => true, 'unique' => true, 'lockSaved' => true, 'case' => 'uppercase', 'width' => '110px'],
            ['key' => 'currency_name', 'label' => 'Currency Name', 'required' => true, 'width' => '300px'],
            ['key' => 'symbol', 'label' => 'Symbol', 'width' => '110px'],
            ['key' => 'counted_word', 'label' => 'Counted Word', 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function prepare(array $in): array
    {
        return $this->upper($in, 'currency_code');
    }

    // Panjang = lebar kolom tabel (fin_currency).
    protected function rules(): array
    {
        return ['currency_code' => ['string', 'max:5'], 'currency_name' => ['string', 'max:50'], 'symbol' => ['string', 'max:10'],
            'counted_word' => ['string', 'max:50']];
    }
}
