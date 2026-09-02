{{-- Picker warna dashboard Filament (docs/admindashboardstandard.md §10),
    ikut persis mockup docs/mockups/mockup_dashboardcolor.html: select utk
    mode dasar + swatch bulat utk corak aksen, ganti INSTAN di client (Alpine)
    persis mockup, lalu ditulis ke Hidden field 'admin_theme_mode'/
    'admin_theme_accent' (via $wire.set, statePath 'data.*') supaya tersimpan
    ke DB saat tombol Simpan (bawaan EditProfile) diklik. --}}
@php
    // $this->data sudah diisi Filament dari user record saat mount() (fillForm())
    // sebelum view ini dirender -- bukan old()/session, langsung dari kolom
    // sec_user.admin_theme_mode/admin_theme_accent.
    $currentMode = $this->data['admin_theme_mode'] ?? 'light';
    $currentAccent = $this->data['admin_theme_accent'] ?? 'blue';
@endphp
<div
    x-data="{
        mode: @js($currentMode),
        accent: @js($currentAccent),
        modes: [
            { value: 'light', label: 'Pure Light (Putih Bersih)' },
            { value: 'pastel', label: 'Soft Pastel (Dynamic Tint)' },
            { value: 'bright', label: 'Bright (Translucent Glass)' },
            { value: 'dark', label: 'Muted Dark' },
            { value: 'black', label: 'Pitch Black (OLED)' },
        ],
        accents: [
            { value: 'blue', hex: '#2563eb', label: 'Blue' },
            { value: 'green', hex: '#059669', label: 'Green' },
            { value: 'red', hex: '#dc2626', label: 'Red' },
            { value: 'purple', hex: '#8b5cf6', label: 'Purple' },
            { value: 'amber', hex: '#f59e0b', label: 'Amber' },
            { value: 'cyan', hex: '#06b6d4', label: 'Cyan' },
            { value: 'mono', hex: '#64748b', label: 'Monochrome' },
        ],
        applyLive() {
            document.documentElement.setAttribute('data-fi-theme', this.mode);
            document.documentElement.setAttribute('data-fi-accent', this.accent);
        },
        setMode(value) {
            this.mode = value;
            this.applyLive();
            $wire.set('data.admin_theme_mode', value);
        },
        setAccent(value) {
            this.accent = value;
            this.applyLive();
            $wire.set('data.admin_theme_accent', value);
        },
    }"
    x-init="applyLive()"
    class="flex flex-col gap-4"
>
    <div class="flex items-center gap-3 flex-wrap">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Base Theme Mode:</label>
        <select x-model="mode" @change="setMode($event.target.value)"
            class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm">
            <template x-for="m in modes" :key="m.value">
                <option :value="m.value" x-text="m.label"></option>
            </template>
        </select>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Corak Aksen (Color Pattern):</label>
        <div class="flex items-center gap-2">
            <template x-for="a in accents" :key="a.value">
                <button type="button" @click="setAccent(a.value)" :title="a.label"
                    :style="{ background: a.hex }"
                    :class="accent === a.value ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white' : ''"
                    class="w-6 h-6 rounded-full cursor-pointer transition-transform hover:scale-110"></button>
            </template>
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">Perubahan langsung terlihat di halaman ini. Klik <b>Save</b> di bawah supaya tersimpan permanen.</p>
</div>
