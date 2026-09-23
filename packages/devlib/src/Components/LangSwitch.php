<?php

namespace Devlib\Components;

// Pilihan bahasa (bendera). Memicu event "dl:lang" di dokumen.
class LangSwitch extends DevlibComponent
{
    public function __construct(
        public array $langs = ['id' => ['🇮🇩', 'Bahasa Indonesia'], 'en' => ['🇬🇧', 'English']],
        public string $active = 'id',
    ) {}

    public function tag(): string
    {
        return 'x-dl::lang-switch';
    }

    public function source(): string
    {
        return 'sec_user.lang_code (tmerp)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-langs') }} {!! $dl() !!} data-dl-langs>
                @foreach ($langs as $code => [$flag, $title])
                    <button type="button" class="dl-flag {{ $code === $active ? 'is-active' : '' }}" data-lang="{{ $code }}" title="{{ $title }}">{{ $flag }}</button>
                @endforeach
            </div>
            BLADE;
    }
}
