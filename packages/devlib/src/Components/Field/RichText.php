<?php

namespace Devlib\Components\Field;

// Catatan berformat (tebal, miring, garis bawah, daftar). Disimpan sebagai HTML --
// WAJIB disaring (sanitize) di server sebelum disimpan/ditampilkan ke user lain.
class RichText extends Field
{
    public function __construct(
        public int $rows = 5,
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
        return 'rich-text';
    }
}
