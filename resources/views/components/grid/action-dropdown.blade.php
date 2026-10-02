{{--
    Pilot (2026-08-30, permintaan user): kolom Aksi gaya Filament -- 1 tombol
    "⋮" mengumpulkan semua aksi per-baris jadi dropdown, menggantikan ikon
    sejajar (§5 griduistandard.md tetap berlaku di grid LAIN yang tidak ikut
    pilot ini -- co/list.blade.php & master/raw-material/index.blade.php
    sejauh ini). Posisi kolom ini tetap di KIRI (§10, tidak berubah).

    $actions: array of ['icon','label','action' (string method call Livewire
    apa adanya, mis. "openOrder(123)"), 'confirm'=>null, 'variant'=>'default'
    |'success'|'danger', 'tooltip'=>null].

    x-data lokal per-baris (bukan Livewire state) -- @click.outside nutup
    dropdown, wire:key di <tr> induk sudah menjamin Alpine state ini tidak
    tercampur antar baris saat re-render.
--}}
@props(['actions'])
<div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" @click.stop="open = !open" title="{{ t('Action') }}"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-[#d7dbe2] bg-white hover:bg-[#f4f5f8] cursor-pointer text-[13px] text-[#4b5568]">⋮</button>
    <div x-show="open" x-cloak x-transition.origin.top.left @click.stop
        class="absolute left-0 top-8 z-20 bg-white border border-[#d7dbe2] rounded-md shadow-lg min-w-[190px] overflow-hidden py-1">
        @foreach ($actions as $a)
            <button type="button" wire:click.stop="{{ $a['action'] }}" @click="open = false"
                @if (! empty($a['confirm'])) wire:confirm="{{ $a['confirm'] }}" @endif
                title="{{ $a['tooltip'] ?? '' }}"
                class="w-full text-left px-3 py-2 text-[12px] font-medium flex items-center gap-2 cursor-pointer hover:bg-[#f4f5f8] {{ match ($a['variant'] ?? 'default') {
                    'danger' => 'text-[#c0392b]',
                    'success' => 'text-[#16a34a]',
                    default => 'text-[#333]',
                } }}">
                <span class="w-4 text-center shrink-0">{{ $a['icon'] }}</span>
                <span>{{ $a['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>
