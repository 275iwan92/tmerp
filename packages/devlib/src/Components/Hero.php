<?php

namespace Devlib\Components;

// Panel foto (acak dari daftar) + selubung gelap; isi (brand/jam/kutipan) lewat slot.
class Hero extends DevlibComponent
{
    public function __construct(
        public array $images = [
            'https://upload.wikimedia.org/wikipedia/commons/e/ea/Offset_press_4_color.jpg',
            'https://upload.wikimedia.org/wikipedia/commons/e/ed/Offset_printing_press.jpg',
        ],
    ) {}

    public function tag(): string
    {
        return 'x-dl::hero';
    }

    public function source(): string
    {
        return 'sys_login_hero (rencana mockup)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-hero') }} {!! $dl() !!} data-dl-hero data-images="{{ json_encode($images) }}"><img class="dl-hero__img" alt=""><div class="dl-hero__veil"></div>{{ $slot }}</div>
            BLADE;
    }
}
