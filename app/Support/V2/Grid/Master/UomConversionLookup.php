<?php

namespace App\Support\V2\Grid\Master;

use App\Models\Master\UomConversion;
use App\Models\Master\UomUnit;
use Illuminate\Database\Eloquent\Builder;

// Konversi Satuan (v1: panel Konversi di pages::master.uom). Arti satu baris: 1 From = Value x To, hanya antar satuan sedimensi.
class UomConversionLookup extends MasterDataLookup
{
    public function slug(): string
    {
        return 'md-uom-conversion';
    }

    public function title(): string
    {
        return 'Unit Conversion';
    }

    public function icon(): string
    {
        return 'fa-solid fa-right-left';
    }

    public function guide(): ?string
    {
        return 'One row reads: 1 From = Value x To (1 M = 100 CM). Both units must be of the same dimension, and a pair is entered once. '
            .'Units are added in Units of Measure.';
    }

    protected function models(): array
    {
        return [UomConversion::class, UomUnit::class];
    }

    protected function query(): Builder
    {
        return UomConversion::query()->orderBy('uom_src')->orderBy('uom_dst');
    }

    public function columns(): array
    {
        // Satuan Specification tidak punya konversi. Satuan lama tanpa dimensi tetap ada di pilihan (sudah dipakai konversi lama,
        // mis. IK25 -> PCS); pasangan baru tetap ditolak check() sampai dimensinya diisi di Units of Measure.
        $units = UomUnit::query()->where(fn ($q) => $q->whereNull('dimension')->orWhere('dimension', '<>', 'SPEC'))->orderBy('uom_code')->get(['uom_code', 'uom_name'])
            ->mapWithKeys(fn (UomUnit $u) => [$u->uom_code => $u->uom_code.' - '.$u->uom_name])->all();

        return [
            ['key' => 'uom_src', 'label' => 'From (1 unit)', 'type' => 'select', 'options' => $units, 'required' => true, 'width' => '260px'],
            ['key' => 'conversion_value', 'label' => 'Value', 'type' => 'number', 'required' => true, 'width' => '140px'],
            ['key' => 'uom_dst', 'label' => 'To', 'type' => 'select', 'options' => $units, 'required' => true, 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '80px'],
        ];
    }

    protected function rules(): array
    {
        return ['uom_dst' => ['different:uom_src'], 'conversion_value' => ['gt:0']];
    }

    protected function check(array $in, ?int $id): ?string
    {
        $dim = UomUnit::query()->whereIn('uom_code', [$in['uom_src'], $in['uom_dst']])->pluck('dimension', 'uom_code');
        if ($dim->count() < 2) {
            return t('Unit not found.');
        }
        if ($dim[$in['uom_src']] !== $dim[$in['uom_dst']]) {
            return tf('Different dimensions (:a and :b): the conversion is not valid.', ['a' => $in['uom_src'], 'b' => $in['uom_dst']]);
        }
        $twin = UomConversion::query()->where('uom_src', $in['uom_src'])->where('uom_dst', $in['uom_dst'])->when($id, fn ($q) => $q->whereKeyNot($id))->exists();

        return $twin ? tf('The pair :a to :b already exists. Edit that row.', ['a' => $in['uom_src'], 'b' => $in['uom_dst']]) : null;
    }
}
