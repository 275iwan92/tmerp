@props(['title', 'close' => 'closeModal', 'size' => ''])
{{-- Modal gaya blueprint; tampil/tidaknya diatur @if di pemanggil (state Livewire). --}}
<div class="lg-modalback" wire:click.self="{{ $close }}">
    <div class="lg-modal {{ $size }}">
        <div class="lg-mh"><h3>{{ $title }}</h3><button type="button" class="lg-x" wire:click="{{ $close }}">×</button></div>
        {{ $slot }}
        @isset($footer)<div class="lg-mf">{{ $footer }}</div>@endisset
    </div>
</div>
