<?php

namespace App\View\Components\Ui;

use Illuminate\View\Component;

// Induk semua primitive UI (docs/componentstandard.md). Subclass hanya
// mendeklarasikan base/variants/sizes; classes() merangkainya. Ubah di
// subclass = semua pemakai tag itu ikut; ubah di sini = semua primitive ikut.
abstract class UiComponent extends Component
{
    public string $variant = '';

    public string $size = '';

    protected function base(): string
    {
        return '';
    }

    /** @return array<string, string> */
    protected function variants(): array
    {
        return [];
    }

    /** @return array<string, string> */
    protected function sizes(): array
    {
        return [];
    }

    protected function extra(): string
    {
        return '';
    }

    public function classes(): string
    {
        $variants = $this->variants();
        $sizes = $this->sizes();

        return trim(implode(' ', array_filter([
            $this->base(),
            $variants[$this->variant] ?? ($variants ? reset($variants) : ''),
            $sizes[$this->size] ?? ($sizes ? reset($sizes) : ''),
            $this->extra(),
        ])));
    }
}
