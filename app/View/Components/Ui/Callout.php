<?php

namespace App\View\Components\Ui;

// Kotak pesan sebaris di dalam layar (peringatan CAPEX, dokumen ditahan, dst).
// Beda dari x-alert.banner (flash session yang bisa ditutup).
class Callout extends UiComponent
{
    public function __construct(string $tone = 'info', public ?string $icon = null)
    {
        $this->variant = $tone;
    }

    protected function base(): string
    {
        return 'border text-xs rounded-md px-4 py-2.5 mb-4';
    }

    protected function variants(): array
    {
        return [
            'info' => 'bg-info-soft text-info-ink border-info-line',
            'warn' => 'bg-warn-soft text-warn-ink border-warn-line',
            'danger' => 'bg-danger-soft text-danger-ink border-danger-line',
            'success' => 'bg-success-soft text-success-ink border-success-line',
            'highlight' => 'bg-highlight-soft text-highlight-ink border-highlight-line',
            'extra' => 'bg-extra-soft text-extra-ink border-extra-line',
            'neutral' => 'bg-line-soft text-ink-soft border-line',
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>@if ($icon){{ $icon }} @endif{{ $slot }}</div>
            BLADE;
    }
}
