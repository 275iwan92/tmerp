<?php

namespace Devlib\Components\Field;

// Kartu kredit/debit: nomor ber-mask per jenis (Visa/Mastercard/Amex/JCB/Discover/Diners/UnionPay),
// cek Luhn, masa berlaku MM/YY (tidak boleh lewat, WIB), CVV 3/4 digit, nama pemegang (badge huruf).
// Terkirim: name, name_exp, name_cvv, name_holder, name_brand.
class CreditCard extends Field
{
    public function __construct(
        public bool $holder = true,
        public bool $preview = true,
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
        return 'credit-card';
    }
}
