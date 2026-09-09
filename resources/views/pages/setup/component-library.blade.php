<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

// Layar "Library Component for UI/UX" (permintaan user 2026-09-09) --
// showcase SEMUA pola widget dari docs/mockups/UIUX_components_part1.html
// + mockup_UIUX_calendar.html (versi ringkas), dijadikan komponen Blade
// reusable sungguhan di resources/views/components/form/*.blade.php --
// BUKAN sekadar tempel HTML mockup mentah. Tujuan: jadi "patokan" visual +
// kontrak pemakaian (wire:model, hidden-input pattern spt x-form.date-picker)
// sebelum dipakai di layar bisnis nyata. Murni referensi -- tombol
// "Simpan"/dst di sini cuma demo dispatch app-alert, TIDAK menulis ke DB
// mana pun.
new #[Title('Library Component for UI/UX — TELASIH MAKMUR ERP')] class extends Component
{
    use WithFileUploads;

    public string $demoName = '';

    public string $demoPassword = '';

    public string $demoGender = 'Pria';

    public bool $demoActive = true;

    public array $demoHobbies = ['Membaca', 'Coding'];

    public string $demoColor = '#2563eb';

    public int $demoExpense = 15000000;

    public string $demoLocation = '';

    public string $demoLanguages = '';

    public int $demoChildren = 0;

    public string $demoOtp = '';

    public int $demoRating = 0;

    public string $demoNik = '';

    public string $demoPhone = '';

    public string $demoValuation = '';

    public $demoFile = null;

    public ?string $demoFileName = null;

    public function updatedDemoFile(): void
    {
        $this->validate(['demoFile' => ['nullable', 'file', 'max:2048']]);
        $this->demoFileName = $this->demoFile?->getClientOriginalName();
    }

    public function demoSave(string $context = 'Form'): void
    {
        $this->dispatch('app-alert', type: 'success', message: "{$context}: aksi demo dijalankan (tidak menulis ke DB).");
    }

    public function locationOptions(): array
    {
        return [
            'Surabaya, Jawa Timur, Indonesia',
            'Malang, Jawa Timur, Indonesia',
            'Sidoarjo, Jawa Timur, Indonesia',
            'Jakarta Selatan, DKI Jakarta, Indonesia',
            'Bandung, Jawa Barat, Indonesia',
            'Medan, Sumatera Utara, Indonesia',
            'Singapore, Central Region, Singapore',
            'Tokyo, Kanto, Japan',
        ];
    }

    public function languageOptions(): array
    {
        return [
            ['value' => 'id', 'label' => '🇮🇩 Bahasa Indonesia'],
            ['value' => 'en', 'label' => '🇬🇧 Bahasa Inggris'],
            ['value' => 'zh', 'label' => '🇨🇳 Bahasa Mandarin'],
            ['value' => 'ja', 'label' => '🇯🇵 Bahasa Jepang'],
            ['value' => 'ar', 'label' => '🇸🇦 Bahasa Arab'],
        ];
    }

    public function splitButtonActions(): array
    {
        return [
            ['label' => 'Save', 'icon' => '💾', 'wireClick' => "demoSave('Save')"],
            ['label' => 'Download', 'icon' => '📥', 'wireClick' => "demoSave('Download')"],
            ['label' => 'Export', 'icon' => '📤', 'wireClick' => "demoSave('Export')"],
        ];
    }

    public function calendarSampleEvents(): array
    {
        $ym = now()->format('Y-m');

        return [
            "{$ym}-08" => [['title' => '1 pending task', 'colorClass' => 'amber']],
            "{$ym}-09" => [['title' => 'Ulang Tahun Sarah', 'colorClass' => 'red']],
            "{$ym}-15" => [['title' => 'Design Review', 'colorClass' => 'green']],
            "{$ym}-18" => [['title' => 'Cuti panjang (H1)', 'colorClass' => 'green'], ['title' => 'Serah terima shift', 'colorClass' => 'amber']],
        ];
    }
};
?>

<div>
    <div class="max-w-[1100px] mx-auto my-5 px-4 flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Library Component for UI/UX</div>
            <p class="text-[11px] text-muted">Katalog pola widget form/interaksi yang tersedia di project ini (<code>resources/views/components/form/*.blade.php</code>) — acuan visual & kontrak pemakaian sebelum dipakai di layar bisnis nyata. Sumber: <code>docs/mockups/UIUX_components_part1.html</code> + <code>mockup_UIUX_calendar.html</code>. Semua interaksi di bawah ini murni demo (tidak menulis ke database).</p>
        </div>

        <!-- A. Teks & Password -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Input Teks & Password</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.input</code> (sudah ada) · <code>x-form.password-input</code> (baru).</p>
            <div class="grid two gap-3.5">
                <x-form.input label="Nama Lengkap" wire:model="demoName" placeholder="Masukkan nama..." />
                <x-form.password-input label="Password" wire:model="demoPassword" placeholder="Password..." />
            </div>
        </div>

        <!-- B. Pilihan: Radio / Checkbox / Switch -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Pilihan (Radio / Checkbox / Switch)</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.radio-group</code>, <code>x-form.checkbox</code> (sudah ada, dipakai berjajar), <code>x-form.switch</code> — semua baru kecuali checkbox.</p>
            <div class="grid three gap-3.5">
                <x-form.radio-group label="Jenis Kelamin" name="demoGender" wire:model="demoGender" :options="['Pria' => 'Pria', 'Wanita' => 'Wanita']" :selected="$demoGender" />
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] text-muted uppercase font-semibold block mb-1">Hobi / Minat</label>
                    <div class="flex items-center gap-3 h-7 flex-wrap">
                        @foreach (['Membaca', 'Olahraga', 'Traveling', 'Coding'] as $hobby)
                            <x-form.checkbox wire:model="demoHobbies" value="{{ $hobby }}">{{ $hobby }}</x-form.checkbox>
                        @endforeach
                    </div>
                </div>
                <x-form.switch label="Status Akun" :checked="$demoActive" wire:model="demoActive" onLabel="Active" offLabel="Non-Active" />
            </div>
        </div>

        <!-- C. Dropdown Pintar -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Dropdown Pintar (Searchable / Multi-Select)</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.searchable-select</code> · <code>x-form.multi-select</code> — keduanya baru.</p>
            <div class="grid two gap-3.5">
                <x-form.searchable-select label="Lokasi Domisili" wire:model="demoLocation" :options="$this->locationOptions()" placeholder="Pilih atau ketik lokasi..." />
                <x-form.multi-select label="Kemampuan Bahasa" wire:model="demoLanguages" :options="$this->languageOptions()" :selected="['id', 'en']" placeholder="Pilih bahasa..." />
            </div>
        </div>

        <!-- D. Angka & Warna -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Angka, Rentang & Warna</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.number-stepper</code> · <code>x-form.range-slider</code> · <code>x-form.color-picker</code> — semua baru.</p>
            <div class="grid three gap-3.5">
                <x-form.number-stepper label="Jumlah Anak" wire:model="demoChildren" :min="0" :value="0" />
                <x-form.range-slider label="Pengeluaran Bulanan" wire:model="demoExpense" :min="0" :max="100000000" :step="1000000" :value="15000000" format="currency" />
                <x-form.color-picker label="Warna Favorit" wire:model="demoColor" :value="$demoColor" />
            </div>
        </div>

        <!-- E. Format Khusus -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Format Khusus (Masking & Prefix)</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.masked-input</code> · <code>x-form.phone-input</code> · <code>x-form.currency-input</code> — semua baru.</p>
            <div class="grid three gap-3.5">
                <x-form.masked-input label="No. KTP" wire:model="demoNik" :segments="[6, 6, 4]" separator="." hint="Format 6.6.4 digit" />
                <x-form.phone-input label="No. HP" wire:model="demoPhone" />
                <x-form.currency-input label="Valuasi Investasi" wire:model="demoValuation" />
            </div>
        </div>

        <!-- F. Tanggal & Kalender -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Tanggal & Kalender</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.date-picker</code> (sudah ada, mode date/datetime) · <code>x-form.mini-calendar</code> (baru, versi ringkas dari mockup kalender — month-view + event, tanpa drag&drop/multi-view; kalau butuh kalender penuh utk fitur bisnis nyata mis. cuti HRM, itu layar tersendiri).</p>
            <div class="grid two gap-3.5 mb-3.5">
                <x-form.date-picker label="Tanggal Lahir" name="demoBirthDate" mode="date" />
                <x-form.date-picker label="Jadwal Meeting" name="demoMeetingAt" mode="datetime" />
            </div>
            <x-form.mini-calendar :events="$this->calendarSampleEvents()" />
        </div>

        <!-- G. Verifikasi & Rating -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Verifikasi & Rating</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.otp-input</code> · <code>x-form.star-rating</code> — semua baru.</p>
            <div class="grid two gap-3.5">
                <x-form.otp-input label="OTP Code (6 Digit)" wire:model="demoOtp" :length="6" />
                <x-form.star-rating label="Rating Pengalaman" wire:model="demoRating" :max="5" />
            </div>
        </div>

        <!-- H. Upload File -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Upload File (Drag & Drop)</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.file-dropzone</code> — baru, native Livewire <code>WithFileUploads</code> (bukan simulasi).</p>
            <x-form.file-dropzone label="Upload Dokumen" wire:model="demoFile" accept="image/*,.pdf" :current-name="$demoFileName" />
        </div>

        <!-- I. Aksi & Tombol -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Aksi & Status</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.split-button</code> (baru) · <code>x-form.badge</code> (sudah ada, ditampilkan lagi sbg referensi tone).</p>
            <div class="flex items-center gap-4 flex-wrap">
                <div class="w-[180px]">
                    <x-form.split-button label="Save" icon="💾" wire:click="demoSave('Split Button')" :actions="$this->splitButtonActions()" />
                </div>
                <x-form.badge tone="default">DRAFT</x-form.badge>
                <x-form.badge tone="info">SUBMITTED</x-form.badge>
                <x-form.badge tone="warn">PENDING</x-form.badge>
                <x-form.badge tone="success">APPROVED</x-form.badge>
                <x-form.badge tone="danger">REJECTED</x-form.badge>
            </div>
        </div>

        <!-- J. QR Code -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5" x-data="tmQrDemo()">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">QR Code Generator</div>
            <p class="text-[11px] text-muted mb-4">Generate QR code 100% lokal di browser (npm <code>qrcode</code>) — TIDAK kirim data ke API pihak ketiga, beda dari mockup asli yang pakai CDN qrcodejs (sama-sama client-side, tapi ini bebas dependency eksternal saat runtime).</p>
            <button type="button" @click="generate('Demo QR — ' + new Date().toISOString())"
                class="px-4 py-2 rounded-md text-xs font-semibold bg-[#059669] text-white cursor-pointer">📱 Generate QR Code Demo</button>

            <div x-show="open" x-cloak x-transition class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click.self="open = false">
                <div class="bg-white rounded-xl p-5 w-[280px] flex flex-col items-center gap-3">
                    <div class="text-sm font-bold text-navy">QR Code Demo</div>
                    <canvas x-ref="canvas" class="border border-[#e2e8f0] rounded-lg"></canvas>
                    <div class="flex gap-2 w-full">
                        <button type="button" @click="download()" class="flex-1 py-1.5 rounded-md text-xs font-semibold bg-accent text-white cursor-pointer">Download PNG</button>
                        <button type="button" @click="open = false" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-[#eef1f5] text-[#4b5568] cursor-pointer">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
