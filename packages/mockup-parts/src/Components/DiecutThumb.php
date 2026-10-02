<?php

namespace MockupParts\Components;

use Devlib\Components\DevlibComponent;

// Gambar kecil diecut (baca-saja) dari dieline_spec + dims, digambar TMDieline.svg() — geometri sama persis
// dengan Dieline v1-v3 (zona beku: esensi tidak diubah). Dipakai RECALC (cek visual sebelum submit) & ringkasan part.
class DiecutThumb extends DevlibComponent
{
    public function __construct(
        public ?array $spec = null,
        public array $dims = [],
        public string $label = 'Diecut',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::diecut-thumb';
    }

    public function source(): string
    {
        return $this->source ?? \MockupParts\Src::of('tm2sdi.iqp_part.dieline_spec');
    }

    public function render()
    {
        return <<<'BLADE'
            @once<script src="{{ \MockupParts\MockupPartsServiceProvider::asset('tmdieline.js') }}"></script>@endonce
            <div {{ $attributes->class('mp-thumb') }} {!! $dl() !!}>
                <small>{{ $label }}</small>
                <div class="mp-thumb__box" data-mp-diecut data-spec="{{ json_encode($spec) }}" data-dims="{{ json_encode((object) $dims) }}">{{ empty($spec['elements']) ? \Devlib\Lang::text('No diecut') : '' }}</div>
            </div>
            BLADE;
    }
}
