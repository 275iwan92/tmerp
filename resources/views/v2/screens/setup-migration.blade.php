{{--
 REMARK
 Tujuan       : Setup > Migration: menjalankan runner tarik data lama (ScriptCase V1, tm2old) ke master dan arsip V2; aman diulang.
 Sumber hulu  : App\Support\V2\Data\MigrationDesk (App\Services\Migration\*), staging tm2old.
 Input        : Session migration_report (laporan hasil sekali tampil); runner dikelompokkan per group.
 Output       : x-dl::card per grup, x-dl::action-form tombol Run Migration ke route v2.migration.run, x-dl::table laporan hasil.
 Dipakai oleh : route v2.screen (routes/v2.php), slug setup-migration (menu Setup > Dummy Data & Migration).
 Status       : Aktif; hanya Administrator.
--}}
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
            <x-dl::box id="{{ $key }}">
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
            </x-dl::box>
        @endforeach
    </x-dl::card>
@endforeach
