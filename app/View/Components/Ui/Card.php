<?php

namespace App\View\Components\Ui;

// Kotak berbingkai. variant: section (grup field di dalam form, judul kecil
// uppercase aksen), panel (kontainer utama layar), plain (tanpa judul).
class Card extends UiComponent
{
    public function __construct(
        string $variant = 'section',
        string $size = 'md',
        public ?string $title = null,
        public ?string $description = null,
    ) {
        $this->variant = $variant;
        $this->size = $size;
    }

    protected function base(): string
    {
        return 'border';
    }

    protected function variants(): array
    {
        return [
            'section' => 'bg-surface border-line rounded-md',
            'panel' => 'bg-surface border-line rounded-[10px] overflow-hidden',
            'muted' => 'bg-surface-alt border-line rounded-md',
            'plain' => 'bg-surface-alt border-line-soft rounded',
        ];
    }

    protected function sizes(): array
    {
        return [
            'md' => 'p-3',
            'sm' => 'p-2.5',
            'none' => '',
        ];
    }

    public function render()
    {
        return view('components.ui.card');
    }
}
