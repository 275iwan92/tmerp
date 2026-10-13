<?php

/*
 * REMARK
 * Tujuan       : Panel lipat DevLib dengan ringkasan di header (summary), lencana berwarna (tone), key dan maxHeight.
 * Sumber hulu  : Komponen generik DevLib, tanpa tabel.
 * Input        : title, summary, badge, tone, key, open, maxHeight, slot isi.
 * Output       : Markup .dl-accordion (perilaku buka/tutup di core.js).
 * Dipakai oleh : x-dl::accordion-group; Form WO (production-wo).
 * Status       : Aktif (DevLib).
 */

namespace Devlib\Components;

// Satu panel lipat. Sendiri = vertikal biasa; di dalam x-dl::accordion-group ikut arah & aturan grupnya.
class Accordion extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public bool $open = false,
        public ?string $icon = null,
        public ?string $badge = null,
        public ?string $summary = null,   // text on the right of the header, readable without opening (e.g. "8 processes · 13 Oct - 16 Oct")
        public string $tone = 'info',     // badge colour: info | ok | warn | danger
        public ?string $key = null,       // identity inside its group (used to remember what is open)
        public ?string $maxHeight = null, // body scrolls inside this height (e.g. 620px)
    ) {}

    public function tag(): string
    {
        return 'x-dl::accordion';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-accordion', 'is-open' => $open, 'has-summary' => $summary !== null]) }} {!! $dl() !!} data-dl-acc @if ($key) data-acc-key="{{ $key }}" @endif>
                <button type="button" class="dl-accordion__head" aria-expanded="{{ $open ? 'true' : 'false' }}">
                    @if ($icon)<i class="fa-solid {{ \Devlib\Icon::fa($icon) }} dl-accordion__icon"></i>@endif
                    <span class="dl-accordion__title">{{ $title }}</span>
                    @if ($summary !== null)<span class="dl-accordion__summary">{{ $summary }}</span>@endif
                    @if ($badge !== null)<span class="dl-accordion__badge dl-accordion__badge--{{ $tone }}">{{ $badge }}</span>@endif
                    <i class="fa-solid fa-chevron-down dl-accordion__chev"></i>
                </button>
                <div class="dl-accordion__body"><div class="dl-accordion__inner"><div class="dl-accordion__pad" @if ($maxHeight) style="max-height:{{ $maxHeight }};overflow:auto" @endif>{{ $slot }}</div></div></div>
            </div>
            BLADE;
    }
}
