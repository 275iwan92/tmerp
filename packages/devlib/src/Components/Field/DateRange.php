<?php

namespace Devlib\Components\Field;

// Rentang tanggal awal s.d. akhir + durasi otomatis.
class DateRange extends Field
{
    public function __construct(
        public ?string $nameEnd = null,
        public mixed $valueEnd = null,
        public bool $duration = true,
        string $name = '',
        string $label = '',
        ?string $tooltip = null,
        ?string $hint = null,
        ?string $error = null,
        mixed $value = null,
        ?string $placeholder = null,
        bool $required = false,
        bool $disabled = false,
        ?string $icon = null,
        string $size = 'sm',
        ?string $i18n = null,
        ?string $source = null,
    ) {
        parent::__construct($name, $label, $tooltip, $hint, $error, $value, $placeholder, $required, $disabled, $icon, $size, $i18n, $source);
    }

    public function control(): string
    {
        return 'date-range';
    }
}
