<?php

namespace App\Livewire\Screens;

use Closure;

// Satu kolom grid GridScreen. Layar hanya mendeklarasikan jenis & sumber nilai;
// cara menggambar sel (lebar, rata kanan angka, badge, avatar) diatur di sini
// dan di resources/views/screens/grid.blade.php.
final class Column
{
    public string $kind = 'text';

    public ?string $width = null;

    public bool $small = false;

    public ?Closure $value = null;

    public ?Closure $code = null;

    public array $tones = [];

    public string $format = 'd M Y';

    private function __construct(public string $key, public string $label) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    // Kolom identitas griduistandard.md §3: avatar + nama + kode.
    public function identity(Closure $name, ?Closure $code = null): self
    {
        $this->kind = 'identity';
        $this->value = $name;
        $this->code = $code;

        return $this;
    }

    public function date(string $format = 'd M Y'): self
    {
        $this->kind = 'date';
        $this->format = $format;

        return $this;
    }

    public function money(): self
    {
        $this->kind = 'money';

        return $this;
    }

    // $tones: nilai => tone (App\View\Components\Ui\Tone); nilai lain = neutral.
    public function badge(array $tones): self
    {
        $this->kind = 'badge';
        $this->tones = $tones;

        return $this;
    }

    public function value(Closure $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function small(): self
    {
        $this->small = true;

        return $this;
    }

    public function isNumeric(): bool
    {
        return $this->kind === 'money';
    }

    public function raw($row): mixed
    {
        return $this->value ? ($this->value)($row) : data_get($row, $this->key);
    }

    public function display($row): string
    {
        $v = $this->raw($row);

        return match ($this->kind) {
            'date' => $v ? $v->translatedFormat($this->format) : '',
            'money' => number_format((float) $v, 0, ',', '.'),
            default => (string) $v,
        };
    }

    public function codeOf($row): ?string
    {
        return $this->code ? ($this->code)($row) : null;
    }

    public function tone($row): string
    {
        return $this->tones[$this->raw($row)] ?? 'neutral';
    }
}
