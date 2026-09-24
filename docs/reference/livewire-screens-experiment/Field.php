<?php

namespace App\Livewire\Screens;

use Closure;

// Satu field header FormScreen. Field ber-key tersimpan lewat
// FormScreen::updateHeader() (wire:change) -- daftar key inilah yang menjadi
// whitelist kolom yang boleh diubah, jadi tidak ada daftar kedua yang bisa basi.
final class Field
{
    public string $kind = 'text';

    public array $options = [];

    public ?string $placeholder = null;

    public int $span = 1;

    public bool $full = false;

    public ?Closure $value = null;

    private function __construct(public ?string $key, public string $label) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public static function date(string $key, string $label): self
    {
        $f = new self($key, $label);
        $f->kind = 'date';

        return $f;
    }

    public static function select(string $key, string $label, array $options): self
    {
        $f = new self($key, $label);
        $f->kind = 'select';
        $f->options = $options;

        return $f;
    }

    // Selalu tampil selebar form, di bawah grid field.
    public static function textarea(string $key, string $label): self
    {
        $f = new self($key, $label);
        $f->kind = 'textarea';
        $f->full = true;

        return $f;
    }

    // Tampilan saja (tak tersimpan), mis. nama supplier dokumen.
    public static function display(string $label, Closure $value): self
    {
        $f = new self(null, $label);
        $f->kind = 'display';
        $f->value = $value;

        return $f;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function span(int $span): self
    {
        $this->span = $span;

        return $this;
    }

    public function valueOf($record): string
    {
        if ($this->value) {
            return (string) ($this->value)($record);
        }

        $v = data_get($record, $this->key);

        return $this->kind === 'date' ? (string) $v?->format('Y-m-d') : (string) $v;
    }
}
