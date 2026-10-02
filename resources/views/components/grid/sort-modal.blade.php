@if ($showSortModal)
    <x-ui.modal size="xs" :title="t('Sort Data')" close="$set('showSortModal', false)">
        <div class="p-4">
            <div class="text-[10px] font-bold text-muted uppercase mb-2">{{ t('Column (from visible ones)') }}</div>
            <div class="border border-line rounded-md h-[180px] overflow-y-auto tm-scroll mb-3">
                <div wire:click="$set('sortModalColumn', null)" class="px-2.5 py-1.5 text-[12px] cursor-pointer border-b border-line-soft {{ ! $sortModalColumn ? 'bg-info-soft font-semibold text-info-ink' : 'hover:bg-surface-alt' }}">{{ t('Not sorted') }}</div>
                @foreach ($gridVisibleColumns as $key)
                    @continue(($columnDefs[$key]['sortable'] ?? true) === false)
                    <div wire:click="$set('sortModalColumn', '{{ $key }}')" wire:key="sort-{{ $key }}"
                        class="px-2.5 py-1.5 text-[12px] cursor-pointer border-b border-line-soft {{ $sortModalColumn === $key ? 'bg-info-soft font-semibold text-info-ink' : 'hover:bg-surface-alt' }}">{{ $columnDefs[$key]['label'] ?? $key }}</div>
                @endforeach
            </div>

            @if ($sortModalColumn)
                <div class="flex gap-2 mb-1">
                    <x-ui.button :variant="$sortModalDirection === 'asc' ? 'navy' : 'neutral'" size="sm" class="flex-1" wire:click="$set('sortModalDirection', 'asc')">▲ A → Z</x-ui.button>
                    <x-ui.button :variant="$sortModalDirection === 'desc' ? 'navy' : 'neutral'" size="sm" class="flex-1" wire:click="$set('sortModalDirection', 'desc')">▼ Z → A</x-ui.button>
                </div>
            @endif

            <x-ui.action-bar variant="end" class="pt-3 border-t border-line-soft">
                <x-ui.button preset="cancel" variant="neutral" wire:click="$set('showSortModal', false)" />
                <x-ui.button preset="apply" wire:click="applySortSelection" />
            </x-ui.action-bar>
        </div>
    </x-ui.modal>
@endif
