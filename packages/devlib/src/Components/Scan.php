<?php

namespace Devlib\Components;

// Kotak pindai QR / barcode. Scanner USB / Bluetooth bekerja seperti keyboard: kode masuk ke kotak lalu Enter. Di HP
// (halaman https) tombol kamera memindai lewat kamera belakang (BarcodeDetector); peramban yang tidak mendukung cukup
// mengetik kodenya. Hasil pindai: bila $action diisi, form GET dikirim (?<name>=kode); selalu ada event dl:scan {code}.
class Scan extends DevlibComponent
{
    protected const TEXT_PROPS = ['label', 'placeholder', 'hint'];

    public function __construct(
        public string $name = 'code',
        public ?string $label = null,
        public string $placeholder = 'Scan or type a code, then Enter',
        public ?string $value = null,
        public ?string $action = null,
        public ?string $hint = null,
        public bool $autofocus = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::scan name='.$this->name;
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class('dl-scan') }} {!! $dl() !!} data-dl-scan method="GET" @if ($action) action="{{ $action }}" @endif>
                @if ($label)<label class="dl-scan__label" for="dl-scan-{{ $name }}">{{ $label }}</label>@endif
                <div class="dl-scan__box">
                    <i class="fa-solid fa-barcode"></i>
                    <input type="text" id="dl-scan-{{ $name }}" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder }}" autocomplete="off" autocapitalize="characters" spellcheck="false" data-scan-input @if ($autofocus) autofocus @endif>
                    <button type="button" class="dl-scan__cam" data-scan-cam hidden title="{{ \Devlib\Lang::text('Scan with the camera') }}"><i class="fa-solid fa-camera"></i></button>
                    <button type="submit" class="dl-btn dl-btn--primary dl-btn--md"><i class="fa-solid fa-magnifying-glass"></i> {{ \Devlib\Lang::text('Find') }}</button>
                </div>
                @if ($hint)<small class="dl-scan__hint">{{ $hint }}</small>@endif
                <div class="dl-scan__video" data-scan-video hidden><video playsinline muted></video><button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-scan-stop>{{ \Devlib\Lang::text('Close camera') }}</button></div>
            </form>
            BLADE;
    }
}
