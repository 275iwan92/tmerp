<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Dieline v2 = v1 (x-dl::dieline, TIDAK diubah) + garis ukuran seperti gambar teknik (rantai panel di atas/kiri,
// total di bawah/kanan), kode istilah internasional (L, W, H, GF, TF, DF, BF), satuan mm/cm, saklar tampil ukuran.
// Geometri tetap dari TMDieline; ukuran dibaca dari hasil render (garis lipat + tepi potong).
class DielineV2 extends Dieline
{
    // [label internasional, kode di garis ukuran] per param_key; label DB dipakai bila key tidak ada.
    public const TERMS = [
        'product_length' => ['Length', 'L'], 'product_width' => ['Width', 'W'], 'product_height' => ['Height', 'H'],
        'diameter' => ['Diameter', 'Ø'], 'lid_height' => ['Lid Height', 'LH'], 'glue_flap' => ['Glue Flap', 'GF'],
        'top_tuck_flap' => ['Top Tuck Flap', 'TF'], 'dust_flap' => ['Dust Flap', 'DF'], 'bottom_tuck_flap' => ['Bottom Tuck Flap', 'BF'],
        'overflow_margin' => ['Bleed', 'BL'],
    ];

    public function __construct(
        array $types = [],
        ?string $type = null,
        array $dims = [],
        string $name = 'dieline',
        bool $picker = true,
        string $height = '460px',
        ?string $source = null,
        public bool $dimensions = true,
        public string $unit = 'mm',
    ) {
        parent::__construct($types, $type, $dims, $name, $picker, $height, $source);
    }

    public function tag(): string
    {
        return 'x-mp::dieline-v2';
    }

    public function level(): int
    {
        return 2;
    }

    public function terms(): array
    {
        return static::TERMS;
    }

    public function render()
    {
        return <<<'BLADE'
            @once<script src="{{ \MockupParts\MockupPartsServiceProvider::asset('tmdieline.js') }}"></script>@endonce
            <div {{ $attributes->class(['dl-dieline', 'dl-dieline--v'.$level()]) }} {!! $dl() !!} data-dl-dieline-x data-level="{{ $level() }}" data-types="{{ json_encode($types) }}" data-type="{{ $type ?? array_key_first($types) }}" data-dims="{{ json_encode((object) $dims) }}" data-name="{{ $name }}" data-terms="{{ json_encode($terms()) }}" data-unit="{{ $unit }}" style="--dl-dl-h:{{ $height }}">
                <div class="dl-dieline__side">
                    @if ($picker)
                        <x-dl::field.select :name="$name.'[type]'" label="Product Type" :options="collect($types)->map(fn ($t) => $t['label'])->all()" :value="$type ?? array_key_first($types)" />
                    @else
                        <input type="hidden" name="{{ $name }}[type]" value="{{ $type }}">
                    @endif
                    <div class="dl-dieline__sizes"><span class="dl-dieline__cap">Standard Sizes</span><div data-d-sizes></div></div>
                    <div class="dl-dieline__cap">Dimensions</div>
                    <div class="dl-dieline__dims" data-d-dims></div>
                    <div class="dl-dieline__flat">
                        <div class="dl-dieline__cap">Flat Size (Blank) <label class="dl-choice"><input type="checkbox" name="{{ $name }}[flat_manual]" value="1" data-d-manual> Manual</label></div>
                        <div class="dl-dieline__flatrow">
                            <label>W <input type="text" inputmode="decimal" name="{{ $name }}[flat_w]" data-d-fw readonly></label>
                            <span>×</span>
                            <label>H <input type="text" inputmode="decimal" name="{{ $name }}[flat_h]" data-d-fh readonly></label>
                            <label>Area <input type="text" name="{{ $name }}[flat_area]" data-d-fa readonly></label>
                        </div>
                    </div>
                </div>
                <div class="dl-dieline__stage">
                    @if ($level() >= 3)<div class="dl-dieline__title" data-d-title></div>@endif
                    <div class="dl-dieline__tools">
                        <span class="dl-dieline__legend"><i class="is-cut"></i>Cutting <i class="is-crease"></i>Creasing <i class="is-bleed"></i>Bleed <i class="is-hole"></i>Hole</span>
                        <span class="dl-print__spacer"></span>
                        {{-- Cetak 2 sisi: tampil bila ada artwork sisi dalam (data-artwork-inner / setArtworkInner). --}}
                        <div class="dl-segment" data-d-side hidden><button type="button" class="dl-segment__opt is-active" data-value="out">Sisi Luar</button><button type="button" class="dl-segment__opt" data-value="in">Sisi Dalam</button></div>
                        <div class="dl-segment dl-dieline__unit" data-d-unit><button type="button" class="dl-segment__opt" data-value="mm">mm</button><button type="button" class="dl-segment__opt" data-value="cm">cm</button></div>
                        <x-dl::icon-button icon="fa-magnifying-glass-minus" title="Perkecil" data-d-zoom="-1" /><b data-d-zoomval>100%</b><x-dl::icon-button icon="fa-magnifying-glass-plus" title="Perbesar" data-d-zoom="1" />
                        <x-dl::button variant="secondary" size="sm" icon="download" label="SVG" data-d-svg />
                    </div>
                    {{-- Opsi tampilan (hanya gambar; tidak mengubah geometri TMDieline maupun panjang pisau). --}}
                    <div class="dl-dieline__opts">
                        <label class="dl-choice"><input type="checkbox" data-d-opt="artwork" checked> Artwork</label>
                        <label class="dl-choice"><input type="checkbox" data-d-opt="overflow" checked> Overflow</label>
                        <label class="dl-choice"><input type="checkbox" data-d-opt="glue" checked> Glue margin</label>
                        <label class="dl-choice"><input type="checkbox" data-d-opt="direction" checked> Direction <x-dl::tooltip text="Arah serat kertas (grain) — sejajar panjang lembar."><i class="fa-regular fa-circle-question dl-dieline__q"></i></x-dl::tooltip></label>
                        <label class="dl-choice"><input type="checkbox" data-d-opt="cross" checked> Cross <x-dl::tooltip text="Tanda register (crosshair) di sudut bleed untuk menyelaraskan cetak CMYK."><i class="fa-regular fa-circle-question dl-dieline__q"></i></x-dl::tooltip></label>
                        <label class="dl-choice"><input type="checkbox" data-d-opt="lock"> Lock tab <x-dl::tooltip text="Takik pengunci gesek (friction lock) di ujung lipatan tuck flap — indikatif."><i class="fa-regular fa-circle-question dl-dieline__q"></i></x-dl::tooltip></label>
                        <label class="dl-choice"><input type="checkbox" data-d-dims-on @checked($dimensions)> Dimension lines</label>
                        <label class="dl-choice" data-d-mirror-wrap hidden><input type="checkbox" data-d-opt="mirror"> Mirror <x-dl::tooltip text="Centang bila file sisi dalam dibuat di atas dieline yang dicerminkan (dilihat dari dalam) — gambar dibalik agar jatuh pas di panelnya."><i class="fa-regular fa-circle-question dl-dieline__q"></i></x-dl::tooltip></label>
                    </div>
                    <div class="dl-dieline__canvas"><div class="dl-dieline__preview" data-d-preview></div></div>
                    @if ($level() >= 3)<div class="dl-dieline__foot"><div class="dl-dieline__lens" data-d-lens></div><div class="dl-dieline__3d" data-d-3d title="Tampilan 3D statis (ilustrasi)"></div></div>@endif
                    <div class="dl-dieline__info" data-d-info></div>
                    <div class="dl-dieline__terms" data-d-terms></div>
                </div>
            </div>
            BLADE;
    }
}
