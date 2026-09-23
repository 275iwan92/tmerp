@php($cfg = $config())
<div {{ $attributes->class('dl-print') }} {!! $dl() !!} data-dl-print data-config="{{ json_encode($cfg) }}" data-paper="{{ $paper }}" data-prices="{{ $prices ? 1 : 0 }}" data-ppn="{{ $ppn ? 1 : 0 }}">
    <div class="dl-print__bar">
        <b>Pratinjau Cetak</b><span class="dl-print__doc">— {{ $title }}{{ $number ? ' · '.$number : '' }}</span>
        <span class="dl-print__spacer"></span>
        <span class="dl-print__lbl">Ukuran</span>
        <div class="dl-segment" data-p-paper><button type="button" class="dl-segment__opt" data-value="a4">A4 potret</button><button type="button" class="dl-segment__opt" data-value="a5">A5 lanskap</button></div>
        @if (collect($columns)->contains(fn ($c) => $c['price'] ?? false))
            <span class="dl-print__lbl">Harga</span>
            <div class="dl-segment" data-p-prices><button type="button" class="dl-segment__opt" data-value="1">Dengan Harga</button><button type="button" class="dl-segment__opt" data-value="0">Tanpa Harga</button></div>
        @endif
        @if ($companyPpn)
            <div class="dl-segment" data-p-ppn><button type="button" class="dl-segment__opt" data-value="0">Non PPN</button><button type="button" class="dl-segment__opt" data-value="1">PPN</button></div>
        @endif
        <x-dl::button variant="secondary" size="sm" icon="ink-saver" label="Hemat Tinta" data-p-bw />
        <x-dl::button preset="print" size="sm" data-p-print />
    </div>
    <div class="dl-print__pages" data-p-pages></div>

    {{-- Sumber tiap bagian; JS menyusunnya ke lembar-lembar sesuai kertas. --}}
    <template data-p-head>
        <header class="dl-print__kop">
            @foreach (['0' => $company, '1' => $companyPpn ?: $company] as $isPpn => $c)
                <div class="dl-print__co" data-p-kop="{{ $isPpn }}">
                    <span class="dl-print__logo">{{ $c['initials'] ?? mb_substr($c['name'] ?? 'TM', 0, 2) }}</span>
                    <div><b>{{ $c['name'] ?? '' }}</b>@if (! empty($c['tagline']))<small>{{ $c['tagline'] }}</small>@endif
                        @if (! empty($c['address']))<span>{{ $c['address'] }}</span>@endif @if (! empty($c['contact']))<span>{{ $c['contact'] }}</span>@endif
                        @if ($isPpn === '1' && ! empty($c['npwp']))<span class="dl-print__npwp">NPWP {{ $c['npwp'] }}</span>@endif</div>
                </div>
            @endforeach
            <div class="dl-print__titlebox"><b class="dl-print__title">{{ $title }}</b>@if ($subtitle)<small>{{ $subtitle }}</small>@endif
                @if ($number)<span class="dl-print__docno">{{ $number }}</span>@endif
                <span class="dl-print__meta">@if ($date){{ \Devlib\Locale::date($date, 'long') }} · @endif Hal <span data-p-pg></span></span>
                @if ($status)<span class="dl-print__status">{{ $status }}</span>@endif
            </div>
        </header>
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
    <template data-p-foot><footer class="dl-print__foot"><span>Dicetak dari DevLib{{ $printedBy ? ' · '.$printedBy : '' }}</span><span>{{ \Devlib\Locale::dateTime(now()) }} · lembar <span data-p-pg></span></span></footer></template>
</div>
