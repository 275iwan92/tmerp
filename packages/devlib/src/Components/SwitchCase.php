<?php

/*
 * REMARK
 * Tujuan       : Satu panel milik x-dl::switcher; tampil hanya bila pilihan dropdown sama dengan value-nya.
 * Sumber hulu  : Isi dari layar pemanggil.
 * Input        : value (kunci pilihan di x-dl::switcher), slot isi panel.
 * Output       : <div data-sw-case="value"> ... </div>; disembunyikan oleh JS Switcher (tanpa JS semua panel tampil).
 * Dipakai oleh : x-dl::switcher (tab Job layar hrm-employee-form); demo devlib.test > Status > Switcher.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class SwitchCase extends DevlibComponent
{
    public function __construct(public string $value = '') {}

    public function tag(): string
    {
        return 'x-dl::switch-case';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-switcher__case') }} {!! $dl() !!} data-sw-case="{{ $value }}">{{ $slot }}</div>
            BLADE;
    }
}
