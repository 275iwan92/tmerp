{{-- Setup > Migration (v2). Runner & laporan = App\Support\V2\Data\MigrationDesk (App\Services\Migration\*). Satu kartu per runner; aman diulang.
     Administrator saja. Laporan hasil hanya tampil sekali setelah runner dijalankan. --}}
@php
    use App\Support\V2\Data\MigrationDesk;
    use App\Support\V2\Src;

    $done = session('migration_report');
    $groups = collect(MigrationDesk::runners())->groupBy('group');
@endphp

@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

<x-dl::alert tone="info" message="Pull the old data (ScriptCase V1) from the staging database tm2old into the V2 masters and archives. Safe to run again and again — existing data is updated, not duplicated." />

@foreach ($groups as $group => $runners)
    <x-dl::card :title="$group">
        @foreach ($runners as $key => $r)
            <div id="{{ $key }}">
                <x-dl::field.display :label="raw($r['title'])" :value="$r['note']" :source="Src::of('tm2old')" />
                <x-dl::action-form action="{{ route('v2.migration.run', $key) }}">
                    <x-dl::button icon="fa-play" label="Run Migration" data-dl-confirm="{{ t('Run this migration now?') }}" />
                </x-dl::action-form>
                @if ($done && $done['key'] === $key)
                    <x-dl::alert tone="success" message="Migration finished." />
                    <x-dl::table :columns="['part' => 'Part', 'result' => 'Result']" :rows="MigrationDesk::rows(collect($done['report'])->except('sample')->all())" :source="Src::of('tm2old')" />
                    @if ($key === 'login' && ! empty($done['report']['sample']))
                        <x-dl::table :columns="['login' => 'Username', 'name' => 'Name', 'password' => 'Password']" :rows="array_map(fn ($s) => ['login' => raw($s['login']), 'name' => raw($s['name']), 'password' => raw($s['password'])], $done['report']['sample'])" :source="Src::of('tm2sys.sec_user')" />
                    @endif
                @endif
            </div>
        @endforeach
    </x-dl::card>
@endforeach
