<?php

namespace Devlib\Components\Field;

// Kode OTP per digit, fokus pindah otomatis.
class Otp extends Field
{
    public function __construct(
        public int $length = 6,
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
        return 'otp';
    }
}
