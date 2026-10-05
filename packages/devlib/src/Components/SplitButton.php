<?php

namespace Devlib\Components;

// Tombol utama + panah pilihan aksi (mockup form: Save / Download / Export). Aksi terpilih dikirim sebagai input _action.
// actions: label => ikon Font Awesome (fa-floppy-disk); teks lain (emoji lama) ditampilkan apa adanya.
// Kelas CSS dl-splitbtn (bukan dl-split: itu milik objek Split, isi + panel samping).
class SplitButton extends DevlibComponent
{
    public function __construct(
        public array $actions = ['Save' => 'fa-floppy-disk', 'Download' => 'fa-download', 'Export' => 'fa-file-export'],
        public string $name = '_action',
    ) {}

    public function tag(): string
    {
        return 'x-dl::split-button';
    }

    public function render()
    {
        return <<<'BLADE'
            @php($ico = fn ($i) => str_starts_with((string) $i, 'fa-') ? '<i class="fa-solid '.e($i).'"></i>' : e($i))
            <div {{ $attributes->class('dl-splitbtn') }} {!! $dl() !!} data-dl-split>
                <input type="hidden" name="{{ $name }}" value="{{ array_key_first($actions) }}" data-dl-split-value>
                <button type="submit" class="dl-splitbtn__main"><span data-dl-split-icon>{!! $ico(reset($actions)) !!}</span><span data-dl-split-label>{{ array_key_first($actions) }}</span></button>
                @if (count($actions) > 1)
                    <button type="button" class="dl-splitbtn__toggle" data-dl-split-toggle title="{{ \Devlib\Lang::text('Choose an action') }}"><i class="fa-solid fa-chevron-down"></i></button>
                    <div class="dl-splitbtn__menu">
                        @foreach ($actions as $label => $icon)
                            <button type="button" class="dl-splitbtn__item" data-action="{{ $label }}"><span>{!! $ico($icon) !!}</span> {{ $label }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
            BLADE;
    }
}
