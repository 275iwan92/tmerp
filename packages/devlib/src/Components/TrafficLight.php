<?php

namespace Devlib\Components;

// Lampu status (scorecard): green | amber | red (+ label & keterangan). Dipakai di baris KPI / scorecard.
class TrafficLight extends DevlibComponent
{
    public function __construct(
        public string $status = 'green',
        public string $label = '',
        public ?string $note = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::traffic-light status='.$this->status;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-light', 'is-'.$status]) }} {!! $dl() !!}>
                <span class="dl-light__box"><i class="is-red"></i><i class="is-amber"></i><i class="is-green"></i></span>
                <span class="dl-light__txt"><b>{{ $label }}</b>@if ($note)<small>{{ $note }}</small>@endif</span>
            </div>
            BLADE;
    }
}
