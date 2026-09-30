<?php

namespace Devlib\Components\Field;

// Geser nilai. range = dua gagang (min-max; nilai kedua dikirim sebagai name_to / nameTo).
// orientation: horizontal | vertical. ticks = jumlah label skala (0 = tanpa).
class Slider extends Field
{
    public function __construct(
        public int|float $min = 0,
        public int|float $max = 100,
        public int|float $step = 1,
        public string $format = '{v}',
        public bool $range = false,
        public mixed $valueTo = null,
        public ?string $nameTo = null,
        public string $orientation = 'horizontal',
        public string $height = '180px',
        public int $ticks = 0,
        string $name = '',
        string $label = '',
        ?string $tooltip = null,
        ?string $hint = null,
        ?string $error = null,
        mixed $value = null,
        ?string $placeholder = null,
        bool $required = false,
        bool $disabled = false,
        bool $readonly = false,
        ?string $icon = null,
        string $size = 'sm',
        ?string $i18n = null,
        ?string $source = null,
    ) {
        parent::__construct($name, $label, $tooltip, $hint, $error, $value, $placeholder, $required, $disabled, $readonly, $icon, $size, $i18n, $source);
    }

    public function control(): string
    {
        return 'slider';
    }

    public function kind(): string
    {
        return 'number';
    }

    public function start(): string
    {
        return '';
    }

    public function fromValue(): int|float
    {
        return $this->value ?? $this->min;
    }

    public function toValue(): int|float
    {
        return $this->valueTo ?? $this->max;
    }

    // [[posisi %, label], ...]
    public function tickList(): array
    {
        if ($this->ticks < 2) {
            return [];
        }
        $out = [];
        for ($i = 0; $i < $this->ticks; $i++) {
            $v = $this->min + ($this->max - $this->min) * $i / ($this->ticks - 1);
            $out[] = [round($i / ($this->ticks - 1) * 100, 2), self::compact($v)];
        }

        return $out;
    }

    public static function compact(int|float $v): string
    {
        foreach ([[1e12, ' T'], [1e9, ' M'], [1e6, ' jt'], [1e3, ' rb']] as [$d, $s]) {
            if (abs($v) >= $d) {
                return \Devlib\Locale::decimal($v / $d, 1).$s;
            }
        }

        return \Devlib\Locale::number($v, $v == (int) $v ? 0 : 1);
    }
}
