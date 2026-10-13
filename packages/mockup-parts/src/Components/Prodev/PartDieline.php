<?php

namespace MockupParts\Components\Prodev;

use Devlib\Components\DevlibComponent;
use MockupParts\Src;

// Panel "Part & Dieline" di form ProDev: panel yang sama dengan Inquiry Product (x-mp::sales.inquiry-part) tanpa
// bahan/warna/finishing. ProDev berhak mengubah dieline & mockup: dimensi, bentangan (Flat Size), artwork luar/dalam
// bisa diubah lalu Save; identitas part (nama, tipe, qty) milik Inquiry -> tampil saja.
// $part = App\Support\V2\Data\Prodev::dieline(); $action = route simpan (POST multipart).
class PartDieline extends DevlibComponent
{
    public function __construct(
        public array $part = [],
        public string $action = '#',
    ) {}

    public function tag(): string
    {
        return 'x-mp::prodev.part-dieline';
    }

    public function source(): string
    {
        return Src::of('tm2sdi.iqp_part.dieline_spec', 'tm2mst.prod_part.dieline_spec', 'tm2sdi.iqp_part.artwork_path');
    }

    // Akibat ke tahap sesudahnya, ditampilkan sebelum Save (tidak memblokir).
    public function warnings(): array
    {
        return array_keys(array_filter([
            'A confirmed layout exists for this part. After changing the dieline, redo the imposition (Save All, then Confirm) so Product Costing uses the new size.' => $this->part['layout_confirmed'],
            'The costing of this inquiry is already published. The selling price does not follow a dieline change: ask Product Costing to recalculate.' => $this->part['costing_published'],
            'This product is already active in Master Product. The change is written to the master as well, and a new artwork becomes a new version that needs Digital ACC again.' => $this->part['product_active'],
        ]));
    }

    public function render()
    {
        return <<<'BLADE'
            @php($p = $part)
            <form {{ $attributes->class('mp-part') }} {!! $dl() !!} method="post" action="{{ $action }}" enctype="multipart/form-data" data-mp-prodev-dieline data-dl-guard>
                @csrf
                <header class="mp-part__head">
                    <b>{{ $p['part_name'] }}</b>
                    @if ($p['inquiry_no'])<x-dl::badge :label="$p['inquiry_no']" tone="info" />@endif
                    <span class="mp-grow"></span>
                    @if ($p['updated_by'])<x-dl::hint note as="span">{{ t('Last changed by') }} {{ $p['updated_by'] }} · {{ $p['updated_at'] }}</x-dl::hint>@endif
                    <x-dl::button preset="save" />
                </header>
                <div class="mp-part__body">
                    @foreach ($warnings() as $w)
                        <x-dl::alert tone="warn" :message="t($w)" />
                    @endforeach
                    <x-dl::form.row cols="5">
                        <x-dl::field.display label="Part Name" :value="$p['part_name']" :source="\MockupParts\Src::of('tm2mst.prod_part.part_name')" />
                        <x-dl::field.display label="Part Type" :value="$p['part_type']" :source="\MockupParts\Src::of('tm2sdi.iqp_part.part_type')" />
                        <x-dl::field.display label="Qty per Set" :value="$p['qty_per_sku']" :source="\MockupParts\Src::of('tm2sdi.iqp_part.qty_per_sku')" />
                        <x-dl::field.display label="Qty per Pack (pcs)" :value="$p['pack_qty']" :source="\MockupParts\Src::of('tm2sdi.iqp_part.pack_qty')" />
                        <div class="mp-part__art">
                            @if ($p['can_upload'])
                                <x-dl::field.upload name="artwork" label="Artwork / Design (outer)" accept=".jpg,.jpeg,.png" data-pd-artwork
                                    :value="$p['artwork_url'] ? ['url' => $p['artwork_url'], 'name' => $p['artwork_name']] : null"
                                    :source="\MockupParts\Src::of('tm2sdi.iqp_part.artwork_path', 'tm2sdi.iqp_part.artwork_colors', 'tm2mst.prod_part_artwork')" />
                                <x-dl::field.upload name="artwork_inner" label="Inner Side Artwork (optional)" accept=".jpg,.jpeg,.png" data-pd-artwork-inner
                                    :value="$p['artwork_inner_url'] ? ['url' => $p['artwork_inner_url'], 'name' => $p['artwork_inner_name']] : null"
                                    :source="\MockupParts\Src::of('tm2sdi.iqp_part.artwork_inner_path', 'tm2mst.prod_part_artwork')" />
                            @else
                                <x-dl::field.display label="Artwork / Design" :value="$p['artwork_name'] ?? '—'" hint="This part has no Inquiry (manual mode): artwork cannot be replaced here yet."
                                    :source="\MockupParts\Src::of('tm2mst.prod_part_artwork')" />
                            @endif
                        </div>
                    </x-dl::form.row>
                    <x-mp::dieline-v3 :data-artwork="$p['artwork_url']" :data-artwork-inner="$p['artwork_inner_url']" :types="$p['types']" :type="$p['type_code']" :dims="$p['dims']" name="dieline" :picker="false" height="400px"
                        :source="\MockupParts\Src::of('tm2mst.prod_type.dieline_spec', 'tm2sdi.iqp_part.dieline_spec', 'tm2sdi.iqp_part.flat_w', 'tm2sdi.iqp_part.flat_h', 'tm2sdi.iqp_part.flat_area', 'tm2mst.prod_part.dieline_spec')" />
                </div>
            </form>
            BLADE;
    }
}
