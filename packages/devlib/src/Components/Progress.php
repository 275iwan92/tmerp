<?php

namespace Devlib\Components;

// Batang kemajuan; tone otomatis dari nilai bila tone="auto" (>=80 hijau, >=50 kuning, sisanya merah).
class Progress extends DevlibComponent
{
    public function __construct(
        public float $value = 0,
        public string $tone = 'accent',
        public ?string $label = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::progress';
    }

    public function render()
    {
        return <<<'BLADE'
            @php($t = $tone === 'auto' ? ($value >= 80 ? 'success' : ($value >= 50 ? 'warn' : 'danger')) : $tone)
            <div {{ $attributes->class(['dl-progress', 'dl-progress--'.$t]) }} {!! $dl() !!}>
                @if ($label)<div class="dl-progress__label"><span>{{ $label }}</span><b>{{ \Devlib\Locale::percent($value, 0) }}</b></div>@endif
                <div class="dl-progress__track"><div class="dl-progress__bar" style="width:{{ max(0, min(100, $value)) }}%"></div></div>
            </div>
            BLADE;
    }
}
