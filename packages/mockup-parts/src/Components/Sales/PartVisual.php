<?php

/*
 * REMARK
 * Tujuan       : Visual part (dieline + 3D) untuk layar Sales dan Form WO; prop readonly menyembunyikan panel editor.
 * Sumber hulu  : Data dieline/artwork part dari Inquiry/ProDev.
 * Input        : part, types, height, readonly.
 * Output       : Markup .dl-dieline dengan atau tanpa panel samping.
 * Dipakai oleh : Layar Sales dan production-wo.
 * Status       : Aktif.
 */

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Tampilan satu part product (lihat saja): dieline TMDieline + artwork bila part punya tipe ber-dieline,
// selain itu artwork/dieline gambar (mis. hasil Proof Digital) dengan tab Sisi Luar / Sisi Dalam.
// $part = baris App\Support\V2\Data\ProductMaster::part().
class PartVisual extends DevlibComponent
{
    public function __construct(
        public array $part = [],
        public array $types = [],
        public string $height = '360px',
        public bool $readonly = false,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.part-visual';
    }

    public function source(): string
    {
        return Src::of('tm2mst.prod_part.dieline_spec', 'tm2mst.prod_part_artwork.artwork_path (side OUTER/INNER)');
    }

    public function typed(): bool
    {
        return ($this->part['has_spec'] ?? false) && isset($this->types[$this->part['type_code'] ?? '']);
    }

    public function sides(): array
    {
        return array_filter(['out' => $this->part['artwork'] ?? null ? 'Outer Side' : null, 'in' => $this->part['artwork_inner'] ?? null ? 'Inner Side' : null]);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-partvis') }} {!! $dl() !!}>
                @if ($typed())
                    <x-mp::dieline-v3 :data-artwork="$part['artwork']" :data-artwork-inner="$part['artwork_inner']" :types="$types" :type="$part['type_code']" :dims="$part['dims']" :name="'pv'.$part['id']" :picker="false" :height="$height" :class="$readonly ? 'dl-dieline--ro' : ''" />
                @elseif ($sides())
                    <x-dl::tabs :tabs="$sides()">
                        <x-slot:out><img class="mp-partvis__img" src="{{ $part['artwork'] }}" alt="{{ strtr(\Devlib\Lang::text('Dieline :name outer side'), [':name' => $part['name']]) }}"></x-slot:out>
                        <x-slot:in><img class="mp-partvis__img" src="{{ $part['artwork_inner'] }}" alt="{{ strtr(\Devlib\Lang::text('Dieline :name inner side'), [':name' => $part['name']]) }}"></x-slot:in>
                    </x-dl::tabs>
                @else
                    <div class="mp-partvis__empty">{{ strtr(\Devlib\Lang::text('No dieline / artwork yet for :name.'), [':name' => $part['name']]) }}</div>
                @endif
            </div>
            BLADE;
    }
}
