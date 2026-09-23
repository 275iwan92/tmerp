<?php

namespace App\View\Components\Ui;

// Label status/kode kecil. variant = tone (lihat Tone::SOFT).
class Badge extends UiComponent
{
    public function __construct(
        string $tone = 'neutral',
        string $size = 'sm',
        public bool $pill = false,
        public bool $mono = false,
        public ?string $icon = null,
    ) {
        $this->variant = $tone;
        $this->size = $size;
    }

    protected function base(): string
    {
        return 'inline-flex items-center gap-1 font-bold whitespace-nowrap';
    }

    protected function variants(): array
    {
        return Tone::SOFT;
    }

    protected function sizes(): array
    {
        return [
            'sm' => 'text-[10px] px-2 py-0.5',
            'xs' => 'text-[9.5px] px-1.5 py-0.5',
            'md' => 'text-[11px] px-2 py-0.5',
        ];
    }

    protected function extra(): string
    {
        return ($this->pill ? 'rounded-full' : 'rounded').($this->mono ? ' font-mono' : '');
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes->merge(['class' => $classes()]) }}>@if ($icon)<i class="fa-solid {{ $icon }} text-[0.85em]"></i>@endif{{ $slot }}</span>
            BLADE;
    }
}
