<?php

namespace App\Livewire\Screens;

// Satu blok isi FormScreen, dirender berurutan. fields = kartu field standar;
// view = blok khas layar (tabel baris, ringkasan pajak) dalam partial Blade
// yang tetap melihat $this komponen.
final class Section
{
    public bool $visible = true;

    private function __construct(public string $type, public ?string $title, public array $fields = [], public ?string $view = null) {}

    /** @param  Field[]  $fields */
    public static function fields(string $title, array $fields): self
    {
        return new self('fields', $title, $fields);
    }

    public static function view(string $view): self
    {
        return new self('view', null, [], $view);
    }

    public function when(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }
}
