<?php

namespace Devlib\Components\Field;

// Daftar pasangan nama - nilai (mis. spesifikasi barang) + tambah/hapus baris.
class KeyValue extends Field
{
    public function __construct(
        public string $keyLabel = 'Name',
        public string $valueLabel = 'Value',
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
        return 'key-value';
    }
}
