{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- radio
    native (bulat, bukan pill spt x-form.toggle-group) satu pilihan wajib.
    :options = value=>label. wire:model/name diteruskan ke SEMUA <input
    radio> lewat $attributes (native radio, Livewire sinkron otomatis --
    beda dari <select>, tidak kena bug morph di memory feedback_livewire_select_morph_bug).
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'selected' => null])
<div class="flex flex-col gap-1">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <div class="flex items-center gap-4 h-7 flex-wrap">
        @foreach ($options as $value => $optLabel)
            <label class="flex items-center gap-1.5 text-xs text-[#374151] cursor-pointer font-medium">
                <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked((string) $selected === (string) $value)
                    {{ $attributes->merge(['class' => 'accent-accent cursor-pointer']) }}>
                {{ $optLabel }}
            </label>
        @endforeach
    </div>
</div>
