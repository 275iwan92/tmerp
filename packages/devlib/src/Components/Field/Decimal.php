<?php

namespace Devlib\Components\Field;

// Angka desimal (otomatis .00 saat keluar) + satuan.
class Decimal extends Field
{
    public function __construct(
        public int $decimals = 2,
        public ?string $unit = null,
        public bool $toggle = true,
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

    // Toggle "00" (0 / 2 / 4 desimal / kalkulator mini) mati sendiri untuk isian terkunci; :toggle="false" bila tidak diinginkan.
    public function showToggle(): bool
    {
        return $this->toggle && ! $this->readonly && ! $this->disabled;
    }

    public function control(): string
    {
        return 'decimal';
    }
}
