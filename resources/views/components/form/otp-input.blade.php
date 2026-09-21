{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- N kotak
    digit OTP, auto-advance ke kotak berikutnya, Backspace mundur. Hidden
    input gabungan (pola sama x-form.date-picker) menampung string gabungan
    utk wire:model.
--}}
@props(['label' => null, 'name' => null, 'length' => 6])
<div class="flex flex-col gap-1" x-data="tmOtpInput({ length: {{ (int) $length }} })">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="flex gap-1.5" x-ref="wrap">
        <template x-for="(box, i) in boxes" :key="i">
            <input type="text" inputmode="numeric" maxlength="1" x-bind:value="box"
                @input="onInput(i, $event)" @keydown="onKeydown(i, $event)"
                class="w-7 h-7 text-center text-sm font-bold border border-field-border rounded focus:border-field-focus">
        </template>
    </div>
</div>
