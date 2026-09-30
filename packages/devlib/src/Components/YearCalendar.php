<?php

namespace Devlib\Components;

use Carbon\CarbonImmutable;
use Devlib\Lang;
use Devlib\Locale;

// Kalender setahun (12 bulan sekaligus) untuk hari libur / hari kerja: tanggal bertanda diwarnai per tone,
// hari non-kerja (offDays, 0 = Minggu) bertulisan merah seperti kalender dinding. Render server, tanpa JS.
// marks: ['YYYY-MM-DD' => ['label' => .., 'tone' => danger|warn|info|success|neutral, 'url' => ?]]
// legend: [tone => label]. Klik tanggal bertanda = url tanda itu; tanggal kosong = newUrl ({date} diganti tanggal).
class YearCalendar extends DevlibComponent
{
    public function __construct(
        public int $year,
        public array $marks = [],
        public array $legend = [],
        public array $offDays = [0],
        public ?string $newUrl = null,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::year-calendar';
    }

    public function source(): string
    {
        return $this->source ?? 'tanggal bertanda';
    }

    // Tiap bulan: nama + sel tanggal (null = sel kosong sebelum tanggal 1), minggu mulai Senin.
    public function months(): array
    {
        $lang = Locale::lang();
        $today = CarbonImmutable::today()->toDateString();

        return array_map(function (int $m) use ($lang, $today) {
            $first = CarbonImmutable::create($this->year, $m, 1);
            $cells = array_fill(0, $first->dayOfWeekIso - 1, null);
            for ($d = $first; $d->month === $m; $d = $d->addDay()) {
                $date = $d->toDateString();
                $mark = $this->marks[$date] ?? null;
                $cells[] = ['day' => $d->day, 'date' => $date, 'mark' => $mark, 'today' => $date === $today,
                    'off' => in_array($d->dayOfWeek, $this->offDays, true),
                    'url' => $mark['url'] ?? ($this->newUrl ? str_replace('{date}', $date, $this->newUrl) : null),
                    'title' => Locale::date($date).($mark ? ' · '.$mark['label'] : '')];
            }

            return ['name' => $first->locale($lang)->translatedFormat('F'), 'cells' => $cells,
                'count' => count(array_filter($this->marks, fn ($k) => str_starts_with($k, $first->format('Y-m')), ARRAY_FILTER_USE_KEY))];
        }, range(1, 12));
    }

    public function weekdays(): array
    {
        return array_map(fn ($i) => CarbonImmutable::create(2024, 1, $i)->locale(Locale::lang())->isoFormat('dd'), range(1, 7));
    }

    public function legendItems(): array
    {
        return array_map(fn ($l) => Lang::text($l), $this->legend);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-ycal']) }} {!! $dl() !!}>
                @if ($legend)
                    <div class="dl-ycal__legend">
                        @foreach ($legendItems() as $tone => $label)
                            <span><i class="dl-ycal__sw dl-ycal__d--{{ $tone }}"></i>{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="dl-ycal__grid">
                    @foreach ($months() as $m)
                        <div class="dl-ycal__m">
                            <div class="dl-ycal__mh"><b>{{ $m['name'] }}</b>@if ($m['count'])<span class="dl-ycal__n">{{ $m['count'] }}</span>@endif</div>
                            <div class="dl-ycal__days">
                                @foreach ($weekdays() as $i => $w)<span class="dl-ycal__h {{ $i === 6 && in_array(0, $offDays, true) ? 'is-off' : '' }}">{{ $w }}</span>@endforeach
                                @foreach ($m['cells'] as $c)
                                    @if ($c === null)
                                        <span></span>
                                    @elseif ($c['url'])
                                        <a href="{{ $c['url'] }}" title="{{ $c['title'] }}" @class(['dl-ycal__d', 'dl-ycal__d--'.($c['mark']['tone'] ?? 'neutral') => $c['mark'], 'is-off' => $c['off'], 'is-today' => $c['today']])>{{ $c['day'] }}</a>
                                    @else
                                        <span title="{{ $c['title'] }}" @class(['dl-ycal__d', 'dl-ycal__d--'.($c['mark']['tone'] ?? 'neutral') => $c['mark'], 'is-off' => $c['off'], 'is-today' => $c['today']])>{{ $c['day'] }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            BLADE;
    }
}
