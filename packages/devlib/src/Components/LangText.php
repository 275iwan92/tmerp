<?php

namespace Devlib\Components;

// Teks antarmuka dari kamus Devlib\\Lang (ID/EN); ikut berganti saat bahasa diganti tanpa muat ulang.
class LangText extends DevlibComponent
{
    public function __construct(
        public string $key = '',
    ) {}

    public function tag(): string
    {
        return 'x-dl::lang-text key='.$this->key;
    }

    public function render()
    {
        return <<<'BLADE'
            <span {{ $attributes }} {!! $dl() !!} data-dl-t="{{ $key }}">{{ \Devlib\Lang::t($key) }}</span>
            BLADE;
    }
}
