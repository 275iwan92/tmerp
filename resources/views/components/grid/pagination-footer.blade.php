{{-- Footer pagination 3-bagian ala ScriptCase (keputusan user 2026-08-27):
     kiri = Go to page + View (baris/halaman), tengah = navigasi, kanan =
     info total. $paginator = instance LengthAwarePaginator dari komponen
     pemanggil (mis. $this->items / $this->orders). Tombol = x-ui.* (2026-09-23). --}}
@php($tr = fn ($k, $d) => \App\Support\Localization\Translator::t($k, $d))
<div class="flex items-center justify-between px-3.5 py-2.5 border-t border-line-soft flex-wrap gap-3 text-[11.5px]">
    <div class="flex items-center gap-2 text-muted shrink-0">
        <span>{{ $tr('shared_pagination_goto', 'Go to') }}</span>
        <input type="number" min="1" max="{{ $paginator->lastPage() }}" value="{{ $paginator->currentPage() }}"
            wire:change="gotoPage($event.target.value)"
            class="w-14 px-1.5 py-1 border border-line rounded text-center text-[11.5px]">
        <span class="ml-1">{{ $tr('shared_pagination_view', 'View') }}</span>
        <div class="flex gap-1">
            @foreach ([10, 25, 50, 100] as $n)
                <x-ui.button :variant="$perPage === $n ? 'navy' : 'neutral'" size="xs" wire:click="setPerPage({{ $n }})">{{ $n }}</x-ui.button>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-1">
        <x-ui.button variant="secondary" size="square" wire:click="gotoPage(1)" :disabled="$paginator->onFirstPage()" title="{{ $tr('shared_pagination_first', 'Halaman pertama') }}">⏮</x-ui.button>
        <x-ui.button variant="secondary" size="square" wire:click="previousPage" :disabled="$paginator->onFirstPage()" title="{{ $tr('shared_pagination_prev', 'Sebelumnya') }}">◀</x-ui.button>
        @foreach ($this->gridPageRange($paginator) as $p)
            <x-ui.button :variant="$p === $paginator->currentPage() ? 'navy' : 'secondary'" size="square" wire:click="gotoPage({{ $p }})">{{ $p }}</x-ui.button>
        @endforeach
        <x-ui.button variant="secondary" size="square" wire:click="nextPage" :disabled="! $paginator->hasMorePages()" title="{{ $tr('shared_pagination_next', 'Berikutnya') }}">▶</x-ui.button>
        <x-ui.button variant="secondary" size="square" wire:click="gotoPage({{ $paginator->lastPage() }})" :disabled="! $paginator->hasMorePages()" title="{{ $tr('shared_pagination_last', 'Halaman terakhir') }}">⏭</x-ui.button>
    </div>

    <div class="text-muted shrink-0">
        [{{ number_format($paginator->firstItem() ?? 0) }} – {{ number_format($paginator->lastItem() ?? 0) }} {{ $tr('shared_pagination_of', 'dari') }} {{ number_format($paginator->total()) }}]
    </div>
</div>
