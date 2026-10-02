@php
    $d = $board;
    $u = $d['user'] ?? [];
    $periods = $d['periods'] ?? [];
    $act = $d['activities'] ?? null;
    $id = 'dash-'.($d['profile'] ?? 'general');
@endphp
<div {{ $attributes->class(['dl-dash', 'dl-dash--'.($d['profile'] ?? 'general')]) }} {!! $dl() !!} data-dl-dash data-period="{{ $period() }}" data-key="{{ $id }}">

    {{-- 1. Salam + jam + cuaca --}}
    <section class="dl-dash__hero">
        <div class="dl-dash__hello">
            <div class="dl-dash__eyebrow">{{ $d['profile_label'] ?? 'Dashboard' }}</div>
            <x-dl::greeting :name="$u['first'] ?? ''" />
            <div class="dl-dash__pos">
                @foreach (array_filter([$u['position'] ?? null, $u['department'] ?? null, isset($u['superior']) ? \Devlib\Lang::text('Reports to').': '.$u['superior'] : null]) as $p)
                    <span>{{ $p }}</span>
                @endforeach
            </div>
            @if (! empty($d['brief']))
                <div class="dl-dash__brief">
                    @foreach ($d['brief'] as [$label, $value])
                        <span>{{ $label }} <b>{{ $value }}</b></span>
                    @endforeach
                </div>
            @endif
            <div class="dl-dash__docs">
                @if (! empty($d['jobdesc']))
                    <x-dl::button variant="secondary" icon="fa-briefcase" label="Job Description" data-dl-open="{{ $id }}-jd" />
                @endif
                @if (! empty($d['sops']))
                    <x-dl::button variant="ghost" icon="fa-book-open" label="SOP" data-dl-open="{{ $id }}-sop"><span class="dl-dash__count">{{ count($d['sops']) }}</span></x-dl::button>
                @endif
            </div>
        </div>
        <div class="dl-dash__side">
            <x-dl::clock />
            @if (! empty($d['attendance']))
                @php($a = $d['attendance'])
                <div class="dl-dash__att">
                    <x-dl::badge :tone="$a['tone'] ?? 'success'" icon="fa-fingerprint" :label="'Check-in '.$a['in']" />
                    <small>{{ $a['status'] ?? '' }} · Shift {{ $a['shift'] ?? '-' }} ({{ $a['start'] ?? '' }}–{{ $a['end'] ?? '' }})</small>
                </div>
            @endif
            @if (! empty($d['weather']))
                <x-dl::weather :city="$d['weather']['city']" :lat="$d['weather']['lat']" :lon="$d['weather']['lon']" />
            @endif
        </div>
    </section>

    {{-- Papan widget ($board['widgets']): menggantikan blok tetap di bawah; susunannya milik user (x-dl::widget-board). --}}
    @if (array_key_exists('widgets', $d))
        <x-dl::widget-board :widgets="$d['widgets']" :catalog="$d['catalog'] ?? []" :action-url="$d['board_url'] ?? null" :editable="$d['board_editable'] ?? true"
            :title="$d['kpi_title'] ?? null" :id="$id.'-board'" :source="$d['board_source'] ?? null" :periods="$d['board_periods'] ?? []" :period="$d['board_period'] ?? null"
            :dashboards="$d['board_dashboards'] ?? []" :dashboard="$d['board_dashboard'] ?? null" />
    @else
    {{-- 2. Pilihan periode --}}
    <div class="dl-dash__bar">
        <h2>{{ $d['kpi_title'] ?? 'KPI Saya' }}</h2>
        <div class="dl-dash__seg" role="tablist">
            @foreach ($periods as $key => $label)
                <button type="button" class="{{ $key === $period() ? 'is-active' : '' }}" data-dash-period="{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @foreach ($periods as $key => $label)
        <div class="dl-dash__period {{ $key === $period() ? 'is-active' : '' }}" data-dash-panel="{{ $key }}">
            {{-- 3. KPI --}}
            <div class="dl-kpis dl-dash__kpis">
                @foreach ($d['kpis'][$key] ?? [] as $k)
                    <x-dl::kpi :label="$k['label']" :value="$k['value']" :previous="$k['previous'] ?? null" :target="$k['target'] ?? null"
                        :format="$k['format'] ?? 'number'" :good-when="$k['good_when'] ?? 'up'" :icon="$k['icon'] ?? 'fa-chart-line'"
                        :tone="$k['tone'] ?? 'accent'" :spark="$k['spark'] ?? []" :hint="$k['hint'] ?? null" />
                @endforeach
            </div>

            <div class="dl-dash__row">
                {{-- 5. Target vs pencapaian --}}
                @if ($t = $d['trend'][$key] ?? null)
                    <div class="dl-dash__trend">
                        <x-dl::chart type="combo" :title="$t['title']" :subtitle="$t['caption'] ?? null" :format="$t['format'] ?? 'number'" height="400px"
                            :labels="$t['labels']" :source="$t['source'] ?? null" :series="[
                                ['name' => 'Pencapaian', 'data' => $t['actual'], 'type' => 'bar', 'tone' => 'accent'],
                                ['name' => 'Target', 'data' => $t['target'], 'type' => 'line', 'tone' => 'danger'],
                            ]" />
                        @if (! empty($t['summary']))
                            <div class="dl-dash__sum">
                                @foreach ($t['summary'] as $s)
                                    <div><small>{{ $s[0] }}</small><b class="{{ isset($s[2]) ? 'is-'.$s[2] : '' }}">{{ $s[1] }}</b></div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- 4. Aktivitas --}}
                @if ($act && ($a = $act['data'][$key] ?? null))
                    @php($tot = \Devlib\Components\Dashboard::totals($a['matrix'], $act['types']))
                    <x-dl::card :title="$act['title']" :description="$act['caption'] ?? null" icon="fa-list-check" class="dl-dash__act">
                        <div class="dl-dash__acts">
                            @foreach ($act['types'] as $ty)
                                @php($target = $a['target'][$ty['key']] ?? 0)
                                <div class="dl-dash__actbox dl-dash--{{ $ty['tone'] }}">
                                    <span><i class="fa-solid {{ \Devlib\Icon::fa($ty['icon']) }}"></i> {{ $ty['label'] }}</span>
                                    <b>{{ \Devlib\Locale::number($tot[$ty['key']]) }} <small>/ {{ \Devlib\Locale::number($target) }}</small></b>
                                    <x-dl::progress :value="$target ? $tot[$ty['key']] / $target * 100 : 0" :tone="$ty['tone']" />
                                </div>
                            @endforeach
                        </div>
                        <table class="dl-table dl-dash__mx">
                            <thead><tr><th>{{ $act['segment_label'] ?? 'Sasaran' }}</th>@foreach ($act['types'] as $ty)<th class="num">{{ $ty['label'] }}</th>@endforeach<th class="num">Total</th></tr></thead>
                            <tbody>
                                @foreach ($act['segments'] as $sg)
                                    @php($row = $a['matrix'][$sg['key']] ?? [])
                                    <tr><td><i class="dl-dash__dot dl-dash--{{ $sg['tone'] }}"></i>{{ $sg['label'] }}</td>
                                        @foreach ($act['types'] as $ty)<td class="num">{{ \Devlib\Locale::number($row[$ty['key']] ?? 0) }}</td>@endforeach
                                        <td class="num">{{ \Devlib\Locale::number(array_sum($row)) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><td>Total</td>@foreach ($act['types'] as $ty)<td class="num">{{ \Devlib\Locale::number($tot[$ty['key']]) }}</td>@endforeach<td class="num">{{ \Devlib\Locale::number(array_sum($tot)) }}</td></tr></tfoot>
                        </table>
                        @if (! empty($a['week']))
                            <x-dl::chart type="bar" :title="$a['week']['title']" format="number" height="150px" :labels="$a['week']['labels']" :legend="false"
                                :series="collect($act['types'])->map(fn ($ty) => ['name' => $ty['label'], 'data' => $a['week'][$ty['key']], 'tone' => $ty['tone']])->all()" />
                        @endif
                    </x-dl::card>
                @endif

                {{-- Target per bidang (profil manajemen: menggantikan aktivitas pribadi) --}}
                @if ($tg = $d['targets'][$key] ?? null)
                    <x-dl::card :title="$tg['title']" :description="$tg['caption'] ?? null" icon="fa-bullseye" class="dl-dash__targets">
                        @foreach ($tg['rows'] as $r)
                            @php($pct = $r['target'] ? $r['actual'] / $r['target'] * 100 : 0)
                            @php($pct = ($r['good_when'] ?? 'up') === 'down' ? ($r['actual'] ? $r['target'] / $r['actual'] * 100 : 100) : $pct)
                            @php($tone = $pct >= 100 ? 'success' : ($pct >= 85 ? 'warn' : 'danger'))
                            <div class="dl-dash__tg">
                                <span class="dl-dash__tgicon dl-dash--{{ $r['tone'] ?? 'accent' }}"><i class="fa-solid {{ \Devlib\Icon::fa($r['icon'] ?? 'fa-bullseye') }}"></i></span>
                                <div class="dl-dash__tgbody">
                                    <div class="dl-dash__tghead"><b>{{ $r['label'] }}</b><x-dl::badge :tone="$tone" :label="\Devlib\Locale::percent($pct, 0)" /></div>
                                    <small>{{ \Devlib\Components\Dashboard::fmt($r['actual'], $r['format'] ?? 'number') }} dari target {{ \Devlib\Components\Dashboard::fmt($r['target'], $r['format'] ?? 'number') }}@if (! empty($r['sub'])) · {{ $r['sub'] }}@endif</small>
                                    <x-dl::progress :value="min(100, $pct)" :tone="$tone" />
                                </div>
                            </div>
                        @endforeach
                    </x-dl::card>
                @endif
            </div>

            {{-- Pipeline penjualan + pencapaian per orang/unit --}}
            @if (($pl = $d['pipeline'][$key] ?? null) || ($rk = $d['ranking'][$key] ?? null))
                @php($rk = $d['ranking'][$key] ?? null)
                <div class="dl-dash__row dl-dash__row--half">
                    @if ($pl)
                        <x-dl::chart type="funnel" :title="$pl['title']" :subtitle="$pl['caption'] ?? null" :format="$pl['format'] ?? 'number'" height="260px"
                            :labels="$pl['labels']" :series="[['name' => $pl['series'] ?? 'Nilai', 'data' => $pl['values']]]" :source="$pl['source'] ?? null" />
                    @endif
                    @if ($rk)
                        <x-dl::card :title="$rk['title']" :description="$rk['caption'] ?? null" icon="fa-ranking-star">
                            <div class="dl-dash__scroll"><table class="dl-table dl-dash__rk">
                                <thead><tr><th>{{ $rk['name_label'] ?? 'Nama' }}</th><th class="num">Target</th><th class="num">Pencapaian</th><th>%</th></tr></thead>
                                <tbody>
                                    @foreach ($rk['rows'] as $r)
                                        @php($pct = $r['target'] ? $r['actual'] / $r['target'] * 100 : 0)
                                        <tr>
                                            <td><div class="dl-dash__who"><x-dl::avatar :name="$r['name']" size="sm" /><div><b>{{ $r['name'] }}</b><small>{{ $r['sub'] ?? '' }}</small></div></div></td>
                                            <td class="num">{{ \Devlib\Components\Dashboard::fmt($r['target'], $rk['format'] ?? 'money') }}</td>
                                            <td class="num">{{ \Devlib\Components\Dashboard::fmt($r['actual'], $rk['format'] ?? 'money') }}</td>
                                            <td class="dl-dash__rkpct"><x-dl::progress :value="min(100, $pct)" :tone="$pct >= 100 ? 'success' : ($pct >= 85 ? 'warn' : 'danger')" /><small>{{ \Devlib\Locale::percent($pct, 0) }}</small></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        </x-dl::card>
                    @endif
                </div>
            @endif
        </div>
    @endforeach

    {{-- Fokus + agenda hari ini --}}
    @if (! empty($d['focus']) || ! empty($d['agenda']))
        <div class="dl-dash__row dl-dash__row--wide">
            @if ($f = $d['focus'] ?? null)
                <x-dl::card :title="$f['title']" :description="$f['caption'] ?? null" icon="fa-bullseye" class="dl-dash__focus">
                    <div class="dl-dash__scroll"><table class="dl-table dl-dash__fu">
                        <thead><tr>@foreach ($f['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($f['rows'] as $r)
                                <tr>
                                    <td><div class="dl-dash__who"><x-dl::avatar :name="$r['name']" size="sm" /><div><b>{{ $r['name'] }}</b><small>{{ $r['sub'] }}</small></div></div></td>
                                    <td><x-dl::badge :tone="$r['tone']" :label="$r['segment']" /></td>
                                    <td><div class="dl-dash__stage">@for ($i = 1; $i <= 4; $i++)<i class="{{ $i <= ($r['step'] ?? 0) ? 'is-on' : '' }}"></i>@endfor</div><small>{{ $r['stage'] }}</small></td>
                                    <td class="dl-dash__cnt">@foreach ($r['counts'] as $icon => $n)<span><i class="fa-solid {{ \Devlib\Icon::fa($icon) }}"></i>{{ $n }}</span>@endforeach</td>
                                    <td><x-dl::badge :tone="$r['last_tone']" :label="$r['last']" /></td>
                                    <td><x-dl::button size="sm" :variant="$r['primary'] ?? false ? 'primary' : 'secondary'" :label="$r['action']" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                </x-dl::card>
            @endif
            @if (! empty($d['agenda']))
                <x-dl::card title="Today's Agenda" :description="\Devlib\Lang::raw(count($d['agenda']).' kegiatan')" icon="fa-calendar-day" class="dl-dash__agenda">
                    <ol class="dl-dash__ag">
                        @foreach ($d['agenda'] as $g)
                            <li class="{{ ($g['done'] ?? false) ? 'is-done' : '' }} dl-dash--{{ $g['tone'] ?? 'accent' }}">
                                <time>{{ $g['time'] }}<small>{{ $g['duration'] ?? '' }}</small></time>
                                <div><b>{{ $g['title'] }}</b><small>{{ $g['note'] ?? '' }}</small></div>
                            </li>
                        @endforeach
                    </ol>
                </x-dl::card>
            @endif
        </div>
    @endif

    {{-- 6. Kata motivasi harian --}}
    @if (! empty($d['quotes']))
        <div class="dl-dash__quote">
            <x-dl::quote :quotes="$d['quotes']" order="day" />
            <div class="dl-dash__doy"><b data-dash-doy>—</b><small>dari {{ count($d['quotes']) >= 366 ? 365 : count($d['quotes']) }}</small></div>
        </div>
    @endif

    @endif

    {{-- Job Description & SOP: HTML dari master jabatan (diisi HR Officer), tampil read-only --}}
    @if (! empty($d['jobdesc']))
        <x-dl::modal :id="$id.'-jd'" :title="'Job Description · '.\Devlib\Lang::raw($d['jobdesc']['title'] ?? '')" icon="fa-briefcase" size="lg">
            <div class="dl-dash__doc">{!! $d['jobdesc']['html'] !!}</div>
            <x-slot:footer><small class="dl-dash__upd">Diperbarui {{ $d['jobdesc']['updated'] ?? '-' }}</small><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
        </x-dl::modal>
    @endif
    @if (! empty($d['sops']))
        <x-dl::modal :id="$id.'-sop'" :title="'SOP · '.\Devlib\Lang::raw($u['position'] ?? '')" icon="fa-book-open" size="lg">
            @foreach ($d['sops'] as $i => $s)
                <x-dl::accordion :title="\Devlib\Lang::raw($s['code'].' · '.$s['title'])" :badge="'Rev. '.$s['revision']" :open="$i === 0" icon="fa-file-lines">
                    <div class="dl-dash__doc"><p class="dl-dash__upd">Berlaku sejak {{ $s['effective'] }}</p>{!! $s['html'] !!}</div>
                </x-dl::accordion>
            @endforeach
            <x-slot:footer><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
        </x-dl::modal>
    @endif
</div>
