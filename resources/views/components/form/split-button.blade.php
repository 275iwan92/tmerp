{{--
    Component library (docs/mockups/UIUX_components_part1.html) -- tombol
    aksi utama + panah dropdown berisi aksi lain. :actions = [{label, icon,
    wireClick}] -- tiap item render wire:click sendiri (Livewire asli, bukan
    Alpine yg tebak-tebak nama method).
--}}
@props(['label' => t('Save'), 'icon' => '💾', 'actions' => []])
<div class="relative flex" x-data="{ open: false }" @click.outside="open = false">
    <button type="button" {{ $attributes->merge(['class' => 'flex-1 px-3 py-1.5 rounded-l-md text-xs font-semibold bg-accent text-white cursor-pointer flex items-center justify-center gap-1.5']) }}>
        <span>{{ $icon }}</span><span>{{ $label }}</span>
    </button>
    <button type="button" @click="open = !open"
        class="px-2 rounded-r-md bg-accent text-white border-l border-white/30 cursor-pointer">
        <span class="inline-block border-l-4 border-r-4 border-t-4 border-transparent border-t-white" style="width:0;height:0"></span>
    </button>
    <div x-show="open" x-cloak x-transition.origin.bottom
        class="absolute z-30 bottom-full right-0 mb-1 w-full min-w-[140px] bg-white border border-field-border rounded-md shadow-lg overflow-hidden">
        @foreach ($actions as $act)
            <button type="button" @click="open = false" @if (! empty($act['wireClick'])) wire:click="{{ $act['wireClick'] }}" @endif
                class="w-full text-left px-3 py-1.5 text-xs text-[#374151] hover:bg-field-soft cursor-pointer flex items-center gap-2">
                <span>{{ $act['icon'] ?? '' }}</span><span>{{ $act['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>
