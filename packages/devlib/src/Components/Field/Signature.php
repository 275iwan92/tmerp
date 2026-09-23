<?php

namespace Devlib\Components\Field;

// Tanda tangan: kanvas mouse / pena / jari. Nilai = gambar PNG (data URL) di input tersembunyi.
// Undo per goresan, Ulangi = kosongkan, Unduh = file PNG.
class Signature extends Field
{
    public function __construct(
        public int $height = 170,
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
        return 'signature';
    }
}
