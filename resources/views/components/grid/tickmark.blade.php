{{--
    Kotak centang per baris grid -- dipakai untuk flag biner yang di-toggle
    officer langsung dari grid (mis. kanal penyerahan slip gaji: Printout /
    Kirim WA). BUKAN pengganti x-grid.toggle-switch, yang khusus kolom
    aktif/nonaktif grid Master (griduistandard.md §5).

    `on` = keadaan sekarang, `action` = method Livewire, `disabled` = tak bisa
    diubah karena prasyaratnya belum terpenuhi (mis. nomor WA kosong).
--}}
@props(['on' => false, 'action', 'title' => null, 'disabled' => false])
@php
    $state = match (true) {
        (bool) $disabled => 'bg-[#f1f5f9] border-[#e2e8f0] text-transparent cursor-not-allowed',
        (bool) $on => 'bg-[#16a34a] border-[#16a34a] text-white cursor-pointer',
        default => 'bg-white border-[#cbd5e1] text-transparent cursor-pointer hover:border-[#94a3b8]',
    };
@endphp
<button type="button"
    @if (! $disabled) wire:click.stop="{{ $action }}" @else disabled @endif
    title="{{ $title }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
    class="inline-flex items-center justify-center w-[18px] h-[18px] rounded border-[1.5px] text-[11px] {{ $state }}">✓</button>
