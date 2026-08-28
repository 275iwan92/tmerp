{{--
    Componentization Form (fase 2) -- 1 baris checkbox+label inline (boolean
    toggle). :labelClass opsional utk kasus warna teks label perlu beda
    (mis. peringatan merah "Blokir supplier") -- $attributes (wire:model dst.)
    tetap ke <input>, bukan ke label.
--}}
@props(['label' => null, 'labelClass' => 'text-muted'])
<label class="flex items-center gap-2 text-xs {{ $labelClass }}">
    <input type="checkbox" {{ $attributes }}>
    {{-- slot dulu (butuh markup spt <b>, aman krn ComponentSlot Htmlable) --}}
    {{ $slot->isNotEmpty() ? $slot : $label }}
</label>
