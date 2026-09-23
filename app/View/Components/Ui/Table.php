<?php

namespace App\View\Components\Ui;

// Tabel data baku. Gaya th/td ada di app.css (.tm-table, @layer components)
// supaya <th>/<td> polos di dalamnya otomatis seragam; class utilitas per sel
// (lebar, text-right) tetap menang karena layer utilities lebih tinggi.
class Table extends UiComponent
{
    public function __construct(
        string $size = 'md',
        public bool $framed = false,
        public bool $sticky = false,
        public bool $hover = false,
    ) {
        $this->size = $size;
    }

    protected function base(): string
    {
        return 'tm-table w-full border-collapse';
    }

    protected function sizes(): array
    {
        return [
            'md' => 'tm-table-md',
            'sm' => 'tm-table-sm',
        ];
    }

    protected function extra(): string
    {
        return trim(($this->sticky ? 'tm-table-sticky ' : '').($this->hover ? 'tm-table-hover' : ''));
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($framed)
                <div {{ $attributes->merge(['class' => 'border border-line rounded-md overflow-x-auto']) }}>
                    <table class="{{ $classes() }}">{{ $slot }}</table>
                </div>
            @else
                <table {{ $attributes->merge(['class' => $classes()]) }}>{{ $slot }}</table>
            @endif
            BLADE;
    }
}
