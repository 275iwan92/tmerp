<?php

namespace App\View\Components\Ui;

// Strip judul di atas form detail (bukan header halaman -- NAV-SHELL-CONTRACT
// melarang halaman merender header/chrome sendiri).
class PanelHeader extends UiComponent
{
    public function __construct(public ?string $title = null, string $variant = 'navy')
    {
        $this->variant = $variant;
    }

    protected function base(): string
    {
        return 'flex justify-between items-center gap-3 px-4 py-3 rounded-t-md';
    }

    protected function variants(): array
    {
        return [
            'navy' => 'bg-navy text-white',
            'light' => 'bg-line-soft text-ink border border-line border-b-0',
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>
                <div class="text-sm font-bold">{{ $title }}{{ $slot }}</div>
                @isset($aside)<div class="text-[11px] opacity-80">{{ $aside }}</div>@endisset
            </div>
            BLADE;
    }
}
