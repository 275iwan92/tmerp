<?php

namespace App\View\Components\Ui;

// Popup baku. Tampil/tidaknya tetap diatur @if di pemanggil (state Livewire),
// close = ekspresi wire:click penutup, mis. "closeContactModal" atau
// "$set('showX', false)".
class Modal extends UiComponent
{
    public function __construct(
        public ?string $title = null,
        public ?string $close = null,
        string $size = 'md',
    ) {
        $this->size = $size;
    }

    protected function base(): string
    {
        return 'bg-surface rounded-xl max-w-[92vw] max-h-[90vh] overflow-y-auto shadow-2xl';
    }

    protected function sizes(): array
    {
        return [
            'md' => 'w-[480px]',
            'xs' => 'w-[360px]',
            'sm' => 'w-[420px]',
            'lg' => 'w-[600px]',
            'xl' => 'w-[720px]',
            '2xl' => 'w-[960px]',
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div class="fixed inset-0 bg-overlay z-[100] flex items-center justify-center p-5" @if ($close) wire:click.self="{{ $close }}" @endif>
                <div {{ $attributes->merge(['class' => $classes()]) }}>
                    @if ($title || isset($header))
                        <div class="bg-navy text-white px-4 py-3.5 text-sm font-semibold flex items-center gap-2 sticky top-0 z-[1]">
                            <span>{{ $header ?? $title }}</span>
                            @if ($close)
                                <button type="button" class="ml-auto cursor-pointer opacity-85 hover:opacity-100 bg-transparent border-0 text-white" wire:click="{{ $close }}" aria-label="Tutup">✕</button>
                            @endif
                        </div>
                    @endif
                    {{ $slot }}
                    @isset($footer)
                        <div class="flex gap-2 justify-end px-4 py-3 border-t border-line-soft">{{ $footer }}</div>
                    @endisset
                </div>
            </div>
            BLADE;
    }
}
