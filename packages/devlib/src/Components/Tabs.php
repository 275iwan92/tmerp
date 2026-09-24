<?php

namespace Devlib\Components;

// Tab: judul di atas (vertical = daftar di kiri, seperti halaman Setup & Security), isi berganti.
// Isi tiap tab = slot bernama sesuai kunci tab. tabs: [kunci => judul] atau [kunci => [judul, ikon]].
class Tabs extends DevlibComponent
{
    public function __construct(
        public array $tabs = [],
        public ?string $active = null,
        public bool $vertical = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::tabs';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-tabs', 'is-vertical' => $vertical]) }} {!! $dl() !!} data-dl-tabs>
                <div class="dl-tabs__bar" role="tablist">
                    @foreach ($tabs as $key => $title)
                        <button type="button" class="dl-tabs__tab {{ $key === ($active ?? array_key_first($tabs)) ? 'is-active' : '' }}" data-tab="{{ $key }}">@if (is_array($title))<i class="fa-solid {{ \Devlib\Icon::fa($title[1]) }}"></i> {{ $title[0] }}@else{{ $title }}@endif</button>
                    @endforeach
                </div>
                @foreach ($tabs as $key => $title)
                    <div class="dl-tabs__panel {{ $key === ($active ?? array_key_first($tabs)) ? 'is-active' : '' }}" data-panel="{{ $key }}">{{ ${$key} ?? '' }}</div>
                @endforeach
            </div>
            BLADE;
    }
}
