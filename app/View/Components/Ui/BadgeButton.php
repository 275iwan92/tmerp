<?php

namespace App\View\Components\Ui;

// Badge yang bisa diklik (toggle status di baris tabel, mis. "★ Utama").
// Tampilan diwarisi dari Badge, hanya elemennya <button>.
class BadgeButton extends Badge
{
    protected function base(): string
    {
        return parent::base().' cursor-pointer border-0 hover:brightness-95';
    }

    public function render()
    {
        return <<<'BLADE'
            <button type="button" {{ $attributes->merge(['class' => $classes()]) }}>@if ($icon)<i class="fa-solid {{ $icon }} text-[0.85em]"></i>@endif{{ $slot }}</button>
            BLADE;
    }
}
