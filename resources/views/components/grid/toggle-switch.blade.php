{{--
    Pilot (2026-08-30, permintaan user): visual toggle-switch (bukan ikon ⏻)
    utk kolom aktif/nonaktif -- TAPI beda dari PowerGrid/Filament, tetap
    WAJIB `wire:confirm` sebelum eksekusi (tmerp sengaja tidak toggle instan,
    guard data Master). Ganti murni visual, mekanisme di baliknya sama persis
    dgn <x-grid.action-icon icon="⏻">.
--}}
@props(['on', 'action', 'confirm' => null, 'title' => null])
<button type="button" wire:click.stop="{{ $action }}"
    @if ($confirm) wire:confirm="{{ $confirm }}" @endif
    title="{{ $title }}"
    class="relative inline-flex items-center w-9 h-5 rounded-full shrink-0 transition-colors cursor-pointer {{ $on ? 'bg-[#16a34a]' : 'bg-[#d1d5db]' }}">
    <span class="inline-block w-3.5 h-3.5 bg-white rounded-full shadow transition-transform {{ $on ? 'translate-x-4' : 'translate-x-1' }}"></span>
</button>
