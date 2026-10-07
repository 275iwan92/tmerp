{{-- Agent Log devlib.test (user 2026-10-07): log pekerjaan agen Iwan dan Jo (satu baris per commit), disaring per agen / bulan.
     Aturan = App\Support\AgentLog. --}}
@extends('layout', ['title' => 'Agent Log', 'dev' => true, 'bodyClass' => 'dl-page-body'])
@php
    use App\Support\AgentLog;
    use Devlib\Lang;
@endphp
@section('body')
<div class="dl-sum">
    <header class="dl-sum__head"><b>{{ Lang::text('Agent Log') }}</b><small>{{ Lang::text('Every job saved by Agent Iwan and Agent Jo, one row per commit. Filter by agent or month.') }}</small></header>
    <x-dl::grid id="agent-log" :rows="AgentLog::rows()" search="Search job, commit..." actions="worklist" :active-key="null"
        :columns="[
            ['key' => 'job', 'label' => 'Job', 'type' => 'doc', 'party' => 'agent'],
            ['key' => 'agent', 'label' => 'Agent', 'type' => 'badge', 'tones' => AgentLog::TONES],
            ['key' => 'commit', 'label' => 'Commit'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
        ]"
        :filters="['agent' => 'Agent', 'month' => 'Month']" />
</div>
@endsection
