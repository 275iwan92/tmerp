<?php

namespace Devlib\Components\Field;

// Rating bintang. half = boleh setengah bintang (klik sisi kiri bintang). readonly = tampil saja.
// Klik bintang yang sama lagi = kosongkan.
class Rating extends Field
{
    public function __construct(
        public int $max = 5,
        public bool $half = false,
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
        return 'rating';
    }
}
