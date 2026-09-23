<?php

namespace Devlib\Components\Field;

// Teks satu baris / banyak baris (auto-tinggi) + tombol huruf aa/Aa/AA.
// Badge aa/Aa/AA WAJIB (LOCKED docs/FORM_UI_STANDARD.md 1b); mati sendiri hanya untuk
// readonly, disabled, dan type non-teks. Opt-out :case-toggle="false" hanya kotak cari
// atau alasan khusus yang ditulis di pemanggil. code=true = huruf mono untuk kode.
class Text extends Field
{
    public function __construct(
        public bool $multiline = false,
        public bool $caseToggle = true,
        public string $type = 'text',
        public ?string $autocomplete = null,
        public bool $code = false,
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

    public function showCase(): bool
    {
        return $this->caseToggle && ! $this->readonly && ! $this->disabled && ! in_array($this->type, ['email', 'number', 'url', 'tel'], true);
    }

    public function control(): string
    {
        return 'text';
    }
}
