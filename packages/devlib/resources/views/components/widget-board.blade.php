@php
    use Devlib\Components\Dashboard;
    use Devlib\Components\WidgetBoard;
    use Devlib\Lang;
    use Devlib\Locale;

    $canEdit = $editable && $actionUrl;
    $free = collect($catalog)->where('installed', false)->groupBy(fn ($c) => $c['group'] ?? 'General');
    $last = count($widgets) - 1;
@endphp
<div {{ $attributes->class(['dl-wb', 'is-edit' => $editing && $canEdit]) }} {!! $dl() !!} data-dl-wboard
    data-config="{{ json_encode(['action' => $actionUrl, 'csrf' => csrf_token()]) }}">
    <div class="dl-wb__bar">
        <h2>{{ $title ?? Lang::text('My Board') }}</h2>
        @if ($canEdit)
            <div class="dl-wb__tools">
                <span class="dl-wb__only-edit dl-wb__hint">{{ Lang::text('Move, resize or remove a widget, or add one from the catalog.') }}</span>
                <x-dl::button class="dl-wb__only-edit" variant="secondary" size="sm" icon="fa-plus" label="Add Widget" :data-dl-open="$id.'-add'" />
                <x-dl::button class="dl-wb__only-edit" variant="ghost" size="sm" icon="fa-rotate-left" label="Reset to Template" data-wb-act="reset"
                    :data-dl-confirm="Lang::text('Put the board back to the template of your position? Your own arrangement is removed.')" />
                <x-dl::button class="dl-wb__only-edit" size="sm" icon="fa-check" label="Done" data-wb-edit />
                <x-dl::button class="dl-wb__not-edit" variant="secondary" size="sm" icon="fa-sliders" label="Customize" data-wb-edit />
            </div>
        @endif
    </div>

    @if (! $widgets)
        <x-dl::empty-state title="No widgets on this board" message="Click Customize, then Add Widget to choose from the catalog." icon="fa-table-cells-large" />
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
                        :tone="$w['tone'] ?? 'accent'" :spark="$data['spark'] ?? []" :hint="$data['hint'] ?? ($w['caption'] ?? null)" />
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
    </div>

    @if ($canEdit)
        <x-dl::modal :id="$id.'-add'" title="Add Widget" icon="fa-plus" size="lg">
            @forelse ($free as $group => $items)
                <h4 class="dl-wb__group">{{ Lang::text($group) }}</h4>
                <div class="dl-wb__cat">
                    @foreach ($items as $c)
                        <div class="dl-wb__catitem">
                            <span class="dl-wb__caticon"><i class="{{ str_contains($c['icon'] ?? '', ' ') ? $c['icon'] : 'fa-solid '.\Devlib\Icon::fa($c['icon'] ?? 'fa-chart-simple') }}"></i></span>
                            <div><b>{{ Lang::text($c['title']) }}</b><small>{{ Lang::text($c['description'] ?? '') }}</small></div>
                            <x-dl::button size="sm" icon="fa-plus" label="Add" data-wb-add="{{ $c['key'] }}" />
                        </div>
                    @endforeach
                </div>
            @empty
                <x-dl::empty-state title="Every widget you may use is already on the board" icon="fa-circle-check" />
            @endforelse
            <x-slot:footer><x-dl::button preset="close" data-dl-close-modal /></x-slot:footer>
        </x-dl::modal>
    @endif
</div>
