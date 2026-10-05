<?php

namespace Devlib\Components;

use Devlib\Lang;
use Devlib\Locale;

// Papan Build Progress: berapa objek, modul, layar, form, grid, laporan dan engine yang sudah dibangun, modul mana yang
// sudah penuh / sebagian / belum, dan berapa yang sudah dikunci user setelah QC. Disusun dari StatBox, Card, Progress, Badge.
// build   = isi berkas yang dibuat aplikasi (tmerp: `php artisan build:catalog` -> resources/data/v2/build.json):
//           ['generated','database','totals' => [..], 'modules' => [[label,total,v2,v1,not_built,waiting_qc,locked,form,grid,report]],
//            'engines' => [['group','items' => [['name','purpose','mode']]]], 'features' => [[ikon, teks]], 'notes' => [teks]]
// library = angka katalog library itu sendiri: ['groups' => [label], 'pages' => n, 'approved' => n, 'demos' => n, 'packages' => [nama]]
// Nama modul / engine / fitur adalah DATA milik aplikasi: tidak diterjemahkan objek ini, hanya judul bagiannya.
class BuildBoard extends DevlibComponent
{
    public function __construct(
        public array $build = [],
        public array $library = [],
        public ?string $enginesUrl = null,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::build-board';
    }

    public function source(): string
    {
        return $this->source ?? 'build catalog';
    }

    public function n(mixed $v): string
    {
        return $v === null ? '-' : Locale::number((float) $v, 0);
    }

    public function t(string $key): mixed
    {
        return $this->build['totals'][$key] ?? null;
    }

    public function pct(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0;
    }

    // [label, tone]
    public function state(array $m): array
    {
        return match (true) {
            $m['total'] > 0 && $m['v2'] === $m['total'] => ['Built', 'success'],
            $m['v2'] > 0 => ['Partial', 'warn'],
            default => ['Not started', 'neutral'],
        };
    }

    /** @return array<string, int> mode => jumlah engine */
    public function modes(): array
    {
        $out = [];
        foreach ($this->build['engines'] ?? [] as $g) {
            foreach ($g['items'] as $e) {
                $out[$e['mode']] = ($out[$e['mode']] ?? 0) + 1;
            }
        }
        arsort($out);

        return $out;
    }

    /** @return list<string> pekerjaan terbuka: dihitung dari modul + catatan aplikasi */
    public function pending(): array
    {
        $out = [];
        foreach ($this->build['modules'] ?? [] as $m) {
            if ($m['not_built'] > 0) {
                $out[] = $m['label'].': '.$this->n($m['not_built']).' '.Lang::text('screens not built');
            }
            if ($m['v1'] > 0) {
                $out[] = $m['label'].': '.$this->n($m['v1']).' '.Lang::text('screens still open the old v1 screen');
            }
        }

        return array_merge($out, $this->build['notes'] ?? []);
    }

    public function render()
    {
        return <<<'BLADE'
            @php($T = \Devlib\Lang::class)
            <div {{ $attributes->class('dl-bb') }} {!! $dl() !!} data-dl-build-board>
                <header class="dl-bb__head">
                    <div><b>{{ $T::text('Build Progress') }}</b>
                        <small>{{ $T::text('Snapshot') }} {{ isset($build['generated']) ? \Devlib\Locale::dateTime($build['generated']) : '-' }} · {{ $build['database'] ?? '' }}@if ($t('commits')) · {{ $n($t('commits')) }} {{ $T::text('commits since') }} {{ \Devlib\Locale::date($t('since')) }}@endif</small></div>
                    <small class="dl-bb__src"><i class="fa-solid fa-database"></i> {{ $source() }}</small>
                </header>

                <div class="dl-bb__tiles">
                    <x-dl::stat-box label="DevLib objects" :value="$n($t('objects'))" :sub="\Devlib\Lang::raw(count($library['groups'] ?? []).' '.$T::text('catalog groups'))" />
                    <x-dl::stat-box label="Modules" :value="$n($t('modules'))" tone="success" :sub="\Devlib\Lang::raw($n($t('modules_done')).' '.$T::text('fully on v2'))" />
                    <x-dl::stat-box label="Screens in menu" :value="$n($t('screens'))" :sub="\Devlib\Lang::raw($n($t('v2')).' v2 · '.$n($t('v1') + $t('not_built')).' '.$T::text('not yet'))" />
                    <x-dl::stat-box label="Forms" :value="$n($t('forms'))" :sub="\Devlib\Lang::raw($T::text('menu items'))" />
                    <x-dl::stat-box label="Grids" :value="$n($t('grid_classes'))" :sub="\Devlib\Lang::raw($n($t('lookups')).' '.$T::text('lookup').' · '.$n($t('report_grids')).' '.$T::text('report'))" />
                    <x-dl::stat-box label="Reports" :value="$n($t('reports'))" :sub="\Devlib\Lang::raw($T::text('menu items'))" />
                    <x-dl::stat-box label="Engines" :value="$n($t('engines'))" tone="warn" :sub="\Devlib\Lang::raw($n($t('engine_groups')).' '.$T::text('business areas'))" />
                    <x-dl::stat-box label="Tables" :value="$n($t('tables'))" tone="neutral" :sub="\Devlib\Lang::raw($n($t('databases')).' '.$T::text('databases'))" />
                </div>

                <div class="dl-bb__cols">
                    <x-dl::card title="Modules" class="dl-bb__wide">
                        <x-slot:actions><span class="dl-bb__legend"><i class="is-v2"></i>{{ $T::text('Built on v2') }} <i class="is-v1"></i>{{ $T::text('Still v1') }} <i class="is-no"></i>{{ $T::text('Not built') }}</span></x-slot:actions>
                        <table class="dl-bb__table">
                            <thead><tr><th>{{ $T::text('Module') }}</th><th class="dl-bb__barcol">{{ $T::text('Progress') }}</th><th>{{ $T::text('Screens') }}</th><th>{{ $T::text('Form') }}</th><th>{{ $T::text('Grid') }}</th><th>{{ $T::text('Report') }}</th><th>{{ $T::text('Locked') }}</th><th class="is-left">{{ $T::text('State') }}</th></tr></thead>
                            <tbody>
                                @foreach ($build['modules'] ?? [] as $m)
                                    <tr><td><b>{{ $m['label'] }}</b></td>
                                        <td><span class="dl-bb__bar"><i class="is-v2" style="width:{{ $pct($m['v2'], $m['total']) }}%"></i><i class="is-v1" style="width:{{ $pct($m['v1'], $m['total']) }}%"></i></span></td>
                                        <td>{{ $n($m['v2']) }} / {{ $n($m['total']) }}</td><td>{{ $n($m['form']) }}</td><td>{{ $n($m['grid']) }}</td><td>{{ $n($m['report']) }}</td><td>{{ $n($m['locked']) }}</td>
                                        <td class="is-left"><x-dl::badge :label="$state($m)[0]" :tone="$state($m)[1]" /></td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><td>{{ $T::text('Total') }}</td>
                                <td><span class="dl-bb__bar"><i class="is-v2" style="width:{{ $pct($t('v2'), $t('screens')) }}%"></i><i class="is-v1" style="width:{{ $pct($t('v1'), $t('screens')) }}%"></i></span></td>
                                <td>{{ $n($t('v2')) }} / {{ $n($t('screens')) }}</td><td>{{ $n($t('forms')) }}</td><td>{{ $n($t('grids')) }}</td><td>{{ $n($t('reports')) }}</td><td>{{ $n($t('locked')) }}</td>
                                <td class="is-left">{{ \Devlib\Locale::percent($pct($t('v2'), $t('screens')), 0) }}</td></tr></tfoot>
                        </table>
                        <p class="dl-bb__note"><i class="fa-solid fa-circle-info"></i> {{ $T::text('Built = the screen exists and reads / writes the database. It is finished only after the user locks it after QC.') }}</p>
                    </x-dl::card>

                    <div class="dl-bb__side">
                        <x-dl::card title="From built to approved">
                            <div class="dl-bb__funnel">
                                <x-dl::progress :label="\Devlib\Lang::raw($T::text('Built, waiting for user QC').' · '.$n($t('waiting_qc')))" :value="$pct($t('waiting_qc'), $t('screens'))" />
                                <x-dl::progress :label="\Devlib\Lang::raw($T::text('Locked by user after QC').' · '.$n($t('locked')))" :value="$pct($t('locked'), $t('screens'))" tone="success" />
                                <x-dl::progress :label="\Devlib\Lang::raw($T::text('Not built').' · '.$n($t('not_built')))" :value="$pct($t('not_built'), $t('screens'))" tone="warn" />
                            </div>
                        </x-dl::card>
                        @if ($library)
                            <x-dl::card title="DevLib library">
                                <div class="dl-bb__nums">
                                    <span><b>{{ $n($t('objects')) }}</b>{{ $T::text('Objects') }}</span>
                                    <span><b>{{ $n($library['approved'] ?? 0) }} / {{ $n($library['pages'] ?? 0) }}</b>{{ $T::text('Pages approved') }}</span>
                                    <span><b>{{ $n($library['demos'] ?? 0) }}</b>{{ $T::text('Live demos') }}</span>
                                    <span><b>{{ $n(count($library['packages'] ?? [])) }}</b>{{ $T::text('Side packages') }}</span>
                                </div>
                                <div class="dl-bb__chips">@foreach ($library['groups'] ?? [] as $g)<span>{{ $g }}</span>@endforeach</div>
                                @if (! empty($library['packages']))<p class="dl-bb__note">{{ $T::text('Side packages') }}: {{ implode(' · ', $library['packages']) }}</p>@endif
                            </x-dl::card>
                        @endif
                        <x-dl::card title="Foundation">
                            <div class="dl-bb__nums">
                                <span><b>{{ $n($t('models')) }}</b>{{ $T::text('Models') }}</span>
                                <span><b>{{ $n($t('migrations')) }}</b>{{ $T::text('Migrations') }}</span>
                                <span><b>{{ $n($t('tests')) }}</b>{{ $T::text('Test files') }}</span>
                                <span><b>{{ $n($t('dictionary')) }}</b>{{ $T::text('Dictionary texts') }} · {{ $n($t('languages')) }} {{ $T::text('languages') }}</span>
                            </div>
                        </x-dl::card>
                    </div>

                    <x-dl::card :title="\Devlib\Lang::raw($T::text('Engines').' · '.$n($t('engines')))" class="dl-bb__wide">
                        <x-slot:actions>@foreach ($modes() as $mode => $count)<x-dl::badge :label="$count.' '.(\Devlib\Components\EngineCatalog::MODES[$mode][0] ?? $mode)" :tone="\Devlib\Components\EngineCatalog::MODES[$mode][1] ?? 'neutral'" />@endforeach</x-slot:actions>
                        <div class="dl-bb__engines">
                            @foreach ($build['engines'] ?? [] as $g)
                                <div class="dl-bb__eg"><b>{{ $g['group'] }} <em>{{ count($g['items']) }}</em></b>
                                    <span>@foreach ($g['items'] as $e)<span title="{{ $e['purpose'] }}">{{ $e['name'] }}</span>@endforeach</span></div>
                            @endforeach
                        </div>
                        @if ($enginesUrl)<a class="dl-bb__link" href="{{ $enginesUrl }}">{{ $T::text('Open Engine Catalog') }} <i class="fa-solid fa-arrow-right"></i></a>@endif
                    </x-dl::card>

                    <div class="dl-bb__side">
                        <x-dl::card title="Features built">
                            <div class="dl-bb__chips">@foreach ($build['features'] ?? [] as $f)<span><i class="fa-solid {{ \Devlib\Icon::fa($f[0]) }}"></i> {{ $f[1] }}</span>@endforeach</div>
                        </x-dl::card>
                        <x-dl::card title="Not yet">
                            <ul class="dl-bb__todo">
                                @foreach ($pending() as $p)<li><i class="fa-regular fa-circle"></i> {{ $p }}</li>@endforeach
                                @if ($t('waiting_qc'))<li class="is-qc"><i class="fa-regular fa-circle"></i> {{ $T::text('User QC') }}: {{ $n($t('waiting_qc')) }} {{ $T::text('screens wait for review') }}</li>@endif
                            </ul>
                        </x-dl::card>
                    </div>
                </div>
            </div>
            BLADE;
    }
}
