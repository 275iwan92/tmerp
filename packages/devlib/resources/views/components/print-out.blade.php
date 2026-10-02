@php($cfg = $config())
<div {{ $attributes->class('dl-print') }} {!! $dl() !!} data-dl-print data-config="{{ json_encode($cfg) }}" data-paper="{{ $paper }}" data-prices="{{ $prices ? 1 : 0 }}" data-ppn="{{ $ppn ? 1 : 0 }}" data-group="{{ $groupBy }}">
    <div class="dl-print__bar">
        <b>{{ \Devlib\Lang::t('pr_preview') }}</b><span class="dl-print__doc">— {{ $title }}{{ $number ? ' · '.$number : '' }}</span>
        <span class="dl-print__spacer"></span>
        <span class="dl-print__lbl">{{ \Devlib\Lang::t('pr_paper') }}</span>
        <div class="dl-segment" data-p-paper><button type="button" class="dl-segment__opt" data-value="a4">{{ \Devlib\Lang::t('pr_a4') }}</button><button type="button" class="dl-segment__opt" data-value="a4l">{{ \Devlib\Lang::t('pr_a4l') }}</button><button type="button" class="dl-segment__opt" data-value="a5">{{ \Devlib\Lang::t('pr_a5') }}</button></div>
        @if ($groups)
            <span class="dl-print__lbl">{{ \Devlib\Lang::t('pr_group') }}</span>
            <div class="dl-segment" data-p-group><button type="button" class="dl-segment__opt" data-value="">{{ \Devlib\Lang::t('pr_none') }}</button>@foreach ($groups as $k => $l)<button type="button" class="dl-segment__opt" data-value="{{ $k }}">{{ $l }}</button>@endforeach</div>
        @endif
        @if (collect($columns)->contains(fn ($c) => $c['price'] ?? false))
            <span class="dl-print__lbl">{{ \Devlib\Lang::t('pr_price') }}</span>
            <div class="dl-segment" data-p-prices><button type="button" class="dl-segment__opt" data-value="1">{{ \Devlib\Lang::t('pr_with_price') }}</button><button type="button" class="dl-segment__opt" data-value="0">{{ \Devlib\Lang::t('pr_without_price') }}</button></div>
        @endif
        @if ($companyPpn)
            <div class="dl-segment" data-p-ppn><button type="button" class="dl-segment__opt" data-value="0">Non PPN</button><button type="button" class="dl-segment__opt" data-value="1">PPN</button></div>
        @endif
        <x-dl::button variant="secondary" size="sm" icon="ink-saver" :label="\Devlib\Lang::t('pr_ink')" data-p-bw />
        <x-dl::button preset="print" size="sm" data-p-print />
    </div>
    <div class="dl-print__pages" data-p-pages></div>

    {{-- Sumber tiap bagian; JS menyusunnya ke lembar-lembar sesuai kertas. --}}
    <template data-p-head>
        <header class="dl-print__kop">
            @foreach (['0' => $company, '1' => $companyPpn ?: $company] as $isPpn => $c)
                <div class="dl-print__co" data-p-kop="{{ $isPpn }}">
                    @if (empty($c['logo']))<span class="dl-print__logo">{{ $c['initials'] ?? mb_substr($c['name'] ?? 'TM', 0, 2) }}</span>@endif
                    <div>@if (! empty($c['logo']))<img class="dl-print__brand" src="{{ $c['logo'] }}" alt="{{ $c['name'] ?? '' }}"><img class="dl-print__brand is-bw" src="{{ $c['logoBw'] ?? $c['logo'] }}" alt="{{ $c['name'] ?? '' }}">@else<b>{{ $c['name'] ?? '' }}</b>@if (! empty($c['tagline']))<small>{{ $c['tagline'] }}</small>@endif @endif
                        @if (! empty($c['address']))<span>{{ $c['address'] }}</span>@endif @if (! empty($c['contact']))<span>{{ $c['contact'] }}</span>@endif
                        @if ($isPpn === '1' && ! empty($c['npwp']))<span class="dl-print__npwp">NPWP {{ $c['npwp'] }}</span>@endif</div>
                </div>
            @endforeach
            <div class="dl-print__titlebox"><b class="dl-print__title">{{ $title }}</b>@if ($subtitle)<small>{{ $subtitle }}</small>@endif
                @if ($number)<span class="dl-print__docno">{{ $number }}</span>@endif
                <span class="dl-print__meta">@if ($date){{ \Devlib\Locale::date($date) }} · @endif {{ \Devlib\Lang::t('pr_page') }} <span data-p-pg></span></span>
                @if ($status)<span class="dl-print__status">{{ $status }}</span>@endif
            </div>
        </header>
    </template>
    <template data-p-criteria>
        @if ($criteria)
            <div class="dl-print__criteria">@foreach ($criteria as $k => $v)<div><small>{{ $k }}</small><b>{{ $v }}</b></div>@endforeach</div>
        @endif
    </template>
    <template data-p-parties>
        @if ($parties)
            <div class="dl-print__parties">@foreach ($parties as $k => $v)<div><span>{{ $k }}</span><b>@foreach ((array) $v as $i => $line){!! $i ? '<br><small>'.e($line).'</small>' : e($line) !!}@endforeach</b></div>@endforeach</div>
        @endif
    </template>
    <template data-p-notes>@isset($notes)<div class="dl-print__notes">{{ $notes }}</div>@endisset</template>
    <template data-p-sign>
        @if ($signatures)
            <div class="dl-print__sign" style="--n:{{ count($signatures) }}">@foreach ($signatures as $s)<div><span>{{ $s[0] }}</span><i></i><b>( {{ $s[2] ?? '..............................' }} )</b><small>{{ $s[1] ?? '' }}</small></div>@endforeach</div>
        @endif
    </template>
    <template data-p-foot><footer class="dl-print__foot"><span>{{ \Devlib\Lang::t('pr_printed_from') }}{{ $printedBy ? ' · '.$printedBy : '' }}</span><span>{{ \Devlib\Locale::dateTime(now()) }} · {{ \Devlib\Lang::t('pr_sheet') }} <span data-p-pg></span></span></footer></template>
</div>
