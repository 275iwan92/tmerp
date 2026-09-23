<?php

namespace Devlib\Components;

// Tombol baku. icon = arti baku (save, delete, print... lihat Devlib\Icon) atau emoji. href terisi = dirender <a>. variant link = tampil sebagai tautan.
class Button extends DevlibComponent
{
    public function __construct(
        public string $label = '',
        public string $variant = 'primary',
        public string $size = 'md',
        public string $type = 'button',
        public ?string $href = null,
        public ?string $icon = null,
        public bool $block = false,
        public ?string $i18n = null,
        public ?string $preset = null,
    ) {
        if ($preset && isset(self::PRESETS[$preset])) {
            [$id, $en, $variant, $icon, $type] = self::PRESETS[$preset];
            $this->label = $this->label !== '' ? $this->label : (\Devlib\Locale::lang() === 'en' ? $en : $id);
            $this->variant = $this->variant === 'primary' ? $variant : $this->variant;
            $this->icon ??= $icon;
            $this->type = $this->type === 'button' ? $type : $this->type;
        }
    }

    // Aksi umum berlabel BAKU (LOCKED): satu kata sama di semua layar, ikut bahasa.
    // preset => [label ID, label EN, variant, ikon, type]
    public const PRESETS = [
        'save' => ['Simpan', 'Save', 'primary', 'save', 'submit'],
        'cancel' => ['Batal', 'Cancel', 'secondary', 'cancel', 'button'],
        'close' => ['Tutup', 'Close', 'secondary', 'close', 'button'],
        'confirm' => ['Confirm', 'Confirm', 'success', 'confirm', 'button'],
        'delete' => ['Hapus', 'Delete', 'danger', 'delete', 'button'],
        'new' => ['+ New Record', '+ New Record', 'primary', null, 'button'],
        'apply' => ['Terapkan', 'Apply', 'primary', null, 'button'],
        'print' => ['Cetak', 'Print', 'secondary', 'print', 'button'],
        'submit' => ['Ajukan', 'Submit', 'primary', 'submit', 'submit'],
        'approve' => ['Setujui', 'Approve', 'success', 'approve', 'button'],
        'reject' => ['Tolak', 'Reject', 'danger', 'reject', 'button'],
        'hold' => ['Tahan', 'Hold', 'secondary', 'hold', 'button'],
    ];

    public function tag(): string
    {
        return 'x-dl::button '.($this->preset ? 'preset='.$this->preset.' ' : '').'variant='.$this->variant.' size='.$this->size;
    }

    public function classes(): string
    {
        return 'dl-btn dl-btn--'.$this->variant.' dl-btn--'.$this->size.($this->block ? ' dl-btn--block' : '');
    }

    public function render()
    {
        return <<<'BLADE'
            @if ($href)
                <a href="{{ $href }}" {{ $attributes->class($classes()) }} {!! $dl() !!} @if ($i18n) data-i18n="{{ $i18n }}" @endif @if ($preset) data-dl-preset="{{ $preset }}" @endif>@if ($icon)<span class="dl-btn__icon">@if (isset(\Devlib\Icon::MAP[$icon]))<i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i>@else{{ $icon }}@endif</span>@endif{{ $label }}{{ $slot }}</a>
            @else
                <button type="{{ $type }}" {{ $attributes->class($classes()) }} {!! $dl() !!} @if ($i18n) data-i18n="{{ $i18n }}" @endif @if ($preset) data-dl-preset="{{ $preset }}" @endif>@if ($icon)<span class="dl-btn__icon">@if (isset(\Devlib\Icon::MAP[$icon]))<i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i>@else{{ $icon }}@endif</span>@endif{{ $label }}{{ $slot }}</button>
            @endif
            BLADE;
    }
}
