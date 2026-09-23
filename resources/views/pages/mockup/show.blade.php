<?php

use App\Support\Mockup\MockupCatalog;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mockup ERP')] class extends Component
{
    public array $mockup = [];

    public string $mode = 'side';

    public function mount(string $file): void
    {
        $this->mockup = MockupCatalog::find($file) ?? abort(404);
    }
};
?>

<div class="h-screen flex flex-col">
    <div class="flex items-center gap-3 px-4 py-2.5 bg-navy text-white shrink-0">
        <a href="{{ route('mockup.index') }}" class="text-white/80 hover:text-white no-underline text-xs"><i class="fa-solid fa-arrow-left"></i> Galeri</a>
        <b class="text-sm">{{ $mockup['title'] }}</b>
        <span class="text-[11px] text-white/60 font-mono">{{ $mockup['file'] }}</span>
        <span class="flex-1"></span>
        <x-ui.button variant="chip" size="xs" :active="$mode === 'side'" wire:click="$set('mode', 'side')">Berdampingan</x-ui.button>
        <x-ui.button variant="chip" size="xs" :active="$mode === 'mockup'" wire:click="$set('mode', 'mockup')">Mockup saja</x-ui.button>
        <a href="{{ route('mockup.file', ['file' => $mockup['file']]) }}" target="_blank" class="text-white/80 hover:text-white text-xs no-underline"><i class="fa-solid fa-up-right-from-square"></i> Tab baru</a>
    </div>

    <div class="flex-1 flex min-h-0">
        <div @class(['min-w-0 flex flex-col border-r border-line', 'w-1/2' => $mode === 'side', 'w-full' => $mode === 'mockup'])>
            <div class="px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-wide text-muted bg-surface-alt border-b border-line">Mockup asli</div>
            @if ($mockup['type'] === 'html')
                <iframe src="{{ route('mockup.file', ['file' => $mockup['file']]) }}" class="flex-1 w-full bg-surface border-0"></iframe>
            @else
                <div class="flex-1 overflow-auto bg-surface p-3">
                    <img src="{{ route('mockup.file', ['file' => $mockup['file']]) }}" alt="{{ $mockup['title'] }}" class="max-w-full">
                </div>
            @endif
        </div>

        @if ($mode === 'side')
            <div class="w-1/2 min-w-0 flex flex-col">
                <div class="px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-wide text-muted bg-surface-alt border-b border-line">Komponen library (DevLib)</div>
                <div class="flex-1 flex items-center justify-center bg-surface-alt p-6">
                    <x-ui.callout tone="neutral" icon="⏳" class="max-w-md text-center">
                        Komponen untuk mockup ini belum dibangun. Dibangun setelah aba-aba <b>GO BUILD LIBRARY</b>,
                        lalu tampil di sini untuk dibandingkan dengan mockup di kiri.
                    </x-ui.callout>
                </div>
            </div>
        @endif
    </div>
</div>
