<?php

namespace App\View\Components\Ui;

// Baris tombol aksi. footer = strip bawah form yang menempel ke tepi
// (form ber-padding p-5); inline = deret tombol biasa.
class ActionBar extends UiComponent
{
    public function __construct(string $variant = 'footer')
    {
        $this->variant = $variant;
    }

    protected function base(): string
    {
        return 'flex items-center gap-2 flex-wrap';
    }

    protected function variants(): array
    {
        return [
            'footer' => 'mt-4 -mx-5 -mb-5 px-4 py-3.5 bg-surface-alt border-t border-line',
            'inline' => 'mt-3',
            'end' => 'mt-3 justify-end',
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>{{ $slot }}</div>
            BLADE;
    }
}
