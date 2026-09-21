{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- prefix
    negara (bendera+kode dial, dropdown searchable) + digit mask 3-4-4.
    Hidden input (pola sama x-form.date-picker) berisi "{dial}{digit mentah}"
    gabungan, mis. "+62812xxxxxxx", utk wire:model.
--}}
@props(['label' => null, 'name' => null, 'countries' => [], 'value' => '', 'hint' => null, 'error' => null])
@php
    $countries = $countries ?: [
        ['name' => 'Indonesia', 'dial' => '+62', 'flag' => '🇮🇩'],
        ['name' => 'United States', 'dial' => '+1', 'flag' => '🇺🇸'],
        ['name' => 'Singapore', 'dial' => '+65', 'flag' => '🇸🇬'],
        ['name' => 'Japan', 'dial' => '+81', 'flag' => '🇯🇵'],
    ];
    $errorMsg = $error ?? ($name ? $errors->first($name) : null);
@endphp
<div class="flex flex-col gap-1" x-data="tmPhoneInput({ countries: @js($countries), initial: @js($value) })" @click.outside="countryOpen = false">
    @if ($label)
        <label class="text-[10px] text-muted uppercase font-semibold block mb-1">{{ $label }}</label>
    @endif
    <input type="hidden" x-ref="model" {{ $attributes }}>
    <div class="flex relative">
        <button type="button" @click="countryOpen = !countryOpen"
            class="flex items-center gap-1 px-2 border border-field-border border-r-0 rounded-l bg-field-soft text-xs font-semibold text-[#374151] cursor-pointer whitespace-nowrap">
            <span x-text="country.flag"></span><span x-text="country.dial"></span>
        </button>
        <input type="text" inputmode="numeric" x-bind:value="display" @input="onInput($event)" placeholder="812-3456-7890"
            class="w-full px-2 py-1.5 border border-field-border rounded-r text-xs focus:border-field-focus">
        <div x-show="countryOpen" x-cloak x-transition.origin.top
            class="absolute z-30 top-full left-0 mt-1 w-56 bg-white border border-field-border rounded-lg shadow-lg overflow-hidden text-xs">
            <div class="p-1.5 bg-field-soft border-b border-field-border">
                <input type="text" x-model="countryQuery" placeholder="Cari negara..." class="w-full px-2 py-1 border border-field-border rounded text-[11px]">
            </div>
            <div class="max-h-[140px] overflow-y-auto">
                <template x-for="c in filteredCountries" :key="c.dial + c.name">
                    <div @click="pickCountry(c)" class="flex items-center justify-between px-2.5 py-1.5 hover:bg-field-soft cursor-pointer text-[#374151]">
                        <span x-text="c.flag + ' ' + c.name"></span><strong x-text="c.dial"></strong>
                    </div>
                </template>
            </div>
        </div>
    </div>
    @if ($hint)<div class="text-[10.5px] text-muted">{{ $hint }}</div>@endif
    @if ($errorMsg)<span class="text-[10.5px] text-red-600">{{ $errorMsg }}</span>@endif
</div>
