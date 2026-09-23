<?php

namespace Devlib\Components;

// Bingkai pratinjau Laptop / Tablet (768px) / Smartphone (390px) dari mockup form.
class DeviceFrame extends DevlibComponent
{
    public function __construct(
        public string $mode = 'laptop',
    ) {}

    public function tag(): string
    {
        return 'x-dl::device-frame';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-device') }} {!! $dl() !!} data-dl-device>
                <div class="dl-device__bar">
                    <span class="dl-device__title">Mode Tampilan:</span>
                    <button type="button" class="dl-device__btn {{ $mode === 'laptop' ? 'is-active' : '' }}" data-mode="laptop"><span>🖥️</span> Laptop / Desktop (Full)</button>
                    <button type="button" class="dl-device__btn {{ $mode === 'tablet' ? 'is-active' : '' }}" data-mode="tablet"><span>📱</span> Tablet (768px)</button>
                    <button type="button" class="dl-device__btn {{ $mode === 'mobile' ? 'is-active' : '' }}" data-mode="mobile"><span>📱</span> Smartphone (390px)</button>
                </div>
                <div class="dl-device__stage"><div class="dl-device__view is-{{ $mode }}" data-dl-device-view>{{ $slot }}</div></div>
            </div>
            BLADE;
    }
}
