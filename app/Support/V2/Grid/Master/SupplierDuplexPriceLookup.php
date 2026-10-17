<?php

/*
 * REMARK
 * Tujuan       : Layar input harga duplex per supplier (pola Exchange Rate): supplier, grade, rentang gsm, rumus (Reference sheet / Weight), index, APKey %, dasar PPN, tanggal berlaku; baris baru dengan tanggal baru = riwayat. Boleh diisi tiap hari atau sesekali.
 * Sumber hulu  : Petugas Purchasing mengisi dari daftar harga supplier; Excel Jo sheet INDEX (tabel index per gsm, PLUS = APKey).
 * Input        : Baris sup_duplex_price.
 * Output       : Konstanta yang dibaca DuplexPriceEngine untuk harga PR dan PO.
 * Dipakai oleh : Menu Master Data > Supplier Duplex Price (v2:md-supplier-duplex-price).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SupDuplexPrice;
use App\Models\Master\SupSupplier;
use App\Services\Pur\DuplexPriceEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SupplierDuplexPriceLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-duplex-price';
    }

    public function title(): string
    {
        return 'Supplier Duplex Price';
    }

    public function icon(): string
    {
        return 'fa-solid fa-boxes-stacked';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Duplex price per supplier by effective date. Reference sheet: P x L / (79 x 109) x index x APKey. Weight: gsm x P x L / 20000 x index x APKey. APKey in percent (500 = x5.00). One row per gsm range.';
    }

    protected function models(): array
    {
        return [SupDuplexPrice::class, SupSupplier::class];
    }

    protected function query(): Builder
    {
        return SupDuplexPrice::query()->orderBy('supplier_id')->orderBy('grade')->orderBy('gsm_from')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        $suppliers = SupSupplier::query()->where('activate', true)->orWhereIn('id_supplier', SupDuplexPrice::query()->select('supplier_id'))
            ->orderBy('supplier_name')->get(['id_supplier', 'supplier_name'])->mapWithKeys(fn (SupSupplier $s) => [$s->id_supplier => $s->supplier_name])->all();

        return [
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'required' => true, 'width' => '240px'],
            ['key' => 'grade', 'label' => 'Grade', 'width' => '90px'],
            ['key' => 'gsm_from', 'label' => 'Gsm from', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '90px'],
            ['key' => 'gsm_to', 'label' => 'Gsm to', 'type' => 'number', 'decimals' => 0, 'required' => true, 'width' => '90px'],
            ['key' => 'formula', 'label' => 'Formula', 'type' => 'select', 'options' => [DuplexPriceEngine::REF_SHEET => 'Reference sheet', DuplexPriceEngine::WEIGHT => 'Weight'], 'required' => true, 'width' => '140px'],
            ['key' => 'index_value', 'label' => 'Index', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '130px'],
            ['key' => 'apkey_pct', 'label' => 'APKey %', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '110px'],
            ['key' => 'price_basis', 'label' => 'Price basis', 'type' => 'select', 'options' => ['EXCL' => 'Excl PPN', 'INCL' => 'Incl PPN'], 'required' => true, 'width' => '110px'],
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
        return $this->upper($in, 'grade');
    }

    protected function rules(): array
    {
        return ['supplier_id' => [Rule::exists(SupSupplier::class, 'id_supplier')], 'gsm_from' => ['min:0'], 'gsm_to' => ['min:0'], 'index_value' => ['min:0'], 'apkey_pct' => ['min:0'], 'effective_date' => ['date']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        if ((float) ($in['gsm_to'] ?? 0) < (float) ($in['gsm_from'] ?? 0)) {
            return t('Gsm to must not be less than gsm from.');
        }
        $grade = ($in['grade'] ?? '') === '' ? null : $in['grade'];
        $same = SupDuplexPrice::query()->where('supplier_id', $in['supplier_id'] ?? 0)->where('gsm_from', $in['gsm_from'] ?? 0)->whereDate('effective_date', (string) ($in['effective_date'] ?? ''))
            ->when($grade, fn ($q) => $q->where('grade', $grade), fn ($q) => $q->whereNull('grade'))->when($id, fn ($q) => $q->whereKeyNot($id));

        return $same->exists() ? t('A price already exists for this supplier, grade, gsm and date.') : null;
    }
}
