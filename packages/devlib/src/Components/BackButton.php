<?php

namespace Devlib\Components;

// Tombol Back baku (label SELALU "Back", LOCKED): kembali ke layar sebelumnya; fallback = alamat bila tak ada riwayat.
class BackButton extends DevlibComponent
{
    public function __construct(
        public string $fallback = '/',
    ) {}

    public function tag(): string
    {
        return 'x-dl::back-button';
    }

    public function render()
    {
        return <<<'BLADE'
            <a href="{{ $fallback }}" {{ $attributes->class('dl-back') }} {!! $dl() !!} data-dl-back title="{{ \Devlib\Lang::text('Back to the previous screen') }}"><span>Back</span><i class="fa-solid fa-arrow-left"></i></a>
            BLADE;
    }
}
