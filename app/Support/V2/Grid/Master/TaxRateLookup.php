<?php

/*
 * REMARK
 * Tujuan       : Layar input tarif pajak (PPN) menurut tanggal berlaku, pola Exchange Rate: baris baru dengan tanggal baru, baris lama tetap sebagai riwayat.
 * Sumber hulu  : Petugas Finance mengisi tarif sesuai peraturan pajak.
 * Input        : Baris fin_tax_rate (pajak, tarif %, tanggal berlaku, aktif).
 * Output       : Tarif yang dibaca TaxRate::ppn(tanggal) untuk PO, CO, Quotation, invoice, DO, harga kertas.
 * Dipakai oleh : Menu Master Data > Tax Rate (v2:md-tax-rate).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\FinTaxRate;
use App\Support\Finance\TaxRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxRateLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-tax-rate';
    }

    public function title(): string
    {
        return 'Tax Rate';
    }

    public function icon(): string
    {
        return 'fa-solid fa-percent';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Tax rate by effective date, like Exchange Rate. A document uses the rate in force on its own date, so an old document keeps its old rate. To change the rate, add a new row with the new date.';
    }

    protected function models(): array
    {
        return [FinTaxRate::class];
    }

    protected function query(): Builder
    {
        return FinTaxRate::query()->orderBy('tax_code')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        return [
            ['key' => 'tax_code', 'label' => 'Tax', 'type' => 'select', 'options' => ['PPN' => 'PPN'], 'required' => true, 'width' => '140px'],
            ['key' => 'rate', 'label' => 'Rate %', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '120px'],
            ['key' => 'effective_date', 'label' => 'Effective', 'type' => 'date', 'required' => true, 'width' => '140px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function row(Model $m): array
    {
        return ['effective_date' => $m->effective_date?->toDateString()] + parent::row($m);
    }

    protected function rules(): array
    {
        return ['rate' => ['min:0', 'max:100'], 'effective_date' => ['date']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $same = FinTaxRate::query()->where('tax_code', $in['tax_code'] ?? '')->whereDate('effective_date', (string) ($in['effective_date'] ?? ''))->when($id, fn ($q) => $q->whereKeyNot($id));

        return $same->exists() ? t('A rate already exists for this tax and date.') : null;
    }

    protected function saved(Model $row): void
    {
        TaxRate::flush();
    }
}
