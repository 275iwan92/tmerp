<?php

/*
 * REMARK
 * Tujuan       : Layar input aturan lembar gelombang per supplier (pola Exchange Rate): lebar trim maksimum dan diskon persen per tingkat running meter (300, 500, 1000, 2000, 5000) menurut tanggal berlaku. Boleh diisi tiap hari atau sesekali.
 * Sumber hulu  : Petugas Purchasing mengisi dari ketentuan supplier; Excel Jo sheet UJK (180 cm), RCA (220 cm) dan tabel diskon E1:F5.
 * Input        : Baris sup_corrugated_rule.
 * Output       : Konstanta yang dibaca CorrugatedSheetPriceEngine untuk harga baris PO.
 * Dipakai oleh : Menu Master Data > Supplier Corrugated Rule (v2:md-supplier-corrugated-rule).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SupCorrugatedRule;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SupplierCorrugatedRuleLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-corrugated-rule';
    }

    public function title(): string
    {
        return 'Supplier Corrugated Rule';
    }

    public function icon(): string
    {
        return 'fa-solid fa-ruler-combined';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Corrugated sheet rules per supplier by effective date: maximum trim width and the discount per running-meter tier. A negative discount raises the price. Below 300 running metres there is no price.';
    }

    protected function models(): array
    {
        return [SupCorrugatedRule::class, SupSupplier::class];
    }

    protected function query(): Builder
    {
        return SupCorrugatedRule::query()->orderBy('supplier_id')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        $suppliers = SupSupplier::query()->where('activate', true)->orWhereIn('id_supplier', SupCorrugatedRule::query()->select('supplier_id'))
            ->orderBy('supplier_name')->get(['id_supplier', 'supplier_name'])->mapWithKeys(fn (SupSupplier $s) => [$s->id_supplier => $s->supplier_name])->all();
        $disc = fn (string $min) => ['key' => 'disc_'.$min, 'label' => "Discount from {$min} RM %", 'type' => 'number', 'decimals' => 2, 'width' => '130px'];

        return [
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'required' => true, 'width' => '240px'],
            ['key' => 'max_trim_width_cm', 'label' => 'Max trim width (cm)', 'type' => 'number', 'decimals' => 1, 'required' => true, 'width' => '140px'],
            $disc('300'), $disc('500'), $disc('1000'), $disc('2000'), $disc('5000'),
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
        return ['supplier_id' => [Rule::exists(SupSupplier::class, 'id_supplier')], 'max_trim_width_cm' => ['min:1'], 'effective_date' => ['date']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $same = SupCorrugatedRule::query()->where('supplier_id', $in['supplier_id'] ?? 0)->whereDate('effective_date', (string) ($in['effective_date'] ?? ''))->when($id, fn ($q) => $q->whereKeyNot($id));

        return $same->exists() ? t('A rule already exists for this supplier and date.') : null;
    }
}
