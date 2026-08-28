{{-- Footer pagination 3-bagian ala ScriptCase (keputusan user 2026-08-27):
     kiri = Go to page + View (baris/halaman), tengah = navigasi, kanan =
     info total. $paginator = instance LengthAwarePaginator dari komponen
     pemanggil (mis. $this->items / $this->orders). --}}
<div class="flex items-center justify-between px-3.5 py-2.5 border-t border-[#eef1f5] flex-wrap gap-3 text-[11.5px]">
    <div class="flex items-center gap-2 text-muted shrink-0">
        <span>Go to</span>
        <input type="number" min="1" max="{{ $paginator->lastPage() }}" value="{{ $paginator->currentPage() }}"
            wire:change="gotoPage($event.target.value)"
            class="w-14 px-1.5 py-1 border border-[#d7dbe2] rounded text-center text-[11.5px]">
        <span class="ml-1">View</span>
        <div class="flex gap-1">
            @foreach ([10, 25, 50, 100] as $n)
                <button type="button" wire:click="setPerPage({{ $n }})"
                    class="px-2 py-1 rounded text-[11px] font-semibold cursor-pointer {{ $perPage === $n ? 'bg-[#2c3e69] text-white' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $n }}</button>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-1">
        <button type="button" wire:click="gotoPage(1)" @if($paginator->onFirstPage()) disabled @endif title="Halaman pertama"
            class="w-8 h-8 rounded-md border border-[#d7dbe2] flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[#f4f5f8]">⏮</button>
        <button type="button" wire:click="previousPage" @if($paginator->onFirstPage()) disabled @endif title="Sebelumnya"
            class="w-8 h-8 rounded-md border border-[#d7dbe2] flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[#f4f5f8]">◀</button>
        @foreach ($this->gridPageRange($paginator) as $p)
            <button type="button" wire:click="gotoPage({{ $p }})"
                class="w-8 h-8 rounded-md text-[11.5px] font-semibold cursor-pointer {{ $p === $paginator->currentPage() ? 'bg-[#2c3e69] text-white' : 'border border-[#d7dbe2] hover:bg-[#f4f5f8]' }}">{{ $p }}</button>
        @endforeach
        <button type="button" wire:click="nextPage" @if(! $paginator->hasMorePages()) disabled @endif title="Berikutnya"
            class="w-8 h-8 rounded-md border border-[#d7dbe2] flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[#f4f5f8]">▶</button>
        <button type="button" wire:click="gotoPage({{ $paginator->lastPage() }})" @if(! $paginator->hasMorePages()) disabled @endif title="Halaman terakhir"
            class="w-8 h-8 rounded-md border border-[#d7dbe2] flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:bg-[#f4f5f8]">⏭</button>
    </div>

    <div class="text-muted shrink-0">
        [{{ number_format($paginator->firstItem() ?? 0) }} – {{ number_format($paginator->lastItem() ?? 0) }} dari {{ number_format($paginator->total()) }}]
    </div>
</div>
