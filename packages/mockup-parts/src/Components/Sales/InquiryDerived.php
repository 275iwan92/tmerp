<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Induk dokumen Inquiry turunan dari inkuiri lain: REVISE (spec berubah, versi baru) & RECALC (spec sama, harga baru).
// tmerp pages/inquiry/revise.blade.php & recalc.blade.php — flow SAMA: bar dokumen -> isi (slot) ->
// bar aksi bawah: Simpan (DRAFT) · ringkasan · Submit ke Costing (SUBMITTED + terkunci).
// Aturan khas per jenis ada di JS turunan (InquiryRevise / InquiryRecalc) lewat jsClass().
class InquiryDerived extends DevlibComponent
{
    public function __construct(
        public string $mode = 'REVISE',
        public array $doc = [],
        public bool $readonly = false,
        public ?string $backUrl = null,
        public ?string $version = null,
        public string $submitLabel = 'Submit ke Costing',
    ) {
        $this->doc += ['id' => null, 'inquiry_no' => null, 'sub_state' => 'DRAFT', 'stat_confirm' => false];
    }

    public function tag(): string
    {
        return 'x-mp::sales.inquiry-derived mode='.$this->mode;
    }

    public function source(): string
    {
        return \MockupParts\Src::of('tm2sdi.iqp_inquiry');
    }

    public function jsClass(): ?string
    {
        return ['REVISE' => 'InquiryRevise', 'RECALC' => 'InquiryRecalc'][$this->mode] ?? null;
    }

    public function config(): array
    {
        return ['mode' => $this->mode, 'doc' => $this->doc, 'readonly' => $this->readonly, 'yy' => date('y')];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-inq') }} {!! $dl() !!} data-mp-inquiry-derived data-config="{{ json_encode($config()) }}">
                <x-mp::sales.doc-bar :mode="$mode" :no="$doc['inquiry_no']" :state="$doc['sub_state']" :version="$version" :back-url="$backUrl" />
                <x-dl::alert tone="success" data-inq-flash />
                @if ($readonly)<x-dl::alert tone="warn">🔒 Inkuiri ini sudah dikirim ke Costing — dibuka lihat-saja, tidak bisa diedit.</x-dl::alert>@endif
                {{ $slot }}
                <div class="mp-actionbar">
                    <x-dl::button preset="save" type="button" data-inq-save />
                    <span class="mp-actionbar__sum" data-inq-summary>{{ $summary ?? '' }}</span>
                    <span class="mp-grow"></span>
                    <x-dl::button variant="success" icon="➜" :label="$submitLabel" data-inq-submit />
                </div>
            </div>
            BLADE;
    }
}
