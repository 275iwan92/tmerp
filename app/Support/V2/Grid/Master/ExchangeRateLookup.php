<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinCurrency;
use App\Models\Master\FinExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

// Master Kurs (v1: pages::master.exchange-rate). Pola versi: satu baris Aktif per mata uang = kurs berlaku; baris lama = riwayat.
class ExchangeRateLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-exchange-rate';
    }

    public function title(): string
    {
        return 'Exchange Rate';
    }

    public function icon(): string
    {
        return 'fa-solid fa-money-bill-transfer';
    }

    public function guide(): ?string
    {
        return 'Rate = value of 1 unit of the currency in Rupiah. One Active row per currency is the rate in force: '
            .'to change a rate, add a new row with its Effective date and tick Active; the older row of that currency is switched off and stays as history.';
    }

    protected function models(): array
    {
        return [FinExchangeRate::class, FinCurrency::class];
    }

    protected function query(): Builder
    {
        return FinExchangeRate::query()->orderByDesc('activate')->orderBy('currency_id')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        // Pilihan = mata uang aktif + yang sudah punya baris kurs (supaya baris lama tetap terbaca).
        $currencies = FinCurrency::query()->where('activate', true)->orWhereIn('id_currency', FinExchangeRate::query()->select('currency_id'))
            ->orderBy('currency_code')->get(['id_currency', 'currency_code', 'currency_name'])
            ->mapWithKeys(fn (FinCurrency $c) => [$c->id_currency => $c->currency_code.' - '.$c->currency_name])->all();

        return [
            ['key' => 'currency_id', 'label' => 'Currency', 'type' => 'select', 'options' => $currencies, 'required' => true, 'width' => '300px'],
            ['key' => 'rate_to_base', 'label' => 'Rate to IDR', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '160px'],
            ['key' => 'effective_date', 'label' => 'Effective', 'type' => 'date', 'width' => '140px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['effective_date' => $m->effective_date?->toDateString()] + parent::row($m);
    }

    protected function rules(): array
    {
        return ['currency_id' => [Rule::exists(FinCurrency::class, 'id_currency')], 'rate_to_base' => ['min:0'], 'effective_date' => ['date']];
    }

    protected function saved(Model $row): void
    {
        if ($row->activate) {
            FinExchangeRate::query()->where('currency_id', $row->currency_id)->whereKeyNot($row->getKey())->where('activate', true)->update(['activate' => false]);
        }
    }
}
