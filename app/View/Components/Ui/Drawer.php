<?php

namespace App\View\Components\Ui;

// Drawer kanan -- semua menu dropdown profil memakai ini (keputusan user
// 2026-09-23, menggantikan modal/jendela/tab baru). open = nama flag di
// profileDrawers() (resources/js/app-shell.js), dibuka lewat openDrawer(flag)
// sehingga hanya satu drawer terbuka pada satu waktu.
// SENGAJA <div>, bukan <aside>: CSS sidebar shell (`.shell aside`, `.shell.mini
// aside`, `.shell.nosidebar aside`) ikut mengenai setiap aside -> drawer jadi
// lebar 0 di HP (nosidebar default) / 66px saat sidebar mini.
class Drawer extends UiComponent
{
    public function __construct(
        public string $open,
        public ?string $title = null,
        public ?string $icon = null,
        string $size = 'md',
        public ?string $note = null,
    ) {
        $this->size = $size;
    }

    protected function base(): string
    {
        return 'drawer';
    }

    protected function sizes(): array
    {
        return [
            'md' => '',
            'lg' => 'dr-lg',
            'xl' => 'dr-xl',
            '2xl' => 'dr-2xl',
        ];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }} :class="{ on: {{ $open }} }" role="dialog" aria-label="{{ $title }}">
                <div class="dr-head">
                    @if ($icon)<i class="{{ $icon }} ico"></i>@endif
                    <span class="dr-title">{{ $title }}</span>
                    @if ($note)<span class="dr-note">{{ $note }}</span>@endif
                    @isset($actions){{ $actions }}@endisset
                    <button type="button" class="x" @click="{{ $open }} = false" title="{{ t('Close') }}"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="dr-body">{{ $slot }}</div>
            </div>
            BLADE;
    }
}
