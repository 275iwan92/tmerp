<?php

namespace App\View\Components\Ui;

// Jendela popup (geser/perkecil/perbesar/tutup), perilaku di resources/js/ui-window.js.
// open = nama flag Alpine di shell pembungkus (appShell/reportShell).
class Window extends UiComponent
{
    public function __construct(
        public string $open,
        public string $id,
        public ?string $title = null,
        public ?string $icon = null,
        public int $width = 720,
        public int $height = 500,
    ) {}

    protected function base(): string
    {
        return 'tm-win';
    }

    public function render()
    {
        return view('components.ui.window');
    }
}
