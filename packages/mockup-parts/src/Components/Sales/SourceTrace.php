<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Jejak relasi dokumen turunan: [asal] -> [dokumen baru] (Revisi: versi baru; Rekalkulasi: RECALC dari inkuiri baku).
class SourceTrace extends DevlibComponent
{
    public function __construct(
        public string $fromLabel = 'Inkuiri asal',
        public string $from = '',
        public string $toLabel = 'Versi baru',
        public ?string $to = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.source-trace';
    }

    public function source(): string
    {
        return \MockupParts\Src::of('tm2sdi.iqp_inquiry.inquiry_no', 'tm2sdi.iqp_inquiry.parent_product_id', 'tm2sdi.iqp_inquiry.revision_no');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-trace') }} {!! $dl() !!}>
                <span class="mp-trace__node"><b>{{ $fromLabel }}:</b> {{ $from }}</span>
                <i class="fa-solid fa-arrow-right"></i>
                <span class="mp-trace__node is-new"><b>{{ $toLabel }}:</b> <span data-inq-no>{{ $to ?? '(belum tersimpan)' }}</span></span>
            </div>
            BLADE;
    }
}
