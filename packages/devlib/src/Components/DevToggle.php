<?php

namespace Devlib\Components;

// Saklar Mode Developer (tooltip asal-usul objek + tanda merah objek non-library). Hanya untuk admin/developer.
class DevToggle extends DevlibComponent
{
    public function __construct(
        public bool $floating = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::dev-toggle';
    }

    public function render()
    {
        return <<<'BLADE'
            <button type="button" {{ $attributes->class(['dl-devtoggle', 'is-floating' => $floating]) }} {!! $dl() !!} data-dl-devtoggle title="{{ \Devlib\Lang::text('Developer Mode') }} (F10)"><i class="fa-solid fa-bug"></i><span>{{ \Devlib\Lang::text('Developer Mode') }}</span><span class="dl-um-switch" data-dl-devswitch></span></button>
            BLADE;
    }
}
