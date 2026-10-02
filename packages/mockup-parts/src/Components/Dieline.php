<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Dieline kemasan hidup (InqProd Engine): pilih tipe -> ukuran pasaran / isi dimensi -> SVG langsung berubah.
// Render = TMDieline (resources/assets/tmdieline.js), salinan UTUH dari tmerp (zona beku: esensi tidak diubah).
// types: [kode => ['label', 'category', 'spec' => DSL dieline_spec, 'params' => [key => ['label','unit','default','min','max']],
//          'sizes' => [['label', 'dims' => [key => nilai]]]]]  (di tmerp: prod_type + prod_type_param + prod_type_size(_val))
// Terkirim: name[type], name[dims][key], name[flat_w], name[flat_h], name[flat_area], name[flat_manual]. Event dl:dieline.
class Dieline extends DevlibComponent
{
    public function __construct(
        public array $types = [],
        public ?string $type = null,
        public array $dims = [],
        public string $name = 'dieline',
        public bool $picker = true,
        public string $height = '440px',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::dieline';
    }

    public function source(): string
    {
        return $this->source ?? 'tm2mst.prod_type (dieline_spec) + prod_type_param + prod_type_size';
    }

    public function render()
    {
        return <<<'BLADE'
            @once<script src="{{ \MockupParts\MockupPartsServiceProvider::asset('tmdieline.js') }}"></script>@endonce
            <div {{ $attributes->class('dl-dieline') }} {!! $dl() !!} data-dl-dieline data-types="{{ json_encode($types) }}" data-type="{{ $type ?? array_key_first($types) }}" data-dims="{{ json_encode((object) $dims) }}" data-name="{{ $name }}" style="--dl-dl-h:{{ $height }}">
                <div class="dl-dieline__side">
                    @if ($picker)
                        <x-dl::field.select :name="$name.'[type]'" label="Product Type" :options="collect($types)->map(fn ($t) => $t['label'])->all()" :value="$type ?? array_key_first($types)" />
                    @else
                        <input type="hidden" name="{{ $name }}[type]" value="{{ $type }}">
                    @endif
                    <div class="dl-dieline__sizes"><span class="dl-dieline__cap">{{ \Devlib\Lang::text('Standard Size') }}</span><div data-d-sizes></div></div>
                    <div class="dl-dieline__cap">{{ \Devlib\Lang::text('Dimensions (mm)') }}</div>
                    <div class="dl-dieline__dims" data-d-dims></div>
                    <div class="dl-dieline__flat">
                        <div class="dl-dieline__cap">{{ \Devlib\Lang::text('Flat Size') }} <label class="dl-choice"><input type="checkbox" name="{{ $name }}[flat_manual]" value="1" data-d-manual> {{ \Devlib\Lang::text('Manual') }}</label></div>
                        <div class="dl-dieline__flatrow">
                            <label>W <input type="text" inputmode="decimal" name="{{ $name }}[flat_w]" data-d-fw readonly></label>
                            <span>×</span>
                            <label>H <input type="text" inputmode="decimal" name="{{ $name }}[flat_h]" data-d-fh readonly></label>
                            <label>{{ \Devlib\Lang::text('Area') }} <input type="text" name="{{ $name }}[flat_area]" data-d-fa readonly></label>
                        </div>
                    </div>
                </div>
                <div class="dl-dieline__stage">
                    <div class="dl-dieline__tools">
                        <span class="dl-dieline__legend"><i class="is-cut"></i>{{ \Devlib\Lang::text('Cutting') }} <i class="is-crease"></i>{{ \Devlib\Lang::text('Creasing') }} <i class="is-bleed"></i>{{ \Devlib\Lang::text('Bleed') }} <i class="is-hole"></i>{{ \Devlib\Lang::text('Hole') }}</span>
                        <span class="dl-print__spacer"></span>
                        <x-dl::icon-button icon="fa-magnifying-glass-minus" title="Zoom out" data-d-zoom="-1" /><b data-d-zoomval>100%</b><x-dl::icon-button icon="fa-magnifying-glass-plus" title="Zoom in" data-d-zoom="1" />
                        <x-dl::button variant="secondary" size="sm" icon="download" label="SVG" data-d-svg />
                    </div>
                    <div class="dl-dieline__canvas"><div class="dl-dieline__preview" data-d-preview></div></div>
                    <div class="dl-dieline__info" data-d-info></div>
                </div>
            </div>
            BLADE;
    }
}
