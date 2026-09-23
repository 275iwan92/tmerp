<?php

namespace Devlib\Components\Form;

use Devlib\Components\DevlibComponent;

// Satu baris form: field berdampingan (default 2 sama lebar).
class Row extends DevlibComponent
{
    public function __construct(
        public int $cols = 2,
    ) {}

    public function tag(): string
    {
        return 'x-dl::form.row cols='.$this->cols;
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-form__row') }} {!! $dl() !!} style="--dl-cols:{{ $cols }}">{{ $slot }}</div>
            BLADE;
    }
}
