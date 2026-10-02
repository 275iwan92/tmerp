@props(['type' => 'info', 'message' => null])

{{-- Varian inline dari standar alert (2026-08-31) -- pengganti banner
hijau/merah yg dulu copy-paste manual di tiap halaman transaksi (session
flash/error). Tetap baca session() langsung lewat prop :message, tak ada
logika PHP yg berubah di controller/Livewire pemanggil. --}}
@if ($message)
    <div class="app-alert-banner {{ $type }}" x-data="{ show: true }" x-show="show" x-transition.duration.150ms>
        <span class="ic">
            <i class="fa-solid {{ match ($type) {
                'success' => 'fa-circle-check',
                'warning' => 'fa-triangle-exclamation',
                'danger' => 'fa-circle-xmark',
                default => 'fa-circle-info',
            } }}"></i>
        </span>
        <span>{{ $message }}</span>
        <button type="button" class="close" @click="show = false" aria-label="{{ t('Close message') }}">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
@endif
