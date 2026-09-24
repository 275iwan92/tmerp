<?php

namespace App\Livewire\Screens;

// Tombol di action bar bawah FormScreen. method = nama method Livewire.
final class Action
{
    public ?string $preset = null;

    public string $variant = 'navy';

    public ?string $icon = null;

    public ?string $confirm = null;

    public bool $visible = true;

    private function __construct(public string $method, public ?string $label) {}

    public static function make(string $method, string $label): self
    {
        return new self($method, $label);
    }

    // Aksi baku ber-label LOCKED (x-ui.button preset, CLAUDE.md §3).
    public static function preset(string $preset, string $method): self
    {
        $a = new self($method, null);
        $a->preset = $preset;

        return $a;
    }

    public function variant(string $variant): self
    {
        $this->variant = $variant;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function confirm(string $text): self
    {
        $this->confirm = $text;

        return $this;
    }

    public function when(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }
}
