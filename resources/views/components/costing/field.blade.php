{{--
    Field padat layar ProCost (meniru blok "Parameter Kalkulasi" program HPP lama).
    Menulis ke method Livewire $action($key, value) saat change -- bukan wire:model,
    supaya engine bisa langsung hitung ulang per perubahan (pola onChange legacy).
    Props: label, key, value, action (updatePart|updateHeader|updateTier),
    type (number|text|select|textarea), options [value => label], readonly, tone (null|sheet|lam|common).
--}}
@props(['label', 'key', 'value' => '', 'action' => 'updatePart', 'type' => 'number', 'options' => [], 'readonly' => false, 'tone' => null, 'step' => 'any'])
@php
    $toneCls = match ($tone) {
        'sheet' => 'bg-[#e9f9ef] text-[#15803d]',
        'lam' => 'bg-[#fef6e7] text-[#b45309]',
        'common' => 'bg-[#eaf2ff] text-[#1d4ed8]',
        default => 'text-[#64748b]',
    };
    $inputCls = 'w-full text-xs px-2 py-1 border rounded-sm outline-none '.($readonly ? 'bg-[#f8fafc] border-[#e2e8f0] text-[#64748b] cursor-not-allowed' : 'bg-white border-[#cbd5e1] focus:border-[#3b82f6]');
    $call = $action."('".$key."', \$event.target.value)";
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col min-w-0']) }}>
    <label class="text-[10px] font-semibold uppercase tracking-wide px-1 mb-0.5 truncate rounded-sm {{ $toneCls }}" title="{{ $label }}">{{ $label }}</label>
    @if ($type === 'select')
        <select @disabled($readonly) wire:change="{{ $call }}" class="{{ $inputCls }}">
            <option value="">—</option>
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $optValue === (string) $value)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea @readonly($readonly) wire:change="{{ $call }}" rows="2" class="{{ $inputCls }} resize-none">{{ $value }}</textarea>
    @else
        <input type="{{ $type }}" @if ($type === 'number') step="{{ $step }}" @endif value="{{ $value }}" @readonly($readonly) wire:change="{{ $call }}" class="{{ $inputCls }} {{ $type === 'number' ? 'text-right font-mono' : '' }}">
    @endif
</div>
