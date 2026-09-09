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

    public string $demoWeight = '';

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

    // Tab Kanban (permintaan user 2026-09-09) -- docs/kanbanboardstandard.md
    // §1 EKSPLISIT bilang belum ada komponen x-kanban.board reusable (sengaja
    // ditunda sampai kanban ke-3 muncul dgn pola serupa) -- markup di bawah
    // ini COPY MANUAL pola standar §2 apa adanya, BUKAN abstraksi baru.
    public array $demoKanbanCards = [
        ['id' => 1, 'title' => 'Order #1001 — PT Sinar Jaya', 'stage' => 'BARU'],
        ['id' => 2, 'title' => 'Order #1002 — CV Makmur Abadi', 'stage' => 'BARU'],
        ['id' => 3, 'title' => 'Order #1003 — Toko Abadi Jaya', 'stage' => 'PROSES'],
        ['id' => 4, 'title' => 'Order #1004 — PT Cipta Karya', 'stage' => 'SELESAI'],
    ];

    public function demoKanbanColumns(): array
    {
        return [
            ['key' => 'BARU', 'label' => 'Baru'],
            ['key' => 'PROSES', 'label' => 'Diproses'],
            ['key' => 'SELESAI', 'label' => 'Selesai'],
        ];
    }

    public function moveDemoCard(int $cardId, string $targetStage): void
    {
        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['stage'] = $targetStage;
                break;
            }
        }
    }

    // Tab Gantt Chart (permintaan user 2026-09-09) -- reuse komponen
    // reusable gantt.board yang SUDAH ADA (docs/ganttboardstandard.md,
    // dipakai Scheduling Board Production). Halaman ini yang resolve
    // jam/durasi mentah -> posisi pixel, komponennya sendiri tidak tahu
    // apa itu "mesin"/"proses" (aturan §2 dokumen itu).
    public array $demoGanttBlocks = [
        ['id' => 1, 'resource' => 'm1', 'title' => 'Cetak — Order #1001', 'category' => 'Cetak', 'start' => 8.0, 'duration' => 3.0, 'progress' => 60],
        ['id' => 2, 'resource' => 'm1', 'title' => 'Laminasi — Order #1001', 'category' => 'Laminasi', 'start' => 11.0, 'duration' => 2.0, 'progress' => 15],
        ['id' => 3, 'resource' => 'm2', 'title' => 'Cetak — Order #1002', 'category' => 'Cetak', 'start' => 9.0, 'duration' => 4.0, 'progress' => 80],
    ];

    protected function demoGanttResourceLabels(): array
    {
        return ['m1' => 'Mesin A', 'm2' => 'Mesin B'];
    }

    public function demoGanttRows(): array
    {
        $timelineStart = 8;
        $pxPerHour = 70;
        $categoryColors = ['Cetak' => ['#bfdbfe', '#1e3a8a'], 'Laminasi' => ['#fde68a', '#78350f'], 'Plong' => ['#bbf7d0', '#14532d']];

        $rows = [];
        foreach ($this->demoGanttResourceLabels() as $key => $label) {
            $blocks = collect($this->demoGanttBlocks)
                ->where('resource', $key)
                ->map(function (array $b) use ($timelineStart, $pxPerHour, $categoryColors) {
                    [$bg, $text] = $categoryColors[$b['category']] ?? ['#e5e7eb', '#374151'];

                    return [
                        'id' => $b['id'],
                        'resource' => $b['resource'],
                        'title' => $b['title'],
                        'subtitle' => $b['category'],
                        'timeLabel' => sprintf('%02d:00–%02d:00', (int) $b['start'], (int) ($b['start'] + $b['duration'])),
                        'statusLabel' => 'Berjalan',
                        'left' => ($b['start'] - $timelineStart) * $pxPerHour,
                        'width' => $b['duration'] * $pxPerHour,
                        'progress' => $b['progress'],
                        'bg' => $bg,
                        'text' => $text,
                        'urgent' => false,
                        'dashed' => false,
                        'risk' => false,
                        'locked' => false,
                    ];
                })->values()->all();

            $rows[] = ['resource' => $key, 'label' => $label, 'blocks' => $blocks];
        }

        return $rows;
    }

    public function moveGanttBlock(int $id, float $newStartHour): void
    {
        foreach ($this->demoGanttBlocks as &$b) {
            if ($b['id'] === $id) {
                $b['start'] = $newStartHour;
                break;
            }
        }
    }

    public function resizeGanttBlock(int $id, float $newDurationHour): void
    {
        foreach ($this->demoGanttBlocks as &$b) {
            if ($b['id'] === $id) {
                $b['duration'] = max(0.5, $newDurationHour);
                break;
            }
        }
    }

    public function reassignGanttBlock(int $id, string $resourceKey, string $label): void
    {
        foreach ($this->demoGanttBlocks as &$b) {
            if ($b['id'] === $id) {
                $b['resource'] = $resourceKey;
                $b['category'] = $label;
                break;
            }
        }
    }
};
?>

<div x-data="{ mainTab: 'forms', device: 'laptop', widths: { laptop: '100%', tablet: '768px', mobile: '390px' } }">
    {{-- Tab utama (permintaan user 2026-09-09, bentuk pill segmented spt
    referensi screenshot "By you / Recents / By others" -- TAPI warna ikut
    tema terang halaman ini, bukan dipaksa gelap spt referensinya, permintaan
    lanjutan user 2026-09-09) + device viewport switcher (mockup
    UIUX_components_part1.html, topbar "Mode Tampilan") -- preview lebar
    layar Laptop/Tablet/Smartphone thd tab yg aktif, murni CSS max-width,
    tidak ubah data apapun. --}}
    <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#e2e8f0] px-4 py-3 flex flex-col items-center gap-2.5 shadow-sm">
        <div class="inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
            <button type="button" @click="mainTab = 'forms'" :class="mainTab === 'forms' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors">Forms</button>
            <button type="button" @click="mainTab = 'calendar'" :class="mainTab === 'calendar' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors">Calendar</button>
            <button type="button" @click="mainTab = 'kanban'" :class="mainTab === 'kanban' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors">Kanban</button>
            <button type="button" @click="mainTab = 'gantt'" :class="mainTab === 'gantt' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors">Gantt Chart</button>
        </div>
        <div class="inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
            <span class="text-muted text-[10.5px] font-semibold pl-2.5 pr-1">Mode Tampilan:</span>
            <button type="button" @click="device = 'laptop'" :class="device === 'laptop' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors">🖥️ Laptop</button>
            <button type="button" @click="device = 'tablet'" :class="device === 'tablet' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors">📱 Tablet</button>
            <button type="button" @click="device = 'mobile'" :class="device === 'mobile' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy'"
                class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors">📱 Smartphone</button>
        </div>
    </div>

    <div class="mx-auto my-5 px-4 flex flex-col gap-5 transition-[max-width] duration-300" :style="{ maxWidth: widths[device] }">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Library Component for UI/UX</div>
            <p class="text-[11px] text-muted">Katalog pola widget form/interaksi yang tersedia di project ini (<code>resources/views/components/form/*.blade.php</code>) — acuan visual & kontrak pemakaian sebelum dipakai di layar bisnis nyata. Sumber: <code>docs/mockups/UIUX_components_part1.html</code> + <code>mockup_UIUX_calendar.html</code>. Semua interaksi di bawah ini murni demo (tidak menulis ke database). Pindah tab di atas utk lihat Forms/Calendar/Kanban/Gantt Chart.</p>
        </div>

        <template x-if="mainTab === 'forms'">
        <div class="flex flex-col gap-5">

        <!-- A. Teks & Password -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Input Teks & Password</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.case-toggle-input</code> (baru, auto-height + tombol ganti huruf kecil/Kapital/BESAR) · <code>x-form.password-input</code> (baru, mata show/hide).</p>
            <div class="grid two gap-3.5">
                <x-form.case-toggle-input label="Nama Lengkap" wire:model="demoName" placeholder="Masukkan nama..." />
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
            <p class="text-[11px] text-muted mb-4"><code>x-form.number-stepper</code> · <code>x-form.range-slider</code> · <code>x-form.color-picker</code> · <code>x-form.decimal-input</code> (baru, auto-format N desimal saat blur + unit suffix) — semua baru.</p>
            <div class="grid three gap-3.5">
                <x-form.number-stepper label="Jumlah Anak" wire:model="demoChildren" :min="0" :value="0" />
                <x-form.range-slider label="Pengeluaran Bulanan" wire:model="demoExpense" :min="0" :max="100000000" :step="1000000" :value="15000000" format="currency" />
                <x-form.color-picker label="Warna Favorit" wire:model="demoColor" :value="$demoColor" />
                <x-form.decimal-input label="Berat Badan" wire:model="demoWeight" unit="kg" :decimals="2" />
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
        </template>

        <!-- Tab Calendar -->
        <template x-if="mainTab === 'calendar'">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Tanggal & Kalender</div>
            <p class="text-[11px] text-muted mb-4"><code>x-form.date-picker</code> (sudah ada, mode date/datetime) · <code>x-form.date-range</code> (baru, 2 tanggal + kalkulator durasi otomatis) · <code>x-form.mini-calendar</code> (baru, versi ringkas dari mockup kalender — month-view + event, tanpa drag&drop/multi-view; kalau butuh kalender penuh utk fitur bisnis nyata mis. cuti HRM, itu layar tersendiri).</p>
            <div class="grid two gap-3.5 mb-3.5">
                <x-form.date-picker label="Tanggal Lahir" name="demoBirthDate" mode="date" />
                <x-form.date-picker label="Jadwal Meeting" name="demoMeetingAt" mode="datetime" />
            </div>
            <div class="mb-3.5">
                <x-form.date-range label="Masa Kerja (Awal s.d. Akhir)" start-name="demoWorkStart" end-name="demoWorkEnd" start-label="Mulai" end-label="Selesai" />
            </div>
            <x-form.mini-calendar :events="$this->calendarSampleEvents()" />
        </div>

        </div>
        </template>

        <!-- Tab Kanban -->
        <template x-if="mainTab === 'kanban'">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Kanban Board</div>
            <p class="text-[11px] text-muted mb-4">Pola drag-drop kartu antar kolom status (docs/kanbanboardstandard.md) — BELUM ada komponen "x-kanban.board" reusable (sengaja, standar bilang tunggu kanban ke-3 dgn pola serupa sebelum diekstrak jadi komponen generik). Markup di bawah ini COPY MANUAL pola standar tsb apa adanya, bukan abstraksi baru.</p>
            <div x-data="{ draggedId: null }">
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($this->demoKanbanColumns() as $column)
                        @php $cardsInColumn = collect($demoKanbanCards)->where('stage', $column['key']); @endphp
                        <div class="w-[220px] shrink-0 bg-[#f4f6fa] rounded-lg p-2.5"
                            x-on:dragover.prevent
                            x-on:drop="$wire.moveDemoCard(draggedId, '{{ $column['key'] }}'); draggedId = null"
                            x-bind:class="draggedId ? 'ring-2 ring-[#93c5fd]' : ''">
                            <div class="flex items-center justify-between mb-2 px-1">
                                <p class="text-[11px] font-bold text-navy uppercase">{{ $column['label'] }}</p>
                                <span class="text-[10px] font-bold text-muted bg-white px-1.5 py-0.5 rounded-full">{{ $cardsInColumn->count() }}</span>
                            </div>
                            <div class="space-y-2 min-h-[60px]">
                                @forelse ($cardsInColumn as $card)
                                    <div wire:key="demo-kanban-card-{{ $card['id'] }}" draggable="true"
                                        x-on:dragstart="draggedId = {{ $card['id'] }}"
                                        class="bg-white border border-[#d7dbe2] rounded-md p-2.5 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md text-xs text-[#374151]">
                                        {{ $card['title'] }}
                                    </div>
                                @empty
                                    <p class="text-[10.5px] text-muted text-center py-4">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        </div>
        </template>

        <!-- Tab Gantt Chart -->
        <template x-if="mainTab === 'gantt'">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Gantt Chart</div>
            <p class="text-[11px] text-muted mb-4">Komponen reusable "x-gantt.board" yang SUDAH ADA (docs/ganttboardstandard.md, dipakai Scheduling Board Production) — geser blok utk pindah jam mulai, tarik tepi kanan utk ubah durasi, klik utk popover detail, dobel-klik utk pindah mesin/kategori.</p>
            <x-gantt.board
                :rows="$this->demoGanttRows()"
                resource-header-label="Mesin"
                :timeline-start="8" :timeline-end="18" :px-per-hour="70"
                :resource-options="[['value' => 'm1', 'label' => 'Mesin A'], ['value' => 'm2', 'label' => 'Mesin B']]"
                :label-options="['Cetak', 'Laminasi', 'Plong']"
                label-field-name="Proses"
                empty-message="Tidak ada data demo."
            />
        </div>

        </div>
        </template>

    </div>
</div>
