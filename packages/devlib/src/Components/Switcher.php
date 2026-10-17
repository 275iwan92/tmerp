<?php

/*
 * REMARK
 * Tujuan       : Dropdown yang menukar panel di bawahnya tanpa memuat ulang (mis. peran / jabatan -> job description dan KPI jabatan itu).
 * Sumber hulu  : Daftar pilihan dan isi panel dari layar pemanggil.
 * Input        : label, options [value => label], value (pilihan awal; kosong = yang pertama); slot berisi x-dl::switch-case value="...".
 * Output       : <div data-dl-switcher> dengan <select> (tanpa name, tidak ikut terkirim) dan panel; hanya panel terpilih yang tampil.
 * Dipakai oleh : resources/views/v2/screens/hrm-employee-form.blade.php (tab Job); demo devlib.test > Status > Switcher.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class Switcher extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public array $options = [],
        public ?string $value = null,
    ) {
        $this->value ??= (string) array_key_first($options);
    }

    public function tag(): string
    {
        return 'x-dl::switcher';
    }

    public function jsClass(): ?string
    {
        return 'Switcher';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-switcher') }} {!! $dl() !!} data-dl-switcher>
                <div class="dl-switcher__bar">
                    <label>{{ $label }}
                        <select data-sw-select>
                            @foreach ($options as $k => $text)<option value="{{ $k }}" @selected((string) $k === $value)>{{ $text }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <div class="dl-switcher__body" data-value="{{ $value }}">{{ $slot }}</div>
            </div>
            BLADE;
    }
}
