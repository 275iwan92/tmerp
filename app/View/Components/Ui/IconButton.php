<?php

namespace App\View\Components\Ui;

// Turunan Button: warna/perilaku diwarisi, hanya ukurannya persegi.
class IconButton extends Button
{
    public function __construct(
        string $icon,
        string $variant = 'ghost',
        string $size = 'md',
        ?string $type = null,
        ?string $href = null,
        bool $active = false,
    ) {
        parent::__construct(variant: $variant, size: $size, type: $type, href: $href, icon: $icon, active: $active);
    }

    protected function sizes(): array
    {
        return [
            'md' => 'w-8 h-8 text-xs',
            'sm' => 'w-7 h-7 text-[11px]',
            'xs' => 'w-6 h-6 text-[10px]',
        ];
    }
}
