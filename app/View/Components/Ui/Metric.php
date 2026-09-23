<?php

namespace App\View\Components\Ui;

// Kotak angka ringkas (KPI kecil di dalam form/detail). size lg = angka
// besar (OEE), sm = baris label+nilai (Stop mesin, MTTR).
class Metric extends UiComponent
{
    public function __construct(
        public ?string $label = null,
        public ?string $hint = null,
        string $tone = 'neutral',
        string $size = 'sm',
        public bool $emphasis = false,
    ) {
        $this->variant = $tone;
        $this->size = $size;
    }

    protected function base(): string
    {
        return $this->emphasis ? 'rounded border-2 border-accent bg-accent-soft' : 'rounded border border-line-soft bg-surface-alt';
    }

    protected function sizes(): array
    {
        return [
            'sm' => 'px-2.5 py-2',
            'lg' => 'p-2.5',
        ];
    }

    public function valueClass(): string
    {
        return ($this->size === 'lg' ? 'text-[20px] font-bold leading-tight ' : 'font-mono font-bold ')
            .($this->variant === 'neutral' ? 'text-ink' : Tone::text($this->variant));
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>
                @if ($label)<span class="text-muted text-[10px] block uppercase tracking-wide">{{ $label }}</span>@endif
                <div class="{{ $valueClass() }}">{{ $slot }}</div>
                @isset($footer)<div class="mt-1">{{ $footer }}</div>@endisset
                @if ($hint)<span class="block text-[9.5px] text-muted mt-1">{{ $hint }}</span>@endif
            </div>
            BLADE;
    }
}
