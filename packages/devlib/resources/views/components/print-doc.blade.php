@php
    $t = fn (?string $s) => \Devlib\PrintDoc\Document::t($s);
    $d = $doc->data;
    $co = $doc->company();
    $party = $d['party'] ?? [];
    $cols = $doc->columns();
    $paper = $doc->paper();
    $totals = $doc->totals();
    $sign = $doc->signatures();
    $field = fn (?array $def) => $def
        ? '<div class="dl-pdoc__f" data-pd-k="'.e($def['key']).'"><span>'.e($t($def['label'])).'</span><i>:</i><b>'.$doc->value($def).'</b></div>'
        : '<div class="dl-pdoc__f"></div>';
@endphp
<div {{ $attributes->class('dl-pdoc') }} {!! $dl() !!} data-dl-pdoc data-paper="{{ $paper }}">
    @foreach ($doc->sheets() as $sheet)
        <section class="dl-pdoc__sheet is-{{ $paper }}" data-pd-sheet data-pd-type="{{ $doc->type }}"
            style="--pd-info-rows:{{ $doc->infoRows }};--pd-rh:{{ $doc->rowHeight }}mm;--pd-tail:{{ $doc->tailHeight }}mm">
            <header class="dl-pdoc__kop">
                <div class="dl-pdoc__co">
                    @if (! $co['logo'])<span class="dl-pdoc__logo">{{ $co['initials'] }}</span>@endif
                    <div>@if ($co['logo'])<img class="dl-pdoc__brand" src="{{ $co['logo'] }}" alt="{{ $co['name'] }}">@else<b>{{ $co['name'] }}</b>@endif @foreach ($co['lines'] as $line)<small>{{ $line }}</small>@endforeach @if ($co['npwp'])<small>NPWP {{ $co['npwp'] }}</small>@endif</div>
                </div>
                <h1 @class(['is-compact' => $doc->titleCompact])>{{ $t($doc->title) }}@if ($doc->subtitle)<small>{{ $t($doc->subtitle) }}</small>@endif</h1>
                <div class="dl-pdoc__kopr"><small>{{ $t('Scan for') }}<br>{{ $t($doc->qrHint) }}</small><span class="dl-pdoc__qr" data-pd-qr></span></div>
            </header>

            <div class="dl-pdoc__info">
                <div>
                    <div class="dl-pdoc__f is-big" data-pd-k="number"><span>{{ $t($doc->numberLabel) }}</span><i>:</i><b>{{ $d['number'] ?? '' }}</b></div>
                    @for ($i = 0; $i < $doc->infoRows - 1; $i++){!! $field($doc->refs[$i] ?? null) !!}@endfor
                </div>
                <div>
                    <div class="dl-pdoc__f is-st" data-pd-k="date"><span>{{ $t('Date') }}</span><i>:</i><b>{{ \Devlib\Locale::d($d['date'] ?? null) ?? '—' }}</b><span class="dl-pdoc__stamp">{{ $d['status'] ?? '' }}</span></div>
                    <div class="dl-pdoc__f" data-pd-k="party"><span>{{ $t($doc->partyLabel) }}</span><i>:</i><b><strong>{{ $party['name'] ?? '' }}</strong>@if (! empty($party['code'])) &middot; {{ $party['code'] }}@endif</b></div>
                    @for ($i = 0; $i < $doc->infoRows - 3; $i++)<div class="dl-pdoc__f is-addr"><span></span><i></i><b>{{ $party['lines'][$i] ?? '' }}</b></div>@endfor
                    {!! $field($doc->side) !!}
                </div>
            </div>

            <p class="dl-pdoc__nb">@if ($doc->nb !== '' && ! $doc->nbPlain)<b>NB:</b>&nbsp;@endif{{ $doc->nbRaw ? $doc->nb : $t($doc->nb) }}</p>

            <table class="dl-pdoc__items">
                <colgroup>
                    <col style="width:7mm"><col>
                    @if ($doc->qtyCols)<col style="width:21mm"><col style="width:15mm">@endif
                    @foreach ($cols as $c)<col style="width:{{ $c['width'] }}mm">@endforeach
                </colgroup>
                <thead><tr>
                    <th></th><th>{{ $t($doc->nameLabel) }}</th>
                    @if ($doc->qtyCols)<th class="is-num">{{ $t('Qty') }}</th><th>{{ $t('Unit') }}</th>@endif
                    @foreach ($cols as $c)<th class="{{ $doc->columnClass($c) }}">{{ $t($c['label']) }}</th>@endforeach
                </tr></thead>
                <tbody>
                    @foreach ($sheet['rows'] as $i => $r)
                        <tr @class([$r['cls'] ?? null])>
                            <td class="is-no">{{ $sheet['from'] + $i + 1 }}.</td>
                            @if ($r)
                                <td @class(['is-name', 'is-two' => ! empty($r['sub'])])><div class="dl-pdoc__n1">{{ $r['name'] ?? '' }}</div>@if (! empty($r['sub']))<div class="dl-pdoc__n2">{{ $r['sub'] }}</div>@endif @if (! empty($r['sub2']))<div class="dl-pdoc__n3">{{ $r['sub2'] }}</div>@endif</td>
                                @if ($doc->qtyCols)<td class="is-num">{{ $doc->qty($r['qty'] ?? null) }}</td><td>{{ $r['unit'] ?? '' }}</td>@endif
                                @foreach ($cols as $c)<td class="{{ $doc->columnClass($c) }}" data-pd-c="{{ $c['key'] }}">@if (! empty($c['wrap']))<div class="dl-pdoc__w">{!! $doc->value($c, $r, '') !!}</div>@else{!! $doc->value($c, $r, '') !!}@endif</td>@endforeach
                            @else
                                <td></td>@if ($doc->qtyCols)<td></td><td></td>@endif @foreach ($cols as $c)<td></td>@endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($sheet['last'])
                <div class="dl-pdoc__tail">
                    <div class="dl-pdoc__notes">@foreach ($doc->notes() as $n)<p><b>{{ $n[0] }}:</b> {{ $n[1] }}</p>@endforeach</div>
                    <div class="dl-pdoc__tot">@foreach ($totals['lines'] as $l)<div @class(['is-grand' => $l[2]])><span>{{ $l[0] }}</span><b>{{ $l[1] }}</b></div>@endforeach</div>
                </div>
                <div class="dl-pdoc__sign" style="grid-template-columns:repeat({{ count($sign) }},1fr)">
                    @foreach ($sign as $s)<div><span>{{ $s[0] }}</span><small>{{ $s[1] }}</small><i></i><b>(@if ($s[2])<u class="is-name">{{ $s[2] }}</u>@else<u></u>@endif)</b></div>@endforeach
                </div>
            @endif

            <footer class="dl-pdoc__foot"><span>{{ $doc->printedLine() }}</span><span>{{ $t('Sheet') }} {{ $sheet['page'] }}/{{ $sheet['pages'] }}</span></footer>
        </section>
    @endforeach
</div>
