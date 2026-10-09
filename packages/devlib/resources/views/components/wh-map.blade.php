@php
    use Devlib\Components\WhMap;
    use Devlib\Lang;
    use Devlib\Locale;

    $S = WhMap::SCALE;
    $counts = $counts();
    $cfg = $config();
    $cfg['status'] = array_map(fn ($s) => Lang::text($s), WhMap::STATUS);
    $cfg['text'] = array_map(fn ($s) => Lang::text($s), ['bin' => 'Bin', 'capacity' => 'Capacity', 'item' => 'Item', 'qty' => 'Qty', 'idle' => 'Idle', 'dead' => 'Dead stock',
        'level' => 'Level', 'nostock' => 'No stock in this bin.', 'saved' => 'Layout saved.', 'failed' => 'Could not save the layout.', 'nomatch' => 'No bin matches the search.',
        'matches' => 'bins match', 'label' => 'Label', 'stack' => 'Stack — top to bottom', 'up' => 'Move up', 'down' => 'Move down', 'area' => 'Area', 'bins' => 'bins in use']);
    $pad = 1.5;   // meter kosong di sekeliling gedung (tempat tulisan ukuran)
    $vb = (-$pad * $S).' '.(-$pad * $S).' '.(($width + 2 * $pad) * $S).' '.(($height + 2 * $pad) * $S);
    $hatch = 'dl-whmap-hatch-'.$id;
    $grid = 'dl-whmap-grid-'.$id;
@endphp
<div {{ $attributes->class(['dl-whmap']) }} {!! $dl() !!} data-dl-whmap data-config="{{ json_encode($cfg) }}">
    <div class="dl-whmap__bar">
        @if ($title)<b class="dl-whmap__title">{{ $title }}</b>@endif
        @if (count($floors) > 1)
            <div class="dl-whmap__floors" role="tablist">
                @foreach ($floors as $f)
                    <button type="button" class="{{ (int) $f['no'] === (int) $floor ? 'is-active' : '' }}" data-wm-floor="{{ $f['no'] }}">{{ $f['label'] ?? Lang::text('Floor').' '.$f['no'] }}</button>
                @endforeach
            </div>
        @endif
        <label class="dl-whmap__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ $search }}" data-wm-search autocomplete="off"></label>
        <span class="dl-whmap__found" data-wm-found></span>
        <span class="dl-whmap__spacer"></span>
        {{ $tools ?? '' }}
        @if ($saveUrl)<button type="button" class="dl-btn dl-btn--secondary dl-btn--sm" data-wm-edit><i class="fa-solid fa-up-down-left-right"></i> {{ Lang::text('Edit Layout') }}</button>@endif
    </div>
    <div class="dl-whmap__legend">
        @foreach (WhMap::STATUS as $key => $label)
            <button type="button" data-wm-only="{{ $key }}"><i class="dl-whmap__swatch dl-whmap__swatch--{{ strtolower($key) }}"></i>{{ Lang::text($label) }} <b>{{ $counts[$key] }}</b></button>
        @endforeach
        <button type="button" data-wm-only="DEAD"><i class="dl-whmap__swatch dl-whmap__swatch--dead"></i>{{ Lang::text('Dead stock') }} <b>{{ $counts['DEAD'] }}</b></button>
        @if ($counts['EXPIRY'])<button type="button" data-wm-only="EXPIRY"><i class="dl-whmap__swatch dl-whmap__swatch--expiry"></i>{{ Lang::text('Expired / expires soon') }} <b>{{ $counts['EXPIRY'] }}</b></button>@endif
        @if ($counts['REMNANT'])<button type="button" data-wm-only="REMNANT"><i class="dl-whmap__swatch dl-whmap__swatch--remnant"></i>{{ Lang::text('Remnant') }} <b>{{ $counts['REMNANT'] }}</b></button>@endif
        @if ($counts['BURIED'])<button type="button" data-wm-only="BURIED"><i class="dl-whmap__swatch dl-whmap__swatch--buried"></i>{{ Lang::text('Buried') }} <b>{{ $counts['BURIED'] }}</b></button>@endif
    </div>
    <div class="dl-whmap__body">
        <div class="dl-whmap__canvas">
            @foreach ($floors as $f)
                <svg class="dl-whmap__svg" viewBox="{{ $vb }}" data-wm-svg="{{ $f['no'] }}" @if ((int) $f['no'] !== (int) $floor) hidden @endif role="img" aria-label="{{ $title }} {{ $f['label'] ?? '' }}">
                    <defs>
                        <pattern id="{{ $hatch }}-{{ $f['no'] }}" width="3" height="3" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="3" height="3" class="dl-whmap__hatchbg"/><line x1="0" y1="0" x2="0" y2="3" class="dl-whmap__hatchline"/></pattern>
                        <pattern id="{{ $grid }}-{{ $f['no'] }}" width="{{ $S }}" height="{{ $S }}" patternUnits="userSpaceOnUse"><path d="M {{ $S }} 0 L 0 0 0 {{ $S }}" class="dl-whmap__gridline"/></pattern>
                        <clipPath id="{{ $grid }}-clip-{{ $f['no'] }}"><polygon points="{{ $points() }}"/></clipPath>
                    </defs>
                    <polygon points="{{ $points() }}" class="dl-whmap__floor"/>
                    <rect x="0" y="0" width="{{ $width * $S }}" height="{{ $height * $S }}" fill="url(#{{ $grid }}-{{ $f['no'] }})" clip-path="url(#{{ $grid }}-clip-{{ $f['no'] }})"/>
                    <polygon points="{{ $points() }}" class="dl-whmap__wall"/>
                    {{-- skala: sisi atas = panjang, sisi kiri = lebar (meter) --}}
                    <text x="{{ $width * $S / 2 }}" y="{{ -0.6 * $S }}" class="dl-whmap__dim" text-anchor="middle">{{ Locale::decimal($width, 2) }} m</text>
                    <text x="{{ -0.6 * $S }}" y="{{ $height * $S / 2 }}" class="dl-whmap__dim" text-anchor="middle" transform="rotate(-90 {{ -0.6 * $S }} {{ $height * $S / 2 }})">{{ Locale::decimal($height, 2) }} m</text>
                    @foreach ($f['zones'] ?? [] as $z)
                        <g class="dl-whmap__zone" data-wm-zone="{{ $z['id'] }}" data-tone="{{ $z['tone'] ?? 'accent' }}" data-x="{{ $z['x'] }}" data-y="{{ $z['y'] }}" data-w="{{ $z['w'] }}" data-h="{{ $z['h'] }}" transform="translate({{ $z['x'] * $S }} {{ $z['y'] * $S }})">
                            <rect class="dl-whmap__zrect" width="{{ $z['w'] * $S }}" height="{{ $z['h'] * $S }}" rx="1.5"/>
                            <text class="dl-whmap__zname" x="2" y="5.2">{{ $z['code'] }} · {{ $z['name'] }}</text>
                            @if (! empty($z['note']))<text class="dl-whmap__znote" x="2" y="{{ $z['h'] * $S - 2 }}">{{ $z['note'] }}</text>@endif
                            @foreach ($z['cells'] ?? [] as $c)
                                @php
                                    $st = $cellStatus($c);
                                    $dead = $cellDead($c);
                                    $exp = $cellExpiry($c);
                                    $rem = $cellHas($c, 'remnant');
                                    $bur = $cellHas($c, 'buried');
                                    $units = $cellCount($c);
                                    $dots = array_keys(array_filter(['dead' => $dead, 'exp' => $exp, 'bur' => $bur, 'rem' => $rem]));
                                    $cw = $c['w'] * $S;
                                    $ch = $c['h'] * $S;
                                    $find = strtolower(implode(' ', array_map(fn ($b) => ($bins[$b]['code'] ?? '').' '.implode(' ', array_map(fn ($i) => ($i['code'] ?? '').' '.($i['name'] ?? '').' '.($i['lot'] ?? ''), $bins[$b]['items'] ?? [])), $c['bins'] ?? [])));
                                    $fs = max(1.6, min(3.2, $cw / max(strlen((string) $c['label']), 1) * 1.5, $ch * 0.42));
                                @endphp
                                <g class="dl-whmap__cell dl-whmap__cell--{{ strtolower($st) }} {{ $dead ? 'is-dead' : '' }}" data-wm-cell="{{ implode(',', $c['bins'] ?? []) }}" data-status="{{ $st }}" data-dead="{{ $dead ? 1 : 0 }}" data-expiry="{{ $exp ? 1 : 0 }}" data-remnant="{{ $rem ? 1 : 0 }}" data-buried="{{ $bur ? 1 : 0 }}" data-find="{{ $find }}" transform="translate({{ ($c['x'] - $z['x']) * $S }} {{ ($c['y'] - $z['y']) * $S }})" tabindex="0">
                                    <rect width="{{ $cw }}" height="{{ $ch }}" rx="1" @if ($st === 'BLOCKED' && ! $dead) style="fill:url(#{{ $hatch }}-{{ $f['no'] }})" @endif/>
                                    <text x="{{ $cw / 2 }}" y="{{ $ch / 2 + $fs * 0.36 }}" text-anchor="middle" style="font-size:{{ round($fs, 2) }}px">{{ $c['label'] }}</text>
                                    @if (count($c['bins'] ?? []) > 1)<text class="dl-whmap__lv" x="{{ $cw - 0.8 }}" y="2.6" text-anchor="end">×{{ count($c['bins']) }}</text>@endif
                                    @foreach ($dots as $n => $dot)<circle class="dl-whmap__{{ $dot }}dot" cx="{{ 2 + $n * 2.8 }}" cy="2" r="1.1"/>@endforeach
                                    @if ($units)<text class="dl-whmap__n" x="{{ $cw - 0.8 }}" y="{{ $ch - 0.9 }}" text-anchor="end">{{ $units }}</text>@endif
                                </g>
                            @endforeach
                            @if ($saveUrl)<rect class="dl-whmap__grip" data-wm-grip x="{{ $z['w'] * $S - 3 }}" y="{{ $z['h'] * $S - 3 }}" width="3" height="3"/>@endif
                        </g>
                    @endforeach
                    @if ($layer)
                        @php $route = array_values(array_filter(array_map(fn ($b) => $layerPoint($b, (int) $f['no']), array_merge($layer['route'] ?? [], isset($layer['end']) ? [$layer['end']] : [])))); @endphp
                        <g class="dl-whmap__layer" data-wm-layer>
                            @if (count($route) > 1)<polyline class="dl-whmap__lroute" points="{{ implode(' ', array_map(fn ($p) => round($p[0], 2).','.round($p[1], 2), $route)) }}"/>@endif
                            @foreach ($layer['marks'] ?? [] as $m)
                                @if ($p = $layerPoint($m['bin'], (int) $f['no']))
                                    <circle class="dl-whmap__lmark dl-whmap__lmark--{{ $m['tone'] ?? 'pick' }}" cx="{{ round($p[0], 2) }}" cy="{{ round($p[1], 2) }}" r="3.4"/><text class="dl-whmap__lnum" x="{{ round($p[0], 2) }}" y="{{ round($p[1] + 1.3, 2) }}" text-anchor="middle">{{ $m['text'] }}</text>
                                @endif
                            @endforeach
                            @if (isset($layer['end']) && ($p = $layerPoint($layer['end'], (int) $f['no'])))<text class="dl-whmap__lend" x="{{ round($p[0], 2) }}" y="{{ round($p[1] - 4, 2) }}" text-anchor="middle">&#10148;</text>@endif
                        </g>
                    @endif
                </svg>
            @endforeach
        </div>
        <aside class="dl-whmap__panel" data-wm-panel>
            @if ($layer && (($layer['rows'] ?? false) || ($layer['notes'] ?? false)))
                <b>{{ $layer['title'] ?? '' }}</b>
                @if ($layer['rows'] ?? false)<x-dl::table :columns="['no' => ['#', 'raw' => true], 'bin' => ['Bin', 'raw' => true], 'qty' => ['Qty', 'num' => true, 'raw' => true]]" :rows="$layer['rows']" />@endif
                @foreach ($layer['notes'] ?? [] as $note)<p class="dl-whmap__hint">{{ $note }}</p>@endforeach
            @else
                <p class="dl-whmap__hint">{{ $empty }}</p>
            @endif
        </aside>
    </div>
</div>
