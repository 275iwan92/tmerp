<?php

use App\Support\Mockup\MockupCatalog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mockup ERP')] class extends Component
{
    public string $search = '';

    #[Computed]
    public function groups(): array
    {
        $term = mb_strtolower(trim($this->search));
        $groups = MockupCatalog::groups();

        if ($term === '') {
            return $groups;
        }

        return array_filter(array_map(
            fn ($items) => array_values(array_filter($items, fn ($m) => str_contains(mb_strtolower($m['title'].' '.$m['file']), $term))),
            $groups,
        ));
    }

    #[Computed]
    public function total(): int
    {
        return array_sum(array_map('count', MockupCatalog::groups()));
    }
};
?>

<div class="max-w-[1400px] mx-auto my-5 px-4">
    <div class="flex items-end gap-4 mb-4 flex-wrap">
        <div>
            <h1 class="text-lg font-bold text-navy">Mockup ERP</h1>
            <p class="text-xs text-muted">{{ $this->total }} mockup · kiri mockup asli, kanan komponen library DevLib yang menirunya.</p>
        </div>
        <span class="flex-1"></span>
        <x-form.input :case-toggle="false" wire:model.live.debounce.300ms="search" placeholder="Cari mockup…" class="w-64" />
    </div>

    @forelse ($this->groups as $group => $items)
        <x-ui.card :title="$group.' ('.count($items).')'" class="mb-4">
            <div class="flex flex-wrap gap-2.5">
                @foreach ($items as $m)
                    <a href="{{ route('mockup.show', ['file' => $m['file']]) }}" wire:key="m-{{ $m['file'] }}"
                        class="w-[260px] no-underline border border-line rounded-md px-3 py-2.5 bg-surface hover:bg-surface-hover">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid {{ $m['type'] === 'html' ? 'fa-file-code' : 'fa-image' }} text-accent"></i>
                            <span class="text-[13px] font-semibold text-ink truncate">{{ $m['title'] }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="text-[11px] text-muted">{{ $m['date'] }}</span>
                            <span class="flex-1"></span>
                            <x-ui.badge :tone="match ($m['status']) { 'done' => 'success', 'partial' => 'warn', default => 'muted' }" pill>
                                {{ match ($m['status']) { 'done' => 'Komponen ✓', 'partial' => 'Sebagian', default => 'Belum' } }}
                            </x-ui.badge>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    @empty
        <p class="text-sm text-muted">Tidak ada mockup cocok.</p>
    @endforelse
</div>
