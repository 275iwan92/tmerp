{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- prefix
    mata uang (symbol+code, dropdown searchable) + grouping ribuan id-ID
    (BigInt, dukung angka sangat besar spt mockup). Hidden input (pola sama
    x-form.date-picker) berisi "{CODE}:{digit mentah}" utk wire:model.
--}}
@props(['label' => null, 'name' => null, 'currencies' => [], 'value' => '', 'hint' => null])
@php
    $currencies = $currencies ?: [
        ['code' => 'IDR', 'symbol' => 'Rp'],
        ['code' => 'USD', 'symbol' => '$'],
        ['code' => 'EUR', 'symbol' => '€'],
        ['code' => 'JPY', 'symbol' => '¥'],
    ];
@endphp
<div class="flex flex-col gap-1" x-data="tmCurrencyInput({ currencies: @js($currencies), initial: @js($value) })" @click.outside="currencyOpen = false">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="flex relative">
        <button type="button" @click="currencyOpen = !currencyOpen"
            class="flex items-center gap-1 px-2 border border-field-border border-r-0 rounded-l bg-field-soft text-xs font-semibold text-[#374151] cursor-pointer whitespace-nowrap">
            <span x-text="currency.symbol"></span><span x-text="currency.code"></span>
        </button>
        <input type="text" inputmode="numeric" x-bind:value="display" @input="onInput($event)"
            class="w-full px-2 py-1.5 border border-field-border rounded-r text-xs focus:border-field-focus">
        <div x-show="currencyOpen" x-cloak x-transition.origin.top
            class="absolute z-30 top-full left-0 mt-1 w-40 bg-white border border-field-border rounded-lg shadow-lg overflow-hidden text-xs">
            <template x-for="c in currencies" :key="c.code">
                <div @click="pickCurrency(c)" class="flex items-center justify-between px-2.5 py-1.5 hover:bg-field-soft cursor-pointer text-[#374151]">
                    <span x-text="c.code"></span><strong x-text="c.symbol"></strong>
                </div>
            </template>
        </div>
    </div>
    <div class="text-[10.5px] font-semibold text-accent bg-accent-soft border border-field-border rounded px-1.5 py-0.5" x-text="'Terbaca: ' + readableValue"></div>
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
</div>
