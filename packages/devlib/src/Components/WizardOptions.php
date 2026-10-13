<?php

/*
 * REMARK
 * Tujuan       : Pembungkus daftar pilihan wizard (x-dl::wizard-option) di dalam modal.
 * Sumber hulu  : -
 * Input        : slot berisi x-dl::wizard-option.
 * Output       : <div class="dl-wiz"> ... </div> (kolom, jarak 10px; gaya .dl-wiz di devlib.css).
 * Dipakai oleh : screens/inquiry.blade.php.
 * Status       : Aktif.
 */

namespace Devlib\Components;

class WizardOptions extends DevlibComponent
{
    public function tag(): string
    {
        return 'x-dl::wizard-options';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-wiz') }} {!! $dl() !!}>{{ $slot }}</div>
            BLADE;
    }
}
