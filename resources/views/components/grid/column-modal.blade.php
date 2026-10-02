@if ($showColumnModal)
    <x-ui.modal size="lg" :title="t('Choose Visible Columns')" close="$set('showColumnModal', false)">
            <div class="p-4">
                <div class="grid grid-cols-[1fr_44px_1fr] gap-2 items-start">
                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase mb-1.5">{{ t('Available') }}</div>
                        <input type="text" wire:model.live.debounce.150ms="columnModalSearch" placeholder="{{ t('Search columns…') }}" class="w-full mb-1.5 px-2 py-1.5 border border-line rounded text-[12px]">
                        <div class="border border-line rounded-md h-[230px] overflow-y-auto tm-scroll bg-surface-alt">
                            @foreach ($columnDefs as $key => $def)
                                @continue(in_array($key, $columnModalSelected, true))
                                @continue($columnModalSearch !== '' && ! str_contains(mb_strtolower($def['label']), mb_strtolower($columnModalSearch)))
                                <div wire:click="addColumnToSelection('{{ $key }}')" wire:key="col-avail-{{ $key }}"
                                    class="px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-info-soft border-b border-line-soft">{{ $def['label'] }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5 pt-[27px] items-center">
                        <x-ui.button variant="secondary" size="square" class="text-[13px]" wire:click="addAllColumns" title="{{ t('Show all') }}">»</x-ui.button>
                        <x-ui.button variant="secondary" size="square" class="text-[13px]" wire:click="removeAllColumns" title="{{ t('Clear all') }}">«</x-ui.button>
                    </div>

                    <div>
                        <div class="text-[10px] font-bold text-muted uppercase mb-1.5">{{ t('Visible') }} ({{ count($columnModalSelected) }})</div>
                        <input type="text" disabled placeholder="{{ t('click to remove →') }}" class="w-full mb-1.5 px-2 py-1.5 border border-line rounded text-[12px] bg-surface-alt text-muted/70">
                        <div class="border border-line rounded-md h-[230px] overflow-y-auto tm-scroll bg-surface-alt">
                            @foreach ($columnModalSelected as $key)
                                <div wire:click="removeColumnFromSelection('{{ $key }}')" wire:key="col-sel-{{ $key }}"
                                    class="group px-2.5 py-1.5 text-[12px] cursor-pointer hover:bg-danger-soft border-b border-line-soft flex items-center justify-between">
                                    <span>{{ $columnDefs[$key]['label'] ?? $key }}</span>
                                    <span class="text-danger-ink opacity-0 group-hover:opacity-100">✕</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-line-soft">
                    <div class="text-[10px] font-bold text-muted uppercase mb-2">{{ t('Group By (from visible columns)') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <x-ui.button :variant="! $gridGroupBy ? 'navy' : 'neutral'" size="xs" wire:click="setGridGroupBy(null)">{{ t('None') }}</x-ui.button>
                        @foreach ($columnModalSelected as $key)
                            <x-ui.button :variant="$gridGroupBy === $key ? 'navy' : 'neutral'" size="xs" wire:click="setGridGroupBy('{{ $key }}')">{{ $columnDefs[$key]['label'] ?? $key }}</x-ui.button>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-between items-center gap-2 pt-4 mt-3 border-t border-line-soft">
                    <x-ui.button variant="ghost" size="xs" class="!text-danger-ink" wire:click="resetGridPreference">↺ {{ t('Reset to default') }}</x-ui.button>
                    <div class="flex gap-2">
                        <x-ui.button preset="cancel" variant="neutral" wire:click="$set('showColumnModal', false)" />
                        <x-ui.button preset="apply" wire:click="applyColumnSelection" />
                    </div>
                </div>
            </div>
    </x-ui.modal>
@endif
