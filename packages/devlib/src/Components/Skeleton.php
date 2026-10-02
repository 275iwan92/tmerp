<?php

namespace Devlib\Components;

// Kerangka abu-abu berdenyut selama data dimuat. type: text | table | card | form.
class Skeleton extends DevlibComponent
{
    public function __construct(
        public string $type = 'text',
        public int $lines = 3,
        public int $rows = 5,
        public int $cols = 4,
    ) {}

    public function tag(): string
    {
        return 'x-dl::skeleton type='.$this->type;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-skel', 'dl-skel--'.$type]) }} {!! $dl() !!} aria-busy="true" aria-label="{{ \Devlib\Lang::text('Loading...') }}">
                @switch($type)
                    @case('table')
                        @for ($r = 0; $r <= $rows; $r++)<div class="dl-skel__row">@for ($c = 0; $c < $cols; $c++)<span class="dl-skel__bar {{ $r === 0 ? 'is-head' : '' }}"></span>@endfor</div>@endfor
                        @break
                    @case('card')
                        <div class="dl-skel__card"><span class="dl-skel__circle"></span><div>@for ($i = 0; $i < 3; $i++)<span class="dl-skel__bar" style="width:{{ [70, 90, 50][$i] }}%"></span>@endfor</div></div>
                        @break
                    @case('form')
                        @for ($i = 0; $i < $lines; $i++)<div class="dl-skel__field"><span class="dl-skel__bar" style="width:30%"></span><span class="dl-skel__bar is-input"></span></div>@endfor
                        @break
                    @default
                        @for ($i = 0; $i < $lines; $i++)<span class="dl-skel__bar" style="width:{{ $i === $lines - 1 ? 60 : 100 }}%"></span>@endfor
                @endswitch
            </div>
            BLADE;
    }
}
