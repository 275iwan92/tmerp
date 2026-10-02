@php
    use Devlib\Components\Dashboard;
    use Devlib\Components\WidgetBoard;
    use Devlib\Lang;
    use Devlib\Locale;

    $canEdit = $editable && $actionUrl;
    $groups = collect($catalog)->groupBy(fn ($c) => $c['group'] ?? 'General');
    $last = count($widgets) - 1;
@endphp
<div {{ $attributes->class(['dl-wb', 'is-edit' => $editing && $canEdit]) }} {!! $dl() !!} data-dl-wboard
    data-config="{{ json_encode(['action' => $actionUrl, 'csrf' => csrf_token()]) }}">
    {{-- Dashboard: Overall (disusun pemiliknya) + dashboard per bidang (template). Klik = ganti dashboard, pilihan tersimpan. --}}
    @if (count($dashboards) > 1 && $actionUrl)
        <div class="dl-wb__tabs" role="tablist">
            @foreach ($dashboards as $d)
                <button type="button" class="dl-wb__tab {{ $d['code'] === $dashboard ? 'is-active' : '' }}" data-wb-dash="{{ $d['code'] }}"><i class="{{ str_contains($d['icon'] ?? '', ' ') ? $d['icon'] : 'fa-solid '.\Devlib\Icon::fa($d['icon'] ?? 'fa-gauge-high') }}"></i> {{ Lang::text($d['title']) }}</button>
            @endforeach
        </div>
    @endif
    <div class="dl-wb__bar">
        <h2>{{ $title ?? Lang::text('My Board') }}</h2>
        @if ($periods && $actionUrl)
            <div class="dl-dash__seg dl-wb__seg" role="tablist">
                @foreach ($periods as $key => $label)
                    <button type="button" class="{{ $key === $period ? 'is-active' : '' }}" data-wb-period="{{ $key }}">{{ Lang::text($label) }}</button>
                @endforeach
            </div>
        @endif
        @if ($canEdit)
            <div class="dl-wb__tools">
                <span class="dl-wb__only-edit dl-wb__hint">{{ Lang::text('Drag a widget to move it; use the buttons to resize or remove it.') }}</span>
                <x-dl::button variant="secondary" size="sm" icon="fa-list-check" label="Choose Widgets" :data-dl-open="$id.'-add'" />
                <x-dl::button class="dl-wb__only-edit" variant="ghost" size="sm" icon="fa-rotate-left" label="Reset to Template" data-wb-act="reset"
                    :data-dl-confirm="Lang::text('Put the board back to the template of your position? Your own arrangement is removed.')" />
                <x-dl::button class="dl-wb__only-edit" size="sm" icon="fa-check" label="Done" data-wb-edit />
                <x-dl::button class="dl-wb__not-edit" variant="secondary" size="sm" icon="fa-sliders" label="Customize" data-wb-edit />
            </div>
        @endif
    </div>

    @if (! $widgets)
        <x-dl::empty-state title="No widgets on this board" :message="$canEdit ? 'Click Choose Widgets and tick the widgets you want to see.' : 'This dashboard has no widget your account may see.'" icon="fa-table-cells-large" />
    @endif

    <div class="dl-wb__grid">
        @foreach ($widgets as $i => $w)
            @php
                $data = $w['data'] ?? [];
                $open = $w['open'] ?? null;
                $size = in_array((int) ($w['size'] ?? 4), WidgetBoard::SIZES, true) ? (int) $w['size'] : 4;
            @endphp
            <div class="dl-wb__item dl-wb__item--{{ $w['type'] }}" data-size="{{ $size }}" data-key="{{ $w['key'] }}" data-dl="widget {{ $w['key'] }}" data-dl-class="widget" data-dl-src="{{ $w['source'] ?? '—' }}">
                @if ($canEdit)
                    <div class="dl-wb__edit">
                        <b>{{ Lang::text($w['title']) }}</b>
                        <x-dl::icon-button icon="fa-arrow-left" title="Move earlier" data-wb-act="left" :disabled="$i === 0" />
                        <x-dl::icon-button icon="fa-arrow-right" title="Move later" data-wb-act="right" :disabled="$i === $last" />
                        <x-dl::icon-button icon="fa-compress" title="Narrower" data-wb-act="narrower" :disabled="$size === WidgetBoard::SIZES[0]" />
                        <x-dl::icon-button icon="fa-expand" title="Wider" data-wb-act="wider" :disabled="$size === 12" />
                        <x-dl::icon-button icon="fa-xmark" variant="danger" title="Remove from board" data-wb-act="remove" />
                    </div>
                @endif

                @if ($w['type'] === 'kpi')
                    <x-dl::kpi :label="$w['title']" :value="$data['value'] ?? 0" :previous="$data['previous'] ?? null" :target="$data['target'] ?? null"
                        :format="$data['format'] ?? 'number'" :good-when="$data['good_when'] ?? 'up'" :icon="$w['icon'] ?? 'fa-chart-line'"
                        :tone="$w['tone'] ?? 'accent'" :spark="$data['spark'] ?? []" :hint="$data['hint'] ?? ($w['caption'] ?? null)" :note="$data['note'] ?? null" />
                @elseif ($w['type'] === 'chart')
                    @if (array_sum(array_map(fn ($s) => array_sum(array_map('abs', $s['data'] ?? [])), $data['series'] ?? [])) > 0)
                        <x-dl::chart :type="$data['chart'] ?? 'bar'" :title="$w['title']" :subtitle="$w['caption'] ?? null" :format="$data['format'] ?? 'number'"
                            :height="$data['height'] ?? '300px'" :labels="$data['labels'] ?? []" :series="$data['series'] ?? []" :legend="$data['legend'] ?? true" :source="$w['source'] ?? null" />
                    @else
                        <x-dl::card :title="$w['title']" :description="$w['caption'] ?? null" :icon="$w['icon'] ?? 'fa-chart-column'">
                            <x-dl::empty-state title="No data in this period" icon="fa-chart-column" />
                        </x-dl::card>
                    @endif
                @elseif ($w['type'] === 'quote')
                    <div class="dl-dash__quote">
                        <div class="dl-quote"><div class="qi">&#8220;</div><div class="q">{{ $data['text'] ?? '—' }}</div>@if (! empty($data['author']))<div class="a">{{ $data['author'] }}</div>@endif</div>
                        <div class="dl-dash__doy"><b>{{ $data['day'] ?? '—' }}</b><small>{{ Lang::text('of') }} {{ $data['days'] ?? 365 }}</small></div>
                    </div>
                @else
                    <x-dl::card :title="$w['title']" :description="$w['caption'] ?? null" :icon="$w['icon'] ?? 'fa-square-poll-vertical'" :class="$w['type'] === 'progress' ? 'dl-dash__targets' : ''">
                        @if ($w['type'] === 'progress')
                            @forelse ($data['rows'] ?? [] as $r)
                                @php($pct = WidgetBoard::pct($r))
                                @php($has = (float) ($r['target'] ?? 0) > 0)
                                <div class="dl-dash__tg">
                                    <span class="dl-dash__tgicon dl-dash--{{ $r['tone'] ?? 'accent' }}"><i class="fa-solid {{ \Devlib\Icon::fa($r['icon'] ?? 'fa-bullseye') }}"></i></span>
                                    <div class="dl-dash__tgbody">
                                        <div class="dl-dash__tghead"><b>{{ Lang::text($r['label']) }}</b>
                                            @if ($has)<x-dl::badge :tone="WidgetBoard::tone($pct)" :label="Locale::percent($pct, 0)" />@else<x-dl::badge tone="neutral" label="No target" />@endif</div>
                                        <small>{{ Dashboard::fmt($r['actual'] ?? 0, $r['format'] ?? 'number') }}@if ($has) {{ Lang::text('of target') }} {{ Dashboard::fmt($r['target'], $r['format'] ?? 'number') }}@endif @if (! empty($r['sub'])) · {{ Lang::text($r['sub']) }}@endif</small>
                                        <x-dl::progress :value="$has ? min(100, $pct) : 0" :tone="$has ? WidgetBoard::tone($pct) : 'accent'" />
                                    </div>
                                </div>
                            @empty
                                <x-dl::empty-state title="No data in this period" icon="fa-bullseye" />
                            @endforelse
                        @elseif ($w['type'] === 'ranking')
                            @if (! empty($data['rows']))
                                <div class="dl-dash__scroll"><table class="dl-table dl-dash__rk">
                                    <thead><tr><th>{{ Lang::text($data['name_label'] ?? 'Name') }}</th><th class="num">{{ Lang::text('Target') }}</th><th class="num">{{ Lang::text('Actual') }}</th><th>%</th></tr></thead>
                                    <tbody>
                                        @foreach ($data['rows'] as $r)
                                            @php($pct = WidgetBoard::pct($r))
                                            @php($has = (float) ($r['target'] ?? 0) > 0)
                                            <tr>
                                                <td><div class="dl-dash__who"><x-dl::avatar :name="$r['name']" size="sm" /><div><b>{{ $r['name'] }}</b><small>{{ $r['sub'] ?? '' }}</small></div></div></td>
                                                <td class="num">{{ $has ? Dashboard::fmt($r['target'], $data['format'] ?? 'money') : '—' }}</td>
                                                <td class="num">{{ Dashboard::fmt($r['actual'] ?? 0, $data['format'] ?? 'money') }}</td>
                                                <td class="dl-dash__rkpct">@if ($has)<x-dl::progress :value="min(100, $pct)" :tone="WidgetBoard::tone($pct)" /><small>{{ Locale::percent($pct, 0) }}</small>@else<small>{{ Lang::text('No target') }}</small>@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table></div>
                            @else
                                <x-dl::empty-state title="No data in this period" icon="fa-ranking-star" />
                            @endif
                        @else
                            @forelse ($data['rows'] ?? [] as $r)
                                <div class="dl-wb__row">
                                    <div><b>{{ $r['title'] }}</b>@if (! empty($r['sub']))<small>{{ $r['sub'] }}</small>@endif</div>
                                    @if (isset($r['value']))<x-dl::badge :tone="$r['tone'] ?? 'neutral'" :label="\Devlib\Lang::raw($r['value'])" />@endif
                                </div>
                            @empty
                                <x-dl::empty-state :title="$data['empty'] ?? 'Nothing to show'" icon="fa-circle-check" />
                            @endforelse
                        @endif
                        @if ($open)
                            <x-slot:actions>
                                <x-dl::button variant="ghost" size="sm" icon="fa-up-right-from-square" :label="$open['label'] ?? 'Open'" :data-dl-tab-url="$open['url']"
                                    :data-dl-tab-code="$open['code'] ?? $w['key']" :data-dl-tab-label="Lang::text($open['title'] ?? $w['title'])" :data-dl-tab-icon="$open['icon'] ?? 'fa-solid fa-chart-pie'" />
                            </x-slot:actions>
                        @endif
                    </x-dl::card>
                @endif
            </div>
        @endforeach
        @if ($canEdit)
            {{-- Ubin tambah (seperti layar ponsel): klik = buka pilihan widget; tahan lama sebuah widget = mode atur; seret = pindah. --}}
            <button type="button" class="dl-wb__addtile" data-dl-open="{{ $id }}-add"><i class="fa-solid fa-plus"></i><span>{{ Lang::text('Add widget') }}</span></button>
        @endif
    </div>

    @if ($canEdit)
        {{-- Pilih widget dengan tanda centang, per bidang (= dashboard asal widget). Centang = tampil di papan; langsung tersimpan. --}}
        <x-dl::modal :id="$id.'-add'" title="Choose Widgets" icon="fa-list-check" size="xl">
            <div class="dl-wb__pick">
                <input type="search" class="dl-wb__search" data-wb-search placeholder="{{ Lang::text('Search widget...') }}" autocomplete="off">
                <span class="dl-wb__hint">{{ Lang::text('Tap a widget to add it; tap again to take it off. Saved at once.') }}</span>
            </div>
            @forelse ($groups as $group => $items)
                <h4 class="dl-wb__group" data-wb-group>{{ Lang::text($group) }} <small>{{ $items->where('installed', true)->count() }} / {{ $items->count() }}</small></h4>
                <div class="dl-wb__cat">
                    @foreach ($items as $c)
                        <label class="dl-wb__catitem {{ $c['installed'] ? 'is-on' : '' }}" data-wb-find="{{ mb_strtolower(Lang::text($c['title']).' '.$c['title'].' '.Lang::text($c['description'] ?? '')) }}">
                            <input type="checkbox" data-wb-toggle="{{ $c['key'] }}" @checked($c['installed'])>
                            <span class="dl-wb__caticon"><i class="{{ str_contains($c['icon'] ?? '', ' ') ? $c['icon'] : 'fa-solid '.\Devlib\Icon::fa($c['icon'] ?? 'fa-chart-simple') }}"></i></span>
                            <span class="dl-wb__cattext"><b>{{ Lang::text($c['title']) }}</b><small>{{ Lang::text($c['description'] ?? '') }}</small></span>
                            @if (! empty($c['type']))<span class="dl-wb__cattype">{{ ['kpi' => 'KPI', 'chart' => 'Chart', 'progress' => 'Target', 'ranking' => 'Ranking', 'list' => 'List', 'quote' => 'Text'][$c['type']] ?? $c['type'] }}</span>@endif
                        </label>
                    @endforeach
                </div>
            @empty
                <x-dl::empty-state title="No widget is available for your account" icon="fa-circle-check" />
            @endforelse
            <x-slot:footer><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
        </x-dl::modal>
    @endif
</div>
