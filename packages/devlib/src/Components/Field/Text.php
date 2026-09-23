<?php

namespace Devlib\Components\Field;

// Teks satu baris / banyak baris (auto-tinggi) + tombol huruf aa/Aa/AA.
class Text extends Field
{
    public function __construct(
        public bool $multiline = false,
        public bool $caseToggle = true,
        public string $type = 'text',
        public ?string $autocomplete = null,
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
        return 'text';
    }
}
