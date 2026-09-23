<?php

namespace Devlib\Components\Field;

// Geser nilai (range) + tampilan nilai berformat.
class Slider extends Field
{
    public function __construct(
        public int|float $min = 0,
        public int|float $max = 100,
        public int|float $step = 1,
        public string $format = '{v}',
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
}
