<?php

namespace Devlib\Components;

// Tab: judul di atas, isi berganti. Isi tiap tab = slot bernama sesuai kunci tab.
class Tabs extends DevlibComponent
{
    public function __construct(
        public array $tabs = [],
        public ?string $active = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::tabs';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-tabs') }} {!! $dl() !!} data-dl-tabs>
                <div class="dl-tabs__bar" role="tablist">
                    @foreach ($tabs as $key => $title)
                        <button type="button" class="dl-tabs__tab {{ $key === ($active ?? array_key_first($tabs)) ? 'is-active' : '' }}" data-tab="{{ $key }}">{{ $title }}</button>
                    @endforeach
                </div>
                @foreach ($tabs as $key => $title)
                    <div class="dl-tabs__panel {{ $key === ($active ?? array_key_first($tabs)) ? 'is-active' : '' }}" data-panel="{{ $key }}">{{ ${$key} ?? '' }}</div>
                @endforeach
            </div>
            BLADE;
    }
}
