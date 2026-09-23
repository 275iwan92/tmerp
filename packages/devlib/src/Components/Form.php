<?php

namespace Devlib\Components;

// Induk form (mockup UIUX_components_part1.html): judul + isi (kolom/baris field) + kaki berisi tombol tambahan opsional dan tombol split aksi.
class Form extends DevlibComponent
{
    public function __construct(
        public string $title = '',
        public ?string $action = null,
        public string $method = 'POST',
        public array $actions = ['Save' => '💾', 'Download' => '📥', 'Export' => '📤'],
        public ?string $extraAction = null,
        public string $extraIcon = '📱',
        public bool $footer = true,
        public bool $guard = true,
    ) {}

    public function tag(): string
    {
        return 'x-dl::form';
    }

    public function render()
    {
        return <<<'BLADE'
            <form {{ $attributes->class('dl-form') }} {!! $dl() !!} method="{{ $method === 'GET' ? 'GET' : 'POST' }}" @if ($action) action="{{ $action }}" @endif data-dl-form @if ($guard) data-dl-guard @endif novalidate>
                @if ($method !== 'GET')
                    @csrf
                @endif
                @if ($title !== '')
                    <div class="dl-form__head"><div class="dl-form__title">{{ $title }}</div></div>
                @endif
                <div class="dl-form__body">{{ $slot }}</div>
                @if ($footer)
                    <div class="dl-form__foot {{ $extraAction ? '' : 'is-single' }}">
                        @if ($extraAction)
                            <x-dl::button variant="success" :icon="$extraIcon" :label="$extraAction" block data-dl-form-extra />
                        @endif
                        <x-dl::split-button :actions="$actions" />
                    </div>
                @endif
            </form>
            BLADE;
    }
}
