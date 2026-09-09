<?php

use App\Livewire\Concerns\HasGridPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

// Layar "Library Component for UI/UX" (permintaan user 2026-09-09) --
// showcase SEMUA pola widget dari docs/mockups/UIUX_components_part1.html
// + mockup_UIUX_calendar.html (versi ringkas), dijadikan komponen Blade
// reusable sungguhan di resources/views/components/form/*.blade.php --
// BUKAN sekadar tempel HTML mockup mentah. Tujuan: jadi "patokan" visual +
// kontrak pemakaian (wire:model, hidden-input pattern spt x-form.date-picker)
// sebelum dipakai di layar bisnis nyata. Murni referensi -- tombol
// "Simpan"/dst di sini cuma demo dispatch app-alert, TIDAK menulis ke DB
// mana pun.
new #[Title('DevLib UI UX')] class extends Component
{
    use HasGridPagination;
    use WithFileUploads;
    use WithPagination;

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

    // Tab utama (Forms/Calendar/Kanban/Gantt Chart) + device viewport switcher
    // -- SENGAJA server-side (Livewire property), BUKAN Alpine x-data client-
    // only spt versi awal. Ditemukan bug nyata 2026-09-09: setiap wire:click
    // dari DALAM satu tab (mis. calSetView di tab Calendar) memicu morph
    // Livewire atas SELURUH komponen -- morph itu memutus reaktivitas x-show/
    // x-if Alpine utk bagian LAIN yang tak terkait (tombol switcher tab jadi
    // macet: state Alpine brubah tapi DOM tak ikut update, dibuktikan lewat
    // Alpine.$data() vs computed style langsung di browser). Server jadi
    // satu-satunya sumber kebenaran menghindari SELURUH kelas bug ini --
    // konten tab dipilih via @if PHP biasa, bukan x-show/x-if Alpine.
    public string $mainTab = 'forms';

    public string $device = 'laptop';

    public function setMainTab(string $tab): void
    {
        if (in_array($tab, ['grid', 'forms', 'calendar', 'kanban', 'gantt', 'graphics'], true)) {
            $this->mainTab = $tab;
        }
    }

    public function setDevice(string $device): void
    {
        if (in_array($device, ['laptop', 'tablet', 'mobile'], true)) {
            $this->device = $device;
        }
    }

    public function deviceMaxWidth(): string
    {
        return match ($this->device) {
            'tablet' => '768px',
            'mobile' => '390px',
            default => '100%',
        };
    }

    // Tab Calendar (permintaan user 2026-09-09) -- replika docs/mockups/
    // mockup_UIUX_calendar.html. Data event demo di properti (bukan DB),
    // dimutasi via method di bawah -- server tetap sumber kebenaran posisi
    // event, sama filosofi Kanban (drag cuma "usulan" sampai re-render).
    public array $calEvents = [];

    public string $calView = 'week';

    public string $calCurrentDate = '';

    public int $calMiniYear = 0;

    public int $calMiniMonth = 0;

    public ?int $calEditingId = null;

    public string $calSearchQuery = '';

    public string $calEditTitle = '';

    public string $calEditStart = '';

    public string $calEditEnd = '';

    public string $calEditDesc = '';

    public function mount(): void
    {
        $today = now();
        $this->calCurrentDate = $today->toDateString();
        $this->calMiniYear = (int) $today->year;
        $this->calMiniMonth = (int) $today->month;

        $this->calEvents = [
            ['id' => 1, 'title' => '1 pending task', 'start' => $today->format('Y-m-d').'T09:00', 'end' => $today->format('Y-m-d').'T17:00', 'colorClass' => 'amber', 'desc' => 'Selesaikan laporan Q3.'],
            ['id' => 2, 'title' => 'Ulang Tahun Sarah', 'start' => $today->copy()->addDay()->format('Y-m-d').'T00:00', 'end' => $today->copy()->addDay()->format('Y-m-d').'T23:59', 'colorClass' => 'red', 'desc' => 'Beli kado dan kue.'],
            ['id' => 3, 'title' => 'Design Review', 'start' => $today->copy()->addDays(7)->format('Y-m-d').'T10:00', 'end' => $today->copy()->addDays(7)->format('Y-m-d').'T12:00', 'colorClass' => 'green', 'desc' => 'Mockup approval.'],
            ['id' => 4, 'title' => 'Cuti panjang', 'start' => $today->copy()->addDays(10)->format('Y-m-d').'T08:00', 'end' => $today->copy()->addDays(11)->format('Y-m-d').'T18:00', 'colorClass' => 'green', 'desc' => 'Liburan ke Bali.', 'span' => 2],
        ];

        // Due date demo relatif ke "sekarang" (bukan tanggal statis) -- salah
        // satu sengaja di masa lalu supaya status overdue kelihatan di kartu.
        $this->demoKanbanCards[0]['dueDate'] = $today->copy()->addDays(2)->format('Y-m-d\TH:i');
        $this->demoKanbanCards[1]['dueDate'] = $today->copy()->addDays(6)->format('Y-m-d\TH:i');
        $this->demoKanbanCards[2]['dueDate'] = $today->copy()->subDay()->format('Y-m-d\TH:i');
    }

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

    // --- Tab Calendar: navigasi & CRUD event demo ---

    public function calToday(): void
    {
        $this->calCurrentDate = now()->toDateString();
        $this->calCloseEdit();
    }

    public function calPrev(): void
    {
        $this->calShift(-1);
    }

    public function calNext(): void
    {
        $this->calShift(1);
    }

    protected function calShift(int $dir): void
    {
        $d = Carbon::parse($this->calCurrentDate);
        match ($this->calView) {
            'day' => $d->addDays($dir),
            'week' => $d->addWeeks($dir),
            'month' => $d->addMonths($dir),
        };
        $this->calCurrentDate = $d->toDateString();
        $this->calCloseEdit();
    }

    public function calSetView(string $view): void
    {
        if (in_array($view, ['day', 'week', 'month'], true)) {
            $this->calView = $view;
        }
    }

    public function calSelectDate(string $dateStr): void
    {
        $this->calCurrentDate = $dateStr;
        $this->calCloseEdit();
    }

    public function calMiniPrevMonth(): void
    {
        $this->calShiftMini(-1);
    }

    public function calMiniNextMonth(): void
    {
        $this->calShiftMini(1);
    }

    protected function calShiftMini(int $dir): void
    {
        $d = Carbon::create($this->calMiniYear, $this->calMiniMonth, 1)->addMonths($dir);
        $this->calMiniYear = (int) $d->year;
        $this->calMiniMonth = (int) $d->month;
    }

    public function calOpenEdit(int $id): void
    {
        $ev = collect($this->calEvents)->firstWhere('id', $id);
        if (! $ev) {
            return;
        }
        $this->calCurrentDate = substr($ev['start'], 0, 10);
        $this->calEditingId = $id;
        $this->calEditTitle = $ev['title'];
        $this->calEditStart = $ev['start'];
        $this->calEditEnd = $ev['end'];
        $this->calEditDesc = $ev['desc'] ?? '';
    }

    public function calCreateNew(): void
    {
        $newId = ((int) (collect($this->calEvents)->max('id') ?? 0)) + 1;
        $this->calEvents[] = [
            'id' => $newId,
            'title' => 'Event Baru',
            'start' => $this->calCurrentDate.'T09:00',
            'end' => $this->calCurrentDate.'T10:00',
            'colorClass' => 'green',
            'desc' => '',
        ];
        $this->calOpenEdit($newId);
    }

    public function calCloseEdit(): void
    {
        $this->calEditingId = null;
    }

    public function calSaveEdit(): void
    {
        foreach ($this->calEvents as &$e) {
            if ($e['id'] === $this->calEditingId) {
                $e['title'] = $this->calEditTitle !== '' ? $this->calEditTitle : 'Tanpa judul';
                $e['start'] = $this->calEditStart ?: $e['start'];
                $e['end'] = $this->calEditEnd ?: $e['end'];
                $e['desc'] = $this->calEditDesc;
                break;
            }
        }
        unset($e);
        $this->calCloseEdit();
    }

    public function calDeleteEvent(int $id): void
    {
        $this->calEvents = collect($this->calEvents)->reject(fn ($e) => $e['id'] === $id)->values()->all();
        if ($this->calEditingId === $id) {
            $this->calCloseEdit();
        }
    }

    public function calMoveEvent(?int $id, string $targetDateStr): void
    {
        if (! $id) {
            return;
        }
        foreach ($this->calEvents as &$e) {
            if ($e['id'] === $id) {
                $timeStart = substr($e['start'], 10);
                $timeEnd = substr($e['end'], 10);
                $e['start'] = $targetDateStr.$timeStart;
                $e['end'] = $targetDateStr.$timeEnd;
                break;
            }
        }
    }

    public function calEventsOn(string $dateStr): array
    {
        return collect($this->calEvents)->filter(fn ($e) => substr($e['start'], 0, 10) === $dateStr)->values()->all();
    }

    public function calTodayEvents(): array
    {
        return $this->calEventsOn(now()->toDateString());
    }

    public function calSearchResults(): array
    {
        $q = mb_strtolower($this->calSearchQuery);

        return collect($this->calEvents)
            ->filter(fn ($e) => str_contains(mb_strtolower($e['title']), $q) || str_contains(mb_strtolower($e['desc'] ?? ''), $q))
            ->values()->all();
    }

    public function calFormatTime(string $datetime): string
    {
        return Carbon::parse($datetime)->format('H:i');
    }

    protected function calMonthNames(): array
    {
        return ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    }

    public function calTitle(): string
    {
        $d = Carbon::parse($this->calCurrentDate);

        return match ($this->calView) {
            'day' => $d->day.' '.$this->calMonthNames()[$d->month - 1].' '.$d->year,
            default => $this->calMonthNames()[$d->month - 1].' '.$d->year,
        };
    }

    public function calMiniLabel(): string
    {
        return $this->calMonthNames()[$this->calMiniMonth - 1].' '.$this->calMiniYear;
    }

    public function calSelectedDateLabel(): string
    {
        $names = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $d = Carbon::parse($this->calCurrentDate);

        return $names[$d->dayOfWeek].', '.$d->day.' '.$this->calMonthNames()[$d->month - 1];
    }

    // Fill hari bulan sebelum/sesudah (dimmed, non-interaktif) -- PERSIS
    // algoritma renderMonthGrid() mockup asli (prevMonthDays/nextMonthDaysNeeded),
    // supaya grid selalu penuh baris (tidak berhenti nanggung di tengah
    // baris terakhir spt versi awal yang cuma isi leading null).
    public function calMonthGrid(): array
    {
        $start = Carbon::parse($this->calCurrentDate)->startOfMonth();
        $firstDow = $start->dayOfWeek;
        $daysInMonth = $start->daysInMonth;
        $prevMonthLastDay = $start->copy()->subDay()->day;

        $cells = [];
        for ($i = $firstDow - 1; $i >= 0; $i--) {
            $cells[] = ['type' => 'adjacent', 'day' => $prevMonthLastDay - $i];
        }
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = $start->copy()->day($day)->toDateString();
            $cells[] = [
                'type' => 'current',
                'day' => $day,
                'dateStr' => $dateStr,
                'isToday' => $dateStr === now()->toDateString(),
                'events' => $this->calEventsOn($dateStr),
            ];
        }
        $nextNeeded = (7 - (($firstDow + $daysInMonth) % 7)) % 7;
        for ($i = 1; $i <= $nextNeeded; $i++) {
            $cells[] = ['type' => 'adjacent', 'day' => $i];
        }

        return $cells;
    }

    public function calMiniCalGrid(): array
    {
        $start = Carbon::create($this->calMiniYear, $this->calMiniMonth, 1);
        $firstDow = $start->dayOfWeek;
        $daysInMonth = $start->daysInMonth;

        $cells = array_fill(0, $firstDow, null);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = $start->copy()->day($day)->toDateString();
            $cells[] = [
                'day' => $day,
                'dateStr' => $dateStr,
                'isToday' => $dateStr === now()->toDateString(),
                'isSelected' => $dateStr === $this->calCurrentDate,
            ];
        }

        return $cells;
    }

    public function calWeekDays(): array
    {
        $dayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $anchor = Carbon::parse($this->calCurrentDate);

        if ($this->calView === 'day') {
            $days = [$anchor];
        } else {
            $start = $anchor->copy()->startOfWeek(Carbon::SUNDAY);
            $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i))->all();
        }

        return collect($days)->map(fn (Carbon $d) => [
            'dateStr' => $d->toDateString(),
            'dayNum' => $d->day,
            'dayLabel' => $dayLabels[$d->dayOfWeek],
            'isToday' => $d->isToday(),
        ])->all();
    }

    // VM utk <x-calendar.board> (component scope terisolasi, tak bisa panggil
    // $this->calFormatTime()/calSpanStyle() sendiri) -- HALAMAN pre-format
    // field turunan (timeLabel/spanStyle) ke tiap event, pola sama gantt/
    // kanban (komponen cuma render, tak pernah hitung ulang domain logic).
    public function calWeekDaysWithEvents(): array
    {
        return collect($this->calWeekDays())->map(function (array $d) {
            $d['events'] = collect($this->calEventsOn($d['dateStr']))
                ->map(fn ($ev) => [...$ev, 'spanStyle' => $this->calSpanStyle($ev)])
                ->all();

            return $d;
        })->all();
    }

    public function calTodayEventsVm(): array
    {
        return collect($this->calTodayEvents())
            ->map(fn ($ev) => [...$ev, 'timeLabel' => $this->calFormatTime($ev['start']).' – '.$this->calFormatTime($ev['end'])])
            ->all();
    }

    public function calEventsOnVm(string $dateStr): array
    {
        return collect($this->calEventsOn($dateStr))
            ->map(fn ($ev) => [...$ev, 'timeLabel' => $this->calFormatTime($ev['start']).' – '.$this->calFormatTime($ev['end'])])
            ->all();
    }

    // Lebar event multi-hari ("span") di baris all-day -- PERSIS mockup asli:
    // width: calc(span*100% + span*2px) SUPAYA visual melebar menembus kolom
    // hari berikutnya (all-day-col TIDAK overflow:hidden). Cuma berlaku view
    // 'week' (7 kolom kelihatan sekaligus) -- 'day' cuma 1 kolom, span tak
    // bermakna, sama persis kondisi `currentView === 'week'` di JS aslinya.
    public function calSpanStyle(array $event): string
    {
        $span = (int) ($event['span'] ?? 1);
        if ($span <= 1 || $this->calView !== 'week') {
            return '';
        }

        return "width: calc({$span}*100% + {$span}*2px); position: relative; z-index: 5;";
    }

    // Tab Grid (2026-09-09) -- showcase pola docs/griduistandard.md (kotak
    // statistik pastel, toolbar search, kolom identitas avatar+nama+kode,
    // Aksi Edit+Toggle-aktif ala grid Master, footer pagination 3-bagian).
    // Data dummy statis di memori (bukan tabel DB) -- HasGridPagination
    // dipakai apa adanya (perPage/gridPageRange), paginasi dibangun manual
    // di atas Collection krn sumbernya array, bukan Eloquent builder.
    public string $demoGridSearch = '';

    protected function demoGridSourceRows(): array
    {
        return collect(range(1, 34))->map(fn ($i) => [
            'id' => $i,
            'name' => 'Customer Demo '.$i,
            'code' => 'CUST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'city' => ['Surabaya', 'Jakarta', 'Bandung', 'Medan'][$i % 4],
            'active' => $i % 5 !== 0,
            'balance' => $i * 1250000,
        ])->all();
    }

    public function demoGridItems(): LengthAwarePaginator
    {
        $filtered = collect($this->demoGridSourceRows())
            ->when($this->demoGridSearch !== '', fn ($c) => $c->filter(fn ($r) => str_contains(strtolower($r['name']), strtolower($this->demoGridSearch))
                || str_contains(strtolower($r['code']), strtolower($this->demoGridSearch))))
            ->values();

        $page = Paginator::resolveCurrentPage('page') ?: 1;

        return new LengthAwarePaginator(
            $filtered->forPage($page, $this->perPage)->values(),
            $filtered->count(),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );
    }

    // Tab Graphics (2026-09-09) -- 3 varian dasar chart via Chart.js CDN
    // (docs/reportstandard.md: "Library grafik -> Chart.js via CDN"),
    // ikut skill dataviz: urutan hue kategorikal TETAP (tak pernah di-cycle),
    // 1 hue utk seri tunggal (garis), legend+tooltip bawaan Chart.js, TANPA
    // dual-axis. Data pie sengaja dari hitungan RIIL demoKanbanCards (bukan
    // angka acak) supaya nyambung ke tab Kanban di halaman yang sama.
    protected function graphicsCategoricalPalette(): array
    {
        return ['#2563eb', '#b45309', '#16a34a', '#6d28d9', '#c0392b'];
    }

    public function graphicsPieConfig(): array
    {
        $columns = $this->demoKanbanColumns;
        $palette = $this->graphicsCategoricalPalette();

        return [
            'type' => 'pie',
            'data' => [
                'labels' => collect($columns)->pluck('label')->all(),
                'datasets' => [[
                    'data' => collect($columns)->map(fn ($c) => count($this->demoKanbanCardsForColumn($c['key'])))->all(),
                    'backgroundColor' => array_slice($palette, 0, count($columns)),
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['font' => ['size' => 11]]]],
            ],
        ];
    }

    public function graphicsBarConfig(): array
    {
        $labels = ['Kotak Karton', 'Kemasan Fleksibel', 'Label Cetak', 'Kemasan Rigid'];
        $palette = $this->graphicsCategoricalPalette();

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Volume Produksi (unit)',
                    'data' => [18200, 12400, 9600, 6100],
                    'backgroundColor' => array_slice($palette, 0, count($labels)),
                    'borderRadius' => 6,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ];
    }

    public function graphicsLineConfig(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->copy()->subMonths($i)->format('M Y'))->all();

        return [
            'type' => 'line',
            'data' => [
                'labels' => $months,
                'datasets' => [[
                    'label' => 'Omzet (Rp juta)',
                    'data' => [820, 910, 875, 960, 1040, 1120],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#2563eb',
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => false]],
            ],
        ];
    }

    // Tab Kanban (2026-09-09, rebuild penuh) -- replika docs/mockups/
    // mockup_UIUX_kanban.html (Trello-style: kolom judul-editable, composer
    // kartu, tag/prioritas/due-date/assignee, drag-drop antar+dalam kolom,
    // modal detail dgn popover Labels/Dates/Checklist/Members, deskripsi
    // inline-edit, checklist+progress bar, komentar). Komponen reusable
    // "x-kanban.board" DIBANGUN di sesi ini atas instruksi eksplisit user,
    // meng-override docs/kanbanboardstandard.md §1 (dulu bilang tunggu
    // kanban ke-3) -- lihat kanbanboardstandard.md utk pola dasarnya.
    public array $demoKanbanColumns = [
        ['key' => 'BARU', 'label' => 'Baru'],
        ['key' => 'PROSES', 'label' => 'Diproses'],
        ['key' => 'SELESAI', 'label' => 'Selesai'],
    ];

    public string $kanbanView = 'board';

    // Kartu modal detail Kanban SENGAJA server-side ($kanbanActiveCardId,
    // BUKAN Alpine activeCardId lokal) -- ditemukan bug nyata 2026-09-09:
    // modal yg dibuka via <template x-if="activeCardId===X"> "membeku" isi
    // checklist/comment-nya begitu ada wire:click LAIN (mis. toggle item)
    // sementara modal masih terbuka -- Alpine memperlakukan cabang x-if yg
    // sudah true sbg "miliknya", tak ikut di-diff ulang morph Livewire thd
    // HTML baru dari server (persis kelas bug $mainTab/x-show yg sudah
    // didokumentasikan panjang lebar di atas, kali ini kena x-if per-kartu).
    // Server jadi satu-satunya sumber kebenaran KARTU MANA yg terbuka.
    public ?int $kanbanActiveCardId = null;

    public function kanbanOpenCard(int $cardId): void
    {
        $this->kanbanActiveCardId = $cardId;
    }

    public function kanbanCloseCard(): void
    {
        $this->kanbanActiveCardId = null;
    }

    public array $demoKanbanCards = [
        [
            'id' => 1, 'columnKey' => 'BARU', 'order' => 0,
            'title' => 'Order #1001 — PT Sinar Jaya',
            'desc' => 'Konfirmasi desain kemasan & jadwal produksi awal sebelum masuk antrean produksi.',
            'tags' => [['label' => 'desain', 'color' => 'blue'], ['label' => 'urgent', 'color' => 'orange']],
            'priority' => 'high', 'dueDate' => null, 'assignee' => 'Johannes Iwan',
            'checklistTitle' => 'Persiapan Desain', 'checklist' => [
                ['id' => 1, 'text' => 'Kirim proof ke customer', 'done' => true],
                ['id' => 2, 'text' => 'Approval warna dari Direksi', 'done' => false],
            ],
            'comments' => [
                ['author' => 'Johannes Iwan', 'initials' => 'JI', 'text' => 'Menunggu approval customer.', 'at' => '2 hari lalu'],
            ],
        ],
        [
            'id' => 2, 'columnKey' => 'BARU', 'order' => 1,
            'title' => 'Order #1002 — CV Makmur Abadi',
            'desc' => 'Evaluasi ketersediaan bahan baku kraft utk kuantitas order.',
            'tags' => [['label' => 'material', 'color' => 'purple']],
            'priority' => 'medium', 'dueDate' => null, 'assignee' => 'Rina',
            'checklistTitle' => null, 'checklist' => [], 'comments' => [],
        ],
        [
            'id' => 3, 'columnKey' => 'PROSES', 'order' => 0,
            'title' => 'Order #1003 — Toko Abadi Jaya',
            'desc' => 'Proses cetak berjalan di Mesin A, target selesai akhir minggu.',
            'tags' => [['label' => 'produksi', 'color' => 'green']],
            'priority' => 'medium', 'dueDate' => null, 'assignee' => 'Johannes Iwan',
            'checklistTitle' => null, 'checklist' => [], 'comments' => [],
        ],
        [
            'id' => 4, 'columnKey' => 'SELESAI', 'order' => 0,
            'title' => 'Order #1004 — PT Cipta Karya',
            'desc' => 'Selesai produksi, menunggu jadwal pengiriman.',
            'tags' => [['label' => 'siap-kirim', 'color' => 'blue']],
            'priority' => 'low', 'dueDate' => null, 'assignee' => 'Rina',
            'checklistTitle' => null, 'checklist' => [], 'comments' => [],
        ],
    ];

    public function kanbanSetView(string $view): void
    {
        if (in_array($view, ['board', 'tabular'], true)) {
            $this->kanbanView = $view;
        }
    }

    public function demoKanbanCardsForColumn(string $columnKey): array
    {
        return collect($this->demoKanbanCards)->where('columnKey', $columnKey)->sortBy('order')->values()->all();
    }

    // VM per-kolom sudah membawa kartu masing-masing (pola sama gantt: HALAMAN
    // resolve Row/Block, komponen cuma render mekanika UI) -- x-kanban.board
    // tak perlu tahu apa itu "columnKey" cocok dgn kartu mana.
    public function demoKanbanBoardColumns(): array
    {
        return collect($this->demoKanbanColumns)->map(fn ($col) => [
            'key' => $col['key'],
            'label' => $col['label'],
            'cards' => $this->demoKanbanCardsForColumn($col['key']),
        ])->all();
    }

    // $targetIndex null = taruh di akhir kolom tujuan (drop ke area kosong
    // paling bawah). Server = sumber kebenaran posisi kartu (sama filosofi
    // Gantt/Calendar) -- drag di layar cuma usulan visual sampai re-render.
    public function kanbanMoveCard(int $cardId, string $targetColumnKey, ?int $targetIndex = null): void
    {
        $cards = collect($this->demoKanbanCards);
        $card = $cards->firstWhere('id', $cardId);
        if (! $card) {
            return;
        }

        $others = $cards->reject(fn ($c) => $c['id'] === $cardId);
        $card['columnKey'] = $targetColumnKey;

        $siblings = $others->where('columnKey', $targetColumnKey)->sortBy('order')->values();
        $insertAt = $targetIndex === null ? $siblings->count() : max(0, min($targetIndex, $siblings->count()));
        $siblings->splice($insertAt, 0, [$card]);
        $siblings = $siblings->values()->map(fn ($c, $i) => [...$c, 'order' => $i]);

        $rest = $others->where('columnKey', '!=', $targetColumnKey);
        $this->demoKanbanCards = $rest->merge($siblings)->values()->all();
    }

    public function kanbanAddCard(string $columnKey, string $title, ?string $dueDate = null): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        $this->demoKanbanCards[] = [
            'id' => (collect($this->demoKanbanCards)->max('id') ?? 0) + 1,
            'columnKey' => $columnKey,
            'order' => collect($this->demoKanbanCards)->where('columnKey', $columnKey)->count(),
            'title' => $title, 'desc' => '', 'tags' => [], 'priority' => null,
            'dueDate' => $dueDate ?: null, 'assignee' => null,
            'checklistTitle' => null, 'checklist' => [], 'comments' => [],
        ];
    }

    public function kanbanAddColumn(string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        $this->demoKanbanColumns[] = ['key' => 'COL_'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)), 'label' => $title];
    }

    public function kanbanRenameColumn(string $columnKey, string $title): void
    {
        $title = trim($title);
        if ($title === '') {
            return;
        }

        foreach ($this->demoKanbanColumns as &$col) {
            if ($col['key'] === $columnKey) {
                $col['label'] = $title;
                break;
            }
        }
    }

    public function kanbanUpdateCard(int $cardId, string $field, ?string $value): void
    {
        if (! in_array($field, ['title', 'desc', 'priority', 'dueDate'], true)) {
            return;
        }

        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card[$field] = $value === '' ? null : $value;
                break;
            }
        }
    }

    public function kanbanAddChecklist(int $cardId, string $title): void
    {
        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['checklistTitle'] = trim($title) !== '' ? trim($title) : 'Checklist';
                break;
            }
        }
    }

    public function kanbanDeleteChecklist(int $cardId): void
    {
        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['checklistTitle'] = null;
                $card['checklist'] = [];
                break;
            }
        }
    }

    public function kanbanAddChecklistItem(int $cardId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['checklist'][] = ['id' => (collect($card['checklist'])->max('id') ?? 0) + 1, 'text' => $text, 'done' => false];
                break;
            }
        }
    }

    public function kanbanToggleChecklistItem(int $cardId, int $itemId): void
    {
        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                foreach ($card['checklist'] as &$item) {
                    if ($item['id'] === $itemId) {
                        $item['done'] = ! $item['done'];
                        break;
                    }
                }
                break;
            }
        }
    }

    public function kanbanAddTag(int $cardId, string $label, string $color): void
    {
        $label = trim($label);
        if ($label === '' || ! in_array($color, ['blue', 'orange', 'green', 'purple'], true)) {
            return;
        }

        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['tags'][] = ['label' => $label, 'color' => $color];
                break;
            }
        }
    }

    public function kanbanRemoveTag(int $cardId, int $tagIndex): void
    {
        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                unset($card['tags'][$tagIndex]);
                $card['tags'] = array_values($card['tags']);
                break;
            }
        }
    }

    public function kanbanAddComment(int $cardId, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        foreach ($this->demoKanbanCards as &$card) {
            if ($card['id'] === $cardId) {
                $card['comments'][] = ['author' => 'Johannes Iwan', 'initials' => 'JI', 'text' => $text, 'at' => 'Baru saja'];
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

<div>
    {{-- Tab utama (bentuk pill segmented spt referensi screenshot "By you /
    Recents / By others", warna ikut tema terang halaman) + device viewport
    switcher (mockup UIUX_components_part1.html, topbar "Mode Tampilan").
    SENGAJA wire:click server-side (lihat catatan panjang di properti
    $mainTab/$device PHP class atas) -- BUKAN Alpine x-data client-only,
    supaya tidak macet setiap kali ada morph Livewire dari tab manapun. --}}
    <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#e2e8f0] px-4 py-3 flex items-center justify-between gap-3 flex-wrap shadow-sm">
        <div class="inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
            <span class="text-muted text-[10.5px] font-semibold pl-2.5 pr-1">Mode Tampilan:</span>
            <button type="button" wire:click="setDevice('laptop')" class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors {{ $device === 'laptop' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy' }}">🖥️ Laptop</button>
            <button type="button" wire:click="setDevice('tablet')" class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors {{ $device === 'tablet' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy' }}">📱 Tablet</button>
            <button type="button" wire:click="setDevice('mobile')" class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer transition-colors {{ $device === 'mobile' ? 'bg-accent text-white shadow-sm' : 'text-muted hover:text-navy' }}">📱 Smartphone</button>
        </div>
        <div class="inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
            <button type="button" wire:click="setMainTab('grid')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'grid' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Grid</button>
            <button type="button" wire:click="setMainTab('forms')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'forms' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Form</button>
            <button type="button" wire:click="setMainTab('calendar')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'calendar' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Calendar</button>
            <button type="button" wire:click="setMainTab('kanban')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'kanban' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Kanban</button>
            <button type="button" wire:click="setMainTab('gantt')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'gantt' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Gantt Chart</button>
            <button type="button" wire:click="setMainTab('graphics')" class="px-4 py-1.5 rounded-full text-xs font-semibold cursor-pointer transition-colors {{ $mainTab === 'graphics' ? 'bg-navy text-white shadow-sm' : 'text-muted hover:text-navy' }}">Graphics</button>
        </div>
    </div>

    <div class="mx-auto my-5 px-4 flex flex-col gap-5 transition-[max-width] duration-300" style="max-width: {{ $this->deviceMaxWidth() }}">

        {{-- Katalog pola widget form/interaksi yang tersedia di project ini
        (resources/views/components/form/*.blade.php) -- acuan visual &
        kontrak pemakaian sebelum dipakai di layar bisnis nyata. Sumber:
        docs/mockups/UIUX_components_part1.html + mockup_UIUX_calendar.html.
        Semua interaksi di halaman ini murni demo (tidak menulis ke
        database). Deskripsi tiap section dipindah ke komentar (bukan
        ditampilkan di UI) atas permintaan user 2026-09-09.

        Heading kartu "Library Component for UI/UX" dulu ada di sini
        (kartu putih terpisah, redundan di atas SETIAP tab) -- dihapus dari
        layar atas permintaan user 2026-09-09 (browser tab title diganti
        "DevLib UI UX" via #[Title(...)] di atas). Dicatat di sini SEBAGAI
        KOMENTAR saja (bukan ditampilkan di UI): halaman/tab APA PUN yang
        ditambahkan ke layar ini WAJIB berupa referensi/katalog component
        library UI/UX -- bukan fitur bisnis sungguhan. --}}

        {{-- SEMUA tab SELALU dirender (bukan @if yang menghapus dari DOM) --
        tab Gantt punya <script> inline (x-gantt.board) yang HANYA jalan
        kalau ada di HTML awal, TIDAK jalan kalau disuntik belakangan via
        morph Livewire (browser tidak eksekusi <script> hasil innerHTML).
        Visibilitas murni class CSS dihitung ULANG server tiap render --
        TIDAK pakai x-show Alpine (state Alpine terbukti bisa putus/berhenti
        reaktif thd DOM setelah morph Livewire dari tab lain, lihat catatan
        panjang di $mainTab properti PHP atas). --}}

        <!-- Tab Grid -->
        <div class="flex flex-col gap-5 {{ $mainTab === 'grid' ? '' : 'hidden' }}">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Grid</div>
            {{-- Showcase docs/griduistandard.md — kotak statistik pastel, toolbar
            search minimal, kolom identitas avatar+nama+kode, Aksi Edit+Toggle-
            aktif (pola grid Master), footer pagination 3-bagian ala ScriptCase. --}}
        </div>

        @php $gridPaginator = $this->demoGridItems(); @endphp
        <div class="bg-white border border-[#e2e8f0] rounded-xl overflow-hidden">
            <x-grid.stat-row :count="3">
                <x-grid.stat-box tone="total" label="Total Customer" :value="count($this->demoGridSourceRows())" />
                <x-grid.stat-box tone="active" label="Aktif" :value="collect($this->demoGridSourceRows())->where('active', true)->count()" />
                <x-grid.stat-box tone="critical" label="Nonaktif" :value="collect($this->demoGridSourceRows())->where('active', false)->count()" />
            </x-grid.stat-row>

            <x-grid.toolbar>
                <input type="text" wire:model.live.debounce.300ms="demoGridSearch" placeholder="Cari nama/kode..."
                    class="px-2.5 py-1.5 border border-[#d7dbe2] rounded-md text-[12px] w-56 focus:border-accent">
                <x-slot:newRecord>
                    <button type="button" wire:click="demoSave('Grid + New Record')" class="px-3 py-1.5 rounded-md bg-accent text-white text-[11.5px] font-semibold cursor-pointer hover:opacity-90">➕ New Record</button>
                </x-slot:newRecord>
            </x-grid.toolbar>

            <x-grid.table-shell>
                <table class="w-full text-[12px]">
                    <thead>
                        <tr>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Identitas</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Kota</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Saldo</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Status</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-center px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase w-[90px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($gridPaginator as $row)
                            <tr wire:key="demo-grid-row-{{ $row['id'] }}" class="hover:bg-[#f8f9fb]">
                                <x-grid.identity-cell :id="$row['id']" :name="$row['name']" :code="$row['code']" />
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#374151]">{{ $row['city'] }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#374151]">Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $row['active'] ? 'bg-[#e9f9ef] text-[#16a34a]' : 'bg-[#eef1f5] text-[#4b5568]' }}">{{ $row['active'] ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    <div class="flex items-center justify-center gap-1">
                                        <x-grid.action-icon icon="✏️" title="Edit" action="demoSave('Grid Edit')" />
                                        <x-grid.action-icon icon="🔁" title="Toggle Aktif" action="demoSave('Grid Toggle Aktif')" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>

            @include('components.grid.pagination-footer', ['paginator' => $gridPaginator])
        </div>

        </div>

        {{-- SEMUA tab SELALU dirender (bukan @if yang menghapus dari DOM) --
        tab Gantt punya <script> inline (x-gantt.board) yang HANYA jalan
        kalau ada di HTML awal, TIDAK jalan kalau disuntik belakangan via
        morph Livewire (browser tidak eksekusi <script> hasil innerHTML).
        Visibilitas murni class CSS dihitung ULANG server tiap render --
        TIDAK pakai x-show Alpine (state Alpine terbukti bisa putus/berhenti
        reaktif thd DOM setelah morph Livewire dari tab lain, lihat catatan
        panjang di $mainTab properti PHP atas). --}}
        <div class="flex flex-col gap-5 {{ $mainTab === 'forms' ? '' : 'hidden' }}">

        <!-- A. Teks & Password -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Input Teks & Password</div>
            {{-- x-form.case-toggle-input (baru, auto-height + tombol ganti huruf kecil/Kapital/BESAR) · x-form.password-input (baru, mata show/hide). --}}
            <div class="grid two gap-3.5">
                <x-form.case-toggle-input label="Nama Lengkap" wire:model="demoName" placeholder="Masukkan nama..." />
                <x-form.password-input label="Password" wire:model="demoPassword" placeholder="Password..." />
            </div>
        </div>

        <!-- B. Pilihan: Radio / Checkbox / Switch -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Pilihan (Radio / Checkbox / Switch)</div>
            {{-- x-form.radio-group, x-form.checkbox (sudah ada, dipakai berjajar), x-form.switch — semua baru kecuali checkbox. --}}
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
            {{-- x-form.searchable-select · x-form.multi-select — keduanya baru. --}}
            <div class="grid two gap-3.5">
                <x-form.searchable-select label="Lokasi Domisili" wire:model="demoLocation" :options="$this->locationOptions()" placeholder="Pilih atau ketik lokasi..." />
                <x-form.multi-select label="Kemampuan Bahasa" wire:model="demoLanguages" :options="$this->languageOptions()" :selected="['id', 'en']" placeholder="Pilih bahasa..." />
            </div>
        </div>

        <!-- D. Angka & Warna -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Angka, Rentang & Warna</div>
            {{-- x-form.number-stepper · x-form.range-slider · x-form.color-picker · x-form.decimal-input (baru, auto-format N desimal saat blur + unit suffix) — semua baru. --}}
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
            {{-- x-form.masked-input · x-form.phone-input · x-form.currency-input — semua baru. --}}
            <div class="grid three gap-3.5">
                <x-form.masked-input label="No. KTP" wire:model="demoNik" :segments="[6, 6, 4]" separator="." hint="Format 6.6.4 digit" />
                <x-form.phone-input label="No. HP" wire:model="demoPhone" />
                <x-form.currency-input label="Valuasi Investasi" wire:model="demoValuation" />
            </div>
        </div>

        <!-- F. Tanggal -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Tanggal</div>
            {{-- x-form.date-picker (sudah ada, mode date/datetime) · x-form.date-range (baru, 2 tanggal + kalkulator durasi otomatis). Kalender penuh (bulan/minggu/hari, drag-drop event) ada di tab Calendar. --}}
            <div class="grid two gap-3.5 mb-3.5">
                <x-form.date-picker label="Tanggal Lahir" name="demoBirthDate" mode="date" />
                <x-form.date-picker label="Jadwal Meeting" name="demoMeetingAt" mode="datetime" />
            </div>
            <x-form.date-range label="Masa Kerja (Awal s.d. Akhir)" start-name="demoWorkStart" end-name="demoWorkEnd" start-label="Mulai" end-label="Selesai" />
        </div>

        <!-- G. Verifikasi & Rating -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Verifikasi & Rating</div>
            {{-- x-form.otp-input · x-form.star-rating — semua baru. --}}
            <div class="grid two gap-3.5">
                <x-form.otp-input label="OTP Code (6 Digit)" wire:model="demoOtp" :length="6" />
                <x-form.star-rating label="Rating Pengalaman" wire:model="demoRating" :max="5" />
            </div>
        </div>

        <!-- H. Upload File -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Upload File (Drag & Drop)</div>
            {{-- x-form.file-dropzone — baru, native Livewire WithFileUploads (bukan simulasi). --}}
            <x-form.file-dropzone label="Upload Dokumen" wire:model="demoFile" accept="image/*,.pdf" :current-name="$demoFileName" />
        </div>

        <!-- I. Aksi & Tombol -->
        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Aksi & Status</div>
            {{-- x-form.split-button (baru) · x-form.badge (sudah ada, ditampilkan lagi sbg referensi tone). --}}
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
            {{-- Generate QR code 100% lokal di browser (npm qrcode) — TIDAK kirim data ke API pihak ketiga, beda dari mockup asli yang pakai CDN qrcodejs (sama-sama client-side, tapi ini bebas dependency eksternal saat runtime). --}}
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

        <!-- Tab Calendar -->
        <div class="{{ $mainTab === 'calendar' ? '' : 'hidden' }}">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Event Calendar</div>
            {{-- Replika docs/mockups/mockup_UIUX_calendar.html — navbar penuh (judul+Today+panah+judul bulan+switch Day/Week/Month) di atas 3 panel (mini-kalender+cari kiri, grid tengah, Today's Events+edit kanan), drag-drop event antar tanggal (hover cuma highlight sel tujuan, tag jadi transparan saat ditarik — persis mockup), event multi-hari melebar di baris all-day view Week (persis mockup — bukan di Month, mockup asli juga begitu). Server = sumber kebenaran (sama filosofi Kanban). Tema warna ikut terang halaman ini (mockup asli dark). Kalender ringan tanpa semua ini: x-form.mini-calendar. --}}
        </div>

        <x-calendar.board
            :view="$calView"
            :current-date="$calCurrentDate"
            :editing-id="$calEditingId"
            :search-query="$calSearchQuery"
            :title="$this->calTitle()"
            :mini-label="$this->calMiniLabel()"
            :mini-cal-grid="$this->calMiniCalGrid()"
            :month-grid="$this->calMonthGrid()"
            :week-days="$this->calWeekDaysWithEvents()"
            :search-results="$this->calSearchResults()"
            :today-events="$this->calTodayEventsVm()"
            :selected-date-label="$this->calSelectedDateLabel()"
            :events-on-current="$this->calEventsOnVm($calCurrentDate)"
        />

        </div>
        </div>

        <!-- Tab Kanban -->
        <div class="{{ $mainTab === 'kanban' ? '' : 'hidden' }}">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="flex items-center justify-between mb-1">
                <div class="text-sm font-bold text-navy uppercase tracking-wide">Kanban Board</div>
                {{-- Toggle Board/Tabular SENGAJA server-side ($kanbanView, bukan Alpine
                x-data lokal) -- konsisten dgn fix $mainTab: sibling wire:click di dalam
                tab ini (mis. drop kartu) memicu morph Livewire penuh, yang terbukti
                memutus reaktivitas x-show utk elemen TAK terkait (root cause bug
                Day/Week/Month sebelumnya). --}}
                <div class="inline-flex items-center gap-1 bg-[#f1f3f7] border border-[#e2e8f0] rounded-full p-1">
                    <button type="button" wire:click="kanbanSetView('board')" class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $kanbanView === 'board' ? 'bg-navy text-white' : 'text-muted hover:text-navy' }}">Board</button>
                    <button type="button" wire:click="kanbanSetView('tabular')" class="px-3 py-1 rounded-full text-[11px] font-semibold cursor-pointer {{ $kanbanView === 'tabular' ? 'bg-navy text-white' : 'text-muted hover:text-navy' }}">Tabular</button>
                </div>
            </div>
            {{-- Replika docs/mockups/mockup_UIUX_kanban.html — komponen reusable
            "x-kanban.board" (resources/views/components/kanban/board.blade.php),
            lihat docs/kanbanboardstandard.md. Tampilan "Tabular" = pola grid
            griduistandard.md sbg alternatif tabel datar dari data kartu yang sama. --}}
        </div>

        <div class="{{ $kanbanView === 'board' ? '' : 'hidden' }} bg-white border border-[#e2e8f0] rounded-xl p-4 overflow-hidden">
            <x-kanban.board :columns="$this->demoKanbanBoardColumns()" :active-card-id="$kanbanActiveCardId" />
        </div>

        <div class="{{ $kanbanView === 'tabular' ? '' : 'hidden' }} bg-white border border-[#e2e8f0] rounded-xl overflow-hidden">
            @php
                $kanbanRows = collect($this->demoKanbanBoardColumns())->flatMap(fn ($col) => collect($col['cards'])->map(fn ($card) => [...$card, 'columnLabel' => $col['label']]));
                $kanbanPrioTones = ['high' => 'bg-[#fdecec] text-[#c0392b]', 'medium' => 'bg-[#fef6e7] text-[#b45309]', 'low' => 'bg-[#eef1f5] text-[#4b5568]'];
                $kanbanPrioLabels = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
            @endphp
            <x-grid.stat-row :count="3">
                <x-grid.stat-box tone="total" label="Total Kartu" :value="$kanbanRows->count()" />
                <x-grid.stat-box tone="warn" label="Prioritas Tinggi" :value="$kanbanRows->where('priority', 'high')->count()" />
                <x-grid.stat-box tone="critical" label="Jatuh Tempo Lewat" :value="$kanbanRows->filter(fn ($c) => $c['dueDate'] && \Illuminate\Support\Carbon::parse($c['dueDate'])->isPast())->count()" />
            </x-grid.stat-row>
            <x-grid.table-shell>
                <table class="w-full text-[12px]">
                    <thead>
                        <tr>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Kartu</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Kolom</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Prioritas</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Jatuh Tempo</th>
                            <th class="sticky top-0 z-[1] bg-[#f8f9fb] text-left px-3 py-2 border-b border-[#e2e7ee] font-bold text-[11px] text-muted uppercase">Assignee</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($kanbanRows as $row)
                            @php $rowOverdue = $row['dueDate'] && \Illuminate\Support\Carbon::parse($row['dueDate'])->isPast(); @endphp
                            <tr wire:key="kanban-row-{{ $row['id'] }}" class="hover:bg-[#f8f9fb]">
                                <x-grid.identity-cell :id="$row['id']" :name="$row['title']" :code="'#'.$row['id']" />
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#374151]">{{ $row['columnLabel'] }}</td>
                                <td class="px-3 py-2 border-b border-[#eef1f5]">
                                    @if ($row['priority'])
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $kanbanPrioTones[$row['priority']] }}">{{ $kanbanPrioLabels[$row['priority']] }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] {{ $rowOverdue ? 'text-[#c0392b] font-semibold' : 'text-[#374151]' }}">
                                    {{ $row['dueDate'] ? \Illuminate\Support\Carbon::parse($row['dueDate'])->format('d M Y, H:i') : '—' }}
                                </td>
                                <td class="px-3 py-2 border-b border-[#eef1f5] text-[#374151]">{{ $row['assignee'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-grid.empty-row :colspan="5" />
                        @endforelse
                    </tbody>
                </table>
            </x-grid.table-shell>
        </div>

        </div>
        </div>

        <!-- Tab Gantt Chart -->
        <div class="{{ $mainTab === 'gantt' ? '' : 'hidden' }}">
        <div class="flex flex-col gap-5">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Gantt Chart</div>
            {{-- Komponen reusable "x-gantt.board" yang SUDAH ADA (docs/ganttboardstandard.md, dipakai Scheduling Board Production) — geser blok utk pindah jam mulai, tarik tepi kanan utk ubah durasi, klik utk popover detail, dobel-klik utk pindah mesin/kategori. --}}
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
        </div>

        <!-- Tab Graphics -->
        <div class="flex flex-col gap-5 {{ $mainTab === 'graphics' ? '' : 'hidden' }}">

        <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
            <div class="text-sm font-bold text-navy uppercase tracking-wide mb-1">Graphics</div>
            {{-- 3 varian dasar chart via Chart.js (docs/reportstandard.md), skill
            dataviz: hue kategorikal urutan tetap, 1 hue utk seri garis tunggal,
            legend+tooltip bawaan, tanpa dual-axis. Chart.js CDN dimuat SEKALI di
            sini (bagian dari HTML awal, bukan disuntik morph — sama prinsip
            dgn catatan Gantt di atas), inisialisasi lewat Alpine.data('tmChart',...)
            di component-library.js (pola SUDAH terbukti jalan di halaman ini). --}}
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
                <div class="text-[12.5px] font-bold text-navy uppercase tracking-wide mb-3">Pie — Distribusi Kartu per Kolom Kanban</div>
                <div wire:key="chart-pie" wire:ignore class="h-[260px]" x-data="tmChart({{ Js::from($this->graphicsPieConfig()) }})" x-init="init()">
                    <canvas x-ref="canvas"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] text-muted cursor-pointer select-none">Lihat sebagai tabel</summary>
                    <table class="w-full text-[11.5px] mt-2">
                        <thead><tr><th class="text-left py-1 text-muted font-semibold">Kolom</th><th class="text-right py-1 text-muted font-semibold">Jumlah Kartu</th></tr></thead>
                        <tbody>
                            @foreach ($this->demoKanbanColumns as $col)
                                <tr class="border-t border-[#eef1f5]"><td class="py-1">{{ $col['label'] }}</td><td class="py-1 text-right">{{ count($this->demoKanbanCardsForColumn($col['key'])) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </div>

            <div class="bg-white border border-[#e2e8f0] rounded-xl p-5">
                <div class="text-[12.5px] font-bold text-navy uppercase tracking-wide mb-3">Batang — Volume Produksi per Kategori</div>
                @php $barConfig = $this->graphicsBarConfig(); @endphp
                <div wire:key="chart-bar" wire:ignore class="h-[260px]" x-data="tmChart({{ Js::from($barConfig) }})" x-init="init()">
                    <canvas x-ref="canvas"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] text-muted cursor-pointer select-none">Lihat sebagai tabel</summary>
                    <table class="w-full text-[11.5px] mt-2">
                        <thead><tr><th class="text-left py-1 text-muted font-semibold">Kategori</th><th class="text-right py-1 text-muted font-semibold">Volume (unit)</th></tr></thead>
                        <tbody>
                            @foreach ($barConfig['data']['labels'] as $i => $label)
                                <tr class="border-t border-[#eef1f5]"><td class="py-1">{{ $label }}</td><td class="py-1 text-right">{{ number_format($barConfig['data']['datasets'][0]['data'][$i], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </div>

            <div class="bg-white border border-[#e2e8f0] rounded-xl p-5 lg:col-span-2">
                <div class="text-[12.5px] font-bold text-navy uppercase tracking-wide mb-3">Garis — Tren Omzet 6 Bulan Terakhir</div>
                @php $lineConfig = $this->graphicsLineConfig(); @endphp
                <div wire:key="chart-line" wire:ignore class="h-[260px]" x-data="tmChart({{ Js::from($lineConfig) }})" x-init="init()">
                    <canvas x-ref="canvas"></canvas>
                </div>
                <details class="mt-3">
                    <summary class="text-[11px] text-muted cursor-pointer select-none">Lihat sebagai tabel</summary>
                    <table class="w-full text-[11.5px] mt-2">
                        <thead><tr>
                            @foreach ($lineConfig['data']['labels'] as $label)
                                <th class="text-right py-1 text-muted font-semibold px-2">{{ $label }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody><tr>
                            @foreach ($lineConfig['data']['datasets'][0]['data'] as $val)
                                <td class="text-right py-1 px-2">{{ number_format($val, 0, ',', '.') }}</td>
                            @endforeach
                        </tr></tbody>
                    </table>
                </details>
            </div>
        </div>

        </div>

    </div>
</div>
