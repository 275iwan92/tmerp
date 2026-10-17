<?php

/*
 * REMARK
 * Tujuan       : Layar input index lembar gelombang per supplier dan substance (pola Exchange Rate): index per m2 lembar trim, dasar PPN, tanggal berlaku. Substance umum SW atau DW dipakai bila substance bahan tidak ada. Boleh diisi tiap hari atau sesekali.
 * Sumber hulu  : Petugas Purchasing mengisi dari daftar harga supplier; Excel Jo sheet UJK, RCA (tabel index E5:F27: SW 4410, DW 7379, SDW K125/M125/K125 7507, dan seterusnya).
 * Input        : Baris sup_corrugated_index.
 * Output       : Konstanta yang dibaca CorrugatedSheetPriceEngine untuk harga baris PO.
 * Dipakai oleh : Menu Master Data > Supplier Corrugated Index (v2:md-supplier-corrugated-index).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\SupCorrugatedIndex;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SupplierCorrugatedIndexLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-corrugated-index';
    }

    public function title(): string
    {
        return 'Supplier Corrugated Index';
    }

    public function icon(): string
    {
        return 'fa-solid fa-table-list';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Corrugated sheet index per supplier and substance by effective date. Index = price per square metre of the trimmed sheet. Use SW or DW as a generic substance. The material name decides which substance is used.';
    }

    protected function models(): array
    {
        return [SupCorrugatedIndex::class, SupSupplier::class];
    }

    protected function query(): Builder
    {
        return SupCorrugatedIndex::query()->orderBy('supplier_id')->orderBy('substance_name')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        $suppliers = SupSupplier::query()->where('activate', true)->orWhereIn('id_supplier', SupCorrugatedIndex::query()->select('supplier_id'))
            ->orderBy('supplier_name')->get(['id_supplier', 'supplier_name'])->mapWithKeys(fn (SupSupplier $s) => [$s->id_supplier => $s->supplier_name])->all();

        return [
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'required' => true, 'width' => '240px'],
            ['key' => 'substance_name', 'label' => 'Substance', 'required' => true, 'width' => '260px'],
            ['key' => 'index_per_m2', 'label' => 'Index / m2', 'type' => 'number', 'decimals' => 2, 'required' => true, 'width' => '130px'],
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
        return $this->upper($in, 'substance_name');
    }

    protected function rules(): array
    {
        return ['supplier_id' => [Rule::exists(SupSupplier::class, 'id_supplier')], 'index_per_m2' => ['min:0'], 'effective_date' => ['date']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $same = SupCorrugatedIndex::query()->where('supplier_id', $in['supplier_id'] ?? 0)->where('substance_name', $in['substance_name'] ?? '')
            ->whereDate('effective_date', (string) ($in['effective_date'] ?? ''))->when($id, fn ($q) => $q->whereKeyNot($id));

        return $same->exists() ? t('An index already exists for this supplier, substance and date.') : null;
    }
}
