{{-- Kontainer toast global, sekali pasang per layout (layouts/app.blade.php &
layouts/shell.blade.php) -- lihat resources/js/app-alerts.js utk store Alpine
& kontrak pemanggilan $this->dispatch('app-alert', ...) dari Livewire mana pun. --}}
<div x-data="appAlerts()" class="app-alert-stack">
    <template x-for="t in items" :key="t.id">
        <div class="app-alert-toast" :class="t.type">
            <span class="bar"></span>
            <span class="ic">
                <i class="fa-solid" :class="{
                    'fa-circle-check': t.type === 'success',
                    'fa-circle-info': t.type === 'info',
                    'fa-clone': t.isDuplicate,
                    'fa-triangle-exclamation': t.type === 'warning' && !t.isDuplicate,
                    'fa-circle-xmark': t.type === 'danger',
                }"></i>
            </span>
            <span class="body">
                <template x-if="t.title"><p class="ttl" x-text="t.title"></p></template>
                <p class="msg" x-text="t.message"></p>
            </span>
            <button type="button" class="close" @click="dismiss(t.id)" aria-label="Tutup notifikasi">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <template x-if="t.duration > 0">
                <span class="progress" :style="'animation-duration:' + t.duration + 'ms'"></span>
            </template>
        </div>
    </template>
</div>
