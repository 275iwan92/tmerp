<?php

namespace Devlib\Components\Form;

use Devlib\Components\DevlibComponent;

// Satu kolom form: field disusun ke bawah.
class Col extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::form.col';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-form__col') }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}
