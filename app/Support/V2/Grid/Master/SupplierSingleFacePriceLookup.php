<?php

/*
 * REMARK
 * Tujuan       : Layar input harga Single Face per supplier (pola Exchange Rate): supplier, substance, flute, index per m2, dasar PPN, tanggal berlaku; baris baru dengan tanggal baru = riwayat.
 * Sumber hulu  : Petugas Purchasing mengisi harga dari supplier; sup_supplier, mat_sheet_rate (daftar substance Single Face).
 * Input        : Baris sup_single_face_price.
 * Output       : Konstanta harga yang dibaca SingleFacePriceEngine untuk harga PR dan PO.
 * Dipakai oleh : Menu Master Data > Supplier Single Face Price (v2:md-supplier-single-face-price).
 * Status       : Aktif.
 */

namespace App\Support\V2\Grid\Master;

use App\Models\Master\MatSheetRate;
use App\Models\Master\SupSingleFacePrice;
use App\Models\Master\SupSupplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SupplierSingleFacePriceLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-supplier-single-face-price';
    }

    public function title(): string
    {
        return 'Supplier Single Face Price';
    }

    public function icon(): string
    {
        return 'fa-solid fa-layer-group';
    }

    public function guide(): ?string
    {
        // Maksimal 255 huruf supaya bisa diterjemahkan lewat kamus.
        return 'Single Face price per supplier, versioned by effective date like Exchange Rate. Index = price per square metre. Price per sheet = index x length (m) x width (m), divided by (1 + PPN/100) when the price includes PPN.';
    }

    protected function models(): array
    {
        return [SupSingleFacePrice::class, SupSupplier::class, MatSheetRate::class];
    }

    protected function query(): Builder
    {
        return SupSingleFacePrice::query()->orderBy('supplier_id')->orderBy('substance_name')->orderBy('flute_type')->orderByDesc('effective_date');
    }

    public function columns(): array
    {
        $suppliers = SupSupplier::query()->where('activate', true)->orWhereIn('id_supplier', SupSingleFacePrice::query()->select('supplier_id'))
            ->orderBy('supplier_name')->get(['id_supplier', 'supplier_name'])->mapWithKeys(fn (SupSupplier $s) => [$s->id_supplier => $s->supplier_name])->all();
        $substances = MatSheetRate::query()->whereIn('mat_subtype', ['SINGLE FACE', 'SF'])->orderBy('substance_name')->pluck('substance_name')
            ->unique()->mapWithKeys(fn (string $n) => [$n => $n])->all();

        return [
            ['key' => 'supplier_id', 'label' => 'Supplier', 'type' => 'select', 'options' => $suppliers, 'required' => true, 'width' => '240px'],
            ['key' => 'substance_name', 'label' => 'Substance', 'type' => 'select', 'options' => $substances, 'required' => true, 'width' => '220px'],
            ['key' => 'flute_type', 'label' => 'Flute', 'type' => 'select', 'options' => ['E/F' => 'E/F', 'B/F' => 'B/F', 'C/F' => 'C/F', 'BC/F' => 'BC/F'], 'required' => true, 'width' => '90px'],
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

    protected function rules(): array
    {
        return ['supplier_id' => [Rule::exists(SupSupplier::class, 'id_supplier')], 'index_per_m2' => ['min:0'], 'effective_date' => ['date']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $same = SupSingleFacePrice::query()->where('supplier_id', $in['supplier_id'] ?? 0)->where('substance_name', $in['substance_name'] ?? '')
            ->where('flute_type', $in['flute_type'] ?? '')->whereDate('effective_date', (string) ($in['effective_date'] ?? ''))->when($id, fn ($q) => $q->whereKeyNot($id));

        return $same->exists() ? t('A price already exists for this supplier, substance, flute and date.') : null;
    }
}
