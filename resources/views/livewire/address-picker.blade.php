<div class="relative" x-data @click.outside="$wire.close()">
    <div wire:click="toggle"
        class="flex items-center justify-between px-2.5 py-1.5 border rounded text-[12.5px] cursor-pointer bg-white min-h-[32px] {{ $open ? 'border-accent shadow-[0_0_0_3px_rgba(47,111,237,.12)]' : 'border-[#d7dbe2] hover:border-accent' }}">
        @if ($selectedLabel)
            <span class="text-[#1c2333] font-semibold">{{ $selectedLabel }}</span>
        @else
            <span class="text-[#6b7280]">{{ t('Select region...') }}</span>
        @endif
        <span class="text-[#6b7280] text-[10px] ml-2">▾</span>
    </div>

    @if ($open)
        <div class="absolute top-[calc(100%+4px)] left-0 right-0 bg-white border border-[#d7dbe2] rounded-md z-30 overflow-hidden shadow-[0_10px_28px_rgba(20,30,60,.16)]" @click.stop>
            <input type="text" wire:model.live.debounce.300ms="search" autofocus
                placeholder="{{ t('Type village / district / city / province / postal code...') }}"
                class="w-full px-3 py-2.5 border-0 border-b border-[#d7dbe2] text-[13px] outline-none box-border">

            <div class="max-h-[260px] overflow-y-auto">
                @forelse ($this->results as $row)
                    <div wire:click="select({{ $row->id_village ?? 'null' }}, {{ $row->id_district ?? 'null' }}, {{ $row->id_city ?? 'null' }}, '{{ addslashes($row->full_label) }}', {{ $row->postal_code ? "'{$row->postal_code}'" : 'null' }})"
                        class="px-3 py-2.5 border-b border-[#eef0f3] last:border-b-0 cursor-pointer flex justify-between gap-2.5 items-start hover:bg-accent hover:text-white group">
                        <span class="text-[12.5px] leading-snug whitespace-normal">{{ $row->full_label }}</span>
                        @if ($row->postal_code)
                            <span class="text-[10px] text-[#6b7280] whitespace-nowrap pt-px group-hover:text-[#dce6ff]">{{ $row->postal_code }}</span>
                        @endif
                    </div>
                @empty
                    <div class="px-3 py-4 text-xs text-[#6b7280] text-center">
                        {{ mb_strlen(trim($search)) < 2 ? t('Type at least 2 letters...') : t('Not found — try another keyword') }}
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
