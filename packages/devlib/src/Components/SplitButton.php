<?php

namespace Devlib\Components;

// Tombol utama + panah pilihan aksi (mockup form: Save / Download / Export). Aksi terpilih dikirim sebagai input _action.
class SplitButton extends DevlibComponent
{
    public function __construct(
        public array $actions = ['Save' => '💾', 'Download' => '📥', 'Export' => '📤'],
        public string $name = '_action',
    ) {}

    public function tag(): string
    {
        return 'x-dl::split-button';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-split') }} {!! $dl() !!} data-dl-split>
                <input type="hidden" name="{{ $name }}" value="{{ array_key_first($actions) }}" data-dl-split-value>
                <button type="submit" class="dl-split__main"><span data-dl-split-icon>{{ reset($actions) }}</span><span data-dl-split-label>{{ array_key_first($actions) }}</span></button>
                <button type="button" class="dl-split__toggle" data-dl-split-toggle title="{{ \Devlib\Lang::text('Choose an action') }}"><span class="dl-split__arrow"></span></button>
                <div class="dl-split__menu">
                    @foreach ($actions as $label => $icon)
                        <button type="button" class="dl-split__item" data-action="{{ $label }}" data-icon="{{ $icon }}"><span>{{ $icon }}</span> {{ $label }}</button>
                    @endforeach
                </div>
            </div>
            BLADE;
    }
}
