<?php

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\AppInterviewSchedule;
use App\Models\Hrr\AppPipelineStageConfig;
use App\Models\Sys\SecUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// L5 -- docs/recruitment_tier4.md SECTION 10, REVISI 2026-08-29 (instruksi
// user): dibangun ulang jadi kalender ala Google Calendar (Day/Month/
// 3-Month/Year) + drag-drop kandidat dari sidebar ke tanggal/jam utk
// menjadwalkan. Pola drag-drop sama dgn pipeline-board.blade.php
// (x-data draggedId + x-on:dragstart/dragover.prevent/drop) & referensi
// production/monitoring-dashboard.blade.php.
new #[Title('Jadwal Interview — TELASIH MAKMUR ERP')] class extends Component
{
    public string $viewMode = 'month'; // day|month|3month|year

    public string $focusDate;

    public bool $showModal = false;

    public ?int $schedulingApplicationId = null;

    public string $schedulingDate = '';

    public string $schedulingTime = '09:00';

    public ?int $interviewer_id = null;

    public string $location = '';

    public ?int $selectedScheduleId = null;

    public function mount(): void
    {
        $this->focusDate = now()->format('Y-m-d');
    }

    #[Computed]
    public function interviewers()
    {
        return SecUser::where('activate', true)->orderBy('name')->pluck('name', 'id_user');
    }

    // Application di stage INTERVIEW_HR/INTERVIEW_USER & belum ada jadwal
    // SCHEDULED aktif utk stage itu -- daftar di sidebar kiri, sumber drag.
    #[Computed]
    public function candidatesAwaiting()
    {
        $stageConfigs = AppPipelineStageConfig::whereIn('stage_code', ['INTERVIEW_HR', 'INTERVIEW_USER'])->get();
        $apps = AppApplication::with(['candidate', 'vacancy.requisition'])->where('status', 'IN_PROGRESS')->get();

        return $apps->map(function ($app) use ($stageConfigs) {
            $trackId = $app->vacancy->requisition->id_track ?? null;
            $stage = $stageConfigs->firstWhere(fn ($s) => $s->id_track === $trackId && $s->stage_seq === $app->current_stage_seq);

            if (! $stage) {
                return null;
            }

            $hasSchedule = AppInterviewSchedule::where('id_application', $app->id_application)->where('stage_code', $stage->stage_code)->where('status', 'SCHEDULED')->exists();

            return $hasSchedule ? null : (object) ['app' => $app, 'stage' => $stage];
        })->filter()->values();
    }

    // public (bukan protected) -- dipanggil langsung dari Blade markup di
    // bawah ($this->focus()->...), scope compiled-view Livewire SFC tidak
    // punya akses ke method protected/private class ini.
    public function focus(): Carbon
    {
        return Carbon::parse($this->focusDate);
    }

    #[Computed]
    public function rangeStart(): Carbon
    {
        return match ($this->viewMode) {
            'day' => $this->focus()->copy()->startOfDay(),
            'month' => $this->focus()->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
            '3month' => $this->focus()->copy()->startOfMonth(),
            'year' => $this->focus()->copy()->startOfYear(),
            default => $this->focus()->copy()->startOfMonth(),
        };
    }

    #[Computed]
    public function rangeEnd(): Carbon
    {
        return match ($this->viewMode) {
            'day' => $this->focus()->copy()->endOfDay(),
            'month' => $this->focus()->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY),
            '3month' => $this->focus()->copy()->addMonths(2)->endOfMonth(),
            'year' => $this->focus()->copy()->endOfYear(),
            default => $this->focus()->copy()->endOfMonth(),
        };
    }

    // Jadwal dalam rentang tampil, dikelompokkan per tanggal (Y-m-d).
    #[Computed]
    public function schedulesByDate(): array
    {
        return AppInterviewSchedule::with('application.candidate')
            ->whereBetween('scheduled_at', [$this->rangeStart, $this->rangeEnd])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($s) => $s->scheduled_at->format('Y-m-d'))
            ->all();
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function navPrev(): void
    {
        $this->shiftFocus(-1);
    }

    public function navNext(): void
    {
        $this->shiftFocus(1);
    }

    protected function shiftFocus(int $dir): void
    {
        $d = $this->focus();
        $this->focusDate = match ($this->viewMode) {
            'day' => $d->addDays($dir)->format('Y-m-d'),
            'month' => $d->addMonths($dir)->format('Y-m-d'),
            '3month' => $d->addMonths(3 * $dir)->format('Y-m-d'),
            'year' => $d->addYears($dir)->format('Y-m-d'),
            default => $d->addMonths($dir)->format('Y-m-d'),
        };
    }

    public function navToday(): void
    {
        $this->focusDate = now()->format('Y-m-d');
    }

    public function goToDate(string $date): void
    {
        $this->focusDate = $date;
        $this->viewMode = 'day';
    }

    // Dipanggil saat drop kartu kandidat ke sel tanggal (month/3month/year)
    // atau slot jam (day) -- buka modal pre-filled, HR tinggal konfirmasi
    // interviewer + lokasi (waktu sudah terisi dari tempat drop, tetap bisa diubah).
    public function dropOnDate(?int $applicationId, string $date, ?int $hour = null): void
    {
        if (! $applicationId) {
            return;
        }

        $this->schedulingApplicationId = $applicationId;
        $this->schedulingDate = $date;
        $this->schedulingTime = $hour !== null ? sprintf('%02d:00', $hour) : '09:00';
        $this->interviewer_id = null;
        $this->location = '';
        $this->showModal = true;
    }

    public function save(): void
    {
        if (! $this->interviewer_id || ! $this->schedulingDate || ! $this->schedulingTime) {
            $this->addError('form', 'Tanggal/jam dan interviewer wajib diisi.');

            return;
        }

        $app = AppApplication::with('vacancy.requisition')->findOrFail($this->schedulingApplicationId);
        $stage = AppPipelineStageConfig::where('id_track', $app->vacancy->requisition->id_track)
            ->where('stage_seq', $app->current_stage_seq)->first();

        AppInterviewSchedule::create([
            'id_application' => $this->schedulingApplicationId,
            'stage_code' => $stage->stage_code ?? 'INTERVIEW_HR',
            'scheduled_at' => "{$this->schedulingDate} {$this->schedulingTime}",
            'interviewer_id' => $this->interviewer_id,
            'location' => $this->location,
            'status' => 'SCHEDULED',
        ]);

        $interviewerLogin = SecUser::where('id_user', $this->interviewer_id)->value('login');
        \App\Models\Sys\SysNotification::create([
            'user_id' => $interviewerLogin,
            'title' => 'Jadwal Interview Baru',
            'message' => 'Anda dijadwalkan interview kandidat '.($app->candidate->name ?? '').' pada '.Carbon::parse("{$this->schedulingDate} {$this->schedulingTime}")->translatedFormat('d M Y H:i').'.',
            'is_read' => false,
        ]);

        $this->showModal = false;
        unset($this->schedulesByDate, $this->candidatesAwaiting);
    }

    public function selectSchedule(int $id): void
    {
        $this->selectedScheduleId = $id;
    }

    #[Computed]
    public function selectedSchedule(): ?AppInterviewSchedule
    {
        return $this->selectedScheduleId ? AppInterviewSchedule::with('application.candidate')->find($this->selectedScheduleId) : null;
    }

    public function markDone(): void
    {
        AppInterviewSchedule::where('id_schedule', $this->selectedScheduleId)->update(['status' => 'DONE']);
        $this->selectedScheduleId = null;
        unset($this->schedulesByDate);
    }

    public function cancelSchedule(): void
    {
        AppInterviewSchedule::where('id_schedule', $this->selectedScheduleId)->update(['status' => 'CANCELLED']);
        $this->selectedScheduleId = null;
        unset($this->schedulesByDate, $this->candidatesAwaiting);
    }

    // Grid tanggal utk month/3month/year -- array Carbon, chunked per 7
    // (minggu). public (bukan protected) -- dipanggil dari Blade, sama
    // alasan seperti focus() di atas.
    public function weeksFor(Carbon $monthAnchor): array
    {
        $start = $monthAnchor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $end = $monthAnchor->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return array_chunk($days, 7);
    }
};
?>

<div class="max-w-[1500px] mx-auto my-5 px-4" x-data="{ draggedId: null }">
    <div class="flex gap-4">
        {{-- SIDEBAR: kandidat menunggu jadwal, sumber drag --}}
        <div class="w-[240px] shrink-0">
            <div class="bg-white border border-[#d7dbe2] rounded-md p-3 sticky top-3">
                <p class="text-[10.5px] font-bold text-muted uppercase mb-2">Menunggu Dijadwalkan ({{ $this->candidatesAwaiting->count() }})</p>
                <div class="space-y-1.5">
                    @forelse ($this->candidatesAwaiting as $row)
                        <div draggable="true" title="Seret ke slot jadwal di kalender untuk menjadwalkan interview" x-on:dragstart="draggedId = {{ $row->app->id_application }}"
                            class="text-[11px] px-2.5 py-2 rounded-md border border-[#d7dbe2] bg-[#fef6e7] cursor-grab active:cursor-grabbing">
                            <p class="font-semibold">{{ $row->app->candidate->name ?? '—' }}</p>
                            <p class="text-[10px] text-muted">{{ $row->stage->stage_name }} · {{ $row->app->vacancy->title ?? '' }}</p>
                        </div>
                    @empty
                        <p class="text-[10.5px] text-muted">Semua sudah terjadwal.</p>
                    @endforelse
                </div>
                <p class="text-[10px] text-muted mt-3">🖱 Geser kartu ke tanggal/jam di kalender untuk menjadwalkan.</p>
            </div>
        </div>

        {{-- CALENDAR --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 bg-white border border-[#d7dbe2] rounded-md px-3 py-2.5 mb-3 flex-wrap">
                <button type="button" wire:click="navToday" class="text-[11px] font-bold border border-[#d7dbe2] px-2.5 py-1.5 rounded">Hari Ini</button>
                <button type="button" wire:click="navPrev" class="text-xs px-2 py-1.5 rounded hover:bg-[#eef3fb]">←</button>
                <button type="button" wire:click="navNext" class="text-xs px-2 py-1.5 rounded hover:bg-[#eef3fb]">→</button>
                <p class="text-sm font-bold text-navy ml-1">
                    @if ($viewMode === 'day') {{ $this->focus()->translatedFormat('d MMMM Y') }}
                    @elseif ($viewMode === 'month') {{ $this->focus()->translatedFormat('MMMM Y') }}
                    @elseif ($viewMode === '3month') {{ $this->focus()->translatedFormat('MMM Y') }} – {{ $this->focus()->copy()->addMonths(2)->translatedFormat('MMM Y') }}
                    @else {{ $this->focus()->format('Y') }}
                    @endif
                </p>
                <span class="ml-auto flex items-center gap-1 bg-[#eef1f5] rounded-lg p-1">
                    @foreach (['day' => 'Hari', 'month' => 'Bulan', '3month' => '3 Bulan', 'year' => 'Tahun'] as $mode => $label)
                        <button type="button" wire:click="setViewMode('{{ $mode }}')"
                            class="text-[10.5px] font-bold px-2.5 py-1 rounded-md {{ $viewMode === $mode ? 'bg-white text-navy shadow-sm' : 'text-muted' }}">{{ $label }}</button>
                    @endforeach
                </span>
            </div>

            @error('form') <div class="bg-[#fdecec] border border-[#f5c6c6] text-[#a3453f] text-xs rounded-md px-4 py-2.5 mb-3">⚠ {{ $message }}</div> @enderror

            <div class="bg-white border border-[#d7dbe2] rounded-md overflow-hidden">
                @if ($viewMode === 'day')
                    {{-- DAY VIEW: timeline jam 07-20 --}}
                    <div class="divide-y divide-[#eef1f5]">
                        @for ($h = 7; $h <= 20; $h++)
                            @php $dateStr = $this->focus()->format('Y-m-d'); $items = collect($this->schedulesByDate[$dateStr] ?? [])->filter(fn($s) => (int) $s->scheduled_at->format('H') === $h); @endphp
                            <div class="flex items-stretch min-h-[52px]"
                                x-on:dragover.prevent x-on:drop="$wire.dropOnDate(draggedId, '{{ $dateStr }}', {{ $h }}); draggedId = null">
                                <div class="w-16 shrink-0 text-[10.5px] text-muted px-2 py-2 border-r border-[#eef1f5]">{{ sprintf('%02d:00', $h) }}</div>
                                <div class="flex-1 p-1.5 flex flex-wrap gap-1.5 hover:bg-[#f8fafc]">
                                    @foreach ($items as $s)
                                        <button type="button" wire:click="selectSchedule({{ $s->id_schedule }})"
                                            class="text-[10.5px] text-left px-2 py-1 rounded {{ match($s->status) { 'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f] line-through', default => 'bg-[#eaf2ff] text-[#1d4ed8]' } }}">
                                            {{ $s->scheduled_at->format('H:i') }} · {{ $s->application->candidate->name ?? '—' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                    </div>
                @elseif ($viewMode === 'year')
                    {{-- YEAR VIEW: 12 mini bulan, klik tanggal -> Day view --}}
                    <div class="grid three gap-3 p-3">
                        @for ($m = 1; $m <= 12; $m++)
                            @php $anchor = Carbon::create((int) $this->focus()->format('Y'), $m, 1); @endphp
                            <div class="border border-[#eef1f5] rounded-md p-1.5">
                                <p class="text-[10.5px] font-bold text-navy text-center mb-1">{{ $anchor->translatedFormat('MMMM') }}</p>
                                <div class="[display:grid] grid-cols-7 gap-0.5 text-center">
                                    @foreach ($this->weeksFor($anchor) as $week)
                                        @foreach ($week as $day)
                                            @php $inMonth = $day->month === $m; $ds = $day->format('Y-m-d'); $has = isset($this->schedulesByDate[$ds]); @endphp
                                            <button type="button" wire:click="goToDate('{{ $ds }}')"
                                                x-on:dragover.prevent x-on:drop="$wire.dropOnDate(draggedId, '{{ $ds }}'); draggedId = null"
                                                class="text-[8.5px] h-4 w-4 flex items-center justify-center rounded-sm {{ $inMonth ? ($has ? 'bg-[#1d4ed8] text-white font-bold' : 'text-[#4b5568] hover:bg-[#eef3fb]') : 'text-[#c8ccd6]' }}">{{ $day->day }}</button>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        @endfor
                    </div>
                @else
                    {{-- MONTH / 3-MONTH VIEW: grid bulan penuh --}}
                    @php $anchors = $viewMode === '3month' ? [$this->focus(), $this->focus()->copy()->addMonth(), $this->focus()->copy()->addMonths(2)] : [$this->focus()]; @endphp
                    <div class="{{ $viewMode === '3month' ? 'grid three divide-x divide-[#eef1f5]' : '' }}">
                        @foreach ($anchors as $anchor)
                            <div>
                                @if ($viewMode === '3month')
                                    <p class="text-xs font-bold text-navy text-center py-2 border-b border-[#eef1f5]">{{ $anchor->translatedFormat('MMMM Y') }}</p>
                                @endif
                                <div class="[display:grid] grid-cols-7 text-center text-[10px] font-bold text-muted uppercase border-b border-[#eef1f5]">
                                    @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $d)
                                        <div class="py-1.5">{{ $d }}</div>
                                    @endforeach
                                </div>
                                @foreach ($this->weeksFor($anchor) as $week)
                                    <div class="[display:grid] grid-cols-7 divide-x divide-[#eef1f5] border-b border-[#eef1f5]">
                                        @foreach ($week as $day)
                                            @php $inMonth = $day->month === $anchor->month; $ds = $day->format('Y-m-d'); $items = $this->schedulesByDate[$ds] ?? []; $isToday = $day->isToday(); @endphp
                                            <div class="{{ $viewMode === '3month' ? 'min-h-[54px]' : 'min-h-[92px]' }} p-1 {{ $inMonth ? 'bg-white' : 'bg-[#f8fafc]' }} hover:bg-[#f0f5ff]"
                                                x-on:dragover.prevent x-on:drop="$wire.dropOnDate(draggedId, '{{ $ds }}'); draggedId = null">
                                                <button type="button" wire:click="goToDate('{{ $ds }}')"
                                                    class="text-[10.5px] w-5 h-5 flex items-center justify-center rounded-full {{ $isToday ? 'bg-navy text-white font-bold' : ($inMonth ? 'text-[#2c3e69]' : 'text-[#c8ccd6]') }}">{{ $day->day }}</button>
                                                <div class="mt-0.5 space-y-0.5">
                                                    @foreach (collect($items)->take($viewMode === '3month' ? 2 : 3) as $s)
                                                        <button type="button" wire:click.stop="selectSchedule({{ $s->id_schedule }})"
                                                            class="w-full text-left text-[9.5px] truncate px-1 py-0.5 rounded {{ match($s->status) { 'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f] line-through', default => 'bg-[#eaf2ff] text-[#1d4ed8]' } }}">
                                                            {{ $s->scheduled_at->format('H:i') }} {{ $s->application->candidate->name ?? '' }}
                                                        </button>
                                                    @endforeach
                                                    @if (count($items) > ($viewMode === '3month' ? 2 : 3))
                                                        <p class="text-[9px] text-muted px-1">+{{ count($items) - ($viewMode === '3month' ? 2 : 3) }} lagi</p>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- MODAL: konfirmasi jadwal baru (dari drop) --}}
    @if ($showModal)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="$set('showModal', false)">
            <div class="bg-white rounded-xl w-full max-w-[420px] p-5">
                <h3 class="text-sm font-bold text-navy mb-3">Jadwalkan Interview</h3>
                @error('form') <p class="text-[11px] text-[#a3241f] mb-2">⚠ {{ $message }}</p> @enderror
                <div class="space-y-3">
                    <div class="grid two">
                        <x-form.date-picker label="Tanggal" wire:model="schedulingDate" />
                        <x-form.input label="Jam" type="time" wire:model="schedulingTime" />
                    </div>
                    <x-form.select wire:model="interviewer_id" :options="$this->interviewers->all()" placeholder="— pilih interviewer —">
                        <x-slot:label>Interviewer</x-slot:label>
                    </x-form.select>
                    <x-form.input label="Lokasi" wire:model="location" placeholder="mis. Ruang Meeting HR" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="$set('showModal', false)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Batal</button>
                    <button type="button" wire:click="save" class="bg-navy text-white text-xs font-bold px-4 py-2 rounded-md">Simpan Jadwal</button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL: detail jadwal existing (klik chip) --}}
    @if ($selectedScheduleId && $this->selectedSchedule)
        <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60] p-4" wire:click.self="selectSchedule(0)">
            <div class="bg-white rounded-xl w-full max-w-[380px] p-5">
                <h3 class="text-sm font-bold text-navy mb-1">{{ $this->selectedSchedule->application->candidate->name ?? '—' }}</h3>
                <p class="text-[11.5px] text-muted mb-3">{{ $this->selectedSchedule->stage_code }} · {{ $this->selectedSchedule->scheduled_at?->translatedFormat('d M Y, H:i') }} @if($this->selectedSchedule->location) · {{ $this->selectedSchedule->location }} @endif</p>
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ match ($this->selectedSchedule->status) { 'DONE' => 'bg-[#eaf7ee] text-[#1f8a4c]', 'CANCELLED' => 'bg-[#fbeaea] text-[#a3241f]', default => 'bg-[#eaf2ff] text-[#1d4ed8]' } }}">{{ $this->selectedSchedule->status }}</span>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="selectSchedule(0)" class="border border-[#d7dbe2] text-xs font-bold px-4 py-2 rounded-md">Tutup</button>
                    @if ($this->selectedSchedule->status === 'SCHEDULED')
                        <button type="button" wire:click="cancelSchedule" wire:confirm="Batalkan jadwal ini?" class="bg-[#fdecec] text-[#a3241f] text-xs font-bold px-4 py-2 rounded-md">Batalkan</button>
                        <button type="button" wire:click="markDone" class="bg-[#1f8a4c] text-white text-xs font-bold px-4 py-2 rounded-md">Tandai Selesai</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
