<?php

namespace App\View\Components\Ui;

// Bar tab. Tab dirender dari data, bukan markup per layar:
//   :tabs="['identity' => '1. Identitas', ...]" :active="$activeTab"
//   model="activeTab"  -> klik = $set('activeTab', key)
//   :badges="['processes' => ['text' => 3, 'tone' => 'success']]" (opsional)
class Tabs extends UiComponent
{
    public function __construct(
        public array $tabs = [],
        public ?string $active = null,
        public ?string $model = null,
        public array $badges = [],
        string $variant = 'bar',
    ) {
        $this->variant = $variant;
    }

    protected function variants(): array
    {
        return [
            'bar' => 'flex flex-wrap bg-line-soft border-b border-line',
        ];
    }

    public function tabClass(string $key): string
    {
        return 'px-3.5 py-2 text-xs font-semibold border-r border-line cursor-pointer '
            .($key === $this->active ? 'bg-surface text-accent border-b-2 border-b-accent' : 'text-ink-soft hover:bg-surface-alt');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->merge(['class' => $classes()]) }}>
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:key="tab-{{ $key }}" @if ($model) wire:click="$set('{{ $model }}', '{{ $key }}')" @endif class="{{ $tabClass($key) }}">
                        {{ $label }}
                        @isset($badges[$key])
                            <x-ui.badge :tone="$badges[$key]['tone'] ?? 'neutral'" pill class="ml-1">{{ $badges[$key]['text'] }}</x-ui.badge>
                        @endisset
                    </button>
                @endforeach
            </div>
            BLADE;
    }
}
