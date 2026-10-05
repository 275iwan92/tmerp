{{-- Executive Summary devlib.test (user 2026-10-05): sembilan halaman, satu berkas. Angka = build.json (tmerp), daftar hasil
     kerja + rating = database devlib (App\Support\Summary). Disusun dari objek x-dl::*. --}}
@extends('layout', ['title' => $label, 'dev' => true, 'bodyClass' => 'dl-page-body'])
@php
    $t = $build['totals'] ?? [];
    $n = fn ($v, $d = 0) => $v === null ? '-' : \Devlib\Locale::number((float) $v, $d);
    $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100, 1) : 0;
    $agentName = \App\Support\Summary::AGENTS;
@endphp
@section('body')
<div class="dl-sum">
    <header class="dl-sum__head"><b>{{ $label }}</b><small>{{ $question }} · {{ \Devlib\Lang::text('Snapshot') }} {{ isset($build['generated']) ? \Devlib\Locale::dateTime($build['generated']) : '-' }}</small></header>

    @switch($page)
        @case('overview')
            <div class="dl-sum__tiles">
                <x-dl::stat-box label="Built on v2" :value="\Devlib\Locale::percent($pct($t['v2'], $t['screens']), 0)" :sub="\Devlib\Lang::raw($n($t['v2']).' / '.$n($t['screens']).' screens')" />
                <x-dl::stat-box label="Locked after QC" :value="$n($t['locked'])" tone="success" :sub="\Devlib\Lang::raw($n($t['waiting_qc']).' waiting for QC')" />
                <x-dl::stat-box label="Rated" :value="$n($ratings['rated']).' / '.$n($ratings['total'])" tone="warn" :sub="\Devlib\Lang::raw($ratings['avg'] === null ? 'no rating yet' : 'average '.$n($ratings['avg'], 1).' of 5')" />
                <x-dl::stat-box label="Commits, last 7 days" :value="$n($pace)" :sub="\Devlib\Lang::raw($n($pace / 7, 0).' per day')" />
                <x-dl::stat-box label="DevLib objects" :value="$n($t['objects'])" tone="neutral" :sub="\Devlib\Lang::raw($n($t['engines']).' engines')" />
                <x-dl::stat-box label="Open work" :value="$n(count($open))" tone="danger" :sub="\Devlib\Lang::raw('see Open Work')" />
            </div>
            <div class="dl-sum__note"><b>{{ \Devlib\Lang::text('Analyst note') }}.</b>
                {{ \Devlib\Locale::percent($pct($t['v2'], $t['screens']), 0) }} of the screens are built on v2, {{ $n($t['locked']) }} locked by the user after QC, {{ $n($t['waiting_qc']) }} still wait for review.
                @if ($t['waiting_qc'] > $t['locked'] * 5) The project is now limited by review, not by building speed: at 10 screens reviewed per working day the waiting list needs about {{ $n(ceil($t['waiting_qc'] / 10)) }} working days. @endif
                @if ($ratings['low']) {{ count($ratings['low']) }} item(s) are rated 3 stars or less and should be fixed first. @endif
            </div>
            <x-dl::build-board :build="$build" :library="\App\Support\Catalog::stats()" :engines-url="route('lib', ['slug' => 'p9-engines'])" source="resources/data/v2/build.json" />
            @break

        @case('module')
            <x-dl::card title="Modules">
                <x-dl::table :striped="false"
                    :columns="['label' => 'Module', 'state' => 'State', 'screens' => ['Screens on v2', 'num' => true, 'raw' => true], 'pct' => ['Built', 'num' => true, 'raw' => true], 'form' => ['Form', 'num' => true], 'grid' => ['Grid', 'num' => true], 'report' => ['Report', 'num' => true], 'locked' => ['Locked', 'num' => true], 'waiting_qc' => ['Waiting QC', 'num' => true], 'rated' => ['Rated', 'num' => true], 'avg' => ['Rating', 'num' => true, 'raw' => true], 'missing' => 'Missing']"
                    :rows="$modules" />
            </x-dl::card>
            @break

        @case('object')
            <x-dl::rate-list :items="$items" :types="\App\Support\Summary::TYPES" :qc="\App\Support\Summary::QC" :agents="$agentName" :action="route('summary.rate')" :csrf="csrf_token()" :can-rate="$canRate" source="devlib.dev_item + dev_rating" />
            @break

        @case('engine')
            <x-dl::card title="Engines">
                <x-dl::table :striped="false" :columns="['name' => 'Engine', 'group' => 'Area', 'mode' => 'Kind', 'used' => ['Used by', 'num' => true], 'tests' => ['Tests', 'num' => true], 'health' => 'Health', 'purpose' => 'Purpose']" :rows="$engines" />
            </x-dl::card>
            <x-dl::rate-list :items="$items" :types="\App\Support\Summary::TYPES" :qc="\App\Support\Summary::QC" :agents="$agentName" :action="route('summary.rate')" :csrf="csrf_token()" :can-rate="$canRate" only="ENGINE" source="devlib.dev_item + dev_rating" />
            @break

        @case('feature')
            <x-dl::rate-list :items="$items" :types="\App\Support\Summary::TYPES" :qc="\App\Support\Summary::QC" :agents="$agentName" :action="route('summary.rate')" :csrf="csrf_token()" :can-rate="$canRate" only="FEATURE" source="devlib.dev_item + dev_rating" />
            @break

        @case('agent')
            <x-dl::card title="Agent Iwan and Agent Jo" description="Volume and quality are shown separately: volume without rating says nothing about satisfaction.">
                <x-dl::table :striped="false" :columns="['measure' => 'Measure', 'iwan' => ['Agent Iwan', 'num' => true, 'raw' => true], 'jo' => ['Agent Jo', 'num' => true, 'raw' => true], 'other' => ['Before tracking', 'num' => true, 'raw' => true]]" :rows="$agentRows" />
            </x-dl::card>
            <x-dl::alert tone="info" message="Agent tracking started 05-Oct-2026 (commit trailer Agent: Iwan / Agent: Jo). Older work cannot be split between the two agents and is counted as Before tracking." />
            @break

        @case('time')
            <x-dl::card title="Commits per week" description="whole history">
                <x-slot:actions><span class="dl-bb dl-bb__legend">@foreach ($agentName as $k => $name)<i class="is-{{ $k }}"></i>{{ $name }} @endforeach<i class="is-other"></i>{{ \Devlib\Lang::text('Before tracking') }}</span></x-slot:actions>
                @php($max = max(1, ...array_map(fn ($w) => $w['iwan'] + $w['jo'] + $w['other'], $build['weeks'] ?? [['iwan' => 0, 'jo' => 0, 'other' => 0]])))
                <div class="dl-bb dl-sum__weeks">
                    @foreach ($build['weeks'] ?? [] as $w)
                        <div class="dl-bb__day" title="{{ $w['week'] }}: {{ $w['iwan'] + $w['jo'] + $w['other'] }}">
                            <span class="dl-bb__stack">@foreach (['other', 'jo', 'iwan'] as $k)@if ($w[$k] > 0)<i class="is-{{ $k }}" style="height:{{ round($w[$k] / $max * 150, 1) }}px"></i>@endif @endforeach</span>
                            <small>{{ substr($w['week'], 5) }}</small>
                        </div>
                    @endforeach
                </div>
            </x-dl::card>
            <x-dl::card title="Per week">
                <x-dl::table :columns="['week' => 'Week', 'iwan' => ['Agent Iwan', 'num' => true], 'jo' => ['Agent Jo', 'num' => true], 'other' => ['Before tracking', 'num' => true], 'total' => ['Total', 'num' => true], 'built' => ['Items first built', 'num' => true]]" :rows="$weekRows" />
            </x-dl::card>
            @break

        @case('quality')
            <div class="dl-sum__tiles">
                <x-dl::stat-box label="Average rating" :value="$ratings['avg'] === null ? '-' : $n($ratings['avg'], 1)" tone="warn" :sub="\Devlib\Lang::raw('of 5')" />
                <x-dl::stat-box label="Rated" :value="$n($ratings['rated'])" :sub="\Devlib\Lang::raw('of '.$n($ratings['total']).' items')" />
                <x-dl::stat-box label="3 stars or less" :value="$n(count($ratings['low']))" tone="danger" :sub="\Devlib\Lang::raw('fix first')" />
                <x-dl::stat-box label="Locked after QC" :value="$n($t['locked'])" tone="success" :sub="\Devlib\Lang::raw($n($t['waiting_qc']).' waiting')" />
                <x-dl::stat-box label="Test files" :value="$n($t['tests'])" tone="neutral" />
            </div>
            <div class="dl-sum__cols">
                <x-dl::card title="Stars" description="latest rating of each item">
                    <div class="dl-sum__dist">
                        @foreach ($ratings['dist'] as $star => $count)
                            <div><span>{{ $star }} ★</span><i><u style="width:{{ $pct($count, max(1, $ratings['rated'])) }}%"></u></i><b>{{ $n($count) }}</b></div>
                        @endforeach
                    </div>
                </x-dl::card>
                <x-dl::card title="By type">
                    <x-dl::table :striped="false" :columns="['type' => 'Type', 'items' => ['Items', 'num' => true], 'rated' => ['Rated', 'num' => true], 'avg' => ['Average', 'num' => true, 'raw' => true], 'low' => ['3 or less', 'num' => true]]"
                        :rows="array_map(fn ($r) => ['avg' => $r['avg'] === null ? '-' : $n($r['avg'], 1)] + $r, $ratings['types'])" />
                </x-dl::card>
            </div>
            <x-dl::card title="Rated 3 stars or less" description="fix these first">
                <x-dl::table empty="Nothing rated 3 stars or less." :columns="['module' => 'Module', 'name' => 'Name', 'agent' => 'Agent', 'rating' => ['Rating', 'num' => true], 'note' => 'Note', 'rated_by' => 'Rated by']"
                    :rows="array_map(fn ($i) => ['agent' => $agentName[$i['changed_by'] ?? $i['built_by'] ?? ''] ?? 'Before tracking'] + $i, $ratings['low'])" />
            </x-dl::card>
            @break

        @case('open')
            <x-dl::card title="Open work" description="computed from the menu, the rating list and the notes in BuildRegistry">
                <x-dl::table empty="Nothing open." :columns="['kind' => 'Kind', 'text' => 'What', 'count' => ['Count', 'num' => true]]" :rows="$open" />
            </x-dl::card>
            <x-dl::card title="Screens not built or still v1">
                <x-dl::table empty="Every screen is on v2." :columns="['module' => 'Module', 'type' => 'Type', 'name' => 'Name', 'state' => 'State']"
                    :rows="array_map(fn ($i) => ['type' => \App\Support\Summary::TYPES[$i['item_type']] ?? $i['item_type'], 'state' => \App\Support\Summary::QC[$i['qc_status']][0] ?? $i['qc_status']] + $i, array_values(array_filter($items, fn ($i) => in_array($i['qc_status'], ['NOT_BUILT', 'V1'], true))))" />
            </x-dl::card>
            @break
    @endswitch
</div>
@endsection
