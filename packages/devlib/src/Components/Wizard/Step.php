<?php

namespace Devlib\Components\Wizard;

use Devlib\Components\DevlibComponent;

// Satu langkah di dalam x-dl::wizard. icon = arti baku (Devlib\Icon) atau kelas fa-*.
class Step extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $icon = null,
        public ?string $hint = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::wizard.step';
    }

    public function render()
    {
        return <<<'BLADE'
            <section {{ $attributes->class('dl-wizard__step') }} {!! $dl() !!} data-w-step data-title="{{ $title }}" data-icon="{{ $icon ? \Devlib\Icon::fa($icon) : '' }}">
                @if ($hint)<p class="dl-wizard__hint">{{ $hint }}</p>@endif
                {{ $slot }}
            </section>
            BLADE;
    }
}
