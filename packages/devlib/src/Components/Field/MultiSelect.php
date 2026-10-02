<?php

namespace Devlib\Components\Field;

// Dropdown pilihan ganda bertanda centang.
class MultiSelect extends Field
{
    public function __construct(
        public array $options = [],
        public string $emptyText = 'Choose (0 Selected)',
        public string $noun = 'Selected',
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
        return 'multi-select';
    }
}
