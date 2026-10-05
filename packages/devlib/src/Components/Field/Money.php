<?php

namespace Devlib\Components\Field;

// Nominal uang + pilihan mata uang. words = tampilkan terbilang di bawahnya, mengikuti mata uang terpilih
// (IDR -> "satu juta dua ratus lima puluh ribu rupiah"). Tanpa words = nominal saja.
// decimals = jumlah desimal awal (bawaan 0, rupiah bulat); user mengubahnya lewat toggle "00".
class Money extends Field
{
    public function __construct(
        public array $currencies = [],
        public string $currency = 'IDR',
        public bool $words = false,
        public int $decimals = 0,
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
        return 'money';
    }
}
